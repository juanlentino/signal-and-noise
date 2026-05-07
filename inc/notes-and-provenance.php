<?php
/**
 * Signal & Noise — Notes content surface + Provenance pillar.
 *
 * Wires up two additions on top of the existing FSE templates:
 *
 *   1. A "Notes" content surface that uses native WordPress Posts. One
 *      category (`notes`), permalink `/notes/%postname%/`, listed at
 *      `/notes` (a Page running a query loop scoped to the Notes
 *      category), and rendered via single-note.html.
 *
 *   2. A static "Provenance" pillar Page at `/provenance` rendered via
 *      page-provenance.html (hero, 5 anchored sections, Section 2 SVG,
 *      footer CTA, dynamic byline). The Page itself is created empty;
 *      all visible content lives in the template.
 *
 * Discussion features (comments, pings, trackbacks, XML-RPC) are NOT
 * touched here — they're disabled site-wide at the WP/infrastructure
 * level and the templates this module ships do not reference them.
 *
 * @package SignalNoise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const SN_NOTES_CATEGORY_SLUG    = 'notes';
const SN_NOTES_PAGE_SLUG        = 'notes';
const SN_PROVENANCE_SLUG        = 'provenance';
const SN_OVER_DETECTION_SLUG    = 'over-detection';
const SN_PERMALINK_STRUCTURE    = '/notes/%postname%/';
const SN_SEED_FLAG_OPTION       = 'sn_content_surfaces_seeded_v1';
const SN_PROV_BODY_MIGRATED_OPT = 'sn_provenance_body_migrated_v1';
const SN_PROV_REFINE_MIGR_OPT   = 'sn_provenance_refine_migrated_v1';
const SN_PROV_BYLINE_RT_MIGR_OPT = 'sn_provenance_byline_reading_time_migrated_v1';
const SN_PROV_SPLIT_MIGR_OPT    = 'sn_provenance_split_migrated_v1';
const SN_NOTES_QUERY_ID         = 42;

/**
 * Activation: seed category, pages, and permalink once per theme install.
 *
 * Idempotent — safe to run multiple times. The seed flag prevents
 * unnecessary work on every theme switch.
 */
add_action( 'after_switch_theme', 'sn_seed_content_surfaces' );

/**
 * Also run on admin_init (cheap option read) so a fresh deploy without a
 * theme-switch event still gets the surfaces created. Guarded by the same
 * seed flag, so it only ever does real work once.
 */
add_action( 'admin_init', 'sn_seed_content_surfaces' );

function sn_seed_content_surfaces() {
	if ( get_option( SN_SEED_FLAG_OPTION ) ) {
		return;
	}

	sn_ensure_notes_category();
	sn_ensure_notes_page();
	sn_ensure_provenance_page();
	sn_ensure_over_detection_page();
	sn_ensure_permalink_structure();

	update_option( SN_SEED_FLAG_OPTION, time(), true );
	flush_rewrite_rules();
}

function sn_ensure_notes_category() {
	$existing = get_term_by( 'slug', SN_NOTES_CATEGORY_SLUG, 'category' );
	if ( $existing ) {
		return (int) $existing->term_id;
	}

	$result = wp_insert_term(
		'Notes',
		'category',
		array( 'slug' => SN_NOTES_CATEGORY_SLUG )
	);

	return is_wp_error( $result ) ? 0 : (int) $result['term_id'];
}

function sn_ensure_notes_page() {
	$existing = get_page_by_path( SN_NOTES_PAGE_SLUG );
	if ( $existing ) {
		return (int) $existing->ID;
	}

	return wp_insert_post( array(
		'post_title'    => 'Notes',
		'post_name'     => SN_NOTES_PAGE_SLUG,
		'post_status'   => 'publish',
		'post_type'     => 'page',
		'post_content'  => '',
		'post_excerpt'  => 'Working notes on music, AI, and the infrastructure underneath. Written when there\'s something worth writing.',
		'page_template' => 'page-notes',
	), false );
}

/**
 * Create the Provenance pillar as a static Page, assigned to the
 * page-provenance.html custom template. Body is pre-populated from
 * inc/seed-content/provenance-body.html — a lean two-paper index
 * (heading + intro + two entries with SSRN + long-form links). The
 * long-form essay for paper 1 lives on the child page /provenance/
 * over-detection (see sn_ensure_over_detection_page).
 *
 * Idempotent: leave any existing /provenance page untouched.
 */
