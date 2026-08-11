<?php
/**
 * Signal & Noise — the provenance badge in a signed Page's title brow.
 *
 * WHY THIS IS THEME WORK AND NOT PLUGIN WORK. The plugin owns the badge's
 * markup (sn_prov_render_chip) and, since plugin v10.87.0, appended it to a
 * signed Page's CONTENT because pages had no placement convention to defer to.
 * The owner's direction is that it belongs in the brow — the eyebrow line above
 * the title, the same register as "SERVICE · THE FIELD". The plugin cannot put
 * it there: it hooks `the_content`, and the brow sits above the title in a
 * separate core/post-title block rendered outside content entirely. Placement
 * has always been the theme's, so the theme takes it back — through the exit the
 * plugin shipped for exactly this, `sn_prov_auto_append_page_panel`.
 *
 * The mechanism is inc/pillar-title-eyebrow.php's, deliberately: same
 * render_block hook, same .sn-catalog-eyebrow register, and the SAME resolver
 * (sn_pillar_eyebrow_page_id) rather than a second copy of its gating. That
 * resolver already refuses admin, feeds, REST, secondary loops and non-main
 * queries — guards worth reusing, never re-deriving.
 *
 * @package SignalNoise
 * @since 11.7.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reset the once-per-request flag. Test seam only; nothing in the theme calls it.
 *
 * @since 11.7.0
 */
function sn_prov_title_badge_reset() {
	unset( $GLOBALS['sn_prov_title_badge_emitted'] );
}

/**
 * Prepend the provenance badge to a signed Page's title block.
 *
 * Its own once-flag, separate from the pillar eyebrow's: a Page can be both a
 * pillar essay and a signed subject, and those are two different statements
 * about it. Sharing one flag would silently drop whichever ran second.
 *
 * @since 11.7.0
 * @param string        $block_content Rendered block HTML.
 * @param array         $block         Parsed block.
 * @param WP_Block|null $instance      Block instance.
 * @return string
 */
function sn_prov_title_badge_filter( $block_content, $block, $instance = null ) {
	if ( ! empty( $GLOBALS['sn_prov_title_badge_emitted'] ) ) {
		return $block_content;
	}
	if ( ! function_exists( 'sn_pillar_eyebrow_page_id' )
		|| ! function_exists( 'sn_prov_subject_kind' )
		|| ! function_exists( 'sn_prov_render_chip' )
		|| ! function_exists( 'get_post' ) ) {
		return $block_content; // Companion plugin absent or partial: degrade to the unchanged title.
	}

	$page_id = sn_pillar_eyebrow_page_id( $block, $instance );
	if ( ! $page_id ) {
		return $block_content;
	}
	// Only a signed PAGE. A note's chip lives in its byline, placed by the
	// single-note template, and is not this filter's business.
	if ( 'page' !== sn_prov_subject_kind( get_post( $page_id ) ) ) {
		return $block_content;
	}

	$chip = sn_prov_render_chip( $page_id );
	if ( '' === $chip ) {
		// No chain yet — an opted-in page whose first commit has not landed.
		// Emit nothing and do NOT burn the flag: a later block may succeed.
		return $block_content;
	}

	$GLOBALS['sn_prov_title_badge_emitted'] = true;
	return '<p class="sn-catalog-eyebrow sn-prov-title-badge">' . $chip . '</p>' . (string) $block_content;
}

if ( ! defined( 'SN_PROV_TITLE_BADGE_TEST' ) || ! SN_PROV_TITLE_BADGE_TEST ) {
	// Same two block hooks as the pillar eyebrow: post-content covers
	// title-less templates, and the once-flag keeps dual-block templates to one.
	add_filter( 'render_block_core/post-title', 'sn_prov_title_badge_filter', 10, 3 );
	add_filter( 'render_block_core/post-content', 'sn_prov_title_badge_filter', 10, 3 );

	// Take placement back from the plugin's content append — the documented
	// exit, used as intended. Without this the badge would render twice on any
	// template where both fire; the plugin's own one-chip-per-subject guard
	// (v10.89.0) would in fact swallow the second, but relying on that would be
	// depending on render ORDER for correctness, which is precisely how the
	// panel came to render twice on /about/ earlier.
	add_filter( 'sn_prov_auto_append_page_panel', '__return_false' );
}
