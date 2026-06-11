<?php
/**
 * Standalone fixture tests for the analytics beacon enqueue (P1).
 * Stubs the WP enqueue/inline/filter funcs so the pure callback in
 * inc/beacon.php runs without a WP load. Mirrors tests/print-styles.php.
 */
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', '/' ); }
define( 'SN_BEACON_TEST', true ); // prevent auto-hooking; we call the callback directly

$GLOBALS['__enqueued_scripts'] = array();
$GLOBALS['__inline']           = array();
$GLOBALS['__filters']          = array(); // filter name => return value override

if ( ! function_exists( 'wp_enqueue_script' ) ) {
	function wp_enqueue_script( $handle, $src = '', $deps = array(), $ver = false, $args = false ) {
		$GLOBALS['__enqueued_scripts'][ $handle ] = compact( 'src', 'deps', 'ver', 'args' );
	}
}
if ( ! function_exists( 'wp_add_inline_script' ) ) {
	function wp_add_inline_script( $handle, $data, $position = 'after' ) {
		$GLOBALS['__inline'][ $handle ] = array( 'data' => $data, 'position' => $position );
	}
}
if ( ! function_exists( 'get_theme_file_uri' ) ) {
	function get_theme_file_uri( $p = '' ) { return 'https://example.test/wp-content/themes/signal-and-noise/' . ltrim( $p, '/' ); }
}
if ( ! function_exists( 'get_theme_file_path' ) ) {
	function get_theme_file_path( $p = '' ) { return realpath( __DIR__ . '/..' ) . '/' . ltrim( $p, '/' ); }
}
if ( ! function_exists( 'wp_get_theme' ) ) {
	function wp_get_theme() { return new class { public function get( $k ) { return '10.0.0'; } }; }
}
if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $name, $value ) { return array_key_exists( $name, $GLOBALS['__filters'] ) ? $GLOBALS['__filters'][ $name ] : $value; }
}
if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $d, $flags = 0 ) { return json_encode( $d, $flags ); }
}
if ( ! function_exists( 'get_the_ID' ) ) { function get_the_ID() { return 42; } }
if ( ! function_exists( 'add_action' ) ) { function add_action() {} }
if ( ! function_exists( 'add_filter' ) ) { function add_filter() {} }
if ( ! defined( 'JSON_HEX_TAG' ) ) { define( 'JSON_HEX_TAG', 1 ); }
if ( ! defined( 'JSON_UNESCAPED_SLASHES' ) ) { define( 'JSON_UNESCAPED_SLASHES', 64 ); }
if ( ! function_exists( 'sn_asset_ver' ) ) { function sn_asset_ver( $p ) { return '123'; } }

require __DIR__ . '/../inc/beacon.php';

$pass = 0; $fail = 0;
function ok( $cond, $label ) { global $pass, $fail; if ( $cond ) { $pass++; echo "PASS: $label\n"; } else { $fail++; echo "FAIL: $label\n"; } }
function reset_state() { $GLOBALS['__enqueued_scripts'] = array(); $GLOBALS['__inline'] = array(); $GLOBALS['__filters'] = array(); }

ok( function_exists( 'sn_beacon_enqueue' ), 'sn_beacon_enqueue() is defined' );
ok( function_exists( 'sn_beacon_enabled' ), 'sn_beacon_enabled() is defined' );

// Enabled by default → enqueued, footer+defer, cache-busted, island emitted.
reset_state();
sn_beacon_enqueue();
$s = $GLOBALS['__enqueued_scripts']['sn-beacon'] ?? null;
ok( $s !== null, 'sn-beacon enqueued by default' );
ok( strpos( (string) ( $s['src'] ?? '' ), 'assets/js/sn-beacon.js' ) !== false, 'src points at assets/js/sn-beacon.js' );
ok( is_array( $s['args'] ?? null ) && ( $s['args']['strategy'] ?? '' ) === 'defer', 'enqueued with strategy=defer' );
ok( is_array( $s['args'] ?? null ) && ( $s['args']['in_footer'] ?? false ) === true, 'enqueued in footer' );
ok( ( $s['ver'] ?? false ) !== false, 'carries a cache-bust version (sn_asset_ver)' );

$inline = $GLOBALS['__inline']['sn-beacon'] ?? null;
ok( $inline !== null, 'data island injected on sn-beacon' );
ok( ( $inline['position'] ?? '' ) === 'before', 'island injected BEFORE the deferred module' );
ok( strpos( (string) ( $inline['data'] ?? '' ), 'window.SN_BEACON=' ) !== false, 'island assigns window.SN_BEACON' );
ok( strpos( (string) ( $inline['data'] ?? '' ), '"endpoint"' ) !== false, 'island carries an endpoint' );
ok( strpos( (string) ( $inline['data'] ?? '' ), '/_sn/px' ) !== false, 'endpoint is the worker route /_sn/px' );
ok( strpos( (string) ( $inline['data'] ?? '' ), '"k"' ) !== false, 'island carries the site token field k' );

// Disabled via filter → nothing enqueued.
reset_state();
$GLOBALS['__filters']['sn_beacon_enabled'] = false;
sn_beacon_enqueue();
ok( ! isset( $GLOBALS['__enqueued_scripts']['sn-beacon'] ), 'NOT enqueued when sn_beacon_enabled=false' );

// JS CONTENT contract (mirrors tests/print-styles.php discography checks).
$js = (string) @file_get_contents( realpath( __DIR__ . '/..' ) . '/assets/js/sn-beacon.js' );
ok( '' !== $js, 'sn-beacon.js is readable' );
ok( strpos( $js, 'sendBeacon' ) !== false, 'beacon uses navigator.sendBeacon' );
ok( strpos( $js, 'globalPrivacyControl' ) !== false, 'beacon honors GPC' );
ok( strpos( $js, 'doNotTrack' ) !== false, 'beacon honors DNT' );
ok( strpos( $js, 'visibilitychange' ) !== false, 'beacon flushes on visibilitychange' );
ok( strpos( $js, 'pagehide' ) !== false, 'beacon flushes on pagehide' );
ok( strpos( $js, "'sc'" ) !== false || strpos( $js, '"sc"' ) !== false, 'beacon sends scroll (sc) events' );
ok( strpos( $js, "'tm'" ) !== false || strpos( $js, '"tm"' ) !== false, 'beacon sends time (tm) events' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
