<?php
/**
 * Standalone fixture tests for /notes pagination helpers (Release 1).
 *
 * Stubs apply_filters + get_query_var so the pure helpers in
 * inc/page-notes-render.php can be exercised without a WP load.
 *
 * @since theme v9.6.0
 */

// SECURITY: Prevent web access. Test fixture, not a runtime module.
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) {
	http_response_code( 404 );
	exit;
}

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/' );
}

// ── Controllable stub state ──
$GLOBALS['__filters']    = array(); // filter name => return value
$GLOBALS['__query_vars'] = array(); // var name => value

if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $hook, $value ) {
		return array_key_exists( $hook, $GLOBALS['__filters'] )
			? $GLOBALS['__filters'][ $hook ]
			: $value;
	}
}
if ( ! function_exists( 'get_query_var' ) ) {
	function get_query_var( $var, $default = '' ) {
		return $GLOBALS['__query_vars'][ $var ] ?? $default;
	}
}
if ( ! function_exists( 'sanitize_text_field' ) ) {
	// v11.10.0: the fixture now drives SEARCH mode (to pin that filtered views
	// still paginate), which reaches sn_notes_search_term()'s non-empty branch.
	// Browse mode short-circuits before these, which is why they were never
	// needed here before.
	function sanitize_text_field( $str ) { return trim( wp_strip_all_tags( (string) $str ) ); }
}
if ( ! function_exists( 'wp_unslash' ) ) {
	function wp_unslash( $v ) { return is_string( $v ) ? stripslashes( $v ) : $v; }
}
if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( $str ) { return strip_tags( (string) $str ); }
}

// Capture the args WP_Query is constructed with.
$GLOBALS['__wpquery_args'] = null;
if ( ! class_exists( 'WP_Query' ) ) {
	class WP_Query {
		public $args;
		public function __construct( $args = array() ) {
			$args['_constructed'] = true;
			$GLOBALS['__wpquery_args'] = $args;
			$this->args = $args;
		}
	}
}

// Stubs for the hero-stats helper (date formatting of the newest note).
if ( ! function_exists( 'get_the_time' ) ) {
	function get_the_time( $format, $post ) { return $GLOBALS['__the_time'] ?? 1700000000; }
}
if ( ! function_exists( 'wp_date' ) ) {
	function wp_date( $format, $ts = null ) { return date( $format, $ts ?? 1700000000 ); }
}

// Pull in ONLY the helper functions. v10.49.0: they live in their own
// always-loadable module now (extracted from page-notes-render.php, which
// is render-path only; the SN_NOTES_RENDER_TEST sentinel is retired).
require __DIR__ . '/../inc/notes-index-helpers.php';

$pass = 0; $fail = 0;
function ok( $cond, $label ) {
	global $pass, $fail;
	if ( $cond ) { $pass++; echo "PASS: $label\n"; }
	else { $fail++; echo "FAIL: $label\n"; }
}

// ── sn_notes_per_page(): default + clamp ──
$GLOBALS['__filters'] = array();
ok( sn_notes_per_page() === 20, 'default per-page is 20 when no filter' );

$GLOBALS['__filters'] = array( 'sn_notes_per_page' => 5 );
ok( sn_notes_per_page() === 5, 'filter override respected (5)' );

$GLOBALS['__filters'] = array( 'sn_notes_per_page' => 0 );
ok( sn_notes_per_page() === 1, 'clamp floor: 0 -> 1' );

$GLOBALS['__filters'] = array( 'sn_notes_per_page' => 999 );
ok( sn_notes_per_page() === 100, 'clamp ceiling: 999 -> 100' );

$GLOBALS['__filters'] = array( 'sn_notes_per_page' => '15abc' );
ok( sn_notes_per_page() === 15, 'cast non-int return to int (15abc -> 15)' );

// ── sn_notes_current_page(): query var + $_GET fallback + floor ──
$GLOBALS['__query_vars'] = array(); unset( $_GET['paged'] );
ok( sn_notes_current_page() === 1, 'default page is 1 (nothing set)' );

$GLOBALS['__query_vars'] = array( 'paged' => 3 ); unset( $_GET['paged'] );
ok( sn_notes_current_page() === 3, 'reads get_query_var(paged)=3' );

$GLOBALS['__query_vars'] = array(); $_GET['paged'] = '2';
ok( sn_notes_current_page() === 2, 'falls back to $_GET[paged]=2 when query var is 0/empty' );

$GLOBALS['__query_vars'] = array( 'paged' => 0 ); $_GET['paged'] = '4';
ok( sn_notes_current_page() === 4, 'query var 0 -> uses $_GET fallback (4)' );

