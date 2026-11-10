<?php
/**
 * Signal & Noise — security.txt (RFC 9116).
 *
 * Serves a flat /.well-known/security.txt (RFC 9116's canonical location; the
 * deprecated top-level alias was removed in v11.0.0) advertising how to report a security
 * issue. The spiritual sibling of /humans.txt — same flush-free virtual-route
 * mechanism (a rewrite rule and the sn_machine query var, inc/machine-routes.php,
 * answered at template_redirect priority 0).
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
 * Is this request for the security.txt? Pure helper (takes the sn_machine
 * query var) so it is testable without WordPress. The rule in
 * inc/machine-routes.php names the RFC 9116 canonical /.well-known/security.txt
 * only (v11.0.0 removed the legacy top-level alias).
 *
 * @param string $route The sn_machine query var.
 * @return bool
 */
function sn_security_txt_is_request( $route ) {
	return 'security' === $route;
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
 * Emit the text/plain header + body. Split from the template_redirect
 * handler so it is testable without exit().
 *
 * The rewrite rule in inc/machine-routes.php resolves the path as a route, not
 * a 404, so core already answers 200; the handler only reads the sn_machine
 * query var.
 */
function sn_security_txt_send() {
	header( 'Content-Type: text/plain; charset=' . get_option( 'blog_charset', 'UTF-8' ) );
	if ( function_exists( 'sn_edge_cache_send_machine' ) ) {
		sn_edge_cache_send_machine();
	}
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- plain-text body from home_url() + a derived expiry; esc_html would corrupt a text/plain document.
	echo sn_security_txt_body();
}

/**
 * template_redirect handler: serve the security.txt, then exit so WP's template
 * loader never runs.
 */
function sn_security_txt_maybe_serve() {
	$req = (string) get_query_var( 'sn_machine' );
	if ( ! sn_security_txt_is_request( $req ) ) {
		return;
	}
	sn_security_txt_send();
	exit;
}

if ( ! defined( 'SN_SECURITY_TXT_TEST' ) || ! SN_SECURITY_TXT_TEST ) {
	add_action( 'template_redirect', 'sn_security_txt_maybe_serve', 0 );
}