function sn_ensure_provenance_page() {
	$existing = get_page_by_path( SN_PROVENANCE_SLUG );
	if ( $existing ) {
		return (int) $existing->ID;
	}

	return wp_insert_post( array(
		'post_title'    => 'On Provenance',
		'post_name'     => SN_PROVENANCE_SLUG,
		'post_status'   => 'publish',
		'post_type'     => 'page',
		'post_content'  => sn_load_provenance_body(),
		'post_excerpt'  => 'Two papers proposing cryptographic provenance as the foundation of music rights infrastructure.',
		'page_template' => 'page-provenance',
	), false );
}

/**
 * Create the long-form essay as a child page under /provenance, at
 * /provenance/over-detection. Reuses page-provenance.html so the prose
 * inherits the same hero/section/byline treatment the essay was designed
 * for. Idempotent: leave any existing child page untouched (so an admin
 * who edits the essay from Pages → Provenance Over Detection isn't
 * clobbered on a future theme reactivation).
 */
function sn_ensure_over_detection_page() {
	$parent = get_page_by_path( SN_PROVENANCE_SLUG );
	$parent_id = $parent ? (int) $parent->ID : 0;

	$existing = get_page_by_path( SN_PROVENANCE_SLUG . '/' . SN_OVER_DETECTION_SLUG );
	if ( $existing ) {
		return (int) $existing->ID;
	}

	return wp_insert_post( array(
		'post_title'    => 'Provenance Over Detection',
		'post_name'     => SN_OVER_DETECTION_SLUG,
		'post_parent'   => $parent_id,
		'post_status'   => 'publish',
		'post_type'     => 'page',
		'post_content'  => sn_load_over_detection_body(),
		'post_excerpt'  => "A short read on why the industry needs to prove what's human, not chase what isn't.",
		'page_template' => 'page-provenance',
	), false );
}

/**
 * Load the seeded Provenance body markup from disk.
 * Empty string fallback if the seed file is missing — the template will
 * just render an empty post-content area, no fatal.
 */
function sn_load_provenance_body() {
	$body_file = __DIR__ . '/seed-content/provenance-body.html';
	return file_exists( $body_file ) ? file_get_contents( $body_file ) : '';
}

/**
 * Load the seeded long-form essay markup from disk. Mirrors
 * sn_load_provenance_body — same fallback semantics.
 */
function sn_load_over_detection_body() {
	$body_file = __DIR__ . '/seed-content/over-detection-body.html';
	return file_exists( $body_file ) ? file_get_contents( $body_file ) : '';
}

/**
 * One-time migration for sites upgrading from v6.1.0 (where the
 * Provenance Page was created with an empty body and all visible content
 * lived in the template). Populates the existing Page's body from the
 * seed file so it becomes editable from Pages → Provenance.
 *
 * Safety:
 *   - Runs at most once per site (guarded by a dedicated option flag).
 *   - Only writes when the existing body is genuinely empty — never
 *     overwrites prose someone has already added.
 *   - The flag is set even on no-op paths so we don't keep checking.
 */
add_action( 'admin_init', 'sn_migrate_provenance_body' );

function sn_migrate_provenance_body() {
	if ( get_option( SN_PROV_BODY_MIGRATED_OPT ) ) {
		return;
	}

	$page = get_page_by_path( SN_PROVENANCE_SLUG );
	if ( ! $page ) {
		// Page doesn't exist yet — sn_ensure_provenance_page() will seed
		// the body when it runs. Mark migrated so we don't keep checking.
		update_option( SN_PROV_BODY_MIGRATED_OPT, time(), true );
		return;
	}

	if ( '' !== trim( $page->post_content ) ) {
		// Body already has content — could be edits we shouldn't touch.
		update_option( SN_PROV_BODY_MIGRATED_OPT, time(), true );
		return;
	}

	$body = sn_load_provenance_body();
	if ( '' === $body ) {
		// Seed file missing — leave the Page alone, do not mark migrated
		// so we retry on next admin_init in case the file lands later.
		return;
	}

	wp_update_post( array(
		'ID'           => $page->ID,
		'post_content' => $body,
	) );

	update_option( SN_PROV_BODY_MIGRATED_OPT, time(), true );
}

