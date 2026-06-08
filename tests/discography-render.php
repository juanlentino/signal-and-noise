<?php
/**
 * Standalone fixture tests for the [sn_discography] release-timeline
 * shortcode (theme v9.13.0 — Music Identity, theme half).
 *
 * The shortcode reads the normalized discography entries off the
 * cross-package filter `sn_discography_entries` (the plugin supplies them
 * via add_filter; the theme reads them) and renders a brutalist
 * year-grouped timeline. It MUST be standalone-safe: when the plugin is
 * absent the filter yields array() and the shortcode degrades to '' —
 * NO fatal, empty timeline.
 *
 * Each entry renders: a lazy-loaded artwork <img loading="lazy">, the
 * title, primary artist, role(s), year, a click-to-play <button
 * class="sn-disco-play" data-spotify="..."> (NOT an eager <iframe> — the
 * iframe is mounted on demand by assets/js/discography.js), and the Muso
 * deep link. Everything is escaped.
 *
 * Mirrors tests/post-share.php (shortcode render contract) and
 * tests/cross-package-listeners.php (filter registry stubs).
 *
 * @since theme v9.13.0
 */

// SECURITY: Prevent web access. Test fixture, not a runtime module.
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) {
	http_response_code( 404 );
	exit;
}

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/' );
}

// ── WP function stubs — minimal mock layer ───────────────────────────
// Filters flow through a global registry so a test can seed the
// sn_discography_entries filter with fixture entries.
$GLOBALS['__test_filters'] = array();

