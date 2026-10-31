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
$GLOBALS['__meta'] = array();
if ( ! function_exists( 'get_post_meta' ) ) {
	function get_post_meta( $id, $key = '', $single = false ) { return $GLOBALS['__meta'][ $id ][ $key ] ?? ''; }
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


define( 'SN_POST_UPDATED_DATE_TEST', true );
require __DIR__ . '/../inc/post-updated-date.php';

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

// ── 13.2.7: the PROVENANCE COMMIT is the clock, post_modified the fallback ──
// A title-tag override bumped post_modified on every note (2026-09-17) and
// all 43 read "Updated" over unchanged prose. With a commit recorded, the
// line follows the commit; without one, post_modified as before.
$GLOBALS['__meta'] = array( 7 => array( '_sn_prov_last_commit_gmt' => gmdate( 'Y-m-d H:i:s', $published + ( 3 * DAY_IN_SECONDS ) ) ) );
$GLOBALS['__timestamps'] = array( 'date' => $published, 'modified' => $published + ( 40 * DAY_IN_SECONDS ) );
ok( sn_post_updated_display( $post ) === '', 'THE PIN: prose committed 3 days after publish, metadata saved 40 days after -> NO Updated line (the commit is the clock)' );
$GLOBALS['__meta'] = array( 7 => array( '_sn_prov_last_commit_gmt' => gmdate( 'Y-m-d H:i:s', $published + ( 30 * DAY_IN_SECONDS ) ) ) );
$out = sn_post_updated_display( $post );
ok( false !== strpos( $out, gmdate( 'Y.m.d', $published + ( 30 * DAY_IN_SECONDS ) ) ), 'prose committed 30 days after publish -> Updated carries the COMMIT date, not post_modified' );
$GLOBALS['__meta'] = array( 7 => array( '_sn_prov_last_commit_gmt' => '' ) );
ok( false !== strpos( sn_post_updated_display( $post ), gmdate( 'Y.m.d', $published + ( 40 * DAY_IN_SECONDS ) ) ), 'an empty commit meta falls back to post_modified' );
$GLOBALS['__meta'] = array();

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

// The render_block bridge cases went with the bridge (#389). The empty-render
// collapse it used to prove is the block's now: tests/blocks-php-only.php pins
// that an empty updated-date stays '' through wpautop.
ok( ! function_exists( 'sn_updated_date_render_block_bridge' ), 'the global render_block bridge is gone (#389)' );

// ── 2026.10: "see what changed". With two or more signed versions the line
// links to /verify with the last two preselected; otherwise it is exactly the
// plain <time> above. The versions are the plugin's view-model, the list the
// provenance panel draws its rows from.
$GLOBALS['__timestamps'] = array( 'date' => $published, 'modified' => $modified );
$plain = sprintf( '<time class="sn-post-frontmatter__updated" datetime="%s">%s</time>', gmdate( 'c', $modified ), 'Updated ' . gmdate( 'Y.m.d', $modified ) );
// Plugin absent: asserted BEFORE the stub below exists (a PHP function cannot be undefined).
ok( sn_post_updated_display( $post ) === $plain, 'plugin absent -> the plain line, byte-identical' );

$GLOBALS['__vm'] = null;
if ( ! function_exists( 'sn_prov_view_data' ) ) {
	function sn_prov_view_data( $id ) { return $GLOBALS['__vm']; }
}
if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( $u ) { return str_replace( '&', '&#038;', (string) $u ); } // core encodes & the same way
}
$uid = '9f0abe29-71ed-4b6d-b3a3-ad00f06b2fc3';
$vm  = static function ( array $nums, $uid = '9f0abe29-71ed-4b6d-b3a3-ad00f06b2fc3', $base = 'https://example.test/verify' ) {
	return array(
		'note_uid'   => $uid,
		'versions'   => array_map( static function ( $n ) { return array( 'version' => $n ); }, $nums ),
		'verify_url' => $base,
	);
};

ok( sn_post_updated_display( $post ) === $plain, 'no record (view-model null) -> plain line' );
$GLOBALS['__vm'] = $vm( array( 1 ) );
ok( sn_post_updated_display( $post ) === $plain, 'one signed version -> plain line (nothing to compare)' );
$GLOBALS['__vm'] = $vm( array( 0, 1 ) );
ok( sn_post_updated_display( $post ) === $plain, 'founding snapshot (v0) + v1 -> plain line: v0 never counts, the compare form starts at 1' );
$GLOBALS['__vm'] = $vm( array( 1, 2 ), '' );
ok( sn_post_updated_display( $post ) === $plain, 'no note id -> plain line' );

$GLOBALS['__vm'] = $vm( array( 1, 2, 3, 4, 5 ) );
$out  = sn_post_updated_display( $post );
$href = preg_match( '/<a [^>]*href="([^"]*)"/', $out, $m ) ? html_entity_decode( $m[1] ) : '';
ok( 0 === strpos( $out, '<a class="sn-post-frontmatter__updated-link" ' ), 'five versions -> the line is a link' );
ok( 'https://example.test/verify?note=' . $uid . '&compare_a=4&compare_b=5#compare' === $href, 'href: /verify, the note, its last two versions, the Compare tab: ' . $href );
ok( false !== strpos( $out, '>' . $plain . '</a>' ), 'the visible <time> inside the link is byte-identical to the plain line' );
ok( false !== strpos( $out, 'aria-label="Updated ' . gmdate( 'Y.m.d', $modified ) . ': see what changed"' ), 'accessible name starts with the visible text, then says what the link does' );

$GLOBALS['__vm'] = $vm( array( 0, 1, 3, 7 ) );
$out = sn_post_updated_display( $post );
ok( false !== strpos( $out, '&#038;compare_a=3&#038;compare_b=7#compare' ), 'the chain\'s own last two numbers (3 and 7), never "latest minus one"' );
$GLOBALS['__vm'] = $vm( array( 2, 3 ), $uid, 'https://example.test/verify?kind=page' );
ok( false !== strpos( sn_post_updated_display( $post ), 'verify?kind=page&#038;note=' ), 'a base that already carries ?kind= joins with &' );
$GLOBALS['__vm'] = $vm( array( 1, 2 ), 'a"b<c' );
ok( false !== strpos( sn_post_updated_display( $post ), 'note=a%22b%3Cc&#038;' ), 'the note id is URL-encoded into the href' );

$GLOBALS['__vm'] = $vm( array( 1, 2, 3, 4, 5 ) );
$GLOBALS['__timestamps'] = array( 'date' => $published, 'modified' => $published + DAY_IN_SECONDS );
ok( sn_post_updated_display( $post ) === '', 'below the threshold there is no line, so no link either' );
$GLOBALS['__vm'] = null;

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
