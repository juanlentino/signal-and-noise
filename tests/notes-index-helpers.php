<?php
/**
 * Standalone fixture tests for inc/notes-index-helpers.php (v10.49.0).
 *
 * The 13 pure /notes-index helpers moved OUT of inc/page-notes-render.php
 * (which is a full-page renderer that echoes HTML at load) into an
 * always-loadable module, retiring the SN_NOTES_RENDER_TEST mid-file
 * return hack. This fixture requires ONLY the new module — proving the
 * helpers no longer need the renderer (or any sentinel constant) to load —
 * and smoke-tests a few behaviors using the same stub patterns as
 * tests/notes-pagination.php.
 *
 * @since theme v10.49.0
 */

// SECURITY: Prevent web access. Test fixture, not a runtime module.
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) {
	http_response_code( 404 );
	exit;
}

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/' );
}

// ── Controllable stub state (mirrors tests/notes-pagination.php) ──
$GLOBALS['__filters']    = array();
$GLOBALS['__query_vars'] = array();

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
if ( ! function_exists( 'get_the_time' ) ) {
	function get_the_time( $format, $post ) { return 1700000000; }
}
if ( ! function_exists( 'wp_date' ) ) {
	function wp_date( $format, $ts = null ) { return date( $format, $ts ?? 1700000000 ); }
}
if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
}
if ( ! function_exists( 'home_url' ) ) {
	function home_url( $p = '' ) { return 'https://example.com' . $p; }
}
if ( ! function_exists( 'get_post_meta' ) ) {
	function get_post_meta( $id, $key, $single ) { return $GLOBALS['__meta'][ $id ][ $key ] ?? 0; }
}
if ( ! class_exists( 'WP_Query' ) ) {
	class WP_Query { public $args; public function __construct( $a = array() ) { $this->args = $a; } }
}
// v12.13.0: tag state, so the browse-vs-search distinction is drivable here.
// sn_notes_current_tag_id() reads is_tag() + get_queried_object(); without both
// the suite could only ever exercise the two states it already had, which is
// exactly why the tag/search conflation went unnoticed in the query builder.
$GLOBALS['__is_tag'] = false;
if ( ! function_exists( 'is_tag' ) ) {
	function is_tag() { return (bool) $GLOBALS['__is_tag']; }
}
if ( ! function_exists( 'get_queried_object' ) ) {
	function get_queried_object() { return $GLOBALS['__is_tag'] ? (object) array( 'term_id' => 7 ) : null; }
}
if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $v ) { return trim( strip_tags( (string) $v ) ); }
}
if ( ! function_exists( 'wp_unslash' ) ) {
	function wp_unslash( $v ) { return $v; }
}

// The whole point: ONLY the new module, no renderer, no sentinel constant.
require __DIR__ . '/../inc/notes-index-helpers.php';

$pass = 0; $fail = 0;
function ok( $cond, $label ) {
	global $pass, $fail;
	if ( $cond ) { $pass++; echo "PASS: $label\n"; }
	else { $fail++; echo "FAIL: $label\n"; }
}

// ── 1. Every extracted helper exists + is callable ──
$helpers = array(
	'sn_notes_render_date',
	'sn_notes_render_reading_time',
	'sn_notes_per_page',
	'sn_notes_current_page',
	'sn_notes_query_posts',
	'sn_notes_search_term',
	'sn_notes_pagination_add_args',
	'sn_notes_hero_stats',
	'sn_notes_sticky_ids',
	'sn_notes_current_tag_id',
	'sn_notes_is_published_post',
	'sn_notes_start_here_id',
	'sn_notes_pagination_base',
);
foreach ( $helpers as $fn ) {
	ok( function_exists( $fn ) && is_callable( $fn ), "helper $fn exists and is callable" );
}

// ── 2. Behavioral smoke asserts (fixture patterns from notes-pagination) ──
ok( 20 === sn_notes_per_page(), 'per-page default is 20' );
$GLOBALS['__filters']['sn_notes_per_page'] = 500;
ok( 100 === sn_notes_per_page(), 'per-page clamps a bad filter return to 100' );
unset( $GLOBALS['__filters']['sn_notes_per_page'] );

$GLOBALS['__query_vars']['paged'] = 0;
ok( 1 === sn_notes_current_page(), 'current page floors at 1' );

ok( date( 'Y.m.d', 1700000000 ) === html_entity_decode( sn_notes_render_date( (object) array() ) ), 'render_date formats Y.m.d from the post timestamp' );

ok( array() === sn_notes_pagination_add_args( '' ), 'no add_args when not searching' );
$args = sn_notes_pagination_add_args( 'two words' );
ok( isset( $args['s'] ) && 'two%20words' === $args['s'], 'search term is rawurlencoded into add_args' );

// ── v10.51.0: search covers the whole corpus, type-labeled ──
$GLOBALS['__query_vars']['s'] = 'signal';
$args = sn_notes_query_posts()->args;
ok( array( 'post', 'page' ) === ( $args['post_type'] ?? null ), 'search mode queries the whole corpus (post + page)' );
$GLOBALS['__query_vars']['s'] = '';
$args = sn_notes_query_posts()->args;
ok( 'post' === ( $args['post_type'] ?? null ), 'browse mode stays Notes-only by construction' );

