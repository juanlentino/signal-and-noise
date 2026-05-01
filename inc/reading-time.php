<?php
/**
 * Signal & Noise — Reading time calculation, caching, and legacy cleanup.
 *
 * Owns three concerns:
 *
 *   1. Calculation. `sn_calculate_reading_time()` strips Gutenberg block
 *      comments, shortcodes, and HTML, then divides word count by a
 *      filterable WPM (default 225). One-minute floor so a haiku doesn't
 *      render "0 min read".
 *
 *   2. Caching. The result is stored in the `_sn_reading_time_minutes`
 *      post meta (private, hidden from the Custom Fields UI). The
 *      [sn_reading_time] shortcode reads from this cache, populating it
 *      lazily on first render. Cache is rebuilt on `wp_after_insert_post`
 *      so edits update immediately.
 *
 *   3. Legacy cleanup. Older posts had reading-time text typed inline by
 *      hand ("8-minute read", "10 min read"). The admin page (Appearance
 *      → Signal & Noise → Dashboard) ships a Preview/Apply pair that
 *      scans post_content, post_excerpt, and public custom fields for
 *      these patterns and offers to remove them.
 *
 * The shortcode itself was originally registered in
 * inc/notes-and-provenance.php at 200 WPM with no cache; that registration
 * has been removed in favour of the version below.
 *
 * @package SignalNoise
 * @since 6.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const SN_READING_TIME_META_KEY = '_sn_reading_time_minutes';
const SN_READING_TIME_DEFAULT_WPM = 225;

/**
 * Regex matching legacy hand-typed reading-time strings.
 *
 * Tolerates a leading approximation marker (~), digits, either a space
 * or hyphen separator, "min"/"mins"/"minute"/"minutes", and the trailing
 * word "read". Case-insensitive. Designed to NOT match the literal
 * shortcode token `[sn_reading_time]`.
 */
const SN_READING_TIME_LEGACY_REGEX = '/~?\s*\d+\s*[-\s]\s*(?:minutes?|mins?)\s+read\b/i';

/**
 * Compute reading time in whole minutes for a post body.
 *
 * Strips block comments (<!-- wp:* -->), shortcodes, and HTML before
 * counting words. The result is filterable via `sn_reading_time_minutes`
 * if a caller wants to override the calculation (e.g. add code-block
 * weighting).
 *
 * @param int|WP_Post $post Post ID or object.
 * @return int Minutes (>= 1).
 */
function sn_calculate_reading_time( $post ) {
	$post = get_post( $post );
	if ( ! $post ) {
		return 1;
	}

	$content = $post->post_content;
	$content = preg_replace( '/<!--\s*\/?wp:[^>]*-->/', ' ', $content ); // strip block delimiters
	$content = strip_shortcodes( $content );
	$content = wp_strip_all_tags( $content );

	$words   = str_word_count( $content );
	$wpm     = (int) apply_filters( 'sn_reading_time_wpm', SN_READING_TIME_DEFAULT_WPM, $post );
	$wpm     = max( 1, $wpm );
	$minutes = max( 1, (int) ceil( $words / $wpm ) );

	return (int) apply_filters( 'sn_reading_time_minutes', $minutes, $post, $words, $wpm );
}

/**
 * Get the cached reading time, populating the cache on miss.
 *
 * @param int|WP_Post|null $post Post ID, object, or null for current.
 * @return int Minutes (>= 1).
 */
function sn_get_reading_time( $post = null ) {
	$post = get_post( $post );
	if ( ! $post ) {
		return 1;
	}

	$cached = get_post_meta( $post->ID, SN_READING_TIME_META_KEY, true );
	if ( '' !== $cached && null !== $cached ) {
		return max( 1, (int) $cached );
	}

	$minutes = sn_calculate_reading_time( $post );
	update_post_meta( $post->ID, SN_READING_TIME_META_KEY, $minutes );
	return $minutes;
}

/**
 * Recompute and cache on every post save. Skips revisions and autosaves.
 */
add_action( 'wp_after_insert_post', function( $post_id, $post, $update, $post_before ) {
	if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
		return;
	}
	if ( ! in_array( $post->post_type, array( 'post', 'page' ), true ) ) {
		return;
	}
	$minutes = sn_calculate_reading_time( $post );
	update_post_meta( $post_id, SN_READING_TIME_META_KEY, $minutes );
}, 10, 4 );

/**
 * Shortcode: [sn_reading_time] → "X min read".
 *
 * Format is filterable via `sn_reading_time_format`. The default uses
 * "{minutes} min read"; pass "{minutes}-minute read" for the long form.
 */
add_shortcode( 'sn_reading_time', function() {
	$post = get_post();
	if ( ! $post ) {
		return '';
	}
	$minutes = sn_get_reading_time( $post );
	$format  = (string) apply_filters( 'sn_reading_time_format', '{minutes} min read', $post, $minutes );
	return esc_html( str_replace( '{minutes}', (string) $minutes, $format ) );
} );

