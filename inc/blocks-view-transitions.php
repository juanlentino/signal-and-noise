<?php
/**
 * Signal & Noise — View Transitions for /notes (v9.2.0).
 *
 * Injects a per-post `view-transition-name` into every `core/post-title`
 * block render via the `render_block_core/post-title` filter, so the browser
 * sees matching transition-names on both source and destination of a
 * catalog→single-note navigation and morphs the title between them.
 *
 * v11.12.0 — THE /notes INDEX NO LONGER RENDERS core/post-title. The v11.10.0
 * notes-index redesign replaced the core query loop with hand-rolled markup in
 * inc/notes-index-row.php, which echoes its own `<h2 class="sn-notes-row-title">`.
 * This filter kept naming the single note's hero, so the destination half of the
 * morph still worked while the source half silently had no name at all — and a
 * morph with only one named side is just the root cross-fade. The row now calls
 * sn_view_transition_name() directly; that function is the ONE definition of the
 * name format, so the two surfaces cannot drift apart again.
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
 * assets/css/article.css (combined stylesheet; moved from critical.css in
 * v10.49.0) disables ALL navigation transitions when the
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
 * The transition name for a post: `sn-note-<sanitized-slug>`.
 *
 * view-transition-name takes a CSS <custom-ident>, so the slug is lowercased and
 * every run of non-alphanumerics collapses to a single hyphen. Shared by the
 * core/post-title filter below and by inc/notes-index-row.php — both surfaces of
 * a morph must emit the identical string, so there is exactly one definition.
 *
 * @param int $post_id
 * @return string '' when the post has no usable slug.
 */
function sn_view_transition_name( $post_id ) {
	$post_id = (int) $post_id;
	if ( $post_id <= 0 ) {
		return '';
	}
	$slug = get_post_field( 'post_name', $post_id );
	if ( ! $slug ) {
		return '';
	}
	$sanitized = preg_replace( '/[^a-z0-9-]+/', '-', strtolower( (string) $slug ) );
	$sanitized = trim( (string) $sanitized, '-' );
	return $sanitized ? 'sn-note-' . $sanitized : '';
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

	$name = sn_view_transition_name( $post_id );
	if ( '' === $name ) {
		return $block_content;
	}

	$style = 'view-transition-name: ' . $name . ';';

	// The first outer h1-h6 or anchor tag in the rendered block
	// (core/post-title renders as a heading; with isLink set, the
	// heading wraps an anchor; either way the outer element is what
	// we want to attach the transition-name to). #383: found and written
	// through WP_HTML_Tag_Processor, core's own HTML API, where a regex with
	// an offset capture and a substr_replace used to do it. A new style
	// attribute lands right after the tag name, exactly where the regex put
	// it; an existing one is extended the same way ('; ' between).
	$tags = new WP_HTML_Tag_Processor( (string) $block_content );
	while ( $tags->next_tag() ) {
		if ( ! in_array( $tags->get_tag(), array( 'H1', 'H2', 'H3', 'H4', 'H5', 'H6', 'A' ), true ) ) {
			continue;
		}
		$existing = $tags->get_attribute( 'style' );
		$tags->set_attribute( 'style', is_string( $existing ) ? $existing . '; ' . $style : $style );
		return $tags->get_updated_html();
	}
	return $block_content;
}
add_filter( 'render_block_core/post-title', 'sn_view_transition_post_title', 10, 3 );
