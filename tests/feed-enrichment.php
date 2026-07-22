<?php
/**
 * Standalone fixture tests for RSS item enrichment (v9.11.0).
 *
 * Stubs the WP primitives the enrichment callbacks touch (escaping helpers,
 * featured-image lookup, the current loop ID) plus a togglable plugin-owned
 * sn_get_reading_time(), so the named functions in inc/feed-enrichment.php run
 * without a WordPress load. Mirrors tests/related-notes.php.
 *
 * @since theme v9.11.0
 */

if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', '/' ); }
define( 'SN_FEED_ENRICH_TEST', true );
$pass = 0; $fail = 0;
function ok( $c, $m ) { global $pass, $fail; if ( $c ) { $pass++; echo "PASS: $m\n"; } else { $fail++; echo "FAIL: $m\n"; } }

function add_action( $h, $cb, $p = 10, $a = 1 ) { return true; }
function add_filter( $h, $cb, $p = 10, $a = 1 ) { return true; }
function esc_url( $u ) { return $u; }
// Real escaping semantics, not a passthrough, so the noteUid escaping assertions
// below can catch an unescaped sink.
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function get_post_meta( $id, $key, $single = false ) { return $GLOBALS['__pm'][ (int) $id ][ $key ] ?? ''; }
$GLOBALS['__thumb'] = 'https://x.test/og/7.png';
function get_post_thumbnail_id( $id ) { return $GLOBALS['__has_thumb'] ? 99 : 0; }
function wp_get_attachment_image_url( $aid, $size ) { return $GLOBALS['__thumb']; }
function get_the_ID() { return 7; }

require __DIR__ . '/../inc/feed-enrichment.php';

// Namespace declaration.
ob_start(); sn_rss_media_ns(); $ns = ob_get_clean();
ok( strpos( $ns, 'xmlns:media="http://search.yahoo.com/mrss/"' ) !== false, 'rss2_ns emits the Media RSS namespace' );
// RSS-1: the sn: prefix MUST be declared or every <sn:readingTimeMinutes> below
// is an undeclared-prefix XML well-formedness error that breaks the whole feed.
ok( strpos( $ns, 'xmlns:sn="https://juanlentino.com/ns/feed"' ) !== false, 'rss2_ns declares the sn: namespace backing readingTimeMinutes (RSS-1)' );

// Item enrichment WITH a featured image + plugin reading-time.
$GLOBALS['__has_thumb'] = true;
function sn_get_reading_time( $id = null ) { return 6; }
ob_start(); sn_rss_item_enrich(); $item = ob_get_clean();
ok( strpos( $item, '<media:content' ) !== false && strpos( $item, $GLOBALS['__thumb'] ) !== false, 'rss2_item emits media:content for the featured image' );
// RSS-2: assert the FULL well-formed, paired element — not just the digit '6'
// appearing anywhere (which a malformed/unclosed element would also satisfy).
ok( strpos( $item, '<sn:readingTimeMinutes>6</sn:readingTimeMinutes>' ) !== false, 'rss2_item emits a well-formed <sn:readingTimeMinutes> element (RSS-2)' );

// Degrade: no featured image → no media:content (no fatal, no empty tag).
$GLOBALS['__has_thumb'] = false;
ob_start(); sn_rss_item_enrich(); $item2 = ob_get_clean();
ok( strpos( $item2, '<media:content' ) === false, 'no media:content when there is no featured image' );

// v10.48.0: <sn:noteUid> mirrors the plugin-owned _sn_prov_uid under the
// already-declared sn: namespace, so RSS subscribers can verify without a
// second fetch. Absent uid → NO element (item2 above had no uid meta).
ok( strpos( $item2, '<sn:noteUid>' ) === false, 'no <sn:noteUid> when the post carries no _sn_prov_uid meta' );
$GLOBALS['__pm'][7]['_sn_prov_uid'] = 'AB12cd34';
ob_start(); sn_rss_item_enrich(); $item3 = ob_get_clean();
ok( strpos( $item3, '<sn:noteUid>ab12cd34</sn:noteUid>' ) !== false,
	'rss2_item emits a well-formed, paired <sn:noteUid> with the lowercased uid' );
// Escaping at the sink: a hostile uid value must not break the XML open.
$GLOBALS['__pm'][7]['_sn_prov_uid'] = 'x<y&z';
ob_start(); sn_rss_item_enrich(); $item4 = ob_get_clean();
ok( strpos( $item4, '<sn:noteUid>x&lt;y&amp;z</sn:noteUid>' ) !== false && strpos( $item4, '<sn:noteUid>x<y' ) === false,
	'noteUid value is escaped at the sink (esc_html), never raw' );
unset( $GLOBALS['__pm'] );

echo "Result: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