/**
 * One-time refinements migration for the Provenance pillar:
 *
 *   1. Inject the inline TOC paragraph (between the hero and the first
 *      separator) if it isn't already present.
 *   2. Add `displayType: "modified"` to the byline's wp:post-date block
 *      so the date reads "last updated" rather than "first published" —
 *      more honest for a permanent reference essay that gets iterated on.
 *
 * Both edits are surgical, defensive, and idempotent: each is skipped
 * when the marker is missing or the change is already applied. Prose
 * paragraphs are never touched. Safe to re-run; in practice runs once
 * per site (guarded by SN_PROV_REFINE_MIGR_OPT).
 */
add_action( 'admin_init', 'sn_migrate_provenance_refinements' );

function sn_migrate_provenance_refinements() {
	if ( get_option( SN_PROV_REFINE_MIGR_OPT ) ) {
		return;
	}

	$page = get_page_by_path( SN_PROVENANCE_SLUG );
	if ( ! $page ) {
		// Page doesn't exist yet — nothing to migrate. Mark done so we
		// don't keep scanning on every admin_init.
		update_option( SN_PROV_REFINE_MIGR_OPT, time(), true );
		return;
	}

	$body     = $page->post_content;
	$original = $body;

	// 1. Inject TOC after the hero group close, before the first separator.
	if ( false === strpos( $body, 'sn-provenance-toc' ) ) {
		$hero_start = strpos( $body, '<!-- wp:group {"className":"sn-provenance-hero"' );
		if ( false !== $hero_start ) {
			$hero_close_marker = '<!-- /wp:group -->';
			$hero_close        = strpos( $body, $hero_close_marker, $hero_start );
			if ( false !== $hero_close ) {
				$insert_at = $hero_close + strlen( $hero_close_marker );
				$body      = substr( $body, 0, $insert_at )
					. "\n\n" . sn_provenance_toc_block_markup() . "\n"
					. substr( $body, $insert_at );
			}
		}
	}

	// 2. Add displayType:"modified" to the byline's wp:post-date.
	if ( false === strpos( $body, '"displayType":"modified"' ) ) {
		$body = preg_replace(
			'/<!-- wp:post-date \{"format":"F j, Y",/',
			'<!-- wp:post-date {"format":"F j, Y","displayType":"modified",',
			$body,
			1
		);
	}

	if ( $body !== $original ) {
		wp_update_post( array(
			'ID'           => $page->ID,
			'post_content' => $body,
		) );
	}

	update_option( SN_PROV_REFINE_MIGR_OPT, time(), true );
}

/**
 * One-time migration that injects the reading-time block into the
 * existing Provenance byline. Mirrors the seed file change in 6.3.1.
 *
 * Idempotent — bails if the byline already contains the reading-time
 * marker (paste-by-hand defensive). Gated by SN_PROV_BYLINE_RT_MIGR_OPT
 * so it only runs once per install.
 */
add_action( 'admin_init', 'sn_migrate_provenance_byline_reading_time' );

function sn_migrate_provenance_byline_reading_time() {
	if ( get_option( SN_PROV_BYLINE_RT_MIGR_OPT ) ) {
		return;
	}

	$page = get_page_by_path( SN_PROVENANCE_SLUG );
	if ( ! $page ) {
		update_option( SN_PROV_BYLINE_RT_MIGR_OPT, time(), true );
		return;
	}

	$body     = $page->post_content;
	$original = $body;

	// Skip if the reading-time block is already present (paste-by-hand defensive).
	if ( false === strpos( $body, 'sn-provenance-byline-reading-time' ) ) {
		// Anchor on the byline's wp:post-date opener and the next ` /-->`
		// (the tag is self-closing). strpos avoids the nested-{} pitfall
		// the regex form hits once the 6.2.6 migration adds a style object.
		$start = strpos( $body, '<!-- wp:post-date {"format":"F j, Y"' );
		if ( false !== $start ) {
			$end_marker = ' /-->';
			$end        = strpos( $body, $end_marker, $start );
			if ( false !== $end ) {
				$insert_at = $end + strlen( $end_marker );
				$body      = substr( $body, 0, $insert_at )
					. "\n\n\t" . sn_provenance_byline_reading_time_markup()
					. substr( $body, $insert_at );
			}
		}
	}

	if ( $body !== $original ) {
		wp_update_post( array(
			'ID'           => $page->ID,
			'post_content' => $body,
		) );
	}

	update_option( SN_PROV_BYLINE_RT_MIGR_OPT, time(), true );
}

