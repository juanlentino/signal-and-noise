<?php
/**
 * /notes index helpers: paging and the archive query.
 *
 * Moved verbatim out of inc/notes-index-helpers.php, which loads it.
 *
 * @package SignalNoise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Notes per page for the /notes index. Default 20; overridable by the
 * plugin via the sn_notes_per_page filter (Release 2). Clamped [1,100]
 * to defend against a bad filter return.
 */
function sn_notes_per_page() {
	$n = (int) apply_filters( 'sn_notes_per_page', 20 );
	return max( 1, min( 100, $n ) );
}

/**
 * Resolve the current page number for the /notes index. Reads WP's
 * `paged` query var, falling back to the raw ?paged= query-string
 * param — the short-circuit router (inc/page-notes-template.php) may
 * not populate the query var cleanly, and the paginate_links() output
 * carries ?paged=N. Floored at 1.
 */
function sn_notes_current_page() {
	$paged = (int) get_query_var( 'paged' );
	if ( $paged < 1 && isset( $_GET['paged'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only pagination index, no state change.
		$paged = (int) $_GET['paged']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}
	return max( 1, $paged );
}

/**
 * Query the notes posts in chronological-descending order.
 *
 * Constraint: post_type=post (Signal & Noise treats all blog posts
 * as Notes — there's no separate post type — and the routing
 * `/notes/%postname%/` is enforced by sn_ensure_permalink_structure).
 * No taxonomy filter needed.
 */
function sn_notes_query_posts() {
	$tag_id        = sn_notes_current_tag_id();
	$start_here_id = sn_notes_start_here_id();
	$term_for_mode = sn_notes_search_term();
	// v11.10.0: BROWSE mode returns the whole corpus in one response and drops
	// pagination entirely. Pagination is what you reach for when a list has no
	// structure but recency; the year spine bounds the VISIBLE page instead
	// (prior years collapse to one line each), so paging would only re-hide
	// what the spine already folds.
	//
	// v12.13.0: A TAG ARCHIVE BROWSES, IT DOES NOT SEARCH. The condition used to
	// read `&& $tag_id <= 0`, grouping tags with search as "filtered" — and the
	// reason given for paginating ("a search result set is unbounded by nature")
	// is true of search and false of a tag. A tag is a BOUNDED, curated set: 23
	// terms deliberately cut down from 83, the largest holding 26 of 38 notes,
	// and every member chosen. It cannot exceed its own count. Search takes an
	// arbitrary string and can return anything, so it keeps sn_notes_per_page().
	//
	// Only Provenance (26) exceeded the 20-per-page default, so this consolidates
	// exactly one page today — but it is the page most likely to carry the notes
	// Google has not indexed, and /tag/provenance/page/2/ was a weaker crawl path
	// for two thirds of the corpus. The sibling half of this fix is in
	// inc/page-notes-render.php: the year spine now folds a tag archive too.
	$browse_all = ( '' === $term_for_mode );
	$args = array(
		'post_type'           => 'post',
		'post_status'         => 'publish',
		'posts_per_page'      => $browse_all ? -1 : sn_notes_per_page(),
		'paged'               => $browse_all ? 1 : sn_notes_current_page(),
		'orderby'             => 'date',
		'order'               => 'DESC',
		'no_found_rows'       => false, // pagination needs found_posts / max_num_pages
		'ignore_sticky_posts' => true,  // the sticky is floated into the Start-here card, never the list
		// v11.4.6: password-protected entries stay off the index and out of search.
		// Search rows render get_the_excerpt(), which for a protected post is WP's
		// "There is no excerpt because this is a protected post." placeholder — no
		// body ever leaked, so this is convention symmetry with inc/feed-json.php and
		// inc/llms-txt.php (and it drops the useless placeholder rows). CMA audit INFO-3.
		'has_password'        => false,
	);
	// Browse mode is Notes-only by construction (post_type=post = the whole
	// Notes corpus). SEARCH mode (v10.51.0) widens to the whole public corpus:
	// posts AND pages, so essays and editorial Pages surface in one
	// type-labeled list (sn_notes_result_type_label) — the owner-decided
	// session-4 shape.
	$term = $term_for_mode;
	if ( '' !== $term ) {
		$args['s']         = $term;
		$args['post_type'] = array( 'post', 'page' );
		// v13.0.0: the companion plugin (>= 14.0.0) ranks NOTES with its
		// kernel when this flag is present, by shaping this same query through
		// posts_clauses; pages and LIKE-only notes keep their date order. With
		// the plugin off the var is inert and this is the query it always was.
		$args['sn_notes_search'] = true;
	}
	if ( $tag_id > 0 ) {
		// Tag-archive mode: constrain to the queried post_tag.
		$args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- single-term tag archive, the page's sole query.
			array(
				'taxonomy' => 'post_tag',
				'field'    => 'term_id',
				'terms'    => $tag_id,
			),
		);
	} elseif ( $start_here_id > 0 ) {
		// Browse mode: the Start-here post is shown as the pinned card above;
		// keep it out of the chronological list (every page) so it never
		// appears twice. Consistent across pages → honest found_posts count.
		$args['post__not_in'] = array( $start_here_id );
	}
	return new WP_Query( $args );
}
