<?php
/**
 * Standalone fixture tests for v9.3.0's pillar shortcode helper.
 *
 * Stubs get_post, wp_get_post_tags → returns whatever the test
 * fixture set. Tests the convention-based tag-to-pillar mapping.
 *
 * @since theme v9.3.0
 */

// SECURITY: Prevent web access. This file is a test fixture, not a runtime
// module. Direct HTTP GET would leak internal structure (function names,
// ability slugs, capability matrices). Allow only CLI / WP-CLI invocations.
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) {
    http_response_code( 404 );
    exit;
}

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/' );
}

$GLOBALS['__test_post'] = null;
$GLOBALS['__test_tags'] = array();

if ( ! function_exists( 'get_post' ) ) {
	function get_post() { return $GLOBALS['__test_post']; }
}
if ( ! function_exists( 'wp_get_post_tags' ) ) {
	function wp_get_post_tags( $post_id, $args = array() ) {
		return $GLOBALS['__test_tags'];
	}
}
if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( $url ) { return $url; }
}
if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $s ) { return $s; }
}
if ( ! function_exists( 'add_shortcode' ) ) {
	function add_shortcode( $tag, $callback ) {
		$GLOBALS['__test_shortcodes'][ $tag ] = $callback;
		return true;
	}
}

function _pf_post( $id, $tag_slugs ) {
	$post     = new stdClass();
	$post->ID = $id;
	$GLOBALS['__test_post'] = $post;
	$GLOBALS['__test_tags'] = (array) $tag_slugs;
}

function _pf_no_post() {
	$GLOBALS['__test_post'] = null;
	$GLOBALS['__test_tags'] = array();
}

require_once __DIR__ . '/../inc/post-frontmatter.php';

$pass = 0; $fail = 0;
function pf_eq( $e, $a, $msg ) {
	global $pass, $fail;
	if ( $e === $a ) { $pass++; echo "  PASS: $msg\n"; }
	else { $fail++; echo "  FAIL: $msg\n    Expected: " . var_export( $e, true ) . "\n    Actual:   " . var_export( $a, true ) . "\n"; }
}
function pf_contains( $haystack, $needle, $msg ) {
	global $pass, $fail;
	if ( false !== strpos( (string) $haystack, (string) $needle ) ) { $pass++; echo "  PASS: $msg\n"; }
	else { $fail++; echo "  FAIL: $msg (searching for '$needle')\n"; }
}

echo "Pillar shortcode test suite — theme v9.3.0\n";

// ─── Test 1: post with no tags → empty string ────────────────────────
echo "\nTest 1: no tags → empty\n";
_pf_post( 101, array() );
pf_eq( '', sn_post_pillar_shortcode(), 'Test 1.1: empty tags returns empty string' );

// ─── Test 2: post tagged with non-pillar slug → empty string ─────────
echo "\nTest 2: non-pillar tag → empty\n";
_pf_post( 102, array( 'random', 'unrelated' ) );
pf_eq( '', sn_post_pillar_shortcode(), 'Test 2.1: non-pillar tags return empty string' );

// ─── Test 3: post tagged "provenance" → returns pillar HTML ──────────
echo "\nTest 3: provenance tag → pillar HTML\n";
_pf_post( 103, array( 'provenance' ) );
$result = sn_post_pillar_shortcode();
pf_contains( $result, 'sn-post-frontmatter__pillar', 'Test 3.1: result has pillar className' );
pf_contains( $result, '/provenance/over-detection/', 'Test 3.2: result links to canonical provenance essay' );
pf_contains( $result, 'PROVENANCE', 'Test 3.3: result shows uppercase pillar label' );

// ─── Test 4: post with mixed tags including "provenance" → returns ──
echo "\nTest 4: mixed tags with provenance → pillar HTML\n";
_pf_post( 104, array( 'foo', 'bar', 'provenance', 'baz' ) );
$result = sn_post_pillar_shortcode();
pf_contains( $result, 'PROVENANCE', 'Test 4.1: provenance tag detected among mixed tags' );

// ─── Test 5: null post → empty string ────────────────────────────────
echo "\nTest 5: null post → empty\n";
_pf_no_post();
pf_eq( '', sn_post_pillar_shortcode(), 'Test 5.1: get_post() returning null → empty string' );

// ─── Test 6: shortcode registered ────────────────────────────────────
echo "\nTest 6: add_shortcode registered\n";
pf_eq( true, isset( $GLOBALS['__test_shortcodes']['sn_post_pillar'] ), 'Test 6.1: sn_post_pillar shortcode registered' );
pf_eq( 'sn_post_pillar_shortcode', $GLOBALS['__test_shortcodes']['sn_post_pillar'], 'Test 6.2: shortcode callback name correct' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
