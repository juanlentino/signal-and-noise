<?php
/**
 * Standalone fixture tests for the [sn_music_featured] shortcode.
 *
 * v9.15.0 rendered the one eager Spotify iframe on /music. The facade release
 * replaces it: the server now emits ZERO iframes — the hero is an accessible
 * card, a real <a> to the release's public Spotify page, and the discography
 * script upgrades it to click-to-play (mounting the embed only on the reader's
 * explicit choice, from the data-embed attribute it validates). With JS off the
 * card is simply a link that works. Nothing is fetched from Spotify before the
 * reader asks — the same argument the site already makes everywhere else.
 *
 * Standalone-safe contract unchanged: plugin absent / setting empty → filter
 * yields array() → shortcode → ''.
 *
 * @since theme v9.15.0 (facade contract since the accessible-embeds release)
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
if ( ! function_exists( 'rawurlencode' ) ) {
	// core PHP; listed only so a grep for the derivation's inputs lands here.
}

require __DIR__ . '/../inc/music-featured-render.php';

$pass = 0; $fail = 0;
function ok( $c, $m ) {
	global $pass, $fail;
	if ( $c ) { $pass++; echo "PASS: $m\n"; } else { $fail++; echo "FAIL: $m\n"; }
}

echo "Featured-release shortcode suite — the facade contract\n\n";

ok( isset( $GLOBALS['shortcode_tags']['sn_music_featured'] ) && 'sn_music_featured_shortcode' === $GLOBALS['shortcode_tags']['sn_music_featured'], 'registers [sn_music_featured] → sn_music_featured_shortcode' );

// ── STANDALONE-SAFE EMPTY ─────────────────────────────────────────────
$GLOBALS['__test_filters']['sn_music_featured'] = array( function () { return array(); } );
ok( sn_music_featured_shortcode() === '', 'empty featured config → "" (plugin absent / unset)' );
$GLOBALS['__test_filters']['sn_music_featured'] = array();
ok( sn_music_featured_shortcode() === '', 'no filter listener → "" (truly standalone)' );

// ── TRACK → compact facade ────────────────────────────────────────────
$GLOBALS['__test_filters']['sn_music_featured'] = array( function () {
	return array( 'type' => 'track', 'id' => '6MuumbyTsu4CLaniAN0lBW', 'embed_url' => 'https://open.spotify.com/embed/track/6MuumbyTsu4CLaniAN0lBW', 'open_url' => 'https://open.spotify.com/track/6MuumbyTsu4CLaniAN0lBW' );
} );
$html = sn_music_featured_shortcode();
ok( $html !== '', 'configured → emits markup' );
ok( strpos( $html, 'sn-music-featured' ) !== false, 'wrapper present' );
// THE LOAD-BEARING PIN. The server never mounts the third party: no iframe
// exists until the reader chooses. This is the row's whole sentence.
ok( strpos( $html, '<iframe' ) === false, 'ZERO iframes server-side — nothing is fetched on a reader\'s behalf before they choose it' );
ok( strpos( $html, '<a class="sn-music-featured__facade" href="https://open.spotify.com/track/6MuumbyTsu4CLaniAN0lBW"' ) !== false, 'the facade is a REAL link to the public Spotify page — with JS off the card still works, keyboard-native' );
ok( strpos( $html, 'data-embed="https://open.spotify.com/embed/track/6MuumbyTsu4CLaniAN0lBW"' ) !== false, 'the embed URL rides as data — the discography script mounts it only on explicit activation' );
ok( strpos( $html, 'data-height="152"' ) !== false, 'track → compact 152px player height, stamped server-side for the JS' );
ok( strpos( $html, 'Featured' ) !== false && strpos( $html, 'Press play' ) !== false, 'label present' );
ok( strpos( $html, 'Play the featured release on Spotify' ) !== false, 'the link NAMES its destination — an accessible card says where it goes, not "click here"' );

// ── ALBUM → full player height ────────────────────────────────────────
$GLOBALS['__test_filters']['sn_music_featured'] = array( function () {
	return array( 'type' => 'album', 'id' => '4m2880jivSbbyEGAKfITCa', 'embed_url' => 'https://open.spotify.com/embed/album/4m2880jivSbbyEGAKfITCa', 'open_url' => 'https://open.spotify.com/album/4m2880jivSbbyEGAKfITCa' );
} );
$album_html = sn_music_featured_shortcode();
ok( strpos( $album_html, 'data-height="352"' ) !== false, 'album → full 352px height (shows tracklist once mounted)' );
ok( strpos( $album_html, '<iframe' ) === false, 'album facade: still zero server-side iframes' );

// ── PLAYLIST, MISSING open_url → derived from type+id ─────────────────
// An older plugin (or a partial record) may omit open_url; the card must
// still link OUT, so the theme derives the public URL from the parts it has
// rather than falling back to the embed page or rendering a dead card.
$GLOBALS['__test_filters']['sn_music_featured'] = array( function () {
	return array( 'type' => 'playlist', 'id' => '37i9dQZF1DXcBWIGoYBM5M', 'embed_url' => 'https://open.spotify.com/embed/playlist/37i9dQZF1DXcBWIGoYBM5M', 'open_url' => '' );
} );
$pl = sn_music_featured_shortcode();
ok( strpos( $pl, 'href="https://open.spotify.com/playlist/37i9dQZF1DXcBWIGoYBM5M"' ) !== false, 'missing open_url → link derived from type+id (never the embed page, never a dead card)' );
ok( strpos( $pl, 'data-height="352"' ) !== false, 'playlist → full 352px height' );

// ── ESCAPING: hostile embed URL ───────────────────────────────────────
$GLOBALS['__test_filters']['sn_music_featured'] = array( function () {
	return array( 'type' => 'track', 'id' => 'x', 'embed_url' => 'https://open.spotify.com/embed/track/x"></a><script>alert(1)</script>', 'open_url' => 'https://open.spotify.com/track/x' );
} );
$evil = sn_music_featured_shortcode();
ok( strpos( $evil, '<script' ) === false, 'hostile embed_url: no injected <script tag' );
ok( strpos( $evil, '<iframe' ) === false, 'hostile embed_url: still zero iframes — the server has no iframe path left to break out of' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
