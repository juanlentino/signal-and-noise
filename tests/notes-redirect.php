<?php
/**
 * Standalone fixture tests for the /?s= -> /notes/?s= search funnel
 * helper in inc/page-notes-template.php (v9.8.0).
 *
 * inc/page-notes-template.php registers hooks at load, so we stub the
 * registrars (add_action/add_filter) as no-ops and exercise the pure
 * URL-builder directly.
 *
 * @since theme v9.8.0
 */

// SECURITY: Prevent web access. Test fixture, not a runtime module.
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) {
	http_response_code( 404 );
	exit;
}

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/' );
}

if ( ! function_exists( 'add_action' ) ) {
	function add_action() {
		return true;
	}
}
if ( ! function_exists( 'add_filter' ) ) {
	function add_filter() {
		return true;
	}
}
if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $s ) {
		return trim( preg_replace( '/<[^>]*>/', '', (string) $s ) );
	}
}
if ( ! function_exists( 'home_url' ) ) {
	function home_url( $path = '' ) {
		return 'https://example.test' . $path;
	}
}
if ( ! function_exists( 'add_query_arg' ) ) {
	// Minimal stub: WP's real add_query_arg does NOT urlencode values
	// (urlencode=false), so the helper pre-encodes with rawurlencode().
	function add_query_arg( $key, $value, $url ) {
		$sep = ( strpos( $url, '?' ) === false ) ? '?' : '&';
		return $url . $sep . $key . '=' . $value;
	}
}

require __DIR__ . '/../inc/page-notes-template.php';

$pass = 0; $fail = 0;
function ok( $cond, $label ) {
	global $pass, $fail;
	if ( $cond ) { $pass++; echo "PASS: $label\n"; }
	else { $fail++; echo "FAIL: $label\n"; }
}

ok( sn_notes_search_redirect_target( '' ) === 'https://example.test/notes/', 'empty term -> bare /notes/' );
ok( sn_notes_search_redirect_target( 'provenance' ) === 'https://example.test/notes/?s=provenance', 'term -> /notes/?s=term' );
ok( strpos( sn_notes_search_redirect_target( 'hello world' ), 's=hello%20world' ) !== false, 'multi-word term is url-encoded' );
ok( strpos( sn_notes_search_redirect_target( '<b>x</b>' ), 's=x' ) !== false, 'tags stripped before redirect' );


// ── v12.13.0: a tag archive has no page 2, so its old paginated URLs 301 home ──
// The pagination removal would otherwise have INTRODUCED a defect: WP's main
// query still parses `paged` and still believes /tag/x/page/2/ exists, so it
// would answer 200 while the renderer — which ignores `paged` now — served page
// ONE's content there. A duplicate of the archive, at a URL Google has crawled,
// carrying a self-canonical asserting it is the original.
$T = 'https://example.test/tag/provenance/';
ok( $T === sn_notes_paged_tag_target( true, '', 2, $T ), 'page 2 of a tag redirects to the archive' );
ok( $T === sn_notes_paged_tag_target( true, '', 9, $T ), 'so does any deeper page' );
ok( '' === sn_notes_paged_tag_target( true, '', 1, $T ), 'page 1 is the archive itself — never redirect it (that is the loop)' );
ok( '' === sn_notes_paged_tag_target( true, '', 0, $T ), 'an unpaged request is page 1' );

// THE GUARD THAT MATTERS MOST. A searched tag still paginates, because
// sn_notes_query_posts() keeps sn_notes_per_page() whenever a term is present.
// Redirecting those would strand a reader on page 1 of their own search.
ok( '' === sn_notes_paged_tag_target( true, 'signature', 2, $T ), 'a SEARCHED tag keeps its pages — the one case that still pages' );
ok( '' === sn_notes_paged_tag_target( true, 'signature', 5, $T ), 'at any depth' );

ok( '' === sn_notes_paged_tag_target( false, '', 2, $T ), 'not a tag: untouched (search and the index own their own paging)' );
ok( '' === sn_notes_paged_tag_target( true, '', 2, '' ), 'no resolvable term link: serve the page rather than guess a target' );

// The extraction itself is the point: the hook stubs add_action() to a no-op in
// this suite, so a closure carrying this logic could not be exercised at all.
$sn_tpl = (string) file_get_contents( __DIR__ . '/../inc/page-notes-template.php' );
ok( 1 === preg_match( '/function sn_notes_paged_tag_target\(/', $sn_tpl ), 'the decision is a pure function, not buried in the closure' );
ok( 1 === preg_match( '/wp_safe_redirect\( \$target, 301 \)/', $sn_tpl ), '301, not 302: these URLs are gone permanently and should hand over their signal' );


