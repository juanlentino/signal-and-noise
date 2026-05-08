<?php
/**
 * Signal & Noise — Plausible Stats API client.
 *
 * Reads the official Plausible plugin's stored settings (domain + Plugin
 * Token) from `plausible_analytics_settings`, makes Stats API calls,
 * and exposes two cache layers:
 *
 *   - sn_plausible_dashboard_data()   — 5-min batched cache covering 7-day
 *                                       aggregate + top pages + top sources.
 *                                       Used by all "last 7 days" widgets.
 *   - sn_plausible_realtime()         — 30-sec cache for the realtime
 *                                       visitor count (separate so it
 *                                       actually feels real-time).
 *
 * One batched fetch per 5 min covers all dashboard widgets that read from
 * dashboard_data; the realtime widget is the only second round-trip.
 *
 * Self-hosted Plausible is supported via the same plugin option's
 * `self_hosted_domain` key; falls back to plausible.io otherwise.
 *
 * @package SignalNoise
 * @since 7.2.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const SN_PLAUSIBLE_BATCH_KEY    = 'sn_plausible_dashboard_v2';
const SN_PLAUSIBLE_BATCH_TTL    = 5 * MINUTE_IN_SECONDS;
const SN_PLAUSIBLE_REALTIME_KEY = 'sn_plausible_realtime_v2';
const SN_PLAUSIBLE_REALTIME_TTL = 30; // seconds

/**
 * Resolve domain + token + API base. Returns null when not configured.
 *
 * @return array{domain: string, token: string, base: string}|null
 */
function sn_plausible_config() {
	$settings = get_option( 'plausible_analytics_settings', array() );
	if ( ! is_array( $settings ) ) {
		return null;
	}
	$domain = trim( (string) ( $settings['domain_name'] ?? '' ) );
	$token  = trim( (string) ( $settings['api_token']   ?? '' ) );
	if ( '' === $domain || '' === $token ) {
		return null;
	}
	$self_host = trim( (string) ( $settings['self_hosted_domain'] ?? '' ) );
	$base      = '' !== $self_host ? rtrim( $self_host, '/' ) : 'https://plausible.io';
	return array( 'domain' => $domain, 'token' => $token, 'base' => $base );
}

/**
 * GET a Plausible Stats API path.
 *
 * @param string $path        Path under /api/v1/stats — e.g. 'aggregate'.
 * @param array  $query       Query args; site_id is merged in automatically.
 * @param array  $cfg         Output of sn_plausible_config().
 * @param bool   $expect_json False = body is a bare integer (realtime endpoint).
 * @return mixed|null Decoded `results` payload, raw int, or null on failure.
 */
function sn_plausible_api( $path, $query, $cfg, $expect_json = true ) {
	$query = wp_parse_args( $query, array( 'site_id' => $cfg['domain'] ) );
	$url   = $cfg['base'] . '/api/v1/stats/' . ltrim( $path, '/' ) . '?' . http_build_query( $query );

	$response = wp_remote_get( $url, array(
		'headers' => array( 'Authorization' => 'Bearer ' . $cfg['token'] ),
		'timeout' => 6,
	) );

	if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
		return null;
	}
	$body = wp_remote_retrieve_body( $response );

	if ( ! $expect_json ) {
		// Realtime endpoint returns a bare integer, not a JSON envelope.
		$trimmed = trim( $body );
		return is_numeric( $trimmed ) ? (int) $trimmed : null;
	}
	$decoded = json_decode( $body, true );
	return $decoded['results'] ?? null;
}

/**
 * Batched 7-day data: aggregate + top pages + top sources, in one
 * 5-minute transient. All "last 7 days" widgets read from this.
 *
 * @return array|null Null when plugin isn't configured.
 */
function sn_plausible_dashboard_data() {
	$cached = get_transient( SN_PLAUSIBLE_BATCH_KEY );
	if ( false !== $cached ) {
		return $cached;
	}
	$cfg = sn_plausible_config();
	if ( ! $cfg ) {
		return null;
	}

	$aggregate = sn_plausible_api( 'aggregate', array(
		'period'  => '7d',
		'metrics' => 'visitors,pageviews,bounce_rate,visit_duration',
	), $cfg );
	$pages = sn_plausible_api( 'breakdown', array(
		'period'   => '7d',
		'property' => 'event:page',
		'limit'    => 7,
	), $cfg );
	$sources = sn_plausible_api( 'breakdown', array(
		'period'   => '7d',
		'property' => 'visit:source',
		'limit'    => 7,
	), $cfg );

	$data = array(
		'aggregate' => is_array( $aggregate ) ? $aggregate : array(),
		'pages'     => is_array( $pages )     ? $pages     : array(),
		'sources'   => is_array( $sources )   ? $sources   : array(),
		'fetched'   => time(),
	);
	set_transient( SN_PLAUSIBLE_BATCH_KEY, $data, SN_PLAUSIBLE_BATCH_TTL );
	return $data;
}

/**
 * Realtime visitor count, cached 30s. Separate from the batched cache
 * because "right now" needs to actually be approximately now.
 *
 * @return int|null Null when plugin isn't configured or API failed.
 */
function sn_plausible_realtime() {
	$cached = get_transient( SN_PLAUSIBLE_REALTIME_KEY );
	if ( false !== $cached ) {
		return $cached;
	}
	$cfg = sn_plausible_config();
	if ( ! $cfg ) {
		return null;
	}
	$value = sn_plausible_api( 'realtime/visitors', array(), $cfg, false );
	// Store the int — or a sentinel array on null so the transient layer
	// can still cache the "no data" state for 30s instead of refetching.
	set_transient( SN_PLAUSIBLE_REALTIME_KEY, $value, SN_PLAUSIBLE_REALTIME_TTL );
	return $value;
}
