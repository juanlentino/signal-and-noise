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

/**
 * Robustness: detect template-file changes between deploys even when
 * the style.css Version: header doesn't change.
 *
 * Why this exists: project policy (per CLAUDE.md) reserves Version:
 * bumps for code/functional changes and discourages bumping for
 * "content-only template edits". But the Version-compare check above
 * is the trigger that clears wp_template DB overrides (Site Editor
 * customizations that mask theme-file updates). Result was a silent
 * footgun: a template file change without a Version bump deployed
 * cleanly to disk but didn't take effect on routes whose template had
 * been opened in Site Editor at any point — WP kept serving the DB
 * override version.
 *
 * Fix: track the most-recent mtime among template files and clear
 * overrides whenever it advances. Self-healing on every deploy that
 * touches templates, regardless of Version bump policy.
 *
 * Implementation notes: glob() of templates/*.html is cheap (<10
 * files); filemtime() is a single stat per file. Total cost on every
 * admin_init when no change has occurred is microseconds. Only fires
 * `sn_clear_template_overrides()` when a real change is detected, so
 * admin Site Editor edits made between deploys aren't repeatedly
 * nuked — they survive until the next theme-file change.
 */
add_action( 'admin_init', function() {
	$templates_dir = get_theme_file_path( 'templates' );
	$parts_dir     = get_theme_file_path( 'parts' );

	$latest_mtime = 0;
	foreach ( array( $templates_dir, $parts_dir ) as $dir ) {
		if ( ! is_dir( $dir ) ) {
			continue;
		}
		foreach ( (array) glob( $dir . '/*.html' ) as $file ) {
			$mtime = (int) @filemtime( $file );
			if ( $mtime > $latest_mtime ) {
				$latest_mtime = $mtime;
			}
		}
	}

	if ( 0 === $latest_mtime ) {
		return; // No template files found or unreadable; nothing to do.
	}

	$cached_mtime = (int) get_option( 'sn_templates_latest_mtime', 0 );
	if ( $latest_mtime > $cached_mtime ) {
		sn_clear_template_overrides();
		update_option( 'sn_templates_latest_mtime', $latest_mtime, true );
	}
} );
