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
	function get_post_meta( $id, $key, $single ) { return 0; }
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

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
