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
echo "home-screen-icon-opacity — the JL mark\n\nGroup 1: the brand PNGs exist and are opaque\n";

// the icons moved to assets/brand/ and the light/dark PNG pairs went
// with the raster logo. What must still hold is the property this file was
// written for: anything a home screen can take is OPAQUE.
$expect = array( 'apple-touch-icon.png' => 180, 'icon-512.png' => 512 );
foreach ( $expect as $name => $want ) {
	$info = hsi_png_info( $root . '/assets/brand/' . $name );
	ok( null !== $info, "$name exists and is a PNG" );
	if ( null === $info ) { continue; }
	ok( $info['w'] === $want && $info['h'] === $want,
		"$name is actually {$want}x{$want} — the surface that will declare it must not lie about its size" );
	ok( 2 === $info['colour'],
		"$name has NO alpha channel (colour type {$info['colour']}, want 2) — iOS renders home-screen transparency as black" );
}

echo "\nGroup 2: the control — the tab icon is the SVG tile, not a raster with alpha\n";
$svg = (string) @file_get_contents( $root . '/assets/brand/favicon.svg' );
ok( false !== strpos( $svg, 'prefers-color-scheme: dark' ), 'favicon.svg carries its own dark rule — that is why no PNG pair is needed' );
ok( 1 === preg_match( '/<rect class="bg" width="200" height="200"/', $svg ), 'favicon.svg is the full-bleed TILE (opaque ground), not the bare mark' );
// Chrome's favicon renderer does not resolve var() in presentation attributes:
// the first cut coloured the strokes with `stroke="var(--fg)"` and the tab
// showed a solid black square (owner, 2026-09-21). Colours live in class
// rules inside <style>; no custom properties anywhere in the file.
ok( false === strpos( $svg, 'var(' ) && false === strpos( $svg, '--' ), 'favicon.svg uses no CSS custom properties (Chrome paints a favicon with var() strokes as a blank tile)' );
ok( 1 === preg_match( '/\.fg\s*\{\s*stroke:\s*#ffffff/', $svg ) && 1 === preg_match( '/prefers-color-scheme: dark\)\s*\{.*?\.fg\s*\{\s*stroke:\s*#000000/s', $svg ), 'the mark is white on black by default and black on white under a dark scheme, via class rules' );

// The .ico is what Chrome shows in the tab (it prefers the sizes="any" entry
// over the SVG). The 13.5.0 file was a SOLID BLACK square at 48, 32 and 16
// (rasterised from the var() SVG), and it stayed one after the SVG was
// fixed. Read the pixels: each frame must hold more than one colour.
$ico = (string) file_get_contents( $root . '/assets/brand/favicon.ico' );
$count = strlen( $ico ) >= 6 ? unpack( 'v', substr( $ico, 4, 2 ) )[1] : 0;
ok( 3 === $count, "favicon.ico carries three frames (got $count)" );
for ( $i = 0; $i < $count; $i++ ) {
	$e = unpack( 'Cw/Ch/Ccc/Cr/vpl/vbpp/Vsz/Voff', substr( $ico, 6 + 16 * $i, 16 ) );
	$img = substr( $ico, $e['off'], $e['sz'] );
	$hdr = unpack( 'V', substr( $img, 0, 4 ) )[1];
	$w = $e['w'] ?: 256; $h = $e['h'] ?: 256;
	$px = substr( $img, $hdr, $w * $h * 4 );
	$colours = count( array_unique( str_split( $px, 4 ) ) );
	ok( 32 === $e['bpp'] && $colours > 1, "favicon.ico {$w}x{$h}: 32bpp with $colours distinct pixels (a single colour is the black tile)" );
}
$dm_src = (string) file_get_contents( $root . '/inc/dark-mode.php' );
ok( str_contains( $dm_src, "add_query_arg( 'ver', sn_asset_ver( \$path )" ), 'the icon links are versioned: a favicon cache is keyed on the URL and outlives every page purge' );

echo "\nGroup 3: ours is the one iOS will take\n";
$dm = (string) file_get_contents( $root . '/inc/dark-mode.php' );
$code = '';
foreach ( token_get_all( $dm ) as $t ) {
	if ( is_array( $t ) && in_array( $t[0], array( T_COMMENT, T_DOC_COMMENT ), true ) ) { $code .= "\n"; continue; }
	$code .= is_array( $t ) ? $t[1] : $t;
}
ok( false !== strpos( $code, 'assets/brand/apple-touch-icon.png' ), 'the opaque brand icon is emitted' );
ok( 1 === substr_count( $code, "'apple-touch-icon'" ) || 1 === substr_count( $code, 'rel="apple-touch-icon"' ),
	'exactly ONE apple-touch-icon is emitted' );
ok( 1 === preg_match( "/add_filter\\(\\s*'site_icon_meta_tags',\\s*'__return_empty_array'\\s*\\)/", $code ),
	"core's Site Icon tags are removed wholesale — wp_site_icon() runs at wp_head:99, AFTER this theme's :1, so its apple-touch-icon would be the link iOS takes and its icon pair a second, competing favicon set" );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