/**
 * Process [sn_reading_time] inside block template parts (mirror of the
 * pattern used for [current_year] in inc/setup.php).
 */
add_filter( 'render_block', function( $block_content, $block ) {
	if ( strpos( $block_content, '[sn_reading_time]' ) !== false ) {
		$block_content = do_shortcode( $block_content );
	}
	return $block_content;
}, 10, 2 );

/**
 * Scan posts/pages for legacy hand-typed reading-time strings.
 *
 * Returns an array keyed by post ID, each entry containing the post
 * object, content matches, excerpt matches, and meta matches. A "match"
 * is an array of [match_string, context_snippet] pairs. Used by the
 * admin preview UI; intentionally read-only.
 *
 * @return array<int, array{post: WP_Post, content: array, excerpt: array, meta: array}>
 */
function sn_find_legacy_reading_time() {
	$posts = get_posts( array(
		'post_type'      => array( 'post', 'page' ),
		'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
		'posts_per_page' => -1,
		'fields'         => 'ids',
	) );

	$report = array();
	foreach ( $posts as $post_id ) {
		$post  = get_post( $post_id );
		$entry = array(
			'post'    => $post,
			'content' => sn_extract_reading_time_matches( $post->post_content ),
			'excerpt' => sn_extract_reading_time_matches( $post->post_excerpt ),
			'meta'    => array(),
		);

		foreach ( get_post_meta( $post_id ) as $key => $values ) {
			if ( '_' === $key[0] ) {
				continue; // private meta — never auto-edit
			}
			foreach ( (array) $values as $value ) {
				if ( ! is_string( $value ) ) {
					continue;
				}
				$matches = sn_extract_reading_time_matches( $value );
				if ( $matches ) {
					$entry['meta'][ $key ] = array_merge( $entry['meta'][ $key ] ?? array(), $matches );
				}
			}
		}

		if ( $entry['content'] || $entry['excerpt'] || $entry['meta'] ) {
			$report[ $post_id ] = $entry;
		}
	}
	return $report;
}

/**
 * Extract [match, snippet] pairs from a string. Snippet is ~50 chars of
 * surrounding context with the match wrapped in `<<…>>` markers for the
 * preview UI. Returns an empty array when no matches.
 */
function sn_extract_reading_time_matches( $text ) {
	if ( ! is_string( $text ) || '' === $text ) {
		return array();
	}
	if ( ! preg_match_all( SN_READING_TIME_LEGACY_REGEX, $text, $matches, PREG_OFFSET_CAPTURE ) ) {
		return array();
	}

	$out = array();
	foreach ( $matches[0] as $m ) {
		$match  = $m[0];
		$offset = $m[1];
		$start  = max( 0, $offset - 50 );
		$end    = min( strlen( $text ), $offset + strlen( $match ) + 50 );
		$before = substr( $text, $start, $offset - $start );
		$after  = substr( $text, $offset + strlen( $match ), $end - $offset - strlen( $match ) );
		$out[]  = array(
			'match'   => $match,
			'snippet' => trim( ( $start > 0 ? '…' : '' ) . $before . '<<' . $match . '>>' . $after . ( $end < strlen( $text ) ? '…' : '' ) ),
		);
	}
	return $out;
}

/**
 * Apply the legacy cleanup: strip the matched substrings from
 * post_content, post_excerpt, and public meta. Also collapses empty
 * inline wrappers (<p></p>, <span></span>) left behind by the removal.
 *
 * Returns a count of edited posts. Recomputes the reading-time cache for
 * each edited post afterward.
 *
 * @return int Number of posts modified.
 */
function sn_apply_legacy_reading_time_cleanup() {
	$report  = sn_find_legacy_reading_time();
	$updated = 0;

	foreach ( $report as $post_id => $entry ) {
		$post    = $entry['post'];
		$changed = false;

		if ( $entry['content'] ) {
			$new = preg_replace( SN_READING_TIME_LEGACY_REGEX, '', $post->post_content );
			$new = preg_replace( '#<(p|span|small|em|strong|i|b)[^>]*>\s*</\1>#i', '', $new );
			if ( $new !== $post->post_content ) {
				wp_update_post( array( 'ID' => $post_id, 'post_content' => $new ) );
				$changed = true;
			}
		}
		if ( $entry['excerpt'] ) {
			$new = preg_replace( SN_READING_TIME_LEGACY_REGEX, '', $post->post_excerpt );
			if ( $new !== $post->post_excerpt ) {
				wp_update_post( array( 'ID' => $post_id, 'post_excerpt' => $new ) );
				$changed = true;
			}
		}
		foreach ( $entry['meta'] as $key => $matches ) {
			$values = get_post_meta( $post_id, $key, false );
			foreach ( $values as $value ) {
				if ( ! is_string( $value ) ) {
					continue;
				}
				$new = preg_replace( SN_READING_TIME_LEGACY_REGEX, '', $value );
				if ( $new !== $value ) {
					update_post_meta( $post_id, $key, $new, $value );
					$changed = true;
				}
			}
		}

		if ( $changed ) {
			delete_post_meta( $post_id, SN_READING_TIME_META_KEY );
			sn_get_reading_time( $post_id ); // repopulate from fresh content
			$updated++;
		}
	}
	return $updated;
}

