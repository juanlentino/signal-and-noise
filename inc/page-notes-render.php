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
 * a directory listing for the brand. Tabular note rows in mono with
 * a date+meta column pulled left like a magazine spec line, a
 * terminal-status RSS footer with a blinking cursor. Stays inside
 * the existing brutalist white/asphalt/blood vocabulary but adds
 * editorial precision. (The numbered pillar-essay rail left this
 * index in v10.47.0: it is now the owner-placeable
 * signal-noise/pillar-essays block, see blocks/pillar-essays/.)
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
// The pure helpers this renderer calls (sn_notes_query_posts,
// sn_notes_search_term, sn_notes_hero_stats, …) live in
// inc/notes-index-helpers.php (v10.49.0 extraction; always loaded via
// functions.php BEFORE the template router pulls this file in), and
// sn_notes_reading_time_for_slug() in inc/notes-reading-time.php
// (v10.42.2, available to REST/MCP). This file is now RENDER PATH ONLY:
// including it emits the full /notes HTML document — the old mid-file
// SN_NOTES_RENDER_TEST early return is retired; fixtures require the
// helpers module directly.

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

// Tag-archive context. $sn_filtered (search OR tag) hides the hero corpus
// stats and the Start-here pinned card (both mislabel filtered results).
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
	max-width: 1320px;
	margin: 0 auto;
}

/* HERO ────────────────────────────────────────────────────────
   Resume treatment since v11.4.4: eyebrow kicker spanning the
   grid; headline + dek left; subscribe + corpus-meta stamp
   right; top-aligned (v11.4.2). Single column below 900px. */

.sn-notes-hero {
	margin-bottom: clamp(2rem, 4vw, 3rem);
}
@media (min-width: 900px) {
	.sn-notes-hero {
		display: grid;
		grid-template-columns: minmax(0, 1.15fr) minmax(0, 1fr);
		column-gap: clamp(2.5rem, 6vw, 5rem);
		/* v11.4.2: TOP alignment is the single split-hero rule — with the
		   uniform title scale the side column is taller than the title
		   block, and bottom alignment floated the dek above the eyebrow. */
		align-items: start;
	}
	.sn-notes-hero > .sn-notes-eyebrow {
		grid-column: 1 / -1;
	}
	.sn-notes-hero-side .sn-notes-meta {
		margin-top: 1rem;
	}
}
.sn-notes-eyebrow,
.sn-notes-meta,
.sn-notes-section-label {
	font-family: 'DM Mono', 'Courier New', monospace;
	font-size: max(0.7rem, 11px);
	letter-spacing: 0.18em;
	text-transform: uppercase;
	color: var(--wp--preset--color--rust);
	margin: 0;
}
.sn-notes-eyebrow {
	color: var(--wp--preset--color--blood);
	margin-bottom: 1rem;
}
.sn-notes-headline {
	font-family: 'Bebas Neue', Impact, sans-serif;
	font-weight: 400;
	/* v11.4.1: unified site-wide title scale (owner audit — "some titles
	   are even larger than others"); was clamp(4rem, 14vw, 11rem). */
	font-size: clamp(3rem, 8vw, 7rem);
	line-height: 0.95;
	letter-spacing: -0.02em;
	margin: 0 0 1.25rem;
	color: var(--wp--preset--color--bone);
}
.sn-notes-dek {
	font-size: clamp(1rem, 1.4vw, 1.15rem);
	line-height: 1.55;
	max-width: 48ch;
	color: var(--wp--preset--color--rust);
	margin: 0 0 1.5rem;
}
/* START HERE — hero wayfinding link. Takes the eyebrow's mono/blood treatment
   rather than the dek's prose voice: it is a navigational accent, not a
   sentence, and it should read as a sibling of the eyebrow above it. */
.sn-notes-start-here {
	margin: 0;
}
/* v11.9.4: promoted from a quiet inline link to a bordered target. A newcomer
   arriving from a shared link lands on ONE note and leaves; the route into the
   argument has to compete with 32 rows of index below it, and an 11px inline
   link does not. Boxed and inverting on hover — stark, no radius, no gradient:
   emphasis by contrast rather than by decoration. */
