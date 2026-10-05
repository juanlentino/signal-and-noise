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
 *   2. Transients: the transient group in Redis when a persistent object
 *      cache is in use (14.10.0), plus the old targeted sn_* SQL DELETE.
 *   3. Origin HTML caches: Breeze's page files and minified assets called
 *      directly (14.10.0; its clear-all action flushed Redis), then the
 *      breeze_clear_varnish action.
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
 *     @type bool $object_cache       Flush the whole object cache (all of Redis).
 *     @type bool $sn_transients      Prune sn_* transients.
 *     @type bool $origin_html        Trigger Breeze / Varnish purges.
 *     @type bool $cloudflare         Trigger Cloudflare zone purge.
 *     @type bool $template_overrides Delete wp_template DB overrides.
 *     @type bool $repopulate         Re-run update_themes.
 *     @type bool $package_caches     update_themes/update_plugins and the
 *                                    plugin/theme metadata caches (14.10.0).
 *     @type string $trigger          Who asked: manual, update, styles (read
 *                                    by the companion plugin's purge ledger).
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
		// 14.10.0: the update-scoped caches (update_themes, update_plugins and
		// the plugin/theme metadata), separate from the whole-Redis flush.
		'package_caches'     => true,
		'trigger'            => 'manual',
	) );

	// v10.23.0: symmetric with sn_after_full_cache_flush. inc/purge-verify.php
	// hooks this to bump the render epoch at the START of an edge-affecting purge,
	// so the post-purge origin re-render emits N+1 while a still-stale edge keeps
	// serving N (the differential the dashboard dot compares).
	do_action( 'sn_before_cache_flush', $args );

	// 15.0.1 (Codex on #470): both flushes below delete Core's doing_cron
	// transient, the lock that keeps two cron runs from overlapping. An
	// automatic update or the rollover runs INSIDE cron, so put it back.
	$cron_lock = function_exists( 'get_transient' ) ? get_transient( 'doing_cron' ) : false;

	$restore_cron_lock = static function () use ( $cron_lock ) {
		if ( false !== $cron_lock && function_exists( 'set_transient' ) ) {
			set_transient( 'doing_cron', $cron_lock ); // Core sets it with no expiry too.
		}
	};

	if ( $args['object_cache'] ) {
		wp_cache_flush();
		$restore_cron_lock(); // Right away: a request in between would see no lock.
	}

	if ( $args['package_caches'] ) {
		delete_site_transient( 'update_themes' );
		delete_site_transient( 'update_plugins' );   // v9.1.5: symmetric with themes
		wp_clean_themes_cache();
		wp_clean_plugins_cache();                     // v9.1.5: SSH plugin deploys leave stale get_plugin_data() cache otherwise
	}

	if ( $args['sn_transients'] ) {
		// 14.10.0: with a persistent object cache (Object Cache Pro here) the
		// transients live in Redis, so the DB delete below found nothing and
		// only the whole-Redis flush cleared them. Clear the transient group
		// instead: every plugin's transients go (disposable by contract), the
		// site-transient group (update_core, update_plugins) stays.
		if ( function_exists( 'wp_using_ext_object_cache' ) && wp_using_ext_object_cache()
			&& function_exists( 'wp_cache_supports' ) && wp_cache_supports( 'flush_group' ) ) {
			wp_cache_flush_group( 'transient' );
			$restore_cron_lock();
		}
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
		// 14.10.0: Breeze's page files and minified assets, called directly.
		// The breeze_clear_all_cache action this replaced ends in
		// wp_cache_flush() (Breeze 2.6.0 __flush_object_cache), so every page
		// purge emptied all of Redis. breeze_cache_flush( false, false, true ):
		// no post-scoped object-cache work, remove the whole HTML folder.
		if ( class_exists( 'Breeze_MinificationCache' ) ) {
			Breeze_MinificationCache::clear_minification();
		}
		if ( class_exists( 'Breeze_PurgeCache' ) ) {
			Breeze_PurgeCache::breeze_cache_flush( false, false, true );
		}
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
	// 14.10.0: ANY plugin or theme update, not only ours. The plugin now
	// removes Breeze's own update purge (owner, 2026-10-03), which emptied
	// all of Redis after every plugin update; this is its replacement.
	// Translations and core are not page changes (core flushes by itself).
	$type = $hook_extra['type'] ?? '';
	if ( 'theme' !== $type && 'plugin' !== $type ) {
		return;
	}
	// Owner 2026-10-05 (option B): our companion plugin's release says
	// whether it changes the public site (its `Front-End Change:` header,
	// written by the release tool). When the update is that plugin alone and
	// its release says "no", the page caches stay warm. Any other package,
	// or a missing or unreadable header, purges as before.
	if ( 'plugin' === $type && sn_update_is_only_companion( $hook_extra ) && sn_plugin_release_skips_purge() ) {
		return;
	}
	// Once per request: a batch update fires upgrader_process_complete per
	// package. A global, not a static, so the standalone tests can reset it.
	if ( ! empty( $GLOBALS['sn_auto_purge_done'] ) ) {
		return;
	}
	$GLOBALS['sn_auto_purge_done'] = true;
	// Page caches and the update-scoped caches; never the whole object cache
	// (Core's update check and every stored reading lived there), never
	// Site Editor template overrides.
	sn_purge_all_caches( array(
		'object_cache'       => false,
		'template_overrides' => false,
		'trigger'            => 'update',
	) );
}
add_action( 'upgrader_process_complete', 'sn_auto_purge_on_update', 10, 2 );