/**
 * Reading-time block markup for the Provenance byline. Factored out so
 * the seed file (inc/seed-content/provenance-body.html) and the
 * migration above share a single source of truth — change the markup
 * here and both ship the same shape.
 */
function sn_provenance_byline_reading_time_markup() {
	return '<!-- wp:paragraph {"className":"sn-provenance-byline-divider","style":{"spacing":{"margin":{"top":"0","bottom":"0"}}}} -->' . "\n"
		. "\t" . '<p class="sn-provenance-byline-divider" style="margin-top:0;margin-bottom:0">·</p>' . "\n"
		. "\t" . '<!-- /wp:paragraph -->' . "\n\n"
		. "\t" . '<!-- wp:paragraph {"className":"sn-provenance-byline-reading-time","style":{"spacing":{"margin":{"top":"0","bottom":"0"}}},"textColor":"blood"} -->' . "\n"
		. "\t" . '<p class="sn-provenance-byline-reading-time has-blood-color has-text-color" style="margin-top:0;margin-bottom:0">[sn_reading_time]</p>' . "\n"
		. "\t" . '<!-- /wp:paragraph -->';
}

/**
 * The TOC block markup, factored out so the seed file and the migration
 * stay in lockstep. If the TOC ever changes shape, change it here.
 */
function sn_provenance_toc_block_markup() {
	return '<!-- wp:paragraph {"className":"sn-provenance-toc","style":{"typography":{"fontSize":"0.95rem","fontStyle":"italic"},"spacing":{"margin":{"top":"0","bottom":"var:preset|spacing|50"}}},"textColor":"rust"} -->' . "\n"
		. '<p class="sn-provenance-toc has-rust-color has-text-color" style="margin-top:0;margin-bottom:var(--wp--preset--spacing--50);font-size:0.95rem;font-style:italic">Jump to: <a href="#setup">The setup</a> · <a href="#analogy">The analogy</a> · <a href="#what-it-means">What provenance means</a> · <a href="#why-it-matters">Why this matters</a> · <a href="#the-shift">The shift</a></p>' . "\n"
		. '<!-- /wp:paragraph -->';
}

/**
 * Set the global permalink structure once, only if it isn't already
 * what we want AND there are no existing posts whose URLs would change.
 *
 * Posts are disabled on this site by default; the empty-post-count guard
 * keeps the change safe even on installs where someone already had a
 * different structure. We never overwrite an existing match either.
 */
function sn_ensure_permalink_structure() {
	$current = get_option( 'permalink_structure' );
	if ( SN_PERMALINK_STRUCTURE === $current ) {
		return;
	}

	$existing_post_count = (int) wp_count_posts( 'post' )->publish;
	if ( $existing_post_count > 0 ) {
		return;
	}

	update_option( 'permalink_structure', SN_PERMALINK_STRUCTURE );
}

/**
 * Default Post Category sync.
 *
 * WordPress's `default_category` option controls which category a new
 * post is assigned when the editor doesn't tick anything explicitly.
 * Pointing it at the Notes category means: any post created from the
 * editor lands in `Notes` automatically, which is what makes them show
 * up at /notes (the index query is filtered by the Notes category).
 *
 * Combined with the Note layout being the default `single.html`
 * template, this makes "Posts → Add New → write → Publish" produce a
 * fully-formed Note with no template dropdown, no category checkbox,
 * no manual setup.
 *
 * Self-healing: runs cheaply on every admin_init and only writes when
 * the option drifts. Safe to call before sn_seed_content_surfaces() has
 * created the category — it just no-ops in that case.
 */
add_action( 'admin_init', 'sn_sync_default_category' );

function sn_sync_default_category() {
	$cat = get_term_by( 'slug', SN_NOTES_CATEGORY_SLUG, 'category' );
	if ( ! $cat ) {
		return;
	}
	if ( (int) get_option( 'default_category' ) !== (int) $cat->term_id ) {
		update_option( 'default_category', (int) $cat->term_id );
	}
}

/**
 * Filter the Notes index query loop (queryId 42 in templates/page-notes.html)
 * to surface only Notes-category posts. Keeping the category restriction
 * here — rather than baked as an ID into block markup — means the template
 * works regardless of the term ID assigned at install time.
 */
