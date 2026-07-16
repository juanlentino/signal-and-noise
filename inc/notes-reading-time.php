<?php
/**
 * Signal & Noise — reading-time helper (always loaded).
 *
 * WHY this file exists: sn_notes_reading_time_for_slug() used to live in
 * inc/page-notes-render.php, which functions.php loads ONLY via the /notes
 * template_include (inc/page-notes-template.php). That renderer runs top-level
 * output at include time (ob_start / do_blocks / wp_head), so it cannot be
 * loaded early or on a REST request. But the get-reading-time-for-slug and
 * get-page-notes-pillars Abilities run over REST / the plugin's MCP server,
 * where the renderer is never loaded — so the helper was undefined there and
 * both abilities silently fell back to "5 min" (found live via MCP,
 * v10.42.1). Extracting the helper to this dependency-free, always-required
 * file fixes both without pulling the whole renderer into a REST request. The
 * renderer keeps calling the same function name — now provided here.
 *
 * @package SignalNoise
 * @since 10.42.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'sn_notes_reading_time_for_slug' ) ) {
	/**
	 * The formatted reading-time string for a post by slug. Mirrors the
	 * [sn_reading_time slug="..."] shortcode (registered by the companion
	 * plugin, so it resolves in any request context including REST). Falls
	 * back to "5 min" when the shortcode is unavailable or the slug does not
	 * resolve to a viewable post.
	 *
	 * @param string $slug
	 * @return string
	 */
	function sn_notes_reading_time_for_slug( $slug ) {
		if ( ! function_exists( 'do_shortcode' ) ) {
			return '5 min';
		}
		$out = do_shortcode( '[sn_reading_time slug="' . esc_attr( (string) $slug ) . '"]' );
		$out = trim( wp_strip_all_tags( (string) $out ) );
		return '' !== $out ? $out : '5 min';
	}
}
