<?php
/**
 * Signal & Noise — Helpful 404 recovery.
 *
 * A recent-notes list rendered inside templates/404.html so a visitor who hit
 * a stale link can recover. Uses its OWN recent-notes WP_Query: the
 * related-notes helper (sn_related_notes_query) deliberately returns [] for
 * post_id < 1, which is the 404 case.
 *
 * The template slot is the signal-noise/suggestions-404 PHP-only block
 * (blocks/suggestions-404, registered by inc/blocks-php-only.php; #386), whose
 * render file calls sn_404_suggestions_shortcode() through the same
 * wpautop() wrapper the core/shortcode block applied, so the page is
 * byte-identical (tests/404-recovery.php pins the bytes). The
 * [sn_404_suggestions] shortcode stays registered for anything typed into
 * post content, as the nine sibling shortcodes do (owner decision 2026-09-12,
 * inc/blocks-php-only.php); no template names it any more.
 *
 * The render_block bridge that used to sit here went with the swap: core
 * resolves shortcodes on the raw template markup before do_blocks() (WP 7.1
 * wp-includes/block-template.php, get_the_block_template_html()), so the
 * token never reached render_block unresolved, and nav is in wpautop()'s
 * block list, so it never got the <p> the bridge claimed to strip.
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
 * The "Recent notes" list, reusing the .sn-notes-row idiom. The renderer
 * behind both the signal-noise/suggestions-404 block (the template's slot)
 * and the [sn_404_suggestions] shortcode (post content only).
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
			'<li class="sn-notes-row"><div class="sn-notes-row-spec"><time class="sn-notes-row-date" datetime="%1$s">%2$s</time>%3$s</div><div class="sn-notes-row-content"><h2 class="sn-notes-row-title"><a href="%4$s">%5$s</a></h2></div></li>',
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

// Skip WP registration under the standalone test harness.
if ( ! defined( 'SN_404_RECOVERY_TEST' ) || ! SN_404_RECOVERY_TEST ) {
	add_shortcode( 'sn_404_suggestions', 'sn_404_suggestions_shortcode' );
}
