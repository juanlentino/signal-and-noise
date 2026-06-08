<?php
/**
 * Signal & Noise — Post frontmatter pillar shortcode.
 *
 * Registers [sn_post_pillar] — used inside parts/post-frontmatter.html
 * to render the PILLAR slot when a post is tagged with a pillar slug.
 *
 * Convention-based tag matching. The shortcode degrades gracefully:
 * posts whose tags don't match any pillar return empty string.
 *
 * @package SignalNoise
 * @since 9.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function sn_post_pillar_shortcode() {
	$pillar_map = array(
		'provenance' => array(
			'label' => 'PROVENANCE',
			'href'  => '/provenance/over-detection/',
		),
		// Add additional pillars here as their essay URLs are published.
	);

	$post = get_post();
	if ( ! $post ) {
		return '';
	}

	$tags = wp_get_post_tags( $post->ID, array( 'fields' => 'slugs' ) );
	if ( empty( $tags ) || ! is_array( $tags ) ) {
		return '';
	}

	foreach ( $tags as $tag_slug ) {
		if ( isset( $pillar_map[ $tag_slug ] ) ) {
			$p = $pillar_map[ $tag_slug ];
			return sprintf(
				'<a class="sn-post-frontmatter__pillar" href="%s">%s</a>',
				esc_url( $p['href'] ),
				esc_html( $p['label'] )
			);
		}
	}

	return '';
}
add_shortcode( 'sn_post_pillar', 'sn_post_pillar_shortcode' );

/**
 * Bridge: resolve [sn_post_pillar] inside block template-part markup.
 *
 * Belt-and-suspenders. Core already do_shortcodes a template part's RAW
 * markup before do_blocks() — render_block_core_template_part(),
 * wp-includes/blocks/template-part.php — so on the standard front-end path the
 * token is resolved before render_block ever fires and this filter is a
 * redundant no-op (the strpos guard finds nothing). It is kept for parity with
 * the [current_year] (inc/setup.php) and [sn_reading_time] (companion plugin)
 * bridges, and as version-independent insurance if the frontmatter part is ever
 * rendered outside the template-part render path (e.g. inlined into a pattern).
 * See docs/WORDPRESS-REFERENCE.md §1.2 for why core resolves it already.
 *
 * @param string $block_content Rendered block HTML.
 * @param array  $block         Parsed block (unused).
 * @return string Block HTML with [sn_post_pillar] resolved.
 */
function sn_post_pillar_render_block( $block_content, $block ) {
	if ( strpos( $block_content, '[sn_post_pillar' ) !== false ) {
		$block_content = do_shortcode( $block_content );
	}
	return $block_content;
}
add_filter( 'render_block', 'sn_post_pillar_render_block', 10, 2 );
