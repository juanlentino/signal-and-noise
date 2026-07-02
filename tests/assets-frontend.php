<?php
/**
 * Standalone fixture tests for inc/assets-frontend.php's stylesheet enqueue
 * closure — BOTH branches.
 *
 * The 2026-07-02 post-ship audit (v10.21.2..v10.21.8 diff) found the
 * combined-CSS branch registered ONLY the `sn-styles` handle, while five
 * unchanged sibling modules (keyboard-nav + the now/index/accessibility/uses
 * virtual routes) still hard-depend on `sn-components`. Real WP_Dependencies
 * resolution silently drops a handle whose dependency was never registered —
 * no <link> prints, no error surfaces — so every post page and all four
 * routes shipped without their page CSS whenever the combiner was working
 * as designed. The fix is core's own alias pattern (a false-src style whose
 * deps carry the real handle); this suite locks it in.
 *
 * Scenarios:
 *   1. Combined mode (envelope returned): sn-styles enqueued, and
 *      `sn-components` REGISTERED as an alias (src === false) depending on
 *      sn-styles, so sibling `array('sn-components')` deps keep resolving.
 *   2. Fallback mode (null envelope): the four per-file handles enqueue in
 *      cascade order and no alias registration fires (sn-components is a
 *      real stylesheet there).
 *
 * Run: php tests/assets-frontend.php
 *
 * @since theme v10.21.9
 */

// SECURITY: Prevent web access. Test fixture, not a runtime module.
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) {
	http_response_code( 404 );
	exit;
}

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/' );
}

$theme_root = realpath( __DIR__ . '/..' );

// ── Captured hook registries + controllable stub state ──
$GLOBALS['__actions']    = array();  // hook => list of callbacks
$GLOBALS['__filters']    = array();  // hook => list of callbacks
$GLOBALS['__enqueued']   = array();  // wp_enqueue_style:  handle => args
$GLOBALS['__registered'] = array();  // wp_register_style: handle => args
$GLOBALS['__enq_js']     = array();  // wp_enqueue_script: handle => args
$GLOBALS['__combined']   = null;     // sn_css_ensure_combined() return control

// The closure checks function_exists('sn_css_ensure_combined') at FIRE time,
// so defining this stub before firing exercises the combined-mode branch —
// the exact branch no suite reached before v10.21.9 (tests/cf7-removal.php
// never defines it, so it only ever runs the fallback).
function sn_css_ensure_combined() {
	return $GLOBALS['__combined'];
}

if ( ! function_exists( 'add_action' ) ) {
	function add_action( $hook, $cb, $priority = 10, $accepted_args = 1 ) {
		$GLOBALS['__actions'][ $hook ][] = $cb;
	}
}
if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( $hook, $cb, $priority = 10, $accepted_args = 1 ) {
		$GLOBALS['__filters'][ $hook ][] = $cb;
	}
}
if ( ! function_exists( 'wp_enqueue_style' ) ) {
	function wp_enqueue_style( $handle, $src = '', $deps = array(), $ver = false, $media = 'all' ) {
		$GLOBALS['__enqueued'][ $handle ] = array( 'src' => $src, 'deps' => $deps, 'ver' => $ver, 'media' => $media );
	}
}
if ( ! function_exists( 'wp_register_style' ) ) {
	function wp_register_style( $handle, $src = '', $deps = array(), $ver = false, $media = 'all' ) {
		$GLOBALS['__registered'][ $handle ] = array( 'src' => $src, 'deps' => $deps, 'ver' => $ver, 'media' => $media );
	}
}
if ( ! function_exists( 'wp_enqueue_script' ) ) {
	function wp_enqueue_script( $handle, $src = '', $deps = array(), $ver = false, $in_footer = false ) {
		$GLOBALS['__enq_js'][ $handle ] = array( 'src' => $src, 'deps' => $deps, 'ver' => $ver, 'in_footer' => $in_footer );
	}
}
if ( ! function_exists( 'wp_dequeue_style' ) ) {
	function wp_dequeue_style( $handle ) {}
}
if ( ! function_exists( 'wp_dequeue_script' ) ) {
	function wp_dequeue_script( $handle ) {}
}
if ( ! function_exists( 'get_theme_file_uri' ) ) {
	function get_theme_file_uri( $path = '' ) {
		return 'https://example.test/wp-content/themes/signal-and-noise/' . ltrim( $path, '/' );
	}
}
if ( ! function_exists( 'get_theme_file_path' ) ) {
	function get_theme_file_path( $path = '' ) {
		return realpath( __DIR__ . '/..' ) . '/' . ltrim( $path, '/' );
	}
}
if ( ! function_exists( 'wp_get_theme' ) ) {
	function wp_get_theme() {
		return new class {
			public function get( $key ) {
				return '10.21.9';
			}
		};
	}
}
if ( ! function_exists( 'is_page' ) ) {
	function is_page( $page = '' ) {
		return false;
	}
}
if ( ! function_exists( 'is_singular' ) ) {
	function is_singular( $types = '' ) {
		return false;
	}
}

