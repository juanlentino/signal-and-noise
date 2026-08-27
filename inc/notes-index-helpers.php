<?php
/**
 * Signal & Noise — /notes index PURE helpers.
 *
 * v10.49.0: extracted VERBATIM from inc/page-notes-render.php. That file is
 * a full-page renderer (echoes HTML + runs WP_Query at include time), so for
 * ~10 versions its testable helpers hid behind a documented mid-file
 * SN_NOTES_RENDER_TEST early-return hack whose placement was load-bearing
 * (helpers above the return, render body below — it already forced one
 * emergency extraction, v10.42.2's notes-reading-time). The helpers now live
 * here, always-loadable: functions.php requires this module BEFORE the
 * template router, the renderer just calls them, and the sentinel is retired.
 *
 * Consumed by inc/page-notes-render.php (the template_include short-circuit
 * render path, WORDPRESS-REFERENCE §10.4) and by the standalone fixtures
 * (tests/notes-pagination.php, tests/notes-search.php,
 * tests/notes-topic-reframe.php, tests/notes-index-helpers.php).
 *
 * @package SignalNoise
 * @since 10.49.0 (function bodies from 9.6.0 onward — see each docblock)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Format a post date for the catalog layout.
 *
 *   2026.05.07
 *
 * Calendar-style with dots, big enough to scan but tight. Uses the
 * post's published date in site timezone.
 */
function sn_notes_render_date( $post ) {
	$ts = get_the_time( 'U', $post );
	return esc_html( wp_date( 'Y.m.d', (int) $ts ) );
}

/**
 * Format a post's reading time, padded for visual rhythm in the
 * spec column.
 *
 *   03 MIN
 *
 * Reads cached value from sn_reading_time post meta if available;
 * computes on the fly otherwise. Two-digit zero-padded for
 * tabular alignment with the date.
 */
function sn_notes_render_reading_time( $post_id ) {
	// Read the canonical cache populated by inc/reading-time.php on save.
	// The constant lives in that module; fall back to the literal key if
	// reading-time.php is somehow not loaded so this never goes stale.
	$meta_key = defined( 'SN_READING_TIME_META_KEY' ) ? SN_READING_TIME_META_KEY : '_sn_reading_time_minutes';
	$mins     = (int) get_post_meta( $post_id, $meta_key, true );
	if ( $mins < 1 ) {
		// Cache miss on a brand-new post that hasn't been saved through
		// the wp_after_insert_post hook yet. Use the canonical helper so
		// we share block-stripping + WPM with the shortcode path; this
		// also populates the cache for the next render.
		$mins = function_exists( 'sn_get_reading_time' )
			? (int) sn_get_reading_time( $post_id )
			: 1;
	}
	return sprintf( '%02d MIN', $mins );
}

/**
 * Notes per page for the /notes index. Default 20; overridable by the
 * plugin via the sn_notes_per_page filter (Release 2). Clamped [1,100]
 * to defend against a bad filter return.
 */
function sn_notes_per_page() {
	$n = (int) apply_filters( 'sn_notes_per_page', 20 );
	return max( 1, min( 100, $n ) );
}

/**
 * Resolve the current page number for the /notes index. Reads WP's
 * `paged` query var, falling back to the raw ?paged= query-string
 * param — the short-circuit router (inc/page-notes-template.php) may
 * not populate the query var cleanly, and the paginate_links() output
 * carries ?paged=N. Floored at 1.
 */
