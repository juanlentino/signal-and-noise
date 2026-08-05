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
	/* v11.4.1: unified site-wide title scale (owner audit — "some titles
	   are even larger than others"); was clamp(4rem, 14vw, 11rem). */
	font-size: clamp(3rem, 8vw, 7rem);
	line-height: 0.95;
	letter-spacing: -0.02em;
	margin: 0 0 1.25rem;
	color: var(--wp--preset--color--bone, #000);
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
	/* Loosened 60ch → 80ch with the 1320px container (owner direction,
	   v11.4.0): the tighter cap left the right half of each row empty. */
	max-width: 80ch;
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
	font-size: max(0.7rem, 11px);
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

	<header class="sn-notes-hero">
		<?php // v11.4.4 resume-treatment: eyebrow is a kicker spanning the
		      // grid; dek reads under the title; the side column carries the
		      // subscribe line with the corpus meta as its closing stamp. ?>
		<p class="sn-notes-eyebrow"><?php if ( $sn_tag ) : ?>Topic &middot; <?php echo esc_html( $sn_tag_name ); ?><?php else : ?>Index &middot; <?php echo esc_html( wp_date( 'Y' ) ); ?><?php endif; ?></p>
		<div class="sn-notes-hero-title">
			<h1 class="sn-notes-headline">Notes.</h1>
			<p class="sn-notes-dek">Working notes on music, AI, and the infrastructure underneath. Written when there&rsquo;s something worth writing.</p>
		</div>
		<div class="sn-notes-hero-side">
			<p class="sn-notes-subscribe">
				No subscription form. No schedule. Notes via <a href="/notes/feed/">RSS</a>, or via email through <a href="https://blogtrottr.com/" target="_blank" rel="noopener noreferrer" data-sn-subscribe="email">Blogtrottr</a> or <a href="https://www.feedrabbit.com/" target="_blank" rel="noopener noreferrer" data-sn-subscribe="email">Feedrabbit</a>.<span class="sn-notes-cursor" aria-hidden="true"></span>
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
						<?php if ( $sn_searching ) : // v10.51.0: search spans the corpus, so rows say what they are. ?>
							<span class="sn-notes-row-type"><?php echo esc_html( sn_notes_result_type_label( $p ) ); ?></span>
						<?php endif; ?>
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
