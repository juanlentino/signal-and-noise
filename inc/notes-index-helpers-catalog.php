<?php
/**
 * /notes index helpers: year and month grouping, and catalog numbers.
 *
 * Moved verbatim out of inc/notes-index-helpers.php, which loads it.
 *
 * @package SignalNoise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Group posts by publication year, newest year first, newest post first.
 *
 * @param array $posts WP_Post-ish objects.
 * @return array<string,array> year => posts
 */
function sn_notes_group_by_year( $posts ) {
	$out = array();
	foreach ( (array) $posts as $p ) {
		$year = function_exists( 'get_the_date' ) ? (string) get_the_date( 'Y', $p ) : '';
		if ( '' === $year ) {
			$year = isset( $p->post_date ) ? substr( (string) $p->post_date, 0, 4 ) : '—';
		}
		$out[ $year ][] = $p;
	}
	krsort( $out, SORT_STRING );
	return $out;
}

/**
 * Whether the year spine should render at all.
 *
 * The spine exists to bound the page permanently as the corpus grows — but
 * every Note published so far is from a single year, so today it would draw
 * one band restating the count already in the section header. It renders when
 * it discriminates, and not before: a structure that appears the moment it
 * carries information, rather than a label that is decorative until 2027.
 *
 * @param array $grouped sn_notes_group_by_year() result.
 * @return bool
 */
function sn_notes_year_spine_is_useful( $grouped ) {
	return count( (array) $grouped ) > 1;
}

/**
 * Group posts by calendar month, newest first. Keys are 'YYYY-MM' so they sort
 * correctly as strings and never collide across years.
 *
 * @param array $posts
 * @return array<string,array>
 */
function sn_notes_group_by_month( $posts ) {
	$out = array();
	foreach ( (array) $posts as $p ) {
		$key = function_exists( 'get_the_date' ) ? (string) get_the_date( 'Y-m', $p ) : '';
		if ( '' === $key ) {
			$key = isset( $p->post_date ) ? substr( (string) $p->post_date, 0, 7 ) : '0000-00';
		}
		$out[ $key ][] = $p;
	}
	krsort( $out, SORT_STRING );
	return $out;
}

/**
 * Whether a year's rows should be subdivided by month.
 *
 * Same discipline as the year spine: a divider that fires on a handful of rows
 * is texture, not structure. Below this count the year reads fine as one run,
 * and chopping it would work against the reason the rows were made dense in the
 * first place — the titles are meant to be read in sequence.
 *
 * @param int $year_count
 * @return bool
 */
function sn_notes_month_dividers_are_useful( $year_count ) {
	return (int) $year_count >= SN_NOTES_MONTH_DIVIDER_MIN;
}

/**
 * A note's catalog number: its place among published notes, oldest first, so
 * the newest note carries the count of everything published. The number is a
 * property of the note, the same on the index, a tag archive or a search. One
 * ids-only query per request; a note outside the published set has none.
 *
 * @param int $post_id
 * @return int 1-based number, or 0.
 */
function sn_notes_catalog_number( $post_id ) {
	if ( ! isset( $GLOBALS['sn_notes_catalog'] ) ) {
		$ids = get_posts(
			array(
				'post_type'      => 'post',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'orderby'        => array( 'date' => 'ASC', 'ID' => 'ASC' ),
				'no_found_rows'  => true,
			)
		);
		$GLOBALS['sn_notes_catalog'] = array_flip( array_map( 'intval', (array) $ids ) );
	}
	$n = $GLOBALS['sn_notes_catalog'][ (int) $post_id ] ?? null;
	return null === $n ? 0 : $n + 1;
}

/**
 * Forget the cached catalog order (tests).
 */
function sn_notes_catalog_reset() {
	unset( $GLOBALS['sn_notes_catalog'] );
}