// ── v12.14.0: a retired tag's URL outlives its term ─────────────────────────
// Provenance sat on 26 of 38 notes (68%), co-occurring with Authorship, AI
// Detection and C2PA at 100%. Split three ways and retired. Its archive was the
// most-crawled on the site, so the URL cannot simply stop existing — and it
// cannot point at one successor without lying about the other two, which is why
// the target is the index.
ok( '/notes/' === sn_notes_retired_tag_target( 'provenance' ), 'the retired tag redirects to the index, not to a nominated heir' );
ok( '' === sn_notes_retired_tag_target( 'verification-limits' ), 'a LIVE successor is not redirected — that would erase the split it was made for' );
ok( '' === sn_notes_retired_tag_target( 'creation-time-capture' ), 'nor the second' );
ok( '' === sn_notes_retired_tag_target( 'provenance-adoption' ), 'nor the third' );
ok( '' === sn_notes_retired_tag_target( 'c2pa' ), 'nor any untouched tag' );
ok( '' === sn_notes_retired_tag_target( '' ), 'an empty slug matches nothing' );

// A near-miss must not be swallowed: the successors all begin with the retired
// slug's letters, and a prefix match would redirect the very tags the split
// created. This is the same class as the subscribe route's path-exactness.
ok( '' === sn_notes_retired_tag_target( 'provenance-' ), 'no prefix matching' );
ok( '' === sn_notes_retired_tag_target( 'PROVENANCE' ), 'and the map is case-exact' );

// ── v12.17.0: two dead URLs found in Search Console, not in the code ────────
// Leftovers from the 83 -> 23 vocabulary migration. Both were answering 404
// while still EARNING IMPRESSIONS. A tag stops existing in the database long
// before it stops existing in Google's index, and nothing on this side reports
// that gap. Unlike provenance these have real successors, so they point at the
// topic rather than at the index.
ok( '/tag/cryptographic-signatures/' === sn_notes_retired_tag_target( 'cryptography' ), 'cryptography points at its successor tag' );
ok( '/tag/music-metadata/' === sn_notes_retired_tag_target( 'music-identification' ), 'music-identification points at its successor tag' );

// NO CHAINS. A target that is itself retired sends a visitor through a second
// redirect, and a target that is retired to something retired can loop. This is
// the invariant that actually rots: retiring a tag later is a normal act, and
// nothing about doing it would prompt anyone to re-read this map.
$sn_map = sn_notes_retired_tags();
$sn_chained = array();
foreach ( $sn_map as $sn_from => $sn_to ) {
	if ( 1 === preg_match( '#^/tag/([a-z0-9-]+)/$#', $sn_to, $sn_m ) && isset( $sn_map[ $sn_m[1] ] ) ) {
		$sn_chained[] = $sn_from . ' -> ' . $sn_to;
	}
}
ok( array() === $sn_chained, 'no retired tag points at another retired tag' . ( $sn_chained ? ' — chains: ' . implode( ', ', $sn_chained ) : '' ) );

// Vacuity guard: the chain check above passes trivially on an empty map.
ok( count( $sn_map ) >= 3, 'the map is populated (' . count( $sn_map ) . ' entries), so the chain check is not vacuous' );

// Every target is a rooted path, never an absolute URL: sn_notes_retired_tag_target()
// output is fed to home_url(), and an absolute URL there would produce a broken
// double-origin redirect.
$sn_bad = array();
foreach ( $sn_map as $sn_from => $sn_to ) {
	if ( '/' !== substr( $sn_to, 0, 1 ) || false !== strpos( $sn_to, '://' ) ) {
		$sn_bad[] = $sn_from;
	}
}
ok( array() === $sn_bad, 'every target is a rooted path, not an absolute URL' );

// THE MAP IS THE POINT. The vocabulary went 83 -> 23 once and split again here;
// the next retirement must be a line in the array, never a second function.
$sn_tpl = (string) file_get_contents( __DIR__ . '/../inc/page-notes-template.php' );
ok( 1 === preg_match( '/function sn_notes_retired_tags\(\)/', $sn_tpl ), 'retirements live in a map' );
ok( 1 === preg_match( "/'provenance'\s*=>\s*'\/notes\/'/", $sn_tpl ), 'and provenance is a row in it' );

// Matched on the REQUEST PATH, not a queried object: once the term is deleted
// WordPress resolves nothing and would 404 before any is_tag() branch fires.
ok( 1 === preg_match( '#\^/tag/\(\[a-z0-9-\]\+\)/\?\$#', $sn_tpl ), 'the route matches the path, so the URL outlives the term' );
ok( 1 === preg_match( '/wp_safe_redirect\( home_url\( \$sn_gone \), 301 \)/', $sn_tpl ), '301 — the term is gone permanently' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
