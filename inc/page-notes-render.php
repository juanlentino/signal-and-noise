<?php
/**
 * Signal & Noise — /notes full PHP renderer.
 *
 * Included via the `template_include` short-circuit in
 * inc/page-notes-template.php. Renders the entire HTML document for
 * the /notes index page from scratch, bypassing WordPress block-
 * template resolution entirely.
 *
 * Layout direction: "Industrial Catalog" — the page is presented as
 * a directory listing for the brand. Numbered pillar essays with a
 * blood-red left rail (NIN-influenced), tabular note rows in mono
 * with a date+meta column pulled left like a magazine spec line, a
 * terminal-status RSS footer with a blinking cursor. Stays inside
 * the existing brutalist white/asphalt/blood vocabulary but adds
 * editorial precision.
 *
 * Design tokens (from theme.json):
 *   void     #ffffff   page background
 *   asphalt  #f5f5f5   subtle card background
 *   concrete #d9d9d9   hairline borders
 *   rust     #666666   secondary text
 *   bone     #000000   primary text
 *   blood    #e00404   accent (rail, hover, cursor, links)
 *   signal   #ff4c47   secondary accent (hover shift)
 *
 * @package SignalNoise
 * @since 7.0.x
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// sn_notes_reading_time_for_slug() moved to inc/notes-reading-time.php
// (v10.42.2) so it is available in REST/MCP requests, not just this
// template-route-only renderer. This file still calls it below.

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
	$args = array(
		'post_type'           => 'post',
		'post_status'         => 'publish',
		'posts_per_page'      => sn_notes_per_page(),
		'paged'               => sn_notes_current_page(),
		'orderby'             => 'date',
		'order'               => 'DESC',
		'no_found_rows'       => false, // pagination needs found_posts / max_num_pages
		'ignore_sticky_posts' => true,  // the sticky is floated into the Start-here card, never the list
	);
	// Notes-only by construction (post_type=post = the whole Notes corpus;
	// Pages are never queried here). Add the search term only when present.
	$term = sn_notes_search_term();
	if ( '' !== $term ) {
		$args['s'] = $term;
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

// Under test (tests/notes-pagination.php, tests/notes-search.php,
// tests/notes-topic-reframe.php), the helper functions above are now
// declared; stop here so the render body (which echoes HTML + runs
// WP_Query) doesn't execute. Placement matters: this return MUST be below
// every helper declaration (PHP does not declare a function written after
// a return that runs). Verified empirically during plan authoring.
if ( defined( 'SN_NOTES_RENDER_TEST' ) && SN_NOTES_RENDER_TEST ) {
	return;
}

// ── BEGIN PAGE OUTPUT ──────────────────────────────────────────
$query = sn_notes_query_posts();
$sn_term      = sn_notes_search_term();
$sn_searching = ( '' !== $sn_term );
// Hero meta (browse mode only): corpus-level stats via the testable helper —
// found_posts, not the per-page post_count, so "N entries" is the whole corpus
// on every page; "Last updated" shows only when posts[0] is genuinely newest.
$sn_hero_stats = sn_notes_hero_stats( $query );
$entry_count   = $sn_hero_stats['count'];
$latest_date   = $sn_hero_stats['latest_date'];

// Tag-archive context. $sn_filtered (search OR tag) hides the pillars +
// Start-here card and collapses the top composition to a single column.
$sn_tag_id   = sn_notes_current_tag_id();
$sn_tag      = ( $sn_tag_id > 0 );
$sn_tag_name = '';
if ( $sn_tag ) {
	$sn_tag_obj  = get_queried_object();
	$sn_tag_name = ( $sn_tag_obj && isset( $sn_tag_obj->name ) ) ? $sn_tag_obj->name : '';
}
$sn_filtered      = $sn_searching || $sn_tag;
$sn_start_here_id = $sn_filtered ? 0 : sn_notes_start_here_id();

// The stickied note is floated to the TOP of the index (page 1) and is
// excluded from $query (post__not_in) so it never appears twice; page 2+ does
// not repeat it — standard WP sticky semantics, which a secondary WP_Query
// does not provide on its own. Add it back into the displayed corpus count.
$sn_pin_on_page1 = ( $sn_start_here_id > 0 && sn_notes_current_page() <= 1 );
if ( $sn_start_here_id > 0 ) {
	$entry_count += 1;
}

// The router only short-circuits a tag archive for a REAL term, so force
// HTTP 200 here — even a valid-but-empty tag archive should be 200, not the
// 404 WP may have committed before template_redirect (WORDPRESS-REFERENCE #40).
if ( $sn_tag && function_exists( 'status_header' ) ) {
	status_header( 200 );
}

// PRE-RENDER the header and footer template parts so their block-
// layout CSS (e.g. `.wp-container-core-group-is-layout-... { flex-
// wrap, justify-content, … }`) gets registered with WP_Style_Engine
// BEFORE wp_head() runs. Without this two-pass, the layout styles
// for the .sn-header / .sn-footer flex containers are queued AFTER
// wp_head() has already printed its stylesheet — they end up nowhere
// in the document, and the header nav packs left instead of right
// (no space-between), the footer copyright packs left instead of
// right, etc. Output buffer captures the markup; WP's style-engine
// receives the side effects.
//
// (Historical note: through v9.7.0 the header also carried a core/search
// block whose script-module enqueue made this pre-render doubly load-
// bearing. v9.8.0 removed that block — search now lives in the /notes
// archive below, not the header — so the pre-render is now justified by
// the block-layout CSS alone. The pass MUST still run BEFORE wp_head().)
ob_start();
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- do_blocks() output is trusted rendered block HTML (the theme's own header template part); escaping it would corrupt the markup. Captured here only to register block-layout styles before wp_head().
echo do_blocks( '<!-- wp:template-part {"slug":"header","area":"header"} /-->' );
$sn_header_html = ob_get_clean();

ob_start();
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- do_blocks() output is trusted rendered block HTML (the theme's own footer template part); must not be escaped.
echo do_blocks( '<!-- wp:template-part {"slug":"footer","area":"footer"} /-->' );
$sn_footer_html = ob_get_clean();
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php
// The document <title> is emitted by core's _wp_render_title_tag() during
// wp_head() below — inc/setup.php registers add_theme_support( 'title-tag' )
// (added for the TSF cutover, v8.5.5), and the value comes from the
// pre_get_document_title filter in inc/page-notes-template.php. Before v9.5.1
// this renderer ALSO echoed a manual <title> here (a leftover from before
// title-tag support existed), which produced a DUPLICATE <title> on /notes.
wp_head();
?>
<style>
/* ──────────────────────────────────────────────────────────────
   /notes — INDUSTRIAL CATALOG
   Inlined so the rendering and the styles ship together as one
   file. If this file deploys, the whole page deploys.
   ────────────────────────────────────────────────────────────── */

