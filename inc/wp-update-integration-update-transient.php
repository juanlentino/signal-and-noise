<?php
/**
 * WP update integration: the update-transient filters.
 *
 * Moved verbatim out of inc/wp-update-integration.php, which loads it.
 *
 * @package SignalNoise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the theme with WP's update transient. WP renders it on
 * wp-admin/update-core.php and Appearance → Themes from this data.
 *
 * Theme update transient shape: `->response` and `->no_update` arrays
 * keyed by stylesheet, values are associative arrays (not stdClass as
 * the plugin transient uses). See WP core's _maybe_update_themes().
 */
/**
 * Whether a forced (cache-bypassing) update check was requested.
 *
 * WP's "Check Again" button is update-core.php?force-check=1; there is no
 * core constant for it (#332). A ?force-check= cache-bust triggers a live
 * GitHub API call — a real side effect that spends the rate-limit budget — so
 * it is gated on the update_themes capability. Without that gate, any
 * logged-in user (or a CSRF <img> pointing at an admin URL) could force
 * repeated API calls and exhaust the token's hourly budget.
 *
 * @since 10.11.2
 * @return bool
 */
function sn_gh_theme_force_refresh_requested() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only cache-buster, capability-gated on this line.
	return ! empty( $_GET['force-check'] ) && current_user_can( 'update_themes' );
}

add_filter( 'pre_set_site_transient_update_themes', function( $transient ) {
	if ( empty( $transient ) || ! is_object( $transient ) ) {
		$transient = new stdClass();
	}

	// v8.5.3: honor WP's "Check Again" button (a capability-gated
	// ?force-check= cache-bust). Without this, our cached value persists even
	// when the user explicitly asks for a fresh check.
	$force_refresh = sn_gh_theme_force_refresh_requested();

	$latest_tag = sn_gh_latest_theme_tag( $force_refresh );
	if ( $latest_tag === null ) {
		return $transient;
	}

	$latest_version  = ltrim( $latest_tag, 'v' );
	$current_version = (string) wp_get_theme( SN_GH_THEME_STYLESHEET )->get( 'Version' );

	$theme_data = array(
		'theme'       => SN_GH_THEME_STYLESHEET,
		'new_version' => $latest_version,
		'url'         => 'https://github.com/' . SN_GH_THEME_OWNER . '/' . SN_GH_THEME_REPO,
		'package'     => sn_gh_theme_package_url( $latest_tag ),
	);

	if ( version_compare( $latest_version, $current_version, '>' ) ) {
		if ( ! isset( $transient->response ) || ! is_array( $transient->response ) ) {
			$transient->response = array();
		}
		$transient->response[ SN_GH_THEME_STYLESHEET ] = $theme_data;
	} else {
		if ( ! isset( $transient->no_update ) || ! is_array( $transient->no_update ) ) {
			$transient->no_update = array();
		}
		$transient->no_update[ SN_GH_THEME_STYLESHEET ] = $theme_data;
	}

	return $transient;
} );

/**
 * Read-time guard: never report the theme as updatable to the version that
 * is already installed (the plugin's twin: sn_plugin_update_drop_stale()).
 *
 * The install request runs the OLD code to its end, and anything in it that
 * rebuilds `update_themes` writes "new version available" for the version
 * just installed. The version watchdog below clears that on the first
 * new-code request, but the desk fires many requests at once after an
 * install, so the stale write can land after the watchdog ran and stay until
 * WordPress re-checks. Checking on READ makes every reader right whoever
 * wrote the record: an entry whose new_version is not newer than the
 * installed Version moves from `response` to `no_update`. A copy is returned;
 * the object read is never changed.
 *
 * @param mixed $transient The update_themes site transient as read.
 * @return mixed
 */
function sn_gh_theme_update_drop_stale( $transient ) {
	if ( ! is_object( $transient ) || ! isset( $transient->response ) || ! is_array( $transient->response ) ) {
		return $transient;
	}
	$entry = $transient->response[ SN_GH_THEME_STYLESHEET ] ?? null;
	if ( ! is_array( $entry ) || ! isset( $entry['new_version'] ) ) {
		return $transient;
	}
	$installed = (string) wp_get_theme( SN_GH_THEME_STYLESHEET )->get( 'Version' );
	if ( '' === $installed || version_compare( (string) $entry['new_version'], $installed, '>' ) ) {
		return $transient;
	}
	$out = clone $transient;
	unset( $out->response[ SN_GH_THEME_STYLESHEET ] );
	$out->no_update                            = isset( $out->no_update ) && is_array( $out->no_update ) ? $out->no_update : array();
	$out->no_update[ SN_GH_THEME_STYLESHEET ] = $entry;
	return $out;
}
add_filter( 'site_transient_update_themes', 'sn_gh_theme_update_drop_stale' );
