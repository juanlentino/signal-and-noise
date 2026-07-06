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
if ( ! function_exists( 'get_the_ID' ) ) { function get_the_ID() { return $GLOBALS['__post_id'] ?? 42; } }
$GLOBALS['__post_id'] = 42;
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

// v10.14.0: web-vitals is vendored + enqueued as a dependency of the beacon so
// window.webVitals exists before the field-CWV section runs.
$wv = $GLOBALS['__enqueued_scripts']['sn-web-vitals'] ?? null;
ok( $wv !== null, 'sn-web-vitals enqueued' );
ok( strpos( (string) ( $wv['src'] ?? '' ), 'assets/js/web-vitals.iife.js' ) !== false, 'web-vitals src points at the vendored self-hosted file' );
ok( ( $wv['args']['strategy'] ?? '' ) === 'defer' && ( $wv['args']['in_footer'] ?? false ) === true, 'web-vitals deferred + footer (matches the beacon)' );
ok( in_array( 'sn-web-vitals', (array) ( $s['deps'] ?? array() ), true ), 'sn-beacon DEPENDS on sn-web-vitals (load + execution order)' );

$inline = $GLOBALS['__inline']['sn-beacon'] ?? null;
ok( $inline !== null, 'data island injected on sn-beacon' );
ok( ( $inline['position'] ?? '' ) === 'before', 'island injected BEFORE the deferred module' );
ok( strpos( (string) ( $inline['data'] ?? '' ), 'window.SN_BEACON=' ) !== false, 'island assigns window.SN_BEACON' );
ok( strpos( (string) ( $inline['data'] ?? '' ), '"endpoint"' ) !== false, 'island carries an endpoint' );
ok( strpos( (string) ( $inline['data'] ?? '' ), '/_sn/px' ) !== false, 'endpoint is the worker route /_sn/px' );
ok( strpos( (string) ( $inline['data'] ?? '' ), '"k"' ) !== false, 'island carries the site token field k' );
ok( strpos( (string) ( $inline['data'] ?? '' ), '"id":42' ) !== false, 'island carries id:42' );

// Zero id (archives/home/404 where get_the_ID() returns 0) — island still emitted with id:0.
$GLOBALS['__post_id'] = 0;
reset_state();
sn_beacon_enqueue();
$inline_zero = $GLOBALS['__inline']['sn-beacon'] ?? null;
ok( $inline_zero !== null, 'island still emitted when id=0' );
ok( strpos( (string) ( $inline_zero['data'] ?? '' ), '"id":0' ) !== false, 'island carries id:0 (not false)' );
$GLOBALS['__post_id'] = 42;

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
// v10.14.0: field Core Web Vitals via web-vitals (guarded; sends vl/vi/vc).
ok( strpos( $js, 'window.webVitals' ) !== false, 'beacon reads window.webVitals (guarded)' );
ok( strpos( $js, 'onLCP' ) !== false && strpos( $js, 'onINP' ) !== false && strpos( $js, 'onCLS' ) !== false, 'beacon wires onLCP/onINP/onCLS' );
ok( strpos( $js, "'vl'" ) !== false && strpos( $js, "'vi'" ) !== false && strpos( $js, "'vc'" ) !== false, 'beacon sends vl/vi/vc CWV events' );
$wvjs = (string) @file_get_contents( realpath( __DIR__ . '/..' ) . '/assets/js/web-vitals.iife.js' );
ok( strpos( $wvjs, 'webVitals' ) !== false && strpos( $wvjs, 'web-vitals v4' ) !== false, 'vendored web-vitals.iife.js present with provenance header' );

// v10.4.0: custom-event API. A no-op stub MUST be assigned before the privacy
// gate's early return, so window.SN_BEACON.event(...) is always callable.
$gate_pos  = strpos( $js, 'globalPrivacyControl === true) return' );
$stub_pos  = strpos( $js, 'cfg.event = function () {}' );
ok( $stub_pos !== false, 'beacon defines a no-op cfg.event stub' );
ok( $gate_pos !== false, 'beacon has the DNT/GPC privacy gate' );
ok( $stub_pos !== false && $gate_pos !== false && $stub_pos < $gate_pos, 'no-op event stub is set BEFORE the privacy gate (callable under DNT/GPC)' );

