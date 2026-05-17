<?php
/**
 * Signal & Noise — WP-native update integration.
 *
 * Hooks into WordPress's standard update system so this theme appears
 * in wp-admin/update-core.php and Appearance → Themes alongside other
 * themes. Polls the GitHub Tags API every 12h (cached in a site
 * transient) to compare local version against the latest tagged release.
 *
 * ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
 * STATUS: VERSION-DISPLAY ONLY. DO NOT CLICK "UPDATE NOW".
 * ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
 *
 * As of v8.5.1, this file *can* serve a working WP UI install — but in
 * practice nobody does, and clicking "Update Now" once would break the
 * canonical update path. Here's why:
 *
 *   1. The canonical install path is `gh workflow run deploy.yml --ref vX.Y.Z`,
 *      which calls Cloudways' /api/v1/git/pull (theme) or SSH `git checkout`
 *      (plugin). Both require the destination dir to be a live git checkout.
 *
 *   2. WP's own installer (WP_Upgrader::install_package() in
 *      wp-admin/includes/class-wp-upgrader.php) calls clear_destination()
 *      which runs `$wp_filesystem->delete( $remote_destination, true )`.
 *      The `true` is recursive — it deletes the `.git` directory along
 *      with everything else. After one WP UI install, the next
 *      workflow_dispatch deploy fails (no `.git` to pull into).
 *
 *   3. The two paths share a destination dir but not a coordination
 *      protocol. They're mutually exclusive. The system has been built
 *      twice (v8.5.0 added the WP-Error gate; v8.5.1 removed it) and
 *      both times the actual install path stayed git-pull.
 *
 * What this file genuinely provides:
 *   - Version visibility in wp-admin (the badge + "Up to date" / "Update
 *     Available" indicator on update-core.php and Appearance → Themes).
 *   - A health signal — if local Version != GitHub latest, the gh
 *     workflow_dispatch hasn't fired yet.
 *
 * What it does NOT provide despite appearances:
 *   - A working "Update Now" button. WP will *try* to install (the
 *     upgrader_source_selection filter below renames the unpacked
 *     archive dir correctly), but doing so destroys `.git` and breaks
 *     the canonical deploy path. The button is a footgun until the
 *     `.git` preservation work (option 2 from the 2026-05-16 v8.5.1
 *     handoff) ships as v8.5.2 or later.
 *
 * Use `gh workflow run deploy.yml --ref vX.Y.Z --repo juanlentino/signal-and-noise`
 * to ship a release. Do not click Update Now in wp-admin.
 *
 * Added in v8.5.0 (2026-05-16). Reworked in v8.5.1 (2026-05-16) to
 * remove the WP_Error gate and add upgrader_source_selection rename,
 * mirroring plugin v1.10.1. Honest docblock added 2026-05-16 after
 * realizing the gate was load-bearing — until .git preservation lands,
 * the gate's intent (block WP UI installs) is the correct behavior.
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
 * Rename the unpacked source directory so WP installs to the correct
 * stylesheet slug.
 *
 * GitHub's auto-generated tag archive (`/archive/refs/tags/v8.5.1.zip`)
 * unpacks to `signal-and-noise-8.5.1/` — with the version suffix but
 * without the leading 'v'. WP's installer uses the dir name to decide
 * where to install, which would end up as
 * `wp-content/themes/signal-and-noise-8.5.1/` (wrong slug, the theme
 * would deactivate on update because the active stylesheet
 * `signal-and-noise` would no longer resolve).
 *
 * The filter receives `$source` (path to the unpacked dir) and renames
 * it to drop the version suffix. Standard pattern for GitHub-hosted
 * themes that ship via auto-generated tag archives.
 *
 * Note: `$hook_extra['theme']` is the slug for theme installs (mirrors
 * the plugin-side filter's `$hook_extra['plugin']` basename). Guarding
 * on this prevents us from renaming other themes that pass through the
 * same filter during a multi-update batch.
 */
add_filter( 'upgrader_source_selection', function( $source, $remote_source, $upgrader, $hook_extra ) {
	$theme = isset( $hook_extra['theme'] ) ? (string) $hook_extra['theme'] : '';
	if ( $theme !== SN_GH_THEME_STYLESHEET ) {
		return $source;
	}

	$source         = trailingslashit( $source );
	$desired_source = trailingslashit( dirname( $source ) ) . SN_GH_THEME_STYLESHEET . '/';

	if ( $source === $desired_source ) {
		return $source;
	}

	global $wp_filesystem;
	if ( ! $wp_filesystem || ! $wp_filesystem->move( untrailingslashit( $source ), untrailingslashit( $desired_source ) ) ) {
		return new WP_Error(
			'sn_rename_source_failed',
			'Could not rename the unpacked theme directory from "' . esc_html( basename( $source ) ) . '" to "' . SN_GH_THEME_STYLESHEET . '". Manual install via SFTP may be required.'
		);
	}

	return $desired_source;
}, 10, 4 );
