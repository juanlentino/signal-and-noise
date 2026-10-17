<?php
/**
 * Signal & Noise: the comparison table's header cells carry scope="col", and
 * its scroll box is a named, keyboard-reachable region.
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

/**
 * Make the comparison table's scroll box reachable from the keyboard.
 *
 * On a phone the table scrolls sideways inside its figure (styles/blocks/
 * sn-table.json sets a 30rem minimum width). Chrome and Firefox make a scroll
 * container focusable on their own; Safari does not, so a keyboard reader
 * there could never scroll a wide table (WCAG 2.1.1; axe's
 * scrollable-region-focusable rule). The figure gets tabindex="0",
 * role="region" and a name: the caption's text, or "Comparison table".
 * A figure that already sets tabindex is left as the author made it.
 * The ring it shows when focused is in assets/css/article.css.
 *
 * @param string $html  Rendered block HTML.
 * @param array  $block Parsed block.
 * @return string
 */
function sn_table_scroll_region( $html, $block ) {
	$classes = preg_split( '/\s+/', trim( (string) ( $block['attrs']['className'] ?? '' ) ) );
	if ( ! in_array( 'is-style-sn-table', $classes, true ) ) {
		return $html;
	}
	$name = 'Comparison table';
	if ( preg_match( '#<figcaption\b[^>]*>(.*?)</figcaption>#is', (string) $html, $m ) ) {
		$text = trim( preg_replace( '/\s+/', ' ', html_entity_decode( wp_strip_all_tags( $m[1] ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) );
		if ( '' !== $text ) {
			$name = $text;
		}
	}
	$p = new WP_HTML_Tag_Processor( (string) $html );
	if ( ! $p->next_tag( 'figure' ) || null !== $p->get_attribute( 'tabindex' ) ) {
		return $html;
	}
	$p->set_attribute( 'tabindex', '0' );
	$p->set_attribute( 'role', 'region' );
	$p->set_attribute( 'aria-label', $name );
	return $p->get_updated_html();
}
add_filter( 'render_block_core/table', 'sn_table_scroll_region', 10, 2 );