.sn-notes-start-here a {
	display: inline-flex;
	align-items: baseline;
	gap: 0.55em;
	font-family: 'DM Mono', 'Courier New', monospace;
	font-size: max(0.8rem, 13px);
	letter-spacing: 0.16em;
	text-transform: uppercase;
	color: var(--wp--preset--color--blood);
	text-decoration: none;
	padding: 0.7rem 1.1rem;
	border: 1px solid var(--wp--preset--color--blood);
	transition: background-color 150ms ease, color 150ms ease;
}
.sn-notes-start-here a:hover,
.sn-notes-start-here a:focus-visible {
	background-color: var(--wp--preset--color--blood);
	color: var(--wp--preset--color--void);
}
.sn-notes-start-here-arrow {
	/* gap owns the spacing now */
}
@media (prefers-reduced-motion: reduce) {
	.sn-notes-start-here a {
		transition: none;
	}
}
.sn-notes-meta {
	display: flex;
	gap: 1rem;
	flex-wrap: wrap;
}
.sn-notes-meta-bullet {
	color: var(--wp--preset--color--blood);
}

/* RULE — section divider, full-width hairline */

.sn-notes-rule {
	border: 0;
	border-top: 1px solid var(--wp--preset--color--concrete);
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
	border-bottom: 1px solid var(--wp--preset--color--concrete);
}
.sn-notes-section-label {
	font-size: max(0.7rem, 11px);
	color: var(--wp--preset--color--rust);
}
.sn-notes-section-count {
	font-family: 'DM Mono', 'Courier New', monospace;
	font-size: max(0.7rem, 11px);
	letter-spacing: 0.18em;
	text-transform: uppercase;
	color: var(--wp--preset--color--rust);
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
	gap: 0.25rem;
	/* v11.10.0 — MANIFEST DENSITY. Was clamp(1rem, 2vw, 1.5rem), which with a
	   4-line excerpt made every row ~250px and buried the one thing that
	   carries the argument: the titles, read in sequence. */
	padding: 0.6rem 0;
	border-bottom: 1px solid var(--wp--preset--color--concrete);
	transition: background-color 0.2s ease;
}
.sn-notes-row:last-child {
	border-bottom: 0;
}
@media (min-width: 720px) {
	.sn-notes-row {
		/* date | title | meta — a ledger line, not a card. */
		grid-template-columns: 108px minmax(0, 1fr) auto;
		grid-template-areas:
			"spec title meta"
			".    excerpt excerpt";
		gap: 0 1.5rem;
		align-items: baseline;
	}
}

/* ── BELOW 720px THE NAMED AREAS DO NOT EXIST ───────────────────────────────
 *
 * `grid-template-areas` is declared ONLY in the query above, but the four
 * children below carry `grid-area: spec|title|meta|excerpt` unconditionally.
 * On a phone the row is a single `1fr` column with no areas defined, so every
 * one of those names is unresolvable — the browser invents implicit lines and
 * drops all four children into the SAME cell.
 *
 * Measured live on 375x812, /notes, 2026-08-19: computed
 * `grid-template-columns: 0px 0px 327px`, and the date, title, meta and
 * excerpt all reported an identical top and left. The date printed on top of
 * the title on every row in the index; the page was unreadable on a phone.
 *
 * Resetting the four to `auto` lets them flow down the single column the base
 * rule already intends. The desktop layout is untouched.
 * ────────────────────────────────────────────────────────────────────────── */
@media (max-width: 719px) {
	.sn-notes-row-spec,
	.sn-notes-row-content,
	.sn-notes-row-meta,
	.sn-notes-row-excerpt-wrap {
		grid-area: auto;
	}

	/* The tags are hidden at this width by design; their separators were not,
	   so the meta line read "03 MIN · ·" with orphan dots after every note. */
	.sn-notes-row-meta .sn-notes-row-sep {
		display: none;
	}
}
/* The hover affordance moves from padding (which reflowed the row) to a
   background wash + the blood date already established as this list's idiom. */
.sn-notes-row:hover,
.sn-notes-row:focus-within {
	background-color: color-mix(in srgb, var(--wp--preset--color--concrete) 28%, transparent);
}

.sn-notes-row-spec {
	grid-area: spec;
	font-family: 'DM Mono', 'Courier New', monospace;
	font-size: 0.6875rem; /* 11px floor */
	letter-spacing: 0.12em;
	text-transform: uppercase;
	color: var(--wp--preset--color--rust);
	display: flex;
	flex-wrap: wrap;
	gap: 0.5rem;
	align-items: baseline;
	transition: color 0.2s ease;
}
.sn-notes-row-date {
	color: var(--wp--preset--color--bone);
	font-weight: 500;
	white-space: nowrap;
}
.sn-notes-row:hover .sn-notes-row-date,
.sn-notes-row:focus-within .sn-notes-row-date {
	color: var(--wp--preset--color--blood);
}

