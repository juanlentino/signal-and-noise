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
 * Placed by the signal-noise/note-share PHP-only block since 13.1.0; the
 * shortcode stays registered for post content. The global render_block
 * bridge went in #389 (no template carries the token).
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

	$post_id   = (int) get_queried_object_id();
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
		esc_html__( 'COPY LINK', 'signal-and-noise' ),
		esc_html__( 'SHARE', 'signal-and-noise' )
	);
}

// Skip WP registration under the standalone test harness (add_shortcode
// isn't stubbed there; the helpers are exercised directly).
if ( ! defined( 'SN_POST_SHARE_TEST' ) || ! SN_POST_SHARE_TEST ) {
	add_shortcode( 'sn_note_share', 'sn_note_share_shortcode' );
}