/* The site's fixed `.sn-footer` (z-index 9990, ~76px desktop /
   ~120px mobile) sits over the bottom of the viewport. <main>
   needs enough padding-bottom to clear it — the global rule
   `main.wp-block-group { padding-bottom: 140px }` doesn't apply
   here because our <main> uses .sn-notes-page, not the block-
   group class. So we set our own clearance: 160px gives the
   feed footer breathing room above the fixed bar on every
   viewport. */
.sn-notes-page {
	padding: clamp(2rem, 5vw, 4.5rem) clamp(1.25rem, 3vw, 3rem) 160px;
	max-width: 1180px;
	margin: 0 auto;
}

/* TOP COMPOSITION ─────────────────────────────────────────────
   Hero (left) + pillar essays (right) on desktop. Stacks
   vertically below the breakpoint. align-items: start so the
   "Notes." headline anchors the top-left and the pillar cards
   begin at the same baseline on the right. */

.sn-notes-top {
	display: grid;
	grid-template-columns: 1fr;
	gap: clamp(2.5rem, 5vw, 4rem);
	margin-bottom: clamp(2rem, 4vw, 3rem);
}
@media (min-width: 980px) {
	.sn-notes-top {
		grid-template-columns: 5fr 7fr;
		gap: clamp(3rem, 6vw, 5rem);
		align-items: start;
	}
}

/* HERO ────────────────────────────────────────────────────────── */

