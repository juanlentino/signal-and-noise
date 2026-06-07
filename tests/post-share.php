<?php
/**
 * Standalone fixture tests for the copy-permalink + Web Share row (v9.10.0).
 *
 * Stubs the WP primitives the [sn_note_share] shortcode touches
 * (is_singular / get_the_queried_object_id / get_permalink / get_the_title
 * / escaping) so the pure helper in inc/post-share.php runs without a
 * WordPress load. Mirrors tests/related-notes.php.
 *
 * The JS layer (assets/js/note-share.js) is progressive enhancement and
 * verified by manual UAT — these tests cover the server-rendered,
 * valid-by-construction markup contract only.
 *
 * @since theme v9.10.0
 */

// SECURITY: Prevent web access. Test fixture, not a runtime module.
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) {
	http_response_code( 404 );
	exit;
}

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/' );
}

// ── Controllable stub state ──────────────────────────────────────────
$GLOBALS['__is_singular_post'] = false; // is_singular('post') return.
$GLOBALS['__queried_id']       = 0;     // get_the_queried_object_id() return.
$GLOBALS['__permalink']        = '';    // get_permalink() return.
$GLOBALS['__title']            = '';    // get_the_title() return.

if ( ! function_exists( 'is_singular' ) ) {
	function is_singular( $types = '' ) {
		// Only the 'post' query is meaningful for this module.
		if ( 'post' === $types || ( is_array( $types ) && in_array( 'post', $types, true ) ) ) {
			return (bool) $GLOBALS['__is_singular_post'];
		}
		return (bool) $GLOBALS['__is_singular_post'];
	}
}
if ( ! function_exists( 'get_the_queried_object_id' ) ) {
	function get_the_queried_object_id() {
		return (int) $GLOBALS['__queried_id'];
	}
}
if ( ! function_exists( 'get_permalink' ) ) {
	function get_permalink( $post = 0 ) {
		return $GLOBALS['__permalink'];
	}
}
if ( ! function_exists( 'get_the_title' ) ) {
	function get_the_title( $post = 0 ) {
		return $GLOBALS['__title'];
	}
}
if ( ! function_exists( 'esc_url' ) ) {
	// Mirror the load-bearing part of WP's esc_url: strip characters that
	// would break out of an HTML attribute (quotes, angle brackets, spaces).
	function esc_url( $u ) {
		$u = (string) $u;
		$u = str_replace( array( '"', "'", '<', '>', ' ' ), '', $u );
		return str_replace( '&', '&amp;', $u );
	}
}
if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $s ) {
		return htmlspecialchars( (string) $s, ENT_QUOTES );
	}
}
if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $s ) {
		return htmlspecialchars( (string) $s, ENT_QUOTES );
	}
}
if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( $s, $d = 'default' ) {
		return htmlspecialchars( (string) $s, ENT_QUOTES );
	}
}
if ( ! function_exists( 'do_shortcode' ) ) {
	// Faithful enough to catch FIX 2: substitutes the real shortcode output
	// (a block-level <div>) where the literal token sits, so a stray <p>
	// wrapper around it is observable.
	function do_shortcode( $content ) {
		$GLOBALS['__do_shortcode_ran'] = true;
		if ( false !== strpos( $content, '[sn_note_share]' ) ) {
			$content = str_replace( '[sn_note_share]', sn_note_share_shortcode(), $content );
		}
		return $content;
	}
}
if ( ! function_exists( 'wp_spaces_regexp' ) ) {
	function wp_spaces_regexp() {
		return '[\r\n\t ]|\xC2\xA0|&nbsp;';
	}
}
// Real shortcode_unautop from WP trunk wp-includes/formatting.php.
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

define( 'SN_POST_SHARE_TEST', true );
require __DIR__ . '/../inc/post-share.php';

// shortcode_unautop() recognises only REGISTERED shortcodes (reads
// $shortcode_tags). Register the token the way the runtime does.
$GLOBALS['shortcode_tags']['sn_note_share'] = 'sn_note_share_shortcode';

$pass = 0; $fail = 0;
function ok( $cond, $label ) {
	global $pass, $fail;
	if ( $cond ) { $pass++; echo "PASS: $label\n"; }
	else { $fail++; echo "FAIL: $label\n"; }
}

