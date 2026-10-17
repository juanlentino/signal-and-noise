<?php
/**
 * The pillar essays' signal path (2026.10): under each pillar's brow, the
 * series in reading order as a thin chain, the current essay marked and the
 * others linked. Same order as Next essay (sn_theme_pillar_sort over the
 * descriptors), so the two never disagree. Emitted by the eyebrow filter
 * (inc/pillar-title-eyebrow.php), before the post-content element, so nothing
 * inside the signed region changes.
 *
 * @package SignalNoise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The series path for a pillar page, or ''.
 *
 * @param int $page_id The pillar page being viewed.
 * @return string
 */
function sn_essay_signal_path_html( $page_id ) {
	if ( ! function_exists( 'sn_theme_pillar_descriptors' ) || ! function_exists( 'sn_theme_pillar_sort' ) ) {
		return '';
	}
	$page = get_post( (int) $page_id );
	if ( ! $page ) {
		return '';
	}
	$current = (string) get_page_uri( $page );
	$pillars = array_values( array_filter( sn_theme_pillar_sort( sn_theme_pillar_descriptors() ), static fn( $d ) => '' !== (string) ( $d['designation'] ?? '' ) ) );
	if ( count( $pillars ) < 2 || ! in_array( $current, array_column( $pillars, 'slug' ), true ) ) {
		return '';
	}
	$items = '';
	foreach ( $pillars as $d ) {
		$no    = '<span class="sn-signal-path__no">' . esc_html( $d['designation'] ) . '</span>';
		$title = '<span class="sn-signal-path__title">' . esc_html( $d['title'] ) . '</span>';
		$items .= ( $d['slug'] === $current )
			? '<li aria-current="page">' . $no . $title . '</li>'
			: '<li><a href="' . esc_url( home_url( '/' . $d['slug'] . '/' ) ) . '">' . $no . $title . '</a></li>';
	}
	return '<nav class="sn-signal-path" aria-label="' . esc_attr__( 'Essay series', 'signal-and-noise' ) . '"><ol>' . $items . '</ol></nav>';
}