/**
 * Render the Reading Time card on the admin Dashboard tab. Hooked to the
 * `sn_admin_dashboard_extras` action emitted by inc/admin-page.php.
 */
add_action( 'sn_admin_dashboard_extras', function() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$preview = isset( $_GET['sn_rt_preview'] );
	$applied = isset( $_POST['sn_action'] ) && 'apply_reading_time_cleanup' === $_POST['sn_action']
		&& check_admin_referer( 'sn_theme_options_nonce', '_wpnonce', false );

	if ( $applied ) {
		$count = sn_apply_legacy_reading_time_cleanup();
		echo '<div class="notice notice-success is-dismissible" style="margin:1em 0;"><p>' .
			esc_html( sprintf( '%d post(s) cleaned. Reading-time cache rebuilt.', $count ) ) .
			'</p></div>';
	}

	$base_url    = admin_url( 'themes.php?page=sn-theme-options' );
	$preview_url = esc_url( add_query_arg( 'sn_rt_preview', '1', $base_url ) );
	$report      = $preview ? sn_find_legacy_reading_time() : array();

	echo '<hr style="margin:1.5em 0;">';
	echo '<h2 style="font-size:1.1em;margin-bottom:0.8em;">Reading Time</h2>';
	echo '<p style="color:#666;font-size:0.9em;max-width:680px;">Word count ÷ ' . (int) SN_READING_TIME_DEFAULT_WPM . ' WPM, cached in <code>_sn_reading_time_minutes</code> post meta and rebuilt on save. The cleanup tool below scans for hand-typed strings like "8-minute read" left over from before the shortcode existed.</p>';

	echo '<div style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-start;margin-top:1em;">';
	echo '<div style="background:#fff;border:1px solid #c3c4c7;border-radius:4px;padding:16px 20px;max-width:300px;">';
	echo '<strong style="display:block;margin-bottom:4px;">Preview Cleanup</strong>';
	echo '<p style="color:#666;font-size:0.85em;margin:0 0 12px;">Scan all posts and pages for legacy reading-time strings. Read-only.</p>';
	echo '<a href="' . $preview_url . '" class="button">Run Preview</a>';
	echo '</div>';

	if ( $preview ) {
		echo '<form method="post" style="background:#fff;border:1px solid #c3c4c7;border-radius:4px;padding:16px 20px;max-width:300px;">';
		wp_nonce_field( 'sn_theme_options_nonce' );
		echo '<strong style="display:block;margin-bottom:4px;">Apply Cleanup</strong>';
		echo '<p style="color:#666;font-size:0.85em;margin:0 0 12px;">Removes the matches above. Cannot be undone — back up first.</p>';
		echo '<button type="submit" name="sn_action" value="apply_reading_time_cleanup" class="button button-primary"' . ( empty( $report ) ? ' disabled' : '' ) . '>Apply to ' . count( $report ) . ' post(s)</button>';
		echo '</form>';
	}
	echo '</div>';

	if ( $preview ) {
		if ( empty( $report ) ) {
			echo '<p style="margin-top:1em;color:#00a32a;">&#10003; No legacy reading-time strings found.</p>';
			return;
		}
		echo '<div style="margin-top:1.5em;background:#fff;border:1px solid #c3c4c7;border-radius:4px;padding:0;max-width:900px;">';
		echo '<table class="widefat striped" style="border:0;"><thead><tr><th style="width:60px;">ID</th><th>Title</th><th>Where</th><th>Match</th></tr></thead><tbody>';
		foreach ( $report as $post_id => $entry ) {
			$rows = array();
			foreach ( $entry['content'] as $m ) $rows[] = array( 'content', $m );
			foreach ( $entry['excerpt'] as $m ) $rows[] = array( 'excerpt', $m );
			foreach ( $entry['meta'] as $key => $matches ) {
				foreach ( $matches as $m ) $rows[] = array( 'meta:' . $key, $m );
			}
			foreach ( $rows as $i => $row ) {
				echo '<tr>';
				echo '<td>' . ( 0 === $i ? '<a href="' . esc_url( get_edit_post_link( $post_id ) ) . '">' . (int) $post_id . '</a>' : '' ) . '</td>';
				echo '<td>' . ( 0 === $i ? esc_html( get_the_title( $post_id ) ) : '' ) . '</td>';
				echo '<td><code style="font-size:0.8em;">' . esc_html( $row[0] ) . '</code></td>';
				echo '<td><span style="color:#d63638;font-family:monospace;font-size:0.85em;">' . esc_html( $row[1]['match'] ) . '</span><br><small style="color:#787c82;">' . esc_html( $row[1]['snippet'] ) . '</small></td>';
				echo '</tr>';
			}
		}
		echo '</tbody></table></div>';
	}
} );
