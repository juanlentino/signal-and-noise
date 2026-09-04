<?php
/**
 * Tests: the home-screen icon is OPAQUE (theme #273).
 *
 * iOS does not composite `apple-touch-icon` transparency onto anything — it
 * renders it BLACK. Measured on the shipped marks before this change:
 * favicon-180.png was 69% transparent pixels and the 512 Site Icon 63%, which
 * is exactly the black tile the installed OpenStation PWA showed on the home
 * screen.
 *
 * Browser-tab icons (`rel="icon"`) are deliberately NOT covered: a tab
 * composites onto the browser's own chrome, and the mark is meant to be
 * transparent there. That distinction is the whole reason this file exists
 * rather than a blanket "no transparent PNGs" rule.
 *
 * Run: php tests/home-screen-icon-opacity.php
 * @since 12.18.6
 */

if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }

$pass = 0; $fail = 0;
function ok( $c, $m ) { global $pass, $fail; if ( $c ) { $pass++; echo "PASS: $m\n"; } else { $fail++; echo "FAIL: $m\n"; } }

/**
 * PNG dimensions + colour type, read from IHDR.
 *
 * Colour type 2 is RGB with no alpha CHANNEL at all — the strongest statement
 * of opacity a PNG can make, and cheaper than decoding every pixel.
 *
 * @return array{w:int,h:int,colour:int}|null
 */
function hsi_png_info( $path ) {
	$d = (string) @file_get_contents( $path );
	if ( strlen( $d ) < 26 || "\x89PNG\r\n\x1a\n" !== substr( $d, 0, 8 ) ) { return null; }
	$dim = unpack( 'Nw/Nh', substr( $d, 16, 8 ) );
	return array( 'w' => (int) $dim['w'], 'h' => (int) $dim['h'], 'colour' => ord( $d[25] ) );
}

$root = dirname( __DIR__ );
echo "home-screen-icon-opacity — theme v12.18.6\n\nGroup 1: the icons exist and are opaque\n";

$expect = array( 180 => 'app-icon-180.png', 180.1 => 'app-icon-180-dark.png', 192 => 'app-icon-192.png', 512 => 'app-icon-512.png' );
foreach ( $expect as $size => $name ) {
	$info = hsi_png_info( $root . '/assets/images/' . $name );
	ok( null !== $info, "$name exists and is a PNG" );
	if ( null === $info ) { continue; }
	$want = (int) $size;
	ok( $info['w'] === $want && $info['h'] === $want,
		"$name is actually {$want}x{$want} — the manifest that will declare it must not lie about its size" );
	ok( 2 === $info['colour'],
		"$name has NO alpha channel (colour type {$info['colour']}, want 2) — iOS renders home-screen transparency as black" );
}

echo "\nGroup 2: the control — tab icons keep their transparency\n";
$fav = hsi_png_info( $root . '/assets/images/favicon-180.png' );
ok( null !== $fav && 6 === $fav['colour'],
	'favicon-180.png still HAS alpha — a blanket "no transparency" rule would have been the wrong fix, and this asserts we did not apply one' );

echo "\nGroup 3: ours is the one iOS will take\n";
$dm = (string) file_get_contents( $root . '/inc/dark-mode.php' );
$code = '';
foreach ( token_get_all( $dm ) as $t ) {
	if ( is_array( $t ) && in_array( $t[0], array( T_COMMENT, T_DOC_COMMENT ), true ) ) { $code .= "\n"; continue; }
	$code .= is_array( $t ) ? $t[1] : $t;
}
ok( false !== strpos( $code, 'app-icon-180.png' ), 'the opaque icon is emitted' );
ok( false === strpos( $code, "'apple-touch-icon', '180x180', 'favicon-180.png'" ),
	'the transparent pair is no longer emitted as an apple-touch-icon' );
ok( false !== strpos( $code, 'site_icon_meta_tags' ),
	"core's own apple-touch-icon is filtered out — it runs at wp_head:99, AFTER this theme's :1, so without this it is the link iOS takes and an opaque icon shipped earlier changes nothing" );
// The light/dark PAIR stays: an existing pin (head-sweep A4) asks for the dark
// variant, and deleting a feature to fix a transparency bug would trade one
// defect for a regression. What must hold is that every one of them is opaque
// and that core's transparent one is gone.
ok( false !== strpos( $code, 'app-icon-180-dark.png' ),
	'the DARK home-screen variant is still offered — the fix is opacity, not removing the pair' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
