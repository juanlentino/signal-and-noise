<?php
/**
 * Mark the header nav link for the page being viewed with aria-current="page".
 *
 * Core sets aria-current only on navigation-link blocks of kind "post-type"
 * that carry an id. The header's links are kind "custom" (parts/header.html),
 * so no page ever announced itself as current (live audit P3-2, 2026.10).
 *
 * "Current" means this exact page: paths compared without a trailing slash or
 * query string. A child URL is not the page (/notes is not current on a single
 * note) and an off-site link never is. On the front page the Home link is
 * current like any other (owner, 2026-10-16: the home page takes design
 * changes, only not new content).
 *
 * @package SignalNoise
 * @since 2026.10
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A URL's path, normalized for comparison: no trailing slash, "/" for root.
 * Null when the URL points at another host.
 *
 * @param string $url Absolute or root-relative URL.
 * @return string|null
 */
function sn_nav_current_path( $url ) {
	$host = wp_parse_url( (string) $url, PHP_URL_HOST );
	if ( $host && wp_parse_url( home_url( '/' ), PHP_URL_HOST ) !== $host ) {
		return null;
	}
	$path = (string) wp_parse_url( (string) $url, PHP_URL_PATH );
	$path = rtrim( $path, '/' );
	return '' === $path ? '/' : $path;
}

/**
 * render_block_core/navigation-link: add aria-current="page" to the link
 * whose URL is the request's own path.
 *
 * @param string $block_content Rendered block.
 * @param array  $block         Parsed block.
 * @return string
 */
function sn_nav_mark_current( $block_content, $block ) {
	$url = isset( $block['attrs']['url'] ) ? (string) $block['attrs']['url'] : '';
	if ( '' === $url || false !== strpos( $block_content, 'aria-current=' ) ) {
		return $block_content;
	}
	$link    = sn_nav_current_path( $url );
	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- only compared against a link path, never output or stored.
	$request = sn_nav_current_path( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '' );
	if ( null === $link || $link !== $request ) {
		return $block_content;
	}
	$p = new WP_HTML_Tag_Processor( $block_content );
	if ( ! $p->next_tag( 'a' ) ) {
		return $block_content;
	}
	$p->set_attribute( 'aria-current', 'page' );
	return $p->get_updated_html();
}
add_filter( 'render_block_core/navigation-link', 'sn_nav_mark_current', 10, 2 );
