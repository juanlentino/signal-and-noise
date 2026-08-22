<?php
// SECURITY: CLI-only fixture.
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', '/' ); }
define( 'SN_FEED_JSON_TEST', true ); // suppress add_feed/add_filter wiring on require

$pass = 0; $fail = 0;
function ok( $cond, $msg ) { global $pass, $fail; if ( $cond ) { $pass++; echo "PASS: $msg\n"; } else { $fail++; echo "FAIL: $msg\n"; } }

// --- Stub WP primitives the builder touches ---
function add_feed( $n, $cb ) { $GLOBALS['__feeds'][ $n ] = $cb; return "do_feed_$n"; }
function add_action( $h, $cb, $p = 10, $a = 1 ) { return true; }
function add_filter( $h, $cb, $p = 10, $a = 1 ) { return true; }
function get_permalink( $p = null ) { return 'https://x.test/notes/n-' . ( is_object( $p ) ? $p->ID : $p ) . '/'; }
function get_the_title( $p = null ) { return 'Note "' . ( is_object( $p ) ? $p->ID : $p ) . '" & co'; }
function get_post_time( $f, $gmt, $p ) { return $GLOBALS['__pubdate'] ?? '2026-06-07T12:00:00+00:00'; }
function get_post_modified_time( $f, $gmt, $p ) { return $GLOBALS['__moddate'] ?? '2026-06-07T13:00:00+00:00'; }
function get_the_category( $id ) { $c = new stdClass(); $c->name = 'analysis'; return array( $c ); }
function has_excerpt( $p ) { return false; }
function get_the_excerpt( $p ) { return ''; }
$GLOBALS['__filters'] = array();
function apply_filters( $h, $v ) { return array_key_exists( $h, $GLOBALS['__filters'] ?? array() ) ? $GLOBALS['__filters'][ $h ] : $v; }
function get_bloginfo( $k ) { return 'Signal & Noise'; }
function home_url( $p = '' ) { return 'https://x.test' . $p; }
function get_option( $k ) { return 'UTF-8'; }
function get_the_post_thumbnail_url( $p = null, $size = '' ) { return $GLOBALS['__thumb'] ?? ''; }
function get_site_icon_url( $s = 512 ) { return $GLOBALS['__icon'] ?? ''; }
function get_post_meta( $id, $key, $single = false ) { return $GLOBALS['__meta'][ (int) $id ][ $key ] ?? ''; }
// Plugin-owned reading time (inc/feed-enrichment.php precedent) — togglable.
function sn_get_reading_time( $id = null ) { return $GLOBALS['__rt'] ?? 0; }
if ( ! function_exists( 'wp_json_encode' ) ) { function wp_json_encode( $v, $flags = 0, $depth = 512 ) { return json_encode( $v, $flags, $depth ); } }

// esc_* seams for the <head> autodiscovery link, unpinned since v9.11.0.
if ( ! function_exists( 'esc_attr' ) ) { function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); } }
if ( ! function_exists( 'esc_url' ) ) { function esc_url( $s ) { return (string) $s; } }

require __DIR__ . '/../inc/note-uid.php'; // v10.49.0: canonical uid read the module now calls
require __DIR__ . '/../inc/feed-json.php';

// --- Behavioral assertions on the pure builder ---
$post = (object) array( 'ID' => 7, 'post_content' => '<p>Body & stuff</p>' );
$item = sn_feed_json_build_item( $post );
ok( is_string( $item['id'] ) && $item['id'] !== '', 'item id is a non-empty string (stable permalink)' );
ok( isset( $item['content_html'] ) && $item['content_html'] !== '', 'content_html present + non-empty (required field)' );
ok( preg_match( '/^\d{4}-\d{2}-\d{2}T/', $item['date_published'] ) === 1, 'date_published is RFC 3339 shape' );
ok( in_array( 'analysis', $item['tags'], true ), 'tags carries category names' );

