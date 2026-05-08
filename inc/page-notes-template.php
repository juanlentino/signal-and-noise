<?php
/**
 * Signal & Noise — /notes page, PHP-authoritative full render.
 *
 * Why this exists: across THREE incidents (2026-04, 2026-05, 2026-05)
 * the /notes page rendered stale content despite the canonical
 * version being correct in `main`. Each incident had a different
 * proximate cause (deploy silently skipping the file, broken self-
 * heal corrupting it, stale `wp_template` DB row surviving the one-
 * shot migration), but the common surface was WordPress's block-
 * template resolution chain — file ↔ DB ↔ object cache ↔ registry —
 * which has too many layers that can drift independently and too
 * many ways to silently render stale content.
 *
 * This module pulls /notes off that chain entirely. We hook
 * `template_include` and short-circuit to a custom render file in
 * `inc/page-notes-render.php`. WP's normal block-template resolution
 * never runs for this page when our hook fires. Front-end rendering
 * is driven from PHP.
 *
 * Defense layers:
 *   1. PHP renderer in inc/page-notes-render.php (PRIMARY) — the
 *      canonical source of truth; what users actually see.
 *   2. templates/page-notes.html (FALLBACK) — kept on disk with the
 *      correct two-card content. Used by WP normally if our
 *      template_include hook fails to resolve (e.g., the render
 *      file is missing post-deploy). Better to render from a stale-
 *      but-correct file than to 404.
 *   3. admin_init wp_template DB sweep — clears any stale Site
 *      Editor save that would otherwise win in get_block_templates()
 *      results.
 *
 * Trade-off: /notes is no longer practically editable via Site
 * Editor — the canonical layout lives in inc/page-notes-render.php.
 * Given the incident history, this is the right call: the page
 * hasn't been customized via Site Editor in practice, and removing
 * the surface eliminates the failure mode entirely.
 *
 * @package SignalNoise
 * @since 7.0.x
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Build marker — surfaces in an HTML comment via wp_footer so the
 * deployed version of THIS file can be verified from the live site
 * with a curl. Bumped manually on each commit that touches /notes
 * routing. Without a marker like this, "did the deploy actually take
 * effect?" was answered by behavioural inference, which has lied to
 * us across multiple incidents on this exact page.
 */
const SN_NOTES_OVERRIDE_BUILD = '2026-05-08-side-by-side-v5';

/**
 * Detect whether the current request is the /notes index page.
 *
 * Two-layer detection because incidents have shown is_page('notes')
 * to be unreliable in this codebase's setup (the page coexists with
 * a `notes` category and a `/notes/%postname%/` permalink structure
 * — WP's resolver sometimes routes through query paths where
 * is_page() returns false despite the URL clearly being the page):
 *
 *   1. is_page('notes') / is_page() with the seed slug — the
 *      idiomatic check, fast.
 *   2. URL-path equality on REQUEST_URI — fires regardless of how
 *      WP parsed the request. Last-resort match.
 *
 * Returns true if EITHER layer matches. False positives here are
 * harmless (we'd render the index for a route that should have
 * been a different page — but no other route on this site has the
 * exact path `/notes` or `/notes/`).
 */
function sn_notes_is_index_request() {
	if ( function_exists( 'is_page' ) && is_page( 'notes' ) ) {
		return true;
	}
	// URL-path fallback. Strip query string + trailing slash, compare
	// against the canonical bare path. WP will not have resolved a
	// single-post URL like /notes/some-slug/ to this branch — those
	// have a longer path.
	$req  = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '';
	$path = strtok( $req, '?' );
	$path = '/' . trim( (string) $path, '/' );
	return ( '/notes' === $path );
}

/**
 * PRIMARY override: short-circuit on `template_redirect`, render
 * the PHP file directly, and exit. This sidesteps the entire WP
 * template-include pipeline so nothing downstream can cache, mask,
 * or otherwise interfere with what we render.
 *
 * Priority 0 (very early) so we beat anything else hooked here.
 * After our `include`, exit — WP would otherwise fall through to
 * its own template loader after this action runs.
 *
 * Fallback: if the render file is missing on disk for any reason,
 * we don't exit — we let WP's normal template resolution run and
 * (eventually) load templates/page-notes.html as a safety net.
 */
add_action( 'template_redirect', function() {
	if ( ! sn_notes_is_index_request() ) {
		return;
	}
	$render = get_theme_file_path( 'inc/page-notes-render.php' );
	if ( ! file_exists( $render ) ) {
		return;
	}
	include $render;
	exit;
}, 0 );

/**
 * Belt-and-suspenders: also hook `template_include` (priority 999)
 * for any code path that calls into WP's template loader without
 * going through `template_redirect` first. Same render file, same
 * outcome.
 */
add_filter( 'template_include', function( $template ) {
	if ( ! sn_notes_is_index_request() ) {
		return $template;
	}
	$render = get_theme_file_path( 'inc/page-notes-render.php' );
	if ( ! file_exists( $render ) ) {
		return $template;
	}
	return $render;
}, 999 );

/**
 * Emit the build marker in wp_footer on every page so we can
 * verify what version of this file is actually on the server.
 *
 *   curl -s https://juanlentino.com/ | grep sn-notes-build
 *
 * Cheap (one extra comment per page), high diagnostic value.
 */
add_action( 'wp_footer', function() {
	echo "\n<!-- sn-notes-build: " . esc_html( SN_NOTES_OVERRIDE_BUILD ) . " -->\n";
}, 999 );

/**
 * Set the document `<title>` for the /notes index page.
 *
 * Why this is needed: when our `template_redirect` short-circuit
 * fires, WordPress's normal title-resolution path can produce
 * unexpected output (the URL, an empty string, or the site name
 * with no page-specific prefix) because the request may not have
 * resolved to the `notes` Page object cleanly — same routing
 * ambiguity that made `is_page('notes')` unreliable. Filtering
 * `pre_get_document_title` short-circuits WP's resolver and
 * returns a title we control, formatted to match the rest of
 * the site (`Page Title — Site Name`).
 */
add_filter( 'pre_get_document_title', function( $title ) {
	if ( ! sn_notes_is_index_request() ) {
		return $title;
	}
	$site = get_bloginfo( 'name' );
	return $site ? 'Notes — ' . $site : 'Notes';
}, 999 );

/**
 * Auto-purge wp_template DB rows for `page-notes` on every admin
 * pageview. The template file has been removed and the renderer
 * is now PHP-authoritative, but a row in the DB could still exist
 * from a Site Editor save in the past. Clearing it keeps
 * `get_block_templates()` results clean for any other code path
 * that queries them, and prevents the row from re-appearing in the
 * Site Editor UI. Cheap query — keyed lookup on post_type +
 * post_name. No-op when no row matches.
 */
add_action( 'admin_init', function() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	if ( ! post_type_exists( 'wp_template' ) ) {
		return;
	}
	$ids = get_posts( array(
		'post_type'      => 'wp_template',
		'post_status'    => 'any',
		'name'           => 'page-notes',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'no_found_rows'  => true,
		'tax_query'      => array(
			array(
				'taxonomy' => 'wp_theme',
				'field'    => 'name',
				'terms'    => 'signal-and-noise',
			),
		),
	) );
	foreach ( $ids as $id ) {
		wp_delete_post( (int) $id, true );
	}
} );
