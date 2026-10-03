<?php
/**
 * Signal & Noise: the next essay, at the end of each pillar.
 *
 * The pillars read in order (sn_theme_pillar_sort over the descriptors the
 * plugin's _sn_pillar flags derive). On a pillar Page this names the next
 * one; on the last pillar, the hub, or any other page it renders nothing.
 * It lives in the template, never the content: the content is signed, and
 * a line in it would mint a ledger version.
 *
 * @package SignalNoise
 * @since 14.9.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The pillar after the one at $uri, or null. 15.0.1 (Codex on #468): the
 * full page path, so two pages sharing a last segment cannot be confused.
 *
 * @param string $slug The current page's path (get_page_uri), e.g. provenance/as-substrate.
 * @return array|null Descriptor (slug, title, ...).
 */
function sn_next_essay_target( $slug ) {
	if ( '' === (string) $slug || ! function_exists( 'sn_theme_pillar_descriptors' ) ) {
		return null;
	}
	$pillars = array_values( sn_theme_pillar_sort( sn_theme_pillar_descriptors() ) );
	foreach ( $pillars as $i => $d ) {
		if ( $d['slug'] === $slug ) {
			return $pillars[ $i + 1 ] ?? null;
		}
	}
	return null;
}

/**
 * The next-essay line for the queried Page, or ''.
 *
 * @return string
 */
function sn_next_essay_html() {
	$page = get_queried_object();
	if ( ! $page || 'page' !== ( $page->post_type ?? '' ) ) {
		return '';
	}
	$next = sn_next_essay_target( (string) get_page_uri( $page ) );
	if ( null === $next || '' === $next['title'] ) {
		return '';
	}
	return '<nav class="sn-next-essay" aria-label="' . esc_attr__( 'Next essay', 'signal-and-noise' ) . '">'
		. '<a class="sn-next-essay__link" href="' . esc_url( home_url( '/' . $next['slug'] . '/' ) ) . '" data-sn-goal="next_essay">'
		. '<span class="sn-next-essay__dir">' . esc_html__( 'Next essay →', 'signal-and-noise' ) . '</span> '
		. '<span class="sn-next-essay__title">' . esc_html( $next['title'] ) . '</span></a></nav>';
}
