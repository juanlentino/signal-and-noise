<?php
/**
 * Standalone fixture tests for the pillar shortcode helper + render_block bridge.
 * Stubs WP fns; tests tag→pillar mapping, core's template-part resolution, the bridge.
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

$GLOBALS['__test_post']    = null;
$GLOBALS['__test_tags']    = array();
$GLOBALS['__test_filters'] = array();

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
if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( $hook, $cb, $priority = 10, $args = 1 ) {
		$GLOBALS['__test_filters'][ $hook ][] = $cb;
		return true;
	}
}
// Minimal stand-in for WP do_shortcode over the [sn_post_pillar] token.
if ( ! function_exists( 'do_shortcode' ) ) {
	function do_shortcode( $content ) {
		$cb = $GLOBALS['__test_shortcodes']['sn_post_pillar'] ?? null;
		return $cb ? preg_replace_callback( '/\[sn_post_pillar\]/', function () use ( $cb ) {
			return call_user_func( $cb );
		}, $content ) : $content;
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

// ─── Test 7: front-end contract — core do_shortcodes raw part markup before do_blocks (template-part.php; see §1.2) ─
echo "\nTest 7: template-part do_shortcode pass resolves the token\n";
$raw = "<!-- wp:shortcode -->\n[sn_post_pillar]\n<!-- /wp:shortcode -->";

_pf_post( 701, array( 'provenance' ) );
$rendered = do_shortcode( $raw );
pf_contains( $rendered, '/provenance/over-detection/', 'Test 7.1: provenance post → token resolves to pillar link' );
pf_eq( false, strpos( $rendered, '[sn_post_pillar]' ) !== false, 'Test 7.2: literal token does not survive the do_shortcode pass' );

_pf_post( 702, array( 'unrelated' ) );
$rendered = do_shortcode( $raw );
pf_eq( false, strpos( $rendered, '[sn_post_pillar]' ) !== false, 'Test 7.3: non-pillar post → token removed, no leak' );
pf_eq( false, strpos( $rendered, 'sn-post-frontmatter__pillar' ) !== false, 'Test 7.4: non-pillar post → no pillar link emitted' );

// ─── Test 8: render_block bridge (belt-and-suspenders; redundant on front end, kept for parity — added 2026-06-07) ─
echo "\nTest 8: render_block bridge resolves the token in block content\n";
pf_eq( true, function_exists( 'sn_post_pillar_render_block' ), 'Test 8.1: bridge function exists' );
pf_eq( true, in_array( 'sn_post_pillar_render_block', (array) ( $GLOBALS['__test_filters']['render_block'] ?? array() ), true ), 'Test 8.2: bridge registered on render_block hook' );
if ( function_exists( 'sn_post_pillar_render_block' ) ) {
	_pf_post( 801, array( 'provenance' ) );
	$out = sn_post_pillar_render_block( '<p>[sn_post_pillar]</p>', array() );
	pf_contains( $out, 'sn-post-frontmatter__pillar', 'Test 8.3: token in block content → bridge runs do_shortcode → pillar link' );
	pf_eq( false, strpos( $out, '[sn_post_pillar]' ) !== false, 'Test 8.4: token does not survive the bridge' );
	pf_eq( '<p>x</p>', sn_post_pillar_render_block( '<p>x</p>', array() ), 'Test 8.5: no token → content unchanged (strpos guard)' );
}

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
