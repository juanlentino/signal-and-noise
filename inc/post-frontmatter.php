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

/**
 * Resolve [sn_post_pillar] inside block template parts.
 *
 * core/shortcode only wpautop()s its content — it never runs do_shortcode
 * on block-template output (verified vs WP trunk
 * wp-includes/blocks/shortcode.php → render_block_core_shortcode is wpautop
 * only). Without this bridge the [sn_post_pillar] token rendered RAW on
 * every single Note. Mirrors the [current_year] bridge in inc/setup.php and
 * the sibling bridges in inc/related-notes.php / inc/post-share.php /
 * inc/post-updated-date.php.
 *
 * @param string $block_content Rendered block HTML.
 * @param array  $block         Parsed block (unused).
 * @return string
 */
function sn_post_pillar_render_block_bridge( $block_content, $block ) {
	if ( false !== strpos( $block_content, '[sn_post_pillar]' ) ) {
		$block_content = do_shortcode( $block_content );
	}
	return $block_content;
}

// Skip WP registration under the standalone test harness (add_shortcode /
// add_filter aren't stubbed there; the helpers are exercised directly).
if ( ! defined( 'SN_POST_FRONTMATTER_TEST' ) || ! SN_POST_FRONTMATTER_TEST ) {
	add_shortcode( 'sn_post_pillar', 'sn_post_pillar_shortcode' );
	add_filter( 'render_block', 'sn_post_pillar_render_block_bridge', 10, 2 );
}
