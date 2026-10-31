<?php
/**
 * Signal & Noise — "Updated YYYY.MM.DD" frontmatter line.
 *
 * Surfaces a reader-visible "Updated" date on Notes that were materially
 * revised after publication. A note is "materially revised" when its
 * substantive-change timestamp (the provenance commit, 13.2.7; post_modified
 * before that and as the fallback) is at least $threshold_days (default 14)
 * after its publish timestamp — so tiny same-week typo fixes don't earn a badge,
 * but a real substantive update does. Since 2026.10 the line links to the
 * /verify compare of the last edit when the record holds two or more signed
 * versions (sn_post_updated_compare_url()).
 *
 * Registers [sn_updated_date]. Placed in parts/post-frontmatter.html by the
 * signal-noise/updated-date PHP-only block since 13.1.0; the shortcode stays
 * registered for post content. The global render_block bridge that once
 * re-resolved the token went in #389.
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
	$modified  = sn_post_substantive_timestamp( $post );
	if ( false === $published || false === $modified ) {
		return '';
	}

	$delta = $modified - $published;
	if ( $delta < $threshold_days * DAY_IN_SECONDS ) {
		return '';
	}

	$label = 'Updated ' . wp_date( 'Y.m.d', $modified );
	$time  = sprintf(
		'<time class="sn-post-frontmatter__updated" datetime="%s">%s</time>',
		esc_attr( wp_date( 'c', $modified ) ),
		esc_html( $label )
	);
	$compare = sn_post_updated_compare_url( $post );
	if ( '' === $compare ) {
		return $time;
	}
	// The visible text stays the date; the name says where the link goes, and
	// starts with the visible text so a voice command can still say it.
	return sprintf(
		'<a class="sn-post-frontmatter__updated-link" href="%s" aria-label="%s">%s</a>',
		esc_url( $compare ),
		esc_attr( $label . ': see what changed' ),
		$time
	);
}

/**
 * The /verify compare of a note's last edit, or '' (2026.10).
 *
 * The note's two latest signed versions, preselected: compare_a and compare_b
 * are the compare form's own field names, and #compare opens its tab. The
 * versions are the plugin's public view-model, the list the provenance panel
 * draws its edit rows from, read the way the print colophon reads it. Version
 * 0 is the founding snapshot, which the compare form does not take, so it
 * never counts. Fewer than two versions, or no plugin, and the line stays plain.
 *
 * @param WP_Post $post Post object.
 * @return string Raw URL, or ''.
 */
function sn_post_updated_compare_url( $post ) {
	if ( ! function_exists( 'sn_prov_view_data' ) ) {
		return '';
	}
	$vm   = sn_prov_view_data( $post->ID );
	$uid  = is_array( $vm ) ? (string) ( $vm['note_uid'] ?? '' ) : '';
	$base = is_array( $vm ) ? (string) ( $vm['verify_url'] ?? '' ) : '';
	$rows = array();
	foreach ( is_array( $vm ) ? (array) ( $vm['versions'] ?? array() ) : array() as $v ) {
		if ( (int) ( $v['version'] ?? 0 ) >= 1 ) {
			$rows[] = $v;
		}
	}
	if ( '' === $uid || '' === $base || count( $rows ) < 2 ) {
		return '';
	}
	list( $a, $b ) = array_slice( $rows, -2 );
	// A version is unanchored from its save until the signer answers, and its
	// credential does not exist until then: no link until both are signed.
	if ( 'unanchored' === ( $a['status'] ?? '' ) || 'unanchored' === ( $b['status'] ?? '' ) ) {
		return '';
	}
	$from = (int) $a['version'];
	$to   = (int) $b['version'];
	return $base . ( false === strpos( $base, '?' ) ? '?' : '&' ) . 'note=' . rawurlencode( $uid ) . '&compare_a=' . $from . '&compare_b=' . $to . '#compare';
}

/**
 * When the PROSE last changed: the honest clock for "Updated".
 *
 * 13.2.7. `post_modified` bumps on ANY save: a title-tag override, a block
 * migration, a bulk re-save. On 2026-09-17 every note gained a search title
 * and all 43 read "Updated 2026.09.17" over prose that had not moved. The
 * plugin's provenance chain commits only when the normalized prose changes
 * and denormalizes that moment into `_sn_prov_last_commit_gmt` (MySQL
 * datetime, GMT), the same clock its stale-posts check has used since
 * plugin v11.11.8. Read it here; fall back to post_modified only for a post
 * with no commit (a Page, or a note predating the chain), never to nothing.
 *
 * @param WP_Post $post Post object.
 * @return int|false Unix timestamp, or false when neither clock reads.
 */
function sn_post_substantive_timestamp( $post ) {
	$commit = (string) get_post_meta( $post->ID, '_sn_prov_last_commit_gmt', true );
	if ( '' !== $commit ) {
		$ts = strtotime( $commit . ' UTC' );
		if ( false !== $ts && $ts > 0 ) {
			return $ts;
		}
	}
	return get_post_timestamp( $post, 'modified' );
}

/**
 * [sn_updated_date] — render the "Updated" line for the current post.
 *
 * @return string
 */
function sn_updated_date_shortcode() {
	return sn_post_updated_display( get_post() );
}

// Placed by the signal-noise/updated-date PHP-only block since 13.1.0; the
// shortcode stays registered for post content. The global render_block
// bridge went in #389 (no template carries the token).
// Skip WP registration under the standalone test harness (add_shortcode
// isn't stubbed there; the helpers are exercised directly).
if ( ! defined( 'SN_POST_UPDATED_DATE_TEST' ) || ! SN_POST_UPDATED_DATE_TEST ) {
	add_shortcode( 'sn_updated_date', 'sn_updated_date_shortcode' );
}