require $theme_root . '/inc/assets-frontend.php';

$pass = 0; $fail = 0;
function ok( $cond, $label ) {
	global $pass, $fail;
	if ( $cond ) { $pass++; echo "PASS: $label\n"; }
	else { $fail++; echo "FAIL: $label\n"; }
}
function fire_enqueue_hooks() {
	$GLOBALS['__enqueued']   = array();
	$GLOBALS['__registered'] = array();
	$GLOBALS['__enq_js']     = array();
	foreach ( $GLOBALS['__actions']['wp_enqueue_scripts'] ?? array() as $cb ) {
		call_user_func( $cb );
	}
}

// ── Scenario 1: combined mode — sn-styles + the sn-components alias ──
echo "Scenario 1: combined mode\n";
$GLOBALS['__combined'] = array(
	'file' => '/tmp/sn-styles-abc123456789.css',
	'url'  => 'https://example.test/wp-content/uploads/sn-css/sn-styles-abc123456789.css',
	'ver'  => 'abc123456789',
);
fire_enqueue_hooks();

ok( isset( $GLOBALS['__enqueued']['sn-styles'] ), 'combined mode enqueues sn-styles' );
ok( 'abc123456789' === ( $GLOBALS['__enqueued']['sn-styles']['ver'] ?? null ), 'sn-styles ver is the combiner hash' );
ok( ! isset( $GLOBALS['__enqueued']['sn-base'] ), 'combined mode does not enqueue the per-file sn-base' );
ok( ! isset( $GLOBALS['__enqueued']['sn-components'] ), 'combined mode does not enqueue the per-file sn-components' );
ok( isset( $GLOBALS['__registered']['sn-components'] ), 'combined mode REGISTERS sn-components (the alias five sibling modules depend on)' );
ok( false === ( $GLOBALS['__registered']['sn-components']['src'] ?? '' ), 'sn-components alias has src === false (prints no <link> of its own)' );
ok( in_array( 'sn-styles', $GLOBALS['__registered']['sn-components']['deps'] ?? array(), true ), 'sn-components alias depends on sn-styles (dependents resolve through it)' );

// ── Scenario 2: fallback mode — the four-file cascade, no alias ──
echo "\nScenario 2: fallback mode\n";
$GLOBALS['__combined'] = null;
fire_enqueue_hooks();

ok( ! isset( $GLOBALS['__enqueued']['sn-styles'] ), 'fallback mode does not enqueue sn-styles' );
foreach ( array( 'sn-base', 'sn-layout', 'sn-components', 'sn-responsive' ) as $h ) {
	ok( isset( $GLOBALS['__enqueued'][ $h ] ), "fallback mode enqueues $h" );
}
ok( in_array( 'sn-components', $GLOBALS['__enqueued']['sn-responsive']['deps'] ?? array(), true ), 'fallback cascade intact: sn-responsive depends on sn-components' );
ok( ! isset( $GLOBALS['__registered']['sn-components'] ), 'fallback mode registers no alias (sn-components is the real stylesheet there)' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
