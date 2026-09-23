<?php
/**
 * Signal & Noise: the front page falls back to the hero pattern while its Page is empty.
 *
 * templates/front-page.html renders the front-page Page's content, like every
 * other page, so the owner edits the hero in the block editor and theme
 * updates never overwrite it (a Site Editor override was deleted by the
 * template purge, 2026-09-23). The companion plugin seeds that Page once. Until
 * it has, or if the Page is ever emptied, the home page must not render blank:
 * an empty post-content block is replaced by patterns/home-hero.php.
 *
 * @package SignalNoise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @param string $html  Rendered core/post-content.
 * @param array  $block Parsed block.
 * @return string
 */
function sn_front_page_hero_fallback( $html, $block ) {
	// The Page's saved content, not the rendered HTML: a Page holding only an
	// image renders no text and must still count as content.
	if ( ! is_front_page() || '' !== trim( (string) get_post_field( 'post_content', get_queried_object_id() ) ) ) {
		return $html;
	}
	$pattern = WP_Block_Patterns_Registry::get_instance()->get_registered( 'signal-noise/home-hero' );
	return $pattern ? do_blocks( $pattern['content'] ) : $html;
}
add_filter( 'render_block_core/post-content', 'sn_front_page_hero_fallback', 10, 2 );
