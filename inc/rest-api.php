<?php
/**
 * Signal & Noise — REST API surface.
 *
 * Wraps the theme's existing maintenance actions (currently exposed
 * only via the Appearance → Signal & Noise admin tab as classic
 * admin-post forms) behind a `signal-noise/v1` REST namespace so
 * they're scriptable from outside the WP UI: WP-CLI via
 * `wp signal-noise <action>` (TBD), CI/automation via curl with an
 * Application Password, or future AI agents via standard REST.
 *
 * Why REST and not the Abilities API: the Abilities API (WP 6.9 PHP,
 * 7.0 JS) is designed for plugins exposing capabilities to external
 * agents and a discovery layer. A single-author personal site has no
 * external agents to expose to and a REST surface is a strict superset
 * of what the admin UI buttons need today (per the analysis in
 * docs/WP-API-MAP.md).
 *
 * Endpoint inventory:
 *
 *   POST signal-noise/v1/purge-cache             — full cache purge
 *                                                  (object cache,
 *                                                  transients, Breeze,
 *                                                  Cloudflare). DOES
 *                                                  NOT touch DB
 *                                                  template overrides.
 *   POST signal-noise/v1/clear-overrides         — clear DB template
 *                                                  / template-part /
 *                                                  navigation overrides.
 *   POST signal-noise/v1/heal-templates          — force re-fetch every
 *                                                  monitored .html file
 *                                                  from GitHub main.
 *                                                  Bypasses rate limit.
 *   POST signal-noise/v1/full-reset              — both above + every
 *                                                  cache. The "after a
 *                                                  bad deploy" panic
 *                                                  button.
 *   POST signal-noise/v1/check-updates           — clear updater
 *                                                  caches + force a
 *                                                  fresh GitHub poll.
 *
 *   GET  signal-noise/v1/plausible/stats         — 7-day batched cache
 *                                                  (visitors, pageviews,
 *                                                  bounce, duration,
 *                                                  top pages, top
 *                                                  sources). Read-only.
 *   GET  signal-noise/v1/plausible/realtime      — current visitor count.
 *   POST signal-noise/v1/plausible/test          — fire a synchronous
 *                                                  Stats API call and
 *                                                  return the outcome.
 *
 * Auth model: every endpoint's permission_callback gates on
 * current_user_can( 'manage_options' ). Cookie-authenticated admins
 * pass automatically; external clients need a WordPress Application
 * Password attached to a manage_options-capable user. Never use
 * __return_true here — these are state-mutating admin endpoints, not
 * public data.
 *
 * Response shape: every endpoint returns either a WP_REST_Response
 * with `{ ok: true, message: '...', data: {...} }` (HTTP 200) or a
 * WP_Error with an appropriate status code that core's REST handler
 * will serialize to JSON automatically.
 *
 * @package SignalNoise
 * @since 7.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const SN_REST_NAMESPACE = 'signal-noise/v1';

/**
 * Shared permission callback. Must be a real check (not __return_true)
 * because these endpoints all mutate site state. Cookie auth + Application
 * Passwords both flow through current_user_can() correctly.
 */
function sn_rest_can_manage() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return new WP_Error(
			'sn_rest_forbidden',
			__( 'You do not have permission to perform this action.', 'signal-noise' ),
			array( 'status' => rest_authorization_required_code() )
		);
	}
	return true;
}

/**
 * Standardized success response. All endpoints return through this so
 * the shape is consistent: `{ ok: true, message: string, data: object }`
 */
function sn_rest_ok( $message, $data = array(), $status = 200 ) {
	return new WP_REST_Response(
		array(
			'ok'      => true,
			'message' => (string) $message,
			'data'    => is_array( $data ) ? $data : array(),
		),
		$status
	);
}

