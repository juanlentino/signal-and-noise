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
preg_match( '/\.jl-mark\s*\{[^}]*height:\s*(\d+)px/s', $crit, $mh );
$mark = (int) ( $mh[1] ?? 0 );
ok( 64 === $mark, "desktop mark is 64px (got {$mark})" );
ok( $clear + $vpad >= $mark * 20 / 128, sprintf( 'clear space above the mark (%.1fpx) is at least one stroke width (%.1fpx)', $clear + $vpad, $mark * 20 / 128 ) );
$header_h = 2 * $vpad + 2 * $clear + $mark;
// 13.8.0: the body offset is --sn-chrome-top; its first definition is the base :root.
preg_match( '/--sn-chrome-top:\s*(\d+)px/', $crit, $bp );
ok( isset( $bp[1] ) && (int) $bp[1] === (int) round( $header_h + 14 ), sprintf( 'the desktop body offset --sn-chrome-top (%s) = header %.0f + 14px gap', $bp[1] ?? '?', $header_h ) );
$base = (string) file_get_contents( $root . '/assets/css/base.css' );
ok( 1 === preg_match( '/scroll-padding-top:\s*calc\(var\(--sn-chrome-top\)\s*\+\s*16px\)/', $base ), 'scroll-padding-top stays 16px past the body offset (it reads the variable)' );
preg_match_all( '/\.jl-mark\s*\{[^}]*height:\s*(\d+)px/s', $crit . (string) file_get_contents( $root . '/assets/css/responsive.css' ), $all );
ok( min( array_map( 'intval', $all[1] ) ) >= 16, 'no state or breakpoint takes the mark under 16px (smallest: ' . min( array_map( 'intval', $all[1] ) ) . 'px)' );

echo "\nGroup 7: at every breakpoint the body pads for the WHOLE header\n";
// Found live on 2026-09-22: at 375px the header was 69px tall and the body padded 65, so
// the header covered the top of every page on phones. Group 5 only checks desktop; this
// walks each context. Header = 2 * vertical padding (the breakpoint's override, else the
// part's) + 2 * clear space + that breakpoint's mark height.
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
	$bmark = preg_match( '/\.jl-mark\s*\{[^}]*height:\s*(\d+)px/s', $chunk, $mm ) ? (int) $mm[1] : null;
	$bpad  = preg_match( '/:root\s*\{[^}]*--sn-chrome-top:\s*(\d+)px/s', $chunk, $pp ) ? (int) $pp[1] : null;
	$bv    = preg_match( '/\.sn-header[^{]*\{[^}]*padding-top:\s*([0-9.]+)rem\s*!important/s', $chunk, $vv ) ? (float) $vv[1] * $rem : $vpad;
	if ( null === $bmark || null === $bpad ) {
		continue;
	}
	++$checked;
	$hh = 2 * $bv + 2 * $clear + $bmark;
	ok( $bpad >= $hh, sprintf( '[%s] body pads %dpx for a %.0fpx header (%.1f padding x2 + %.0f clear + %dpx mark)%s', $bp, $bpad, $hh, $bv, 2 * $clear, $bmark, $bpad >= $hh ? '' : ' -- the header covers the top of the page' ) );
}
ok( 3 === $checked, "all three breakpoints were measured (got {$checked}; fewer would pass vacuously)" );

echo "\nGroup 6: the brand appears once, top-left\n";
// Owner, 2026-09-22: the mark top-left already signs the page, and a second
// version of it bottom-left (13.6.0's stamp + wordmark + descriptor lockup) read
// as repetition, not as a signature. The footer carries no brand mark; its
// left edge belongs to the social links again, as it did before 13.6.0.
$footer = (string) file_get_contents( $root . '/parts/footer.html' );
ok( ! str_contains( $footer, 'footer-signature' ) && ! str_contains( $footer, 'jl-mark' ) && ! str_contains( $footer, 'jl-stamp' ), 'the footer part carries no brand mark, stamp or signature block' );
$first_block = preg_match( '/<div class="wp-block-group sn-footer[^>]*>\s*(?:<!--(?! wp:).*?-->\s*)*<!-- wp:([a-z0-9\/-]+)/s', $footer, $fb ) ? $fb[1] : '';
ok( 'social-links' === $first_block, "the social links are the footer's first block, so they sit at its left edge (got: {$first_block})" );
ok( ! is_dir( $root . '/blocks/footer-signature' ), 'the signature block is deleted, not just unplaced (a registered, unused block still shows in the inserter)' );
$mark_count = substr_count( (string) file_get_contents( $root . '/parts/header.html' ), 'class="jl-mark"' );
ok( 1 === $mark_count, "the header places the mark exactly once (got {$mark_count})" );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
