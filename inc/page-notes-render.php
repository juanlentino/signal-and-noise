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

/**
 * Return the formatted reading-time string for a post by slug.
 * Mirrors the [sn_reading_time slug="..."] shortcode but called
 * directly from PHP. Falls back to "5 min" if the slug doesn't
 * resolve (won't happen in practice, both pillar slugs are seeded).
 */
function sn_notes_reading_time_for_slug( $slug ) {
	if ( ! function_exists( 'do_shortcode' ) ) {
		return '5 min';
	}
	$out = do_shortcode( '[sn_reading_time slug="' . esc_attr( $slug ) . '"]' );
	$out = trim( wp_strip_all_tags( (string) $out ) );
	return '' !== $out ? $out : '5 min';
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
	$mins = (int) get_post_meta( $post_id, '_sn_reading_time', true );
	if ( $mins < 1 ) {
		// Fallback compute. ~225 wpm matches the existing shortcode default.
		$content = (string) get_post_field( 'post_content', $post_id );
		$words   = str_word_count( wp_strip_all_tags( $content ) );
		$mins    = max( 1, (int) ceil( $words / 225 ) );
	}
	return sprintf( '%02d MIN', $mins );
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
	return new WP_Query( array(
		'post_type'      => 'post',
		'post_status'    => 'publish',
		'posts_per_page' => 50,
		'orderby'        => 'date',
		'order'          => 'DESC',
		'no_found_rows'  => true,
	) );
}

// ── BEGIN PAGE OUTPUT ──────────────────────────────────────────
$query = sn_notes_query_posts();
$entry_count = (int) $query->post_count;

// Latest-post date for the meta line.
$latest_date = '';
if ( $entry_count > 0 ) {
	$latest = $query->posts[0];
	$latest_date = wp_date( 'Y.m.d', (int) get_the_time( 'U', $latest ) );
}
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php wp_head(); ?>
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