// ── v11.4.6: CMA audit 2026-08-05 INFO-3 — protected posts stay off bulk corpus
// surfaces. Search mode renders get_the_excerpt(), which for a protected post is
// WP's "There is no excerpt because this is a protected post." placeholder, so no
// body ever leaked. Stating the gate at the query makes the convention uniform with
// inc/feed-json.php + inc/llms-txt.php and removes the placeholder rows entirely.
$GLOBALS['__query_vars']['s'] = 'signal';
$args = sn_notes_query_posts()->args;
ok( false === ( $args['has_password'] ?? null ), 'search mode excludes password-protected posts' );
$GLOBALS['__query_vars']['s'] = '';
$args = sn_notes_query_posts()->args;
ok( false === ( $args['has_password'] ?? null ), 'browse mode excludes password-protected posts' );

$note  = (object) array( 'ID' => 1, 'post_type' => 'post' );
$page_ = (object) array( 'ID' => 2, 'post_type' => 'page' );
$pill  = (object) array( 'ID' => 3, 'post_type' => 'page' );
$GLOBALS['__meta'] = array( 3 => array( '_sn_pillar' => '1' ) );
ok( 'Note' === sn_notes_result_type_label( $note ), 'posts label as Note' );
ok( 'Page' === sn_notes_result_type_label( $page_ ), 'plain pages label as Page' );
ok( 'Essay' === sn_notes_result_type_label( $pill ), 'pillar-designated pages label as Essay' );

// v10.51.1: the plugin owns robots emission (it removes core wp_robots), so
// this answers the plugin's sn_seo_robots_directives seam — a DIRECTIVE LIST,
// not wp_robots' map. The v10.51.0 map shape was pinning a contract that
// could never fire (live-verified inert).
$dirs = array( 'max-snippet:-1', 'max-image-preview:large' );
ok( $dirs === sn_notes_search_robots( $dirs, '' ), 'no term: directives untouched' );
$out = sn_notes_search_robots( $dirs, 'signal' );
ok( in_array( 'noindex', $out, true ) && in_array( 'follow', $out, true ), 'search mode: noindex + follow appended' );
ok( in_array( 'max-snippet:-1', $out, true ), 'existing directives preserved' );
ok( array_values( $out ) === $out, 'returns a list, the seam contract shape' );
ok( $dirs === array( 'max-snippet:-1', 'max-image-preview:large' ), 'input array not mutated' );


// ── v12.13.0: A TAG ARCHIVE BROWSES, IT DOES NOT SEARCH ────────────────────
// The query builder grouped tag with search and paginated both. The stated
// reason — "a search result set is unbounded by nature" — is true of search and
// false of a tag: 23 curated terms, the largest holding 26 of 38 notes, every
// member chosen. Only Provenance exceeded the 20 default, so this consolidated
// exactly one page; it is also the page carrying two thirds of the corpus, and
// page/2 was a weaker crawl path for it.
$GLOBALS['__query_vars']['s'] = '';
$GLOBALS['__is_tag'] = true;
$args = sn_notes_query_posts()->args;
ok( -1 === ( $args['posts_per_page'] ?? null ), 'a TAG archive returns every matching note in one response, like browse' );
ok( 1 === ( $args['paged'] ?? null ), 'and asks for page 1, so no page/2 exists to split the crawl' );
ok( 'post' === ( $args['post_type'] ?? null ), 'a tag stays Notes-only — it never widens to pages the way search does' );

$GLOBALS['__query_vars']['s'] = 'signal';
$args = sn_notes_query_posts()->args;
ok( sn_notes_per_page() === ( $args['posts_per_page'] ?? null ), 'SEARCH still paginates: an arbitrary string can return anything, and that is the case the rule was written for' );
$GLOBALS['__is_tag'] = false;
$GLOBALS['__query_vars']['s'] = '';
$args = sn_notes_query_posts()->args;
ok( -1 === ( $args['posts_per_page'] ?? null ), 'browse is unchanged' );

// The reason, pinned at the source: a comment that still called a tag unbounded
// would be the conflation growing back in prose after being removed in code.
$sn_src = (string) file_get_contents( __DIR__ . '/../inc/notes-index-helpers.php' );
ok( false !== strpos( $sn_src, 'A TAG ARCHIVE BROWSES, IT DOES NOT SEARCH' ), 'the query builder states WHY a tag is not a search' );
$sn_render = (string) file_get_contents( __DIR__ . '/../inc/page-notes-render.php' );
ok( 1 === preg_match( '/if \( \$sn_searching \) \{/', $sn_render ), 'the render branch keys on $sn_searching, so the year spine folds a tag archive too' );
ok( 1 === preg_match( '/\$sn_filtered\s*\?\s*0\s*:\s*sn_notes_start_here_id/', $sn_render ), 'and $sn_filtered still governs Start Here — "search OR tag" is right THERE, which is why it was not changed wholesale' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
