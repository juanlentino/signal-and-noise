<?php
/**
 * Standalone tests for the content-as-data document builder (sub-project C).
 * @since theme v10.38.0
 */
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', '/' ); }
define( 'SN_CONTENT_JSON_TEST', true );

// --- WP stubs ---
if ( ! function_exists( 'home_url' ) ) { function home_url( $p = '' ) { return 'https://juanlentino.com' . $p; } }
if ( ! function_exists( 'apply_filters' ) ) { function apply_filters( $h, $v ) { return $v; } } // the_content → identity
if ( ! function_exists( 'wp_strip_all_tags' ) ) { function wp_strip_all_tags( $s ) { return trim( preg_replace( '/<[^>]*>/', '', (string) $s ) ); } }
$GLOBALS['__posts'] = array();
function sn_test_post( $id, $args ) { $p = (object) array_merge( array( 'ID' => $id, 'post_type' => 'post', 'post_status' => 'publish', 'post_content' => '', 'post_parent' => 0, 'post_title' => '', 'post_author' => 1 ), $args ); $GLOBALS['__posts'][ $id ] = $p; return $p; }
if ( ! function_exists( 'get_post_type' ) ) { function get_post_type( $p ) { return is_object( $p ) ? $p->post_type : ( $GLOBALS['__posts'][ $p ]->post_type ?? 'post' ); } }
if ( ! function_exists( 'get_the_title' ) ) { function get_the_title( $p ) { $o = is_object( $p ) ? $p : ( $GLOBALS['__posts'][ $p ] ?? null ); return $o ? $o->post_title : ''; } }
if ( ! function_exists( 'get_permalink' ) ) { function get_permalink( $p ) { $id = is_object( $p ) ? $p->ID : $p; $o = is_object( $p ) ? $p : ( $GLOBALS['__posts'][ $p ] ?? null ); $slug = $o->post_type === 'page' ? 'about' : 'notes/some-note'; return 'https://juanlentino.com/' . ( $id === 99 ? 'about/uses' : $slug ) . '/'; } }
if ( ! function_exists( 'get_the_date' ) ) { function get_the_date( $f, $p ) { return '2026-07-01T14:32:00-04:00'; } }
if ( ! function_exists( 'get_the_modified_date' ) ) { function get_the_modified_date( $f, $p ) { return '2026-07-05T09:10:00-04:00'; } }
if ( ! function_exists( 'get_post_ancestors' ) ) { function get_post_ancestors( $p ) { $o = is_object( $p ) ? $p : ( $GLOBALS['__posts'][ $p ] ?? null ); return ( $o && $o->post_parent ) ? array( $o->post_parent ) : array(); } }

require __DIR__ . '/../inc/content-json-document.php';

$pass = 0; $fail = 0;
function ok( $c, $m ) { global $pass, $fail; if ( $c ) { $pass++; echo "PASS: $m\n"; } else { $fail++; echo "FAIL: $m\n"; } }
echo "content-json document — v10.38.0\n\n";

// A Note
$note = sn_test_post( 1, array( 'post_type' => 'post', 'post_title' => 'Some Note', 'post_content' => '<p>Hello <b>world</b>.</p>' ) );
$d = sn_content_json_document( $note );
ok( $d['type'] === 'note', 'a post → type note' );
ok( $d['title'] === 'Some Note', 'title present' );
ok( $d['content_html'] === '<p>Hello <b>world</b>.</p>', 'content_html is the rendered body' );
ok( $d['content_text'] === 'Hello world.', 'content_text is tag-stripped + collapsed' );
ok( $d['date_published'] === '2026-07-01T14:32:00-04:00', 'ISO date_published' );
ok( ( $d['schema']['type'] ?? '' ) === 'Article', 'schema type Article for a Note' );
ok( isset( $d['provenance']['verify_url'] ), 'provenance reference present for a Note' );
ok( $d['breadcrumb'][0]['name'] === 'Home' && $d['breadcrumb'][1]['name'] === 'Notes' && end( $d['breadcrumb'] )['name'] === 'Some Note', 'Note breadcrumb: Home → Notes → self' );

// A Page with an ancestor (e.g. /about/uses under /about)
$page = sn_test_post( 99, array( 'post_type' => 'page', 'post_title' => 'Uses', 'post_parent' => 5, 'post_content' => '<p>Gear.</p>' ) );
sn_test_post( 5, array( 'post_type' => 'page', 'post_title' => 'About' ) );
$dp = sn_content_json_document( $page );
ok( $dp['type'] === 'page', 'a page → type page' );
ok( ( $dp['schema']['type'] ?? '' ) === 'WebPage', 'schema type WebPage for a Page' );
ok( ! isset( $dp['provenance'] ), 'no provenance key for a Page' );
$names = array_map( function( $c ) { return $c['name']; }, $dp['breadcrumb'] );
ok( $names === array( 'Home', 'About', 'Uses' ), 'Page breadcrumb walks ancestors: Home → About → Uses' );

// valid JSON
ok( json_decode( json_encode( $d ), true ) !== null, 'document round-trips through JSON' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
