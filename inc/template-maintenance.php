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
		'verified'           => false,
	) );

	// v10.23.0: symmetric with sn_after_full_cache_flush. inc/purge-verify.php
	// hooks this to bump the render epoch at the START of an edge-affecting purge,
	// so the post-purge origin re-render emits N+1 while a still-stale edge keeps
	// serving N (the differential the dashboard dot compares).
	do_action( 'sn_before_cache_flush', $args );

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

	if ( $args['cloudflare'] ) {
		// v10.23.0: a verified purge (the manual "Purge All Caches" button) routes
		// CF to the plugin's BLOCKING variant so the report can carry a real
		// {success:true} accept-confirmation; the result is stashed for the report
		// writer on sn_after_full_cache_flush. Fast auto-purges keep the
		// non-blocking fn so a save/update request never waits on the CF API.
		if ( ! empty( $args['verified'] ) && function_exists( 'sn_cf_purge_everything_verified' ) ) {
			$GLOBALS['sn_cf_verified_result'] = sn_cf_purge_everything_verified();
		} elseif ( function_exists( 'sn_cf_purge_everything' ) ) {
			// Gated on configuration internally; no-op if no token/zone set.
			sn_cf_purge_everything();
		}
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
 * Automatic purge triggers (v10.22.0).
 *
 * 2026-07-02 incident: installing theme v10.21.9 plus deleting a Styles
 * Additional-CSS rule left three cache layers (Breeze file cache → Varnish
 * → Cloudflare) serving a morning-stale render through four manual
 * layer-by-layer purges — sn_purge_all_caches() had the whole chain in the
 * right order but nothing fired it. These triggers close the gap: the two
 * events that actually change rendered HTML outside a post save (our own
 * package updates, Site Editor Styles saves) now ride the chain
 * automatically.
 */

/**
 * Full-chain purge after OUR theme or companion plugin finishes updating.
 *
 * Fires on upgrader_process_complete, which runs in the updating request
 * AFTER the new files land — old code runs this hook (the theme being
 * replaced was loaded at request start), which is fine for a purge: it
 * does not depend on new-version semantics, unlike migrations
 * (WP-REFERENCE: install hooks cannot self-observe).
 *
 * @param object $upgrader   WP_Upgrader instance (unused).
 * @param mixed  $hook_extra Package descriptor from the upgrader.
 */
function sn_auto_purge_on_update( $upgrader, $hook_extra ) {
	if ( ! is_array( $hook_extra ) || 'update' !== ( $hook_extra['action'] ?? '' ) ) {
		return;
	}
	$ours = false;
	$type = $hook_extra['type'] ?? '';
	if ( 'theme' === $type ) {
		$themes = (array) ( $hook_extra['themes'] ?? array() );
		$ours   = in_array( get_stylesheet(), $themes, true ) || in_array( get_template(), $themes, true );
	} elseif ( 'plugin' === $type ) {
		foreach ( (array) ( $hook_extra['plugins'] ?? array() ) as $file ) {
			if ( 0 === strpos( (string) $file, 'signal-and-noise-tools/' ) ) {
				$ours = true;
				break;
			}
		}
	}
	if ( ! $ours ) {
		return;
	}
	// Once per request: a batch update (theme + plugin together) fires
	// upgrader_process_complete per package. A global, not a static, so
	// the standalone tests can reset it between scenarios (the
	// sn_css_combined_memo convention).
	if ( ! empty( $GLOBALS['sn_auto_purge_done'] ) ) {
		return;
	}
	$GLOBALS['sn_auto_purge_done'] = true;
	// template_overrides=false — an update must never nuke Site Editor
	// edits as a side effect (matches the dashboard button semantics).
	sn_purge_all_caches( array( 'template_overrides' => false ) );
}
add_action( 'upgrader_process_complete', 'sn_auto_purge_on_update', 10, 2 );

/**
 * Focused origin-HTML + CDN purge when Site Editor global styles save —
 * this includes Additional CSS edits, which change every page's rendered
 * <style> block but ride NO other purge path (Breeze only watches post
 * saves; the wp_global_styles CPT is invisible to it).
 *
 * Deliberately narrow: no object-cache flush, no transient prune, no
 * update_themes churn, and never template overrides — a Styles save
 * changes rendered CSS, nothing else.
 *
 * @param int    $post_id Global-styles post ID (unused).
 * @param object $post    Post object (unused).
 */
function sn_auto_purge_on_styles_save( $post_id, $post ) {
	sn_purge_all_caches( array(
		'object_cache'       => false,
		'sn_transients'      => false,
		'template_overrides' => false,
		'repopulate'         => false,
	) );
}
add_action( 'save_post_wp_global_styles', 'sn_auto_purge_on_styles_save', 10, 2 );

/**
 * Companion-plugin contract listeners (since v8.2.0).
 *
 * Two filter contracts owned by this module:
 *   sn_purge_all_caches_result         → count cleared (int)
 *   sn_clear_template_overrides_result → count cleared (int)
 *
 * See docs/WORDPRESS-REFERENCE.md §10.0.
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
