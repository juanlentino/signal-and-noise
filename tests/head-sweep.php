<?php
/**
 * Standalone fixture tests for the <head> sweep (A4, theme v10.5.0).
 *
 * Two behaviours:
 *   1. inc/frontend-filters.php strips the legacy xmlrpc-era head links
 *      (rsd_link / wlwmanifest_link) via file-scope remove_action() — same
 *      mechanism as the existing wp_generator strip.
 *   2. inc/assets-frontend.php emits a brand <meta name="theme-color"> in the
 *      priority-1 wp_head closure, with the value pinned to theme.json's
 *      `blood` palette slug so the two can never drift apart.
 *
 * Both files register at file scope (closures / bare remove_action), so the
 * harness records add_action/remove_action args and invokes the captured
 * wp_head closure directly rather than booting WP. Mirrors tests/print-styles.php.
 *
 * @since theme v10.5.0
 */

// SECURITY: Prevent web access. Test fixture, not a runtime module.
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) {
	http_response_code( 404 );
	exit;
}

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/' );
}

// ── Recording stubs ──
$GLOBALS['__actions'] = array(); // each: [ 'hook' => , 'cb' => , 'pri' => ]
$GLOBALS['__removed'] = array(); // each: [ 'hook' => , 'cb' => ] (cb is a callable name)

if ( ! function_exists( 'add_action' ) ) {
	function add_action( $hook, $cb = null, $pri = 10, $args = 1 ) {
		$GLOBALS['__actions'][] = array( 'hook' => $hook, 'cb' => $cb, 'pri' => $pri );
		return true;
	}
}
if ( ! function_exists( 'add_filter' ) ) {
	function add_filter() { return true; }
}
if ( ! function_exists( 'remove_action' ) ) {
	function remove_action( $hook, $cb, $pri = 10 ) {
		$GLOBALS['__removed'][] = array( 'hook' => $hook, 'cb' => $cb );
		return true;
	}
}
if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( $u ) { return str_replace( array( '"', ' ' ), array( '', '' ), (string) $u ); }
}
if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $a ) { return htmlspecialchars( (string) $a, ENT_QUOTES ); }
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
			public function get( $key ) { return '10.5.0'; }
		};
	}
}
if ( ! function_exists( 'wp_enqueue_style' ) ) {
	function wp_enqueue_style() {}
}
if ( ! function_exists( 'wp_enqueue_script' ) ) {
	function wp_enqueue_script() {}
}
if ( ! function_exists( 'is_page' ) ) {
	function is_page() { return false; }
}
if ( ! function_exists( 'is_singular' ) ) {
	function is_singular() { return false; }
}

$pass = 0; $fail = 0;
function ok( $cond, $label ) {
	global $pass, $fail;
	if ( $cond ) { $pass++; echo "PASS: $label\n"; }
	else { $fail++; echo "FAIL: $label\n"; }
}

/** True if a [hook, cb] removal was recorded. */
function removed_has( $hook, $cb ) {
	foreach ( $GLOBALS['__removed'] as $r ) {
		if ( $r['hook'] === $hook && $r['cb'] === $cb ) {
			return true;
		}
	}
	return false;
}

/** Invoke every wp_head closure at a given priority, return concatenated output. */
function render_wp_head_at( $priority ) {
	$out = '';
	foreach ( $GLOBALS['__actions'] as $a ) {
		if ( 'wp_head' === $a['hook'] && (int) $a['pri'] === (int) $priority && is_callable( $a['cb'] ) ) {
			ob_start();
			call_user_func( $a['cb'] );
			$out .= ob_get_clean();
		}
	}
	return $out;
}

// ── 1. Head-link strip (inc/frontend-filters.php) ──
require __DIR__ . '/../inc/frontend-filters.php';

ok( removed_has( 'wp_head', 'wp_generator' ), 'existing wp_generator strip still present (sanity)' );
ok( removed_has( 'wp_head', 'rsd_link' ), 'A4: rsd_link (EditURI/RSD) removed from wp_head' );
ok( removed_has( 'wp_head', 'wlwmanifest_link' ), 'A4: wlwmanifest_link removed from wp_head' );

// ── 2. theme-color meta (inc/assets-frontend.php) ──
require __DIR__ . '/../inc/assets-frontend.php';

$head1 = render_wp_head_at( 1 );
ok( strpos( $head1, 'name="theme-color"' ) !== false, 'A4: <meta name="theme-color"> emitted in priority-1 wp_head closure' );
ok( strpos( $head1, '#e00404' ) !== false, 'A4: theme-color content is the brand red #e00404' );

// ── 3. Brand-drift guard: meta value MUST equal theme.json `blood` slug ──
$theme_json = json_decode( (string) file_get_contents( realpath( __DIR__ . '/..' ) . '/theme.json' ), true );
$blood = '';
foreach ( ( $theme_json['settings']['color']['palette'] ?? array() ) as $c ) {
	if ( 'blood' === ( $c['slug'] ?? '' ) ) {
		$blood = strtolower( $c['color'] );
	}
}
ok( '#e00404' === $blood, 'theme.json `blood` slug is #e00404 (palette source of truth)' );
ok( '' !== $blood && strpos( strtolower( $head1 ), $blood ) !== false, 'theme-color meta matches theme.json `blood` (no drift)' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