add_action( 'rest_api_init', function() {

	// ── Maintenance actions (POST, mutating) ─────────────────────────

	register_rest_route( SN_REST_NAMESPACE, '/purge-cache', array(
		'methods'             => WP_REST_Server::CREATABLE,
		'permission_callback' => 'sn_rest_can_manage',
		'callback'            => 'sn_rest_purge_cache',
	) );

	register_rest_route( SN_REST_NAMESPACE, '/clear-overrides', array(
		'methods'             => WP_REST_Server::CREATABLE,
		'permission_callback' => 'sn_rest_can_manage',
		'callback'            => 'sn_rest_clear_overrides',
	) );

	register_rest_route( SN_REST_NAMESPACE, '/heal-templates', array(
		'methods'             => WP_REST_Server::CREATABLE,
		'permission_callback' => 'sn_rest_can_manage',
		'callback'            => 'sn_rest_heal_templates',
	) );

	register_rest_route( SN_REST_NAMESPACE, '/full-reset', array(
		'methods'             => WP_REST_Server::CREATABLE,
		'permission_callback' => 'sn_rest_can_manage',
		'callback'            => 'sn_rest_full_reset',
	) );

	register_rest_route( SN_REST_NAMESPACE, '/check-updates', array(
		'methods'             => WP_REST_Server::CREATABLE,
		'permission_callback' => 'sn_rest_can_manage',
		'callback'            => 'sn_rest_check_updates',
	) );

	// ── Plausible read endpoints (GET, idempotent) ───────────────────

	register_rest_route( SN_REST_NAMESPACE, '/plausible/stats', array(
		'methods'             => WP_REST_Server::READABLE,
		'permission_callback' => 'sn_rest_can_manage',
		'callback'            => 'sn_rest_plausible_stats',
	) );

	register_rest_route( SN_REST_NAMESPACE, '/plausible/realtime', array(
		'methods'             => WP_REST_Server::READABLE,
		'permission_callback' => 'sn_rest_can_manage',
		'callback'            => 'sn_rest_plausible_realtime',
	) );

	register_rest_route( SN_REST_NAMESPACE, '/plausible/test', array(
		'methods'             => WP_REST_Server::CREATABLE,
		'permission_callback' => 'sn_rest_can_manage',
		'callback'            => 'sn_rest_plausible_test',
	) );
} );

// ── Callbacks ────────────────────────────────────────────────────────

/**
 * POST /purge-cache — full cache purge minus DB template overrides.
 * Mirrors the "Purge All Caches" button in the admin Dashboard tab.
 */
function sn_rest_purge_cache( WP_REST_Request $request ) {
	if ( ! function_exists( 'sn_purge_all_caches' ) ) {
		return new WP_Error( 'sn_rest_unavailable', __( 'Cache purge module not loaded.', 'signal-noise' ), array( 'status' => 500 ) );
	}
	$cleared = (int) sn_purge_all_caches( array( 'template_overrides' => false ) );
	return sn_rest_ok( __( 'All caches purged.', 'signal-noise' ), array( 'cleared' => $cleared ) );
}

/**
 * POST /clear-overrides — clear DB template/template-part/navigation
 * overrides. Site reverts to reading templates from theme files.
 */
function sn_rest_clear_overrides( WP_REST_Request $request ) {
	if ( ! function_exists( 'sn_clear_template_overrides' ) ) {
		return new WP_Error( 'sn_rest_unavailable', __( 'Template override module not loaded.', 'signal-noise' ), array( 'status' => 500 ) );
	}
	$count = (int) sn_clear_template_overrides();
	return sn_rest_ok(
		/* translators: %d: number of database overrides cleared. */
		sprintf( __( '%d database override(s) cleared.', 'signal-noise' ), $count ),
		array( 'cleared' => $count )
	);
}

/**
 * POST /heal-templates — force re-fetch every monitored .html file
 * from the tracked GitHub branch. Bypasses the 5-min ambient rate
 * limit and clears per-file failure cooldowns.
 */
