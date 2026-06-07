<?php
/**
 * Signal & Noise — Copy-permalink + native Web Share row.
 *
 * Registers [sn_note_share] — rendered in the single-note footer
 * (parts/post-closing.html). Emits a static, valid-by-construction row:
 *   - a COPY LINK <button> carrying the permalink + title as data-attrs
 *   - a SHARE <button> that ships `hidden` and is revealed by JS only when
 *     navigator.share exists.
 *
 * Progressive enhancement: with JS disabled the COPY button no-ops and the
 * SHARE button stays hidden — nothing breaks, nothing misleads. The JS in
 * assets/js/note-share.js wires clipboard copy + the Web Share API.
 *
 * `core/shortcode` only runs wpautop() on its content — it does NOT call
 * do_shortcode (verified vs WP trunk wp-includes/blocks/shortcode.php).
 * The render_block bridge below (mirroring inc/setup.php:62-67 and
 * inc/related-notes.php) is what actually resolves [sn_note_share] inside
 * the block template.
 *
 * @package SignalNoise
 * @since 9.10.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * [sn_note_share] — copy-permalink + Web Share row for the queried Note.
 *
 * Single-post only (is_singular('post')); returns '' everywhere else so the
 * shortcode is inert on pages, archives, and the home/list views even if the
 * token ever leaks into a shared template part.
 *
 * Text-only labels (no icons), brutalist styling lives in components.css.
 *
 * @return string Row HTML, or '' off single notes.
 */
function sn_note_share_shortcode() {
	if ( ! is_singular( 'post' ) ) {
		return '';
	}

	$post_id   = (int) get_the_queried_object_id();
	$permalink = get_permalink( $post_id );
	$title     = get_the_title( $post_id );

	if ( ! $permalink ) {
		return '';
	}

	return sprintf(
		'<div class="sn-note-share">'
			. '<span class="sn-note-share__label">Share</span>'
			. '<button type="button" class="sn-note-share__copy" data-sn-share-url="%1$s" data-sn-share-title="%2$s">%3$s</button>'
			. '<button type="button" class="sn-note-share__native" data-sn-share-url="%1$s" data-sn-share-title="%2$s" hidden>%4$s</button>'
			. '</div>',
		esc_url( $permalink ),
		esc_attr( $title ),
		esc_html__( 'COPY LINK', 'signal-noise' ),
		esc_html__( 'SHARE', 'signal-noise' )
	);
}

/**
 * Resolve [sn_note_share] inside block template parts.
 *
 * core/shortcode only wpautop()s its content — it never runs do_shortcode
 * on block-template output. Mirrors the [current_year] bridge in
 * inc/setup.php:62-67 and the Related Notes bridge.
 *
 * @param string $block_content Rendered block HTML.
 * @param array  $block         Parsed block (unused).
 * @return string
 */
function sn_note_share_render_block_bridge( $block_content, $block ) {
	if ( false !== strpos( $block_content, '[sn_note_share' ) ) {
		// core/shortcode wpautop()'d the bare token first (verified vs WP
		// trunk), so this block-level <div> output would end up wrapped in an
		// invalid <p>. shortcode_unautop() strips the <p> around the
		// registered token before we resolve it.
		$block_content = do_shortcode( shortcode_unautop( $block_content ) );
	}
	return $block_content;
}

// Skip WP registration under the standalone test harness (add_shortcode /
// add_filter aren't stubbed there; the helpers are exercised directly).
if ( ! defined( 'SN_POST_SHARE_TEST' ) || ! SN_POST_SHARE_TEST ) {
	add_shortcode( 'sn_note_share', 'sn_note_share_shortcode' );
	add_filter( 'render_block', 'sn_note_share_render_block_bridge', 10, 2 );
}
