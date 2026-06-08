<?php
/**
 * Standalone fixture tests for the print / save-as-PDF stylesheet enqueue (v9.10.0).
 *
 * Stubs the WP enqueue + conditional functions that sn_enqueue_print_styles()
 * touches so the pure callback in inc/assets-frontend.php runs without a WP
 * load. Mirrors tests/notes-search.php.
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

// ── Controllable stub state ──
$GLOBALS['__singular']  = array();   // map of post-type => bool for is_singular()
$GLOBALS['__enqueued']  = array();   // captured wp_enqueue_style() calls

if ( ! function_exists( 'is_singular' ) ) {
	function is_singular( $types = '' ) {
		$map = $GLOBALS['__singular'];
		if ( '' === $types || array() === $types ) {
			// "any singular" — true if any registered type is singular.
			return in_array( true, $map, true );
		}
		foreach ( (array) $types as $t ) {
			if ( ! empty( $map[ $t ] ) ) {
				return true;
			}
		}
		return false;
	}
}
if ( ! function_exists( 'wp_enqueue_style' ) ) {
	function wp_enqueue_style( $handle, $src = '', $deps = array(), $ver = false, $media = 'all' ) {
		$GLOBALS['__enqueued'][ $handle ] = array(
			'src'   => $src,
			'deps'  => $deps,
			'ver'   => $ver,
			'media' => $media,
		);
	}
}
if ( ! function_exists( 'get_theme_file_uri' ) ) {
	function get_theme_file_uri( $path = '' ) {
		return 'https://example.test/wp-content/themes/signal-and-noise/' . ltrim( $path, '/' );
	}
}
if ( ! function_exists( 'get_theme_file_path' ) ) {
	function get_theme_file_path( $path = '' ) {
		return realpath( __DIR__ . '/..' ) . '/' . ltrim( $path, '/' );
	}
}
if ( ! function_exists( 'wp_get_theme' ) ) {
	function wp_get_theme() {
		return new class {
			public function get( $key ) {
				return '9.10.0';
			}
		};
	}
}
// add_action / add_filter are no-ops here: we call the helper directly.
if ( ! function_exists( 'add_action' ) ) {
	function add_action() {}
}
if ( ! function_exists( 'add_filter' ) ) {
	function add_filter() {}
}
if ( ! function_exists( 'wp_enqueue_script' ) ) {
	function wp_enqueue_script( $handle, $src = '', $deps = array(), $ver = false, $in_footer = false ) {
		$GLOBALS['__enqueued_scripts'][ $handle ] = array(
			'src'       => $src,
			'deps'      => $deps,
			'ver'       => $ver,
			'in_footer' => $in_footer,
		);
	}
}
// is_page('music') is controllable via $GLOBALS['__page_slug'].
$GLOBALS['__page_slug']       = '';
$GLOBALS['__enqueued_scripts'] = array();
if ( ! function_exists( 'is_page' ) ) {
	function is_page( $page = '' ) {
		if ( '' === $page ) {
			return '' !== $GLOBALS['__page_slug'];
		}
		return (string) $page === $GLOBALS['__page_slug'];
	}
}

require __DIR__ . '/../inc/assets-frontend.php';

$pass = 0; $fail = 0;
function ok( $cond, $label ) {
	global $pass, $fail;
	if ( $cond ) { $pass++; echo "PASS: $label\n"; }
	else { $fail++; echo "FAIL: $label\n"; }
}

function reset_enqueue() {
	$GLOBALS['__enqueued'] = array();
}

ok( function_exists( 'sn_enqueue_print_styles' ), 'sn_enqueue_print_styles() is defined' );

// ── Singular post → sn-print enqueued, media=print ──
reset_enqueue();
$GLOBALS['__singular'] = array( 'post' => true );
sn_enqueue_print_styles();
ok( isset( $GLOBALS['__enqueued']['sn-print'] ), 'sn-print enqueued on singular post' );
ok( ( $GLOBALS['__enqueued']['sn-print']['media'] ?? null ) === 'print', 'sn-print enqueued with media=print' );
ok( strpos( (string) ( $GLOBALS['__enqueued']['sn-print']['src'] ?? '' ), 'assets/css/print.css' ) !== false, 'sn-print src points at assets/css/print.css' );
ok( ( $GLOBALS['__enqueued']['sn-print']['ver'] ?? null ) !== false, 'sn-print carries a cache-bust version (sn_asset_ver)' );

// ── Singular page → sn-print enqueued too ──
reset_enqueue();
$GLOBALS['__singular'] = array( 'page' => true );
sn_enqueue_print_styles();
ok( isset( $GLOBALS['__enqueued']['sn-print'] ), 'sn-print enqueued on singular page' );

// ── Non-singular (archive / front) → NOT enqueued ──
reset_enqueue();
$GLOBALS['__singular'] = array();
sn_enqueue_print_styles();
ok( ! isset( $GLOBALS['__enqueued']['sn-print'] ), 'sn-print NOT enqueued when not singular' );

// ── Singular of an unrelated type → NOT enqueued (posts + pages only) ──
reset_enqueue();
$GLOBALS['__singular'] = array( 'attachment' => true );
sn_enqueue_print_styles();
ok( ! isset( $GLOBALS['__enqueued']['sn-print'] ), 'sn-print NOT enqueued on non-post/page singular' );

// ── CSS CONTENT: the stylesheet strips the nav-like affordances the
//    CHANGELOG promises (header, footer, nav, share row, related notes). ──
$print_css = (string) @file_get_contents( realpath( __DIR__ . '/..' ) . '/assets/css/print.css' );
ok( '' !== $print_css, 'print.css is readable' );
ok( strpos( $print_css, '.sn-note-share' ) !== false, 'print.css hides the share row' );
// FIX 7 — the Related Notes footer is nav-like; the CHANGELOG claims print
// strips it, so the selector must actually be in the hide list.
ok( strpos( $print_css, '.sn-related-notes' ) !== false, 'FIX 7: print.css hides the Related Notes footer (.sn-related-notes)' );

// ── DISCOGRAPHY ENQUEUE (v9.13.0): /music-page-scoped lazy-embed JS ──
function reset_scripts() {
	$GLOBALS['__enqueued_scripts'] = array();
}

ok( function_exists( 'sn_enqueue_discography' ), 'sn_enqueue_discography() is defined' );

// On the /music page → sn-discography enqueued, footer, cache-busted.
reset_scripts();
$GLOBALS['__page_slug'] = 'music';
sn_enqueue_discography();
ok( isset( $GLOBALS['__enqueued_scripts']['sn-discography'] ), 'sn-discography enqueued on /music page' );
ok( strpos( (string) ( $GLOBALS['__enqueued_scripts']['sn-discography']['src'] ?? '' ), 'assets/js/discography.js' ) !== false, 'sn-discography src points at assets/js/discography.js' );
ok( ( $GLOBALS['__enqueued_scripts']['sn-discography']['in_footer'] ?? null ) === true, 'sn-discography loaded in footer' );
ok( ( $GLOBALS['__enqueued_scripts']['sn-discography']['ver'] ?? null ) !== false, 'sn-discography carries a cache-bust version (sn_asset_ver)' );

// On any other page → NOT enqueued.
reset_scripts();
$GLOBALS['__page_slug'] = 'contact';
sn_enqueue_discography();
ok( ! isset( $GLOBALS['__enqueued_scripts']['sn-discography'] ), 'sn-discography NOT enqueued off the /music page' );

// JS CONTENT: lazy mount only — no eager iframe in the source, swaps the
// play button for the Spotify embed on click.
$disco_js = (string) @file_get_contents( realpath( __DIR__ . '/..' ) . '/assets/js/discography.js' );
ok( '' !== $disco_js, 'discography.js is readable' );
ok( strpos( $disco_js, 'sn-disco-play' ) !== false, 'discography.js targets the .sn-disco-play trigger' );
ok( strpos( $disco_js, 'open.spotify.com/embed/' ) !== false, 'discography.js builds the Spotify embed URL' );
ok( strpos( $disco_js, "'track' : 'album'" ) !== false, 'discography.js picks the track vs album embed path by entry type' );
ok( strpos( $disco_js, "getAttribute( 'data-type' )" ) !== false, 'discography.js reads data-type from the trigger (render contract)' );
ok( strpos( $disco_js, "createElement( 'iframe' )" ) !== false, 'discography.js mounts the iframe on demand (not server-rendered)' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
