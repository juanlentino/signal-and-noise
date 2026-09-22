<?php
/**
 * Standalone test: the header's brand.
 *
 * 13.5.0 replaced a broken uploads-hosted logo with the JL mark inlined as
 * SVG. 13.10.0 (owner, 2026-09-22): the page carries the WORDMARK, the name
 * as live text in Bebas, and the JL monogram stays the favicon and OG image.
 * These pins hold: no image and no uploads dependency, one ink token, a
 * box with the old mark's heights so the header offsets hold, a home link
 * with one accessible name, and the brand once per page.
 *
 * Run: php tests/brand-mark.php
 * @since Unreleased
 */
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }

$pass = 0; $fail = 0;
function ok( $c, $m ) { global $pass, $fail; if ( $c ) { $pass++; echo "PASS: $m\n"; } else { $fail++; echo "FAIL: $m\n"; } }

$root   = dirname( __DIR__ );
$header = (string) file_get_contents( $root . '/parts/header.html' );

echo "brand-mark — the header wordmark\n\nGroup 1: the header carries the wordmark as live text\n";
ok( 1 === preg_match( '/<a href="\/" class="sn-logo-link" aria-label="Juan Lentino[^"]*"[^>]*>\s*<span class="sn-wordmark">Juan Lentino<\/span>\s*<\/a>/', $header ),
	'the home link wraps <span class="sn-wordmark">Juan Lentino</span>, and its aria-label starts with the same name' );
ok( false === strpos( $header, '<svg' ) && false === strpos( $header, 'jl-mark' ), 'the header carries no SVG and no monogram: the page brand is the wordmark' );
ok( false === strpos( $header, '<img' ) && false === strpos( $header, 'wp-content/uploads' ), 'no <img> and no uploads URL remain in the header' );
// The monogram lives on off the page: favicon, touch icon, OG image.
foreach ( array( 'favicon.svg', 'favicon.ico', 'apple-touch-icon.png', 'og-image.png', 'jl-mark.svg' ) as $f ) {
	ok( is_file( $root . "/assets/brand/$f" ), "assets/brand/$f still ships (the JL monogram stays the icon and OG image)" );
}

echo "\nGroup 2: nothing in the theme references the dead attachment\n";
$hits = array();
foreach ( array( 'parts', 'templates', 'patterns', 'blocks', 'inc', 'assets/css', 'assets/js', 'styles' ) as $dir ) {
	$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root . '/' . $dir, FilesystemIterator::SKIP_DOTS ) );
	foreach ( $it as $f ) {
		if ( false !== strpos( (string) file_get_contents( (string) $f ), 'cropped-jl_logo' ) ) { $hits[] = substr( (string) $f, strlen( $root ) + 1 ); }
	}
}
ok( empty( $hits ), 'zero references to cropped-jl_logo-min-150x150.webp' . ( $hits ? ': ' . implode( ', ', $hits ) : '' ) );

echo "\nGroup 3: the wordmark's face, size and ink\n";
$heights = array(); $widths = array();
foreach ( array( 'critical', 'layout', 'responsive' ) as $css ) {
	$src = (string) file_get_contents( $root . "/assets/css/$css.css" );
	preg_match_all( '/\.sn-wordmark\s*\{([^}]*)\}/s', $src, $m );
	foreach ( $m[1] as $body ) {
		if ( preg_match( '/height:\s*(\d+)px/', $body, $h ) ) { $heights[] = (int) $h[1]; }
		if ( preg_match( '/\bwidth:\s*(\d+)px/', $body ) ) { $widths[] = $css; }
	}
}
ok( count( $heights ) >= 6, 'the wordmark box has explicit heights at every breakpoint, in critical and deferred (' . count( $heights ) . ' found)' );
ok( empty( $widths ), 'no fixed pixel width: the name sets its own width' );
$crit = (string) file_get_contents( $root . '/assets/css/critical.css' );
$wm   = preg_match( '/\.sn-wordmark\s*\{([^}]*)\}/s', $crit, $wb ) ? $wb[1] : '';
ok( str_contains( $wm, 'font-family: var(--wp--preset--font-family--heading)' ), 'the wordmark is set in the heading face, Bebas Neue (docs/BRAND.md: never substitute)' );
ok( 1 === preg_match( '/text-transform:\s*uppercase/', $wm ) && 1 === preg_match( '/white-space:\s*nowrap/', $wm ), 'uppercase, on one line' );
$fs = preg_match( '/font-size:\s*clamp\(\s*([0-9.]+)rem\s*,\s*[0-9.]+vw\s*,\s*([0-9.]+)rem\s*\)/', $wm, $fz ) ? array( (float) $fz[1] * 16, (float) $fz[2] * 16 ) : null;
ok( null !== $fs && $fs[0] >= 32 && $fs[1] <= 2.5 * $fs[0], 'the size is one clamp() with a 32px floor that keeps max within 2.5x min (zoom, WCAG 1.4.4): ' . ( $fs ? "{$fs[0]}-{$fs[1]}px" : 'none' ) );
ok( 1 === preg_match( '/color:\s*var\(--wp--preset--color--bone\)/', $wm ), 'the ink is the bone token (flipped by the dark layer)' );
ok( false === strpos( $crit, 'data-theme="dark"] .sn-wordmark' ), 'no second dark-mode rule for the wordmark: the token layer already does it' );

