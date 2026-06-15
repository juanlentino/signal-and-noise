<?php
/**
 * Standalone fixture tests for the /index whole-site dossier (C3, v10.7.0).
 *
 * inc/page-index-template.php registers a postless /index virtual route
 * (template_redirect short-circuit + template_include fallback + document
 * title); inc/page-index-render.php emits a full HTML document aggregating
 * the site — Notes (post_type=post), Pages (post_type=page), and the
 * discography (sn_discography_entries filter) — reusing the brutalist
 * tabular row idiom. Like every postless route it MUST force HTTP 200
 * (WORDPRESS-REFERENCE gotcha #40). Mirrors tests/humans-txt.php.
 *
 * @since theme v10.7.0
 */

// SECURITY: CLI-only fixture.
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', '/' ); }
define( 'SN_INDEX_TEST', true );        // suppress template_redirect/wp_head/enqueue wiring on require
define( 'SN_INDEX_RENDER_TEST', true ); // suppress the render file's page output on require

ob_start(); // buffer in case any header()/output side effect runs.

$pass = 0; $fail = 0;
function ok( $c, $m ) { global $pass, $fail; if ( $c ) { $pass++; echo "PASS: $m\n"; } else { $fail++; echo "FAIL: $m\n"; } }

// ── WP primitive stubs ────────────────────────────────────────────────
function add_action( $h, $cb, $p = 10, $a = 1 ) { return true; }
function add_filter( $h, $cb, $p = 10, $a = 1 ) { return true; }
$GLOBALS['__filters'] = array();
function apply_filters( $tag, $value ) {
	return array_key_exists( $tag, $GLOBALS['__filters'] ?? array() ) ? $GLOBALS['__filters'][ $tag ] : $value;
}
$GLOBALS['__status'] = 0;
function status_header( $code ) { $GLOBALS['__status'] = (int) $code; }
function get_bloginfo( $k = '' ) { return 'name' === $k ? 'Signal & Noise' : ''; }
function get_theme_file_uri( $p = '' ) { return 'https://x.test/wp-content/themes/sn/' . $p; }
function get_theme_file_path( $p = '' ) { return __DIR__ . '/../' . $p; }
function sn_asset_ver( $p = '' ) { return '123'; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_url( $u ) { $u = (string) $u; $u = str_replace( array( '"', "'", '<', '>', ' ' ), '', $u ); return str_replace( '&', '&amp;', $u ); }
$GLOBALS['__qargs'] = array();
class WP_Query {
	public $posts;
	public $post_count;
	public function __construct( $args ) { $GLOBALS['__qargs'][] = $args; $this->posts = array(); $this->post_count = 0; }
	public function have_posts() { return false; }
}

require __DIR__ . '/../inc/page-index-template.php';

echo "Index dossier suite — theme v10.7.0\n\n";

// ── Route matcher (pure helper) ───────────────────────────────────────
ok( function_exists( 'sn_index_is_request' ), 'sn_index_is_request() is defined' );
ok( sn_index_is_request( '/index' ) === true, 'matches /index' );
ok( sn_index_is_request( '/index/' ) === true, 'matches /index/ (trailing slash)' );
ok( sn_index_is_request( 'index' ) === true, 'matches bare index (no leading slash)' );
ok( sn_index_is_request( '/index?from=footer' ) === true, 'matches /index with a query string' );
ok( sn_index_is_request( '/notes' ) === false, 'rejects /notes' );
ok( sn_index_is_request( '/' ) === false, 'rejects site root' );
ok( sn_index_is_request( '/index.bak' ) === false, 'rejects near-miss /index.bak' );
ok( sn_index_is_request( '/indexes' ) === false, 'rejects near-miss /indexes' );

// ── Document title ────────────────────────────────────────────────────
ok( function_exists( 'sn_index_title' ), 'sn_index_title() is defined' );
ok( sn_index_title() === 'Index — Signal & Noise', 'title is "Index — <site>"' );

// ── Render-file data helpers + forced 200 ─────────────────────────────
require __DIR__ . '/../inc/page-index-render.php';

ok( 200 === $GLOBALS['__status'], 'render forces HTTP 200 for the postless virtual route (gotcha #40)' );

ok( function_exists( 'sn_index_notes_query' ), 'sn_index_notes_query() is defined' );
$GLOBALS['__qargs'] = array();
sn_index_notes_query();
ok( $GLOBALS['__qargs'][0]['post_type'] === 'post', 'notes query targets post_type=post' );
ok( $GLOBALS['__qargs'][0]['post_status'] === 'publish', 'notes query is publish-only' );

ok( function_exists( 'sn_index_pages_query' ), 'sn_index_pages_query() is defined' );
$GLOBALS['__qargs'] = array();
sn_index_pages_query();
ok( $GLOBALS['__qargs'][0]['post_type'] === 'page', 'pages query targets post_type=page' );

ok( function_exists( 'sn_index_music_entries' ), 'sn_index_music_entries() is defined' );
ok( sn_index_music_entries() === array(), 'music entries default to [] (plugin absent / standalone-safe)' );
$GLOBALS['__filters']['sn_discography_entries'] = array( array( 'title' => 'X', 'year' => 2024 ) );
ok( count( sn_index_music_entries() ) === 1, 'music entries read the sn_discography_entries filter' );
$GLOBALS['__filters'] = array();

// ── Row helper escapes every field ────────────────────────────────────
ok( function_exists( 'sn_index_render_row' ), 'sn_index_render_row() is defined' );
$row = sn_index_render_row( '2026.03.14', 'Hostile <b>title</b>', 'https://x.test/a"onx=', 'A & B', false );
ok( strpos( $row, 'sn-index-row' ) !== false, 'row carries the .sn-index-row class' );
ok( strpos( $row, '<b>title</b>' ) === false, 'row title is escaped' );
ok( strpos( $row, '"onx=' ) === false, 'row href is esc_url\'d' );
$ext = sn_index_render_row( '2024', 'Track', 'https://open.spotify.com/x', 'Artist', true );
ok( strpos( $ext, 'rel="noopener"' ) !== false && strpos( $ext, 'target="_blank"' ) !== false, 'external rows open in a new tab with rel=noopener' );
$unlinked = sn_index_render_row( '', 'Plain', '', '', false );
ok( strpos( $unlinked, '<a ' ) === false, 'an empty href renders an unlinked title' );

// ── CSS contract ──────────────────────────────────────────────────────
$css = file_get_contents( __DIR__ . '/../assets/css/index.css' );
ok( is_string( $css ) && '' !== $css, 'index.css exists + non-empty' );
ok( strpos( $css, '.sn-index-row' ) !== false, 'index.css defines the .sn-index-row idiom' );
ok( strpos( $css, '.sn-index-page' ) !== false, 'index.css is scoped under .sn-index-page' );
ok( strpos( $css, '--wp--preset--color--' ) !== false, 'index.css uses theme preset color tokens (no bespoke palette)' );
ok( preg_match( '/prefers-reduced-motion:\s*reduce/', $css ) === 1, 'index.css neutralizes its row transition under reduced motion' );

// ── functions.php wires the module ────────────────────────────────────
$fn = file_get_contents( __DIR__ . '/../functions.php' );
ok( strpos( $fn, "inc/page-index-template.php" ) !== false, 'functions.php requires inc/page-index-template.php' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
