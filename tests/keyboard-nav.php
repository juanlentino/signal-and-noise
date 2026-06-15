<?php
/**
 * Standalone fixture tests for keyboard navigation on single notes (C5, v10.7.0).
 *
 * inc/keyboard-nav.php conditionally enqueues assets/js/keyboard-nav.js +
 * assets/css/keyboard-nav.css on single posts. The JS adds j = next note,
 * k = previous note (following the post-closing prev/next links), and ? = a
 * keyboard cheat-sheet overlay, all skipped while typing in a form field
 * (same isFormField guard as the command palette). The SN_KBD_NAV_TEST
 * sentinel suppresses the hook wiring on require. Mirrors tests/command-palette.php.
 *
 * @since theme v10.7.0
 */

// SECURITY: CLI-only fixture.
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', '/' ); }
define( 'SN_KBD_NAV_TEST', true ); // suppress wp_enqueue_scripts wiring on require.

$pass = 0; $fail = 0;
function ok( $c, $m ) { global $pass, $fail; if ( $c ) { $pass++; echo "PASS: $m\n"; } else { $fail++; echo "FAIL: $m\n"; } }

// ── WP primitive stubs ────────────────────────────────────────────────
function add_action( $h, $cb, $p = 10, $a = 1 ) { return true; }
$GLOBALS['__filters'] = array();
function apply_filters( $tag, $value ) {
	return array_key_exists( $tag, $GLOBALS['__filters'] ?? array() ) ? $GLOBALS['__filters'][ $tag ] : $value;
}
$GLOBALS['__is_singular'] = false;
function is_singular( $t = '' ) { return (bool) $GLOBALS['__is_singular']; }
function get_theme_file_uri( $p = '' ) { return 'https://x.test/wp-content/themes/sn/' . $p; }
function sn_asset_ver( $p = '' ) { return '123'; }
$GLOBALS['__enq'] = array();
function wp_enqueue_script( $handle, $src = '', $deps = array(), $ver = false, $args = array() ) { $GLOBALS['__enq'][] = $handle; }
function wp_enqueue_style( $handle, $src = '', $deps = array(), $ver = false, $media = 'all' ) { $GLOBALS['__enq'][] = $handle; }

require __DIR__ . '/../inc/keyboard-nav.php';

echo "Keyboard-nav suite — theme v10.7.0\n\n";

// ── Kill-switch ───────────────────────────────────────────────────────
ok( function_exists( 'sn_keyboard_nav_enabled' ), 'sn_keyboard_nav_enabled() is defined' );
$GLOBALS['__filters'] = array();
ok( sn_keyboard_nav_enabled() === true, 'enabled by default' );
$GLOBALS['__filters']['sn_keyboard_nav_enabled'] = false;
ok( sn_keyboard_nav_enabled() === false, 'sn_keyboard_nav_enabled=false disables it' );
$GLOBALS['__filters'] = array();

// ── Conditional enqueue: single posts only ────────────────────────────
ok( function_exists( 'sn_keyboard_nav_enqueue' ), 'sn_keyboard_nav_enqueue() is defined' );

$GLOBALS['__enq'] = array(); $GLOBALS['__is_singular'] = false;
sn_keyboard_nav_enqueue();
ok( empty( $GLOBALS['__enq'] ), 'no enqueue off single-post views (is_singular false)' );

$GLOBALS['__enq'] = array(); $GLOBALS['__is_singular'] = true;
sn_keyboard_nav_enqueue();
ok( in_array( 'sn-keyboard-nav', $GLOBALS['__enq'], true ), 'enqueues sn-keyboard-nav on single posts' );
ok( count( array_filter( $GLOBALS['__enq'], function ( $h ) { return $h === 'sn-keyboard-nav'; } ) ) === 2, 'enqueues both the JS and the CSS (2 handles)' );

// Disabled → no enqueue even on a single post.
$GLOBALS['__enq'] = array(); $GLOBALS['__is_singular'] = true;
$GLOBALS['__filters']['sn_keyboard_nav_enabled'] = false;
sn_keyboard_nav_enqueue();
ok( empty( $GLOBALS['__enq'] ), 'disabled kill-switch → no enqueue even on single posts' );
$GLOBALS['__filters'] = array();

// ── JS contract (static source checks) ────────────────────────────────
$js = file_get_contents( __DIR__ . '/../assets/js/keyboard-nav.js' );
ok( is_string( $js ) && '' !== $js, 'keyboard-nav.js exists + non-empty' );
ok( strpos( $js, 'function isFormField' ) !== false, 'JS defines the isFormField guard' );

// isFormField must match command-palette.js verbatim (so both skip while typing).
$pal = file_get_contents( __DIR__ . '/../assets/js/command-palette.js' );
function sn_extract_isformfield( $src ) {
	if ( ! preg_match( '/function isFormField\([^)]*\)\s*\{(.*?)\n\t\}/s', $src, $m ) ) { return null; }
	return preg_replace( '/\s+/', ' ', trim( $m[1] ) );
}
ok( sn_extract_isformfield( $js ) !== null && sn_extract_isformfield( $js ) === sn_extract_isformfield( $pal ), 'isFormField guard body matches command-palette.js verbatim' );

ok( preg_match( "/===\s*'j'/", $js ) === 1, 'JS handles the j key' );
ok( preg_match( "/===\s*'k'/", $js ) === 1, 'JS handles the k key' );
ok( strpos( $js, "'?'" ) !== false, 'JS handles the ? key' );
ok( strpos( $js, 'sn-post-closing__next' ) !== false, 'JS targets the next-note link' );
ok( strpos( $js, 'sn-post-closing__prev' ) !== false, 'JS targets the prev-note link' );
ok( strpos( $js, 'Escape' ) !== false, 'JS closes the overlay on Escape' );
ok( strpos( $js, 'console.log' ) === false && strpos( $js, 'debugger' ) === false, 'no console.log / debugger left in JS' );
ok( strpos( $js, '.innerHTML' ) === false, 'JS never uses innerHTML (textContent-only DOM writes)' );

// ── CSS contract ──────────────────────────────────────────────────────
$css = file_get_contents( __DIR__ . '/../assets/css/keyboard-nav.css' );
ok( is_string( $css ) && '' !== $css, 'keyboard-nav.css exists + non-empty' );
ok( strpos( $css, 'prefers-reduced-motion: no-preference' ) !== false, 'CSS gates motion under prefers-reduced-motion: no-preference' );
ok( preg_match( '/@media\s*\(\s*prefers-reduced-motion[^)]*\)\s*\{[^}]*@keyframes/s', $css ) === 1
	|| preg_match( '/prefers-reduced-motion:\s*no-preference\s*\)\s*\{(?:[^{}]|\{[^{}]*\})*@keyframes/s', $css ) === 1,
	'@keyframes is gated inside the reduced-motion media query (animation only, not display)' );
ok( preg_match( '/@media\s*\(\s*prefers-reduced-motion\s*\)\s*\{[^}]*display\s*:/s', $css ) !== 1, 'reduced-motion does NOT gate display/visibility' );
ok( strpos( $css, '--wp--preset--color--' ) !== false, 'CSS uses theme preset color tokens (no bespoke palette)' );

// ── Prev/next links exist in the post-closing part (j/k targets) ──────
$closing = file_get_contents( __DIR__ . '/../parts/post-closing.html' );
ok( strpos( $closing, 'sn-post-closing__prev' ) !== false, 'post-closing part renders the prev container (k target)' );
ok( strpos( $closing, 'sn-post-closing__next' ) !== false, 'post-closing part renders the next container (j target)' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
