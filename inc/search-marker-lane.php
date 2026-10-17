<?php
/**
 * Search marker lane (2026.10): on a search results row, a hairline with a
 * tick wherever the term occurs across that note, like markers on a session
 * timeline, plus the count as text. It shows why a note matched and where.
 * Drawn on the index row, never inside a note.
 *
 * @package SignalNoise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Most ticks one row draws. */
const SN_NOTES_LANE_MAX = 40;

/**
 * Where $term occurs in $text, as fractions of the text's length.
 *
 * @param string $text Plain text.
 * @param string $term The search term.
 * @return float[] 0..1, at most SN_NOTES_LANE_MAX.
 */
function sn_notes_term_positions( $text, $term ) {
	$text = (string) $text;
	$term = trim( (string) $term );
	$len  = mb_strlen( $text, 'UTF-8' );
	if ( '' === $term || 0 === $len ) {
		return array();
	}
	$out = array();
	$at  = 0;
	while ( count( $out ) < SN_NOTES_LANE_MAX ) {
		$hit = mb_stripos( $text, $term, $at, 'UTF-8' );
		if ( false === $hit ) {
			break;
		}
		$out[] = $hit / $len;
		$at    = $hit + max( 1, mb_strlen( $term, 'UTF-8' ) );
	}
	return $out;
}

/**
 * The lane markup for a set of positions, or ''.
 *
 * @param float[] $positions From sn_notes_term_positions().
 * @return string
 */
function sn_notes_row_lane_html( $positions ) {
	$positions = array_values( (array) $positions );
	if ( array() === $positions ) {
		return '';
	}
	$ticks = '';
	foreach ( $positions as $x ) {
		$ticks .= '<i style="--x:' . esc_attr( sprintf( '%.2F', max( 0, min( 1, (float) $x ) ) * 100 ) ) . '%"></i>';
	}
	$n = count( $positions );
	return '<div class="sn-notes-row-lane" aria-hidden="true">' . $ticks . '</div>'
		. '<span class="sn-notes-row-lane-n">' . esc_html( $n . ( 1 === $n ? ' match' : ' matches' ) ) . '</span>';
}
