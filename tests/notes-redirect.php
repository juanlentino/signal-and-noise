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

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
