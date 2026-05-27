<?php
/**
 * Standalone fixture tests for v9.5.0's cross-package listener
 * contracts (theme-side consumer locks).
 *
 * Verifies the 4 filter contracts the theme exposes to the companion
 * plugin (signal-and-noise-tools v4.4.0+):
 *
 *   1. sn_purge_all_caches_result          → inc/template-maintenance.php
 *   2. sn_clear_template_overrides_result  → inc/template-maintenance.php
 *   3. sn_og_font_paths                    → inc/og-fonts.php
 *   4. sn_gh_latest_theme_tag_result       → inc/wp-update-integration.php
 *
 * For each contract:
 *   - Assert that the listener registers itself when its module loads
 *     (the add_filter() call must fire on require_once).
 *   - Assert that applying the filter returns a value of the documented
 *     shape — int for purge/clear/tag-coalesced-result, array for font
 *     paths, string|null for the GitHub tag.
 *
 * This is the consumer-side seal that mirrors the plugin's
 * tests/contracts-stub.php (producer-side, 20 assertions). Plugin side
 * locks "the dispatch shape stays as published"; this side locks "the
 * theme keeps providing what the plugin expects."
 *
 * @since theme v9.5.0
 */

// SECURITY: CLI-only. Same guard pattern as tests/abilities-integration.php.
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) {
	http_response_code( 404 );
	exit;
}

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/' );
}

// ─── WP function stubs — minimal mock layer ─────────────────────────
// add_filter / apply_filters go through a global registry so each test
// can inspect what registered + what value flows through.

$GLOBALS['__test_filters']  = array();
$GLOBALS['__test_actions']  = array();
$GLOBALS['__test_options']  = array();

if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		$GLOBALS['__test_filters'][ $hook ][] = $callback;
		return true;
	}
}
if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $hook, $value /*, ...args */ ) {
		$args      = func_get_args();
		$callbacks = $GLOBALS['__test_filters'][ $hook ] ?? array();
		foreach ( $callbacks as $cb ) {
			$args[1] = call_user_func_array( $cb, array_slice( $args, 1 ) );
		}
		return $args[1];
	}
}
if ( ! function_exists( 'add_action' ) ) {
	function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		$GLOBALS['__test_actions'][ $hook ][] = $callback;
		return true;
	}
}
if ( ! function_exists( 'do_action' ) ) {
	function do_action( $hook /*, ...args */ ) {
		$args      = array_slice( func_get_args(), 1 );
		$callbacks = $GLOBALS['__test_actions'][ $hook ] ?? array();
		foreach ( $callbacks as $cb ) {
			call_user_func_array( $cb, $args );
		}
	}
}
if ( ! function_exists( 'get_option' ) ) {
	function get_option( $key, $default = false ) {
		return $GLOBALS['__test_options'][ $key ] ?? $default;
	}
}
if ( ! function_exists( 'update_option' ) ) {
	function update_option( $key, $val ) {
		$GLOBALS['__test_options'][ $key ] = $val;
		return true;
	}
}
if ( ! function_exists( 'delete_option' ) ) {
	function delete_option( $key ) {
		unset( $GLOBALS['__test_options'][ $key ] );
		return true;
	}
}
if ( ! function_exists( 'wp_parse_args' ) ) {
	function wp_parse_args( $args, $defaults = array() ) {
		if ( is_array( $args ) ) {
			return array_merge( $defaults, $args );
		}
		return $defaults;
	}
}
if ( ! function_exists( 'get_theme_file_path' ) ) {
	function get_theme_file_path( $rel = '' ) {
		return dirname( __DIR__ ) . '/' . ltrim( (string) $rel, '/' );
	}
}
if ( ! function_exists( 'get_posts' ) ) {
	function get_posts( $args ) { return array(); }
}
if ( ! function_exists( 'wp_delete_post' ) ) {
	function wp_delete_post( $id, $force = false ) { return true; }
}
if ( ! function_exists( 'wp_cache_flush' ) ) {
	function wp_cache_flush() { return true; }
}
if ( ! function_exists( 'wp_die' ) ) {
	function wp_die( $msg = '' ) { throw new RuntimeException( (string) $msg ); }
}
if ( ! function_exists( 'wp_get_theme' ) ) {
	function wp_get_theme() {
		return new class {
			public function get( $field ) { return '9.4.6'; }
		};
	}
}
if ( ! function_exists( 'wp_remote_get' ) ) {
	function wp_remote_get( $url, $args = array() ) {
		// Return a stub HTTP response. Tests for sn_gh_latest_theme_tag
		// should NOT actually fetch GitHub; they verify the filter wiring.
		return array(
			'response' => array( 'code' => 500 ),
			'body'     => '',
		);
	}
}
if ( ! function_exists( 'wp_remote_retrieve_response_code' ) ) {
	function wp_remote_retrieve_response_code( $r ) {
		return is_array( $r ) ? ( $r['response']['code'] ?? 0 ) : 0;
	}
}
if ( ! function_exists( 'wp_remote_retrieve_body' ) ) {
	function wp_remote_retrieve_body( $r ) {
		return is_array( $r ) ? ( $r['body'] ?? '' ) : '';
	}
}
if ( ! function_exists( 'set_transient' ) ) {
	function set_transient( $k, $v, $ttl ) { return true; }
}
if ( ! function_exists( 'get_transient' ) ) {
	function get_transient( $k ) { return false; }
}
if ( ! function_exists( 'delete_transient' ) ) {
	function delete_transient( $k ) { return true; }
}
if ( ! function_exists( 'esc_url_raw' ) ) {
	function esc_url_raw( $u ) { return (string) $u; }
}
if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $v ) { return false; }
}
if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
	define( 'HOUR_IN_SECONDS', 3600 );
}
if ( ! defined( 'DAY_IN_SECONDS' ) ) {
	define( 'DAY_IN_SECONDS', 86400 );
}
if ( ! function_exists( 'delete_site_transient' ) ) {
	function delete_site_transient( $k ) { return true; }
}
if ( ! function_exists( 'get_site_transient' ) ) {
	function get_site_transient( $k ) { return false; }
}
if ( ! function_exists( 'set_site_transient' ) ) {
	function set_site_transient( $k, $v, $ttl = 0 ) { return true; }
}
if ( ! function_exists( 'wp_clean_themes_cache' ) ) {
	function wp_clean_themes_cache( $clear_update_cache = true ) { return true; }
}
if ( ! function_exists( 'wp_clean_plugins_cache' ) ) {
	function wp_clean_plugins_cache( $clear_update_cache = true ) { return true; }
}
if ( ! function_exists( 'wp_update_themes' ) ) {
	function wp_update_themes() { return true; }
}
// $wpdb global — null guard in template-maintenance.php means this stub is optional,
// but define it so the global exists and the null check short-circuits cleanly.
if ( ! isset( $GLOBALS['wpdb'] ) ) {
	$GLOBALS['wpdb'] = null;
}
if ( ! function_exists( 'home_url' ) ) {
	function home_url( $path = '', $scheme = null ) { return 'https://example.com' . $path; }
}

