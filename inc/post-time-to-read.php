<?php
/**
 * Reading time is core's Time to Read block (core/post-time-to-read), showing
 * the companion plugin's figure (2026-11-10).
 *
 * The block counts words itself at 189 wpm and says "N minutes". The site's
 * byline, schema and tracklist all use the plugin's sn_get_reading_time(),
 * so the figure is swapped in here and worded as before ("N min read").
 * Without the plugin, core's own text stays.
 *
 * @package SignalNoise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter(
	'render_block_core/post-time-to-read',
	static function ( $html, $block, $instance = null ) {
		$attrs = (array) ( $block['attrs'] ?? array() );
		if ( 'time' !== ( $attrs['displayMode'] ?? 'time' ) || ! empty( $attrs['displayAsRange'] ) ) {
			return $html;
		}
		$post_id = (int) ( $instance->context['postId'] ?? 0 );
		if ( ! $post_id || ! function_exists( 'sn_get_reading_time' ) || ! class_exists( 'WP_HTML_Tag_Processor' ) ) {
			return $html;
		}
		$tags = new WP_HTML_Tag_Processor( (string) $html );
		while ( $tags->next_token() ) {
			if ( '#text' === $tags->get_token_type() ) {
				$tags->set_modifiable_text( sprintf( '%d min read', max( 1, (int) sn_get_reading_time( $post_id ) ) ) );
				break;
			}
		}
		return $tags->get_updated_html();
	},
	10,
	3
);
