<?php
/**
 * /notes index helpers: the search term, its paging args, and the hero stats.
 *
 * Moved verbatim out of inc/notes-index-helpers.php, which loads it.
 *
 * @package SignalNoise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolve the current Notes search term, if any. Mirrors
 * sn_notes_current_page(): reads WP's `s` query var, falling back to the
 * raw ?s= query-string param (the short-circuit router may not populate
 * the query var cleanly). Unslashed, tag-stripped, trimmed. Returns ''
 * when absent or whitespace-only (= browse mode). The empty short-circuit
 * means sanitize_text_field()/wp_unslash() are only touched when a term
 * exists — keeping the pagination fixtures (which don't stub them) green.
 */
function sn_notes_search_term() {
	$term = (string) get_query_var( 's' );
	if ( '' === $term && isset( $_GET['s'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only search index, no state change.
		$term = sanitize_text_field( wp_unslash( $_GET['s'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only, sanitized at point of read.
	}
	if ( '' === $term ) {
		return '';
	}
	return trim( sanitize_text_field( $term ) );
}

/**
 * The extra query args paginate_links() must carry so search-result page
 * 2+ stays inside the search. Empty array when browsing. The term is
 * rawurlencode()'d because WP's add_query_arg() does not URL-encode values
 * (urlencode=false), so a multi-word term would otherwise yield a broken
 * page link.
 */
function sn_notes_pagination_add_args( $term = '' ) {
	return ( '' !== $term ) ? array( 's' => rawurlencode( $term ) ) : array();
}

/**
 * Corpus-level hero stats for the /notes archive header: total entry count and
 * the newest note's date. Extracted (and kept above the SN_NOTES_RENDER_TEST
 * guard) so it's unit-testable, and so the count is the CORPUS total rather than
 * the current page's slice.
 *
 * - count: $query->found_posts — the whole result set, NOT $query->post_count
 *   (only this page's ≤per_page slice, which mis-read "N entries" on page 2+,
 *   e.g. "8 entries" on a short final page).
 * - latest_date: the newest note's date. The query is date-DESC, so on page 1
 *   posts[0] IS the newest (free). On page 2+ posts[0] is this page's first row,
 *   not the corpus newest, so the "Last updated" line is suppressed rather than
 *   show a wrong date. (The hero renders in browse mode only.)
 *
 * @param WP_Query $query The notes archive query.
 * @return array{count:int,latest_date:string}
 */
function sn_notes_hero_stats( $query ) {
	$count       = isset( $query->found_posts ) ? (int) $query->found_posts : 0;
	$latest_date = '';
	if ( $count > 0 && sn_notes_current_page() <= 1 && ! empty( $query->posts ) ) {
		$latest_date = wp_date( 'Y.m.d', (int) get_the_time( 'U', $query->posts[0] ) );
	}
	return array( 'count' => $count, 'latest_date' => $latest_date );
}
