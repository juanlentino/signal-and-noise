<?php
/**
 * Signal & Noise — /.well-known/gpc.json (Global Privacy Control support).
 *
 * Serves the GPC support resource per the spec
 * (https://globalprivacycontrol.github.io/gpc-spec/): {"gpc":true,"lastUpdate":"…"}.
 * This is the server-side DECLARATION that the site honors GPC — the counterpart
 * to the analytics beacon's client-side GPC bail (assets/js/sn-beacon.js). The
 * site is cookieless and sets no cross-site tracking, so the declaration is honest.
 *
 * Same flush-free virtual-route mechanism as /.well-known/security.txt
 * (template_redirect priority 0). lastUpdate is a FIXED date (not request-time)
 * so the resource is byte-stable and edge-cacheable.
 *
 * @package SignalNoise
 * @since 10.19.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'SN_GPC_LAST_UPDATE' ) ) {
	// Date the GPC-honoring posture was last affirmed. Bump only when the
	// privacy posture materially changes — keeps the JSON cache-stable.
	define( 'SN_GPC_LAST_UPDATE', '2026-06-30' );
}

/**
 * Is this request for /.well-known/gpc.json? Pure helper (takes the path).
 *
 * @param string $uri Request URI (may carry a query string).
 * @return bool
 */
function sn_gpc_json_is_request( $uri ) {
	$path = strtok( (string) $uri, '?' );
	$path = '/' . trim( (string) $path, '/' );
	return ( '/.well-known/gpc.json' === $path );
}

/**
 * Build the GPC support JSON. Static scalars only.
 *
 * @return string
 */
function sn_gpc_json_body() {
	return (string) wp_json_encode(
		array(
			'gpc'        => true,
			'lastUpdate' => SN_GPC_LAST_UPDATE,
		)
	);
}

/**
 * Emit the 200 status + application/json header + body. status_header( 200 ) is
 * REQUIRED (postless virtual path → 404 by template_redirect; WP-REFERENCE #40).
 */
function sn_gpc_json_send() {
	if ( function_exists( 'status_header' ) ) {
		status_header( 200 );
	}
	header( 'Content-Type: application/json; charset=utf-8' );
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- application/json document from wp_json_encode of static scalars; HTML escaping would corrupt the JSON.
	echo sn_gpc_json_body();
}

/**
 * template_redirect handler: serve the GPC resource, then exit.
 */
function sn_gpc_json_maybe_serve() {
	$req = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
	if ( ! sn_gpc_json_is_request( $req ) ) {
		return;
	}
	sn_gpc_json_send();
	exit;
}

if ( ! defined( 'SN_GPC_JSON_TEST' ) || ! SN_GPC_JSON_TEST ) {
	add_action( 'template_redirect', 'sn_gpc_json_maybe_serve', 0 );
}
