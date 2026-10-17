<?php
/**
 * The pillar essays' tracklist (2026.10): under the signal path, the essay's
 * sections set like the back of a sleeve, each linked, each with its length at
 * the byline's own reading rate (the plugin's sn_reading_time_wpm, 225 by
 * default), and the byline's own total in the head. Read from the stored
 * content and emitted by the eyebrow filter (inc/pillar-title-eyebrow.php),
 * before the post-content element, so nothing inside the signed region changes.
 *
 * A section is a "Section N" paragraph followed by its h2, wrapped in a section
 * group or loose in the content. It runs until the next label, or the end.
 *
 * @package SignalNoise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The essay's sections: title, anchor id ('' when it has none), length in seconds.
 *
 * @param string $content Stored post content.
 * @param int    $wpm     Reading rate, words per minute.
 * @return array<int, array{title: string, id: string, seconds: int}>
 */
function sn_essay_tracks( $content, $wpm ) {
	$content = (string) preg_replace( '/<!--\s*\/?wp:[^>]*-->/', ' ', (string) $content );
	if ( ! preg_match_all( '#<p\b[^>]*>\s*Section\s+\d+\s*</p>\s*<h2\b([^>]*)>(.*?)</h2>#is', $content, $m, PREG_SET_ORDER | PREG_OFFSET_CAPTURE ) ) {
		return array();
	}
	$tracks = array();
	$prev   = 0;
	foreach ( $m as $i => $hit ) {
		$start = $hit[0][1];
		$end   = $start + strlen( $hit[0][0] );
		$next  = isset( $m[ $i + 1 ] ) ? $m[ $i + 1 ][0][1] : strlen( $content );
		$id    = preg_match( '/\bid="([^"]+)"/', $hit[1][0], $hid ) ? $hid[1] : '';
		if ( '' === $id && preg_match_all( '/<section\b[^>]*\bid="([^"]+)"/i', substr( $content, $prev, $start - $prev ), $sid ) ) {
			$id = end( $sid[1] );
		}
		$words    = sn_essay_word_count( wp_strip_all_tags( substr( $content, $end, $next - $end ) ) );
		$tracks[] = array(
			'title'   => html_entity_decode( wp_strip_all_tags( $hit[2][0] ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ),
			'id'      => $id,
			'seconds' => (int) round( $words / max( 1, (int) $wpm ) * 60 ),
		);
		$prev = $end;
	}
	return $tracks;
}

/**
 * Words, counted the way the plugin's reading time counts them.
 *
 * @param string $text Plain text.
 * @return int
 */
function sn_essay_word_count( $text ) {
	if ( function_exists( 'snt_word_count' ) ) {
		return (int) snt_word_count( $text );
	}
	return (int) preg_match_all( '/[\p{L}\p{N}]+(?:[\'\x{2019}-][\p{L}\p{N}]+)*/u', (string) $text );
}

/**
 * The tracklist for a pillar page, or '' (no post, or fewer than two sections).
 *
 * @param int $page_id The pillar page being viewed.
 * @return string
 */
function sn_essay_tracklist_html( $page_id ) {
	$page = get_post( (int) $page_id );
	if ( ! $page ) {
		return '';
	}
	$tracks = sn_essay_tracks( $page->post_content, (int) apply_filters( 'sn_reading_time_wpm', 225, $page ) );
	if ( count( $tracks ) < 2 ) {
		return '';
	}
	$rows = '';
	foreach ( $tracks as $i => $t ) {
		$len   = sprintf( '<time datetime="PT%dM%dS">%d:%02d</time>', intdiv( $t['seconds'], 60 ), $t['seconds'] % 60, intdiv( $t['seconds'], 60 ), $t['seconds'] % 60 );
		$cells = '<span class="sn-essay-tracklist__no">A' . ( $i + 1 ) . '</span><span class="sn-essay-tracklist__title">' . esc_html( $t['title'] ) . '</span><span class="sn-essay-tracklist__len">' . $len . '</span>';
		$rows .= '<li>' . ( '' !== $t['id'] ? '<a href="#' . esc_attr( $t['id'] ) . '">' . $cells . '</a>' : '<span class="sn-essay-tracklist__row">' . $cells . '</span>' ) . '</li>';
	}
	$total = function_exists( 'sn_get_reading_time' ) ? '<span>' . (int) sn_get_reading_time( $page ) . ' min</span>' : '';
	return '<nav class="sn-essay-tracklist" aria-label="' . esc_attr__( 'Sections', 'signal-and-noise' ) . '">'
		. '<p class="sn-essay-tracklist__head"><span>Side A &middot; ' . count( $tracks ) . ' sections</span>' . $total . '</p><ol>' . $rows . '</ol></nav>';
}
