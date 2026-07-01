<?php
/**
 * Signal & Noise — Cited-by footer.
 *
 * Registers [sn_cited_by] — the REVERSE of related-notes: up to N published
 * Notes whose post_content links to the current Note at /notes/<post_name>
 * (absolute or site-relative href). Rendered in the single.html footer
 * beside [sn_related_notes], same render_block bridge convention.
 *
 * WordPress pingbacks would be the native answer but are deliberately dead
 * here (the plugin's security-headers module kills XML-RPC + pings_open;
 * comments are unsurfaced) — this bounded reverse-content query is the
 * complement, not a duplicate.
 *
 * Query shape: one SQL LIKE prefilter (bounded LIMIT 50) + a PHP boundary
 * regex, because LIKE '%/notes/craft%' would also match /notes/craft-two.
 * Empty result renders '' — no chrome on uncited notes.
 *
 * @package SignalNoise
 * @since 10.21.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Boundary-aware pattern for "links to /notes/$post_name". After the slug we
 * require a path / quote / query / fragment terminator or end-of-string, so
 * slug prefixes never collide. Mirrors the plugin's
 * sn_health_contains_note_link() so the two surfaces agree.
 *
 * @param string $post_name Note slug.
 * @return string PCRE pattern.
 */
function sn_cited_by_link_pattern( $post_name ) {
	return '#/notes/' . preg_quote( (string) $post_name, '#' ) . '(?=[/"\'?\#]|$)#i';
}

/**
 * IDs of published Notes citing $post_id, recency DESC, capped at $limit.
 *
 * @param int $post_id The cited note.
 * @param int $limit   Max citers.
 * @return int[]
 */
function sn_cited_by_query( $post_id, $limit = 5 ) {
	global $wpdb;
	$post_id = (int) $post_id;
	$limit   = max( 1, (int) $limit );
	$post    = get_post( $post_id );
	if ( ! $post || '' === (string) $post->post_name ) {
		return array();
	}

	$needle = '/notes/' . $post->post_name;
	// LIKE prefilter is broad (prefix collisions included) — LIMIT 50 bounds
	// the PHP pass; the boundary regex below is the precise filter.
	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT ID, post_content FROM {$wpdb->posts}
		 WHERE post_status = 'publish'
		   AND post_type = 'post'
		   AND ID != %d
		   AND post_content LIKE %s
		 ORDER BY post_date DESC
		 LIMIT 50",
		$post_id,
		'%' . $wpdb->esc_like( $needle ) . '%'
	), ARRAY_A );
	if ( ! is_array( $rows ) ) {
		return array();
	}

	$pattern = sn_cited_by_link_pattern( (string) $post->post_name );
	$ids     = array();
	foreach ( $rows as $row ) {
		if ( preg_match( $pattern, (string) $row['post_content'] ) ) {
			$ids[] = (int) $row['ID'];
			if ( count( $ids ) >= $limit ) {
				break;
			}
		}
	}
	return $ids;
}

/**
 * [sn_cited_by] — render the Cited-by footer for the queried Note.
 * Mirrors the related-notes .sn-notes-row vocabulary (date spec column +
 * linked title). Empty result renders '' (no chrome).
 *
 * @return string
 */
function sn_cited_by_shortcode() {
	$post_id = (int) get_queried_object_id();
	if ( $post_id < 1 ) {
		return '';
	}

	$citers = sn_cited_by_query( $post_id, (int) apply_filters( 'sn_cited_by_count', 5 ) );
	if ( empty( $citers ) ) {
		return '';
	}

	$rows = '';
	foreach ( $citers as $id ) {
		$rows .= sprintf(
			'<li class="sn-notes-row"><div class="sn-notes-row-spec"><time class="sn-notes-row-date" datetime="%1$s">%2$s</time></div><div class="sn-notes-row-content"><h3 class="sn-notes-row-title"><a href="%3$s">%4$s</a></h3></div></li>',
			esc_attr( get_the_date( 'c', $id ) ),
			esc_html( get_the_date( 'Y.m.d', $id ) ),
			esc_url( get_permalink( $id ) ),
			esc_html( get_the_title( $id ) )
		);
	}

	return '<footer class="sn-cited-by" aria-label="Cited by">'
		. '<h2 class="sn-cited-by__label">Cited by</h2>'
		. '<ul class="sn-cited-by__list">' . $rows . '</ul>'
		. '</footer>';
}

/**
 * Resolve [sn_cited_by] inside block templates. core/shortcode only
 * wpautop()s its content — it never runs do_shortcode on block-template
 * output. Mirrors sn_related_notes_render_block_bridge (inc/related-notes.php).
 *
 * @param string $block_content Rendered block HTML.
 * @param array  $block         Parsed block (unused).
 * @return string
 */
function sn_cited_by_render_block_bridge( $block_content, $block ) {
	if ( false !== strpos( $block_content, '[sn_cited_by' ) ) {
		$block_content = do_shortcode( shortcode_unautop( $block_content ) );
	}
	return $block_content;
}

// Skip WP registration under the standalone test harness (add_shortcode /
// add_filter aren't stubbed there; the helpers are exercised directly).
if ( ! defined( 'SN_CITED_BY_TEST' ) || ! SN_CITED_BY_TEST ) {
	add_shortcode( 'sn_cited_by', 'sn_cited_by_shortcode' );
	add_filter( 'render_block', 'sn_cited_by_render_block_bridge', 10, 2 );
}
