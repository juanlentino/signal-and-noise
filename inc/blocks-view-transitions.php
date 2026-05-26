<?php
/**
 * Signal & Noise — View Transitions for /notes (v9.2.0).
 *
 * Injects a per-post `view-transition-name` into every `core/post-title`
 * block render via the `render_block_core/post-title` filter. The same
 * block renders the catalog card titles in /notes AND the single-note
 * article hero — so the browser sees matching transition-names on both
 * source and destination of any catalog→single-note navigation and
 * morphs the title from card position to hero position.
 *
 * Why a filter instead of template edits:
 *   - Block themes use core/query loops for catalog enumeration.
 *     Emitting per-post inline styles from template markup requires
 *     a custom dynamic block; the filter approach is ~50 LOC instead
 *     of registering a whole new block type.
 *   - One hook covers EVERY render of core/post-title anywhere on the
 *     site — catalog, hero, related-posts widgets, future surfaces.
 *
 * Reduced-motion respect: the existing @media (prefers-reduced-motion:
 * reduce) { @view-transition { navigation: none; } } block in
 * assets/css/critical.css disables ALL navigation transitions when the
 * user opts out. Our per-element view-transition-names inherit that
 * disable automatically.
 *
 * Browser support: Chrome/Edge 111+, Safari 18+. Firefox falls back to
 * standard instant cross-doc navigation (the v9.0.0 default behavior).
 *
 * @package SignalNoise
 * @since 9.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Inject `view-transition-name: sn-note-<slug>` into the outer tag of
 * rendered core/post-title blocks.
 *
 * @param string   $block_content Block HTML rendered by core.
 * @param array    $block         Parsed block array.
 * @param WP_Block $instance      Block instance (provides post context).
 * @return string Modified HTML with the inline style injected.
 */
function sn_view_transition_post_title( $block_content, $block, $instance ) {
	$post_id = null;
	if ( isset( $instance->context['postId'] ) ) {
		$post_id = (int) $instance->context['postId'];
	} elseif ( function_exists( 'get_the_ID' ) ) {
		$post_id = get_the_ID();
	}
	if ( ! $post_id ) {
		return $block_content;
	}

	$slug = get_post_field( 'post_name', $post_id );
	if ( ! $slug ) {
		return $block_content;
	}

	// Sanitize slug to a CSS-safe identifier. view-transition-name
	// requires CSS-identifier syntax (alphanumeric + hyphen). Lowercase
	// + collapse anything non-alphanumeric to a single hyphen.
	$sanitized = preg_replace( '/[^a-z0-9-]+/', '-', strtolower( $slug ) );
	$sanitized = trim( $sanitized, '-' );
	if ( ! $sanitized ) {
		return $block_content;
	}

	$style = 'view-transition-name: sn-note-' . $sanitized . ';';

	// Find the first outer h1-h6 or anchor tag in the rendered block
	// (core/post-title renders as a heading; with isLink set, the
	// heading wraps an anchor — either way the outer element is what
	// we want to attach the transition-name to).
	if ( ! preg_match( '/<(h[1-6]|a)\b[^>]*>/i', $block_content, $m, PREG_OFFSET_CAPTURE ) ) {
		return $block_content;
	}

	$tag        = $m[0][0];
	$tag_offset = $m[0][1];

	if ( false !== strpos( $tag, 'style=' ) ) {
		// Append to existing style attribute (preserve other styles).
		$new_tag = preg_replace(
			'/style\s*=\s*"([^"]*)"/i',
			'style="$1; ' . $style . '"',
			$tag,
			1
		);
	} else {
		// Insert a new style attribute right after the tag name.
		$new_tag = preg_replace(
			'/^<(h[1-6]|a)/i',
			'<$1 style="' . $style . '"',
			$tag,
			1
		);
	}

	return substr_replace( $block_content, $new_tag, $tag_offset, strlen( $tag ) );
}
add_filter( 'render_block_core/post-title', 'sn_view_transition_post_title', 10, 3 );