/* The right-hand stamp: reading time, tags, provenance version. Editorial
   signals only — never traffic or decay, which would publish per-note
   performance and turn a reading list into a leaderboard. */
.sn-notes-row-meta {
	grid-area: meta;
	font-family: 'DM Mono', 'Courier New', monospace;
	font-size: 0.6875rem;
	letter-spacing: 0.12em;
	text-transform: uppercase;
	color: var(--wp--preset--color--rust);
	display: flex;
	flex-wrap: wrap;
	gap: 0.5rem;
	align-items: baseline;
	justify-content: flex-end;
}
.sn-notes-row-meta a {
	color: inherit;
	text-decoration: none;
	border-bottom: 1px solid transparent;
}
.sn-notes-row-meta a:hover,
.sn-notes-row-meta a:focus-visible {
	color: var(--wp--preset--color--bone);
	border-bottom-color: var(--wp--preset--color--blood);
}
.sn-notes-row-sep {
	opacity: 0.5;
}
/* v2+ only: "signed once" is true of nearly every Note and says nothing. */
.sn-notes-row-prov {
	color: var(--wp--preset--color--blood);
	white-space: nowrap;
}
@media (max-width: 719px) {
	.sn-notes-row-meta {
		justify-content: flex-start;
	}
	.sn-notes-row-tags {
		display: none; /* the date + reading time carry the row on a phone */
	}
}

.sn-notes-row-content {
	grid-area: title;
	min-width: 0;
}
.sn-notes-row-title {
	font-family: 'Bebas Neue', Impact, sans-serif;
	font-weight: 400;
	/* Was clamp(1.5rem, 2.4vw, 2rem). The titles are aphorisms that read as a
	   manifesto in sequence, so they stay the loudest thing in the row — but a
	   manifesto is read in a column, not one line per screen. */
	font-size: clamp(1.15rem, 1.5vw, 1.45rem);
	line-height: 1.15;
	letter-spacing: 0.005em;
	margin: 0;
}
.sn-notes-row-title a {
	color: var(--wp--preset--color--bone);
	text-decoration: none;
	background-image: linear-gradient(currentColor, currentColor);
	background-position: 0 100%;
	background-repeat: no-repeat;
	background-size: 0 1px;
	transition: background-size 0.3s ease, color 0.2s ease;
	padding-bottom: 2px;
}
.sn-notes-row-title a:hover,
.sn-notes-row-title a:focus-visible {
	color: var(--wp--preset--color--blood);
	background-size: 100% 1px;
}

/* THE EXCERPT: present in the DOM always (crawlers, AEO and screen readers
   lose nothing), collapsed visually so the titles can be read in sequence.
   grid-template-rows 0fr → 1fr animates the reveal without animating height
   on the element itself, and without the row ever reflowing at rest. */
.sn-notes-row-excerpt-wrap {
	grid-area: excerpt;
	display: grid;
	grid-template-rows: 0fr;
	transition: grid-template-rows 0.28s cubic-bezier(0.16, 1, 0.3, 1);
}
.sn-notes-row:hover .sn-notes-row-excerpt-wrap,
.sn-notes-row:focus-within .sn-notes-row-excerpt-wrap {
	grid-template-rows: 1fr;
}
.sn-notes-row-excerpt {
	overflow: hidden;
	min-height: 0;
	font-size: 0.9rem;
	line-height: 1.55;
	color: var(--wp--preset--color--rust);
	margin: 0;
	max-width: 80ch;
	/* Three lines is a scent, not the note. Clamping bounds how far the rows
	   below can be displaced by a reveal — an unclamped 5-line excerpt shoves
	   thirty rows a quarter-screen down and the list stops feeling stable. */
	display: -webkit-box;
	-webkit-box-orient: vertical;
	-webkit-line-clamp: 3;
	line-clamp: 3;
}
.sn-notes-row:hover .sn-notes-row-excerpt,
.sn-notes-row:focus-within .sn-notes-row-excerpt {
	padding-top: 0.4rem;
}
@media (prefers-reduced-motion: reduce) {
	.sn-notes-row-excerpt-wrap {
		transition: none;
	}
}

/* THE MONTH DIVIDER — quieter than the year band by design. The year band
   DIVIDES (rule, stronger colour); the month band only MARKS where you are, so
   it carries no rule and sits at the same 11px floor in muted rust. Aligned to
   the date column so the eye reads it as part of the ledger's left margin. */