function sn_rest_heal_templates( WP_REST_Request $request ) {
	if ( ! function_exists( 'sn_self_heal_force_run' ) ) {
		return new WP_Error( 'sn_rest_unavailable', __( 'Self-heal module not loaded.', 'signal-noise' ), array( 'status' => 500 ) );
	}
	$result    = sn_self_heal_force_run();
	$fixed_n   = isset( $result['fixed'] ) ? count( (array) $result['fixed'] ) : 0;
	$failed_n  = isset( $result['failed'] ) ? count( (array) $result['failed'] ) : 0;
	$message   = $fixed_n > 0
		/* translators: %d: number of files re-synced from GitHub. */
		? sprintf( __( 'Self-heal: re-synced %d template file(s) from GitHub.', 'signal-noise' ), $fixed_n )
		: __( 'Self-heal: all monitored files already match GitHub.', 'signal-noise' );

	if ( $failed_n > 0 ) {
		return new WP_Error(
			'sn_heal_partial',
			/* translators: %d: number of files that failed to write. */
			sprintf( __( 'Self-heal: drift detected but write failed for %d file(s).', 'signal-noise' ), $failed_n ),
			array(
				'status' => 500,
				'fixed'  => $result['fixed'] ?? array(),
				'failed' => $result['failed'] ?? array(),
			)
		);
	}
	return sn_rest_ok(
		$message,
		array(
			'fixed'  => $result['fixed'] ?? array(),
			'failed' => $result['failed'] ?? array(),
		)
	);
}

/**
 * POST /full-reset — purge all caches AND clear DB overrides. The
 * "I just deployed and something's wrong" panic button.
 */
function sn_rest_full_reset( WP_REST_Request $request ) {
	if ( ! function_exists( 'sn_purge_all_caches' ) ) {
		return new WP_Error( 'sn_rest_unavailable', __( 'Cache purge module not loaded.', 'signal-noise' ), array( 'status' => 500 ) );
	}
	delete_transient( 'sn_github_error' );
	$count = (int) sn_purge_all_caches();
	return sn_rest_ok(
		/* translators: %d: number of overrides cleared as part of a full reset. */
		sprintf( __( 'Full reset: %d override(s) cleared and all caches purged.', 'signal-noise' ), $count ),
		array( 'cleared' => $count )
	);
}

/**
 * POST /check-updates — clear updater caches + force a fresh GitHub
 * poll. Mirrors the "Check Now" button. Returns the post-poll
 * synthetic version label for sanity-checking from CI.
 */
function sn_rest_check_updates( WP_REST_Request $request ) {
	if ( ! function_exists( 'sn_updater_branch' ) ) {
		return new WP_Error( 'sn_rest_unavailable', __( 'Self-updater module not loaded.', 'signal-noise' ), array( 'status' => 500 ) );
	}

	$branch = sanitize_key( sn_updater_branch() );

	// Mirror the cache-clear sequence the admin button uses — see
	// inc/admin-page.php's `check_updates` action handler.
	delete_transient( 'sn_github_error' );
	delete_transient( 'sn_github_branch_' . $branch );
	delete_transient( 'sn_github_remote_version_' . $branch );
	global $wpdb;
	if ( $wpdb ) {
		$wpdb->query( $wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
			$wpdb->esc_like( '_transient_sn_github_revcount_' . $branch ) . '%',
			$wpdb->esc_like( '_transient_timeout_sn_github_revcount_' . $branch ) . '%'
		) );
	}
	delete_site_transient( 'update_themes' );
	wp_clean_themes_cache();

	// Repopulate update_themes immediately so the response can include
	// the new SHA / version label without a separate poll.
	wp_update_themes();

	$transient = get_site_transient( 'update_themes' );
	$slug      = defined( 'SN_THEME_SLUG' ) ? SN_THEME_SLUG : 'signal-and-noise';
	$offered   = ( is_object( $transient ) && isset( $transient->response[ $slug ] ) )
		? $transient->response[ $slug ]
		: null;

	return sn_rest_ok(
		__( 'Update check complete.', 'signal-noise' ),
		array(
			'branch'           => $branch,
			'update_available' => null !== $offered,
			'offered'          => $offered,
		)
	);
}