// v10.13.0: per-item image from the featured thumbnail (omitted when absent).
ok( ! isset( $item['image'] ), 'item has no image when there is no featured thumbnail' );
$GLOBALS['__thumb'] = 'https://x.test/wp-content/uploads/card.jpg';
$with_img = sn_feed_json_build_item( (object) array( 'ID' => 9, 'post_content' => 'x' ) );
ok( ( $with_img['image'] ?? null ) === 'https://x.test/wp-content/uploads/card.jpg', 'item carries the featured-thumbnail image when present' );
unset( $GLOBALS['__thumb'] );

// v10.13.0: feed-level authors (JSON Feed 1.1) — name + /about url + site-icon avatar.
$authors = sn_feed_json_authors();
ok( is_array( $authors ) && count( $authors ) === 1, 'authors is a one-entry array' );
ok( ( $authors[0]['name'] ?? null ) === 'Signal & Noise', 'author name from site identity' );
ok( ( $authors[0]['url'] ?? null ) === 'https://x.test/about/', 'author url points at /about/' );
ok( ! isset( $authors[0]['avatar'] ), 'no avatar when the site icon is unset' );
$GLOBALS['__icon'] = 'https://x.test/wp-content/uploads/icon.png';
$authors2 = sn_feed_json_authors();
ok( ( $authors2[0]['avatar'] ?? null ) === 'https://x.test/wp-content/uploads/icon.png', 'avatar from the site icon when set' );
unset( $GLOBALS['__icon'] );

