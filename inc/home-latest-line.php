<?php
/**
 * The home page's latest line (2026.10): one line naming the newest published
 * note, its catalog number, title and date, set on the hero's pulse. Real data
 * from the same query the notes index numbers by. It is appended after the
 * post-content element, so the home Page's own content is untouched, and
 * critical.css positions it over the hero, so it adds no height: home stays one
 * screen (owner, 2026-10-17).
 *
 * @package SignalNoise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The line for the newest published note, or '' when there is none.
 *
 * @return string
 */
function sn_home_latest_line_html() {
	$posts = get_posts(
		array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'orderby'        => array( 'date' => 'DESC', 'ID' => 'DESC' ),
			'no_found_rows'  => true,
		)
	);
	$note = $posts[0] ?? null;
	if ( ! $note ) {
		return '';
	}
	$no    = function_exists( 'sn_notes_catalog_number' ) ? (int) sn_notes_catalog_number( $note->ID ) : 0;
	$es    = function_exists( 'sn_theme_is_spanish' ) && sn_theme_is_spanish();
	$label = $es ? 'Lo último' : 'Latest';
	// On the Spanish home the note is still English: say so (hreflang, lang).
	return '<a class="sn-home-latest" href="' . esc_url( get_permalink( $note ) ) . '"' . ( $es ? ' hreflang="en"' : '' ) . '>'
		. '<span class="sn-home-latest__k">' . esc_html( $label ) . '</span>'
		. ( $no > 0 ? '<span>&#8470; ' . sprintf( '%03d', $no ) . '</span>' : '' )
		. '<span class="sn-home-latest__t"' . ( $es ? ' lang="en"' : '' ) . '>' . esc_html( get_the_title( $note ) ) . '</span>'
		. '<span>' . esc_html( get_post_time( 'Y.m.d', false, $note ) ) . ' <span aria-hidden="true">&rarr;</span></span></a>';
}

/**
 * Reset the once-per-request flag (tests).
 */
function sn_home_latest_line_reset() {
	unset( $GLOBALS['sn_home_latest_emitted'] );
}

/**
 * render_block_core/post-content filter: append the line after the home hero's
 * post content, once, for the queried page only.
 *
 * @param string        $block_content Rendered post content.
 * @param array         $block         Parsed block.
 * @param WP_Block|null $instance      Block instance.
 * @return string
 */
function sn_home_latest_line_filter( $block_content, $block, $instance = null ) {
	if ( ! empty( $GLOBALS['sn_home_latest_emitted'] ) || ! function_exists( 'sn_hero_pulse_is_home' ) || ! sn_hero_pulse_is_home() ) {
		return $block_content;
	}
	if ( ! is_object( $instance ) || (int) ( $instance->context['postId'] ?? 0 ) !== (int) get_queried_object_id() ) {
		return $block_content;
	}
	$GLOBALS['sn_home_latest_emitted'] = true;
	return (string) $block_content . sn_home_latest_line_html();
}

if ( ! defined( 'SN_HOME_LATEST_TEST' ) || ! SN_HOME_LATEST_TEST ) {
	add_filter( 'render_block_core/post-content', 'sn_home_latest_line_filter', 10, 3 );
}
