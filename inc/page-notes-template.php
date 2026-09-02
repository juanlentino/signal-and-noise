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
 * Detect a tag-archive request (/tag/{slug}/).
 *
 * CORRECTED in v12.15.0: this said the archives live at /notes/tag/{slug}/,
 * "under the /notes permalink front". They do not — the `/notes/%postname%/`
 * structure does NOT carry onto the tag base, and /notes/tag/{slug}/ 404s for
 * a live tag. The canonical path is /tag/{slug}/, which is what the site emits
 * and what the retired-tag map in this file matches. The comment was wrong for
 * its whole life; nothing was built on it, which is the only reason it stayed
 * harmless. is_tag() identifies the route either way, so no code changes.
 *
 * The gate also requires a REAL queried term:
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
	return sn_notes_is_index_request() || sn_notes_is_tag_request() || sn_notes_is_tags_request();
}

/**
 * Detect the /notes/tags/ glossary request.
 *
 * Path-matched, because nothing in WordPress resolves this URL: there is no
 * page and no post with that slug, so WP sets a 404 and the dispatcher has to
 * clear it. Matching the path is therefore not a fallback here (as it is for
 * /notes, which IS a real page) — it is the only signal.
 *
 * `/notes` and `/notes/tags` cannot collide: sn_notes_is_index_request()
 * compares against the exact bare path.
 *
 * @since 12.15.0
 * @return bool
 */
function sn_notes_is_tags_request() {
	$req  = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
	$path = strtok( $req, '?' );
	$path = '/' . trim( (string) $path, '/' );
	return ( '/notes/tags' === $path );
}

/**
 * Which render file owns this request.
 *
 * One resolver so the template_redirect short-circuit and the template_include
 * belt-and-suspenders can never disagree about which file to include — the
 * same reason sn_notes_owns_request() is shared with the enqueue.
 *
 * @since 12.15.0
 * @return string Absolute path, or '' when no file exists.
 */