// Whole-feed shape + escaping discipline (JSON, not esc_html).
$feed = array(
	'version' => 'https://jsonfeed.org/version/1.1',
	'title'   => get_bloginfo( 'name' ),
	'items'   => array( $item ),
);
$json    = wp_json_encode( $feed, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
$decoded = json_decode( $json, true );
ok( $decoded['version'] === 'https://jsonfeed.org/version/1.1', 'feed round-trips with JSON Feed 1.1 version' );
ok( strpos( $json, '&amp;' ) === false, 'no HTML-entity mangling in JSON (title used esc-free path)' );
ok( strpos( $decoded['items'][0]['title'], '&' ) !== false, 'raw ampersand survives into decoded title' );

// JF-2: a false/zeroed post date must be OMITTED, never serialized as `false`.
$GLOBALS['__pubdate'] = false; $GLOBALS['__moddate'] = false;
$bad = sn_feed_json_build_item( (object) array( 'ID' => 8, 'post_content' => 'x' ) );
ok( ! isset( $bad['date_published'] ) && ! isset( $bad['date_modified'] ), 'non-string dates are omitted, not serialized as false (JF-2)' );
unset( $GLOBALS['__pubdate'], $GLOBALS['__moddate'] );

// v10.48.0: the _signal_noise extension (feed-level provenance). JSON Feed 1.1
// custom extensions MUST be underscore-prefixed — this one is. An item whose
// Note carries a plugin-owned _sn_prov_uid republishes it so feed subscribers
// can verify WITHOUT a second fetch; an item without a uid gets NO key at all.
$no_uid = sn_feed_json_build_item( (object) array( 'ID' => 11, 'post_content' => 'x' ) );
ok( ! array_key_exists( '_signal_noise', $no_uid ), 'item without a Note uid carries NO _signal_noise key at all' );
$GLOBALS['__meta'][12]['_sn_prov_uid'] = 'AB12cd34';
$GLOBALS['__rt'] = 6;
$ext_item = sn_feed_json_build_item( (object) array( 'ID' => 12, 'post_content' => 'x' ) );
$ext      = $ext_item['_signal_noise'] ?? null;
ok( is_array( $ext ), 'item with a Note uid carries the _signal_noise extension object' );
ok( 'ab12cd34' === ( $ext['note_uid'] ?? null ), 'note_uid republishes the uid lowercased (content-json-document precedent)' );
ok( 'https://x.test/verify?note=ab12cd34' === ( $ext['verify_url'] ?? null ), 'verify_url is the Note\'s own /verify docket URL' );
ok( 'https://x.test/notes/n-12.json' === ( $ext['json_url'] ?? null ), 'json_url is the .json content twin (permalink, trailing slash trimmed, + .json)' );
ok( 6 === ( $ext['reading_time_minutes'] ?? null ), 'reading_time_minutes rides the plugin-owned sn_get_reading_time()' );
$GLOBALS['__rt'] = 0;
$ext_no_rt = sn_feed_json_build_item( (object) array( 'ID' => 12, 'post_content' => 'x' ) );
ok( ! isset( $ext_no_rt['_signal_noise']['reading_time_minutes'] ), 'reading_time_minutes omitted when the plugin reports none (mirrors the RSS >= 1 gate)' );
ok( 'ab12cd34' === ( $ext_no_rt['_signal_noise']['note_uid'] ?? null ), 'the rest of the extension survives a missing reading time' );
unset( $GLOBALS['__meta'][12], $GLOBALS['__rt'] );

// v9.12.0: feed item count honors sn_json_feed_items (default 20).
$GLOBALS['__filters']['sn_json_feed_items'] = 5;
ok( (int) sn_feed_json_query_args()['posts_per_page'] === 5, 'json-feed: honors sn_json_feed_items=5' );
$GLOBALS['__filters'] = array();
ok( (int) sn_feed_json_query_args()['posts_per_page'] === 20, 'json-feed: default item count is 20' );

// v10.48.0: password-protected posts must never enter the JSON feed. The raw
// apply_filters( 'the_content', ... ) render bypasses post_password_required(),
// so the query excludes them outright and build_item() refuses them defensively
// (mirrors the content-json.php gate and the OG-card leak fix, plugin v9.25.2).
ok( false === ( sn_feed_json_query_args()['has_password'] ?? null ), 'json-feed: query excludes password-protected posts (has_password=false)' );
$protected = sn_feed_json_build_item( (object) array( 'ID' => 13, 'post_content' => 'secret body', 'post_password' => 'pw' ) );
ok( null === $protected, 'json-feed: build_item refuses a password-protected post outright' );

// --- v12.2.0: TWO URL forms, one registration (JF-1) ---
// The subscribe page teaches the pretty path because a human copies it; the
// <head> link and the feed's own feed_url keep the query form because that one
// resolves on a cold deploy, before the PLUGIN's next rewrite flush. Two forms
// is a deliberate split, so both derive from ONE slug accessor: renaming the
// registered feed moves both, and neither can be left pointing at nothing.
ok( 'json' === sn_feed_json_slug(), 'slug accessor returns the registered feed slug' );
ok( 'https://x.test/?feed=json' === sn_feed_json_url(), 'machine URL is the always-live query form (JF-1)' );
ok( 'https://x.test/feed/json/' === sn_feed_json_pretty_url(), 'human URL is the pretty path' );
ok( false !== strpos( sn_feed_json_url(), sn_feed_json_slug() ), 'machine URL is derived from the slug' );
ok( false !== strpos( sn_feed_json_pretty_url(), sn_feed_json_slug() ), 'human URL is derived from the slug' );

// Source-level: the registration and the content-type filter must READ the
// accessor, not repeat the literal. A literal here is how the two silently
// diverge from the URLs above.
$json_src = file_get_contents( __DIR__ . '/../inc/feed-json.php' );
ok( false !== strpos( $json_src, 'add_feed( sn_feed_json_slug()' ), 'add_feed() registers via the slug accessor, not a literal' );
ok( false !== strpos( $json_src, 'sn_feed_json_slug() === $feed' ), 'content_type filter compares against the slug accessor' );

// --- The <head> autodiscovery link: shipped v9.11.0, PINNED v12.2.0 ---
// It has been live on production this whole time with zero assertions on it.
ob_start(); sn_feed_json_head_link(); $head = ob_get_clean();
ok( false !== strpos( $head, 'rel="alternate"' ), 'head link is a rel=alternate' );
ok( false !== strpos( $head, 'type="application/feed+json"' ), 'head link declares the JSON Feed media type' );
ok( false !== strpos( $head, sn_feed_json_url() ), 'head link advertises the MACHINE url' );
ok( false === strpos( $head, sn_feed_json_pretty_url() ), 'head link does NOT advertise the pretty path (JF-1: it 404s on a cold deploy)' );

// The feed document's self-reference obeys the same rule.

echo "Result: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
