<?php
/**
 * /notes index helpers: the pagination base, result labels, and search robots.
 *
 * Moved verbatim out of inc/notes-index-helpers.php, which loads it.
 *
 * @package SignalNoise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pagination base URL: the tag-archive permalink in tag mode, the bare
 * /notes/ index otherwise. Both paginate via ?paged=%#% (the exact-path
 * router strips the query string before matching, so ?paged= is safe on
 * both routes).
 *
 * @return string
 */
function sn_notes_pagination_base() {
	$tag_id = sn_notes_current_tag_id();
	if ( $tag_id > 0 && function_exists( 'get_term_link' ) ) {
		$link = get_term_link( $tag_id, 'post_tag' );
		if ( is_string( $link ) && '' !== $link ) {
			return $link;
		}
	}
	return home_url( '/notes/' );
}

/**
 * Type label for a search-result row: Note (posts), Essay (pillar-designated
 * Pages, the _sn_pillar meta from the v10.47.0 curation surface), Page
 * (everything else). Pure; only meaningful in search mode (browse is
 * Notes-only so rows need no label).
 *
 * @param object $post Post-like object (ID + post_type).
 * @return string
 */
function sn_notes_result_type_label( $post ) {
	$type = isset( $post->post_type ) ? (string) $post->post_type : 'post';
	if ( 'post' === $type ) {
		return 'Note';
	}
	$pillar = (string) get_post_meta( (int) ( $post->ID ?? 0 ), '_sn_pillar', true );
	return '' !== $pillar && '0' !== $pillar ? 'Essay' : 'Page';
}

/**
 * Robots directives for search mode: a crafted ?s= URL must not be indexable
 * as site content (query-stuffing abuse), so a non-empty term appends
 * noindex + follow.
 *
 * v10.51.1: answers the PLUGIN's `sn_seo_robots_directives` seam — a directive
 * LIST. The plugin turns that list into core's wp_robots (since plugin
 * 2026.10.64; before that it removed core's emitter and printed its own).
 * v10.51.0 filtered `wp_robots` with a map and was live-verified inert: the
 * array it mutated was never printed. Pure: input never mutated, list-shaped
 * result, browse mode returns the input unchanged.
 *
 * @param array  $directives Directive list from the plugin's emitter.
 * @param string $term       Current search term ('' = browse).
 * @return array
 */
function sn_notes_search_robots( $directives, $term ) {
	$directives = array_values( (array) $directives );
	if ( '' === (string) $term ) {
		return $directives;
	}
	$directives[] = 'noindex';
	$directives[] = 'follow';
	return array_values( array_unique( $directives ) );
}
