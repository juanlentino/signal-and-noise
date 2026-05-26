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