// ─── Load the listener modules ──────────────────────────────────────
// Each require_once triggers its module's add_filter() calls, which
// register into $GLOBALS['__test_filters'].

require_once __DIR__ . '/../inc/template-maintenance.php';
require_once __DIR__ . '/../inc/og-fonts.php';
require_once __DIR__ . '/../inc/wp-update-integration.php';

// ─── Harness ────────────────────────────────────────────────────────
$pass = 0; $fail = 0;
function cpl_eq( $e, $a, $msg ) {
	global $pass, $fail;
	if ( $e === $a ) { $pass++; echo "  PASS: $msg\n"; }
	else { $fail++; echo "  FAIL: $msg\n    Expected: " . var_export( $e, true ) . "\n    Actual:   " . var_export( $a, true ) . "\n"; }
}
function cpl_true( $c, $msg ) {
	global $pass, $fail;
	if ( $c ) { $pass++; echo "  PASS: $msg\n"; } else { $fail++; echo "  FAIL: $msg\n"; }
}
function cpl_type( $type, $val, $msg ) {
	global $pass, $fail;
	$actual_type = gettype( $val );
	if ( $type === $actual_type || ( 'NULL' === $type && null === $val ) ) {
		$pass++; echo "  PASS: $msg\n";
	} else {
		$fail++; echo "  FAIL: $msg\n    Expected: $type\n    Actual:   $actual_type\n";
	}
}

echo "Cross-package listener contracts suite — theme v9.5.0\n";

// ═════════════════════════════════════════════════════════════════════
// CONTRACT 1: sn_purge_all_caches_result
// Producer (plugin): apply_filters('sn_purge_all_caches_result', 0, $args)
// Consumer (theme): returns (int) sn_purge_all_caches($args) count
// ═════════════════════════════════════════════════════════════════════

echo "\nContract 1: sn_purge_all_caches_result\n";
cpl_true( isset( $GLOBALS['__test_filters']['sn_purge_all_caches_result'] ), 'Test 1.1: listener registered' );
cpl_true( count( $GLOBALS['__test_filters']['sn_purge_all_caches_result'] ?? array() ) === 1, 'Test 1.2: exactly one listener attached' );

