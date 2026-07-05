<?php
/**
 * Signal & Noise — verified purge, Tier-1 (render-epoch marker + durable report).
 *
 * Owner-approved 2026-07-03 as the box-DNS-independent slice of the verified-purge
 * arc. Tier-2 (an external worker probe + verify-and-escalate loop) is deferred:
 * the analytics worker's workers.dev URL is disabled by security design, and
 * whether the origin box can reach its own edge is an on-box empirical we cannot
 * settle from here. This module ships the two things that DON'T need an external
 * vantage:
 *
 *   1. A monotonic render-epoch emitted in every page <head>. It bumps at the
 *      start of every edge-affecting purge, so a still-stale edge serves the old
 *      value while a fresh origin render serves the new one — a universal staleness
 *      differential (any render change, not just CSS) the dashboard dot compares
 *      canonical-vs-cache-busted.
 *   2. A durable per-leg purge report (sn_last_purge_report) recording what each
 *      cache layer actually returned, surfaced read-only by the companion plugin.
 *
 * @package SignalNoise
 * @since 10.23.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const SN_RENDER_EPOCH_OPT     = 'sn_render_epoch';
const SN_LAST_PURGE_REPORT_OPT = 'sn_last_purge_report';

/**
 * Current render epoch (>= 1). Seeds at 1 so a never-purged site still emits a
 * stable, comparable marker.
 *
 * @return int
 */
function sn_render_epoch() {
	return max( 1, (int) get_option( SN_RENDER_EPOCH_OPT, 1 ) );
}

/**
 * Increment the render epoch and persist it NON-autoloaded (it is read only
 * during a purge + emitted in wp_head, never needed on every request's autoload).
 *
 * @return int The new epoch.
 */
function sn_bump_render_epoch() {
	$next = sn_render_epoch() + 1;
	update_option( SN_RENDER_EPOCH_OPT, $next, false );
	return $next;
}

/**
 * Emit the render-epoch marker into the document head. Part of the cached HTML,
 * so a stale cache carries the old N and a fresh origin render carries the new N.
 *
 * @return void
 */
function sn_render_epoch_meta() {
	echo '<meta name="sn-render-epoch" content="' . esc_attr( (string) sn_render_epoch() ) . '">' . "\n";
}
add_action( 'wp_head', 'sn_render_epoch_meta' );

/**
 * Whether a purge touches the public edge (origin HTML caches or the CDN). Only
 * these bump the epoch + write a report — a pure object-cache flush changes
 * nothing a visitor's cached page would carry.
 *
 * @param array $args Parsed sn_purge_all_caches() args.
 * @return bool
 */
function sn_purge_is_edge_affecting( $args ) {
	return ! empty( $args['origin_html'] ) || ! empty( $args['cloudflare'] );
}

/**
 * Bump the render epoch at the START of an edge-affecting purge, so the
 * post-purge origin re-render emits N+1 while a still-stale edge keeps serving N.
 *
 * @param array $args Parsed purge args (from sn_before_cache_flush).
 * @return void
 */
function sn_before_cache_flush_bump_epoch( $args ) {
	if ( sn_purge_is_edge_affecting( (array) $args ) ) {
		sn_bump_render_epoch();
	}
}
add_action( 'sn_before_cache_flush', 'sn_before_cache_flush_bump_epoch' );

/**
 * Introspect which origin-HTML purge legs are wired (without firing anything),
 * for the report's Breeze columns. Breeze registers breeze_clear_all_cache; the
 * companion plugin hooks breeze_clear_varnish (its Cloudways purge).
 *
 * @return array{breeze_file:bool,breeze_varnish:bool}
 */
function sn_purge_leg_state() {
	return array(
		'breeze_file'    => (bool) has_action( 'breeze_clear_all_cache' ),
		'breeze_varnish' => (bool) has_action( 'breeze_clear_varnish' ),
	);
}

