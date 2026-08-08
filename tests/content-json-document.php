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
$GLOBALS['__meta'] = array();
if ( ! function_exists( 'get_post_meta' ) ) { function get_post_meta( $id, $key, $single = false ) { return $GLOBALS['__meta'][ $id ][ $key ] ?? ''; } }

require __DIR__ . '/../inc/note-uid.php'; // v10.49.0: canonical uid read the module now calls
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

// Provenance uid republication (v10.45.0): the /verify page resolves a pasted
// Note URL by probing the twin's provenance.note_uid — without it, paste-a-URL
// could never work (caught live 2026-07-21). The plugin owns _sn_prov_uid;
// the twin republishes it and points verify_url at the per-note docket.
$GLOBALS['__meta'][1] = array( '_sn_prov_uid' => 'DEADBEEF-dead-4eef-8eef-deadbeefdead' );
$dv = sn_content_json_document( $note );
ok( ( $dv['provenance']['note_uid'] ?? '' ) === 'deadbeef-dead-4eef-8eef-deadbeefdead', 'twin republishes the Note uid (lowercased) when the plugin meta exists' );
ok( ( $dv['provenance']['verify_url'] ?? '' ) === 'https://juanlentino.com/verify?note=deadbeef-dead-4eef-8eef-deadbeefdead', 'verify_url points at the per-note /verify docket when a uid exists' );
// v10.49.0: the twin now reads via sn_theme_note_uid(), which TRIMS — this
// call site previously lowercased without trimming, so a uid stored with
// stray whitespace republished whitespace into the value /verify matches on.
$GLOBALS['__meta'][1] = array( '_sn_prov_uid' => "  DEADBEEF-dead-4eef-8eef-deadbeefdead\n" );
$dt = sn_content_json_document( $note );
ok( ( $dt['provenance']['note_uid'] ?? '' ) === 'deadbeef-dead-4eef-8eef-deadbeefdead', 'twin trims stray whitespace off the uid (v10.49.0 shared-helper normalization)' );
$GLOBALS['__meta'] = array();
$dn = sn_content_json_document( $note );
ok( ! isset( $dn['provenance']['note_uid'] ), 'no fabricated note_uid when the meta is absent' );
// v11.5.1: this line USED TO PIN 'https://juanlentino.com/provenance/verify/' —
// a URL that returns 404 live (confirmed 2026-08-08, redirects followed). The test
// did not merely miss the defect, it asserted the defect was CORRECT, which is how
// a uid-less Note kept publishing a dead link in its machine-readable twin.
//
// Rewritten as a RELATIONSHIP: whatever a uid-less Note advertises must share the
// same base path as the uid-ful docket URL, so the fallback and the real thing can
// only drift together. A literal merely re-freezes today's string — the mistake
// this very line already made once.
$uidful_path   = (string) parse_url( (string) ( $dt['provenance']['verify_url'] ?? '' ), PHP_URL_PATH );
$fallback_path = (string) parse_url( (string) ( $dn['provenance']['verify_url'] ?? '' ), PHP_URL_PATH );
ok( '' !== $fallback_path, 'a uid-less Note still advertises a verify_url' );
ok( rtrim( $fallback_path, '/' ) === rtrim( $uidful_path, '/' ), 'the uid-less fallback points at the SAME docket as the uid-ful URL (never the 404 /provenance/verify)' );
ok( false === strpos( $fallback_path, '/provenance/verify' ), 'the fallback is never the unrelated /provenance/verify Page' );

// valid JSON
ok( json_decode( json_encode( $d ), true ) !== null, 'document round-trips through JSON' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
