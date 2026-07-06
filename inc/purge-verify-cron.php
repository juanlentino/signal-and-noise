<?php
/**
 * Signal & Noise: deferred cron verify for AUTO purges (verified-purge Tier-2 follow-up).
 *
 * inc/purge-verify.php probes each cache-critical route inline, but only on a VERIFIED
 * (manual) purge, where the owner is watching and the bounded escalation latency is fine.
 * An AUTO purge (a theme/plugin update completing, a Site Editor Styles save) must stay
 * fast on the save path, so it writes its report and skips the probe. That left a gap: an
 * auto-purge could fail to propagate to the edge and nothing would ever check.
 *
 * This module closes it WITHOUT slowing the save path. After an auto edge-purge, it
 * schedules a single WP-Cron event about 75s out (enough for edge propagation). The cron
 * request, not the save request, runs the SAME sn_purge_verify_routes() probe/escalate
 * loop and folds the per-route result back into the durable sn_last_purge_report.
 *
 * Two things make the wiring safe:
 *   1. Scheduling is O(1) on the save path (a next-scheduled check plus a schedule call);
 *      all probing happens later, in the cron request.
 *   2. The epoch this purge bumped to travels WITH the scheduled event (a cron arg), and
 *      the handler re-checks it against the report before writing, so a newer purge that
 *      lands in the meantime, carrying its own verify, is never clobbered by a stale run.
 *
 * @package SignalNoise
 * @since 10.25.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The one-shot cron hook that runs the deferred route verify.
 */
const SN_AUTO_PURGE_VERIFY_HOOK = 'sn_verify_auto_purge';

/**
 * Seconds to wait before the deferred verify fires. Mid-point of the ~60-90s edge
 * propagation window: long enough for Cloudflare plus Varnish to settle after the purge,
 * short enough that a stale edge is caught while it still matters. Filterable.
 */
const SN_AUTO_PURGE_VERIFY_DELAY = 75;

/**
 * Schedule (or re-target) the single deferred verify for an auto edge-purge.
 *
 * Guards against stacking on rapid successive auto-purges (a batch of Styles saves, a
 * theme plus plugin update landing together): if an event is already queued for THIS
 * exact epoch we leave it; otherwise we drop any event queued for an older epoch and
 * schedule a fresh one, so exactly one verify is ever pending and it always targets the
 * latest render.
 *
 * WP identifies a scheduled event by md5(serialize($args)), so wp_next_scheduled() must
 * be queried WITH the epoch arg, and the replace must use wp_unschedule_hook() (drops
 * every instance of the hook regardless of args). wp_clear_scheduled_hook() would only
 * match the empty-args key and leave older-epoch events behind. (WP-REFERENCE section 2.3.)
 *
 * @param int $epoch The render epoch this purge bumped to (the value stored in the report).
 * @return void
 */
function sn_schedule_auto_purge_verify( $epoch ) {
	$epoch = (int) $epoch;
	if ( $epoch < 1 ) {
		return;
	}

	// Already queued for this exact epoch: idempotent, don't push the fire time back.
	if ( wp_next_scheduled( SN_AUTO_PURGE_VERIFY_HOOK, array( $epoch ) ) ) {
		return;
	}

	// Replace any event queued for an older epoch so successive auto-purges collapse to
	// one verify targeting the newest render.
	wp_unschedule_hook( SN_AUTO_PURGE_VERIFY_HOOK );

	$delay = max( 1, (int) apply_filters( 'sn_auto_purge_verify_delay', SN_AUTO_PURGE_VERIFY_DELAY ) );
	wp_schedule_single_event( time() + $delay, SN_AUTO_PURGE_VERIFY_HOOK, array( $epoch ) );
}

/**
 * On an AUTO edge-purge, defer the route verify to cron. Runs at priority 20, after the
 * report writer (priority 10) has stamped sn_last_purge_report with this purge's epoch,
 * so it can bind the scheduled event to the exact report the cron handler will verify.
 *
 * A verified (manual) purge already probed inline; a non-edge flush changed nothing a
 * visitor's cached page carries. Both skip the deferral.
 *
 * @param array $args    Parsed purge args (from sn_after_full_cache_flush).
 * @param int   $cleared Template overrides cleared (unused; hook signature parity).
 * @return void
 */
function sn_after_purge_schedule_verify( $args, $cleared = 0 ) {
	$args = (array) $args;
	if ( ! empty( $args['verified'] ) || ! sn_purge_is_edge_affecting( $args ) ) {
		return;
	}

	// Bind to the report the writer just stamped: reading the epoch back guarantees the
	// cron arg and the report's epoch are the same value, so the handler's freshness
	// check is exact rather than a same-request coincidence.
	$report = get_option( SN_LAST_PURGE_REPORT_OPT, null );
	if ( ! is_array( $report ) || empty( $report['epoch'] ) ) {
		return;
	}

	sn_schedule_auto_purge_verify( (int) $report['epoch'] );
}
add_action( 'sn_after_full_cache_flush', 'sn_after_purge_schedule_verify', 20, 2 );

/**
 * The deferred verify itself, run in the cron request. Probes plus escalates every
 * cache-critical route (reusing sn_purge_verify_routes() as-is, so it inherits the
 * "unknown/unreachable is surfaced honestly, never coerced to a pass" contract) and
 * merges the result onto the durable report.
 *
 * Skips if the report on record is no longer the one we were scheduled to verify: a newer
 * purge (manual or another auto) has since written a fresher report carrying its own
 * freshness, and clobbering it with our stale-epoch routes would lie. The epoch is the
 * discriminator: every edge-purge bumps it, so a mismatch means "superseded." The check
 * runs twice, once up front and once after the (blocking, possibly multi-second) probe,
 * because a newer purge can land while we are on the wire.
 *
 * @param int $epoch The render epoch this run was scheduled to verify.
 * @return void
 */
function sn_verify_auto_purge_cron( $epoch ) {
	$epoch = (int) $epoch;

	$report = get_option( SN_LAST_PURGE_REPORT_OPT, null );
	if ( ! is_array( $report ) || (int) ( $report['epoch'] ?? 0 ) !== $epoch ) {
		return; // superseded (or gone) before we even started.
	}

	$verify = sn_purge_verify_routes( $epoch );

	// Re-read: the probe made blocking HTTP (plus possible CF re-evicts). A newer purge
	// may have written a fresher report while we waited, so don't overwrite it.
	$current = get_option( SN_LAST_PURGE_REPORT_OPT, null );
	if ( ! is_array( $current ) || (int) ( $current['epoch'] ?? 0 ) !== $epoch ) {
		return;
	}

	$current['routes']      = $verify['routes'];
	$current['resolved']    = $verify['resolved'];
	$current['verify']      = 'cron';
	$current['verified_at'] = time();
	update_option( SN_LAST_PURGE_REPORT_OPT, $current, false );
}
add_action( SN_AUTO_PURGE_VERIFY_HOOK, 'sn_verify_auto_purge_cron' );
