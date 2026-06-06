<?php
/**
 * Verifies the v9.9.0 WP-version admin notice renders correctly under the
 * dispatch matrix: WP version < 7.0, dismissed state, capability gate, >=7.0.
 *
 * Mirrors the plugin's tests/wp-version-admin-notice.php with theme function
 * names + the theme dismissal key. NOTE: version_compare() is a PHP builtin
 * and is intentionally NOT stubbed (the plugin's plan had that bug); the WP
 * version is steered via $GLOBALS['SN_THEME_WP_VERSION_OVERRIDE'].
 *
 * @since theme v9.9.0
 */

// SECURITY: Prevent web access. Test fixture, not a runtime module.
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) {
	http_response_code( 404 );
	exit;
}

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/' );
}

$GLOBALS['__test_caps']      = array( 'manage_options' => true );
$GLOBALS['__test_user_id']   = 1;
$GLOBALS['__test_user_meta'] = array();

if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can( $cap ) { return ! empty( $GLOBALS['__test_caps'][ $cap ] ); }
}
if ( ! function_exists( 'get_current_user_id' ) ) {
	function get_current_user_id() { return $GLOBALS['__test_user_id']; }
}
if ( ! function_exists( 'get_user_meta' ) ) {
	function get_user_meta( $uid, $key, $single = false ) {
		return $GLOBALS['__test_user_meta'][ $uid ][ $key ] ?? '';
	}
}
if ( ! function_exists( 'update_user_meta' ) ) {
	function update_user_meta( $uid, $key, $val ) {
		$GLOBALS['__test_user_meta'][ $uid ][ $key ] = $val;
		return true;
	}
}
if ( ! function_exists( 'add_action' ) ) {
	function add_action( ...$args ) { /* no-op */ }
}
if ( ! function_exists( 'wp_nonce_url' ) ) {
	function wp_nonce_url( $url, $action = -1 ) { return $url . '&_wpnonce=stub'; }
}
if ( ! function_exists( 'add_query_arg' ) ) {
	function add_query_arg( $key, $val ) { return '?' . $key . '=' . $val; }
}
if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
}
if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( $u ) { return (string) $u; }
}

require_once dirname( __DIR__ ) . '/inc/admin-notice-wp-version.php';

$pass = 0; $fail = 0;
echo "WP version admin notice — theme v9.9.0\n\n";

// Test 1: WP < 7.0, no dismissal, manage_options — notice renders.
$GLOBALS['SN_THEME_WP_VERSION_OVERRIDE'] = '6.4.2';
ob_start();
sn_theme_render_wp_version_notice();
$out = ob_get_clean();
if ( str_contains( $out, 'v10.0.0 will require WordPress 7.0' ) && str_contains( $out, '6.4.2' ) ) {
	echo "  PASS: Test 1: notice renders for WP < 7.0\n"; $pass++;
} else {
	echo "  FAIL: Test 1: notice did NOT render. Got: " . substr( $out, 0, 120 ) . "\n"; $fail++;
}

// Test 2: WP >= 7.0 → notice suppressed.
$GLOBALS['SN_THEME_WP_VERSION_OVERRIDE'] = '7.0';
ob_start();
sn_theme_render_wp_version_notice();
$out = ob_get_clean();
if ( '' === $out ) {
	echo "  PASS: Test 2: WP >= 7.0 suppresses notice\n"; $pass++;
} else {
	echo "  FAIL: Test 2: WP 7.0 rendered notice: " . substr( $out, 0, 120 ) . "\n"; $fail++;
}

// Test 3: WP < 7.0 + user dismissed → notice suppressed.
$GLOBALS['SN_THEME_WP_VERSION_OVERRIDE'] = '6.4.2';
$GLOBALS['__test_user_meta'][1]['sn_theme_dismissed_wp_version_notice_v990'] = '1';
ob_start();
sn_theme_render_wp_version_notice();
$out = ob_get_clean();
if ( '' === $out ) {
	echo "  PASS: Test 3: dismissal sentinel suppresses notice\n"; $pass++;
} else {
	echo "  FAIL: Test 3: dismissed but rendered: " . substr( $out, 0, 120 ) . "\n"; $fail++;
}

// Test 4: WP < 7.0, un-dismissed, but non-admin user → notice suppressed.
$GLOBALS['__test_user_meta'][1]['sn_theme_dismissed_wp_version_notice_v990'] = '';
$GLOBALS['__test_caps'] = array();
ob_start();
sn_theme_render_wp_version_notice();
$out = ob_get_clean();
if ( '' === $out ) {
	echo "  PASS: Test 4: non-admin does not see notice\n"; $pass++;
} else {
	echo "  FAIL: Test 4: non-admin saw notice: " . substr( $out, 0, 120 ) . "\n"; $fail++;
}

echo "\n$pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
