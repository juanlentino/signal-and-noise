<?php
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

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
