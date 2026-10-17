<?php
/**
 * Résumé arrangement lanes (2026.10): under each role's date line on /resume
 * and /cv, a hairline lane on one shared axis from the earliest role to today,
 * like clips on a DAW timeline, so parallel roles read as parallel. Drawn at
 * render time from the date line already on the page (a render_block filter on
 * the rail paragraph), so the stored page never changes. A date line it cannot
 * read is skipped, never guessed.
 *
 * @package SignalNoise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Month abbreviations, English and Spanish, to a 0-based index.
 *
 * @return array<string,int>
 */
function sn_resume_lane_months() {
	return array(
		'jan' => 0, 'feb' => 1, 'mar' => 2, 'apr' => 3, 'may' => 4, 'jun' => 5, 'jul' => 6, 'aug' => 7, 'sep' => 8, 'oct' => 9, 'nov' => 10, 'dec' => 11,
		'ene' => 0, 'abr' => 3, 'ago' => 7, 'set' => 8, 'dic' => 11,
	);
}

/**
 * A role's date line as a span.
 *
 * @param string $text e.g. "Sep 2018 - Present", "ene. 2016 - actualidad".
 * @return array{start:float,end:float|null}|null Null when it cannot be read; end null = still open.
 */
function sn_resume_lane_span( $text ) {
	$m_re = '([a-z]{3})[a-z]*\.?\s+(\d{4})';
	if ( 1 !== preg_match( '/' . $m_re . '\s*[-–]\s*(?:' . $m_re . '|(present|actualidad|presente))/iu', (string) $text, $m ) ) {
		return null;
	}
	$months = sn_resume_lane_months();
	$m1     = strtolower( $m[1] );
	if ( ! isset( $months[ $m1 ] ) ) {
		return null;
	}
	$start = (int) $m[2] + $months[ $m1 ] / 12;
	if ( ! empty( $m[5] ) ) {
		return array( 'start' => $start, 'end' => null );
	}
	$m3 = strtolower( $m[3] ?? '' );
	if ( ! isset( $months[ $m3 ] ) ) {
		return null;
	}
	return array( 'start' => $start, 'end' => (int) $m[4] + $months[ $m3 ] / 12 );
}

/**
 * The lane for one span on the shared axis.
 *
 * @param array $span From sn_resume_lane_span().
 * @param float $min  Axis start (earliest role).
 * @param float $now  Axis end (today).
 * @return string
 */
function sn_resume_lane_html( $span, $min, $now ) {
	$width = (float) $now - (float) $min;
	if ( $width <= 0 || ! is_array( $span ) ) {
		return '';
	}
	$end  = null === $span['end'] ? (float) $now : (float) $span['end'];
	$left = ( $span['start'] - $min ) / $width * 100;
	$w    = max( 0, $end - $span['start'] ) / $width * 100;
	return '<div class="sn-resume-lane" aria-hidden="true"><i' . ( null === $span['end'] ? ' class="is-open"' : '' )
		. ' style="--s:' . esc_attr( sprintf( '%.2F', $left ) ) . '%;--w:' . esc_attr( sprintf( '%.2F', $w ) ) . '%"></i></div>';
}

/**
 * The earliest role start on the page being rendered (memoized per request).
 *
 * @return float|null
 */
function sn_resume_lane_axis_start() {
	if ( array_key_exists( 'sn_resume_lane_axis', $GLOBALS ) ) {
		return $GLOBALS['sn_resume_lane_axis'];
	}
	$min  = null;
	$post = function_exists( 'get_queried_object' ) ? get_queried_object() : null;
	$raw  = is_object( $post ) ? (string) ( $post->post_content ?? '' ) : '';
	if ( preg_match_all( '#<p[^>]*class="[^"]*\bsn-resume-rail\b[^"]*"[^>]*>(.*?)</p>#is', $raw, $ms ) ) {
		foreach ( $ms[1] as $inner ) {
			$span = sn_resume_lane_span( wp_strip_all_tags( $inner ) );
			if ( $span && ( null === $min || $span['start'] < $min ) ) {
				$min = $span['start'];
			}
		}
	}
	$GLOBALS['sn_resume_lane_axis'] = $min;
	return $min;
}

/**
 * render_block_core/paragraph: append the lane under a résumé rail.
 *
 * @param string $html  Rendered paragraph.
 * @param array  $block Parsed block.
 * @return string
 */
function sn_resume_lane_filter( $html, $block ) {
	if ( false === strpos( (string) ( $block['attrs']['className'] ?? '' ), 'sn-resume-rail' ) ) {
		return $html;
	}
	$span = sn_resume_lane_span( wp_strip_all_tags( (string) $html ) );
	$min  = $span ? sn_resume_lane_axis_start() : null;
	if ( ! $span || null === $min ) {
		return $html;
	}
	$now = (int) gmdate( 'Y' ) + ( (int) gmdate( 'n' ) - 1 ) / 12;
	return $html . sn_resume_lane_html( $span, $min, $now );
}
add_filter( 'render_block_core/paragraph', 'sn_resume_lane_filter', 10, 2 );
