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
 * Comprehensive cache flush. Single source of truth for "make sure no
 * stale rendered HTML or stale metadata is being served anywhere".
 * Called automatically on every theme-file change (Version: bump,
 * upgrader complete, mtime advance) and from the admin "Purge All
 * Caches" / "Full Reset" buttons.
 *
 * Why this exists: prior to v7.0.0 these triggers each ran a subset
 * of the necessary clears. Specifically, `upgrader_process_complete`
 * cleared DB template overrides but didn't touch Breeze/Varnish, so
 * the origin's HTML page cache kept serving the old rendered template
 * even after a theme update wiped the override. The 2026-05-07
 * "/notes still showing one card after Update" symptom was that.
 *
 * Order matters:
 *   1. WP object cache + theme metadata cache + update_themes — these
 *      are in-process and need to be cleared first so subsequent calls
 *      don't repopulate from stale state.
 *   2. Our own sn_* transients — pruned with a targeted SQL DELETE so
 *      we don't disturb plugin transients.
 *   3. Origin HTML caches (Breeze + Varnish) via plugin action hooks.
 *      Plugin no-op if not installed; safe to call unconditionally.
 *   4. CDN cache (Cloudflare) via our own purge module — gated on
 *      having a configured token.
 *   5. DB template overrides via sn_clear_template_overrides().
 *   6. Repopulate update_themes by running our filter once, so the
 *      Updates page renders correct state instead of empty.
 *   7. Extension hook for future modules (sn_after_full_cache_flush).
 *
 * @param array $args {
 *     Optional flags. All default true.
 *     @type bool $object_cache       Flush WP object cache + theme caches.
 *     @type bool $sn_transients      Prune sn_* transients.
 *     @type bool $self_heal_state    Clear self-heal rate-limit + failures.
 *     @type bool $origin_html        Trigger Breeze / Varnish purges.
 *     @type bool $cloudflare         Trigger Cloudflare zone purge.
 *     @type bool $template_overrides Delete wp_template DB overrides.
 *     @type bool $repopulate         Re-run update_themes.
 * }
 * @return int Count of template overrides cleared (matches the legacy
 *             return signature of sn_clear_template_overrides()).
 */
function sn_purge_all_caches( $args = array() ) {
	$args = wp_parse_args( $args, array(
		'object_cache'       => true,
		'sn_transients'      => true,
		'self_heal_state'    => true,
		'origin_html'        => true,
		'cloudflare'         => true,
		'template_overrides' => true,
		'repopulate'         => true,
	) );

	if ( $args['object_cache'] ) {
		wp_cache_flush();
		delete_site_transient( 'update_themes' );
		wp_clean_themes_cache();
	}

	if ( $args['sn_transients'] ) {
		global $wpdb;
		if ( $wpdb ) {
			$wpdb->query(
				"DELETE FROM {$wpdb->options}
				 WHERE option_name LIKE '\\_transient\\_sn\\_%'
				    OR option_name LIKE '\\_transient\\_timeout\\_sn\\_%'"
			);
		}
	}

	if ( $args['self_heal_state'] ) {
		// Self-heal rate-limit + failure cooldown are stored as regular
		// options (not transients), so the sn_transients DELETE above
		// doesn't reach them. Clearing them here means Full Reset / any
		// caller of sn_purge_all_caches() unblocks the next admin
		// pageview to run a fresh self-heal — closing the recovery loop
		// for "I clicked Full Reset but page-notes.html still shows old
		// content". Constants come from inc/template-self-heal.php; gate
		// on defined() so this stays safe if that module is ever
		// disabled.
		if ( defined( 'SN_SELF_HEAL_LAST_CHECK_OPT' ) ) {
			delete_option( SN_SELF_HEAL_LAST_CHECK_OPT );
		}
		if ( defined( 'SN_SELF_HEAL_FAILURES_OPT' ) ) {
			delete_option( SN_SELF_HEAL_FAILURES_OPT );
		}
	}

	if ( $args['origin_html'] ) {
		// Plugin action hooks — no-op if Breeze isn't installed.
		do_action( 'breeze_clear_all_cache' );
		do_action( 'breeze_clear_varnish' );
	}

	if ( $args['cloudflare'] && function_exists( 'sn_cf_purge_everything' ) ) {
		// Gated on configuration internally; no-op if no token/zone set.
		sn_cf_purge_everything();
	}

	$cleared = 0;
	if ( $args['template_overrides'] ) {
		$cleared = sn_clear_template_overrides();
	}

	if ( $args['repopulate'] ) {
		// Re-run the update_themes filter so subsequent admin pageloads
		// see correct state instead of the empty-transient false-positive
		// "all up to date".
		wp_update_themes();
	}

	do_action( 'sn_after_full_cache_flush', $args, $cleared );

	return $cleared;
}

/**
 * Delete all database-stored template overrides.
 * Called on theme activation, via admin button, and from
 * sn_purge_all_caches().
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
 * Auto-purge everything when this theme is updated via the WP updater.
 *
 * Pre-v7.0.0 this only called sn_clear_template_overrides() — but DB
 * override clearing alone leaves Breeze's HTML page cache serving stale
 * rendered output. Now calls sn_purge_all_caches() which includes
 * Breeze, Varnish, object cache, Cloudflare (if configured), AND
 * overrides. One responsible call instead of a partial subset.
 */
add_action( 'upgrader_process_complete', function( $upgrader, $options ) {
	if ( 'theme' === ( $options['type'] ?? '' ) ) {
		$theme_slug = get_option( 'stylesheet' );
		$updated    = $options['themes'] ?? ( isset( $options['theme'] ) ? array( $options['theme'] ) : array() );
		if ( in_array( $theme_slug, $updated, true ) ) {
			sn_purge_all_caches();
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
		// Use the unified purge helper so Breeze/Varnish/Cloudflare also
		// clear, not just the WP-side caches that were here pre-v7.0.0.
		sn_purge_all_caches();

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
		// Same architectural rule as the Version-compare check above: any
		// theme-file change must purge ALL caches (Breeze HTML cache
		// included), not just the DB overrides. Calling the unified helper
		// keeps the two triggers in lockstep.
		sn_purge_all_caches();
		update_option( 'sn_templates_latest_mtime', $latest_mtime, true );
	}
} );

/**
 * Companion-plugin contract listeners (since v8.2.0).
 *
 * Two filter contracts owned by this module:
 *   sn_purge_all_caches_result         → count cleared (int)
 *   sn_clear_template_overrides_result → count cleared (int)
 *
 * See docs/superpowers/specs/2026-05-15-companion-plugin-phase-1-design.md
 * + docs/WORDPRESS-REFERENCE.md §10.0.
 */

/**
 * Filter listener: accept dispatched purge calls from the companion
 * plugin, run the local sn_purge_all_caches() implementation, return
 * the count cleared.
 *
 * @param int   $count Seed value (typically 0) passed by caller.
 * @param array $args  Purge args (e.g., array('template_overrides' => false)).
 * @return int Items cleared.
 */
add_filter( 'sn_purge_all_caches_result', function( $count, $args ) {
	return (int) sn_purge_all_caches( is_array( $args ) ? $args : array() );
}, 10, 2 );

/**
 * Filter listener: accept dispatched template-overrides-clear calls
 * from the companion plugin, run the local sn_clear_template_overrides()
 * implementation, return the count cleared.
 *
 * @param int $count Seed value (typically 0) passed by caller.
 * @return int DB overrides cleared.
 */
add_filter( 'sn_clear_template_overrides_result', function( $count ) {
	return (int) sn_clear_template_overrides();
} );
