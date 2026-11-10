<?php
/**
 * The 404 notes search is core/search (templates/404.html, 2026-11-10).
 *
 * Core renders a <search> landmark around a form that submits to home_url('/').
 * The theme's search results live on /notes/ (the OpenSearch description and
 * the Notes index use /notes/?s=), so the form keeps that action. The theme's
 * classes go on the field and the button, and the button drops core's
 * element-button class, whose theme.json padding and size would restyle it.
 * Rendered field and button (the iOS zoom guard reads these):
 *   <input class="wp-block-search__input sn-404-search__input" type="search" name="s">
 *   <button class="wp-block-search__button sn-404-search__submit" type="submit">
 *
 * @package SignalNoise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Dress the 404 search. Pure over its inputs.
 *
 * @param string $html   Rendered core/search.
 * @param string $action The results URL.
 * @return string
 */
function sn_search_404_html( $html, $action ) {
	if ( ! class_exists( 'WP_HTML_Tag_Processor' ) ) {
		return $html;
	}
	$tags = new WP_HTML_Tag_Processor( (string) $html );
	while ( $tags->next_tag() ) {
		$tag = $tags->get_tag();
		if ( 'FORM' === $tag ) {
			$tags->set_attribute( 'action', $action );
		} elseif ( 'INPUT' === $tag && 'search' === $tags->get_attribute( 'type' ) ) {
			$tags->add_class( 'sn-404-search__input' );
		} elseif ( 'BUTTON' === $tag ) {
			$tags->add_class( 'sn-404-search__submit' );
			$tags->remove_class( 'wp-element-button' );
		}
	}
	return $tags->get_updated_html();
}

add_filter(
	'render_block_core/search',
	static function ( $html, $block ) {
		if ( false === strpos( (string) ( $block['attrs']['className'] ?? '' ), 'sn-404-search' ) ) {
			return $html;
		}
		return sn_search_404_html( (string) $html, home_url( '/notes/' ) );
	},
	10,
	2
);
