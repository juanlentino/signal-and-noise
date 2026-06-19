<?php
/**
 * Signal & Noise — security.txt (RFC 9116).
 *
 * Serves a flat /.well-known/security.txt (and the deprecated top-level
 * /security.txt some scanners still probe) advertising how to report a security
 * issue. The spiritual sibling of /humans.txt — same flush-free virtual-route
 * mechanism (template_redirect priority 0, no add_rewrite_rule; the theme must
 * not flush, that is the plugin's job).
 *
 * The mandatory `Expires` field is derived as ~1 year from the request time, so
 * the file never silently expires and needs zero recurring maintenance. Contact
 * + Canonical are built from home_url() so the file is portable.
 *
 * @package SignalNoise
 * @since 10.13.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Is this request for the security.txt? Pure helper (takes the path) so it is
 * testable without $_SERVER. Matches the RFC 9116 canonical
 * /.well-known/security.txt and the deprecated legacy /security.txt.
 *
 * @param string $uri Request URI (may carry a query string).
 * @return bool
 */
function sn_security_txt_is_request( $uri ) {
	$path = strtok( (string) $uri, '?' );
	$path = '/' . trim( (string) $path, '/' );
	return ( '/.well-known/security.txt' === $path || '/security.txt' === $path );
}

/**
 * RFC 9116 `Expires` value: an ISO-8601 UTC timestamp ~1 year ahead of $now.
 * Pure (takes $now) so the body is testable deterministically. Kept just under
 * a full year per the RFC's "less than a year into the future" guidance.
 *
 * @param int $now Unix timestamp (GMT).
 * @return string e.g. "2027-06-04T00:00:00Z".
 */
function sn_security_txt_expires( $now ) {
	$year = defined( 'YEAR_IN_SECONDS' ) ? YEAR_IN_SECONDS : 31536000;
	$day  = defined( 'DAY_IN_SECONDS' ) ? DAY_IN_SECONDS : 86400;
	return gmdate( 'Y-m-d\TH:i:s\Z', (int) $now + $year - ( 15 * $day ) );
}

/**
 * Build the plain-text security.txt body. Trusted by construction: home_url()
 * values + a derived expiry, no user input.
 *
 * @param int|null $now Unix timestamp (GMT); defaults to time(). Injectable for tests.
 * @return string
 */
function sn_security_txt_body( $now = null ) {
	if ( null === $now ) {
		$now = time();
	}
	$lines = array(
		'# Security policy for ' . home_url( '/' ),
		'Contact: ' . home_url( '/contact/' ),
		'Expires: ' . sn_security_txt_expires( (int) $now ),
		'Preferred-Languages: en, es',
		'Canonical: ' . home_url( '/.well-known/security.txt' ),
		'',
	);
	return implode( "\n", $lines ) . "\n";
}

/**
 * Emit the 200 status + text/plain header + body. Split from the
 * template_redirect handler so it is testable without exit().
 *
 * status_header( 200 ) is REQUIRED: a virtual path with no backing post has
 * already been handed a 404 by WP's handle_404() before template_redirect, so
 * without this the file would return its body under a 404 and status-aware
 * disclosure scanners would treat it as absent (WORDPRESS-REFERENCE gotcha #40).
 */
function sn_security_txt_send() {
	if ( function_exists( 'status_header' ) ) {
		status_header( 200 );
	}
	header( 'Content-Type: text/plain; charset=' . get_option( 'blog_charset', 'UTF-8' ) );
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- plain-text body from home_url() + a derived expiry; esc_html would corrupt a text/plain document.
	echo sn_security_txt_body();
}

/**
 * template_redirect handler: serve the security.txt, then exit so WP's template
 * loader never runs.
 */
function sn_security_txt_maybe_serve() {
	$req = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
	if ( ! sn_security_txt_is_request( $req ) ) {
		return;
	}
	sn_security_txt_send();
	exit;
}

if ( ! defined( 'SN_SECURITY_TXT_TEST' ) || ! SN_SECURITY_TXT_TEST ) {
	add_action( 'template_redirect', 'sn_security_txt_maybe_serve', 0 );
}
