<?php
/**
 * /notes index helpers: sticky notes, the current tag, and Start Here.
 *
 * Moved verbatim out of inc/notes-index-helpers.php, which loads it.
 *
 * @package SignalNoise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sticky-post ids, defensively cast to int. Empty when none are set or the
 * option is absent. The function_exists guard keeps the standalone fixtures
 * (which don't stub get_option) resolving to "no sticky".
 *
 * @return int[]
 */
function sn_notes_sticky_ids() {
	if ( ! function_exists( 'get_option' ) ) {
		return array();
	}
	$ids = get_option( 'sticky_posts' );
	return is_array( $ids ) ? array_map( 'intval', $ids ) : array();
}

/**
 * The queried post_tag term id for a /notes tag-archive request, else 0.
 * Reads the queried object only when is_tag() is true; guarded so the
 * fixtures (no is_tag stub) resolve to browse mode (0).
 *
 * @return int
 */
function sn_notes_current_tag_id() {
	if ( ! function_exists( 'is_tag' ) || ! is_tag() ) {
		return 0;
	}
	$obj = function_exists( 'get_queried_object' ) ? get_queried_object() : null;
	return ( $obj && isset( $obj->term_id ) ) ? (int) $obj->term_id : 0;
}

/**
 * The queried tag's own description for a tag-archive request, else ''.
 *
 * The hero dek is otherwise the same sentence on all 23 tag archives, which
 * is the thin-content shape the contentless-page rule warns about. A term
 * that carries a description gets to speak for itself; one that does not
 * keeps the generic dek, because an invented sentence per tag would be
 * exactly the fabrication the rest of this codebase refuses.
 *
 * The plugin reads the SAME term description for the meta description
 * (v13.14.0), so writing one sentence lights up both surfaces at once.
 *
 * @since theme v12.11.0
 * @return string Stripped description, or '' when absent or not a tag view.
 */
function sn_notes_tag_description() {
	if ( ! function_exists( 'is_tag' ) || ! is_tag() ) {
		return '';
	}
	$obj = function_exists( 'get_queried_object' ) ? get_queried_object() : null;
	if ( ! $obj || ! isset( $obj->description ) ) {
		return '';
	}
	$desc = (string) $obj->description;
	$desc = function_exists( 'wp_strip_all_tags' ) ? wp_strip_all_tags( $desc ) : strip_tags( $desc );
	return trim( $desc );
}

/**
 * Is $post_id a published Note (post_type=post)? Guarded for the test
 * harness — returns false when the WP accessors are absent.
 *
 * @param int $post_id
 * @return bool
 */
function sn_notes_is_published_post( $post_id ) {
	$post_id = (int) $post_id;
	if ( $post_id < 1 || ! function_exists( 'get_post_status' ) ) {
		return false;
	}
	if ( 'publish' !== get_post_status( $post_id ) ) {
		return false;
	}
	if ( function_exists( 'get_post_type' ) && 'post' !== get_post_type( $post_id ) ) {
		return false;
	}
	return true;
}

/**
 * The "Start here" front-door post id: the first published sticky, but ONLY
 * in pure browse mode (no search, no tag) — search/tag views hide the card.
 * 0 when there's no eligible sticky. The owner stickies the post they want
 * pinned; this is the editorial control for the card.
 *
 * @return int
 */
function sn_notes_start_here_id() {
	if ( '' !== sn_notes_search_term() || sn_notes_current_tag_id() > 0 ) {
		return 0;
	}
	foreach ( sn_notes_sticky_ids() as $sid ) {
		if ( sn_notes_is_published_post( $sid ) ) {
			return (int) $sid;
		}
	}
	return 0;
}

/**
 * The Start Here PAGE id for the hero wayfinding link, or 0.
 *
 * Deliberately separate from sn_notes_start_here_id(): that one answers the
 * sticky-POST question that drives the pinned index row, and returns 0 once no
 * sticky qualifies. Start Here became a page (a child of /notes/) in 2026-08,
 * which freed its index slot but left it unreachable from any navigation — a
 * page is not in the post query, so nothing linked it. This resolves the page
 * itself so the hero can carry the link.
 *
 * Resolved by path rather than a pinned id so the link dies with the page
 * instead of pointing at a 404: an absent, trashed, or unpublished page yields
 * 0 and the hero simply renders without it.
 *
 * @return int
 */
function sn_notes_start_here_page_id() {
	if ( ! function_exists( 'get_page_by_path' ) || ! function_exists( 'get_post_status' ) ) {
		return 0;
	}
	$page = get_page_by_path( 'notes/start-here' );
	if ( ! $page || ! isset( $page->ID ) ) {
		return 0;
	}
	if ( 'publish' !== get_post_status( $page->ID ) ) {
		return 0;
	}
	return (int) $page->ID;
}