.sn-notes-month-band {
	font-family: 'DM Mono', 'Courier New', monospace;
	font-size: 0.6875rem;
	letter-spacing: 0.16em;
	text-transform: uppercase;
	color: var(--wp--preset--color--rust);
	display: flex;
	align-items: baseline;
	gap: 0.6rem;
	margin: 1.5rem 0 0.15rem;
	opacity: 0.75;
}
.sn-notes-month-band:first-child {
	margin-top: 0.5rem;
}
.sn-notes-month-n {
	color: var(--wp--preset--color--rust);
}
.sn-notes-month-sep {
	opacity: 0.6;
}

details.sn-notes-month > summary {
	cursor: pointer;
	list-style: none;
}
details.sn-notes-month > summary::-webkit-details-marker {
	display: none;
}
details.sn-notes-month > summary .sn-notes-month-band::after {
	content: '\002B';
	margin-left: auto;
}
details.sn-notes-month[open] > summary .sn-notes-month-band::after {
	content: '\2212';
}
details.sn-notes-month > summary:hover .sn-notes-month-band,
details.sn-notes-month > summary:focus-visible .sn-notes-month-band {
	opacity: 1;
	color: var(--wp--preset--color--bone);
}
details.sn-notes-month > summary:focus-visible .sn-notes-month-band {
	outline: 2px solid var(--wp--preset--color--blood);
	outline-offset: 2px;
}

/* THE YEAR SPINE. Renders only when the index spans more than one year — see
   sn_notes_year_spine_is_useful(). Prior years collapse to a single line, so
   the visible page stays bounded no matter how long the corpus runs. */
.sn-notes-year {
	margin: 0 0 2rem;
}
.sn-notes-year-band {
	display: flex;
	align-items: baseline;
	gap: 0.75rem;
	font-family: 'DM Mono', 'Courier New', monospace;
	font-size: 0.6875rem;
	letter-spacing: 0.16em;
	text-transform: uppercase;
	color: var(--wp--preset--color--rust);
	border-bottom: 1px solid var(--wp--preset--color--concrete);
	padding-bottom: 0.5rem;
	margin: 0 0 0.25rem;
}
.sn-notes-year-band .sn-notes-year-n {
	color: var(--wp--preset--color--bone);
	font-weight: 500;
}
details.sn-notes-year > summary {
	cursor: pointer;
	list-style: none;
}
details.sn-notes-year > summary::-webkit-details-marker {
	display: none;
}
details.sn-notes-year > summary .sn-notes-year-band::after {
	content: '\002B'; /* + ; flips to − when open */
	margin-left: auto;
	color: var(--wp--preset--color--rust);
}
details.sn-notes-year[open] > summary .sn-notes-year-band::after {
	content: '\2212';
}
details.sn-notes-year > summary:focus-visible .sn-notes-year-band {
	outline: 2px solid var(--wp--preset--color--blood);
	outline-offset: 2px;
}

/* Scoped to .sn-notes-page so this inline rule deterministically wins over
   the global components.css .sn-notes-empty (specificity 0,2,0 > 0,1,0),
   not by source order alone. */
.sn-notes-page .sn-notes-empty {
	font-family: 'DM Mono', 'Courier New', monospace;
	font-size: 0.85rem;
	letter-spacing: 0.1em;
	text-transform: uppercase;
	color: var(--wp--preset--color--rust);
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
	border-bottom: 1px solid var(--wp--preset--color--concrete);
	transition: border-color 0.2s ease;
}
.sn-notes-search:focus-within {
	border-bottom-color: var(--wp--preset--color--bone);
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
	color: var(--wp--preset--color--bone);
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
	outline: 2px solid var(--wp--preset--color--blood);
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
	color: var(--wp--preset--color--rust);
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
	color: var(--wp--preset--color--bone);
	font-family: 'DM Mono', 'Courier New', monospace;
	font-size: 1.15rem;
	line-height: 1;
	transition: color 0.2s ease, transform 0.2s ease;
}
.sn-notes-search button:hover,
.sn-notes-search button:focus {
	color: var(--wp--preset--color--blood);
	transform: translateX(2px);
}

