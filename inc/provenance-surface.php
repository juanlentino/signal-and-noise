<?php
/**
 * Signal & Noise — Provenance surface.
 *
 * Registers [sn_prov_chip] and [sn_prov_panel] — thin theme-side placement
 * seams for the companion plugin's public provenance rendering. The plugin
 * owns the markup (sn_prov_render_chip / sn_prov_render_panel); the theme only
 * decides WHERE it appears on a single Note:
 *
 *   - [sn_prov_chip]  → the byline pill, in parts/post-frontmatter.html, after
 *                       the pillar slot.
 *   - [sn_prov_panel] → the expandable provenance record, in
 *                       parts/post-closing.html, between the closing rule and
 *                       the prev/next nav.
 *
 * Both helpers are plugin-owned and function_exists()-guarded, exactly like
 * inc/related-notes.php guards sn_get_reading_time: when the companion plugin
 * is absent both shortcodes render '' and the Note degrades cleanly. The
 * plugin helpers themselves return '' for non-Notes / Notes without a chain,
 * so the shortcodes stay inert off a provenance-bearing Note.
 *
 * `core/shortcode` only wpautop()s its content — it does NOT run do_shortcode
 * on block-template output (verified vs WP trunk wp-includes/blocks/shortcode.php).
 * The render_block bridge below resolves the tokens inside the block template
 * parts, mirroring sn_related_notes_render_block_bridge (inc/related-notes.php)
 * and sn_404_suggestions_render_block_bridge (inc/404-recovery.php): the
 * shortcode_unautop() strips the invalid <p> that wpautop() wraps around the
 * (block-level) panel token before we resolve it.
 *
 * @package SignalNoise
 * @since 10.30.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * [sn_prov_chip] — the provenance byline pill for the current Note.
 *
 * Plugin-owned (sn_prov_render_chip): function_exists-guarded so the byline
 * degrades gracefully when the companion plugin is absent. The plugin returns
 * '' for non-Notes / Notes without a provenance chain.
 *
 * @return string Chip HTML, or '' when the plugin is absent or the Note has no chain.
 */
function sn_prov_chip_shortcode() {
	return function_exists( 'sn_prov_render_chip' )
		? sn_prov_render_chip( get_the_ID() )
		: '';
}

/**
 * [sn_prov_panel] — the expandable provenance record for the current Note.
 *
 * Plugin-owned (sn_prov_render_panel): function_exists-guarded, same graceful
 * degradation contract as the chip above.
 *
 * @return string Panel HTML, or '' when the plugin is absent or the Note has no chain.
 */
function sn_prov_panel_shortcode() {
	return function_exists( 'sn_prov_render_panel' )
		? sn_prov_render_panel( get_the_ID() )
		: '';
}

/**
 * Resolve [sn_prov_chip] / [sn_prov_panel] inside block template parts.
 *
 * core/shortcode only wpautop()s its content — it never runs do_shortcode on
 * block-template output. Mirrors sn_related_notes_render_block_bridge
 * (inc/related-notes.php) and sn_404_suggestions_render_block_bridge
 * (inc/404-recovery.php): shortcode_unautop() strips the <p> that wpautop()
 * wraps around the block-level panel token before we resolve it.
 *
 * @param string $block_content Rendered block HTML.
 * @param array  $block         Parsed block (unused).
 * @return string
 */
function sn_provenance_surface_render_block_bridge( $block_content, $block ) {
	if ( false !== strpos( $block_content, '[sn_prov_chip' )
		|| false !== strpos( $block_content, '[sn_prov_panel' ) ) {
		$block_content = do_shortcode( shortcode_unautop( $block_content ) );
	}
	return $block_content;
}

// Skip WP registration under the standalone test harness (add_shortcode /
// add_filter aren't stubbed there; the helpers are exercised directly).
if ( ! defined( 'SN_PROVENANCE_SURFACE_TEST' ) || ! SN_PROVENANCE_SURFACE_TEST ) {
	add_shortcode( 'sn_prov_chip', 'sn_prov_chip_shortcode' );
	add_shortcode( 'sn_prov_panel', 'sn_prov_panel_shortcode' );
	add_filter( 'render_block', 'sn_provenance_surface_render_block_bridge', 10, 2 );
}
