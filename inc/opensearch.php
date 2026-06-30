<?php
/**
 * Signal & Noise — /opensearch.xml (OpenSearch Description Document).
 *
 * Serves an OSDD pointed at the owned /notes/?s= search route, and emits a
 * <link rel="search"> head tag so browsers (Chrome, Safari, Firefox) can
 * register the site as a search provider. Cookieless standards plumbing over a
 * route we already own.
 *
 * Same flush-free virtual-route mechanism as /humans.txt (template_redirect
 * priority 0). The {searchTerms} token must reach the client LITERAL — esc_url()
 * would mangle the braces, so it is swapped in after escaping via an alnum sentinel.
 *
 * @package SignalNoise
 * @since 10.19.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Is this request for /opensearch.xml? Pure helper (takes the path).
 *
 * @param string $uri Request URI (may carry a query string).
 * @return bool
 */
function sn_opensearch_is_request( $uri ) {
	$path = strtok( (string) $uri, '?' );
	$path = '/' . trim( (string) $path, '/' );
	return ( '/opensearch.xml' === $path );
}

/**
 * The provider ShortName — the site title, capped at the OpenSearch 16-char limit.
 *
 * @return string
 */
function sn_opensearch_short_name() {
	$name = (string) get_bloginfo( 'name' );
	if ( '' === $name ) {
		$name = 'Signal & Noise';
	}
	return function_exists( 'mb_substr' ) ? mb_substr( $name, 0, 16 ) : substr( $name, 0, 16 );
}

/**
 * Build the OpenSearch Description Document. Node/attr values are esc_html/
 * esc_url'd; the {searchTerms} token is preserved literal.
 *
 * @return string
 */
function sn_opensearch_body() {
	$name = sn_opensearch_short_name();
	$home = rtrim( home_url( '/' ), '/' );

	// Preserve the literal {searchTerms} token through esc_url() via an alnum sentinel.
	$template = esc_url( $home . '/notes/?s=__SN_SEARCHTERMS__' );
	$template = str_replace( '__SN_SEARCHTERMS__', '{searchTerms}', $template );

	$icon = function_exists( 'get_site_icon_url' ) ? (string) get_site_icon_url( 64 ) : '';

	$lines   = array();
	$lines[] = '<?xml version="1.0" encoding="UTF-8"?>';
	$lines[] = '<OpenSearchDescription xmlns="http://a9.com/-/spec/opensearch/1.1/">';
	$lines[] = '  <ShortName>' . esc_html( $name ) . '</ShortName>';
	$lines[] = '  <Description>' . esc_html( 'Search ' . $name ) . '</Description>';
	$lines[] = '  <InputEncoding>UTF-8</InputEncoding>';
	if ( '' !== $icon ) {
		$lines[] = '  <Image width="64" height="64" type="image/png">' . esc_url( $icon ) . '</Image>';
	}
	$lines[] = '  <Url type="text/html" method="get" template="' . $template . '"/>';
	$lines[] = '  <Url type="application/opensearchdescription+xml" rel="self" template="' . esc_url( $home . '/opensearch.xml' ) . '"/>';
	$lines[] = '</OpenSearchDescription>';
	$lines[] = '';

	return implode( "\n", $lines );
}

/**
 * Emit the 200 status + OSDD MIME header + body. status_header( 200 ) is REQUIRED
 * (postless virtual path → 404 by template_redirect; WP-REFERENCE #40).
 */
function sn_opensearch_send() {
	if ( function_exists( 'status_header' ) ) {
		status_header( 200 );
	}
	header( 'Content-Type: application/opensearchdescription+xml; charset=' . get_option( 'blog_charset', 'UTF-8' ) );
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- XML document; node/attr values are esc_html/esc_url'd in sn_opensearch_body() and the {searchTerms} token must stay literal.
	echo sn_opensearch_body();
}

/**
 * template_redirect handler: serve the OSDD, then exit.
 */
function sn_opensearch_maybe_serve() {
	$req = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
	if ( ! sn_opensearch_is_request( $req ) ) {
		return;
	}
	sn_opensearch_send();
	exit;
}

/**
 * <head>: advertise the OSDD so browsers can register the site as a search
 * provider. Static — no user data.
 */
function sn_opensearch_head_link() {
	printf(
		'<link rel="search" type="application/opensearchdescription+xml" title="%s" href="%s">' . "\n",
		esc_attr( sn_opensearch_short_name() ),
		esc_url( home_url( '/opensearch.xml' ) )
	);
}

if ( ! defined( 'SN_OPENSEARCH_TEST' ) || ! SN_OPENSEARCH_TEST ) {
	add_action( 'template_redirect', 'sn_opensearch_maybe_serve', 0 );
	add_action( 'wp_head', 'sn_opensearch_head_link' );
}
