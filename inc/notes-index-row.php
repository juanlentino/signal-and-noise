<?php
/**
 * Signal & Noise theme — /notes index ROW + YEAR-SPINE rendering (v11.10.0).
 *
 * Extracted from inc/page-notes-render.php, which was carrying the markup for
 * two near-identical rows (the pinned Start-here row and the chronological
 * row) that had already drifted apart once.
 *
 * The index reads as a MANIFEST, not a feed:
 *   - one dense ledger line per Note (date | title | stamp);
 *   - the excerpt stays in the DOM for crawlers, AEO and screen readers, and
 *     is collapsed visually so the titles — aphorisms that read as a
 *     manifesto in sequence — can actually be read in sequence;
 *   - row differentiators are EDITORIAL ONLY: reading time, tags, provenance
 *     version. Never traffic or decay. Publishing per-note performance would
 *     cut against the ML kernel's refusals and turn a reading list into a
 *     leaderboard.
 *
 * @package SignalNoise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One index row.
 *
 * @param WP_Post $p    The post.
 * @param array   $args {
 *     @type bool $pinned    Render the blood "Start here" flag.
 *     @type bool $show_type Render the corpus-type label (search mode).
 * }
 * @return void Echoes.
 */
function sn_notes_render_row( $p, $args = array() ) {
	if ( ! $p ) {
		return;
	}
	$pinned    = ! empty( $args['pinned'] );
	$show_type = ! empty( $args['show_type'] );

	$excerpt = get_the_excerpt( $p );
	$tags    = $pinned ? array() : sn_notes_row_tags( $p->ID, 2 );
	$version = sn_notes_prov_version( $p->ID );

	echo '<li class="sn-notes-row' . ( $pinned ? ' is-pinned' : '' ) . '">';

	echo '<div class="sn-notes-row-spec">';
	if ( $pinned ) {
		echo '<span class="sn-notes-row-pin">' . esc_html__( 'Start here', 'signal-noise' ) . '</span>';
	}
	echo '<time class="sn-notes-row-date" datetime="' . esc_attr( get_the_date( 'c', $p ) ) . '">'
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper returns esc_html()'d output; escaping again would double-encode.
		. sn_notes_render_date( $p )
		. '</time>';
	echo '</div>';

	echo '<div class="sn-notes-row-content">';
	echo '<h3 class="sn-notes-row-title"><a href="' . esc_url( get_permalink( $p ) ) . '">'
		. esc_html( get_the_title( $p ) ) . '</a></h3>';
	echo '</div>';

	echo '<div class="sn-notes-row-meta">';
	echo '<span class="sn-notes-row-rt">' . esc_html( sn_notes_render_reading_time( $p->ID ) ) . '</span>';
	if ( $show_type ) {
		echo '<span class="sn-notes-row-sep" aria-hidden="true">&middot;</span>';
		echo '<span class="sn-notes-row-type">' . esc_html( sn_notes_result_type_label( $p ) ) . '</span>';
	}
	foreach ( $tags as $tag ) {
		echo '<span class="sn-notes-row-sep" aria-hidden="true">&middot;</span>';
		if ( '' !== $tag['url'] ) {
			echo '<a class="sn-notes-row-tags" href="' . esc_url( $tag['url'] ) . '">' . esc_html( $tag['name'] ) . '</a>';
		} else {
			echo '<span class="sn-notes-row-tags">' . esc_html( $tag['name'] ) . '</span>';
		}
	}
	// v2+ only. "Signed once" is true of nearly every Note, so rendering v1
	// thirty times costs a column and says nothing; from v2 it reports what
	// the date cannot — this argument was revisited, and the revision signed.
	if ( sn_notes_prov_version_is_notable( $version ) ) {
		echo '<span class="sn-notes-row-sep" aria-hidden="true">&middot;</span>';
		echo '<span class="sn-notes-row-prov" title="' . esc_attr__( 'Substantively revised and signed this many times', 'signal-noise' ) . '">'
			. esc_html( sprintf( 'v%d', (int) $version ) ) . '</span>';
	}
	echo '</div>';

	if ( $excerpt ) {
		echo '<div class="sn-notes-row-excerpt-wrap">';
		echo '<p class="sn-notes-row-excerpt">' . esc_html( wp_strip_all_tags( $excerpt ) ) . '</p>';
		echo '</div>';
	}

	echo '</li>';
}

/**
 * Rows for one year, subdivided by month, with surplus months collapsed.
 *
 * THE YEAR IS THE WRONG BOUNDING UNIT ON ITS OWN. At the observed cadence —
 * 22 Notes scheduled across ten weeks, ~114/year — a single open year reaches
 * ~4,900px by December, which is the exact wall this redesign removed. The year
 * spine can only collapse whole years, so it does nothing until January. The
 * month is the finer unit that bounds growth INSIDE a year.
 *
 * So the same rule runs at both granularities: expand newest-first until the
 * page carries substance, then collapse the surplus. $shown is shared by
 * reference across years and months, so the budget is spent once, globally —
 * the page holds roughly a screen and a half of rows no matter how large the
 * corpus grows.
 *
 * Month bands are deliberately QUIETER than year bands — no rule. The year band
 * divides; the month band only marks where you are.
 *
 * @param array $posts
 * @param array $args  Passed through to sn_notes_render_row().
 * @param int   $shown Running count of rows already expanded (by reference).
 * @return void Echoes.
 */