/**
 * Write the durable per-leg purge report after an edge-affecting purge.
 *
 * MUST be an OPTION, never a transient: the purge itself deletes every
 * _transient_sn_% row, so a transient report would erase itself (see the
 * transients-are-flush-volatile-under-Breeze note).
 *
 * Per-leg truth: the Varnish leg reads the companion plugin's
 * sn_cloudways_last_purge (the real {ok,http,operation_id} confirmation); the CF
 * leg is a confirmed {accepted,http,cf_success} on a verified purge (the manual
 * button, which ran the blocking CF variant and stashed its result) or a plain
 * dispatched-but-unconfirmed record on a fast auto-purge.
 *
 * @param array $args    Parsed purge args (from sn_after_full_cache_flush).
 * @param int   $cleared Template overrides cleared (the flush's int return).
 * @return void
 */
function sn_write_purge_report( $args, $cleared = 0 ) {
	$args = (array) $args;
	if ( ! sn_purge_is_edge_affecting( $args ) ) {
		return; // don't overwrite the last real report with an empty object-cache flush.
	}

	$verified = ! empty( $args['verified'] );
	$state    = sn_purge_leg_state();

	// Varnish leg — the Cloudways confirmation is authoritative when present.
	$cw = get_option( 'sn_cloudways_last_purge', array() );
	if ( is_array( $cw ) && ! empty( $cw ) ) {
		$varnish = array(
			'via'          => 'cloudways',
			'ok'           => ! empty( $cw['ok'] ),
			'http'         => isset( $cw['http'] ) ? (int) $cw['http'] : 0,
			'operation_id' => isset( $cw['operation_id'] ) ? (int) $cw['operation_id'] : 0,
		);
	} else {
		$varnish = array( 'via' => 'breeze', 'listener' => $state['breeze_varnish'] );
	}

	// CF leg — confirmed on a verified purge, else dispatched-but-unconfirmed.
	$cf_result = isset( $GLOBALS['sn_cf_verified_result'] ) ? $GLOBALS['sn_cf_verified_result'] : null;
	if ( $verified && is_array( $cf_result ) ) {
		$cf = array(
			'accepted'   => ! empty( $cf_result['accepted'] ),
			'http'       => isset( $cf_result['http'] ) ? (int) $cf_result['http'] : 0,
			'cf_success' => ! empty( $cf_result['cf_success'] ),
		);
	} else {
		$cf = array( 'dispatched' => true, 'confirmed' => null );
	}
	unset( $GLOBALS['sn_cf_verified_result'] ); // one report per stashed result.

	$report = array(
		'time'               => time(),
		'mode'               => $verified ? 'verified' : 'auto',
		'epoch'              => sn_render_epoch(),
		'legs'               => array(
			'breeze_file' => $state['breeze_file'],
			'varnish'     => $varnish,
			'cf'          => $cf,
		),
		'template_overrides' => (int) $cleared,
	);

	// Tier-2 (v10.24.0): a verified (manual) purge probes each cache-critical route
	// through CF from the box — now that the box→CF path is confirmed reachable — and
	// records per-route freshness + the escalation outcome, so the report proves the
	// edge serves the current render instead of assuming it. Auto-purges skip the
	// inline probe to stay fast (a deferred cron verify is a later slice).
	if ( $verified ) {
		$verify             = sn_purge_verify_routes( sn_render_epoch() );
		$report['routes']   = $verify['routes'];
		$report['resolved'] = $verify['resolved'];
	}

	update_option( SN_LAST_PURGE_REPORT_OPT, $report, false );
}
add_action( 'sn_after_full_cache_flush', 'sn_write_purge_report', 10, 2 );

/**
 * Cache-critical routes the verified purge probes + reports on. Filterable so the
 * set grows without a code edit; root-relative paths with a leading slash.
 *
 * @return string[]
 */
function sn_verified_purge_routes() {
	$routes = (array) apply_filters( 'sn_verified_purge_routes', array( '/', '/notes/', '/provenance/' ) );
	$routes = array_filter( $routes, static function ( $r ) {
		return is_string( $r ) && '' !== $r;
	} );
	return array_values( array_unique( $routes ) );
}