function sn_notes_current_page() {
	$paged = (int) get_query_var( 'paged' );
	if ( $paged < 1 && isset( $_GET['paged'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only pagination index, no state change.
		$paged = (int) $_GET['paged']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}
	return max( 1, $paged );
}

/**
 * Query the notes posts in chronological-descending order.
 *
 * Constraint: post_type=post (Signal & Noise treats all blog posts
 * as Notes — there's no separate post type — and the routing
 * `/notes/%postname%/` is enforced by sn_ensure_permalink_structure).
 * No taxonomy filter needed.
 */
function sn_notes_query_posts() {
	$tag_id        = sn_notes_current_tag_id();
	$start_here_id = sn_notes_start_here_id();
	$term_for_mode = sn_notes_search_term();
	// v11.10.0: BROWSE mode returns the whole corpus in one response and drops
	// pagination entirely. Pagination is what you reach for when a list has no
	// structure but recency; the year spine bounds the VISIBLE page instead
	// (prior years collapse to one line each), so paging would only re-hide
	// what the spine already folds. Filtered modes keep sn_notes_per_page():
	// a search result set is unbounded by nature and has no spine to fold it.
	$browse_all = ( '' === $term_for_mode && $tag_id <= 0 );
	$args = array(
		'post_type'           => 'post',
		'post_status'         => 'publish',
		'posts_per_page'      => $browse_all ? -1 : sn_notes_per_page(),
		'paged'               => $browse_all ? 1 : sn_notes_current_page(),
		'orderby'             => 'date',
		'order'               => 'DESC',
		'no_found_rows'       => false, // pagination needs found_posts / max_num_pages
		'ignore_sticky_posts' => true,  // the sticky is floated into the Start-here card, never the list
		// v11.4.6: password-protected entries stay off the index and out of search.
		// Search rows render get_the_excerpt(), which for a protected post is WP's
		// "There is no excerpt because this is a protected post." placeholder — no
		// body ever leaked, so this is convention symmetry with inc/feed-json.php and
		// inc/llms-txt.php (and it drops the useless placeholder rows). CMA audit INFO-3.
		'has_password'        => false,
	);
	// Browse mode is Notes-only by construction (post_type=post = the whole
	// Notes corpus). SEARCH mode (v10.51.0) widens to the whole public corpus:
	// posts AND pages, so essays and editorial Pages surface in one
	// type-labeled list (sn_notes_result_type_label) — the owner-decided
	// session-4 shape.
	$term = $term_for_mode;
	if ( '' !== $term ) {
		$args['s']         = $term;
		$args['post_type'] = array( 'post', 'page' );
	}
	if ( $tag_id > 0 ) {
		// Tag-archive mode: constrain to the queried post_tag.
		$args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- single-term tag archive, the page's sole query.
			array(
				'taxonomy' => 'post_tag',
				'field'    => 'term_id',
				'terms'    => $tag_id,
			),
		);
	} elseif ( $start_here_id > 0 ) {
		// Browse mode: the Start-here post is shown as the pinned card above;
		// keep it out of the chronological list (every page) so it never
		// appears twice. Consistent across pages → honest found_posts count.
		$args['post__not_in'] = array( $start_here_id );
	}
	return new WP_Query( $args );
}

/**
 * Resolve the current Notes search term, if any. Mirrors
 * sn_notes_current_page(): reads WP's `s` query var, falling back to the
 * raw ?s= query-string param (the short-circuit router may not populate
 * the query var cleanly). Unslashed, tag-stripped, trimmed. Returns ''
 * when absent or whitespace-only (= browse mode). The empty short-circuit
 * means sanitize_text_field()/wp_unslash() are only touched when a term
 * exists — keeping the pagination fixtures (which don't stub them) green.
 */
function sn_notes_search_term() {
	$term = (string) get_query_var( 's' );
	if ( '' === $term && isset( $_GET['s'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only search index, no state change.
		$term = sanitize_text_field( wp_unslash( $_GET['s'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only, sanitized at point of read.
	}
	if ( '' === $term ) {
		return '';
	}
	return trim( sanitize_text_field( $term ) );
}

/**
 * The extra query args paginate_links() must carry so search-result page
 * 2+ stays inside the search. Empty array when browsing. The term is
 * rawurlencode()'d because WP's add_query_arg() does not URL-encode values
 * (urlencode=false), so a multi-word term would otherwise yield a broken
 * page link.
 */
function sn_notes_pagination_add_args( $term = '' ) {
	return ( '' !== $term ) ? array( 's' => rawurlencode( $term ) ) : array();
}

/**
 * Corpus-level hero stats for the /notes archive header: total entry count and
 * the newest note's date. Extracted (and kept above the SN_NOTES_RENDER_TEST
 * guard) so it's unit-testable, and so the count is the CORPUS total rather than
 * the current page's slice.
 *
 * - count: $query->found_posts — the whole result set, NOT $query->post_count
 *   (only this page's ≤per_page slice, which mis-read "N entries" on page 2+,
 *   e.g. "8 entries" on a short final page).
 * - latest_date: the newest note's date. The query is date-DESC, so on page 1
 *   posts[0] IS the newest (free). On page 2+ posts[0] is this page's first row,
 *   not the corpus newest, so the "Last updated" line is suppressed rather than
 *   show a wrong date. (The hero renders in browse mode only.)
 *
 * @param WP_Query $query The notes archive query.
 * @return array{count:int,latest_date:string}
 */
function sn_notes_hero_stats( $query ) {
	$count       = isset( $query->found_posts ) ? (int) $query->found_posts : 0;
	$latest_date = '';
	if ( $count > 0 && sn_notes_current_page() <= 1 && ! empty( $query->posts ) ) {
		$latest_date = wp_date( 'Y.m.d', (int) get_the_time( 'U', $query->posts[0] ) );
	}
	return array( 'count' => $count, 'latest_date' => $latest_date );
}

/**
 * Sticky-post ids, defensively cast to int. Empty when none are set or the
 * option is absent. The function_exists guard keeps the standalone fixtures
 * (which don't stub get_option) resolving to "no sticky".
 *
 * @return int[]
 */
function sn_notes_sticky_ids() {
	if ( ! function_exists( 'get_option' ) ) {
		return array();
	}
	$ids = get_option( 'sticky_posts' );
	return is_array( $ids ) ? array_map( 'intval', $ids ) : array();
}

/**
 * The queried post_tag term id for a /notes tag-archive request, else 0.
 * Reads the queried object only when is_tag() is true; guarded so the
 * fixtures (no is_tag stub) resolve to browse mode (0).
 *
 * @return int
 */
function sn_notes_current_tag_id() {
	if ( ! function_exists( 'is_tag' ) || ! is_tag() ) {
		return 0;
	}
	$obj = function_exists( 'get_queried_object' ) ? get_queried_object() : null;
	return ( $obj && isset( $obj->term_id ) ) ? (int) $obj->term_id : 0;
}

/**
 * The queried tag's own description for a tag-archive request, else ''.
 *
 * The hero dek is otherwise the same sentence on all 23 tag archives, which
 * is the thin-content shape the contentless-page rule warns about. A term
 * that carries a description gets to speak for itself; one that does not
 * keeps the generic dek, because an invented sentence per tag would be
 * exactly the fabrication the rest of this codebase refuses.
 *
 * The plugin reads the SAME term description for the meta description
 * (v13.14.0), so writing one sentence lights up both surfaces at once.
 *
 * @since theme v12.11.0
 * @return string Stripped description, or '' when absent or not a tag view.
 */
function sn_notes_tag_description() {
	if ( ! function_exists( 'is_tag' ) || ! is_tag() ) {
		return '';
	}
	$obj = function_exists( 'get_queried_object' ) ? get_queried_object() : null;
	if ( ! $obj || ! isset( $obj->description ) ) {
		return '';
	}
	$desc = (string) $obj->description;
	$desc = function_exists( 'wp_strip_all_tags' ) ? wp_strip_all_tags( $desc ) : strip_tags( $desc );
	return trim( $desc );
}

/**
 * Is $post_id a published Note (post_type=post)? Guarded for the test
 * harness — returns false when the WP accessors are absent.
 *
 * @param int $post_id
 * @return bool
 */
function sn_notes_is_published_post( $post_id ) {
	$post_id = (int) $post_id;
	if ( $post_id < 1 || ! function_exists( 'get_post_status' ) ) {
		return false;
	}
	if ( 'publish' !== get_post_status( $post_id ) ) {
		return false;
	}
	if ( function_exists( 'get_post_type' ) && 'post' !== get_post_type( $post_id ) ) {
		return false;
	}
	return true;
}

/**
 * The "Start here" front-door post id: the first published sticky, but ONLY
 * in pure browse mode (no search, no tag) — search/tag views hide the card.
 * 0 when there's no eligible sticky. The owner stickies the post they want
 * pinned; this is the editorial control for the card.
 *
 * @return int
 */
function sn_notes_start_here_id() {
	if ( '' !== sn_notes_search_term() || sn_notes_current_tag_id() > 0 ) {
		return 0;
	}
	foreach ( sn_notes_sticky_ids() as $sid ) {
		if ( sn_notes_is_published_post( $sid ) ) {
			return (int) $sid;
		}
	}
	return 0;
}

/**
 * The Start Here PAGE id for the hero wayfinding link, or 0.
 *
 * Deliberately separate from sn_notes_start_here_id(): that one answers the
 * sticky-POST question that drives the pinned index row, and returns 0 once no
 * sticky qualifies. Start Here became a page (a child of /notes/) in 2026-08,
 * which freed its index slot but left it unreachable from any navigation — a
 * page is not in the post query, so nothing linked it. This resolves the page
 * itself so the hero can carry the link.
 *
 * Resolved by path rather than a pinned id so the link dies with the page
 * instead of pointing at a 404: an absent, trashed, or unpublished page yields
 * 0 and the hero simply renders without it.
 *
 * @return int
 */
function sn_notes_start_here_page_id() {
	if ( ! function_exists( 'get_page_by_path' ) || ! function_exists( 'get_post_status' ) ) {
		return 0;
	}
	$page = get_page_by_path( 'notes/start-here' );
	if ( ! $page || ! isset( $page->ID ) ) {
		return 0;
	}
	if ( 'publish' !== get_post_status( $page->ID ) ) {
		return 0;
	}
	return (int) $page->ID;
}

/**
 * Pagination base URL: the tag-archive permalink in tag mode, the bare
 * /notes/ index otherwise. Both paginate via ?paged=%#% (the exact-path
 * router strips the query string before matching, so ?paged= is safe on
 * both routes).
 *
 * @return string
 */
function sn_notes_pagination_base() {
	$tag_id = sn_notes_current_tag_id();
	if ( $tag_id > 0 && function_exists( 'get_term_link' ) ) {
		$link = get_term_link( $tag_id, 'post_tag' );
		if ( is_string( $link ) && '' !== $link ) {
			return $link;
		}
	}
	return home_url( '/notes/' );
}

/**
 * Type label for a search-result row: Note (posts), Essay (pillar-designated
 * Pages, the _sn_pillar meta from the v10.47.0 curation surface), Page
 * (everything else). Pure; only meaningful in search mode (browse is
 * Notes-only so rows need no label).
 *
 * @param object $post Post-like object (ID + post_type).
 * @return string
 */
function sn_notes_result_type_label( $post ) {
	$type = isset( $post->post_type ) ? (string) $post->post_type : 'post';
	if ( 'post' === $type ) {
		return 'Note';
	}
	$pillar = (string) get_post_meta( (int) ( $post->ID ?? 0 ), '_sn_pillar', true );
	return '' !== $pillar && '0' !== $pillar ? 'Essay' : 'Page';
}

/**
 * Robots directives for search mode: a crafted ?s= URL must not be indexable
 * as site content (query-stuffing abuse), so a non-empty term appends
 * noindex + follow.
 *
 * v10.51.1: answers the PLUGIN's `sn_seo_robots_directives` seam — a directive
 * LIST — because the plugin removes core's wp_robots and emits the tag itself.
 * v10.51.0 filtered `wp_robots` with a map and was live-verified inert: the
 * array it mutated was never printed. Pure: input never mutated, list-shaped
 * result, browse mode returns the input unchanged.
 *
 * @param array  $directives Directive list from the plugin's emitter.
 * @param string $term       Current search term ('' = browse).
 * @return array
 */
function sn_notes_search_robots( $directives, $term ) {
	$directives = array_values( (array) $directives );
	if ( '' === (string) $term ) {
		return $directives;
	}
	$directives[] = 'noindex';
	$directives[] = 'follow';
	return array_values( array_unique( $directives ) );
}

/* ─────────────────────────────────────────────────────────────────────
 * v11.10.0 — the index reads as a MANIFEST, not a feed.
 *
 * Row differentiators are editorial only: reading time, tags, and the
 * provenance version. Traffic and decay data are deliberately absent —
 * publishing per-note performance would cut against the ML kernel's
 * refusals, and a reader choosing what to read next is owed the argument,
 * not the numbers.
 * ───────────────────────────────────────────────────────────────────── */

/**
 * The Note's provenance version: how many times its prose has been
 * substantively revised AND signed.
 *
 * Reads the companion plugin's `_sn_prov_chain` meta by its literal key —
 * the established theme↔plugin seam (precedent: the `_sn_prov_uid` twin in
 * inc/note-uid.php). Absent plugin, absent meta, or a malformed chain all
 * return 0, so the badge simply does not render.
 *
 * @param int $post_id
 * @return int Highest version in the chain, 0 when there is none.
 */
function sn_notes_prov_version( $post_id ) {
	if ( ! function_exists( 'get_post_meta' ) ) {
		return 0;
	}
	$chain = get_post_meta( (int) $post_id, '_sn_prov_chain', true );
	if ( ! is_array( $chain ) ) {
		return 0;
	}
	$max = 0;
	foreach ( $chain as $entry ) {
		if ( is_array( $entry ) && (int) ( $entry['version'] ?? 0 ) > $max ) {
			$max = (int) $entry['version'];
		}
	}
	return $max;
}

/**
 * Whether to show a provenance badge at all.
 *
 * v1 means "published once, signed once" — true of nearly every Note, so
 * rendering it 30 times says nothing and costs a column. The badge earns its
 * place only from v2, where it reports something the date cannot: this
 * argument was revisited, and the revision was signed.
 *
 * @param int $version
 * @return bool
 */
function sn_notes_prov_version_is_notable( $version ) {
	return (int) $version >= 2;
}

/**
 * Up to $limit tags for a row, as {name, url} pairs.
 *
 * Tags are the only cross-cutting structure the corpus actually has (the
 * TF-IDF topic partition covers 4 of 33 Notes, so it cannot carry a spine),
 * which makes them the row's most useful non-chronological signal.
 *
 * @param int $post_id
 * @param int $limit
 * @return array<int,array{name:string,url:string}>
 */
function sn_notes_row_tags( $post_id, $limit = 2 ) {
	if ( ! function_exists( 'get_the_tags' ) ) {
		return array();
	}
	$tags = get_the_tags( (int) $post_id );
	if ( ! is_array( $tags ) ) {
		return array();
	}
	$out = array();
	foreach ( $tags as $tag ) {
		if ( count( $out ) >= (int) $limit ) {
			break;
		}
		$link  = function_exists( 'get_term_link' ) ? get_term_link( $tag ) : '';
		$out[] = array(
			'name' => (string) $tag->name,
			'url'  => is_string( $link ) ? $link : '',
		);
	}
	return $out;
}

/**
 * Group posts by publication year, newest year first, newest post first.
 *
 * @param array $posts WP_Post-ish objects.
 * @return array<string,array> year => posts
 */
function sn_notes_group_by_year( $posts ) {
	$out = array();
	foreach ( (array) $posts as $p ) {
		$year = function_exists( 'get_the_date' ) ? (string) get_the_date( 'Y', $p ) : '';
		if ( '' === $year ) {
			$year = isset( $p->post_date ) ? substr( (string) $p->post_date, 0, 4 ) : '—';
		}
		$out[ $year ][] = $p;
	}
	krsort( $out, SORT_STRING );
	return $out;
}

/**
 * Whether the year spine should render at all.
 *
 * The spine exists to bound the page permanently as the corpus grows — but
 * every Note published so far is from a single year, so today it would draw
 * one band restating the count already in the section header. It renders when
 * it discriminates, and not before: a structure that appears the moment it
 * carries information, rather than a label that is decorative until 2027.
 *
 * @param array $grouped sn_notes_group_by_year() result.
 * @return bool
 */
function sn_notes_year_spine_is_useful( $grouped ) {
	return count( (array) $grouped ) > 1;
}

/**
 * Group posts by calendar month, newest first. Keys are 'YYYY-MM' so they sort
 * correctly as strings and never collide across years.
 *
 * @param array $posts
 * @return array<string,array>
 */
function sn_notes_group_by_month( $posts ) {
	$out = array();
	foreach ( (array) $posts as $p ) {
		$key = function_exists( 'get_the_date' ) ? (string) get_the_date( 'Y-m', $p ) : '';
		if ( '' === $key ) {
			$key = isset( $p->post_date ) ? substr( (string) $p->post_date, 0, 7 ) : '0000-00';
		}
		$out[ $key ][] = $p;
	}
	krsort( $out, SORT_STRING );
	return $out;
}

/**
 * Whether a year's rows should be subdivided by month.
 *
 * Same discipline as the year spine: a divider that fires on a handful of rows
 * is texture, not structure. Below this count the year reads fine as one run,
 * and chopping it would work against the reason the rows were made dense in the
 * first place — the titles are meant to be read in sequence.
 *
 * @param int $year_count
 * @return bool
 */
function sn_notes_month_dividers_are_useful( $year_count ) {
	return (int) $year_count >= SN_NOTES_MONTH_DIVIDER_MIN;
}
