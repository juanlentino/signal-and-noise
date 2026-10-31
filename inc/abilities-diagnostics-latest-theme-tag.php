<?php
/**
 * Abilities API: the get-latest-theme-tag callback.
 *
 * Moved verbatim out of inc/abilities-diagnostics.php, which loads it.
 *
 * @package SignalNoise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ability execute_callback for signal-and-noise/get-latest-theme-tag.
 *
 * Delegates to sn_gh_latest_theme_tag() in inc/wp-update-integration.php.
 * That function handles the GitHub API call + caching + filter dispatch
 * to the plugin (via sn_gh_latest_theme_tag_result contract listener).
 *
 * @since 9.9.0
 * @param mixed $input { force_refresh?: bool } or null.
 * @return array{ok:bool,tag:?string}
 */
function sn_theme_ability_get_latest_theme_tag( $input ) {
	if ( ! function_exists( 'sn_gh_latest_theme_tag' ) ) {
		return array( 'ok' => false, 'tag' => null );
	}
	// force_refresh triggers a fresh outbound GitHub API call. Honor it ONLY for
	// operators (manage_options) so a read-capable subscriber cannot drive
	// unthrottled outbound calls via the core /wp-abilities run-path. Everyone
	// else gets the cached tag, keeping the ability readable for agents.
	$force_refresh = is_array( $input ) && ! empty( $input['force_refresh'] ) && current_user_can( 'manage_options' );
	$tag = sn_gh_latest_theme_tag( $force_refresh );
	if ( ! is_string( $tag ) || '' === $tag ) {
		return array( 'ok' => false, 'tag' => null );
	}
	return array( 'ok' => true, 'tag' => $tag );
}
