<?php
/**
 * Signal & Noise theme — the reading-path slot's shortcode bridge (v11.9.0).
 *
 * The PLUGIN (signal-and-noise-tools v11.3.0) owns [sn_reading_path] — the
 * renderer for the per-cluster reading chain (ML pipeline #10). This theme
 * places the token in templates/single.html, and this bridge is what makes a
 * block-template shortcode actually resolve: core/shortcode only wpautop()s
 * its content, never do_shortcode (the inc/related-notes.php finding, verified
 * against WP trunk).
 *
 * PLUGIN-ABSENT GUARD, and it is the reason this bridge returns '' rather
 * than passing through: with the plugin deactivated the shortcode is
 * unregistered, do_shortcode() would leave the literal token in the page, and
 * a reader would see "[sn_reading_path]" as prose. An empty slot is the
 * honest render — same posture as the renderer itself, which self-gates to ''
 * when it has nothing to show.
 *
 * @package signal-and-noise
 */

/**
 * Resolve the [sn_reading_path] token inside core/shortcode block output.
 *
 * @param string $block_content Rendered block HTML.
 * @param array  $block         Parsed block (unused).
 * @return string
 */
function sn_reading_path_render_block_bridge( $block_content, $block ) {
	if ( false === strpos( $block_content, '[sn_reading_path' ) ) {
		return $block_content;
	}
	if ( ! shortcode_exists( 'sn_reading_path' ) ) {
		return ''; // Plugin absent: an empty slot, never a literal token in prose.
	}
	return do_shortcode( shortcode_unautop( $block_content ) );
}

// Skip WP registration under the standalone test harness (the helpers are
// exercised directly there) — the related-notes idiom.
if ( ! defined( 'SN_READING_PATH_TEST' ) || ! SN_READING_PATH_TEST ) {
	add_filter( 'render_block', 'sn_reading_path_render_block_bridge', 10, 2 );
}
