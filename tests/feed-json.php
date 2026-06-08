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
function apply_filters( $h, $v ) { return $v; }
function get_bloginfo( $k ) { return 'Signal & Noise'; }
function home_url( $p = '' ) { return 'https://x.test' . $p; }
function get_option( $k ) { return 'UTF-8'; }
if ( ! function_exists( 'wp_json_encode' ) ) { function wp_json_encode( $v, $flags = 0, $depth = 512 ) { return json_encode( $v, $flags, $depth ); } }

require __DIR__ . '/../inc/feed-json.php';

// --- Behavioral assertions on the pure builder ---
$post = (object) array( 'ID' => 7, 'post_content' => '<p>Body & stuff</p>' );
$item = sn_feed_json_build_item( $post );
ok( is_string( $item['id'] ) && $item['id'] !== '', 'item id is a non-empty string (stable permalink)' );
ok( isset( $item['content_html'] ) && $item['content_html'] !== '', 'content_html present + non-empty (required field)' );
ok( preg_match( '/^\d{4}-\d{2}-\d{2}T/', $item['date_published'] ) === 1, 'date_published is RFC 3339 shape' );
ok( in_array( 'analysis', $item['tags'], true ), 'tags carries category names' );

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

echo "Result: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
