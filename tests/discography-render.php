<?php
/**
 * Standalone fixture tests for the [sn_discography] cover-grid gallery
 * (theme v9.14.0 — /music redesign).
 *
 * The shortcode reads normalized discography entries off the cross-package
 * filter `sn_discography_entries` and renders a brutalist cover-grid: a sticky
 * controls rail (live count + role-filter chips), year-grouped sections of
 * album-cover cards (each carrying data-roles for client-side filtering +
 * data-spotify/data-type for lazy click-to-play), and a hidden empty state.
 *
 * Standalone-safe: plugin absent → filter yields array() → shortcode → ''.
 * Every external-data field is escaped; the server emits ZERO eager iframes.
 *
 * @since theme v9.14.0
 */

if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) {
	http_response_code( 404 );
	exit;
}
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/' );
}

// ── WP function stubs ─────────────────────────────────────────────────
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

require __DIR__ . '/../inc/discography-render.php';

$pass = 0; $fail = 0;
function ok( $cond, $label ) {
	global $pass, $fail;
	if ( $cond ) { $pass++; echo "PASS: $label\n"; } else { $fail++; echo "FAIL: $label\n"; }
}

echo "Discography cover-grid suite — theme v9.14.0\n\n";

// ── REGISTRATION ──────────────────────────────────────────────────────
ok( isset( $GLOBALS['shortcode_tags']['sn_discography'] ) && 'sn_discography_shortcode' === $GLOBALS['shortcode_tags']['sn_discography'], 'registers [sn_discography] → sn_discography_shortcode' );

// ── STANDALONE-SAFE EMPTY ─────────────────────────────────────────────
$GLOBALS['__test_filters']['sn_discography_entries'] = array( function () { return array(); } );
ok( sn_discography_shortcode() === '', 'empty store → "" (plugin absent / not synced)' );
$GLOBALS['__test_filters']['sn_discography_entries'] = array();
ok( sn_discography_shortcode() === '', 'no filter listener → "" (truly standalone)' );

// ── POPULATED ─────────────────────────────────────────────────────────
$entries = array(
	array( 'id' => 'a1', 'title' => 'New & Loud', 'artist' => 'Artist B', 'roles' => array( 'Producer', 'Mixing' ), 'year' => 2024, 'date' => '2024-05-01', 'type' => 'album', 'image' => 'https://i.scdn.co/image/new.jpg', 'spotify_id' => 'newAlbumId', 'spotify_url' => 'https://open.spotify.com/album/newAlbumId', 'muso_url' => 'https://credits.muso.ai/album/new' ),
	array( 'id' => 'a2', 'title' => 'Old Record', 'artist' => 'Artist A', 'roles' => array( 'Mastering' ), 'year' => 2005, 'date' => '2005-09-10', 'type' => 'single', 'image' => 'https://i.scdn.co/image/old.jpg', 'spotify_id' => 'oldAlbumId', 'spotify_url' => 'https://open.spotify.com/album/oldAlbumId', 'muso_url' => 'https://credits.muso.ai/album/old' ),
	// Unmatched (no Spotify) — kept, Muso-only, no play affordance.
	array( 'id' => 'a3', 'title' => 'Unlinked', 'artist' => 'Artist C', 'roles' => array( 'Engineer' ), 'year' => 2005, 'date' => '2005-01-01', 'type' => 'album', 'image' => 'https://i.scdn.co/image/x.jpg', 'spotify_id' => '', 'spotify_url' => '', 'muso_url' => 'https://credits.muso.ai/album/x' ),
);
$GLOBALS['__test_filters']['sn_discography_entries'] = array( function () use ( $entries ) { return array( $entries[1], $entries[2], $entries[0] ); } );
$html = sn_discography_shortcode();

ok( $html !== '', 'populated store → emits markup' );

// Controls rail: count + role chips.
ok( strpos( $html, 'sn-disco-controls' ) !== false, 'controls rail present' );
ok( strpos( $html, 'data-disco-count' ) !== false, 'count carries data-disco-count (JS updates it on filter)' );
ok( preg_match( '/data-disco-count[^>]*>\s*3\s*</', $html ), 'count shows total release count (3)' );
ok( strpos( $html, '2005' ) !== false && strpos( $html, '2024' ) !== false, 'count/year span shows the catalog range' );

