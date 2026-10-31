<?php
/**
 * WP update integration: tag-fetch failure classification.
 *
 * Moved verbatim out of inc/wp-update-integration.php, which loads it.
 *
 * @package SignalNoise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Will this failure plausibly have fixed itself in five minutes?
 *
 * WHY THIS EXISTS (theme v10.43.0 — porting plugin v9.54.1)
 *
 * 2026-07-16 22:51 UTC GitHub declared "Degraded REST API Availability" — ~35%
 * of REST requests failing. Both S&N version cards went red four minutes later.
 * The 503 was GitHub's; the SIXTY MINUTES of blindness were ours. Every failure
 * cached the empty sentinel for HOUR_IN_SECONDS, so a one-second blip cost an
 * hour, and the next hourly poll had another ~35% chance of re-arming it.
 *
 * The tell was on the same dashboard: "Recent deploys" stayed live throughout
 * because its fetch caches failures for FIVE minutes and self-heals. Same host,
 * same token, same timeout — only the failure TTL differed.
 *
 * So classify by whether re-asking could plausibly get a different answer:
 *   - 5xx / 429 / network / timeout → the far end is unwell; it recovers.
 *   - 401 / 404                     → nothing changes in an hour. Don't hammer.
 *
 * @since theme v10.43.0
 * @param int|null $code HTTP status, 0 for a WP_Error, null when unknown.
 * @return bool
 */
function sn_gh_theme_failure_is_transient( $code ) {
	$code = (int) $code;
	return 0 === $code || 429 === $code || $code >= 500;
}

/**
 * How long to hold a failure before asking GitHub again.
 *
 * @since theme v10.43.0
 * @param int|null $code
 * @return int Seconds.
 */
function sn_gh_theme_failure_ttl( $code ) {
	return sn_gh_theme_failure_is_transient( $code )
		? SN_GH_THEME_FAIL_TTL_TRANSIENT
		: SN_GH_THEME_FAIL_TTL_DURABLE;
}

/**
 * Strip anything token-shaped out of a message before it can reach a screen.
 *
 * The reason is rendered in wp-admin. An HTTP driver message is not ours and
 * could in principle quote a request header back at us. Redact defensively
 * rather than reason about whether cURL ever does: the cost is a regex, the
 * failure mode is a leaked credential.
 *
 * @since theme v10.43.0
 * @param string $message
 * @return string
 */
function sn_gh_theme_redact_secrets( $message ) {
	return (string) preg_replace(
		'/\b(gh[pousr]_[A-Za-z0-9]{16,}|github_pat_[A-Za-z0-9_]{20,}|Bearer\s+\S+)/i',
		'[redacted]',
		(string) $message
	);
}

/**
 * Turn a failed tags fetch into a short sentence a human can act on.
 *
 * WHY (theme v10.43.0): on 2026-07-16 the PLUGIN's card said "GitHub returned
 * an unexpected HTTP 503" and that one line ended an hour of confident, wrong
 * theorising (an expired token; then a response-size timeout). The THEME's card
 * said nothing — a bare red "unknown" — because this function did not exist.
 * Same outage, same second, same screen: one surface could explain itself and
 * the other could not. Never silent.
 *
 * @since theme v10.43.0
 * @param array|WP_Error $response
 * @return string Never contains a credential.
 */
function sn_gh_theme_fetch_failure_reason( $response ) {
	if ( is_wp_error( $response ) ) {
		// No HTTP response at all — timeout, DNS, TLS. Carry the real driver
		// message ("cURL error 28: Operation timed out after 8001 ms"): the
		// number in it is the actual diagnosis.
		return sn_gh_theme_redact_secrets( sprintf(
			/* translators: %s: underlying HTTP error message. */
			__( 'could not reach GitHub — %s', 'signal-and-noise' ),
			$response->get_error_message()
		) );
	}

	$code = (int) wp_remote_retrieve_response_code( $response );
	switch ( $code ) {
		case 401:
			return __( 'GitHub rejected the credential (401) — SNT_GITHUB_TOKEN in wp-config.php is invalid, expired, or revoked', 'signal-and-noise' );
		case 403:
			return __( 'GitHub refused the request (403) — usually a rate limit; set SNT_GITHUB_TOKEN in wp-config.php to raise 60/h to 5000/h', 'signal-and-noise' );
		case 404:
			return __( 'GitHub returned 404 — the repository was renamed, deleted, or made private', 'signal-and-noise' );
		case 200:
			return __( 'GitHub returned 200 but the body was not a readable tag list', 'signal-and-noise' );
		default:
			return sprintf(
				/* translators: %d: HTTP status code. */
				__( 'GitHub returned an unexpected HTTP %d', 'signal-and-noise' ),
				$code
			);
	}
}
