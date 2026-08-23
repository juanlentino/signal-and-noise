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
 * effect?" was answered by behavioral inference, which has lied to
 * us across multiple incidents on this exact page.
 */
const SN_NOTES_OVERRIDE_BUILD = '2026-06-05-notes-search-v12';

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
	$req  = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
	$path = strtok( $req, '?' );
	$path = '/' . trim( (string) $path, '/' );
	return ( '/notes' === $path );
}

/**
 * Detect a /notes tag-archive request (/notes/tag/{slug}/).
 *
 * All post_tag archives live under the /notes permalink front (the
 * `/notes/%postname%/` structure carries onto the tag base), so is_tag()
 * alone identifies the route. The gate also requires a REAL queried term:
 * a non-existent tag slug resolves to no term (WP sets the 404), so we
 * never short-circuit it — and never force a bogus tag to HTTP 200.
 *
 * When true, the same template_include short-circuit that owns /notes
 * renders the catalog with a tag filter (inc/page-notes-render.php),
 * keeping one styling source of truth instead of the generic index.html
 * fallback.
 */
function sn_notes_is_tag_request() {
	if ( ! function_exists( 'is_tag' ) || ! is_tag() ) {
		return false;
	}
	$obj = function_exists( 'get_queried_object' ) ? get_queried_object() : null;
	return (bool) ( $obj && isset( $obj->term_id ) && (int) $obj->term_id > 0 );
}

/**
 * Does this request belong to the /notes renderer?
 *
 * ONE gate, called by both the template_redirect short-circuit below and
 * sn_notes_enqueue(). They must fire on exactly the same requests: a view the
 * renderer claims but the enqueue does not would render with no stylesheet at
 * all — the silent-unstyled failure /notes/subscribe/ shipped with from
 * v11.9.4 to v12.2.1. Sharing the predicate makes that drift impossible rather
 * than merely detectable, and a third route added later reaches both.
 *
 * @since 12.4.1
 * @return bool
 */
function sn_notes_owns_request() {
	return sn_notes_is_index_request() || sn_notes_is_tag_request();
}

/**
 * Enqueue the /notes stylesheet on the /notes routes only. Mirrors
 * sn_index_enqueue() in inc/page-index-template.php: fired during the render
 * file's wp_head() (which triggers wp_enqueue_scripts), gated by the route so
 * it never loads elsewhere.
 *
 * The `sn-components` dependency is load-bearing, not decorative.
 * WP_Dependencies silently drops any handle whose dependency is unregistered
 * (no <link>, only a _doing_it_wrong). In combined mode inc/assets-frontend.php
 * registers `sn-components` as an alias resolving to `sn-styles`; declaring the
 * dependency is what opts this stylesheet into that. Five sibling modules
 * vanished in combined mode in v10.21.6-.8 for exactly this reason.
 *
 * @since 12.4.1
 * @return void
 */
function sn_notes_enqueue() {
	if ( ! sn_notes_owns_request() ) {
		return;
	}
	wp_enqueue_style(
		'sn-notes',
		get_theme_file_uri( 'assets/css/notes.css' ),
		array( 'sn-components' ),
		sn_asset_ver( 'assets/css/notes.css' )
	);
}
add_action( 'wp_enqueue_scripts', 'sn_notes_enqueue', 30 );

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
	if ( ! sn_notes_owns_request() ) {
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
	if ( ! sn_notes_is_index_request() && ! sn_notes_is_tag_request() ) {
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
 * Build the /notes index document title: "Notes — Site", plus "— Page N" for
 * paginated views (N>1). THIS IS THE SINGLE OWNER of the paged suffix — the
 * plugin's document_title_parts filter never fires for /notes because this
 * pre_get_document_title return short-circuits wp_get_document_title() (verified
 * against WP core). The paged read is inlined here (not via the render file's
 * sn_notes_current_page) so it has no load-order dependency on that file.
 */
function sn_notes_index_title() {
	$site  = get_bloginfo( 'name' );
	$title = $site ? 'Notes — ' . $site : 'Notes';
	$paged = (int) get_query_var( 'paged' );
	if ( $paged < 1 && isset( $_GET['paged'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only pagination index, no state change.
		$paged = (int) $_GET['paged']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}
	if ( $paged > 1 ) {
		$title .= ' — Page ' . $paged;
	}
	return $title;
}

/**
 * Build the /notes tag-archive document title: "Notes — {Tag} — {Site}".
 * Same single-owner contract as sn_notes_index_title(): the renderer's
 * short-circuit means this pre_get_document_title return is authoritative
 * for the route. Falls back to "Notes" when the term name is unavailable.
 */
function sn_notes_tag_title() {
	$site = get_bloginfo( 'name' );
	$obj  = function_exists( 'get_queried_object' ) ? get_queried_object() : null;
	$name = ( $obj && isset( $obj->name ) ) ? $obj->name : '';
	$base = $name ? 'Notes — ' . $name : 'Notes';
	return $site ? $base . ' — ' . $site : $base;
}

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
	if ( sn_notes_is_index_request() ) {
		return sn_notes_index_title();
	}
	if ( sn_notes_is_tag_request() ) {
		return sn_notes_tag_title();
	}
	return $title;
}, 999 );

/**
 * Build the canonical Notes-search URL for a term. Empty term -> /notes/.
 * rawurlencode() the term because WP's add_query_arg() does NOT urlencode
 * values (urlencode=false), so a multi-word term would otherwise produce
 * an invalid URL.
 *
 * @param string $term Raw search term.
 * @return string Absolute /notes/ URL, with ?s= when a term is present.
 */
function sn_notes_search_redirect_target( $term ) {
	$term = trim( sanitize_text_field( (string) $term ) );
	$url  = home_url( '/notes/' );
	if ( '' !== $term ) {
		$url = add_query_arg( 's', rawurlencode( $term ), $url );
	}
	return $url;
}

// v10.51.1: search-mode renders are noindexed. This answers the PLUGIN's
// sn_seo_robots_directives seam (plugin v9.88.0) — NOT core's wp_robots, which
// the plugin removes in inc/seo.php so it can emit the tag itself. v10.51.0
// hooked wp_robots and was live-verified inert; the pure logic in
// sn_notes_search_robots() (tests/notes-index-helpers.php) was always correct,
// only its wiring was wrong. Cross-package listener #9 (WORDPRESS-REFERENCE §10.0).
add_filter( 'sn_seo_robots_directives', function( $directives ) {
	if ( ! function_exists( 'sn_notes_search_term' ) || ! function_exists( 'sn_notes_search_robots' ) ) {
		return $directives;
	}
	return sn_notes_search_robots( (array) $directives, sn_notes_search_term() );
} );

/**
 * Funnel any site-wide search (?s=) to the single Notes search surface
 * (v9.8.0). There is exactly one search UI on the site — the /notes
 * archive — so a raw ?s= anywhere (old bookmarks, browser search
 * providers, a leftover global-search URL) is redirected there.
 *
 * Priority 1: AFTER the notes-render short-circuit (priority 0, which
 * already exits for the /notes index) and BEFORE redirect_canonical
 * (priority 10). Skips admin, REST, feeds, and the /notes index itself
 * (defensive — that branch has already exited) so there is no loop.
 */
add_action( 'template_redirect', function() {
	if ( is_admin() || is_feed() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
		return;
	}
	if ( ! is_search() ) {
		return;
	}
	if ( sn_notes_is_index_request() ) {
		return; // already the /notes surface; its renderer handles ?s=.
	}
	wp_safe_redirect( sn_notes_search_redirect_target( get_search_query( false ) ), 302 );
	exit;
}, 1 );

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