// Filter chips: All + one per PRESENT role; none for absent roles.
ok( strpos( $html, 'data-role="*"' ) !== false, 'All chip present (data-role="*")' );
ok( strpos( $html, 'is-active' ) !== false, 'All chip is active by default' );
ok( strpos( $html, 'data-role="Producer"' ) !== false, 'chip for present role Producer' );
ok( strpos( $html, 'data-role="Mixing"' ) !== false, 'chip for present role Mixing' );
ok( strpos( $html, 'data-role="Engineer"' ) !== false, 'chip for present role Engineer' );
ok( strpos( $html, 'data-role="Mastering"' ) !== false, 'chip for present role Mastering' );
ok( strpos( $html, 'data-role="Composer"' ) === false, 'NO chip for an absent role (Composer)' );

// Preferred chip order: Producer before Mixing before Mastering before Engineer.
ok( strpos( $html, 'data-role="Producer"' ) < strpos( $html, 'data-role="Mixing"' ), 'chip order: Producer before Mixing (preferred order)' );
ok( strpos( $html, 'data-role="Mastering"' ) < strpos( $html, 'data-role="Engineer"' ), 'chip order: Mastering before Engineer' );

// Year grouping desc.
ok( strpos( $html, 'sn-disco-year' ) !== false, 'year sections present' );
ok( strpos( $html, 'New &amp; Loud' ) < strpos( $html, 'Old Record' ), 'entries ordered by year desc (2024 group before 2005)' );

// Cards + data attrs.
ok( strpos( $html, 'sn-disco-card' ) !== false, 'cover cards present' );
ok( strpos( $html, 'data-roles="Producer|Mixing"' ) !== false, 'card carries pipe-joined data-roles (filter source)' );
ok( strpos( $html, 'data-spotify="newAlbumId"' ) !== false, 'playable card carries data-spotify (album id)' );
ok( strpos( $html, 'data-type="album"' ) !== false, 'card carries data-type' );
ok( strpos( $html, 'sn-disco-cover-wrap' ) !== false, 'cover-wrap present' );
ok( strpos( $html, 'sn-disco-play-badge' ) !== false, 'play badge present on a playable card' );
ok( strpos( $html, 'role="button"' ) !== false, 'playable cover-wrap is a keyboard button (role=button)' );

// Meta.
ok( strpos( $html, 'sn-disco-title' ) !== false && strpos( $html, 'New &amp; Loud' ) !== false, 'title rendered (escaped)' );
ok( strpos( $html, 'Artist B' ) !== false, 'artist rendered' );
ok( strpos( $html, 'Producer · Mixing' ) !== false, 'roles rendered as middot-joined string' );
ok( strpos( $html, 'https://credits.muso.ai/album/new' ) !== false, 'per-card Muso credits link' );

// Cover img lazy; NO eager iframe.
ok( strpos( $html, 'loading="lazy"' ) !== false, 'cover img is lazy' );
ok( stripos( $html, '<iframe' ) === false, 'NO eager iframe in server markup' );

// Unmatched (no spotify_id): rendered, but NOT a play button.
ok( strpos( $html, 'Unlinked' ) !== false, 'unmatched album still rendered' );
ok( substr_count( $html, 'role="button"' ) === 2, 'only the 2 playable cards are buttons (unmatched is not)' );

// Empty state element present (hidden).
ok( strpos( $html, 'sn-disco-empty' ) !== false, 'hidden empty-state element present (for the no-match filter case)' );

// ── ESCAPING ──────────────────────────────────────────────────────────
$GLOBALS['__test_filters']['sn_discography_entries'] = array( function () {
	return array( array(
		'id' => 'x', 'title' => 'Bad <b>title</b>', 'artist' => 'A"onmouseover="x', 'roles' => array( '<script>r</script>' ),
		'year' => 2020, 'type' => 'album', 'image' => 'https://x/"onerror="alert(1)', 'spotify_id' => 'id"><img src=x>',
		'spotify_url' => 'https://open.spotify.com/album/x', 'muso_url' => 'https://credits.muso.ai/x',
	) );
} );
$evil = sn_discography_shortcode();
ok( strpos( $evil, '<b>title</b>' ) === false, 'hostile title escaped' );
ok( strpos( $evil, '<script>r</script>' ) === false, 'hostile role escaped' );
ok( strpos( $evil, '"onerror="alert(1)' ) === false, 'hostile image URL esc_url\'d' );
ok( strpos( $evil, 'id"><img src=x>' ) === false, 'hostile spotify_id esc_attr\'d' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
