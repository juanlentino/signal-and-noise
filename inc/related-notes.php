<?php
/**
 * Signal & Noise — Related Notes footer.
 *
 * Registers [sn_related_notes] — rendered in the single.html footer below
 * the prev/next nav. Surfaces up to N other Notes that share a post_tag
 * with the current Note (recency-ranked), backfilling with the most
 * recent published Notes when shared-tag matches fall short.
 *
 * `core/shortcode` only runs wpautop() on its content — it does NOT call
 * do_shortcode (verified vs WP trunk wp-includes/blocks/shortcode.php).
 * The render_block bridge below (mirroring inc/setup.php:62-67) is what
 * actually resolves [sn_related_notes] inside the block template.
 *
 * Reading time is plugin-owned (sn_get_reading_time): guarded with
 * function_exists() so the row degrades gracefully when the plugin is
 * absent.
 *
 * @package SignalNoise
 * @since 9.10.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Build the related-notes list for a given Note.
 *
 * KERNEL pass (v11.2.0): when the companion plugin's deterministic ML kernel
 * is present (snt_ml_related_for_post), its blended-relatedness ranking leads.
 * Contract (plugin inc/ml-artifacts.php): rows of {post_id:int, score:float}
 * already ranked; null when the artifacts were never built; [] when the post
 * is unindexed (an empty ANSWER — see the null-vs-zero house rule). Rows are
 * re-verified here anyway (publish-only, never self) so the theme stays safe
 * against any upstream drift.
 *
 * PRIMARY pass: other published posts sharing at least one post_tag with
 * $post_id, recency DESC. BACKFILL pass: if fewer than $limit, top up with
 * the most recent published posts excluding self + the already-selected.
 * When the plugin accessor is absent this is byte-identical to pre-11.2.0.
 *
 * @param int $post_id Current note ID.
 * @param int $limit   Max results (default 3).
 * @return WP_Post[]   Ordered list, possibly empty.
 */