echo "\nGroup 4: no rounded corners\n";
$tj = json_decode( (string) file_get_contents( $root . '/theme.json' ), true );
ok( '0' === (string) ( $tj['styles']['elements']['button']['border']['radius'] ?? '' ), 'theme.json button radius is 0 (was 999px)' );

echo "\nGroup 5: the header mark's heights and the offsets keyed to them\n";
$crit = (string) file_get_contents( $root . '/assets/css/critical.css' );
$rem  = 16;
// header = 2 * (the part's block padding) + 2 * (link clear space) + mark; body offset keeps a 14px gap.
// The block padding is READ from parts/header.html and resolved through theme.json's
// spacing scale, never written here as a number: 13.7.1 changed it (0.44rem to 1rem)
// and a literal would have pinned the old header as correct.
$tj_sp  = array();
foreach ( (array) ( json_decode( (string) file_get_contents( $root . '/theme.json' ), true )['settings']['spacing']['spacingSizes'] ?? array() ) as $sz ) {
	$tj_sp[ (string) $sz['slug'] ] = (float) $sz['size'];
}
$hdr_part = (string) file_get_contents( $root . '/parts/header.html' );
preg_match( '/"padding":\{"top":"var:preset\|spacing\|(\d+)"/', $hdr_part, $vp );
$vpad = isset( $vp[1], $tj_sp[ $vp[1] ] ) ? $tj_sp[ $vp[1] ] * $rem : 0;
ok( $vpad > 0, sprintf( 'the header part\'s vertical padding resolves through theme.json (spacing-%s = %.1fpx)', $vp[1] ?? '?', $vpad ) );
preg_match( '/\.sn-logo-link\s*\{[^}]*padding-block:\s*([0-9.]+)rem/s', $crit, $pb );
ok( isset( $pb[1] ), 'the home link carries clear-space padding' );
$clear = isset( $pb[1] ) ? (float) $pb[1] * $rem : 0;
preg_match( '/\.sn-wordmark\s*\{[^}]*height:\s*(\d+)px/s', $crit, $mh );
$mark = (int) ( $mh[1] ?? 0 );
ok( 64 === $mark, "desktop wordmark box is 64px, the old mark's height, so the header does not move (got {$mark})" );
$header_h = 2 * $vpad + 2 * $clear + $mark;
// 13.8.0: the body offset is --sn-chrome-top; its first definition is the base :root.
preg_match( '/--sn-chrome-top:\s*(\d+)px/', $crit, $bp );
ok( isset( $bp[1] ) && (int) $bp[1] === (int) round( $header_h + 14 ), sprintf( 'the desktop body offset --sn-chrome-top (%s) = header %.0f + 14px gap', $bp[1] ?? '?', $header_h ) );
$base = (string) file_get_contents( $root . '/assets/css/base.css' );
ok( 1 === preg_match( '/scroll-padding-top:\s*calc\(var\(--sn-chrome-top\)\s*\+\s*16px\)/', $base ), 'scroll-padding-top stays 16px past the body offset (it reads the variable)' );