add_filter( 'query_loop_block_query_vars', function( $query, $block ) {
	$context_query_id = $block->context['queryId'] ?? null;
	if ( SN_NOTES_QUERY_ID !== $context_query_id ) {
		return $query;
	}
	$query['category_name'] = SN_NOTES_CATEGORY_SLUG;
	return $query;
}, 10, 2 );

/**
 * One-time migration that splits the existing /provenance pillar page
 * into a lean two-paper index (parent: /provenance) and a long-form
 * essay (child: /provenance/over-detection). The essay prose itself
 * is never edited — it's lifted verbatim from the existing live page
 * body and moved into the new child page.
 *
 * Algorithm:
 *   1. Locate the essay's hero block (`sn-provenance-hero` className) in
 *      the existing /provenance body. This anchor is stable across the
 *      seed-file shape and the prior unreleased "prepend cards" shape,
 *      so the same code handles both starting states.
 *   2. Everything from that anchor to end-of-body = the essay. Hand it
 *      to a new child page at /provenance/over-detection.
 *   3. Overwrite the parent /provenance body with the cards-only index.
 *
 * Safety:
 *   - If the hero anchor is missing (page was hand-edited away from
 *     seed shape), bail WITHOUT setting the flag, so a future run after
 *     manual recovery can complete the split.
 *   - If a /provenance/over-detection page already exists, leave its
 *     body untouched (admin may have edited prose there) — only the
 *     parent body is rewritten.
 *   - Gated by SN_PROV_SPLIT_MIGR_OPT, runs at most once per install.
 */
add_action( 'admin_init', 'sn_migrate_provenance_split' );

function sn_migrate_provenance_split() {
	if ( get_option( SN_PROV_SPLIT_MIGR_OPT ) ) {
		return;
	}

	$page = get_page_by_path( SN_PROVENANCE_SLUG );
	if ( ! $page ) {
		// Page doesn't exist yet — sn_ensure_provenance_page() will seed
		// it cleanly. Mark migrated so we don't keep scanning.
		update_option( SN_PROV_SPLIT_MIGR_OPT, time(), true );
		return;
	}

	$body        = $page->post_content;
	$hero_anchor = '<!-- wp:group {"className":"sn-provenance-hero"';
	$hero_pos    = strpos( $body, $hero_anchor );

	// If the hero marker is missing the body has been hand-edited away
	// from the seed shape. Bail without flagging — the migration can
	// re-run after the admin restores the marker (or manually splits).
	if ( false === $hero_pos ) {
		return;
	}

	$essay = trim( substr( $body, $hero_pos ) );

	// Create the child page if it doesn't already exist. We never
	// overwrite an existing child body — admin may have edited the prose
	// there, and our migration job is structural (move), not editorial.
	$child = get_page_by_path( SN_PROVENANCE_SLUG . '/' . SN_OVER_DETECTION_SLUG );
	if ( ! $child ) {
		wp_insert_post( array(
			'post_title'    => 'Provenance Over Detection',
			'post_name'     => SN_OVER_DETECTION_SLUG,
			'post_parent'   => (int) $page->ID,
			'post_status'   => 'publish',
			'post_type'     => 'page',
			'post_content'  => $essay,
			'post_excerpt'  => "A short read on why the industry needs to prove what's human, not chase what isn't.",
			'page_template' => 'page-provenance',
		), false );
	}

	// Replace the parent body with the cards-only index. Title also
	// updates so the WP admin reflects the new role of the page.
	wp_update_post( array(
		'ID'           => $page->ID,
		'post_title'   => 'On Provenance',
		'post_content' => sn_provenance_papers_index_markup(),
	) );

	update_option( SN_PROV_SPLIT_MIGR_OPT, time(), true );
}

/**
 * Block markup for the "On Provenance" series header + two-paper index.
 * Single source of truth — the seed file
 * (inc/seed-content/provenance-body.html) and the migration above must
 * stay in lockstep. Change the markup here and both ship the same shape.
 */
