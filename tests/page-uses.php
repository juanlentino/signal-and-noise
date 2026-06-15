<?php
/**
 * Standalone fixture tests for the /uses gear page (D6, theme v10.10.0).
 *
 * inc/uses-data.php holds the structured gear list + the sn_uses_groups()
 * accessor (filterable via `sn_uses_groups` — the seam that lets the plugin
 * supply the list later without a theme change). inc/page-uses-template.php
 * registers a postless /uses virtual route (template_redirect short-circuit +
 * template_include fallback + document title), and inc/page-uses-render.php
 * emits a full HTML document grouping the kit in the brutalist row idiom. Like
 * every postless route it MUST force HTTP 200 (WORDPRESS-REFERENCE gotcha #40).
 * Mirrors tests/page-index.php + tests/humans-txt.php.
 *
 * @since theme v10.10.0
 */

// SECURITY: CLI-only fixture.
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', '/' ); }
define( 'SN_USES_TEST', true );        // suppress template_redirect/wp_head/enqueue wiring on require
define( 'SN_USES_RENDER_TEST', true ); // suppress the render file's page output on require

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

require __DIR__ . '/../inc/uses-data.php';
require __DIR__ . '/../inc/page-uses-template.php';

echo "Uses gear-page suite — theme v10.10.0\n\n";

// ── Route matcher (pure helper) — /uses is a child of /about (the bio page) ──
ok( function_exists( 'sn_uses_is_request' ), 'sn_uses_is_request() is defined' );
ok( sn_uses_is_request( '/about/uses' ) === true, 'matches /about/uses' );
ok( sn_uses_is_request( '/about/uses/' ) === true, 'matches /about/uses/ (trailing slash)' );
ok( sn_uses_is_request( 'about/uses' ) === true, 'matches bare about/uses (no leading slash)' );
ok( sn_uses_is_request( '/about/uses?ref=footer' ) === true, 'matches /about/uses with a query string' );
ok( sn_uses_is_request( '/uses' ) === false, 'rejects bare /uses (it lives under /about now)' );
ok( sn_uses_is_request( '/about' ) === false, 'rejects the parent /about' );
ok( sn_uses_is_request( '/about/used' ) === false, 'rejects /about/used' );
ok( sn_uses_is_request( '/' ) === false, 'rejects site root' );
ok( sn_uses_is_request( '/about/uses.bak' ) === false, 'rejects near-miss /about/uses.bak' );
ok( sn_uses_is_request( '/about/usesful' ) === false, 'rejects near-miss /about/usesful' );

// ── Document title ────────────────────────────────────────────────────
ok( function_exists( 'sn_uses_title' ), 'sn_uses_title() is defined' );
ok( sn_uses_title() === 'Uses — Signal & Noise', 'title is "Uses — <site>"' );

// ── Data + filter seam ────────────────────────────────────────────────
ok( function_exists( 'sn_uses_groups' ), 'sn_uses_groups() is defined' );
$groups = sn_uses_groups();
ok( is_array( $groups ) && count( $groups ) >= 1, 'sn_uses_groups() returns a non-empty group list' );
$shape_ok = true;
foreach ( $groups as $g ) {
	if ( ! is_array( $g ) || '' === (string) ( $g['label'] ?? '' ) || empty( $g['items'] ) || ! is_array( $g['items'] ) ) { $shape_ok = false; break; }
	foreach ( $g['items'] as $it ) { if ( '' === (string) ( $it['name'] ?? '' ) ) { $shape_ok = false; break 2; } }
}
ok( $shape_ok, 'every group has a label + items, every item has a name' );
ok( sn_uses_item_count() === 11, 'the seeded gear list has 11 items' );

// A known seeded item with its note survives.
$found = false;
foreach ( $groups as $g ) { foreach ( $g['items'] as $it ) {
	if ( strpos( $it['name'], 'Apollo Twin X DUO' ) !== false && strpos( $it['note'], 'Custom 10' ) !== false ) { $found = true; }
} }
ok( $found, 'seeded item "Apollo Twin X DUO" carries its note "Custom 10 plug-in upgrade"' );

