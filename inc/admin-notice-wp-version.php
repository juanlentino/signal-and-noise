<?php
/**
 * Signal & Noise theme — WP version pre-warning admin notice (v9.9.0).
 *
 * Dismissible admin notice rendered on every wp-admin page when the
 * current WP version is < 7.0. Announces that v10.0.0 will require WP 7.0.
 *
 * Persisted via user-meta key `sn_theme_dismissed_wp_version_notice_v990`
 * so each admin user can dismiss independently. The version suffix allows
 * future minors to re-introduce the notice if needed.
 *
 * v10.0.0 hard-raises Requires at least: 7.0, after which this file is
 * deleted entirely (along with its require_once in functions.php).
 *
 * Mirrors the plugin's inc/admin-notice-wp-version.php pattern. Kept
 * theme-namespaced to avoid collision when both are loaded on the same
 * WP install.
 *
 * @package SignalNoise
 * @since 9.9.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Test seam: returns the current WordPress version.
 *
 * Wraps global $wp_version so tests can stub it. Test stub override:
 * if $GLOBALS['SN_THEME_WP_VERSION_OVERRIDE'] is non-empty, returns it.
 * (Global, not constant — constants are immutable and tests need to
 * exercise both <7.0 and >=7.0 paths in a single run.)
 *
 * @since 9.9.0
 * @return string e.g. "6.4.2" or "7.0".
 */
function sn_theme_get_wp_version() {
	if ( ! empty( $GLOBALS['SN_THEME_WP_VERSION_OVERRIDE'] ) ) {
		return (string) $GLOBALS['SN_THEME_WP_VERSION_OVERRIDE'];
	}
	global $wp_version;
	return is_string( $wp_version ) ? $wp_version : '0.0';
}

/**
 * Renders the WP < 7.0 admin notice if applicable.
 *
 * Hooked to admin_notices. Bails when:
 *   - WP version is >= 7.0
 *   - Current user has dismissed via user-meta sentinel
 *   - User lacks manage_options
 *
 * @since 9.9.0
 */
function sn_theme_render_wp_version_notice() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	if ( version_compare( sn_theme_get_wp_version(), '7.0', '>=' ) ) {
		return;
	}
	$user_id = get_current_user_id();
	if ( $user_id && get_user_meta( $user_id, 'sn_theme_dismissed_wp_version_notice_v990', true ) ) {
		return;
	}

	$dismiss_url = wp_nonce_url(
		add_query_arg( 'sn_theme_dismiss_wp_version_notice', '1' ),
		'sn_theme_dismiss_wp_version_notice'
	);

	printf(
		'<div class="notice notice-warning is-dismissible"><p><strong>Signal &amp; Noise theme:</strong> v10.0.0 will require WordPress 7.0 (you are on %s). Plan your upgrade — the v10.0.0 release will refuse to load on WP &lt; 7.0.</p><p><a href="%s">Dismiss this notice</a></p></div>',
		esc_html( sn_theme_get_wp_version() ),
		esc_url( $dismiss_url )
	);
}
add_action( 'admin_notices', 'sn_theme_render_wp_version_notice' );

/**
 * Handles dismiss-link click — records user-meta sentinel.
 *
 * @since 9.9.0
 */
function sn_theme_handle_wp_version_notice_dismiss() {
	if ( empty( $_GET['sn_theme_dismiss_wp_version_notice'] ) ) {
		return;
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	check_admin_referer( 'sn_theme_dismiss_wp_version_notice' );

	update_user_meta( get_current_user_id(), 'sn_theme_dismissed_wp_version_notice_v990', '1' );

	wp_safe_redirect( remove_query_arg( array( 'sn_theme_dismiss_wp_version_notice', '_wpnonce' ) ) );
	exit;
}
add_action( 'admin_init', 'sn_theme_handle_wp_version_notice_dismiss' );