function sn_related_notes_query( $post_id, $limit = 3 ) {
	$post_id = (int) $post_id;
	$limit   = max( 1, (int) $limit );
	if ( $post_id < 1 ) {
		return array();
	}

	// KERNEL — deterministic ML ranking from the companion plugin, when built.
	$selected = array();
	if ( function_exists( 'snt_ml_related_for_post' ) ) {
		$rows = snt_ml_related_for_post( $post_id, $limit );
		if ( is_array( $rows ) ) { // null (unbuilt) and WP_Error alike fall through.
			$seen = array();
			foreach ( $rows as $row ) {
				if ( ! is_array( $row ) || ! isset( $row['post_id'] ) ) {
					continue; // Malformed row: skip, never fabricate.
				}
				$rid = (int) $row['post_id'];
				if ( $rid < 1 || $rid === $post_id || isset( $seen[ $rid ] ) ) {
					continue;
				}
				$p = get_post( $rid );
				// Publish AND type re-checked here, not trusted from the
				// artifact: post IDs are global across types, so a stale or
				// widened artifact must never surface a non-Note in this footer.
				if ( ! $p || 'publish' !== get_post_status( $p ) || 'post' !== get_post_type( $p ) ) {
					continue;
				}
				$seen[ $rid ] = true;
				$selected[]   = $p;
				if ( count( $selected ) >= $limit ) {
					break;
				}
			}
		}
	}
	if ( count( $selected ) >= $limit ) {
		return array_slice( $selected, 0, $limit );
	}

	// Collect the note's post_tag term_ids.
	$tag_ids = array();
	$terms   = get_the_terms( $post_id, 'post_tag' );
	if ( is_array( $terms ) && ! is_wp_error( $terms ) ) {
		foreach ( $terms as $term ) {
			$tag_ids[] = (int) $term->term_id;
		}
	}

	// Exclusion list for the heuristic passes: self + any kernel picks. With
	// the kernel absent/empty this is exactly array( $post_id ) — byte-identical
	// to the pre-11.2.0 PRIMARY query.
	$primary_exclude = array( $post_id );
	foreach ( $selected as $p ) {
		$primary_exclude[] = (int) $p->ID;
	}

	// PRIMARY — shared-tag matches, recency DESC.
	if ( ! empty( $tag_ids ) ) {
		$primary = new WP_Query(
			array(
				'post_type'             => 'post',
				'post_status'           => 'publish',
				'post__not_in'          => array_values( array_unique( $primary_exclude ) ),
				'posts_per_page'        => $limit - count( $selected ),
				'orderby'               => 'date',
				'order'                 => 'DESC',
				'tax_query'             => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- bounded to $limit, no_found_rows, related-notes footer only.
					array(
						'taxonomy' => 'post_tag',
						'field'    => 'term_id',
						'terms'    => $tag_ids,
					),
				),
				'no_found_rows'         => true,
				'ignore_sticky_posts'   => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);
		$selected = array_merge( $selected, $primary->posts );
	}

	// BACKFILL — top up to $limit with most-recent Notes, excluding self
	// and anything already selected.
	if ( count( $selected ) < $limit ) {
		$exclude = array( $post_id );
		foreach ( $selected as $p ) {
			$exclude[] = (int) $p->ID;
		}
		$need     = $limit - count( $selected );
		$backfill = new WP_Query(
			array(
				'post_type'             => 'post',
				'post_status'           => 'publish',
				'post__not_in'          => array_values( array_unique( $exclude ) ),
				'posts_per_page'        => $need,
				'orderby'               => 'date',
				'order'                 => 'DESC',
				'no_found_rows'         => true,
				'ignore_sticky_posts'   => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);
		$selected = array_merge( $selected, $backfill->posts );
	}

	return array_slice( $selected, 0, $limit );
}

/**
 * Render the reading-time spec string for a related row, plugin-guarded.
 *
 * @param int $post_id Related note ID.
 * @return string Zero-padded "NN MIN", or '' when the plugin is absent.
 */
function sn_related_notes_reading_time( $post_id ) {
	if ( ! function_exists( 'sn_get_reading_time' ) ) {
		return '';
	}
	$mins = (int) sn_get_reading_time( $post_id );
	if ( $mins < 1 ) {
		return '';
	}
	return sprintf( '%02d MIN', $mins );
}

/**
 * [sn_related_notes] — render the footer for the queried Note.
 *
 * Title-only rows (no excerpt, no matched-tag label) reusing the
 * .sn-notes-row two-column idiom from the /notes index.
 *
 * @return string Footer HTML, or '' when there are no related notes.
 */
function sn_related_notes_shortcode() {
	$post_id = (int) get_queried_object_id();
	if ( $post_id < 1 ) {
		return '';
	}

	/**
	 * Related-notes count. Default 3 (was hardcoded to 3 at this call site);
	 * now configurable — the companion plugin supplies the chosen value via
	 * sn_setting('theme.related_count').
	 */
	$related = sn_related_notes_query( $post_id, (int) apply_filters( 'sn_related_count', 3 ) );
	if ( empty( $related ) ) {
		return '';
	}

	$rows = '';
	foreach ( $related as $p ) {
		$rt        = sn_related_notes_reading_time( $p->ID );
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

	return '<footer class="sn-related-notes" aria-label="More on this">'
		. '<h2 class="sn-related-notes__label">More on this</h2>'
		. '<ul class="sn-related-notes__list">' . $rows . '</ul>'
		. '</footer>';
}

/**
 * Resolve [sn_related_notes] inside block template parts.
 *
 * core/shortcode only wpautop()s its content — it never runs do_shortcode
 * on block-template output. Mirrors the [current_year] bridge in
 * inc/setup.php:62-67.
 *
 * @param string $block_content Rendered block HTML.
 * @param array  $block         Parsed block (unused).
 * @return string
 */
function sn_related_notes_render_block_bridge( $block_content, $block ) {
	if ( false !== strpos( $block_content, '[sn_related_notes' ) ) {
		// core/shortcode wpautop()'d the bare token first (verified vs WP
		// trunk), so a block-level shortcode output (this <footer>) would end
		// up wrapped in an invalid <p>. shortcode_unautop() strips the <p>
		// around the registered token before we resolve it.
		$block_content = do_shortcode( shortcode_unautop( $block_content ) );
	}
	return $block_content;
}

// Skip WP registration under the standalone test harness (add_shortcode /
// add_filter aren't stubbed there; the helpers are exercised directly).
if ( ! defined( 'SN_RELATED_NOTES_TEST' ) || ! SN_RELATED_NOTES_TEST ) {
	add_shortcode( 'sn_related_notes', 'sn_related_notes_shortcode' );
	add_filter( 'render_block', 'sn_related_notes_render_block_bridge', 10, 2 );
}
