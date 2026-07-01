<?php
/**
 * Standalone fixture tests for the /now page (theme v10.21.0).
 *
 * inc/now-data.php holds the owner-editable sections + the sn_now_sections()
 * accessor (filterable via `sn_now_sections`, mirroring the /uses plugin
 * seam) and the explicit sn_now_updated() date the page renders so staleness
 * stays honest. inc/page-now-template.php registers a postless /now virtual
 * route (template_redirect short-circuit + template_include fallback +
 * document title), and inc/page-now-render.php emits a full HTML document.
 * Like every postless route it MUST force HTTP 200 (WORDPRESS-REFERENCE
 * gotcha #40). Mirrors tests/page-uses.php.
 *
 * @since theme v10.21.0
 */

// SECURITY: CLI-only fixture.
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', '/' ); }
define( 'SN_NOW_TEST', true );        // suppress template_redirect/wp_head/enqueue wiring on require
define( 'SN_NOW_RENDER_TEST', true ); // suppress the render file's page output on require

ob_start();

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

require __DIR__ . '/../inc/now-data.php';
require __DIR__ . '/../inc/page-now-template.php';

echo "Now page suite — theme v10.21.0\n\n";

// ── Route matcher (pure helper) ──────────────────────────────────────
ok( function_exists( 'sn_now_is_request' ), 'sn_now_is_request() is defined' );
ok( sn_now_is_request( '/now' ) === true, 'matches /now' );
ok( sn_now_is_request( '/now/' ) === true, 'matches /now/ (trailing slash)' );
ok( sn_now_is_request( 'now' ) === true, 'matches bare now (no leading slash)' );
ok( sn_now_is_request( '/now?ref=footer' ) === true, 'matches /now with a query string' );
ok( sn_now_is_request( '/nowhere' ) === false, 'rejects /nowhere' );
ok( sn_now_is_request( '/about/now' ) === false, 'rejects nested /about/now' );
ok( sn_now_is_request( '/' ) === false, 'rejects site root' );
ok( sn_now_is_request( '/now.bak' ) === false, 'rejects near-miss /now.bak' );

// ── Document title ────────────────────────────────────────────────────
ok( function_exists( 'sn_now_title' ), 'sn_now_title() is defined' );
ok( sn_now_title() === 'Now — Signal & Noise', 'title is "Now — <site>"' );

// ── Data + filter seam ────────────────────────────────────────────────
ok( function_exists( 'sn_now_sections' ), 'sn_now_sections() is defined' );
ok( 1 === preg_match( '/^\d{4}-\d{2}-\d{2}$/', sn_now_updated() ), 'sn_now_updated() is a YYYY-MM-DD date' );

// v10.21.1: the updated date is filterable (sn_now_updated) so the plugin's
// Now editor can supply the live save-stamp — completing the plugin seam
// that sn_now_sections already had.
$GLOBALS['__filters']['sn_now_updated'] = '2030-12-31';
ok( '2030-12-31' === sn_now_updated(), 'sn_now_updated honors the sn_now_updated filter (plugin seam)' );
$GLOBALS['__filters'] = array();
$sections = sn_now_sections();
ok( is_array( $sections ) && count( $sections ) >= 3, 'sn_now_sections() returns a non-empty section list' );
$shape_ok = true;
foreach ( $sections as $s ) {
	if ( ! is_array( $s ) || '' === (string) ( $s['label'] ?? '' ) || empty( $s['items'] ) || ! is_array( $s['items'] ) ) { $shape_ok = false; break; }
	foreach ( $s['items'] as $it ) { if ( ! is_string( $it ) || '' === $it ) { $shape_ok = false; break 2; } }
}
ok( $shape_ok, 'every section has a label + non-empty string items' );

// Filter seam: an override replaces the list.
$GLOBALS['__filters']['sn_now_sections'] = array( array( 'label' => 'Override', 'items' => array( 'Only item' ) ) );
$o = sn_now_sections();
ok( count( $o ) === 1 && $o[0]['label'] === 'Override', 'sn_now_sections filter override is honored (the plugin seam)' );
$GLOBALS['__filters'] = array();

// Robustness: malformed filter values degrade safely.
$GLOBALS['__filters']['sn_now_sections'] = 'not-an-array';
ok( sn_now_sections() === array(), 'non-array filter → empty (no fatal)' );
$GLOBALS['__filters']['sn_now_sections'] = array(
	array( 'label' => 'No items', 'items' => array() ),               // dropped: empty
	array( 'label' => '', 'items' => array( 'x' ) ),                  // dropped: no label
	array( 'label' => 'Keep', 'items' => array( '', 'Real' ) ),       // empty item dropped
);
$m = sn_now_sections();
ok( count( $m ) === 1 && $m[0]['label'] === 'Keep' && count( $m[0]['items'] ) === 1, 'malformed sections/items are pruned' );
// {text:...} shorthand items are accepted (lenient filter input).
$GLOBALS['__filters']['sn_now_sections'] = array( array( 'label' => 'Objs', 'items' => array( array( 'text' => 'From object' ) ) ) );
$t = sn_now_sections();
ok( $t[0]['items'][0] === 'From object', 'an array item with a text key normalizes to its string' );
$GLOBALS['__filters'] = array();

// ── Render-file: forced 200 + item helper ─────────────────────────────
require __DIR__ . '/../inc/page-now-render.php';
ok( 200 === $GLOBALS['__status'], 'render forces HTTP 200 for the postless virtual route (gotcha #40)' );

ok( function_exists( 'sn_now_render_item' ), 'sn_now_render_item() is defined' );
$row = sn_now_render_item( 'Hostile <b>item</b> & text' );
ok( strpos( $row, 'sn-now-item' ) !== false, 'item carries the .sn-now-item class' );
ok( strpos( $row, '<b>item</b>' ) === false, 'item text is escaped' );
ok( strpos( $row, '&amp; text' ) !== false, 'ampersand escaped' );

// ── CSS contract ──────────────────────────────────────────────────────
$css = file_get_contents( __DIR__ . '/../assets/css/now.css' );
ok( is_string( $css ) && '' !== $css, 'now.css exists + non-empty' );
ok( strpos( $css, '.sn-now-page' ) !== false, 'now.css is scoped under .sn-now-page' );
ok( strpos( $css, '.sn-now-item' ) !== false, 'now.css defines the .sn-now-item idiom' );
ok( strpos( $css, '--wp--preset--color--' ) !== false, 'now.css uses theme preset color tokens (no bespoke palette)' );
ok( strpos( $css, 'prefers-reduced-motion' ) !== false, 'now.css neutralizes the row transition under reduced motion' );

// ── functions.php wires the module ────────────────────────────────────
$fn = file_get_contents( __DIR__ . '/../functions.php' );
ok( strpos( $fn, 'inc/page-now-template.php' ) !== false, 'functions.php requires inc/page-now-template.php' );
ok( strpos( $fn, 'inc/now-data.php' ) !== false, 'functions.php requires inc/now-data.php' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