/**
 * GET /plausible/stats — read-only accessor for the 7-day batched
 * cache. Returns whatever the SWR layer has (possibly stale, possibly
 * empty if the very first cron warmup hasn't landed yet). Never
 * triggers a network call.
 */
function sn_rest_plausible_stats( WP_REST_Request $request ) {
	if ( ! function_exists( 'sn_plausible_dashboard_data' ) ) {
		return new WP_Error( 'sn_rest_unavailable', __( 'Plausible module not loaded.', 'signal-noise' ), array( 'status' => 500 ) );
	}
	$data = sn_plausible_dashboard_data();
	if ( null === $data ) {
		return new WP_Error( 'sn_plausible_unconfigured', __( 'Plausible is not configured (missing domain or token).', 'signal-noise' ), array( 'status' => 503 ) );
	}
	return sn_rest_ok(
		__( 'Plausible 7-day stats.', 'signal-noise' ),
		$data
	);
}

/**
 * GET /plausible/realtime — read-only accessor for the realtime cache.
 * Same SWR semantics as /stats.
 */
function sn_rest_plausible_realtime( WP_REST_Request $request ) {
	if ( ! function_exists( 'sn_plausible_realtime' ) ) {
		return new WP_Error( 'sn_rest_unavailable', __( 'Plausible module not loaded.', 'signal-noise' ), array( 'status' => 500 ) );
	}
	$value = sn_plausible_realtime();
	return sn_rest_ok(
		__( 'Plausible realtime visitors.', 'signal-noise' ),
		array( 'visitors' => $value )
	);
}

/**
 * POST /plausible/test — fire a synchronous 7-day aggregate call to
 * the Stats API and return the outcome. Mirrors the "Test Connection"
 * button in the Plausible admin tab. The synchronous-by-design
 * exception to the SWR-everywhere rule: an admin clicked "test",
 * they're waiting on a real-network result, not a cached one.
 */
function sn_rest_plausible_test( WP_REST_Request $request ) {
	if ( ! function_exists( 'sn_plausible_config' ) || ! function_exists( 'sn_plausible_api' ) ) {
		return new WP_Error( 'sn_rest_unavailable', __( 'Plausible module not loaded.', 'signal-noise' ), array( 'status' => 500 ) );
	}
	$cfg = sn_plausible_config();
	if ( ! $cfg ) {
		return new WP_Error( 'sn_plausible_unconfigured', __( 'Plausible is not configured (missing domain or token).', 'signal-noise' ), array( 'status' => 503 ) );
	}
	delete_transient( SN_PLAUSIBLE_ERR_KEY );
	$result = sn_plausible_api( 'aggregate', array( 'period' => '7d', 'metrics' => 'visitors' ), $cfg );
	if ( is_array( $result ) ) {
		$visitors = (int) ( $result['visitors']['value'] ?? 0 );
		return sn_rest_ok(
			/* translators: %d: number of visitors in the last 7 days. */
			sprintf( __( 'Plausible API call succeeded — %d visitor(s) in last 7 days.', 'signal-noise' ), $visitors ),
			array( 'visitors_7d' => $visitors )
		);
	}
	$err     = function_exists( 'sn_plausible_last_error' ) ? sn_plausible_last_error() : null;
	$status  = $err && isset( $err['code'] ) && (int) $err['code'] >= 400 ? (int) $err['code'] : 502;
	return new WP_Error(
		'sn_plausible_test_failed',
		__( 'Plausible API call failed.', 'signal-noise' ),
		array(
			'status'      => $status,
			'last_error'  => $err,
		)
	);
}
