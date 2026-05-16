<?php
/**
 * Signal & Noise — WP-native update integration.
 *
 * Hooks into WordPress's standard update system so this theme appears
 * in wp-admin/update-core.php alongside other plugins/themes. Polls
 * the GitHub Tags API every 12h (cached in a site transient) to
 * compare local version against the latest tagged release.
 *
 * Under normal operation (Cloudways auto-deploy on tag push, Phase 2a),
 * local always matches GitHub within ~30s of a tag push, so the UI
 * shows "up to date." If auto-deploy ever fails or hasn't caught up,
 * the UI shows "update available" — useful deploy-health indicator.
 *
 * "Update Now" is intercepted by `upgrader_pre_install`: we return a
 * WP_Error directing the maintainer to push a git tag (WP's installer
 * would overwrite the .git checkout and break subsequent auto-deploys).
 *
 * Added in v8.5.0 (2026-05-16). The version-display surface dropped in
 * Phase 2b (`inc/updater.php`, 683 LOC) is partially restored here as
 * ~70 LOC of native-WP integration — keeps the visibility, drops the
 * polling/SHA-tracking/self-heal complexity that auto-deploy obviated.
 *
 * @package SignalNoise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const SN_GH_THEME_OWNER      = 'juanlentino';
const SN_GH_THEME_REPO       = 'signal-and-noise';
const SN_GH_THEME_CACHE_KEY  = 'sn_gh_latest_theme';
const SN_GH_THEME_CACHE_TTL  = 12 * HOUR_IN_SECONDS;
const SN_GH_THEME_STYLESHEET = 'signal-and-noise';

/**
 * Fetch the highest semver-formatted tag from GitHub. Returns the tag
 * string (e.g. "v8.5.0") on success, null on error / no matching tags.
 * Cached for SN_GH_THEME_CACHE_TTL; empty sentinel cached 1h on failure.
 */
function sn_gh_latest_theme_tag() {
	$cached = get_site_transient( SN_GH_THEME_CACHE_KEY );
	if ( $cached !== false ) {
		return $cached === '' ? null : $cached;
	}

	$url      = 'https://api.github.com/repos/' . SN_GH_THEME_OWNER . '/' . SN_GH_THEME_REPO . '/tags?per_page=100';
	$response = wp_remote_get( $url, array(
		'timeout' => 8,
		'headers' => array(
			'Accept'     => 'application/vnd.github+json',
			'User-Agent' => 'WordPress; ' . home_url(),
		),
	) );

	if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
		set_site_transient( SN_GH_THEME_CACHE_KEY, '', HOUR_IN_SECONDS );
		return null;
	}

	$tags = json_decode( wp_remote_retrieve_body( $response ), true );
	if ( ! is_array( $tags ) ) {
		set_site_transient( SN_GH_THEME_CACHE_KEY, '', HOUR_IN_SECONDS );
		return null;
	}

	$highest = '';
	foreach ( $tags as $tag ) {
		$name = isset( $tag['name'] ) ? (string) $tag['name'] : '';
		if ( ! preg_match( '/^v\d+\.\d+\.\d+$/', $name ) ) {
			continue;
		}
		if ( $highest === '' || version_compare( ltrim( $name, 'v' ), ltrim( $highest, 'v' ), '>' ) ) {
			$highest = $name;
		}
	}

	if ( $highest === '' ) {
		set_site_transient( SN_GH_THEME_CACHE_KEY, '', HOUR_IN_SECONDS );
		return null;
	}

	set_site_transient( SN_GH_THEME_CACHE_KEY, $highest, SN_GH_THEME_CACHE_TTL );
	return $highest;
}

/**
 * Register the theme with WP's update transient. WP renders it on
 * wp-admin/update-core.php and Appearance → Themes from this data.
 */
add_filter( 'pre_set_site_transient_update_themes', function( $transient ) {
	if ( empty( $transient ) || ! is_object( $transient ) ) {
		$transient = new stdClass();
	}

	$latest_tag = sn_gh_latest_theme_tag();
	if ( $latest_tag === null ) {
		return $transient;
	}

	$latest_version  = ltrim( $latest_tag, 'v' );
	$current_version = (string) wp_get_theme( SN_GH_THEME_STYLESHEET )->get( 'Version' );

	$theme_data = array(
		'theme'       => SN_GH_THEME_STYLESHEET,
		'new_version' => $latest_version,
		'url'         => 'https://github.com/' . SN_GH_THEME_OWNER . '/' . SN_GH_THEME_REPO,
		'package'     => 'https://github.com/' . SN_GH_THEME_OWNER . '/' . SN_GH_THEME_REPO . '/archive/refs/tags/' . $latest_tag . '.zip',
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
 * Intercept "Update Now" for this theme. Auto-deploy is the only
 * supported installation path; WP's installer would overwrite the
 * .git checkout and break subsequent deploys.
 */
add_filter( 'upgrader_pre_install', function( $result, $hook_extra ) {
	$theme_slug = isset( $hook_extra['theme'] ) ? (string) $hook_extra['theme'] : '';
	if ( $theme_slug !== SN_GH_THEME_STYLESHEET ) {
		return $result;
	}
	return new WP_Error(
		'sn_managed_by_auto_deploy',
		sprintf(
			/* translators: %s: linked repo URL */
			'Signal &amp; Noise is managed via Cloudways auto-deploy on git tag push. To install an update, push a tag from %s — the GitHub Actions workflow handles deployment within ~30 seconds. WP\'s installer would overwrite the git checkout and break subsequent auto-deploys.',
			'<a href="https://github.com/' . SN_GH_THEME_OWNER . '/' . SN_GH_THEME_REPO . '">github.com/' . SN_GH_THEME_OWNER . '/' . SN_GH_THEME_REPO . '</a>'
		)
	);
}, 10, 2 );