/* SECTION LABEL — chapter heading on a hairline */

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
	color: var(--wp--preset--color--bone, #000);
}
.sn-notes-section-count {
	font-family: 'DM Mono', 'Courier New', monospace;
	font-size: 0.7rem;
	letter-spacing: 0.18em;
	text-transform: uppercase;
	color: var(--wp--preset--color--rust, #666);
}

/* PILLAR ESSAYS ──────────────────────────────────────────────── */

.sn-notes-pillars {
	display: grid;
	grid-template-columns: 1fr;
	gap: clamp(1rem, 2vw, 1.5rem);
}
/* Pillar cards stay in a single column even when the hero+pillars
   composition splits (≥980px) — they live in the right-hand cell
   and stack vertically there, paired with the hero on the left. */

.sn-notes-pillar {
	position: relative;
	display: grid;
	grid-template-columns: 64px 1fr;
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
	width: 6px;
	background: var(--wp--preset--color--blood, #e00404);
	transition: width 0.35s cubic-bezier(0.2, 0.8, 0.2, 1);
}
.sn-notes-pillar:hover::before {
	width: 14px;
}
.sn-notes-pillar:hover {
	transform: translateX(2px);
}
.sn-notes-pillar-number {
	font-family: 'DM Mono', 'Courier New', monospace;
	font-size: clamp(1.2rem, 2vw, 1.6rem);
	color: var(--wp--preset--color--blood, #e00404);
	padding: clamp(1.5rem, 3vw, 2rem) 0 0 1.5rem;
	letter-spacing: 0.05em;
	font-weight: 500;
}
.sn-notes-pillar-body {
	padding: clamp(1.5rem, 3vw, 2rem) clamp(1.5rem, 3vw, 2rem) clamp(1.5rem, 3vw, 2rem) 0;
}
.sn-notes-pillar-eyebrow {
	font-family: 'DM Mono', 'Courier New', monospace;
	font-size: 0.7rem;
	letter-spacing: 0.18em;
	text-transform: uppercase;
	color: var(--wp--preset--color--blood, #e00404);
	margin: 0 0 0.75rem;
}
.sn-notes-pillar-title {
	font-family: 'Bebas Neue', Impact, sans-serif;
	font-weight: 400;
	font-size: clamp(2rem, 4vw, 3rem);
	line-height: 0.95;
	letter-spacing: -0.01em;
	margin: 0 0 1rem;
	color: var(--wp--preset--color--bone, #000);
}
.sn-notes-pillar-dek {
	font-size: clamp(0.95rem, 1.2vw, 1.05rem);
	line-height: 1.55;
	color: var(--wp--preset--color--rust, #666);
	margin: 0 0 1.25rem;
	max-width: 38ch;
}
.sn-notes-pillar-cta {
	font-family: 'DM Mono', 'Courier New', monospace;
	font-size: 0.75rem;
	letter-spacing: 0.18em;
	text-transform: uppercase;
	color: var(--wp--preset--color--bone, #000);
	text-decoration: none;
	display: inline-flex;
	align-items: center;
	gap: 0.5rem;
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

.sn-notes-empty {
	font-family: 'DM Mono', 'Courier New', monospace;
	font-size: 0.85rem;
	letter-spacing: 0.1em;
	text-transform: uppercase;
	color: var(--wp--preset--color--rust, #666);
	padding: 2rem 0;
}

/* RSS FEED FOOTER — terminal status line ─────────────────────── */

.sn-notes-feed {
	margin-top: clamp(2rem, 4vw, 3rem);
	margin-bottom: clamp(2rem, 4vw, 3rem);
	font-family: 'DM Mono', 'Courier New', monospace;
}
.sn-notes-feed-status {
	font-size: 0.85rem;
	letter-spacing: 0.12em;
	text-transform: uppercase;
	color: var(--wp--preset--color--bone, #000);
	margin: 0 0 0.5rem;
}
.sn-notes-feed-status a {
	color: var(--wp--preset--color--blood, #e00404);
	text-decoration: none;
	border-bottom: 1px solid transparent;
	transition: border-color 0.2s ease;
}
.sn-notes-feed-status a:hover {
	border-bottom-color: var(--wp--preset--color--blood, #e00404);
}
.sn-notes-feed-cursor {
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
.sn-notes-feed-note {
	font-size: 0.7rem;
	letter-spacing: 0.18em;
	text-transform: uppercase;
	color: var(--wp--preset--color--rust, #666);
	margin: 0;
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
	.sn-notes-feed-cursor { animation: none; opacity: 0.6; }
	.sn-notes-pillar { transition: none; }
	.sn-notes-pillar::before { transition: none; }
	.sn-notes-row { transition: none; }
}
</style>
</head>
<body <?php body_class( 'sn-notes-body' ); ?>>
<?php wp_body_open(); ?>

<?php
// Render the header via the template-part block syntax (instead of
// `block_template_part('header')` which outputs only the inner
// content). The block syntax produces the full structure including
// the `<header class="wp-block-template-part">` wrapper that the
// CSS for .sn-header (notably the fixed-position rail) depends on
// to position correctly. Mirror what `templates/page.html` would
// have rendered.
echo do_blocks( '<!-- wp:template-part {"slug":"header","area":"header"} /-->' );
?>

<main class="sn-notes-page" id="content">

	<div class="sn-notes-top">

		<header class="sn-notes-hero">
			<p class="sn-notes-eyebrow">Index &middot; Vol. 01 &middot; <?php echo esc_html( wp_date( 'Y' ) ); ?></p>
			<h1 class="sn-notes-headline">Notes.</h1>
			<p class="sn-notes-dek">Working notes on music, AI, and the infrastructure underneath. Written when there&rsquo;s something worth writing.</p>
			<p class="sn-notes-meta">
				<span><?php echo esc_html( sprintf( _n( '%d entry', '%d entries', $entry_count, 'signal-noise' ), $entry_count ) ); ?></span>
				<?php if ( $latest_date ) : ?>
					<span class="sn-notes-meta-bullet" aria-hidden="true">&middot;</span>
					<span>Last updated <?php echo esc_html( $latest_date ); ?></span>
				<?php endif; ?>
			</p>
		</header>

		<section class="sn-notes-pillars-section" aria-labelledby="sn-pillars-heading">
			<div class="sn-notes-section-wrap">
				<p class="sn-notes-section-label" id="sn-pillars-heading">Pillar Essays &mdash; Featured</p>
				<span class="sn-notes-section-count">02 / 02</span>
			</div>

			<div class="sn-notes-pillars">

				<article class="sn-notes-pillar">
					<span class="sn-notes-pillar-number" aria-hidden="true">&#8470; 01</span>
					<div class="sn-notes-pillar-body">
						<p class="sn-notes-pillar-eyebrow">Pillar Essay &middot; March 2026 &middot; <?php echo esc_html( sn_notes_reading_time_for_slug( 'provenance/over-detection' ) ); ?></p>
						<h2 class="sn-notes-pillar-title">Provenance Over Detection</h2>
						<p class="sn-notes-pillar-dek">Detection chases what isn&rsquo;t. Provenance proves what is.</p>
						<a class="sn-notes-pillar-cta" href="/provenance/over-detection/">Read essay</a>
					</div>
				</article>

				<article class="sn-notes-pillar">
					<span class="sn-notes-pillar-number" aria-hidden="true">&#8470; 02</span>
					<div class="sn-notes-pillar-body">
						<p class="sn-notes-pillar-eyebrow">Pillar Essay &middot; May 2026 &middot; <?php echo esc_html( sn_notes_reading_time_for_slug( 'provenance/as-substrate' ) ); ?></p>
						<h2 class="sn-notes-pillar-title">Provenance as Substrate</h2>
						<p class="sn-notes-pillar-dek">Music files need fingerprints, not name tags.</p>
						<a class="sn-notes-pillar-cta" href="/provenance/as-substrate/">Read essay</a>
					</div>
				</article>

			</div>
		</section>

	</div>

	<hr class="sn-notes-rule" aria-hidden="true">

	<section class="sn-notes-index-section" aria-labelledby="sn-index-heading">
		<div class="sn-notes-section-wrap">
			<p class="sn-notes-section-label" id="sn-index-heading">Notes &mdash; Index</p>
			<span class="sn-notes-section-count"><?php echo esc_html( sprintf( '%02d / %02d', $entry_count, $entry_count ) ); ?></span>
		</div>

		<?php if ( $query->have_posts() ) : ?>
			<ol class="sn-notes-index-list">
			<?php while ( $query->have_posts() ) : $query->the_post(); $p = get_post(); ?>
				<li class="sn-notes-row">
					<div class="sn-notes-row-spec" aria-hidden="false">
						<time class="sn-notes-row-date" datetime="<?php echo esc_attr( get_the_date( 'c', $p ) ); ?>"><?php echo sn_notes_render_date( $p ); ?></time>
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
		<?php else : ?>
			<p class="sn-notes-empty">No notes published yet. Check back soon.</p>
		<?php endif; ?>
	</section>

	<hr class="sn-notes-rule" aria-hidden="true">

	<footer class="sn-notes-feed" aria-label="RSS feed">
		<p class="sn-notes-feed-status">
			Feed &mdash; <a href="/notes/feed/">/notes/feed/</a><span class="sn-notes-feed-cursor" aria-hidden="true"></span>
		</p>
		<p class="sn-notes-feed-note">No subscription form. No schedule. Notes available via RSS.</p>
	</footer>

</main>

<?php
// Footer via the template-part block syntax — same reasoning as
// the header above. Produces the `<footer class="wp-block-template-
// part">` wrapper that the .sn-footer CSS depends on (position:
// fixed, z-index 9990, etc).
echo do_blocks( '<!-- wp:template-part {"slug":"footer","area":"footer"} /-->' );
?>

<?php wp_footer(); ?>
</body>
</html>
