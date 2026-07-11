<?php
/**
 * Standalone fixture tests for the per-Page bespoke stylesheet enqueue (v10.36.0).
 *
 * inc/cms-page-styles.php enqueues now.css on /now, uses.css on /about/uses, and
 * accessibility.css on /accessibility — each depending on the shared sn-components
 * handle, keyed on the real CMS Pages. The pages-to-CMS flip retired the virtual
 * routes whose templates used to enqueue these, so the enqueue moved here. This
 * stubs is_page/wp_enqueue_style/get_theme_file_uri/sn_asset_ver so the pure
 * callback runs without a WP load, and restores the CSS-content contract the
 * now-deleted route tests carried (tests/page-now.php / page-uses.php /
 * page-accessibility.php). Mirrors tests/print-styles.php + tests/beacon.php.
 *
 * @since theme v10.36.0
 */

// SECURITY: CLI-only fixture, not a runtime module.
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', '/' ); }

// ── Controllable stub state ──
$GLOBALS['__page']     = '';        // current page path, drives is_page()
$GLOBALS['__enqueued'] = array();   // captured wp_enqueue_style() calls
$GLOBALS['__actions']  = array();   // captured add_action() registrations

if ( ! function_exists( 'is_page' ) ) {
	function is_page( $page = '' ) {
		if ( '' === $page || array() === $page ) {
			return '' !== $GLOBALS['__page'];
		}
		foreach ( (array) $page as $p ) {
			if ( (string) $p === (string) $GLOBALS['__page'] ) { return true; }
		}
		return false;
	}
}
if ( ! function_exists( 'wp_enqueue_style' ) ) {
	function wp_enqueue_style( $handle, $src = '', $deps = array(), $ver = false, $media = 'all' ) {
		$GLOBALS['__enqueued'][ $handle ] = compact( 'src', 'deps', 'ver', 'media' );
	}
}
if ( ! function_exists( 'get_theme_file_uri' ) ) {
	function get_theme_file_uri( $p = '' ) {
		return 'https://example.test/wp-content/themes/signal-and-noise/' . ltrim( $p, '/' );
	}
}
if ( ! function_exists( 'sn_asset_ver' ) ) {
	function sn_asset_ver( $p = '' ) { return '123'; }
}
if ( ! function_exists( 'add_action' ) ) {
	function add_action( $hook, $cb, $priority = 10, $args = 1 ) {
		$GLOBALS['__actions'][] = compact( 'hook', 'cb', 'priority', 'args' );
	}
}

require __DIR__ . '/../inc/cms-page-styles.php';

$pass = 0; $fail = 0;
function ok( $c, $m ) { global $pass, $fail; if ( $c ) { $pass++; echo "PASS: $m\n"; } else { $fail++; echo "FAIL: $m\n"; } }
function reset_enqueue() { $GLOBALS['__enqueued'] = array(); }

echo "CMS-page bespoke stylesheet enqueue — theme v10.36.0\n\n";

ok( function_exists( 'sn_enqueue_cms_page_styles' ), 'sn_enqueue_cms_page_styles() is defined' );

// ── Self-hook on require (no test guard): wp_enqueue_scripts at priority 30. ──
$hook = null;
foreach ( $GLOBALS['__actions'] as $a ) {
	if ( 'sn_enqueue_cms_page_styles' === $a['cb'] ) { $hook = $a; break; }
}
ok( $hook !== null, 'callback registers itself on require' );
ok( $hook && 'wp_enqueue_scripts' === $hook['hook'], 'hooked on wp_enqueue_scripts' );
ok( $hook && 30 === $hook['priority'], 'hooked at priority 30 (after the base theme styles register)' );

// ── /now → sn-now only ──
reset_enqueue();
$GLOBALS['__page'] = 'now';
sn_enqueue_cms_page_styles();
$now = $GLOBALS['__enqueued']['sn-now'] ?? null;
ok( $now !== null, 'sn-now enqueued on /now' );
ok( strpos( (string) ( $now['src'] ?? '' ), 'assets/css/now.css' ) !== false, 'sn-now src points at assets/css/now.css' );
ok( in_array( 'sn-components', (array) ( $now['deps'] ?? array() ), true ), 'sn-now DEPENDS on sn-components' );
ok( ( $now['ver'] ?? false ) !== false, 'sn-now carries a cache-bust version (sn_asset_ver)' );
ok( ! isset( $GLOBALS['__enqueued']['sn-uses'] ) && ! isset( $GLOBALS['__enqueued']['sn-a11y'] ), 'only sn-now enqueued on /now (elseif exclusivity)' );