$GLOBALS['__query_vars'] = array(); $_GET['paged'] = '-5';
ok( sn_notes_current_page() === 1, 'floor at 1 for negative $_GET' );
unset( $_GET['paged'] );

// ── sn_notes_query_posts(): pagination args ──
$GLOBALS['__filters'] = array(); $GLOBALS['__query_vars'] = array( 'paged' => 2 ); unset( $_GET['paged'] );
$q = sn_notes_query_posts();
$a = $GLOBALS['__wpquery_args'];
// v11.10.0: BROWSE mode returns the whole corpus and drops pagination — the
// year spine bounds the visible page instead, so paging would only re-hide what
// the spine already folds. These two assertions inverted deliberately; the
// per-page contract below still holds for FILTERED modes, which have no spine.
ok( $a['posts_per_page'] === -1, 'browse mode fetches the whole corpus (no pagination)' );
ok( $a['paged'] === 1,           'browse mode ignores paged — there is only one page' );
ok( $a['no_found_rows'] === false, 'no_found_rows is false (pagination needs found_posts)' );
ok( $a['post_status'] === 'publish', 'still publish-only (scheduled excluded)' );
ok( $a['post_type'] === 'post',  'still post_type=post' );

// The sn_notes_per_page filter (Release 2 contract) still governs FILTERED
// modes. Driven through search, because browse no longer paginates at all —
// asserting the filter against browse would pin a path that cannot use it.
$GLOBALS['__filters'] = array( 'sn_notes_per_page' => 10 ); $GLOBALS['__query_vars'] = array( 's' => 'provenance', 'paged' => 3 );
sn_notes_query_posts();
ok( $GLOBALS['__wpquery_args']['posts_per_page'] === 10, 'filter override flows into a FILTERED query (10)' );
ok( $GLOBALS['__wpquery_args']['paged'] === 3, 'filtered mode still paginates (paged=3)' );

// The negative control for the split: same filter, browse mode, and the
// per-page value must NOT be honoured — otherwise "browse is unpaginated" is
// only true until someone sets the filter.
$GLOBALS['__filters'] = array( 'sn_notes_per_page' => 10 ); $GLOBALS['__query_vars'] = array( 'paged' => 3 );
sn_notes_query_posts();
ok( $GLOBALS['__wpquery_args']['posts_per_page'] === -1, 'browse ignores the per-page filter (spine bounds the page, not paging)' );

// ── sn_notes_hero_stats(): corpus total, not the page slice ──
ok( function_exists( 'sn_notes_hero_stats' ), 'sn_notes_hero_stats() is defined' );

// Page 1: count = found_posts (corpus), latest_date = newest (posts[0], free).
$GLOBALS['__query_vars'] = array( 'paged' => 1 ); unset( $_GET['paged'] );
$q1 = new stdClass();
$q1->found_posts = 42;     // whole corpus
$q1->post_count  = 20;     // this page's slice (the old, buggy source)
$q1->posts       = array( (object) array( 'ID' => 1 ) );
$h1 = sn_notes_hero_stats( $q1 );
ok( $h1['count'] === 42, 'hero count is found_posts (corpus total), NOT post_count (page slice)' );
ok( $h1['latest_date'] === date( 'Y.m.d', 1700000000 ), 'hero latest_date set on page 1 (posts[0] is the newest)' );

// Page 3: count still the corpus total; latest_date suppressed (posts[0] here is
// the page's first row, NOT the corpus newest — showing it would mislabel "Last updated").
$GLOBALS['__query_vars'] = array( 'paged' => 3 ); unset( $_GET['paged'] );
$q3 = new stdClass();
$q3->found_posts = 42;
$q3->post_count  = 2;      // a short final page
$q3->posts       = array( (object) array( 'ID' => 41 ) );
$h3 = sn_notes_hero_stats( $q3 );
ok( $h3['count'] === 42, 'hero count is found_posts on page 3 too (not the 2-item page slice)' );
ok( $h3['latest_date'] === '', 'hero latest_date suppressed on page 3 (avoids a wrong "Last updated")' );

// Zero results: count 0, no date, no crash on empty posts.
$GLOBALS['__query_vars'] = array(); unset( $_GET['paged'] );
$q0 = new stdClass();
$q0->found_posts = 0;
$q0->post_count  = 0;
$q0->posts       = array();
$h0 = sn_notes_hero_stats( $q0 );
ok( $h0['count'] === 0 && $h0['latest_date'] === '', 'hero zero results: count 0, no date' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
