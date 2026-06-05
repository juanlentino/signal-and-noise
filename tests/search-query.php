<?php
/**
 * Standalone fixture tests for on-site search query-vars injection.
 *
 * Stubs is_search() + get_search_query() + add_filter so the pure
 * functions in inc/search-query.php run without a WP load.
 *
 * @since theme v9.7.0
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
$GLOBALS['__is_search']    = false;
$GLOBALS['__search_query'] = '';

if ( ! function_exists( 'is_search' ) ) {
	function is_search() {
		return (bool) $GLOBALS['__is_search'];
	}
}
if ( ! function_exists( 'get_search_query' ) ) {
	// Real signature is get_search_query( $escaped = true ); the filter
	// calls it with false to get the raw term. Stub returns raw either way.
	function get_search_query( $escaped = true ) {
		return $GLOBALS['__search_query'];
	}
}
if ( ! function_exists( 'add_filter' ) ) {
	// No-op: the file calls add_filter() at load; we invoke the callback directly.
	function add_filter( $hook, $cb, $priority = 10, $args = 1 ) {
		return true;
	}
}

require __DIR__ . '/../inc/search-query.php';

$pass = 0; $fail = 0;
function ok( $cond, $label ) {
	global $pass, $fail;
	if ( $cond ) { $pass++; echo "PASS: $label\n"; }
	else { $fail++; echo "FAIL: $label\n"; }
}

// Build a post-template-like block carrying the query context.
function sn_mk_block( $post_type ) {
	return (object) array( 'context' => array( 'query' => array( 'postType' => $post_type ) ) );
}

$base = array( 'post_type' => 'post', 'order' => 'DESC' );

// ── discriminator: sn_is_search_loop() ──
ok( sn_is_search_loop( sn_mk_block( 'post' ) ) === true,  'discriminator true for postType post' );
ok( sn_is_search_loop( sn_mk_block( 'page' ) ) === true,  'discriminator true for postType page' );
ok( sn_is_search_loop( sn_mk_block( '' ) ) === false,     'discriminator false for empty postType' );
ok( sn_is_search_loop( sn_mk_block( 'attachment' ) ) === false, 'discriminator false for other postType' );
ok( sn_is_search_loop( 'not-a-block' ) === false,         'discriminator false for non-object' );

// ── filter: sn_search_inject_term() ──
$GLOBALS['__is_search'] = false; $GLOBALS['__search_query'] = 'provenance';
$out = sn_search_inject_term( $base, sn_mk_block( 'post' ) );
ok( ! isset( $out['s'] ), 'no s injected when not a search page' );

$GLOBALS['__is_search'] = true;
$out = sn_search_inject_term( $base, sn_mk_block( 'post' ) );
ok( ( $out['s'] ?? null ) === 'provenance', 'post loop on search page gets s' );

$out = sn_search_inject_term( $base, sn_mk_block( 'page' ) );
ok( ( $out['s'] ?? null ) === 'provenance', 'page loop on search page gets s' );

$out = sn_search_inject_term( $base, sn_mk_block( 'attachment' ) );
ok( ! isset( $out['s'] ), 'non-post/page loop on a search page is untouched' );

$out = sn_search_inject_term( $base, sn_mk_block( 'post' ) );
ok( $out['post_type'] === 'post' && $out['order'] === 'DESC', 'existing query args preserved' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