function sn_notes_render_months( $posts, $args = array(), &$shown = null ) {
	$local = 0;
	if ( null === $shown ) {
		$shown = &$local;
	}
	if ( ! sn_notes_month_dividers_are_useful( count( (array) $posts ) ) ) {
		sn_notes_render_row_list( $posts, $args );
		$shown += count( (array) $posts );
		return;
	}
	foreach ( sn_notes_group_by_month( $posts ) as $key => $month_posts ) {
		$count = count( $month_posts );
		$label = date_i18n( 'F', strtotime( $key . '-01 00:00:00' ) );
		$band  = '<p class="sn-notes-month-band"><span class="sn-notes-month-m">' . esc_html( $label )
			. '</span><span class="sn-notes-month-sep" aria-hidden="true">&middot;</span><span class="sn-notes-month-n">'
			. esc_html( number_format_i18n( $count ) ) . '</span></p>';

		if ( $shown < SN_NOTES_SPINE_MIN_VISIBLE ) {
			echo $band; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- assembled from esc_html() above.
			sn_notes_render_row_list( $month_posts, $args );
			$shown += $count;
			continue;
		}
		echo '<details class="sn-notes-month">';
		echo '<summary>' . $band . '</summary>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- assembled from esc_html() above.
		sn_notes_render_row_list( $month_posts, $args );
		echo '</details>';
	}
}

/**
 * A flat <ol> of rows.
 *
 * @param array $posts
 * @param array $args Passed through to sn_notes_render_row().
 * @return void Echoes.
 */
function sn_notes_render_row_list( $posts, $args = array() ) {
	echo '<ol class="sn-notes-index-list">';
	foreach ( (array) $posts as $p ) {
		sn_notes_render_row( $p, $args );
	}
	echo '</ol>';
}

/**
 * How many rows the spine must leave visible before it starts collapsing.
 *
 * The spine's job is to bound a long page, NOT to hide the corpus. Collapsing
 * strictly by "newest year open, everything else closed" fails on the only
 * distribution this site will ever actually have: the corpus began in
 * 2026-04, so the spine's FIRST real activation is January 2027 — a year
 * holding one or two Notes, sitting above a year holding thirty-plus. That
 * rule would have shown a reader two rows and folded the entire argument
 * behind a closed line, on the exact day it switched on.
 *
 * So years expand newest-first until this many rows are showing. The year that
 * crosses the line stays open; only genuine surplus collapses.
 */
const SN_NOTES_SPINE_MIN_VISIBLE = 24;

/**
 * Rows in a year before it gets month dividers.
 *
 * A divider that fires on four rows is texture, not structure. Below this, a
 * year reads fine as one run and chopping it works against the reason the rows
 * were made dense — the titles are meant to be read in sequence.
 */
const SN_NOTES_MONTH_DIVIDER_MIN = 12;

/**
 * The year spine: enough years open to carry the page, the rest collapsed.
 *
 * This is what bounds the visible page permanently. Pagination was the wrong
 * instrument — you reach for it when a list has no structure but recency, and
 * it hides the corpus behind numbered pages that say nothing about what is on
 * them. A year band says what it is holding.
 *
 * Falls back to a flat list when the corpus spans a single year, so the
 * structure appears the moment it discriminates rather than drawing a
 * decorative band that restates the count already in the section header.
 *
 * @param array $posts
 * @param array $args Passed through to sn_notes_render_row().
 * @return void Echoes.
 */
function sn_notes_render_year_spine( $posts, $args = array() ) {
	$grouped = sn_notes_group_by_year( $posts );
	$shown   = 0;
	if ( ! sn_notes_year_spine_is_useful( $grouped ) ) {
		// The state the site is actually in and will stay in through 2026: one
		// year, no year spine. The MONTH dividers carry the whole structure
		// here, which is why they must be able to collapse on their own.
		sn_notes_render_months( $posts, $args, $shown );
		return;
	}
	foreach ( $grouped as $year => $year_posts ) {
		$count = count( $year_posts );
		$band  = '<p class="sn-notes-year-band"><span class="sn-notes-year-y">' . esc_html( (string) $year )
			. '</span><span class="sn-notes-year-sep" aria-hidden="true">&middot;</span><span class="sn-notes-year-n">'
			. esc_html( number_format_i18n( $count ) ) . '</span></p>';

		// Open while the page is still thin. The check runs BEFORE this year's
		// rows are counted, so the year that crosses the threshold is itself
		// open — a reader always lands on a page with substance on it.
		if ( $shown < SN_NOTES_SPINE_MIN_VISIBLE ) {
			echo '<section class="sn-notes-year">';
			echo $band; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- assembled from esc_html() above.
			sn_notes_render_months( $year_posts, $args, $shown );
			echo '</section>';
			continue;
		}
		echo '<details class="sn-notes-year">';
		echo '<summary>' . $band . '</summary>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- assembled from esc_html() above.
		sn_notes_render_row_list( $year_posts, $args );
		echo '</details>';
	}
}
