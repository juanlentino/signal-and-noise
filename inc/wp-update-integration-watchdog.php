<?php
/**
 * WP update integration: the version watchdog.
 *
 * Moved verbatim out of inc/wp-update-integration.php, which loads it.
 *
 * @package SignalNoise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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
 * Costs one get_option() call per request. Negligible.
 *
 * HOOK CHOICE — `init`, NOT `admin_init` (v12.7.0, 2026-08-24)
 *
 * Registered on `admin_init` from v8.5.3 until v12.7.0, so it only ever fired
 * for a logged-in wp-admin pageview. WP-CLI is not an admin request and neither
 * is wp-cron, so the two contexts that most need the invalidation never got it.
 * On a site with a persistent object cache the stale value lives in Redis,
 * where `wp transient delete --all` cannot reach it either — so the
 * self-healing existed but was unreachable from the way the theme is operated.
 *
 * `init` fires in wp-admin, on the front end, under wp-cron and under WP-CLI,
 * and precedes `admin_init` in admin requests, so it strictly dominates the old
 * registration. `wp_clean_themes_cache()` lives in wp-includes/theme.php, so
 * unlike the plugin's counterpart it needs no wp-admin include.
 *
 * Added in v8.5.3 (2026-05-16). Mirrors plugin v12.25.0.
 */
function sn_gh_theme_version_watchdog() {
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
}
add_action( 'init', 'sn_gh_theme_version_watchdog' );
