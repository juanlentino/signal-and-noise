<?php
/**
 * /notes index helpers: the row date and reading time.
 *
 * Moved verbatim out of inc/notes-index-helpers.php, which loads it.
 *
 * @package SignalNoise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Format a post date for the catalog layout.
 *
 *   2026.05.07
 *
 * Calendar-style with dots, big enough to scan but tight. Uses the
 * post's published date in site timezone.
 */
function sn_notes_render_date( $post ) {
	$ts = get_the_time( 'U', $post );
	return esc_html( wp_date( 'Y.m.d', (int) $ts ) );
}

/**
 * Format a post's reading time, padded for visual rhythm in the
 * spec column.
 *
 *   03 MIN
 *
 * Reads cached value from sn_reading_time post meta if available;
 * computes on the fly otherwise. Two-digit zero-padded for
 * tabular alignment with the date.
 */
function sn_notes_render_reading_time( $post_id ) {
	// Read the canonical cache populated by inc/reading-time.php on save.
	// The constant lives in that module; fall back to the literal key if
	// reading-time.php is somehow not loaded so this never goes stale.
	$meta_key = defined( 'SN_READING_TIME_META_KEY' ) ? SN_READING_TIME_META_KEY : '_sn_reading_time_minutes';
	$mins     = (int) get_post_meta( $post_id, $meta_key, true );
	if ( $mins < 1 ) {
		// Cache miss on a brand-new post that hasn't been saved through
		// the wp_after_insert_post hook yet. Use the canonical helper so
		// we share block-stripping + WPM with the shortcode path; this
		// also populates the cache for the next render.
		$mins = function_exists( 'sn_get_reading_time' )
			? (int) sn_get_reading_time( $post_id )
			: 1;
	}
	return sprintf( '%02d MIN', $mins );
}
