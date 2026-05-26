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
 *   - Exposes cross-package filter contracts for the companion plugin.
 *
 * @package SignalNoise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Comprehensive cache flush. Single source of truth for "make sure no
 * stale rendered HTML or stale metadata is being served anywhere".
 * Called on theme activation and from the admin "Purge All Caches" /
 * "Full Reset" buttons, and via the companion-plugin filter contract.
 *
 * Why this exists: prior to v7.0.0 these triggers each ran a subset
 * of the necessary clears — missing Breeze/Varnish, so the origin's
 * HTML page cache kept serving the old rendered template even after a
 * theme update wiped the override. The 2026-05-07 "/notes still
 * showing one card after Update" symptom was that.
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
		'origin_html'        => true,
		'cloudflare'         => true,
		'template_overrides' => true,
		'repopulate'         => true,
	) );

	if ( $args['object_cache'] ) {
		wp_cache_flush();
		delete_site_transient( 'update_themes' );
		delete_site_transient( 'update_plugins' );   // v9.1.5: symmetric with themes
		wp_clean_themes_cache();
		wp_clean_plugins_cache();                     // v9.1.5: SSH plugin deploys leave stale get_plugin_data() cache otherwise
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

	// v9.1.6 (X-07): removed the `self_heal_state` branch. Constants
	// SN_SELF_HEAL_LAST_CHECK_OPT + SN_SELF_HEAL_FAILURES_OPT were
	// defined in inc/template-self-heal.php (retired in v8.3.0). The
	// defined() guards meant the branch was permanently dead code on
	// the current codebase — no behavior change, just removing the
	// stale reference to a retired module to reduce confusion.

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