// v10.4.0: the real sender on the tracked path. Assert its shape, the ce event
// type, the name + property/value clamps, and the 4-prop cap.
ok( strpos( $js, 'cfg.event = function (name, props)' ) !== false, 'beacon defines the real event(name, props) sender' );
ok( strpos( $js, "e: 'ce'" ) !== false || strpos( $js, 'e:"ce"' ) !== false, 'real event() sends a ce payload' );
ok( strpos( $js, 'String(name || \'\').slice(0, 64)' ) !== false || strpos( $js, 'slice(0, 64)' ) !== false, 'real event() clamps name to 64' );
ok( strpos( $js, 'slice(0, 60)' ) !== false, 'real event() clamps property key to 60' );
ok( strpos( $js, 'slice(0, 180)' ) !== false, 'real event() clamps property value to 180' );
ok( preg_match( '/c\+\+\s*>=\s*4/', $js ) === 1 || strpos( $js, '>= 4) break' ) !== false, 'real event() caps props at 4' );
ok( strpos( $js, 'if (!n) return' ) !== false || strpos( $js, 'if(!n)return' ) !== false, 'real event() ignores an empty name' );
// The real sender must come AFTER the privacy gate (reassigns the stub only on the tracked path).
$real_pos = strpos( $js, 'cfg.event = function (name, props)' );
ok( $real_pos !== false && $gate_pos !== false && $real_pos > $gate_pos, 'real event() sender is assigned AFTER the privacy gate' );

// v10.26.0: goal-action click tracking. One delegated click listener fires 'ce'
// conversion events cookielessly, AT MOST once per click (priority: explicit
// data-sn-goal / data-sn-subscribe, then RSS feed link, then cross-host outbound).
ok( substr_count( $js, "addEventListener('click'" ) === 1, 'exactly one delegated click listener (no double-fire from multiple bindings)' );
ok( strpos( $js, 'data-sn-goal' ) !== false, 'honors the data-sn-goal CTA convention (name = attribute value)' );
ok( strpos( $js, 'data-sn-subscribe' ) !== false, 'honors the data-sn-subscribe convention (target = attribute value)' );
ok( strpos( $js, "cfg.event('outbound'" ) !== false, 'fires an outbound goal event' );
ok( strpos( $js, "cfg.event('subscribe'" ) !== false, 'fires a subscribe goal event' );
ok( strpos( $js, "target: 'rss'" ) !== false, 'RSS/feed links map to subscribe target rss' );
// Outbound MUST carry the destination HOSTNAME ONLY — never the path/query (no URL PII).
ok( strpos( $js, 'host: url.hostname' ) !== false, 'outbound sends host: url.hostname (host only)' );
ok( preg_match( '/cfg\.event\(\s*\'outbound\'\s*,\s*\{\s*host:[^}]*\}\s*\)/', $js ) === 1, 'outbound props carry ONLY a host key (no path/search leak)' );
ok( strpos( $js, 'location.hostname' ) !== false, 'outbound is gated on a cross-host comparison (url.hostname !== location.hostname)' );
// First-match-wins: explicit classification precedes the automatic outbound branch,
// so a tagged link (e.g. an email-subscribe outbound link) never double-fires.
$goal_pos  = strpos( $js, "closest('[data-sn-goal]')" );
$out_pos   = strpos( $js, "cfg.event('outbound'" );
ok( $goal_pos !== false && $out_pos !== false && $goal_pos < $out_pos, 'explicit goal/subscribe classification precedes outbound (first-match-wins)' );
// The click section sits AFTER the DNT/GPC gate, so an opted-out visitor never binds it.
$click_pos = strpos( $js, "addEventListener('click'" );
ok( $click_pos !== false && $gate_pos !== false && $click_pos > $gate_pos, 'click listener is bound AFTER the DNT/GPC privacy gate' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
