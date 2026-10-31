<?php
require_once __DIR__ . '/lib/components-source.php'; // the component layer, joined (2026-10-31 split)
/**
 * Standalone fixture tests for the [sn_music_featured] shortcode (theme v9.15.0).
 *
 * Renders the single "press play" Spotify player at the top of /music from the
 * featured config the companion plugin supplies over the standalone-safe
 * `sn_music_featured` filter. Standalone-safe: plugin absent / setting empty →
 * filter yields array() → shortcode → ''. The embed URL is escaped; the player
 * height adapts to the embed type (compact track vs full album/playlist).
 *
 * @since theme v9.15.0
 */

if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) {
	http_response_code( 404 );
	exit;
}
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/' );
}

$GLOBALS['__test_filters'] = array();
if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( $hook, $cb, $p = 10, $a = 1 ) { $GLOBALS['__test_filters'][ $hook ][] = $cb; return true; }
}
if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $hook, $value ) {
		$args = func_get_args();
		foreach ( $GLOBALS['__test_filters'][ $hook ] ?? array() as $cb ) {
			$args[1] = call_user_func_array( $cb, array_slice( $args, 1 ) );
		}
		return $args[1];
	}
}
if ( ! function_exists( 'add_shortcode' ) ) {
	function add_shortcode( $tag, $cb ) { $GLOBALS['shortcode_tags'][ $tag ] = $cb; return true; }
}
if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( $u ) { $u = (string) $u; $u = str_replace( array( '"', "'", '<', '>', ' ' ), '', $u ); return str_replace( '&', '&amp;', $u ); }
}
if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
}
if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
}
if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( $s, $d = 'default' ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
}

if ( ! function_exists( 'add_action' ) ) { function add_action() {} }
require __DIR__ . '/../inc/music-featured-render.php';

$pass = 0; $fail = 0;
function ok( $c, $m ) {
	global $pass, $fail;
	if ( $c ) { $pass++; echo "PASS: $m\n"; } else { $fail++; echo "FAIL: $m\n"; }
}

echo "Featured-release shortcode suite — theme v9.15.0\n\n";

ok( isset( $GLOBALS['shortcode_tags']['sn_music_featured'] ) && 'sn_music_featured_shortcode' === $GLOBALS['shortcode_tags']['sn_music_featured'], 'registers [sn_music_featured] → sn_music_featured_shortcode' );

// ── STANDALONE-SAFE EMPTY ─────────────────────────────────────────────
$GLOBALS['__test_filters']['sn_music_featured'] = array( function () { return array(); } );
ok( sn_music_featured_shortcode() === '', 'empty featured config → "" (plugin absent / unset)' );
$GLOBALS['__test_filters']['sn_music_featured'] = array();
ok( sn_music_featured_shortcode() === '', 'no filter listener → "" (truly standalone)' );

// ── TRACK → compact player ────────────────────────────────────────────
$GLOBALS['__test_filters']['sn_music_featured'] = array( function () {
	return array( 'type' => 'track', 'id' => '6MuumbyTsu4CLaniAN0lBW', 'embed_url' => 'https://open.spotify.com/embed/track/6MuumbyTsu4CLaniAN0lBW', 'open_url' => 'https://open.spotify.com/track/6MuumbyTsu4CLaniAN0lBW' );
} );
$html = sn_music_featured_shortcode();
ok( $html !== '', 'configured → emits markup' );
ok( strpos( $html, 'sn-music-featured' ) !== false, 'wrapper present' );
ok( strpos( $html, 'embed/track/6MuumbyTsu4CLaniAN0lBW' ) !== false, 'iframe src is the embed URL' );
ok( strpos( $html, 'height="152"' ) !== false, 'track → compact 152px player' );
ok( strpos( $html, 'Featured' ) !== false && strpos( $html, 'Press play' ) !== false, 'label present' );
ok( strpos( $html, 'loading="lazy"' ) !== false, 'iframe lazy-loaded' );

