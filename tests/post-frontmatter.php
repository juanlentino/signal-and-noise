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
		$GLOBALS['__test_shortcodes'][ $tag ]      = $callback;
		$GLOBALS['shortcode_tags'][ $tag ]         = $callback; // shortcode_unautop reads this.
		return true;
	}
}
if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {
		$GLOBALS['__test_filters'][ $hook ][] = $callback;
		return true;
	}
}
if ( ! function_exists( 'do_shortcode' ) ) {
	// Resolve only the registered token, mirroring real do_shortcode for the
	// single token this fixture exercises.
	function do_shortcode( $content ) {
		if ( false !== strpos( $content, '[sn_post_pillar]' ) ) {
			return str_replace( '[sn_post_pillar]', sn_post_pillar_shortcode(), $content );
		}
		return $content;
	}
}
if ( ! function_exists( 'wp_spaces_regexp' ) ) {
	function wp_spaces_regexp() {
		return '[\r\n\t ]|\xC2\xA0|&nbsp;';
	}
}
// Real shortcode_unautop from WP trunk wp-includes/formatting.php — strips a
// <p> wrapper around a registered shortcode token. Reads $shortcode_tags.
if ( ! function_exists( 'shortcode_unautop' ) ) {
	function shortcode_unautop( $text ) {
		global $shortcode_tags;
		if ( empty( $shortcode_tags ) || ! is_array( $shortcode_tags ) ) {
			return $text;
		}
		$tagregexp = implode( '|', array_map( 'preg_quote', array_keys( $shortcode_tags ) ) );
		$spaces    = wp_spaces_regexp();
		$pattern   =
			'/'
			. '<p>'
			. '(?:' . $spaces . ')*+'
			. '('
			.     '\\['
			.     "($tagregexp)"
			.     '(?![\\w-])'
			.     '[^\\]\\/]*'
			.     '(?:'
			.         '\\/(?!\\])'
			.         '[^\\]\\/]*'
			.     ')*?'
			.     '(?:'
			.         '\\/\\]'
			.     '|'
			.         '\\]'
			.         '(?:'
			.             '[^\\[]*+'
			.             '(?:'
			.                 '\\[(?!\\/\\2\\])'
			.                 '[^\\[]*+'
			.             ')*+'
			.             '\\[\\/\\2\\]'
			.         ')?'
			.     ')'
			. ')'
			. '(?:' . $spaces . ')*+'
			. '<\\/p>'
			. '/';
		return preg_replace( $pattern, '$1', $text );
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

define( 'SN_POST_FRONTMATTER_TEST', true );
require_once __DIR__ . '/../inc/post-frontmatter.php';

// Exercise the real registration path the runtime takes (the require above is
// guarded by SN_POST_FRONTMATTER_TEST, so register explicitly here). This also
// populates $shortcode_tags so shortcode_unautop() recognises the token.
add_shortcode( 'sn_post_pillar', 'sn_post_pillar_shortcode' );
add_filter( 'render_block', 'sn_post_pillar_render_block_bridge', 10, 2 );

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

// ─── Test 6: shortcode + render_block bridge registered ──────────────
echo "\nTest 6: add_shortcode + add_filter registered\n";
pf_eq( true, isset( $GLOBALS['__test_shortcodes']['sn_post_pillar'] ), 'Test 6.1: sn_post_pillar shortcode registered' );
pf_eq( 'sn_post_pillar_shortcode', $GLOBALS['__test_shortcodes']['sn_post_pillar'], 'Test 6.2: shortcode callback name correct' );
pf_eq(
	true,
	in_array( 'sn_post_pillar_render_block_bridge', $GLOBALS['__test_filters']['render_block'] ?? array(), true ),
	'Test 6.3: render_block bridge registered (token was rendering RAW without it)'
);

// ─── Test 7: render_block bridge resolves the token ──────────────────
echo "\nTest 7: render_block bridge\n";
_pf_post( 701, array( 'provenance' ) );
$bridged = sn_post_pillar_render_block_bridge( '<p>[sn_post_pillar]</p>', array() );
pf_contains( $bridged, 'sn-post-frontmatter__pillar', 'Test 7.1: bridge resolves [sn_post_pillar] to its rendered HTML' );
pf_eq( false, strpos( $bridged, '[sn_post_pillar]' ) !== false, 'Test 7.2: raw token no longer present after bridge' );

// Token absent → content returned untouched.
$untouched = sn_post_pillar_render_block_bridge( '<p>no token</p>', array() );
pf_eq( '<p>no token</p>', $untouched, 'Test 7.3: content unchanged when token absent' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
