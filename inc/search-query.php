<?php
/**
 * Signal & Noise — on-site search: make the grouped Query Loops search-aware.
 *
 * templates/search.html renders two core/query blocks with inherit:false
 * (postType=post "Notes", postType=page "Pages"). A non-inherited Query
 * Loop is built from block attributes and does NOT read the ?s= term, so
 * we inject it here. WordPress runs query_loop_block_query_vars ONLY on the
 * inherit:false path (the inherit:true path uses the global $wp_query and
 * never calls the builder) — verified against wp-includes/blocks.php
 * build_query_vars_from_query_block() on 2026-06-05.
 *
 * Guarded by is_search() + a post-type discriminator so we never bleed the
 * search term into an unrelated custom Query Loop elsewhere on the site.
 *
 * @package SignalNoise
 * @since 9.7.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Is this Post Template block one of our search-results loops?
 * True only when its query context targets posts or pages.
 *
 * @param mixed $block The WP_Block (post-template) passed to the filter.
 * @return bool
 */
function sn_is_search_loop( $block ) {
	if ( ! is_object( $block ) ) {
		return false;
	}
	$post_type = $block->context['query']['postType'] ?? '';
	return in_array( $post_type, array( 'post', 'page' ), true );
}

/**
 * Inject the current search term into our grouped, non-inherited Query
 * Loops so they return search results instead of a generic post list.
 *
 * @param array $query The WP_Query args built from the block.
 * @param mixed $block The WP_Block instance (post-template).
 * @return array
 */
function sn_search_inject_term( $query, $block ) {
	if ( ! is_search() ) {
		return $query;
	}
	if ( ! sn_is_search_loop( $block ) ) {
		return $query;
	}
	$query['s'] = get_search_query( false ); // false = raw term, not display-escaped.
	return $query;
}
add_filter( 'query_loop_block_query_vars', 'sn_search_inject_term', 10, 2 );