// Filter seam: an override replaces the list.
$GLOBALS['__filters']['sn_uses_groups'] = array( array( 'label' => 'Override', 'items' => array( array( 'name' => 'Only Item' ) ) ) );
$o = sn_uses_groups();
ok( count( $o ) === 1 && $o[0]['label'] === 'Override' && sn_uses_item_count() === 1, 'sn_uses_groups filter override is honored (the plugin seam)' );
$GLOBALS['__filters'] = array();

// Robustness: malformed filter values degrade safely.
$GLOBALS['__filters']['sn_uses_groups'] = 'not-an-array';
ok( sn_uses_groups() === array(), 'non-array filter → empty (no fatal)' );
$GLOBALS['__filters']['sn_uses_groups'] = array(
	array( 'label' => 'No items', 'items' => array() ),                              // dropped: empty
	array( 'label' => '', 'items' => array( array( 'name' => 'x' ) ) ),             // dropped: no label
	array( 'label' => 'Keep', 'items' => array( array( 'name' => '' ), array( 'name' => 'Real' ) ) ), // nameless item dropped
);
$m = sn_uses_groups();
ok( count( $m ) === 1 && $m[0]['label'] === 'Keep' && count( $m[0]['items'] ) === 1, 'malformed groups/items are pruned' );
$GLOBALS['__filters'] = array();

// String-shorthand items are accepted (lenient filter input).
$GLOBALS['__filters']['sn_uses_groups'] = array( array( 'label' => 'Strs', 'items' => array( 'Plain Name' ) ) );
$s = sn_uses_groups();
ok( $s[0]['items'][0]['name'] === 'Plain Name' && $s[0]['items'][0]['note'] === '', 'a bare-string item normalizes to {name, note:""}' );
$GLOBALS['__filters'] = array();

// ── Render-file: forced 200 + item helper ─────────────────────────────
require __DIR__ . '/../inc/page-uses-render.php';
ok( 200 === $GLOBALS['__status'], 'render forces HTTP 200 for the postless virtual route (gotcha #40)' );

ok( function_exists( 'sn_uses_render_item' ), 'sn_uses_render_item() is defined' );
$row = sn_uses_render_item( 'Hostile <b>name</b>', 'A & note' );
ok( strpos( $row, 'sn-uses-item' ) !== false, 'item carries the .sn-uses-item class' );
ok( strpos( $row, '<b>name</b>' ) === false, 'item name is escaped' );
ok( strpos( $row, 'A &amp; note' ) !== false, 'item note is escaped' );
$nonote = sn_uses_render_item( 'Just a name', '' );
ok( strpos( $nonote, 'sn-uses-item-note' ) === false, 'an empty note renders no note element' );

// ── CSS contract ──────────────────────────────────────────────────────
$css = file_get_contents( __DIR__ . '/../assets/css/uses.css' );
ok( is_string( $css ) && '' !== $css, 'uses.css exists + non-empty' );
ok( strpos( $css, '.sn-uses-item' ) !== false, 'uses.css defines the .sn-uses-item idiom' );
ok( strpos( $css, '.sn-uses-page' ) !== false, 'uses.css is scoped under .sn-uses-page' );
ok( strpos( $css, '--wp--preset--color--' ) !== false, 'uses.css uses theme preset color tokens (no bespoke palette)' );

// ── functions.php wires the module ────────────────────────────────────
$fn = file_get_contents( __DIR__ . '/../functions.php' );
ok( strpos( $fn, 'inc/page-uses-template.php' ) !== false, 'functions.php requires inc/page-uses-template.php' );
ok( strpos( $fn, 'inc/uses-data.php' ) !== false, 'functions.php requires inc/uses-data.php' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
