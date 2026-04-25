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
		'post_excerpt'  => 'Short essays on music, AI, and the systems behind both.',
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
 * Auto-route posts in the Notes category to single-note.html. Editors
 * can still pick a different template explicitly via the post sidebar;
 * this only kicks in when no template is assigned.
 */
add_filter( 'single_template_hierarchy', function( $templates ) {
	if ( ! is_singular( 'post' ) ) {
		return $templates;
	}
	$post = get_queried_object();
	if ( ! $post || ! has_category( SN_NOTES_CATEGORY_SLUG, $post ) ) {
		return $templates;
	}
	array_unshift( $templates, 'single-note' );
	return $templates;
} );

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
