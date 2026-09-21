<?php
/**
 * Standalone test: the JL mark ships inline, sized by height, from the theme.
 *
 * This cut replaced the header's broken uploads-hosted logo (a `cropped-*`
 * attachment thumbnail that no theme file can repair) with the JL mark
 * inlined as SVG in currentColor. These pins hold the properties that made
 * that the fix: no request, no uploads dependency, one ink token, never
 * below the 16px screen floor, and a home link with exactly one accessible
 * name.
 *
 * Run: php tests/brand-mark.php
 * @since Unreleased
 */
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }

$pass = 0; $fail = 0;
function ok( $c, $m ) { global $pass, $fail; if ( $c ) { $pass++; echo "PASS: $m\n"; } else { $fail++; echo "FAIL: $m\n"; } }

$root   = dirname( __DIR__ );
$header = (string) file_get_contents( $root . '/parts/header.html' );

echo "brand-mark — the JL mark\n\nGroup 1: the header mark is inline SVG, not an image\n";
ok( 1 === preg_match( '/<a href="\/" class="sn-logo-link" aria-label="[^"]+"[^>]*>\s*<svg class="jl-mark"/', $header ),
	'the home link wraps an inline <svg class="jl-mark"> and carries an aria-label' );
ok( false !== strpos( $header, 'stroke="currentColor"' ), 'the mark is drawn in currentColor (dark mode needs no second asset)' );
ok( 1 === preg_match( '/<svg class="jl-mark"[^>]*aria-hidden="true"/', $header ), 'the SVG is aria-hidden — the link already has the name' );
ok( false !== strpos( $header, 'viewBox="0 0 146 128"' ) && false !== strpos( $header, 'stroke-width="20"' ), 'geometry is the canonical mark (146x128, stroke 20)' );
ok( false === strpos( $header, '<img' ) && false === strpos( $header, 'wp-content/uploads' ), 'no <img> and no uploads URL remain in the header' );

echo "\nGroup 2: nothing in the theme references the dead attachment\n";
$hits = array();
foreach ( array( 'parts', 'templates', 'patterns', 'blocks', 'inc', 'assets/css', 'assets/js', 'styles' ) as $dir ) {
	$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root . '/' . $dir, FilesystemIterator::SKIP_DOTS ) );
	foreach ( $it as $f ) {
		if ( false !== strpos( (string) file_get_contents( (string) $f ), 'cropped-jl_logo' ) ) { $hits[] = substr( (string) $f, strlen( $root ) + 1 ); }
	}
}
ok( empty( $hits ), 'zero references to cropped-jl_logo-min-150x150.webp' . ( $hits ? ': ' . implode( ', ', $hits ) : '' ) );

echo "\nGroup 3: sized by height only, never below the 16px floor\n";
$heights = array(); $widths = array();
foreach ( array( 'critical', 'layout', 'responsive' ) as $css ) {
	$src = (string) file_get_contents( $root . "/assets/css/$css.css" );
	preg_match_all( '/\.jl-mark\s*\{([^}]*)\}/s', $src, $m );
	foreach ( $m[1] as $body ) {
		if ( preg_match( '/height:\s*(\d+)px/', $body, $h ) ) { $heights[] = (int) $h[1]; }
		if ( preg_match( '/\bwidth:\s*(\d+)px/', $body ) ) { $widths[] = $css; }
	}
}
ok( count( $heights ) >= 6, 'the mark has explicit heights across the header states/breakpoints (' . count( $heights ) . ' found)' );
ok( ! empty( $heights ) && min( $heights ) >= 16, 'the smallest is ' . ( $heights ? min( $heights ) : 'n/a' ) . 'px — the 16px floor holds' );
ok( empty( $widths ), 'no fixed pixel width anywhere — one axis is never sized alone' );
$crit = (string) file_get_contents( $root . '/assets/css/critical.css' );
ok( 1 === preg_match( '/\.jl-mark\s*\{[^}]*color:\s*var\(--wp--preset--color--bone\)/s', $crit ), 'the ink is the bone token (flipped by the dark layer)' );
ok( false === strpos( $crit, 'data-theme="dark"] .jl-mark' ), 'no second dark-mode rule for the mark — the token layer already does it' );

echo "\nGroup 4: no rounded corners\n";
$tj = json_decode( (string) file_get_contents( $root . '/theme.json' ), true );
ok( '0' === (string) ( $tj['styles']['elements']['button']['border']['radius'] ?? '' ), 'theme.json button radius is 0 (was 999px)' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
