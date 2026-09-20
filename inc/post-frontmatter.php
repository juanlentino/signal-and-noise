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

/**
 * Resolve the pillar anchor HTML for a post.
 *
 * Extracted so the Block Bindings source (inc/block-bindings.php) can pass an
 * explicit, context-resolved $post_id instead of relying on the global post
 * (BND-4). The shortcode wrapper below preserves the no-arg / global behavior.
 *
 * @param int $post_id Post to resolve for; 0 = the current global post.
 * @return string Anchor HTML, or '' when the post has no pillar tag.
 */
function sn_post_pillar_html( $post_id = 0 ) {
	$pillar_map = array(
		'provenance' => array(
			'label' => 'PROVENANCE',
			'href'  => '/provenance/over-detection/',
		),
		// Add additional pillars here as their essay URLs are published.
	);

	$post = $post_id ? get_post( $post_id ) : get_post();
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
 * [sn_post_pillar] — renders the PILLAR slot for the current global post.
 */
function sn_post_pillar_shortcode() {
	return sn_post_pillar_html();
}
add_shortcode( 'sn_post_pillar', 'sn_post_pillar_shortcode' );
// Placed by the signal-noise/post-pillar PHP-only block since 13.1.0; the
// shortcode stays registered for post content. The belt-and-suspenders
// render_block bridge went in #389: core already do_shortcodes a template
// part's raw markup before do_blocks() (docs/WORDPRESS-REFERENCE.md 1.2).
