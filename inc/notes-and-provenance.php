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
const SN_PERMALINK_STRUCTURE    = '/notes/%postname%/';
const SN_SEED_FLAG_OPTION       = 'sn_content_surfaces_seeded_v1';
const SN_PROV_BODY_MIGRATED_OPT = 'sn_provenance_body_migrated_v1';
const SN_PROV_REFINE_MIGR_OPT   = 'sn_provenance_refine_migrated_v1';
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
 * inc/seed-content/provenance-body.html so the Page is editable from
 * Pages → Provenance like any other page (no Site Editor required for
 * prose changes).
 *
 * Idempotent: leave any existing /provenance page untouched.
 */
function sn_ensure_provenance_page() {
	$existing = get_page_by_path( SN_PROVENANCE_SLUG );
	if ( $existing ) {
		return (int) $existing->ID;
	}

	return wp_insert_post( array(
		'post_title'    => 'Provenance Over Detection',
		'post_name'     => SN_PROVENANCE_SLUG,
		'post_status'   => 'publish',
		'post_type'     => 'page',
		'post_content'  => sn_load_provenance_body(),
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
 * Shortcode: [sn_reading_time] — outputs e.g. "3 min read".
 *
 * Calculates from the current post body at ~200 words per minute, with
 * a one-minute floor so very short notes don't render "0 min read".
 */
function sn_reading_time_shortcode() {
	$post = get_post();
	if ( ! $post ) {
		return '';
	}
	$word_count = str_word_count( wp_strip_all_tags( $post->post_content ) );
	$minutes    = max( 1, (int) ceil( $word_count / 200 ) );
	return esc_html( $minutes . ' min read' );
}
add_shortcode( 'sn_reading_time', 'sn_reading_time_shortcode' );

/**
 * Process the [sn_reading_time] shortcode inside block template parts
 * (mirror of the pattern in inc/setup.php for [current_year]).
 */
add_filter( 'render_block', function( $block_content, $block ) {
	if ( strpos( $block_content, '[sn_reading_time]' ) !== false ) {
		$block_content = do_shortcode( $block_content );
	}
	return $block_content;
}, 10, 2 );