/**
 * Whether a plugin update's packages are the companion plugin and nothing
 * else (bulk 'plugins' or single 'plugin'). PURE.
 *
 * @param array $hook_extra Package descriptor from the upgrader.
 * @return bool
 */
function sn_update_is_only_companion( array $hook_extra ) {
	$plugins = (array) ( $hook_extra['plugins'] ?? array() );
	if ( isset( $hook_extra['plugin'] ) ) {
		$plugins[] = (string) $hook_extra['plugin'];
	}
	if ( array() === $plugins ) {
		return false;
	}
	foreach ( $plugins as $file ) {
		if ( 0 !== strpos( (string) $file, 'signal-and-noise-tools/' ) ) {
			return false;
		}
	}
	return true;
}

/**
 * Whether the companion plugin's freshly installed release declares no
 * public-site change since the version it replaced. Reads the headers from
 * the NEW files on disk (this hook runs old code, after the new files land);
 * the version it replaced is the old plugin still loaded in this request
 * (SNT_VERSION).
 *
 * "Front-End Change: no" describes one step, and the updater installs the
 * latest tag directly, so it holds only for a FORWARD update from a version
 * at or after the release's "Front-End Baseline:" (the last release that
 * changed the public site). A jump past a public release, a rollback, an
 * unknown prior version or a missing header is false: when in doubt, purge.
 *
 * @return bool
 */
function sn_plugin_release_skips_purge() {
	$file = (string) ( $GLOBALS['sn_plugin_main_file'] ?? ( defined( 'WP_PLUGIN_DIR' ) ? WP_PLUGIN_DIR . '/signal-and-noise-tools/signal-and-noise-tools.php' : '' ) );
	if ( '' === $file || ! is_readable( $file ) || ! function_exists( 'get_file_data' ) ) {
		return false;
	}
	$h      = get_file_data( $file, array( 'to' => 'Version', 'front_end' => 'Front-End Change', 'base' => 'Front-End Baseline' ) );
	$from   = (string) ( $GLOBALS['sn_plugin_prior_version'] ?? ( defined( 'SNT_VERSION' ) ? SNT_VERSION : '' ) ); // Test seam first.
	$to     = trim( (string) ( $h['to'] ?? '' ) );
	$base   = trim( (string) ( $h['base'] ?? '' ) );
	$semver = '/^\d+\.\d+\.\d+$/';
	if ( 'no' !== strtolower( trim( (string) ( $h['front_end'] ?? '' ) ) ) ) {
		return false;
	}
	if ( ! preg_match( $semver, $from ) || ! preg_match( $semver, $to ) || ! preg_match( $semver, $base ) ) {
		return false;
	}
	return version_compare( $to, $from, '>' ) && version_compare( $from, $base, '>=' );
}

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
		'package_caches'     => false,
		'trigger'            => 'styles',
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