// ── OFF singular-post → '' ────────────────────────────────────────────
$GLOBALS['__is_singular_post'] = false;
$GLOBALS['__queried_id']       = 1;
$GLOBALS['__permalink']        = 'https://x/notes/1/';
$GLOBALS['__title']            = 'A note';
ok( sn_note_share_shortcode() === '', 'returns "" when not is_singular(post)' );

// ── ON singular-post → emits both buttons ─────────────────────────────
$GLOBALS['__is_singular_post'] = true;
$GLOBALS['__queried_id']       = 1;
$GLOBALS['__permalink']        = 'https://x/notes/provenance/';
$GLOBALS['__title']            = 'Provenance & Noise';
$html = sn_note_share_shortcode();

ok( $html !== '', 'emits markup on singular post' );
ok( strpos( $html, 'sn-note-share' ) !== false, 'wrapper class present' );
ok( strpos( $html, 'sn-note-share__copy' ) !== false, 'COPY button class present' );
ok( strpos( $html, 'sn-note-share__native' ) !== false, 'SHARE button class present' );
ok( substr_count( $html, 'type="button"' ) === 2, 'both buttons are type=button (no form submit)' );

// data-attrs carry the permalink + title for the JS layer.
ok( strpos( $html, 'data-sn-share-url="https://x/notes/provenance/"' ) !== false, 'copy button carries data-sn-share-url permalink' );
ok( strpos( $html, 'data-sn-share-title="Provenance &amp; Noise"' ) !== false, 'copy button carries esc_attr title (ampersand encoded)' );

// SHARE button starts hidden (revealed only when navigator.share exists).
ok( preg_match( '/<button[^>]*sn-note-share__native[^>]*\bhidden\b/', $html ) === 1, 'SHARE button is hidden by default' );
ok( strpos( $html, 'COPY LINK' ) !== false, 'COPY LINK label present' );
ok( strpos( $html, 'SHARE' ) !== false, 'SHARE label present' );

// No raw HTML leaks from a hostile title.
$GLOBALS['__title']     = 'Bad <b>note</b>';
$GLOBALS['__permalink'] = 'https://x/"onmouseover="alert(1)';
$evil = sn_note_share_shortcode();
ok( strpos( $evil, '<b>note</b>' ) === false, 'title is escaped — no raw <b> leaks' );
ok( strpos( $evil, '"onmouseover="alert(1)' ) === false, 'permalink is esc_url\'d — quote breakout stripped' );

// ── BRIDGE: feed a wpautop-shaped input through the REAL bridge ───────
// core/shortcode wpautop()s the bare token into "<p>[sn_note_share]</p>".
// The bridge must shortcode_unautop() before do_shortcode (FIX 2) so the
// block-level <div> isn't emitted wrapped in an invalid <p>.
$GLOBALS['__is_singular_post'] = true;
$GLOBALS['__queried_id']       = 1;
$GLOBALS['__permalink']        = 'https://x/notes/1/';
$GLOBALS['__title']            = 'A note';
$GLOBALS['__do_shortcode_ran'] = false;

$out = sn_note_share_render_block_bridge( '<p>[sn_note_share]</p>', array() );
ok( ! empty( $GLOBALS['__do_shortcode_ran'] ), 'bridge: do_shortcode runs when token present' );
ok( strpos( $out, 'class="sn-note-share"' ) !== false, 'bridge: token resolved to the real <div> output' );
ok( strpos( $out, '[sn_note_share]' ) === false, 'bridge: raw token gone after resolution' );
// FIX 2 — the block-level <div> must NOT be wrapped in a <p>.
ok( strpos( $out, '<p><div' ) === false, 'FIX 2: <div> not directly wrapped in <p>' );
ok( strpos( $out, '<p>' ) === false, 'FIX 2: no leftover <p> wrapping the block-level output at all' );

$GLOBALS['__do_shortcode_ran'] = false;
$untouched = sn_note_share_render_block_bridge( '<p>no token here</p>', array() );
ok( empty( $GLOBALS['__do_shortcode_ran'] ), 'bridge: do_shortcode NOT run when token absent' );
ok( $untouched === '<p>no token here</p>', 'bridge: content returned unchanged when token absent' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