function sn_provenance_papers_index_markup() {
	return <<<'HTML'
<!-- wp:group {"className":"sn-prov-series","style":{"spacing":{"margin":{"bottom":"var:preset|spacing|60"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group sn-prov-series" style="margin-bottom:var(--wp--preset--spacing--60)">

	<!-- wp:heading {"level":2,"className":"font-display sn-prov-series-heading","style":{"typography":{"fontSize":"clamp(1.75rem, 4vw, 2.5rem)"},"spacing":{"margin":{"top":"0","bottom":"var:preset|spacing|30"}}}} -->
	<h2 class="wp-block-heading font-display sn-prov-series-heading" style="margin-top:0;margin-bottom:var(--wp--preset--spacing--30);font-size:clamp(1.75rem, 4vw, 2.5rem)">On Provenance</h2>
	<!-- /wp:heading -->

	<!-- wp:paragraph {"className":"sn-prov-series-intro","style":{"typography":{"fontSize":"1.05rem"},"spacing":{"margin":{"top":"0","bottom":"var:preset|spacing|50"}}},"textColor":"rust"} -->
	<p class="sn-prov-series-intro has-rust-color has-text-color" style="margin-top:0;margin-bottom:var(--wp--preset--spacing--50);font-size:1.05rem">Two papers proposing cryptographic provenance as the foundation of music rights infrastructure. The first argues that detection of AI-generated music is structurally falsifiable; provenance is not. The second extends the framework from authorship verification to the music industry's identifier infrastructure.</p>
	<!-- /wp:paragraph -->

	<!-- wp:group {"className":"sn-prov-papers","layout":{"type":"default"}} -->
	<div class="wp-block-group sn-prov-papers">

		<!-- wp:group {"tagName":"article","className":"sn-prov-paper-card","layout":{"type":"default"}} -->
		<article class="wp-block-group sn-prov-paper-card">

			<!-- wp:paragraph {"className":"sn-prov-paper-meta","style":{"typography":{"fontSize":"0.75rem","letterSpacing":"0.15em","textTransform":"uppercase"},"spacing":{"margin":{"top":"0","bottom":"var:preset|spacing|20"}}},"textColor":"blood","fontFamily":"body"} -->
			<p class="sn-prov-paper-meta has-blood-color has-text-color has-body-font-family" style="margin-top:0;margin-bottom:var(--wp--preset--spacing--20);font-size:0.75rem;letter-spacing:0.15em;text-transform:uppercase">March 2026 · 4 min read</p>
			<!-- /wp:paragraph -->

			<!-- wp:heading {"level":3,"className":"font-display sn-prov-paper-title","style":{"typography":{"fontSize":"1.5rem","lineHeight":"1.15"},"spacing":{"margin":{"top":"0","bottom":"var:preset|spacing|10"}}}} -->
			<h3 class="wp-block-heading font-display sn-prov-paper-title" style="margin-top:0;margin-bottom:var(--wp--preset--spacing--10);font-size:1.5rem;line-height:1.15"><a href="https://papers.ssrn.com/sol3/papers.cfm?abstract_id=6402298" target="_blank" rel="noopener noreferrer">Provenance Over Detection</a></h3>
			<!-- /wp:heading -->

			<!-- wp:paragraph {"className":"sn-prov-paper-subtitle","style":{"typography":{"fontSize":"0.85rem","lineHeight":"1.4"},"spacing":{"margin":{"top":"0","bottom":"var:preset|spacing|30"}}},"textColor":"rust"} -->
			<p class="sn-prov-paper-subtitle has-rust-color has-text-color" style="margin-top:0;margin-bottom:var(--wp--preset--spacing--30);font-size:0.85rem;line-height:1.4">A Cryptographic Framework for Human Authorship Verification in Music Distribution</p>
			<!-- /wp:paragraph -->

			<!-- wp:paragraph {"className":"sn-prov-paper-blurb","style":{"typography":{"fontSize":"0.875rem"},"spacing":{"margin":{"top":"0","bottom":"var:preset|spacing|30"}}}} -->
			<p class="sn-prov-paper-blurb" style="margin-top:0;margin-bottom:var(--wp--preset--spacing--30);font-size:0.875rem">Detection-based responses to AI-generated music — output classifiers that lag the models they chase and produce false positives that punish legitimate artists — are the wrong frame. The solution is provenance: cryptographic verification of human authorship embedded at the point of creation and carried through the distribution chain in a single tamper-evident metadata layer.</p>
			<!-- /wp:paragraph -->

			<!-- wp:paragraph {"className":"sn-prov-paper-longform","style":{"typography":{"fontSize":"0.8rem","fontStyle":"italic"},"spacing":{"margin":{"top":"0","bottom":"0"}}},"textColor":"rust"} -->
			<p class="sn-prov-paper-longform has-rust-color has-text-color" style="margin-top:0;margin-bottom:0;font-size:0.8rem;font-style:italic"><a href="/provenance/over-detection/">Read the long-form on this site →</a></p>
			<!-- /wp:paragraph -->

		</article>
		<!-- /wp:group -->

		<!-- wp:group {"tagName":"article","className":"sn-prov-paper-card","layout":{"type":"default"}} -->
		<article class="wp-block-group sn-prov-paper-card">

			<!-- wp:paragraph {"className":"sn-prov-paper-meta","style":{"typography":{"fontSize":"0.75rem","letterSpacing":"0.15em","textTransform":"uppercase"},"spacing":{"margin":{"top":"0","bottom":"var:preset|spacing|20"}}},"textColor":"blood","fontFamily":"body"} -->
			<p class="sn-prov-paper-meta has-blood-color has-text-color has-body-font-family" style="margin-top:0;margin-bottom:var(--wp--preset--spacing--20);font-size:0.75rem;letter-spacing:0.15em;text-transform:uppercase">May 2026</p>
			<!-- /wp:paragraph -->

			<!-- wp:heading {"level":3,"className":"font-display sn-prov-paper-title","style":{"typography":{"fontSize":"1.5rem","lineHeight":"1.15"},"spacing":{"margin":{"top":"0","bottom":"var:preset|spacing|10"}}}} -->
			<h3 class="wp-block-heading font-display sn-prov-paper-title" style="margin-top:0;margin-bottom:var(--wp--preset--spacing--10);font-size:1.5rem;line-height:1.15"><a href="https://papers.ssrn.com/sol3/papers.cfm?abstract_id=6730343" target="_blank" rel="noopener noreferrer">Provenance as Substrate</a></h3>
			<!-- /wp:heading -->

			<!-- wp:paragraph {"className":"sn-prov-paper-subtitle","style":{"typography":{"fontSize":"0.85rem","lineHeight":"1.4"},"spacing":{"margin":{"top":"0","bottom":"var:preset|spacing|30"}}},"textColor":"rust"} -->
			<p class="sn-prov-paper-subtitle has-rust-color has-text-color" style="margin-top:0;margin-bottom:var(--wp--preset--spacing--30);font-size:0.85rem;line-height:1.4">A Cryptographic Identifier Framework for Music Rights and Royalty Infrastructure</p>
			<!-- /wp:paragraph -->

			<!-- wp:paragraph {"className":"sn-prov-paper-blurb","style":{"typography":{"fontSize":"0.875rem"},"spacing":{"margin":{"top":"0","bottom":"0"}}}} -->
			<p class="sn-prov-paper-blurb" style="margin-top:0;margin-bottom:0;font-size:0.875rem">Extends the provenance argument from authorship verification to identifier infrastructure. The proposal is cryptographic provenance as a substrate beneath ISRC, ISWC, and the rest of the music industry's identifier stack — self-issuing, collision-resistant, signed at creation, with legacy identifiers continuing to function as aliases. The unmatched royalty pool, hundreds of millions at the MLC alone, is a downstream consequence of identifier failure.</p>
			<!-- /wp:paragraph -->

		</article>
		<!-- /wp:group -->

	</div>
	<!-- /wp:group -->

	<!-- wp:paragraph {"className":"sn-prov-series-footer","style":{"typography":{"fontSize":"0.85rem","letterSpacing":"0.15em","textTransform":"uppercase"},"spacing":{"margin":{"top":"var:preset|spacing|40","bottom":"0"}}},"fontFamily":"heading"} -->
	<p class="sn-prov-series-footer has-heading-font-family" style="margin-top:var(--wp--preset--spacing--40);margin-bottom:0;font-size:0.85rem;letter-spacing:0.15em;text-transform:uppercase"><a href="/notes/">Read more notes →</a></p>
	<!-- /wp:paragraph -->

</div>
<!-- /wp:group -->
HTML;
}

/* The [sn_reading_time] shortcode and its render_block bridge moved to
 * inc/reading-time.php in 6.3.0 (cached + 225 WPM + cleanup tooling). */