if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		$GLOBALS['__test_filters'][ $hook ][] = $callback;
		return true;
	}
}
if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $hook, $value /*, ...args */ ) {
		$args      = func_get_args();
		$callbacks = $GLOBALS['__test_filters'][ $hook ] ?? array();
		foreach ( $callbacks as $cb ) {
			$args[1] = call_user_func_array( $cb, array_slice( $args, 1 ) );
		}
		return $args[1];
	}
}
if ( ! function_exists( 'add_shortcode' ) ) {
	function add_shortcode( $tag, $callback ) {
		$GLOBALS['shortcode_tags'][ $tag ] = $callback;
		return true;
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

require __DIR__ . '/../inc/discography-render.php';

$pass = 0; $fail = 0;
function ok( $cond, $label ) {
	global $pass, $fail;
	if ( $cond ) { $pass++; echo "PASS: $label\n"; }
	else { $fail++; echo "FAIL: $label\n"; }
}

echo "Discography render suite — theme v9.13.0\n\n";

// ── REGISTRATION ──────────────────────────────────────────────────────
ok(
	isset( $GLOBALS['shortcode_tags']['sn_discography'] )
		&& 'sn_discography_shortcode' === $GLOBALS['shortcode_tags']['sn_discography'],
	'registers [sn_discography] → sn_discography_shortcode'
);

// ── GRACEFUL EMPTY (standalone-safe: plugin absent → filter yields []) ─
$GLOBALS['__test_filters']['sn_discography_entries'] = array(
	function ( $entries ) { return array(); },
);
ok( sn_discography_shortcode() === '', 'empty store → returns "" (no fatal, plugin absent)' );

// Also: no filter listener at all (truly standalone) → still '' .
$GLOBALS['__test_filters']['sn_discography_entries'] = array();
ok( sn_discography_shortcode() === '', 'no filter listener → returns "" (truly standalone)' );

// ── POPULATED: two entries, different years ───────────────────────────
$entries = array(
	array(
		'id'          => 'isrc-new',
		'title'       => 'New & Loud',
		'artist'      => 'Artist B',
		'roles'       => array( 'Producer', 'Mixing' ),
		'year'        => 2024,
		'type'        => 'album',
		'image'       => 'https://i.scdn.co/image/new.jpg',
		'spotify_id'  => 'newAlbumId123',
		'spotify_url' => 'https://open.spotify.com/album/newAlbumId123',
		'muso_url'    => 'https://credits.muso.ai/album/new',
		'isrc'        => '',
		'upc'         => '',
	),
	array(
		'id'          => 'isrc-old',
		'title'       => 'Old Record',
		'artist'      => 'Artist A',
		'roles'       => array( 'Producer' ),
		'year'        => 2005,
		'type'        => 'album',
		'image'       => 'https://i.scdn.co/image/old.jpg',
		'spotify_id'  => 'oldAlbumId456',
		'spotify_url' => 'https://open.spotify.com/album/oldAlbumId456',
		'muso_url'    => 'https://credits.muso.ai/album/old',
		'isrc'        => '',
		'upc'         => '',
	),
);
// Deliberately seed ascending order to prove the renderer groups year-desc.
$GLOBALS['__test_filters']['sn_discography_entries'] = array(
	function ( $ignored ) use ( $entries ) {
		return array( $entries[1], $entries[0] ); // old first, new second.
	},
);

$html = sn_discography_shortcode();
ok( $html !== '', 'populated store → emits markup' );

// Year-desc grouping: the 2024 year header must appear before the 2005 one.
$pos_2024 = strpos( $html, '2024' );
$pos_2005 = strpos( $html, '2005' );
ok( $pos_2024 !== false && $pos_2005 !== false, 'both year groups present' );
ok( $pos_2024 < $pos_2005, 'years grouped descending (2024 before 2005)' );

// New entry must render before the old entry (the entry markup follows
// the descending year grouping).
ok( strpos( $html, 'New &amp; Loud' ) < strpos( $html, 'Old Record' ), 'entries ordered by year desc' );

// Each field present (titles escaped).
ok( strpos( $html, 'New &amp; Loud' ) !== false, 'title (escaped ampersand) present' );
ok( strpos( $html, 'Old Record' ) !== false, 'second title present' );
ok( strpos( $html, 'Artist B' ) !== false, 'primary artist present' );
ok( strpos( $html, 'Artist A' ) !== false, 'second artist present' );
ok( strpos( $html, 'Producer' ) !== false, 'role present' );
ok( strpos( $html, 'Mixing' ) !== false, 'multi-role joined (Mixing present)' );

// Artwork: lazy <img>.
ok( strpos( $html, 'loading="lazy"' ) !== false, 'artwork uses loading="lazy"' );
ok( strpos( $html, 'https://i.scdn.co/image/new.jpg' ) !== false, 'artwork src present' );

// Click-to-play trigger carrying the spotify_id — NOT an eager iframe.
ok( strpos( $html, 'class="sn-disco-play"' ) !== false, 'play trigger button present' );
ok( strpos( $html, 'data-spotify="newAlbumId123"' ) !== false, 'play trigger carries spotify_id' );
ok( strpos( $html, 'data-type="album"' ) !== false, 'play trigger carries entry type (album)' );
ok( stripos( $html, '<iframe' ) === false, 'NO eager <iframe> in server markup' );

// Muso deep link.
ok( strpos( $html, 'https://credits.muso.ai/album/new' ) !== false, 'Muso link present' );

// type-aware embed: a track-type entry tags the trigger so the JS uses /embed/track/.
$GLOBALS['__test_filters']['sn_discography_entries'] = array(
	function ( $ignored ) {
		return array( array( 'title' => 'Single Cut', 'artist' => 'Z', 'year' => 2022, 'type' => 'track', 'spotify_id' => 'trk987' ) );
	},
);
$track_html = sn_discography_shortcode();
ok( strpos( $track_html, 'data-type="track"' ) !== false, 'track entry → play trigger tagged data-type="track"' );

// ── ESCAPING: hostile entry data must not leak raw HTML ───────────────
$GLOBALS['__test_filters']['sn_discography_entries'] = array(
	function ( $ignored ) {
		return array(
			array(
				'id'          => 'x',
				'title'       => 'Bad <b>title</b>',
				'artist'      => 'A"onmouseover="x',
				'roles'       => array( '<script>r</script>' ),
				'year'        => 2020,
				'type'        => 'album',
				'image'       => 'https://x/"onerror="alert(1)',
				'spotify_id'  => 'id"><img src=x>',
				'spotify_url' => 'https://open.spotify.com/album/x',
				'muso_url'    => 'https://credits.muso.ai/x',
				'isrc'        => '',
				'upc'         => '',
			),
		);
	},
);
$evil = sn_discography_shortcode();
ok( strpos( $evil, '<b>title</b>' ) === false, 'hostile title escaped — no raw <b> leak' );
ok( strpos( $evil, '<script>r</script>' ) === false, 'hostile role escaped — no raw <script> leak' );
ok( strpos( $evil, '"onerror="alert(1)' ) === false, 'hostile image URL esc_url\'d — quote breakout stripped' );
ok( strpos( $evil, 'id"><img src=x>' ) === false, 'hostile spotify_id esc_attr\'d — attribute breakout stripped' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