/**
 * Probe one route through Cloudflare from the origin box (the box→CF path is
 * confirmed reachable — verified-purge spec §12). Reads the served render-epoch out
 * of the edge HTML and compares it to the epoch this purge bumped to: a stale edge
 * carries an older N. Anonymous GET (no cookies) so it sees the PUBLIC cache state;
 * redirects are followed so the final rendered page is measured. Carries no
 * credential, so `redirection => 0` is intentionally NOT set (that guard is for
 * credentialed calls; here it would misread a canonicalizing 3xx as "unknown").
 *
 * @param string $url          Absolute URL on our host.
 * @param int    $expect_epoch The epoch the current purge bumped to.
 * @return array{url:string,http:int,epoch_seen:?int,fresh:?bool,cf_cache_status:string}
 *   fresh = true|false when the epoch is readable, null (unknown) on transport error
 *   or a missing marker — never coerced to a pass.
 */
function sn_purge_probe( $url, $expect_epoch ) {
	$res = wp_remote_get( $url, array( 'timeout' => 5, 'sslverify' => true ) );
	if ( is_wp_error( $res ) ) {
		return array( 'url' => $url, 'http' => 0, 'epoch_seen' => null, 'fresh' => null, 'cf_cache_status' => '' );
	}
	$http = (int) wp_remote_retrieve_response_code( $res );
	$body = (string) wp_remote_retrieve_body( $res );
	$cf   = (string) wp_remote_retrieve_header( $res, 'cf-cache-status' );

	$epoch_seen = null;
	if ( preg_match( '/<meta[^>]+name=["\']sn-render-epoch["\'][^>]+content=["\'](\d+)["\']/i', $body, $m ) ) {
		$epoch_seen = (int) $m[1];
	}
	$fresh = ( null === $epoch_seen ) ? null : ( $epoch_seen >= (int) $expect_epoch );

	return array(
		'url'             => $url,
		'http'            => $http,
		'epoch_seen'      => $epoch_seen,
		'fresh'           => $fresh,
		'cf_cache_status' => $cf,
	);
}

/**
 * Probe every verified route; a route that reads stale is escalated — re-evict the
 * CDN (the Cloudways Varnish leg already fired once this request under its
 * per-request guard, so the resistant layer is CF re-seeding) + back off for
 * propagation, then re-probe — up to a small attempt budget. Returns the per-route
 * probe results + a `resolved` verdict (false iff any route is DEFINITIVELY stale;
 * an unknown/unreachable route never flips resolved — it is surfaced honestly).
 *
 * Runs only on the synchronous manual purge (the owner watches), so the bounded
 * backoff (≈1.5s × ≤2 escalations) is acceptable request latency.
 *
 * @param int      $expect_epoch The epoch the current purge bumped to.
 * @param int      $attempts     Max probes per route (>=1).
 * @param int|null $backoff_us   Microseconds between attempts; null = the filterable
 *                               default (1.5s). Tests pass 0.
 * @return array{routes:array<int,array>,resolved:bool}
 */
function sn_purge_verify_routes( $expect_epoch, $attempts = 3, $backoff_us = null ) {
	if ( null === $backoff_us ) {
		$backoff_us = (int) apply_filters( 'sn_purge_verify_backoff_us', 1500000 );
	}
	$attempts = max( 1, (int) $attempts );
	$results  = array();
	$resolved = true;

	foreach ( sn_verified_purge_routes() as $route ) {
		$url   = home_url( $route );
		$probe = null;
		for ( $i = 0; $i < $attempts; $i++ ) {
			if ( $i > 0 ) {
				// Escalate the resistant layer: re-evict CF (verified, blocking) so a
				// re-seed from a briefly-stale inner cache is cleared, then wait.
				if ( function_exists( 'sn_cf_purge_everything_verified' ) ) {
					sn_cf_purge_everything_verified();
				} elseif ( function_exists( 'sn_cf_purge_everything' ) ) {
					sn_cf_purge_everything();
				}
				if ( $backoff_us > 0 ) {
					usleep( $backoff_us ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions -- bounded, sync manual-purge only
				}
			}
			$probe = sn_purge_probe( $url, $expect_epoch );
			// Fresh, or unknown (re-purging can't resolve an unreachable / marker-less
			// route) — stop escalating either way.
			if ( true === $probe['fresh'] || null === $probe['fresh'] ) {
				break;
			}
		}
		$results[] = $probe;
		if ( false === $probe['fresh'] ) {
			$resolved = false;
		}
	}

	return array( 'routes' => $results, 'resolved' => $resolved );
}
