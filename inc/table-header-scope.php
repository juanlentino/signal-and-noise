<?php
/**
 * Signal & Noise: the comparison table's header cells carry scope="col".
 *
 * The house table (core/table with the "Comparison table" style,
 * styles/blocks/sn-table.json) requires a header row whose cells are
 * <th scope="col">. Core's table editor never writes scope: its state
 * creates header cells as {tag: 'th'} and there is no control for it, so a
 * table built in the editor stores bare <th> (measured on WordPress 7.1,
 * 2026-10-16). Rather than ask authors for markup the editor cannot make, the
 * style guarantees it when the block renders.
 *
 * Only <th> inside <thead>, only when scope is absent (an author's own
 * scope, colgroup for one, is kept), only for is-style-sn-table. An attribute
 * on a tag changes no text, so nothing the provenance ledger signs moves.
 *
 * @package SignalNoise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Add scope="col" to the header cells of a comparison table.
 *
 * @param string $html  Rendered block HTML.
 * @param array  $block Parsed block.
 * @return string
 */
function sn_table_header_scope( $html, $block ) {
	$classes = preg_split( '/\s+/', trim( (string) ( $block['attrs']['className'] ?? '' ) ) );
	if ( ! in_array( 'is-style-sn-table', $classes, true ) || false === stripos( (string) $html, '<thead' ) ) {
		return $html;
	}
	$p       = new WP_HTML_Tag_Processor( (string) $html );
	$in_head = false;
	while ( $p->next_tag( array( 'tag_closers' => 'visit' ) ) ) {
		$tag = $p->get_tag();
		if ( 'THEAD' === $tag ) {
			$in_head = ! $p->is_tag_closer();
			continue;
		}
		if ( $in_head && 'TH' === $tag && ! $p->is_tag_closer() && null === $p->get_attribute( 'scope' ) ) {
			$p->set_attribute( 'scope', 'col' );
		}
	}
	return $p->get_updated_html();
}
add_filter( 'render_block_core/table', 'sn_table_header_scope', 10, 2 );
