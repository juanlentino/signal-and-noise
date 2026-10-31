<?php
/**
 * WP update integration: the update offer (core's Update URI hook) and the
 * read-time stale guard on `update_themes`. Loaded by inc/wp-update-integration.php.
 *
 * @package SignalNoise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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

/**
 * Offer this theme's update through core's own Update URI hook (2026-10-31).
 *
 * style.css names the repository in `Update URI:`, so core's
 * wp_update_themes() (6.1+) asks `update_themes_github.com` for this theme
 * once per check, then places the answer in `response` or `no_update` itself
 * and sets `id` and `new_version`. Before, a pre_set_site_transient filter
 * rebuilt the entry on every write of `update_themes` (core writes it twice
 * per check). One difference: core skips the hook when its WordPress.org
 * request fails, so a release published during an outage shows on the next
 * check (or Check Again), not that one; the previous entry stays meanwhile.
 *
 * The hook fires for every theme whose Update URI is on github.com, so any
 * other stylesheet passes through untouched.
 *
 * @param array|false $update     The update so far (core passes false).
 * @param array       $theme_data Theme headers (unused).
 * @param string      $stylesheet The theme being checked.
 * @return array|false
 */
function sn_gh_theme_update_offer( $update, $theme_data = array(), $stylesheet = '' ) {
	if ( SN_GH_THEME_STYLESHEET !== $stylesheet ) {
		return $update;
	}
	$latest_tag = sn_gh_latest_theme_tag( sn_gh_theme_force_refresh_requested() );
	if ( null === $latest_tag ) {
		return $update;
	}
	return array(
		'theme'   => SN_GH_THEME_STYLESHEET,
		'version' => ltrim( $latest_tag, 'v' ),
		'url'     => 'https://github.com/' . SN_GH_THEME_OWNER . '/' . SN_GH_THEME_REPO,
		'package' => sn_gh_theme_package_url( $latest_tag ),
	);
}
add_filter( 'update_themes_github.com', 'sn_gh_theme_update_offer', 10, 3 );

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