/* Clear link replaces the count in the section header during search. */
.sn-notes-section-clear {
	font-family: 'DM Mono', 'Courier New', monospace;
	font-size: max(0.7rem, 11px);
	letter-spacing: 0.18em;
	text-transform: uppercase;
	color: var(--wp--preset--color--rust);
	text-decoration: none;
	transition: color 0.2s ease;
}
.sn-notes-section-clear:hover,
.sn-notes-section-clear:focus {
	color: var(--wp--preset--color--blood);
}

/* Result-count line under the search header. */
.sn-notes-search-summary {
	font-family: 'DM Mono', 'Courier New', monospace;
	font-size: max(0.7rem, 11px);
	letter-spacing: 0.18em;
	text-transform: uppercase;
	color: var(--wp--preset--color--rust);
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
	font-size: max(0.7rem, 11px);
	letter-spacing: 0.18em;
	text-transform: uppercase;
	line-height: 1.7;
	color: var(--wp--preset--color--rust);
	max-width: 48ch;
}
.sn-notes-subscribe a {
	color: var(--wp--preset--color--blood);
	text-decoration: none;
	border-bottom: 1px solid transparent;
	transition: border-color 0.2s ease;
}
.sn-notes-subscribe a:hover {
	border-bottom-color: var(--wp--preset--color--blood);
}
.sn-notes-cursor {
	display: inline-block;
	width: 0.4em;
	height: 0.95em;
	background: var(--wp--preset--color--blood);
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
	.sn-notes-row { transition: none; }
	.sn-notes-search { transition: none; }
	.sn-notes-search button { transition: none; transform: none; }
}

/* PINNED ROW — the stickied "Start here" note floated to the top of the
   index (page 1). The blood "Start here" label in the spec column signals the
   out-of-date-order placement is intentional (a sticky), not a sort bug; the
   row is otherwise an ordinary .sn-notes-row. */
