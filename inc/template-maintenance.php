<?php
/**
 * Signal & Noise — Template override protection.
 *
 * WordPress block themes store Site Editor customizations as wp_template /
 * wp_template_part custom post types in the database. These override the
 * actual theme files, which means uploading an updated theme ZIP won't
 * change the site until the DB records are deleted.
 *
 * This module:
 *   - Provides sn_clear_template_overrides() for manual + admin-button use.
 *   - Auto-clears on theme activation (after_switch_theme).
 *   - Auto-clears when this theme is updated via the WP updater.
 *   - Auto-flushes theme cache on admin_init when the style.css Version
 *     header changes (catches CI/CD deploys that bypass the WP upgrader).
 *
 * @package SignalNoise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Delete all database-stored template overrides.
 * Called on theme activation and via admin button.
 */
function sn_clear_template_overrides() {
	$post_types = array( 'wp_template', 'wp_template_part', 'wp_navigation' );
	$count      = 0;

	foreach ( $post_types as $post_type ) {
		$posts = get_posts( array(
			'post_type'      => $post_type,
			'posts_per_page' => -1,
			'post_status'    => 'any',
		) );
		foreach ( $posts as $post ) {
			wp_delete_post( $post->ID, true );
			$count++;
		}
	}

	return $count;
}

/**
 * Auto-clear on theme activation (covers fresh installs + re-activations).
 */
add_action( 'after_switch_theme', function() {
	sn_clear_template_overrides();
} );

/**
 * Auto-clear when this theme is updated via the WP updater.
 */
add_action( 'upgrader_process_complete', function( $upgrader, $options ) {
	if ( 'theme' === ( $options['type'] ?? '' ) ) {
		$theme_slug = get_option( 'stylesheet' );
		$updated    = $options['themes'] ?? ( isset( $options['theme'] ) ? array( $options['theme'] ) : array() );
		if ( in_array( $theme_slug, $updated, true ) ) {
			sn_clear_template_overrides();
		}
	}
}, 10, 2 );

/**
 * Performance: Auto-flush theme cache when deployed version changes.
 *
 * CI/CD deploys bypass WordPress's upgrader hooks, so the cached theme
 * header (version, description, etc.) goes stale. This detects the
 * mismatch on the first admin page load after deploy and flushes it.
 * Zero cost on subsequent loads — only fires when the version changes.
 */
add_action( 'admin_init', function() {
	$theme          = wp_get_theme();
	$current        = $theme->get( 'Version' );
	$cached_version = get_option( 'sn_deployed_version' );

	if ( $cached_version !== $current ) {
		// Clear theme-related caches.
		delete_site_transient( 'update_themes' );
		wp_clean_themes_cache();
		wp_cache_flush();

		// Clear all template/template-part/navigation overrides.
		sn_clear_template_overrides();

		// Store new version so this only runs once per deploy.
		update_option( 'sn_deployed_version', $current, true );
	}
} );