// ── /about/uses → sn-uses only ──
reset_enqueue();
$GLOBALS['__page'] = 'about/uses';
sn_enqueue_cms_page_styles();
$uses = $GLOBALS['__enqueued']['sn-uses'] ?? null;
ok( $uses !== null, 'sn-uses enqueued on /about/uses' );
ok( strpos( (string) ( $uses['src'] ?? '' ), 'assets/css/uses.css' ) !== false, 'sn-uses src points at assets/css/uses.css' );
ok( in_array( 'sn-components', (array) ( $uses['deps'] ?? array() ), true ), 'sn-uses DEPENDS on sn-components' );
ok( ( $uses['ver'] ?? false ) !== false, 'sn-uses carries a cache-bust version' );
ok( ! isset( $GLOBALS['__enqueued']['sn-now'] ) && ! isset( $GLOBALS['__enqueued']['sn-a11y'] ), 'only sn-uses enqueued on /about/uses' );

// ── /accessibility → sn-a11y only ──
reset_enqueue();
$GLOBALS['__page'] = 'accessibility';
sn_enqueue_cms_page_styles();
$a11y = $GLOBALS['__enqueued']['sn-a11y'] ?? null;
ok( $a11y !== null, 'sn-a11y enqueued on /accessibility' );
ok( strpos( (string) ( $a11y['src'] ?? '' ), 'assets/css/accessibility.css' ) !== false, 'sn-a11y src points at assets/css/accessibility.css' );
ok( in_array( 'sn-components', (array) ( $a11y['deps'] ?? array() ), true ), 'sn-a11y DEPENDS on sn-components' );
ok( ( $a11y['ver'] ?? false ) !== false, 'sn-a11y carries a cache-bust version' );
ok( ! isset( $GLOBALS['__enqueued']['sn-now'] ) && ! isset( $GLOBALS['__enqueued']['sn-uses'] ), 'only sn-a11y enqueued on /accessibility' );

// ── An unrelated page → nothing enqueued ──
reset_enqueue();
$GLOBALS['__page'] = 'contact';
sn_enqueue_cms_page_styles();
ok( array() === $GLOBALS['__enqueued'], 'no bespoke stylesheet enqueued off the three Pages' );

// ── CSS CONTENT contract (restored from the deleted route tests) ──
$now_css = (string) @file_get_contents( __DIR__ . '/../assets/css/now.css' );
ok( '' !== $now_css, 'now.css is readable' );
ok( strpos( $now_css, '.sn-now-page' ) !== false, 'now.css is scoped under .sn-now-page' );
ok( strpos( $now_css, '.sn-now-item' ) !== false, 'now.css defines the .sn-now-item idiom' );
ok( strpos( $now_css, '--wp--preset--color--' ) !== false, 'now.css uses theme preset color tokens (no bespoke palette)' );
ok( strpos( $now_css, 'prefers-reduced-motion' ) !== false, 'now.css neutralizes motion under reduced-motion' );

$uses_css = (string) @file_get_contents( __DIR__ . '/../assets/css/uses.css' );
ok( '' !== $uses_css, 'uses.css is readable' );
ok( strpos( $uses_css, '.sn-uses-page' ) !== false, 'uses.css is scoped under .sn-uses-page' );
ok( strpos( $uses_css, '.sn-uses-item' ) !== false, 'uses.css defines the .sn-uses-item idiom' );
ok( strpos( $uses_css, '--wp--preset--color--' ) !== false, 'uses.css uses theme preset color tokens' );

$a11y_css = (string) @file_get_contents( __DIR__ . '/../assets/css/accessibility.css' );
ok( '' !== $a11y_css, 'accessibility.css is readable' );
ok( strpos( $a11y_css, '.sn-a11y-page' ) !== false, 'accessibility.css is scoped under .sn-a11y-page' );
ok( strpos( $a11y_css, '--wp--preset--color--' ) !== false, 'accessibility.css uses theme preset color tokens' );

// ── functions.php wires the module ──
$fn = (string) @file_get_contents( __DIR__ . '/../functions.php' );
ok( strpos( $fn, 'inc/cms-page-styles.php' ) !== false, 'functions.php requires inc/cms-page-styles.php' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