// ── ALBUM → full player ───────────────────────────────────────────────
$GLOBALS['__test_filters']['sn_music_featured'] = array( function () {
	return array( 'type' => 'album', 'id' => '4m2880jivSbbyEGAKfITCa', 'embed_url' => 'https://open.spotify.com/embed/album/4m2880jivSbbyEGAKfITCa', 'open_url' => 'https://open.spotify.com/album/4m2880jivSbbyEGAKfITCa' );
} );
$album_html = sn_music_featured_shortcode();
ok( strpos( $album_html, 'height="352"' ) !== false, 'album → full 352px player (shows tracklist)' );
ok( strpos( $album_html, 'embed/album/' ) !== false, 'album embed path' );

// ── PLAYLIST → full player ────────────────────────────────────────────
$GLOBALS['__test_filters']['sn_music_featured'] = array( function () {
	return array( 'type' => 'playlist', 'id' => '37i9dQZF1DXcBWIGoYBM5M', 'embed_url' => 'https://open.spotify.com/embed/playlist/37i9dQZF1DXcBWIGoYBM5M', 'open_url' => '' );
} );
ok( strpos( sn_music_featured_shortcode(), 'height="352"' ) !== false, 'playlist → full 352px player' );

// ── ESCAPING: hostile embed URL ───────────────────────────────────────
$GLOBALS['__test_filters']['sn_music_featured'] = array( function () {
	return array( 'type' => 'track', 'id' => 'x', 'embed_url' => 'https://open.spotify.com/embed/track/x"></iframe><script>alert(1)</script>', 'open_url' => '' );
} );
$evil = sn_music_featured_shortcode();
ok( strpos( $evil, '<script' ) === false, 'hostile embed_url: no injected <script tag' );
ok( substr_count( $evil, '<iframe' ) === 1, 'hostile embed_url cannot inject a SECOND iframe (esc_url stripped the breakout)' );

echo "\nGroup: a keyboard focus ring for the player (WCAG 2.4.7)\n";
// Measured 2026-10-18 in Chromium: Tab into a cross-origin iframe moves focus
// INSIDE it, and the <iframe> then matches none of :focus, :focus-visible or
// :focus-within, so no CSS rule can ring it. A programmatic iframe.focus()
// does match :focus-within. The ring is on the wrapper (the iframe's mask
// would clip an outline of its own), set by :focus-within in CSS and, for
// real keyboard entry, by a class from assets/js/frame-focus.js.
$GLOBALS['__enq'] = array();
if ( ! function_exists( 'wp_enqueue_script' ) ) { function wp_enqueue_script( $h ) { $GLOBALS['__enq'][] = $h; } }
$GLOBALS['__test_filters']['sn_music_featured'] = array( function () { return array( 'embed_url' => 'https://open.spotify.com/embed/track/x', 'type' => 'track' ); } );
sn_music_featured_shortcode();
ok( in_array( 'sn-frame-focus', $GLOBALS['__enq'], true ), 'the player enqueues sn-frame-focus, only when it renders' );
$GLOBALS['__enq'] = array();
$GLOBALS['__test_filters']['sn_music_featured'] = array( function () { return array(); } );
sn_music_featured_shortcode();
ok( array() === $GLOBALS['__enq'], 'no player, no script' );
ok( function_exists( 'sn_register_frame_focus' ), 'the handle is registered by a named function' );
$css = (string) preg_replace( '#/\*.*?\*/#s', '', snt_components_source() );
$ring = '/\.sn-music-featured:has\(> \.sn-music-featured__player:focus-within\),\s*\.sn-music-featured\.is-frame-focus\s*\{[^}]*outline:\s*2px solid var\(--wp--preset--color--blood\);[^}]*outline-offset:\s*3px/';
ok( 1 === preg_match( $ring, $css ), 'the wrapper wears the house ring (2px blood, 3px offset) on :focus-within and on .is-frame-focus' );
ok( is_file( __DIR__ . '/../assets/js/frame-focus.js' ), 'assets/js/frame-focus.js exists' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
