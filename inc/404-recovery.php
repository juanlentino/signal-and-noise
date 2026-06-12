<?php
/**
 * Signal & Noise — Helpful 404 recovery.
 *
 * Registers [sn_404_suggestions] — a recent-notes list rendered inside
 * templates/404.html so a visitor who hit a stale link can recover. Uses its
 * OWN recent-notes WP_Query: the related-notes helper (sn_related_notes_query)
 * deliberately returns [] for post_id < 1, which is the 404 case.
 *
 * The render_block bridge mirrors inc/related-notes.php — shortcodes in .html
 * FSE templates resolve via core's template-level do_shortcode, but the bridge
 * is belt-and-suspenders and strips the invalid <p> that wpautop() would wrap
 * around this block-level <nav> token.
 *
 * Reading time is plugin-owned (sn_get_reading_time via
 * sn_related_notes_reading_time): function_exists-guarded so the row degrades
 * gracefully when the plugin / related-notes module is absent.
 */

if ( ! defined( 'ABSPATH' ) && ! ( defined( 'SN_404_RECOVERY_TEST' ) && SN_404_RECOVERY_TEST ) ) {
	exit;
}

/**
 * The most-recent published notes, for the 404 suggestions list.
 *
 * @param int $limit Max results.
 * @return WP_Post[]
 */
function sn_404_recent_notes( $limit ) {
	$limit = max( 1, (int) $limit );
	$q     = new WP_Query(
		array(
			'post_type'              => 'post',
			'post_status'            => 'publish',
			'posts_per_page'         => $limit,
			'orderby'                => 'date',
			'order'                  => 'DESC',
			'no_found_rows'          => true,
			'ignore_sticky_posts'    => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		)
	);
	return $q->posts;
}

/**
 * [sn_404_suggestions] — a "Recent notes" list reusing the .sn-notes-row idiom.
 *
 * @return string Nav HTML, or '' when there are no notes.
 */
function sn_404_suggestions_shortcode() {
	$notes = sn_404_recent_notes( (int) apply_filters( 'sn_404_suggestions_count', 5 ) );
	if ( empty( $notes ) ) {
		return '';
	}

	$rows = '';
	foreach ( $notes as $p ) {
		$rt        = function_exists( 'sn_related_notes_reading_time' )
			? sn_related_notes_reading_time( $p->ID )
			: '';
		$rt_markup = '' !== $rt
			? '<span class="sn-notes-row-rt">' . esc_html( $rt ) . '</span>'
			: '';
		$rows     .= sprintf(
			'<li class="sn-notes-row"><div class="sn-notes-row-spec"><time class="sn-notes-row-date" datetime="%1$s">%2$s</time>%3$s</div><div class="sn-notes-row-content"><h3 class="sn-notes-row-title"><a href="%4$s">%5$s</a></h3></div></li>',
			esc_attr( get_the_date( 'c', $p ) ),
			esc_html( get_the_date( 'Y.m.d', $p ) ),
			$rt_markup,
			esc_url( get_permalink( $p ) ),
			esc_html( get_the_title( $p ) )
		);
	}

	return '<nav class="sn-404-suggestions" aria-label="Recent notes">'
		. '<p class="sn-404-suggestions__label">Recent notes</p>'
		. '<ul class="sn-404-suggestions__list">' . $rows . '</ul>'
		. '</nav>';
}

/**
 * Resolve [sn_404_suggestions] inside the block template.
 * Mirrors sn_related_notes_render_block_bridge (inc/related-notes.php).
 *
 * @param string $block_content Rendered block HTML.
 * @param array  $block         Parsed block (unused).
 * @return string
 */
function sn_404_suggestions_render_block_bridge( $block_content, $block ) {
	if ( false !== strpos( $block_content, '[sn_404_suggestions' ) ) {
		$block_content = do_shortcode( shortcode_unautop( $block_content ) );
	}
	return $block_content;
}

// Skip WP registration under the standalone test harness.
if ( ! defined( 'SN_404_RECOVERY_TEST' ) || ! SN_404_RECOVERY_TEST ) {
	add_shortcode( 'sn_404_suggestions', 'sn_404_suggestions_shortcode' );
	add_filter( 'render_block', 'sn_404_suggestions_render_block_bridge', 10, 2 );
}