.sn-notes-row-pin {
	color: var(--wp--preset--color--blood);
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
	color: var(--wp--preset--color--rust);
	text-decoration: none;
	padding: 0.25rem 0.5rem;
	min-width: 1.5rem;
	text-align: center;
}
.sn-notes-pagination a:hover,
.sn-notes-pagination a:focus {
	color: var(--wp--preset--color--bone);
	text-decoration: underline;
	text-underline-offset: 0.25em;
}
.sn-notes-pagination .current {
	color: var(--wp--preset--color--bone);
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

	<header class="sn-notes-hero">
		<?php // v11.4.4 resume-treatment: eyebrow is a kicker spanning the
		      // grid; dek reads under the title; the side column carries the
		      // subscribe line with the corpus meta as its closing stamp. ?>
		<p class="sn-notes-eyebrow"><?php if ( $sn_tag ) : ?>Topic &middot; <?php echo esc_html( $sn_tag_name ); ?><?php else : ?>Index &middot; <?php echo esc_html( wp_date( 'Y' ) ); ?><?php endif; ?></p>
		<div class="sn-notes-hero-title">
			<h1 class="sn-notes-headline">Notes.</h1>
			<p class="sn-notes-dek">Working notes on music, AI, and the infrastructure underneath. Written when there&rsquo;s something worth writing.</p>
			<?php
			// Start Here is a PAGE under /notes/, so it is absent from the post
			// query that builds the index below — without this link the corpus
			// has no route to its own front door. Rendered unconditionally
			// (unlike the corpus meta, which is filtered-state suppressed):
			// wayfinding is most useful precisely when a newcomer landed on a
			// tag or search view. Resolver returns 0 if the page is gone, so a
			// missing page removes the link rather than serving a 404.
			$sn_start_here_page = sn_notes_start_here_page_id();
			?>
			<?php if ( $sn_start_here_page ) : ?>
				<p class="sn-notes-start-here">
					<a href="<?php echo esc_url( get_permalink( $sn_start_here_page ) ); ?>">First time? Start here<span class="sn-notes-start-here-arrow" aria-hidden="true">&rarr;</span></a>
				</p>
			<?php endif; ?>
		</div>
		<div class="sn-notes-hero-side">
			<p class="sn-notes-subscribe">
				No subscription form. No schedule. Notes via <a href="/notes/subscribe/">RSS</a>, or via email through <a href="https://blogtrottr.com/" target="_blank" rel="noopener noreferrer" data-sn-subscribe="email">Blogtrottr</a> or <a href="https://www.feedrabbit.com/" target="_blank" rel="noopener noreferrer" data-sn-subscribe="email">Feedrabbit</a>.<span class="sn-notes-cursor" aria-hidden="true"></span>
			</p>
			<?php if ( ! $sn_filtered ) : ?>
			<?php // Corpus stats: entry count + last-updated, the side column's
			      // closing stamp. Suppressed in search/tag state — there the
			      // figures describe the filtered result set and would mislabel
			      // the corpus (the count lives in the summary line below). ?>
			<p class="sn-notes-meta">
				<span><?php echo esc_html( sprintf( _n( '%d entry', '%d entries', $entry_count, 'signal-noise' ), $entry_count ) ); ?></span>
				<?php if ( $latest_date ) : ?>
					<span class="sn-notes-meta-bullet" aria-hidden="true">&middot;</span>
					<span>Last updated <?php echo esc_html( $latest_date ); ?></span>
				<?php endif; ?>
			</p>
			<?php endif; ?>
		</div>
	</header>

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
				<p class="sn-notes-section-label" id="sn-index-heading">Notes: Search &middot; &ldquo;<?php echo esc_html( $sn_term ); ?>&rdquo;</p>
				<a class="sn-notes-section-clear" href="<?php echo esc_url( home_url( '/notes/' ) ); ?>">Clear &times;</a>
			<?php elseif ( $sn_tag ) : ?>
				<p class="sn-notes-section-label" id="sn-index-heading">Notes: Tag &middot; &ldquo;<?php echo esc_html( $sn_tag_name ); ?>&rdquo;</p>
				<a class="sn-notes-section-clear" href="<?php echo esc_url( home_url( '/notes/' ) ); ?>">All notes</a>
			<?php else : ?>
				<p class="sn-notes-section-label" id="sn-index-heading">Notes: Index</p>
				<span class="sn-notes-section-count"><?php echo esc_html( sprintf( '%02d', (int) $entry_count ) ); ?></span>
			<?php endif; ?>
		</div>

		<?php if ( $sn_searching ) : ?>
			<p class="sn-notes-search-summary"><?php echo esc_html( sprintf( _n( '%d note found', '%d notes found', (int) $query->found_posts, 'signal-noise' ), (int) $query->found_posts ) ); ?></p>
		<?php elseif ( $sn_tag ) : ?>
			<p class="sn-notes-search-summary"><?php echo esc_html( sprintf( _n( '%d note tagged', '%d notes tagged', (int) $query->found_posts, 'signal-noise' ), (int) $query->found_posts ) ); ?></p>
		<?php endif; ?>

		<?php if ( $query->have_posts() || $sn_pin_on_page1 ) : ?>
			<?php
			// v11.10.0: rows and the year spine render from inc/notes-index-row.php.
			// The pinned Start-here row and the chronological rows are the SAME
			// markup through one function — they had already drifted apart once.
			$sn_rows = array();
			if ( $sn_pin_on_page1 ) {
				$sn_pin_post = get_post( $sn_start_here_id );
				if ( $sn_pin_post ) {
					$sn_rows[] = array( 'post' => $sn_pin_post, 'pinned' => true );
				}
			}
			while ( $query->have_posts() ) {
				$query->the_post();
				$sn_rows[] = array( 'post' => get_post(), 'pinned' => false );
			}
			wp_reset_postdata();

			$sn_row_args = array( 'show_type' => (bool) $sn_searching );
			if ( $sn_filtered ) {
				// Filtered views stay flat: a search result set is ordered by
				// relevance to the query, and folding it by year would impose a
				// spine on a list whose structure is the QUERY, not the calendar.
				sn_notes_render_row_list( wp_list_pluck( $sn_rows, 'post' ), $sn_row_args );
			} else {
				// Browse mode. The pinned row sits ABOVE the spine — it is
				// deliberately out of date order, so filing it under a year would
				// contradict the reason it is pinned.
				$sn_pinned_rows = array();
				$sn_chrono      = array();
				foreach ( $sn_rows as $sn_r ) {
					if ( $sn_r['pinned'] ) {
						$sn_pinned_rows[] = $sn_r['post'];
					} else {
						$sn_chrono[] = $sn_r['post'];
					}
				}
				if ( $sn_pinned_rows ) {
					sn_notes_render_row_list( $sn_pinned_rows, array( 'pinned' => true ) );
				}
				sn_notes_render_year_spine( $sn_chrono, $sn_row_args );
			}
			?>
		<?php elseif ( $sn_searching ) : ?>
			<p class="sn-notes-empty">Nothing matches &ldquo;<?php echo esc_html( $sn_term ); ?>&rdquo;. Notes, essays, and pages all searched.</p>
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
