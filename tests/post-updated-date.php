<?php
/**
 * Standalone fixture tests for the "Updated YYYY.MM.DD" frontmatter line.
 *
 * Stubs the WP functions sn_post_updated_display() touches so the pure
 * helper in inc/post-updated-date.php runs without a WP load. Mirrors the
 * stub pattern in tests/related-notes.php / tests/notes-search.php.
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
if ( ! defined( 'DAY_IN_SECONDS' ) ) {
	define( 'DAY_IN_SECONDS', 86400 );
}

// ── Controllable stub state ──
$GLOBALS['__filters']        = array();   // hook => value override (apply_filters)
$GLOBALS['__post']           = null;      // object returned by get_post()
$GLOBALS['__timestamps']     = array();   // field => unix ts (get_post_timestamp)

if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $hook, $value ) {
		return array_key_exists( $hook, $GLOBALS['__filters'] )
			? $GLOBALS['__filters'][ $hook ]
			: $value;
	}
}
if ( ! function_exists( 'get_post' ) ) {
	function get_post( $post = null ) {
		// Mirror core: explicit arg passes through; null falls back to global fixture.
		if ( null !== $post ) {
			return $post;
		}
		return $GLOBALS['__post'];
	}
}
if ( ! function_exists( 'get_post_timestamp' ) ) {
	function get_post_timestamp( $post = null, $field = 'date' ) {
		if ( ! isset( $GLOBALS['__timestamps'][ $field ] ) ) {
			return false;
		}
		return $GLOBALS['__timestamps'][ $field ];
	}
}
if ( ! function_exists( 'wp_date' ) ) {
	// Deterministic UTC formatter — no site timezone in the harness.
	function wp_date( $format, $timestamp = null ) {
		return gmdate( $format, null === $timestamp ? time() : $timestamp );
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

define( 'SN_POST_UPDATED_DATE_TEST', true );
require __DIR__ . '/../inc/post-updated-date.php';

// shortcode_unautop() recognises only REGISTERED shortcodes (reads
// $shortcode_tags). Register the token the way the runtime does.
$GLOBALS['shortcode_tags']['sn_updated_date'] = 'sn_updated_date_shortcode';

$pass = 0; $fail = 0;
function ok( $cond, $label ) {
	global $pass, $fail;
	if ( $cond ) { $pass++; echo "PASS: $label\n"; }
	else { $fail++; echo "FAIL: $label\n"; }
}

// Fixed reference instant: 2026-06-01 12:00:00 UTC.
$published = 1748779200; // gmdate('Y.m.d') == 2026.06.01

// ── No post → empty ──
$GLOBALS['__post'] = null;
$GLOBALS['__timestamps'] = array();
ok( sn_post_updated_display( null ) === '', 'no post -> empty string' );

// ── Below threshold (modified 5 days after publish, default 14d) -> empty ──
$post = (object) array( 'ID' => 7 );
$GLOBALS['__timestamps'] = array(
	'date'     => $published,
	'modified' => $published + ( 5 * DAY_IN_SECONDS ),
);
ok( sn_post_updated_display( $post ) === '', 'below 14d threshold -> empty string' );

// ── At/above threshold (modified 20 days after publish) -> rendered ──
$modified = $published + ( 20 * DAY_IN_SECONDS ); // 2026-06-21
$GLOBALS['__timestamps'] = array(
	'date'     => $published,
	'modified' => $modified,
);
$out = sn_post_updated_display( $post );
ok( $out !== '', 'above 14d threshold -> non-empty' );
ok( strpos( $out, 'Updated ' ) !== false, 'output contains "Updated "' );
ok( strpos( $out, gmdate( 'Y.m.d', $modified ) ) !== false, 'output contains modified Y.m.d (2026.06.21)' );
ok( strpos( $out, 'class="sn-post-frontmatter__updated"' ) !== false, 'output carries the frontmatter__updated class' );

// ── datetime attribute present + ISO-8601 (wp_date c) ──
ok( preg_match( '/datetime="[^"]+"/', $out ) === 1, 'output has a non-empty datetime attribute' );
ok( strpos( $out, 'datetime="' . gmdate( 'c', $modified ) . '"' ) !== false, 'datetime attr = ISO-8601 modified time' );

// ── Exact boundary: delta == threshold should render (>= threshold) ──
$GLOBALS['__timestamps'] = array(
	'date'     => $published,
	'modified' => $published + ( 14 * DAY_IN_SECONDS ),
);
ok( sn_post_updated_display( $post ) !== '', 'delta == threshold (14d) -> rendered (inclusive boundary)' );

// ── Just under boundary (14d minus 1s) -> empty ──
$GLOBALS['__timestamps'] = array(
	'date'     => $published,
	'modified' => $published + ( 14 * DAY_IN_SECONDS ) - 1,
);
ok( sn_post_updated_display( $post ) === '', 'delta == threshold-1s -> empty' );

// ── Filter overrides the threshold (drop to 3 days; a 5d delta now renders) ──
$GLOBALS['__filters'] = array( 'sn_updated_date_threshold_days' => 3 );
$mod3 = $published + ( 5 * DAY_IN_SECONDS );
$GLOBALS['__timestamps'] = array(
	'date'     => $published,
	'modified' => $mod3,
);
ok( sn_post_updated_display( $post ) !== '', 'filter lowers threshold to 3d -> 5d delta now renders' );

// ── Filter raising the threshold suppresses a previously-shown date ──
$GLOBALS['__filters'] = array( 'sn_updated_date_threshold_days' => 60 );
$GLOBALS['__timestamps'] = array(
	'date'     => $published,
	'modified' => $published + ( 20 * DAY_IN_SECONDS ),
);
ok( sn_post_updated_display( $post ) === '', 'filter raises threshold to 60d -> 20d delta suppressed' );
$GLOBALS['__filters'] = array();

// ── Shortcode delegates to the helper for the current post ──
$GLOBALS['__post'] = $post;
$GLOBALS['__timestamps'] = array(
	'date'     => $published,
	'modified' => $modified,
);
$sc = sn_updated_date_shortcode();
ok( strpos( $sc, 'Updated ' ) !== false, 'shortcode renders the helper output for get_post()' );

// ── render_block bridge: faithful do_shortcode resolving the real token ──
// Faithful enough to catch FIX 2: substitutes the REAL shortcode output
// (which is '' below threshold, a <time> above it) at the token position, so
// both the empty-<p></p> collapse and the <p>-wrapped-<time> bug are visible.
$GLOBALS['__do_shortcode_ran'] = false;
if ( ! function_exists( 'do_shortcode' ) ) {
	function do_shortcode( $content ) {
		$GLOBALS['__do_shortcode_ran'] = true;
		if ( false !== strpos( $content, '[sn_updated_date]' ) ) {
			$content = str_replace( '[sn_updated_date]', sn_updated_date_shortcode(), $content );
		}
		return $content;
	}
}

// Above threshold → a real <time> element. Must NOT be <p>-wrapped (FIX 2).
$GLOBALS['__post']       = $post;
$GLOBALS['__timestamps'] = array( 'date' => $published, 'modified' => $modified );
$resolved = sn_updated_date_render_block_bridge( '<p>[sn_updated_date]</p>', array() );
ok( ! empty( $GLOBALS['__do_shortcode_ran'] ), 'bridge: do_shortcode run when token present' );
ok( strpos( $resolved, '<time' ) !== false, 'bridge: token resolved to the real <time> output' );
ok( strpos( $resolved, '<p>' ) === false, 'FIX 2: <time> not left wrapped in a <p>' );

// Below threshold → empty shortcode output. The whole <p>[token]</p> must
// collapse to '' (FIX 2), not leave an empty <p></p>.
$GLOBALS['__timestamps'] = array( 'date' => $published, 'modified' => $published + ( 2 * DAY_IN_SECONDS ) );
$empty = sn_updated_date_render_block_bridge( '<p>[sn_updated_date]</p>', array() );
ok( $empty === '', 'FIX 2: empty render collapses to "" (no empty <p></p>)' );

$GLOBALS['__do_shortcode_ran'] = false;
$untouched = sn_updated_date_render_block_bridge( '<p>no token here</p>', array() );
ok( empty( $GLOBALS['__do_shortcode_ran'] ), 'bridge: do_shortcode NOT run when token absent' );
ok( $untouched === '<p>no token here</p>', 'bridge: content returned unchanged when token absent' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