.sn-notes-hero {
	margin-bottom: 0; /* gap handled by .sn-notes-top */
}
.sn-notes-eyebrow,
.sn-notes-meta,
.sn-notes-section-label {
	font-family: 'DM Mono', 'Courier New', monospace;
	font-size: 0.7rem;
	letter-spacing: 0.18em;
	text-transform: uppercase;
	color: var(--wp--preset--color--rust, #666);
	margin: 0;
}
.sn-notes-eyebrow {
	color: var(--wp--preset--color--blood, #e00404);
	margin-bottom: 1rem;
}
.sn-notes-headline {
	font-family: 'Bebas Neue', Impact, sans-serif;
	font-weight: 400;
	font-size: clamp(4rem, 14vw, 11rem);
	line-height: 0.85;
	letter-spacing: -0.02em;
	margin: 0 0 1.25rem;
	color: var(--wp--preset--color--bone, #000);
}
@media (min-width: 980px) {
	/* Hero now lives in a ~5fr column. Cap the headline so
	   "Notes." stays inside the column at desktop widths
	   (Bebas Neue at 11rem would overflow a ~480px column). */
	.sn-notes-headline {
		font-size: clamp(5rem, 9vw, 8.5rem);
	}
}
.sn-notes-dek {
	font-size: clamp(1rem, 1.4vw, 1.15rem);
	line-height: 1.55;
	max-width: 48ch;
	color: var(--wp--preset--color--rust, #666);
	margin: 0 0 1.5rem;
}
.sn-notes-meta {
	display: flex;
	gap: 1rem;
	flex-wrap: wrap;
}
.sn-notes-meta-bullet {
	color: var(--wp--preset--color--blood, #e00404);
}

/* RULE — section divider, full-width hairline */

.sn-notes-rule {
	border: 0;
	border-top: 1px solid var(--wp--preset--color--concrete, #d9d9d9);
	margin: clamp(2rem, 4vw, 3.5rem) 0;
}

/* SECTION LABEL — chapter heading on a hairline.
   Kept in rust grey (not bone black) so it reads as a quiet
   meta-marker rather than competing with content for attention.
   The heading-of-a-section role is carried by the hairline +
   placement, not by the type weight. */

.sn-notes-section-wrap {
	display: grid;
	grid-template-columns: 1fr auto;
	align-items: end;
	gap: 1rem;
	margin-bottom: clamp(1.5rem, 3vw, 2.5rem);
	padding-bottom: 0.5rem;
	border-bottom: 1px solid var(--wp--preset--color--concrete, #d9d9d9);
}
.sn-notes-section-label {
	font-size: max(0.7rem, 11px);
	color: var(--wp--preset--color--rust, #666);
}
.sn-notes-section-count {
	font-family: 'DM Mono', 'Courier New', monospace;
	font-size: max(0.7rem, 11px);
	letter-spacing: 0.18em;
	text-transform: uppercase;
	color: var(--wp--preset--color--rust, #666);
}

/* PILLAR ESSAYS ──────────────────────────────────────────────── */

.sn-notes-pillars {
	display: grid;
	grid-template-columns: 1fr;
	gap: clamp(0.75rem, 1.5vw, 1rem);
}
/* Pillar cards stay in a single column even when the hero+pillars
   composition splits (≥980px) — they live in the right-hand cell
   and stack vertically there, paired with the hero on the left. */

/* Pillar cards live BESIDE the hero, not below it as a hero-equivalent
   feature. The hero already carries the page's identity — these cards
   should feel ELEVATED but not OVERPOWERING. Compact treatment so the
   notes index below doesn't feel relegated. */

.sn-notes-pillar {
	position: relative;
	display: grid;
	grid-template-columns: 48px 1fr;
	gap: 0;
	background: var(--wp--preset--color--asphalt, #f5f5f5);
	color: var(--wp--preset--color--bone, #000);
	text-decoration: none;
	overflow: hidden;
	transition: transform 0.35s cubic-bezier(0.2, 0.8, 0.2, 1);
}
.sn-notes-pillar::before {
	/* Left rail — blood accent. Expands on hover. */
	content: '';
	position: absolute;
	inset: 0 auto 0 0;
	width: 4px;
	background: var(--wp--preset--color--blood, #e00404);
	transition: width 0.35s cubic-bezier(0.2, 0.8, 0.2, 1);
}
.sn-notes-pillar:hover::before {
	width: 10px;
}
.sn-notes-pillar:hover {
	transform: translateX(2px);
}
.sn-notes-pillar-number {
	font-family: 'DM Mono', 'Courier New', monospace;
	font-size: clamp(0.95rem, 1.4vw, 1.15rem);
	color: var(--wp--preset--color--blood, #e00404);
	padding: clamp(1.1rem, 2vw, 1.4rem) 0 0 1.1rem;
	letter-spacing: 0.05em;
	font-weight: 500;
}
.sn-notes-pillar-body {
	padding: clamp(1.1rem, 2vw, 1.4rem) clamp(1.25rem, 2.5vw, 1.6rem) clamp(1.1rem, 2vw, 1.4rem) 0;
}
.sn-notes-pillar-eyebrow {
	font-family: 'DM Mono', 'Courier New', monospace;
	font-size: 0.7rem;
	letter-spacing: 0.18em;
	text-transform: uppercase;
	color: var(--wp--preset--color--blood, #e00404);
	margin: 0 0 0.5rem;
}
.sn-notes-pillar-title {
	font-family: 'Bebas Neue', Impact, sans-serif;
	font-weight: 400;
	font-size: clamp(1.4rem, 2.4vw, 1.9rem);
	line-height: 1;
	letter-spacing: -0.005em;
	margin: 0 0 0.65rem;
	color: var(--wp--preset--color--bone, #000);
}
.sn-notes-pillar-dek {
	font-size: clamp(0.85rem, 1vw, 0.95rem);
	line-height: 1.5;
	color: var(--wp--preset--color--rust, #666);
	margin: 0 0 0.85rem;
	max-width: 42ch;
}
.sn-notes-pillar-cta {
	font-family: 'DM Mono', 'Courier New', monospace;
	font-size: max(0.75rem, 11px);
	letter-spacing: 0.18em;
	text-transform: uppercase;
	color: var(--wp--preset--color--bone, #000);
	text-decoration: none;
	display: inline-flex;
	align-items: center;
	gap: 0.5rem;
	min-height: 44px;
	padding: 0.6rem 0;
	transition: color 0.2s ease, gap 0.2s ease;
}
.sn-notes-pillar-cta::after {
	content: '→';
	display: inline-block;
	transition: transform 0.2s ease;
}
.sn-notes-pillar-cta:hover {
	color: var(--wp--preset--color--blood, #e00404);
}
.sn-notes-pillar-cta:hover::after {
	transform: translateX(4px);
}

/* NOTES INDEX — tabular ──────────────────────────────────────── */

.sn-notes-index-list {
	list-style: none;
	margin: 0;
	padding: 0;
	counter-reset: sn-note-counter;
}
.sn-notes-row {
	display: grid;
	grid-template-columns: 1fr;
	gap: 0.5rem;
	padding: clamp(1rem, 2vw, 1.5rem) 0;
	border-bottom: 1px solid var(--wp--preset--color--concrete, #d9d9d9);
	transition: padding 0.2s ease;
}
.sn-notes-row:last-child {
	border-bottom: 0;
}
@media (min-width: 720px) {
	.sn-notes-row {
		grid-template-columns: 140px 1fr;
		gap: 2rem;
		align-items: start;
	}
}
.sn-notes-row:hover {
	padding-left: 6px;
}

.sn-notes-row-spec {
	font-family: 'DM Mono', 'Courier New', monospace;
	font-size: 0.75rem;
	letter-spacing: 0.14em;
	text-transform: uppercase;
	color: var(--wp--preset--color--rust, #666);
	display: flex;
	flex-wrap: wrap;
	gap: 0.5rem;
	align-items: baseline;
	transition: color 0.2s ease;
}
@media (min-width: 720px) {
	.sn-notes-row-spec {
		flex-direction: column;
		gap: 0.4rem;
	}
}
.sn-notes-row-date {
	color: var(--wp--preset--color--bone, #000);
	font-weight: 500;
}
.sn-notes-row-rt {
	color: var(--wp--preset--color--rust, #666);
}
.sn-notes-row:hover .sn-notes-row-date {
	color: var(--wp--preset--color--blood, #e00404);
}

.sn-notes-row-content {
	min-width: 0; /* allow text to wrap inside grid cell */
}
.sn-notes-row-title {
	font-family: 'Bebas Neue', Impact, sans-serif;
	font-weight: 400;
	font-size: clamp(1.5rem, 2.4vw, 2rem);
	line-height: 1.05;
	letter-spacing: -0.005em;
	margin: 0 0 0.6rem;
}
.sn-notes-row-title a {
	color: var(--wp--preset--color--bone, #000);
	text-decoration: none;
	background-image: linear-gradient(currentColor, currentColor);
	background-position: 0 100%;
	background-repeat: no-repeat;
	background-size: 0 1px;
	transition: background-size 0.3s ease, color 0.2s ease;
	padding-bottom: 2px;
}
.sn-notes-row-title a:hover {
	color: var(--wp--preset--color--blood, #e00404);
	background-size: 100% 1px;
}
.sn-notes-row-excerpt {
	font-size: 0.95rem;
	line-height: 1.6;
	color: var(--wp--preset--color--rust, #666);
	margin: 0;
	max-width: 60ch;
}

/* Scoped to .sn-notes-page so this inline rule deterministically wins over
   the global components.css .sn-notes-empty (specificity 0,2,0 > 0,1,0),
   not by source order alone. */
.sn-notes-page .sn-notes-empty {
	font-family: 'DM Mono', 'Courier New', monospace;
	font-size: 0.85rem;
	letter-spacing: 0.1em;
	text-transform: uppercase;
	color: var(--wp--preset--color--rust, #666);
	padding: 2rem 0;
}

/* SEARCH FORM ─────────────────────────────────────────────────
   Hand-rolled (no core/search → no .wp-element-button chrome, so no
   black-pill "blob"). Thin underline field in the catalog vocabulary:
   bone text, rust uppercase placeholder, blood on submit hover. */
.sn-notes-search {
	display: flex;
	align-items: center;
	gap: 0.5rem;
	margin-bottom: clamp(1.5rem, 3vw, 2.25rem);
	border-bottom: 1px solid var(--wp--preset--color--concrete, #d9d9d9);
	transition: border-color 0.2s ease;
}
.sn-notes-search:focus-within {
	border-bottom-color: var(--wp--preset--color--bone, #000);
}
.sn-notes-search input[type="search"] {
	flex: 1 1 auto;
	min-width: 0;
	border: 0;
	background: transparent;
	padding: 0.65rem 0;
	font-family: 'DM Mono', 'Courier New', monospace;
	font-size: max(0.9rem, 12px);
	letter-spacing: 0.04em;
	color: var(--wp--preset--color--bone, #000);
	-webkit-appearance: none;
	appearance: none;
}
.sn-notes-search input[type="search"]:focus {
	outline: none;
}
/* Restore a real keyboard ring: mouse focus stays ring-less (rule above),
   but :focus-visible re-adds the brand 2px blood ring for keyboard nav —
   the theme's global focus-visible list (base.css) doesn't cover
   input[type="search"]. WCAG 2.4.7. */
.sn-notes-search input[type="search"]:focus-visible {
	outline: 2px solid var(--wp--preset--color--blood, #e00404);
	outline-offset: 3px;
}
/* Strip the UA search-clear "✕" + decoration so the brutalist field stays a
   clean underline on WebKit/Blink. */
.sn-notes-search input[type="search"]::-webkit-search-cancel-button,
.sn-notes-search input[type="search"]::-webkit-search-decoration {
	-webkit-appearance: none;
	appearance: none;
}
.sn-notes-search input[type="search"]::placeholder {
	color: var(--wp--preset--color--rust, #666);
	text-transform: uppercase;
	letter-spacing: 0.16em;
	font-size: 0.78em;
}
.sn-notes-search button {
	flex: 0 0 auto;
	border: 0;
	background: transparent;
	padding: 0.75rem 0.6rem;
	min-height: 44px;
	display: inline-flex;
	align-items: center;
	justify-content: center;
	cursor: pointer;
	color: var(--wp--preset--color--bone, #000);
	font-family: 'DM Mono', 'Courier New', monospace;
	font-size: 1.15rem;
	line-height: 1;
	transition: color 0.2s ease, transform 0.2s ease;
}
.sn-notes-search button:hover,
.sn-notes-search button:focus {
	color: var(--wp--preset--color--blood, #e00404);
	transform: translateX(2px);
}

/* Search state: hero spans full width (pillars hidden). Specificity
   (0,2,0) beats the .sn-notes-top media rule (0,1,0) at all widths. */
.sn-notes-top.is-search {
	grid-template-columns: 1fr;
}

/* Clear link replaces the count in the section header during search. */
.sn-notes-section-clear {
	font-family: 'DM Mono', 'Courier New', monospace;
	font-size: max(0.7rem, 11px);
	letter-spacing: 0.18em;
	text-transform: uppercase;
	color: var(--wp--preset--color--rust, #666);
	text-decoration: none;
	transition: color 0.2s ease;
}
.sn-notes-section-clear:hover,
.sn-notes-section-clear:focus {
	color: var(--wp--preset--color--blood, #e00404);
}

/* Result-count line under the search header. */
.sn-notes-search-summary {
	font-family: 'DM Mono', 'Courier New', monospace;
	font-size: max(0.7rem, 11px);
	letter-spacing: 0.18em;
	text-transform: uppercase;
	color: var(--wp--preset--color--rust, #666);
	margin: 0 0 clamp(1.25rem, 2.5vw, 1.75rem);
}

/* Visually-hidden label. Scoped + self-contained — /notes inlines all
   its own CSS, so we don't rely on a global .screen-reader-text. */
.sn-notes-page .screen-reader-text {
	position: absolute !important;
	width: 1px;
	height: 1px;
	padding: 0;
	margin: -1px;
	overflow: hidden;
	clip: rect(0, 0, 0, 0);
	white-space: nowrap;
	border: 0;
}

/* SUBSCRIBE NOTE — compact colophon nested in the hero column.
   Tertiary in the hero hierarchy (title > dek > meta > subscribe).
   Same DM Mono / 0.7rem / uppercase as eyebrow + meta; inline links
   in blood. Cursor terminates the sentence as a "live system" beat
   inherited from the previous footer aesthetic. */

.sn-notes-subscribe {
	margin: 1.25rem 0 0;
	font-family: 'DM Mono', 'Courier New', monospace;
	font-size: 0.7rem;
	letter-spacing: 0.18em;
	text-transform: uppercase;
	line-height: 1.7;
	color: var(--wp--preset--color--rust, #666);
	max-width: 48ch;
}
.sn-notes-subscribe a {
	color: var(--wp--preset--color--blood, #e00404);
	text-decoration: none;
	border-bottom: 1px solid transparent;
	transition: border-color 0.2s ease;
}
.sn-notes-subscribe a:hover {
	border-bottom-color: var(--wp--preset--color--blood, #e00404);
}
.sn-notes-cursor {
	display: inline-block;
	width: 0.4em;
	height: 0.95em;
	background: var(--wp--preset--color--blood, #e00404);
	margin-left: 0.4em;
	vertical-align: -0.1em;
	animation: sn-blink 1.05s steps(2, end) infinite;
}
@keyframes sn-blink {
	from, 49.999% { opacity: 1; }
	50%, to       { opacity: 0; }
}

/* PAGE ENTRY ANIMATION — staggered reveal on first paint */

.sn-notes-page > * {
	animation: sn-rise 0.55s cubic-bezier(0.2, 0.7, 0.2, 1) backwards;
}
.sn-notes-page > *:nth-child(1) { animation-delay: 0.05s; }
.sn-notes-page > *:nth-child(2) { animation-delay: 0.12s; }
.sn-notes-page > *:nth-child(3) { animation-delay: 0.18s; }
.sn-notes-page > *:nth-child(4) { animation-delay: 0.24s; }
.sn-notes-page > *:nth-child(5) { animation-delay: 0.30s; }
.sn-notes-page > *:nth-child(6) { animation-delay: 0.36s; }
@keyframes sn-rise {
	from { opacity: 0; transform: translateY(12px); }
	to   { opacity: 1; transform: translateY(0); }
}
@media (prefers-reduced-motion: reduce) {
	.sn-notes-page > * { animation: none; }
	.sn-notes-cursor { animation: none; opacity: 0.6; }
	.sn-notes-pillar { transition: none; }
	.sn-notes-pillar::before { transition: none; }
	.sn-notes-row { transition: none; }
	.sn-notes-search { transition: none; }
	.sn-notes-search button { transition: none; transform: none; }
}

/* PINNED ROW — the stickied "Start here" note floated to the top of the
   index (page 1). The blood "Start here" label in the spec column signals the
   out-of-date-order placement is intentional (a sticky), not a sort bug; the
   row is otherwise an ordinary .sn-notes-row. */
.sn-notes-row-pin {
	color: var(--wp--preset--color--blood, #e00404);
	font-weight: 500;
}

/* PAGINATION — numbered control below the notes index.
   paginate_links() emits <a>/<span> with .current on the active page.
   DM Mono numerals + 11px floor, matching the catalog vocabulary. No
   animated transitions → nothing to gate under prefers-reduced-motion.
   (Active/hover use `bone` #000 — `void` is the white page background.) */
.sn-notes-pagination {
	display: flex;
	flex-wrap: wrap;
	gap: 0.75rem;
	align-items: center;
	justify-content: center;
	margin-top: clamp(2rem, 5vw, 3.5rem);
	font-family: 'DM Mono', 'Courier New', monospace;
	font-size: max(0.8rem, 12px);
	letter-spacing: 0.1em;
}
.sn-notes-pagination a,
.sn-notes-pagination span {
	color: var(--wp--preset--color--rust, #666);
	text-decoration: none;
	padding: 0.25rem 0.5rem;
	min-width: 1.5rem;
	text-align: center;
}
.sn-notes-pagination a:hover,
.sn-notes-pagination a:focus {
	color: var(--wp--preset--color--bone, #000);
	text-decoration: underline;
	text-underline-offset: 0.25em;
}
.sn-notes-pagination .current {
	color: var(--wp--preset--color--bone, #000);
	font-weight: 700;
}
</style>
</head>
<body <?php body_class( 'sn-notes-body' ); ?>>
<?php wp_body_open(); ?>

<?php
// Header was pre-rendered above (before wp_head ran) so the block-
// layout styles registered correctly. Now we just echo the captured
// HTML.
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $sn_header_html is trusted do_blocks() output captured above; must not be re-escaped.
echo $sn_header_html;
?>

<main class="sn-notes-page" id="wp--skip-link--target">

	<div class="sn-notes-top<?php echo $sn_filtered ? ' is-search' : ''; ?>">

		<header class="sn-notes-hero">
			<p class="sn-notes-eyebrow"><?php if ( $sn_tag ) : ?>Topic &middot; <?php echo esc_html( $sn_tag_name ); ?><?php else : ?>Index &middot; <?php echo esc_html( wp_date( 'Y' ) ); ?><?php endif; ?></p>
			<h1 class="sn-notes-headline">Notes.</h1>
			<p class="sn-notes-dek">Working notes on music, AI, and the infrastructure underneath. Written when there&rsquo;s something worth writing.</p>
			<?php if ( ! $sn_filtered ) : ?>
			<?php // Corpus stats: entry count + last-updated. Suppressed in
			      // search/tag state — there $entry_count is the filtered result
			      // count and $latest_date is the newest match, so "entries"/
			      // "Last updated" would mislabel them (the count lives in the
			      // summary line below). Hero stays as page identity only. ?>
			<p class="sn-notes-meta">
				<span><?php echo esc_html( sprintf( _n( '%d entry', '%d entries', $entry_count, 'signal-noise' ), $entry_count ) ); ?></span>
				<?php if ( $latest_date ) : ?>
					<span class="sn-notes-meta-bullet" aria-hidden="true">&middot;</span>
					<span>Last updated <?php echo esc_html( $latest_date ); ?></span>
				<?php endif; ?>
			</p>
			<?php endif; ?>
			<p class="sn-notes-subscribe">
				No subscription form. No schedule. Notes via <a href="/notes/feed/">RSS</a>, or via email through <a href="https://blogtrottr.com/" target="_blank" rel="noopener noreferrer" data-sn-subscribe="email">Blogtrottr</a> or <a href="https://www.feedrabbit.com/" target="_blank" rel="noopener noreferrer" data-sn-subscribe="email">Feedrabbit</a>.<span class="sn-notes-cursor" aria-hidden="true"></span>
			</p>
		</header>

		<?php if ( ! $sn_filtered ) : ?>
		<?php
		// v10.46.0: the rail derives from sn_theme_pillar_descriptors() —
		// published /provenance/ child Pages — instead of hardcoded markup
		// (which held a new essay invisible until a theme release). The
		// eyebrow deliberately carries NO month: the Page dates are CMS-flip
		// artifacts, and printing them would fabricate essay dates.
		$sn_pillars = function_exists( 'sn_theme_pillar_descriptors' ) ? sn_theme_pillar_descriptors() : array();
		?>
		<?php if ( $sn_pillars ) : ?>
		<section class="sn-notes-pillars-section" aria-labelledby="sn-pillars-heading">
			<div class="sn-notes-section-wrap">
				<p class="sn-notes-section-label" id="sn-pillars-heading">Pillar Essays &mdash; Featured</p>
				<span class="sn-notes-section-count"><?php echo esc_html( sprintf( '%02d / %02d', count( $sn_pillars ), count( $sn_pillars ) ) ); ?></span>
			</div>

			<div class="sn-notes-pillars">
				<?php foreach ( $sn_pillars as $sn_pillar_i => $sn_pillar ) : ?>
				<article class="sn-notes-pillar">
					<span class="sn-notes-pillar-number" aria-hidden="true">&#8470; <?php echo esc_html( sprintf( '%02d', $sn_pillar_i + 1 ) ); ?></span>
					<div class="sn-notes-pillar-body">
						<p class="sn-notes-pillar-eyebrow">Pillar Essay &middot; <?php echo esc_html( sn_notes_reading_time_for_slug( $sn_pillar['slug'] ) ); ?></p>
						<h2 class="sn-notes-pillar-title"><?php echo esc_html( $sn_pillar['title'] ); ?></h2>
						<?php if ( '' !== $sn_pillar['dek'] ) : ?>
						<p class="sn-notes-pillar-dek"><?php echo esc_html( $sn_pillar['dek'] ); ?></p>
						<?php endif; ?>
						<a class="sn-notes-pillar-cta" href="<?php echo esc_url( '/' . $sn_pillar['slug'] . '/' ); ?>">Read essay</a>
					</div>
				</article>
				<?php endforeach; ?>
			</div>
		</section>
		<?php endif; ?>
		<?php endif; ?>

	</div>

	<?php if ( ! $sn_searching ) : ?>
	<hr class="sn-notes-rule" aria-hidden="true">
	<?php endif; ?>

	<section class="sn-notes-index-section" aria-labelledby="sn-index-heading">

		<form class="sn-notes-search" role="search" method="get" action="<?php echo esc_url( home_url( '/notes/' ) ); ?>">
			<label class="screen-reader-text" for="sn-notes-q">Search notes</label>
			<input type="search" id="sn-notes-q" name="s" value="<?php echo esc_attr( $sn_term ); ?>" placeholder="Search notes" autocomplete="off" />
			<button type="submit" aria-label="Search"><span aria-hidden="true">&rarr;</span></button>
		</form>

		<div class="sn-notes-section-wrap">
			<?php if ( $sn_searching ) : ?>
				<p class="sn-notes-section-label" id="sn-index-heading">Notes &mdash; Search &middot; &ldquo;<?php echo esc_html( $sn_term ); ?>&rdquo;</p>
				<a class="sn-notes-section-clear" href="<?php echo esc_url( home_url( '/notes/' ) ); ?>">Clear &times;</a>
			<?php elseif ( $sn_tag ) : ?>
				<p class="sn-notes-section-label" id="sn-index-heading">Notes &mdash; Tag &middot; &ldquo;<?php echo esc_html( $sn_tag_name ); ?>&rdquo;</p>
				<a class="sn-notes-section-clear" href="<?php echo esc_url( home_url( '/notes/' ) ); ?>">All notes</a>
			<?php else : ?>
				<p class="sn-notes-section-label" id="sn-index-heading">Notes &mdash; Index</p>
				<span class="sn-notes-section-count"><?php echo esc_html( sprintf( '%02d', (int) $entry_count ) ); ?></span>
			<?php endif; ?>
		</div>

		<?php if ( $sn_searching ) : ?>
			<p class="sn-notes-search-summary"><?php echo esc_html( sprintf( _n( '%d note found', '%d notes found', (int) $query->found_posts, 'signal-noise' ), (int) $query->found_posts ) ); ?></p>
		<?php elseif ( $sn_tag ) : ?>
			<p class="sn-notes-search-summary"><?php echo esc_html( sprintf( _n( '%d note tagged', '%d notes tagged', (int) $query->found_posts, 'signal-noise' ), (int) $query->found_posts ) ); ?></p>
		<?php endif; ?>

		<?php if ( $query->have_posts() || $sn_pin_on_page1 ) : ?>
			<ol class="sn-notes-index-list">
			<?php
			// PINNED ROW — the stickied note, floated to the top of page 1. Same
			// .sn-notes-row markup as the chronological rows; a blood "Start here"
			// label flags that the out-of-date-order position is intentional.
			if ( $sn_pin_on_page1 ) :
				$sn_pin_post = get_post( $sn_start_here_id );
				if ( $sn_pin_post ) :
			?>
				<li class="sn-notes-row is-pinned">
					<div class="sn-notes-row-spec" aria-hidden="false">
						<span class="sn-notes-row-pin">Start here</span>
						<time class="sn-notes-row-date" datetime="<?php echo esc_attr( get_the_date( 'c', $sn_pin_post ) ); ?>"><?php echo sn_notes_render_date( $sn_pin_post ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper returns esc_html()'d output; escaping again would double-encode. ?></time>
						<span class="sn-notes-row-rt"><?php echo esc_html( sn_notes_render_reading_time( $sn_pin_post->ID ) ); ?></span>
					</div>
					<div class="sn-notes-row-content">
						<h3 class="sn-notes-row-title"><a href="<?php echo esc_url( get_permalink( $sn_pin_post ) ); ?>"><?php echo esc_html( get_the_title( $sn_pin_post ) ); ?></a></h3>
						<?php $sn_pin_excerpt = get_the_excerpt( $sn_pin_post ); if ( $sn_pin_excerpt ) : ?>
							<p class="sn-notes-row-excerpt"><?php echo esc_html( wp_strip_all_tags( $sn_pin_excerpt ) ); ?></p>
						<?php endif; ?>
					</div>
				</li>
			<?php endif; endif; ?>
			<?php while ( $query->have_posts() ) : $query->the_post(); $p = get_post(); ?>
				<li class="sn-notes-row">
					<div class="sn-notes-row-spec" aria-hidden="false">
						<time class="sn-notes-row-date" datetime="<?php echo esc_attr( get_the_date( 'c', $p ) ); ?>"><?php echo sn_notes_render_date( $p ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper returns esc_html()'d output (see sn_notes_render_date); escaping again would double-encode. ?></time>
						<span class="sn-notes-row-rt"><?php echo esc_html( sn_notes_render_reading_time( $p->ID ) ); ?></span>
					</div>
					<div class="sn-notes-row-content">
						<h3 class="sn-notes-row-title"><a href="<?php the_permalink(); ?>"><?php echo esc_html( get_the_title() ); ?></a></h3>
						<?php $excerpt = get_the_excerpt(); if ( $excerpt ) : ?>
							<p class="sn-notes-row-excerpt"><?php echo esc_html( wp_strip_all_tags( $excerpt ) ); ?></p>
						<?php endif; ?>
					</div>
				</li>
			<?php endwhile; wp_reset_postdata(); ?>
			</ol>
		<?php elseif ( $sn_searching ) : ?>
			<p class="sn-notes-empty">No notes match &ldquo;<?php echo esc_html( $sn_term ); ?>&rdquo;.</p>
		<?php elseif ( $sn_tag ) : ?>
			<p class="sn-notes-empty">No notes tagged &ldquo;<?php echo esc_html( $sn_tag_name ); ?>&rdquo;.</p>
		<?php else : ?>
			<p class="sn-notes-empty">No notes published yet. Check back soon.</p>
		<?php endif; ?>

		<?php if ( $query->max_num_pages > 1 ) : ?>
			<nav class="sn-notes-pagination" aria-label="Notes pages">
				<?php
				$sn_notes_links = paginate_links( array(
					'base'      => esc_url_raw( add_query_arg( 'paged', '%#%', sn_notes_pagination_base() ) ),
					'format'    => '',
					'current'   => sn_notes_current_page(),
					'total'     => (int) $query->max_num_pages,
					'add_args'  => sn_notes_pagination_add_args( $sn_term ),
					'type'      => 'array',
					'prev_text' => '&larr;',
					'next_text' => '&rarr;',
					'mid_size'  => 2,
					'end_size'  => 1,
				) );
				if ( is_array( $sn_notes_links ) ) {
					foreach ( $sn_notes_links as $sn_link ) {
						// paginate_links() returns pre-escaped, controlled
						// <a>/<span> markup (WP core helper). Echo as-is;
						// wrapping in esc_html would mangle the anchors.
						echo $sn_link; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- paginate_links() output is trusted WP-core-generated markup.
					}
				}
				?>
			</nav>
		<?php endif; ?>
	</section>

</main>

<?php
// Footer pre-rendered above. Echo the captured HTML.
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $sn_footer_html is trusted do_blocks() output captured above; must not be re-escaped.
echo $sn_footer_html;
?>

<?php wp_footer(); ?>
</body>
</html>