echo "\nGroup 7: at every breakpoint the body pads for the WHOLE header\n";
// Found live on 2026-09-22: at 375px the header was 69px tall and the body padded 65, so
// the header covered the top of every page on phones. Group 5 only checks desktop; this
// walks each context. Header = 2 * vertical padding (the breakpoint's override, else the
// part's) + 2 * clear space + that breakpoint's wordmark box height.
function bm_contexts( $css ) {
	$css = (string) preg_replace( '#/\*.*?\*/#s', '', $css );
	$out = array( 'base' => $css );
	if ( preg_match_all( '/@media\s*\(max-width:\s*(\d+)px\)\s*\{/', $css, $m, PREG_OFFSET_CAPTURE ) ) {
		foreach ( $m[0] as $k => $hit ) {
			$start = $hit[1] + strlen( $hit[0] );
			for ( $i = $start, $d = 1; $i < strlen( $css ) && $d > 0; $i++ ) {
				$d += ( '{' === $css[ $i ] ) - ( '}' === $css[ $i ] );
			}
			$out[ $m[1][ $k ][0] ] = ( $out[ $m[1][ $k ][0] ] ?? '' ) . substr( $css, $start, $i - $start );
		}
	}
	return $out;
}
$ctx     = bm_contexts( $crit );
$checked = 0;
foreach ( array( 'base', '781', '480' ) as $bp ) {
	$chunk = $ctx[ $bp ] ?? '';
	if ( 'base' === $bp ) {
		$chunk = (string) preg_replace( '/@media[^{]*\{(?:[^{}]*\{[^{}]*\})*[^{}]*\}/s', '', $chunk );
	}
	$bmark = preg_match( '/\.sn-wordmark\s*\{[^}]*height:\s*(\d+)px/s', $chunk, $mm ) ? (int) $mm[1] : null;
	$bpad  = preg_match( '/:root\s*\{[^}]*--sn-chrome-top:\s*(\d+)px/s', $chunk, $pp ) ? (int) $pp[1] : null;
	$bv    = preg_match( '/\.sn-header[^{]*\{[^}]*padding-top:\s*([0-9.]+)rem\s*!important/s', $chunk, $vv ) ? (float) $vv[1] * $rem : $vpad;
	if ( null === $bmark || null === $bpad ) {
		continue;
	}
	++$checked;
	$hh = 2 * $bv + 2 * $clear + $bmark;
	ok( $bpad >= $hh, sprintf( '[%s] body pads %dpx for a %.0fpx header (%.1f padding x2 + %.0f clear + %dpx box)%s', $bp, $bpad, $hh, $bv, 2 * $clear, $bmark, $bpad >= $hh ? '' : ' -- the header covers the top of the page' ) );
}
ok( 3 === $checked, "all three breakpoints were measured (got {$checked}; fewer would pass vacuously)" );

echo "\nGroup 6: the brand appears once, top-left\n";
// Owner, 2026-09-22: the brand top-left already signs the page, and a second
// version of it bottom-left (13.6.0's stamp + wordmark + descriptor lockup) read
// as repetition, not as a signature. The footer carries no brand mark; its
// left edge belongs to the social links again, as it did before 13.6.0.
$footer = (string) file_get_contents( $root . '/parts/footer.html' );
ok( ! str_contains( $footer, 'footer-signature' ) && ! str_contains( $footer, 'jl-mark' ) && ! str_contains( $footer, 'jl-stamp' ), 'the footer part carries no brand mark, stamp or signature block' );
$first_block = preg_match( '/<div class="wp-block-group sn-footer[^>]*>\s*(?:<!--(?! wp:).*?-->\s*)*<!-- wp:([a-z0-9\/-]+)/s', $footer, $fb ) ? $fb[1] : '';
ok( 'social-links' === $first_block, "the social links are the footer's first block, so they sit at its left edge (got: {$first_block})" );
ok( ! is_dir( $root . '/blocks/footer-signature' ), 'the signature block is deleted, not just unplaced (a registered, unused block still shows in the inserter)' );
$mark_count = substr_count( (string) file_get_contents( $root . '/parts/header.html' ), 'class="sn-wordmark"' );
ok( 1 === $mark_count, "the header places the wordmark exactly once (got {$mark_count})" );
ok( ! str_contains( $footer, 'sn-wordmark' ), 'the footer carries no wordmark' );
// The wordmark is ~160px wider than the monogram. Core shows the full nav from
// 600px; beside it the header wrapped to two rows from 601 to 780. The
// hamburger runs to the theme's own 781px breakpoint instead.
ok( 1 === preg_match( '/@media\s*\(min-width:\s*600px\)\s*and\s*\(max-width:\s*781px\)\s*\{\s*\.sn-header \.wp-block-navigation__responsive-container-open[^{]*\{\s*display:\s*flex;\s*\}\s*\.sn-header \.wp-block-navigation__responsive-container:not\(\.hidden-by-default\):not\(\.is-menu-open\)\s*\{\s*display:\s*none;/s', $crit ), 'critical.css shows the hamburger and hides the inline nav from 600 to 781px, so the header stays one row beside the wordmark' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
