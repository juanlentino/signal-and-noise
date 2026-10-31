<?php
/**
 * WP update integration: recording and reporting a failed tag fetch.
 *
 * Moved verbatim out of inc/wp-update-integration.php, which loads it.
 *
 * @package SignalNoise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Record why a fetch failed, alongside the empty-sentinel negative cache, for
 * a duration proportional to how likely the failure is to persist.
 *
 * @since theme v10.43.0
 * @param string   $reason
 * @param int|null $code
 * @return null Always null — callers `return sn_gh_theme_record_fetch_failure(...)`.
 */
function sn_gh_theme_record_fetch_failure( $reason, $code = null ) {
	$ttl = sn_gh_theme_failure_ttl( $code );
	set_site_transient( SN_GH_THEME_CACHE_KEY, '', $ttl );
	set_site_transient( SN_GH_THEME_ERROR_KEY, $reason, $ttl );
	return null;
}

/**
 * Why the last theme tag fetch failed, or '' if the last one succeeded.
 *
 * @since theme v10.43.0
 * @return string
 */
function sn_gh_latest_theme_tag_error() {
	$reason = get_site_transient( SN_GH_THEME_ERROR_KEY );
	return is_string( $reason ) ? $reason : '';
}

/**
 * Answer the companion plugin's Dashboard card.
 *
 * The plugin's snt_deploy_status_for('theme') asks
 * `apply_filters( 'sn_gh_latest_theme_tag_error_result', '' )` — a seam it
 * opened in v9.54.0 and, at the time, only implemented for itself. Without a
 * listener the filter returns '' forever and the theme's card renders a bare,
 * unexplained red dot. That is precisely what the owner stared at during the
 * 2026-07-16 outage while the plugin's card, six inches away, named the cause.
 *
 * Mirrors the tag filter's contract: the theme owns its data, the plugin owns
 * the card. (The plugin never calls a theme function directly — see
 * WORDPRESS-REFERENCE.md §10 and the sn_gh_latest_theme_tag_result seam.)
 *
 * @since theme v10.43.0
 */
add_filter( 'sn_gh_latest_theme_tag_error_result', function ( $reason ) {
	$own = sn_gh_latest_theme_tag_error();
	return '' !== $own ? $own : $reason;
} );
