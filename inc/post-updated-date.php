<?php
/**
 * Signal & Noise — "Updated YYYY.MM.DD" frontmatter line.
 *
 * Surfaces a reader-visible "Updated" date on Notes that were materially
 * revised after publication. A note is "materially revised" when its
 * modified timestamp is at least $threshold_days (default 14) after its
 * publish timestamp — so tiny same-week typo fixes don't earn a badge,
 * but a real substantive update does.
 *
 * Registers [sn_updated_date], used inside parts/post-frontmatter.html
 * after the published date. Shortcodes do NOT auto-resolve in FSE
 * template parts (core/shortcode only wpautop()s — verified vs WP trunk
 * wp-includes/blocks/shortcode.php), so a token-specific render_block
 * bridge resolves it, mirroring the [current_year] bridge in
 * inc/setup.php:62-67 and the plugin's [sn_reading_time] bridge.
 *
 * @package SignalNoise
 * @since 9.10.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Build the "Updated YYYY.MM.DD" <time> element for a materially-revised post.
 *
 * Uses true Unix timestamps from get_post_timestamp() (NOT the offset-summed
 * legacy form) for the delta comparison, and wp_date() for site-timezone
 * display + the ISO-8601 datetime attribute.
 *
 * @param int|WP_Post|null $post           Post ID/object. Default current post.
 * @param int              $threshold_days Min days between publish and modify
 *                                         to surface the line. Default 14.
 * @return string Rendered <time> element, or '' if not materially revised.
 */
function sn_post_updated_display( $post = null, $threshold_days = 14 ) {
	$post = get_post( $post );
	if ( ! $post ) {
		return '';
	}

	/**
	 * Filter the day threshold for surfacing the "Updated" line.
	 *
	 * @param int              $threshold_days Default 14.
	 * @param int|WP_Post|null $post           The post being evaluated.
	 */
	$threshold_days = (int) apply_filters( 'sn_updated_date_threshold_days', $threshold_days, $post );

	$published = get_post_timestamp( $post, 'date' );
	$modified  = get_post_timestamp( $post, 'modified' );
	if ( false === $published || false === $modified ) {
		return '';
	}

	$delta = $modified - $published;
	if ( $delta < $threshold_days * DAY_IN_SECONDS ) {
		return '';
	}

	return sprintf(
		'<time class="sn-post-frontmatter__updated" datetime="%s">%s</time>',
		esc_attr( wp_date( 'c', $modified ) ),
		esc_html( 'Updated ' . wp_date( 'Y.m.d', $modified ) )
	);
}

/**
 * [sn_updated_date] — render the "Updated" line for the current post.
 *
 * @return string
 */
function sn_updated_date_shortcode() {
	return sn_post_updated_display( get_post() );
}

/**
 * Resolve [sn_updated_date] inside block template parts.
 *
 * core/shortcode only wpautop()s its content — it never runs do_shortcode
 * on block-template output. Token-specific strpos (not a prefix-match) so
 * lookalikes can't false-positive. Mirrors inc/setup.php:62-67.
 *
 * @param string $block_content Rendered block HTML.
 * @param array  $block         Parsed block (unused).
 * @return string
 */
function sn_updated_date_render_block_bridge( $block_content, $block ) {
	if ( false !== strpos( $block_content, '[sn_updated_date]' ) ) {
		$block_content = do_shortcode( $block_content );
	}
	return $block_content;
}

// Skip WP registration under the standalone test harness (add_shortcode /
// add_filter aren't stubbed there; the helpers are exercised directly).
if ( ! defined( 'SN_POST_UPDATED_DATE_TEST' ) || ! SN_POST_UPDATED_DATE_TEST ) {
	add_shortcode( 'sn_updated_date', 'sn_updated_date_shortcode' );
	add_filter( 'render_block', 'sn_updated_date_render_block_bridge', 10, 2 );
}