$result = apply_filters( 'sn_purge_all_caches_result', 0, array( 'template_overrides' => false ) );
cpl_type( 'integer', $result, 'Test 1.3: returns int' );
cpl_true( $result >= 0, 'Test 1.4: returns non-negative int' );

// Idempotency: applying twice should not double-count (each call runs a fresh purge).
$result2 = apply_filters( 'sn_purge_all_caches_result', 0, array( 'template_overrides' => false ) );
cpl_type( 'integer', $result2, 'Test 1.5: second invocation also returns int' );

// ═════════════════════════════════════════════════════════════════════
// CONTRACT 2: sn_clear_template_overrides_result
// Producer (plugin): apply_filters('sn_clear_template_overrides_result', 0)
// Consumer (theme): returns (int) sn_clear_template_overrides() count
// ═════════════════════════════════════════════════════════════════════

echo "\nContract 2: sn_clear_template_overrides_result\n";
cpl_true( isset( $GLOBALS['__test_filters']['sn_clear_template_overrides_result'] ), 'Test 2.1: listener registered' );
cpl_true( count( $GLOBALS['__test_filters']['sn_clear_template_overrides_result'] ?? array() ) === 1, 'Test 2.2: exactly one listener attached' );

$result = apply_filters( 'sn_clear_template_overrides_result', 0 );
cpl_type( 'integer', $result, 'Test 2.3: returns int' );
cpl_true( $result >= 0, 'Test 2.4: returns non-negative int' );

// ═════════════════════════════════════════════════════════════════════
// CONTRACT 3: sn_og_font_paths
// Producer (plugin OR theme): apply_filters('sn_og_font_paths', array())
// Consumer (theme): returns array with 'bebas' + 'dmmono' absolute paths
// ═════════════════════════════════════════════════════════════════════

echo "\nContract 3: sn_og_font_paths\n";
cpl_true( isset( $GLOBALS['__test_filters']['sn_og_font_paths'] ), 'Test 3.1: listener registered' );

$paths = apply_filters( 'sn_og_font_paths', array() );
cpl_type( 'array', $paths, 'Test 3.2: returns array' );
cpl_true( isset( $paths['bebas'] ), 'Test 3.3: array has bebas key' );
cpl_true( isset( $paths['dmmono'] ), 'Test 3.4: array has dmmono key' );
cpl_type( 'string', $paths['bebas'] ?? null, 'Test 3.5: bebas value is a string (path)' );
cpl_type( 'string', $paths['dmmono'] ?? null, 'Test 3.6: dmmono value is a string (path)' );
cpl_true( strpos( $paths['bebas'] ?? '', 'BebasNeue' ) !== false, 'Test 3.7: bebas path mentions BebasNeue' );
cpl_true( strpos( $paths['dmmono'] ?? '', 'DMMono' ) !== false, 'Test 3.8: dmmono path mentions DMMono' );

// ═════════════════════════════════════════════════════════════════════
// CONTRACT 4: sn_gh_latest_theme_tag_result
// Producer (plugin): apply_filters('sn_gh_latest_theme_tag_result', null)
// Consumer (theme): returns string tag or null on fetch failure
// ═════════════════════════════════════════════════════════════════════

echo "\nContract 4: sn_gh_latest_theme_tag_result\n";
cpl_true( isset( $GLOBALS['__test_filters']['sn_gh_latest_theme_tag_result'] ), 'Test 4.1: listener registered' );
cpl_true( count( $GLOBALS['__test_filters']['sn_gh_latest_theme_tag_result'] ?? array() ) === 1, 'Test 4.2: exactly one listener attached' );

// Our wp_remote_get stub returns response code 500 → sn_gh_latest_theme_tag()
// should return null per its documented "degrades gracefully" contract.
$tag = apply_filters( 'sn_gh_latest_theme_tag_result', null );
cpl_true( null === $tag || is_string( $tag ), 'Test 4.3: returns string or null' );

// In the failure path (which our stub forces) it should be exactly null.
cpl_eq( null, $tag, 'Test 4.4: returns null on HTTP failure (stub returns 500)' );

// ═════════════════════════════════════════════════════════════════════
// META: listener-count summary across all 4 contracts.
// ═════════════════════════════════════════════════════════════════════

echo "\nMeta: contract surface summary\n";
$expected_contracts = array(
	'sn_purge_all_caches_result',
	'sn_clear_template_overrides_result',
	'sn_og_font_paths',
	'sn_gh_latest_theme_tag_result',
);
foreach ( $expected_contracts as $c ) {
	cpl_true( isset( $GLOBALS['__test_filters'][ $c ] ), "Test meta: contract '$c' has a listener" );
}

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
