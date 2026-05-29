<?php
/**
 * Signal & Noise — WP-native update integration.
 *
 * Hooks into WordPress's standard update system so this theme appears
 * in wp-admin/update-core.php and Appearance → Themes alongside other
 * themes. Polls the GitHub Tags API every 12h (cached in a site
 * transient) to compare local version against the latest tagged release.
 *
 * Both install paths now coexist (since v8.5.2):
 *   - **Canonical:** `gh workflow run deploy.yml --ref vX.Y.Z --repo juanlentino/signal-and-noise`
 *     → Cloudways /api/v1/git/pull → fast, well-understood, preserves .git
 *   - **WP UI:** wp-admin → Updates → Update Now
 *     → WP downloads GitHub tag ZIP → upgrader_source_selection rename
 *     (below) drops the version suffix → install + .git preserved by
 *     inc/wp-update-git-preservation.php pre/post-install filters
 *
 * The .git preservation work was the missing piece. Without it, clicking
 * "Update Now" destroyed the .git directory (via WP_Upgrader's recursive
 * clear_destination) and broke the next gh workflow_dispatch deploy. The
 * paths shared a destination dir but no coordination protocol. v8.5.0
 * added a WP_Error gate to block WP UI installs entirely; v8.5.1 removed
 * the gate without solving the underlying problem; v8.5.2 added the
 * pre/post-install filter pair that backs up + restores .git atomically.
 *
 * What this file provides:
 *   - Version visibility in wp-admin (badge + "Up to date" / "Update
 *     Available" on update-core.php and Appearance → Themes).
 *   - Health signal — local Version != GitHub latest means no update
 *     has landed yet via either path.
 *   - GitHub tag → install via upgrader_source_selection rename so WP
 *     installs to the correct stylesheet slug.
 *
 * Added in v8.5.0 (2026-05-16). Reworked in v8.5.1 (2026-05-16) to
 * remove the WP_Error gate. v8.5.2 (2026-05-16) extracted .git
 * preservation into inc/wp-update-git-preservation.php — the missing
 * piece that makes the WP UI install path actually safe.
 *
 * @package SignalNoise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const SN_GH_THEME_OWNER         = 'juanlentino';
const SN_GH_THEME_REPO          = 'signal-and-noise';
const SN_GH_THEME_CACHE_KEY     = 'sn_gh_latest_theme';
const SN_GH_THEME_CACHE_TTL     = HOUR_IN_SECONDS; // v8.5.3: 12h → 1h (mirrors plugin v1.11.1)
const SN_GH_THEME_STYLESHEET    = 'signal-and-noise';
const SN_GH_THEME_LAST_SEEN_OPT = 'sn_last_seen_theme_version';

/**
 * Fetch the highest semver-formatted tag from GitHub. Returns the tag
 * string (e.g. "v8.5.0") on success, null on error / no matching tags.
 * Cached for SN_GH_THEME_CACHE_TTL; empty sentinel cached 1h on failure.
 *
 * @param bool $force_refresh When true, bypass the cache and re-fetch.
 *                            Used when WP's "Check Again" button is
 *                            clicked (WP_FORCE_UPDATE_CHECK constant
 *                            or `?force-check=1` query arg). Added v8.5.3.
 */
function sn_gh_latest_theme_tag( $force_refresh = false ) {
	if ( ! $force_refresh ) {
		$cached = get_site_transient( SN_GH_THEME_CACHE_KEY );
		if ( $cached !== false ) {
			return $cached === '' ? null : $cached;
		}
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
 * v4.1.1 (X-01 in plugin's audit): expose sn_gh_latest_theme_tag() as a
 * filter so the companion plugin (signal-and-noise-tools) can fetch the
 * latest theme tag without calling a theme function directly. Per
 * WORDPRESS-REFERENCE.md §10, plugin → theme calls go through filter/action
 * contracts — never function_exists guards on theme functions. The plugin
 * dispatches apply_filters('sn_gh_latest_theme_tag_result', null) and
 * this filter substitutes the GitHub tag. Returns null when the call fails
 * (same shape as the function itself), so the plugin's deploy-status card
 * degrades gracefully if the theme is absent/inactive.
 */
add_filter( 'sn_gh_latest_theme_tag_result', 'sn_gh_latest_theme_tag' );

/**
 * Register the theme with WP's update transient. WP renders it on
 * wp-admin/update-core.php and Appearance → Themes from this data.
 *
 * Theme update transient shape: `->response` and `->no_update` arrays
 * keyed by stylesheet, values are associative arrays (not stdClass as
 * the plugin transient uses). See WP core's _maybe_update_themes().
 */
add_filter( 'pre_set_site_transient_update_themes', function( $transient ) {
	if ( empty( $transient ) || ! is_object( $transient ) ) {
		$transient = new stdClass();
	}

	// v8.5.3: honor WP's "Check Again" button. WP sets the WP_FORCE_UPDATE_CHECK
	// constant during the wp-admin/update-core.php?force-check=1 flow.
	// Without this, our cached value persists even when the user explicitly
	// asks for a fresh check. Mirrors plugin v1.11.1.
	$force_refresh = ( defined( 'WP_FORCE_UPDATE_CHECK' ) && WP_FORCE_UPDATE_CHECK )
		|| ! empty( $_GET['force-check'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only cache-buster; presence-only boolean, no state change.

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

/**
 * On every admin pageview, check whether the on-disk theme version
 * differs from the last-seen version. If it does, clear the update
 * transient — the cached "latest" was relative to the previous
 * version and is now stale.
 *
 * Handles the upgrade-just-happened case automatically:
 * - WP UI install completes → next admin pageview clears the cache
 * - workflow_dispatch deploy lands → next admin pageview clears the cache
 *
 * Costs one get_option() call per admin pageview. Negligible.
 *
 * Added in v8.5.3 (2026-05-16). Mirrors plugin v1.11.1's admin_init
 * cache-invalidation handler.
 */
add_action( 'admin_init', function() {
	$last_seen = (string) get_option( SN_GH_THEME_LAST_SEEN_OPT, '' );
	$current   = (string) wp_get_theme( SN_GH_THEME_STYLESHEET )->get( 'Version' );
	if ( $current && $last_seen !== $current ) {
		delete_site_transient( SN_GH_THEME_CACHE_KEY );
		// Also clear WP's own theme update transient so the next poll
		// re-fetches fresh data (covers the case where WP cached our
		// pre-update version as "latest").
		delete_site_transient( 'update_themes' );
		// v8.5.4: also clear the parsed-themes-headers cache so the
		// Appearance → Themes screen renders the current theme header
		// (Name, Description, Author) instead of the cached pre-update
		// values. Required because our SSH-checkout deploy path doesn't
		// trigger WP's installer (which would call wp_clean_themes_cache
		// automatically). Bug surfaced when the theme name in style.css
		// had a literal &amp; entity that double-encoded on display;
		// fixing the header alone wasn't enough — the cache was stale.
		if ( function_exists( 'wp_clean_themes_cache' ) ) {
			wp_clean_themes_cache();
		}
		update_option( SN_GH_THEME_LAST_SEEN_OPT, $current );
	}
} );
