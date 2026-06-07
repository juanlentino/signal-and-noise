<?php
/**
 * Standalone fixture tests for the print / save-as-PDF stylesheet enqueue (v9.10.0).
 *
 * Stubs the WP enqueue + conditional functions that sn_enqueue_print_styles()
 * touches so the pure callback in inc/assets-frontend.php runs without a WP
 * load. Mirrors tests/notes-search.php.
 *
 * @since theme v9.10.0
 */

// SECURITY: Prevent web access. Test fixture, not a runtime module.
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) {
	http_response_code( 404 );
	exit;
}

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/' );
}

// ── Controllable stub state ──
$GLOBALS['__singular']  = array();   // map of post-type => bool for is_singular()
$GLOBALS['__enqueued']  = array();   // captured wp_enqueue_style() calls

if ( ! function_exists( 'is_singular' ) ) {
	function is_singular( $types = '' ) {
		$map = $GLOBALS['__singular'];
		if ( '' === $types || array() === $types ) {
			// "any singular" — true if any registered type is singular.
			return in_array( true, $map, true );
		}
		foreach ( (array) $types as $t ) {
			if ( ! empty( $map[ $t ] ) ) {
				return true;
			}
		}
		return false;
	}
}
if ( ! function_exists( 'wp_enqueue_style' ) ) {
	function wp_enqueue_style( $handle, $src = '', $deps = array(), $ver = false, $media = 'all' ) {
		$GLOBALS['__enqueued'][ $handle ] = array(
			'src'   => $src,
			'deps'  => $deps,
			'ver'   => $ver,
			'media' => $media,
		);
	}
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
				return '9.10.0';
			}
		};
	}
}
// add_action / add_filter are no-ops here: we call the helper directly.
if ( ! function_exists( 'add_action' ) ) {
	function add_action() {}
}
if ( ! function_exists( 'add_filter' ) ) {
	function add_filter() {}
}
if ( ! function_exists( 'wp_enqueue_script' ) ) {
	function wp_enqueue_script() {}
}
if ( ! function_exists( 'is_page' ) ) {
	function is_page() {
		return false;
	}
}

require __DIR__ . '/../inc/assets-frontend.php';

$pass = 0; $fail = 0;
function ok( $cond, $label ) {
	global $pass, $fail;
	if ( $cond ) { $pass++; echo "PASS: $label\n"; }
	else { $fail++; echo "FAIL: $label\n"; }
}

function reset_enqueue() {
	$GLOBALS['__enqueued'] = array();
}

ok( function_exists( 'sn_enqueue_print_styles' ), 'sn_enqueue_print_styles() is defined' );

// ── Singular post → sn-print enqueued, media=print ──
reset_enqueue();
$GLOBALS['__singular'] = array( 'post' => true );
sn_enqueue_print_styles();
ok( isset( $GLOBALS['__enqueued']['sn-print'] ), 'sn-print enqueued on singular post' );
ok( ( $GLOBALS['__enqueued']['sn-print']['media'] ?? null ) === 'print', 'sn-print enqueued with media=print' );
ok( strpos( (string) ( $GLOBALS['__enqueued']['sn-print']['src'] ?? '' ), 'assets/css/print.css' ) !== false, 'sn-print src points at assets/css/print.css' );
ok( ( $GLOBALS['__enqueued']['sn-print']['ver'] ?? null ) !== false, 'sn-print carries a cache-bust version (sn_asset_ver)' );

// ── Singular page → sn-print enqueued too ──
reset_enqueue();
$GLOBALS['__singular'] = array( 'page' => true );
sn_enqueue_print_styles();
ok( isset( $GLOBALS['__enqueued']['sn-print'] ), 'sn-print enqueued on singular page' );

// ── Non-singular (archive / front) → NOT enqueued ──
reset_enqueue();
$GLOBALS['__singular'] = array();
sn_enqueue_print_styles();
ok( ! isset( $GLOBALS['__enqueued']['sn-print'] ), 'sn-print NOT enqueued when not singular' );

// ── Singular of an unrelated type → NOT enqueued (posts + pages only) ──
reset_enqueue();
$GLOBALS['__singular'] = array( 'attachment' => true );
sn_enqueue_print_styles();
ok( ! isset( $GLOBALS['__enqueued']['sn-print'] ), 'sn-print NOT enqueued on non-post/page singular' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