function sn_notes_render_file() {
	$file   = sn_notes_is_tags_request() ? 'inc/page-notes-tags-render.php' : 'inc/page-notes-render.php';
	$render = get_theme_file_path( $file );
	return file_exists( $render ) ? $render : '';
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
/**
 * A tag archive has no page 2 any more — send its old paginated URLs home.
 *
 * v12.13.0 made a tag return every matching note in one response (a tag is a
 * bounded, curated set; only search is unbounded). That leaves the paginated
 * URLs behind: WP's MAIN query still parses `paged`, still uses the site
 * posts_per_page, and still believes /tag/provenance/page/2/ exists — so it
 * would answer 200 while the renderer, which ignores `paged` now, served page
 * ONE's content there. A duplicate of the archive, at a URL Google has already
 * crawled, carrying a self-canonical that asserts it is the original.
 *
 * That is a defect this release would have INTRODUCED, so it is fixed in the
 * same release rather than left for the crawl data to surface in a fortnight.
 *
 * 301, not 302: these URLs are gone permanently, and the whole point is to hand
 * their accumulated signal to the archive rather than split it.
 *
 * SEARCH IS EXCLUDED. A tag archive with ?s= still paginates (sn_notes_query_posts
 * keeps sn_notes_per_page() whenever a term is present), so redirecting those
 * would strand a reader on page 1 of their own search. The guard reads the same
 * term the query builder reads, so the two cannot drift apart.
 *
 * Priority 0, beside the renderer's own hook, and BEFORE it: the render exits,
 * so anything after it never runs.
 */
/**
 * Tags retired by an editorial split, and where each one's readers now go.
 *
 * v12.14.0. `Provenance` sat on 26 of 38 notes — 68% — and every note tagged
 * Authorship, AI Detection or C2PA was also tagged Provenance, at 100%. A term
 * that co-occurs with almost every other term, on two thirds of the corpus, is
 * not a category; it is the name of the site. It was split three ways
 * (Creation-Time Capture 8, Verification Limits 10, Provenance Adoption 8) and
 * the term retired.
 *
 * ITS ARCHIVE WAS THE MOST-CRAWLED ON THE SITE, so the URL cannot simply stop
 * existing. It also cannot point at one successor without lying about the other
 * two, which is why the target is the index rather than a nominated heir: the
 * honest answer to "where did the provenance notes go" is "all of them, and
 * they are still all here".
 *
 * A MAP, not a special case. The split is an editorial act that will happen
 * again — the vocabulary went 83 -> 23 once already — and the second retirement
 * should be a line in this array, never a second copy of this function.
 *
 * @return array<string,string> retired tag slug => path it redirects to.
 */
function sn_notes_retired_tags() {
	return array(
		// v12.14.0: split into three narrower tags, so no single successor.
		'provenance'          => '/notes/',
		// v12.17.0: leftovers from the 83 -> 23 vocabulary migration. Found via
		// Search Console, NOT by reading the code: both were still EARNING
		// IMPRESSIONS while answering 404. A retired tag stops existing in the
		// database long before it stops existing in Google's index, and nothing
		// on this side reports that gap — the only instrument that sees it is
		// the coverage/performance read. These two have real successors, so
		// they point at the topic rather than at the index.
		'cryptography'        => '/tag/cryptographic-signatures/',
		'music-identification' => '/tag/music-metadata/',
	);
}

/**
 * PURE: the target for a retired tag slug, or '' when the slug is still live.
 *
 * Takes the slug rather than reading the query, so tests can drive it without a
 * request — the lesson from sn_notes_paged_tag_target(), whose logic could not
 * be exercised at all while it lived inside the hook closure.
 *
 * @param string $slug Requested tag slug.
 * @return string Relative path, or '' to serve the request.
 */
function sn_notes_retired_tag_target( $slug ) {
	$map = sn_notes_retired_tags();
	return isset( $map[ (string) $slug ] ) ? $map[ (string) $slug ] : '';
}

/**
 * PURE: where a paginated tag URL should go, or '' to leave it alone.
 *
 * Extracted so the DECISION is testable. tests/notes-redirect.php stubs
 * add_action() to a no-op — hooks never register there — so a closure carrying
 * this logic could not be exercised at all, and the one guard that matters most
 * (a searched tag still pages) would have shipped unverified.
 *
 * @param bool   $is_tag    Whether this is a tag archive request.
 * @param string $term      The active search term ('' when not searching).
 * @param int    $paged     The requested page number.
 * @param string $term_link Permalink of the tag archive ('' when unresolvable).
 * @return string Redirect target, or '' for "serve the request".
 */
function sn_notes_paged_tag_target( $is_tag, $term, $paged, $term_link ) {
	if ( ! $is_tag ) {
		return '';
	}
	// A searched tag still paginates (sn_notes_query_posts keeps
	// sn_notes_per_page() whenever a term is present), so redirecting it would
	// strand a reader on page 1 of their own search.
	if ( '' !== (string) $term ) {
		return '';
	}
	if ( (int) $paged < 2 ) {
		return '';
	}
	// No target we can name: serve the page rather than guess one.
	return is_string( $term_link ) && '' !== $term_link ? $term_link : '';
}

add_action( 'template_redirect', function() {
	if ( is_admin() || is_feed() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
		return;
	}
	// A retired tag is matched on the REQUEST PATH, not on a queried object:
	// once the term is deleted WordPress resolves nothing and would 404 before
	// any is_tag() branch could fire. The URL outlives the term, which is the
	// whole reason this exists.
	if ( isset( $_SERVER['REQUEST_URI'] ) ) {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- path-compared against a fixed map, never echoed.
		$sn_path = (string) wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH );
		if ( 1 === preg_match( '#^/tag/([a-z0-9-]+)/?$#', $sn_path, $sn_m ) ) {
			$sn_gone = sn_notes_retired_tag_target( $sn_m[1] );
			if ( '' !== $sn_gone ) {
				wp_safe_redirect( home_url( $sn_gone ), 301 );
				exit;
			}
		}
	}
	$term_obj  = sn_notes_is_tag_request() ? get_queried_object() : null;
	$term_link = ( $term_obj && isset( $term_obj->term_id ) ) ? get_term_link( (int) $term_obj->term_id, 'post_tag' ) : '';
	$target    = sn_notes_paged_tag_target(
		null !== $term_obj,
		function_exists( 'sn_notes_search_term' ) ? sn_notes_search_term() : '',
		(int) get_query_var( 'paged' ),
		is_string( $term_link ) ? $term_link : ''
	);
	if ( '' === $target ) {
		return;
	}
	wp_safe_redirect( $target, 301 );
	exit;
}, 0 );

add_action( 'template_redirect', function() {
	if ( ! sn_notes_owns_request() ) {
		return;
	}
	$render = sn_notes_render_file();
	if ( '' === $render ) {
		return;
	}
	// /notes/tags/ resolves to nothing in WP, so the main query has already
	// set 404 by the time we get here. Clearing is_404 is not enough on its
	// own — status_header() is what actually changes the response line, and
	// without it the page renders correctly and still answers 404 to every
	// crawler. Both, or neither is worth doing.
	if ( sn_notes_is_tags_request() ) {
		global $wp_query;
		if ( $wp_query instanceof WP_Query ) {
			$wp_query->is_404 = false;
		}
		status_header( 200 );
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
	if ( ! sn_notes_owns_request() ) {
		return $template;
	}
	$render = sn_notes_render_file();
	return '' !== $render ? $render : $template;
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
	// Ordered AFTER the tag branch on purpose: /notes/tags/ is not a tag
	// archive and get_queried_object() is null there, so sn_notes_tag_title()
	// would fall back to a bare "Notes" and the glossary would be
	// indistinguishable from the index in search results and browser tabs.
	if ( sn_notes_is_tags_request() ) {
		$site = get_bloginfo( 'name' );
		return $site ? 'Tags — ' . $site : 'Tags';
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
