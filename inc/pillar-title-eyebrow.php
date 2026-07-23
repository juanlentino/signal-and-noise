<?php
/**
 * Signal & Noise — pillar designation eyebrow on the essay Page itself.
 *
 * v10.47.0 gave pillar essays an editorial designation ("№ 1.01") that renders
 * on the signal-noise/pillar-essays block cards — but the essay Page itself
 * showed a bare title, so the designation vanished the moment the reader
 * clicked through. A render_block filter on core/post-title prepends an
 * escaped eyebrow line (the designation mark, linking back to /provenance/)
 * ONLY on the reader-facing main-query singular Page that is flagged
 * `_sn_pillar` = '1' AND carries a non-empty `_sn_pillar_designation` (both
 * plugin-owned literal meta keys — the sn_theme_pillar_descriptors()
 * precedent). Everywhere else — other blocks, non-main queries, feeds, REST
 * (which covers the editor's ServerSideRender path), wp-admin, unflagged
 * Pages — it degrades to literally the unchanged input.
 *
 * Styling reuses .sn-catalog-eyebrow (assets/css/components.css — part of the
 * combined stylesheet, so it is loadable on every singular page) plus the
 * minimal .sn-pillar-designation link rules added beside it.
 *
 * @package SignalNoise
 * @since 10.48.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolve the queried Page ID this core/post-title render may decorate, or 0.
 *
 * 0 unless ALL of: the block is core/post-title; the request is a reader-facing
 * front-end render (not admin, not a feed, not REST); the main query is a
 * singular Page; and the block is rendering THAT page (block context postId,
 * falling back to the loop's get_the_ID(), equals get_queried_object_id() — a
 * secondary query loop rendering some other page never qualifies).
 *
 * @since 10.48.0
 * @param array         $block    Parsed block (render_block's 2nd arg).
 * @param WP_Block|null $instance Block instance (render_block's 3rd arg).
 * @return int Queried Page ID, or 0 when the eyebrow must not render.
 */
function sn_pillar_eyebrow_page_id( $block, $instance = null ) {
	// v10.48.1: pillar essays render templates/page-provenance.html, which has
	// NO core/post-title block (the hero heading lives in content), so the
	// eyebrow also attaches via core/post-content. The once-flag in the filter
	// keeps templates that render BOTH blocks (page.html) to a single eyebrow.
	$name = is_array( $block ) ? ( $block['blockName'] ?? '' ) : '';
	if ( 'core/post-title' !== $name && 'core/post-content' !== $name ) {
		return 0;
	}
	if ( function_exists( 'is_admin' ) && is_admin() ) {
		return 0;
	}
	if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
		return 0;
	}
	if ( function_exists( 'is_feed' ) && is_feed() ) {
		return 0;
	}
	if ( ! function_exists( 'is_singular' ) || ! is_singular( 'page' ) ) {
		return 0;
	}
	if ( ! function_exists( 'get_queried_object_id' ) ) {
		return 0;
	}
	$queried = (int) get_queried_object_id();
	if ( $queried < 1 ) {
		return 0;
	}
	$context_id = 0;
	if ( is_object( $instance ) && isset( $instance->context['postId'] ) ) {
		$context_id = (int) $instance->context['postId'];
	} elseif ( function_exists( 'get_the_ID' ) ) {
		$context_id = (int) get_the_ID();
	}
	return ( $context_id === $queried ) ? $queried : 0;
}

/**
 * The Page's pillar designation, or '' when the eyebrow must not render.
 *
 * The `_sn_pillar` = '1' flag gates: a leftover designation on an unflagged
 * Page is not a pillar claim. Trimmed; empty stays empty (no fabricated mark).
 *
 * @since 10.48.0
 * @param int $page_id Queried Page ID.
 * @return string
 */
function sn_pillar_eyebrow_designation( $page_id ) {
	if ( ! function_exists( 'get_post_meta' ) ) {
		return '';
	}
	if ( '1' !== (string) get_post_meta( $page_id, '_sn_pillar', true ) ) {
		return '';
	}
	return trim( (string) get_post_meta( $page_id, '_sn_pillar_designation', true ) );
}

/**
 * render_block_core/post-title filter: prepend the designation eyebrow.
 *
 * @since 10.48.0
 * @param string        $block_content Rendered title markup.
 * @param array         $block         Parsed block.
 * @param WP_Block|null $instance      Block instance.
 * @return string
 */
/**
 * Reset the once-per-request emit flag (tests; never needed in production).
 *
 * @since 10.48.1
 * @return void
 */
function sn_pillar_eyebrow_reset() {
	$GLOBALS['sn_pillar_eyebrow_emitted'] = false;
}

function sn_pillar_eyebrow_filter( $block_content, $block, $instance = null ) {
	// Once per request: page.html renders post-title THEN post-content; the
	// first ACTUAL emit wins and every later candidate passes through. A
	// rejected candidate (unflagged page, secondary loop) never burns the flag.
	if ( ! empty( $GLOBALS['sn_pillar_eyebrow_emitted'] ) ) {
		return $block_content;
	}
	$page_id = sn_pillar_eyebrow_page_id( $block, $instance );
	if ( ! $page_id ) {
		return $block_content;
	}
	$designation = sn_pillar_eyebrow_designation( $page_id );
	if ( '' === $designation ) {
		return $block_content;
	}
	// Same designation vocabulary as the pillar block cards (&#8470; = №).
	$eyebrow = sprintf(
		'<p class="sn-catalog-eyebrow sn-pillar-designation"><a href="%s">&#8470; %s &middot; Pillar Essay</a></p>',
		esc_url( home_url( '/provenance/' ) ),
		esc_html( $designation )
	);
	$GLOBALS['sn_pillar_eyebrow_emitted'] = true;
	return $eyebrow . (string) $block_content;
}

if ( ! defined( 'SN_PILLAR_EYEBROW_TEST' ) || ! SN_PILLAR_EYEBROW_TEST ) {
	// Block-specific render_block variants — never fire for other blocks;
	// the blockName check in the resolver stays as belt-and-suspenders.
	// post-content covers title-less templates (page-provenance.html); the
	// once-flag keeps dual-block templates (page.html) to a single eyebrow.
	add_filter( 'render_block_core/post-title', 'sn_pillar_eyebrow_filter', 10, 3 );
	add_filter( 'render_block_core/post-content', 'sn_pillar_eyebrow_filter', 10, 3 );
}
