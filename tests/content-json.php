<?php
/**
 * Standalone tests for the content-as-data route (sub-project C): the .json
 * suffix matcher, the resolution gate, serve (status 200), the head link, the
 * manifest advertisement, and the purge callback.
 * @since theme v10.38.0
 */
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', '/' ); }
define( 'SN_CONTENT_JSON_TEST', true );

ob_start(); // buffer so the real header() in send() does not warn after PASS lines

$GLOBALS['__status'] = 0;
if ( ! function_exists( 'home_url' ) ) { function home_url( $p = '' ) { return 'https://juanlentino.com' . $p; } }
if ( ! function_exists( 'status_header' ) ) { function status_header( $c ) { $GLOBALS['__status'] = (int) $c; } }
if ( ! function_exists( 'esc_url' ) ) { function esc_url( $u ) { return str_replace( array( '"', '<', '>' ), '', (string) $u ); } }
if ( ! function_exists( 'apply_filters' ) ) { function apply_filters( $h, $v ) { return $v; } }
if ( ! function_exists( 'wp_strip_all_tags' ) ) { function wp_strip_all_tags( $s ) { return trim( preg_replace( '/<[^>]*>/', '', (string) $s ) ); } }
if ( ! function_exists( 'get_post_type' ) ) { function get_post_type( $p ) { return is_object( $p ) ? $p->post_type : 'post'; } }
if ( ! function_exists( 'get_the_title' ) ) { function get_the_title( $p ) { return is_object( $p ) ? $p->post_title : ''; } }
if ( ! function_exists( 'get_permalink' ) ) { function get_permalink( $p = 0 ) { $id = is_object( $p ) ? $p->ID : (int) $p; return 'https://juanlentino.com/notes/some-note/'; } }
if ( ! function_exists( 'get_the_date' ) ) { function get_the_date( $f, $p ) { return '2026-07-01T00:00:00-04:00'; } }
if ( ! function_exists( 'get_the_modified_date' ) ) { function get_the_modified_date( $f, $p ) { return '2026-07-01T00:00:00-04:00'; } }
if ( ! function_exists( 'get_post_ancestors' ) ) { function get_post_ancestors( $p ) { return array(); } }
$GLOBALS['__urlmap'] = array( 'https://juanlentino.com/notes/some-note/' => 7 );
if ( ! function_exists( 'url_to_postid' ) ) { function url_to_postid( $u ) { return $GLOBALS['__urlmap'][ $u ] ?? 0; } }
$GLOBALS['__postobjs'] = array( 7 => (object) array( 'ID' => 7, 'post_type' => 'post', 'post_status' => 'publish', 'post_content' => '<p>Hi.</p>', 'post_title' => 'Some Note', 'post_parent' => 0 ) );
if ( ! function_exists( 'get_post' ) ) { function get_post( $id ) { return $GLOBALS['__postobjs'][ $id ] ?? null; } }
if ( ! function_exists( 'setup_postdata' ) ) { function setup_postdata( $p ) { return true; } }
if ( ! function_exists( 'wp_reset_postdata' ) ) { function wp_reset_postdata() { return true; } }
if ( ! function_exists( 'wp_json_encode' ) ) { function wp_json_encode( $d, $f = 0 ) { return json_encode( $d, $f ); } }
if ( ! function_exists( 'add_action' ) ) { function add_action() { return true; } }
if ( ! function_exists( 'add_filter' ) ) { function add_filter() { return true; } }

require __DIR__ . '/../inc/content-json-document.php';
require __DIR__ . '/../inc/content-json.php';

$pass = 0; $fail = 0;
function ok( $c, $m ) { global $pass, $fail; if ( $c ) { $pass++; echo "PASS: $m\n"; } else { $fail++; echo "FAIL: $m\n"; } }
echo "content-json route — v10.38.0\n\n";

// --- base path matcher (pure) ---
ok( sn_content_json_base_path( '/notes/some-note.json' ) === '/notes/some-note', 'matches + strips .json' );
ok( sn_content_json_base_path( '/about.json?x=1' ) === '/about', 'strips query string' );
ok( sn_content_json_base_path( '/notes/some-note/' ) === '', 'rejects a non-.json path' );
ok( sn_content_json_base_path( '/foo.json.bak' ) === '', 'rejects a near-miss suffix' );
ok( sn_content_json_base_path( '/.json' ) === '', 'rejects an empty base' );

// --- resolution gate ---
ok( sn_content_json_resolve( '/notes/some-note.json' ) === 7, 'resolves a published singular post' );
ok( sn_content_json_resolve( '/notes/some-note/' ) === 0, 'non-.json → 0' );
$GLOBALS['__postobjs'][7]->post_status = 'draft';
ok( sn_content_json_resolve( '/notes/some-note.json' ) === 0, 'draft post → 0 (not served)' );
$GLOBALS['__postobjs'][7]->post_status = 'publish';

// --- send sets 200 + emits valid JSON ---
ob_start(); sn_content_json_send( get_post( 7 ) ); $out = ob_get_clean();
ok( $GLOBALS['__status'] === 200, 'send() calls status_header(200)' );
ok( json_decode( $out, true )['type'] === 'note', 'send() emits the JSON document' );

// --- head link (singular gate handled by caller; test the markup) ---
if ( ! function_exists( 'is_singular' ) ) { function is_singular( $t = '' ) { return true; } }
ob_start(); sn_content_json_head_link(); $link = ob_get_clean();
ok( strpos( $link, 'type="application/json"' ) !== false && strpos( $link, '/notes/some-note.json' ) !== false, 'head link points at the .json twin (no trailing slash)' );

// --- manifest advertisement ---
$s = sn_content_json_advertise_surface( array() );
ok( count( $s ) === 1 && $s[0]['type'] === 'content-json', 'advertises a content-json surface' );

// --- purge callback appends the twin ---
$urls = sn_content_json_purge_url( array( 'https://juanlentino.com/notes/some-note/' ), 7, get_post( 7 ) );
ok( in_array( 'https://juanlentino.com/notes/some-note.json', $urls, true ), 'purge callback appends the .json twin' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
