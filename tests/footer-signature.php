<?php
/**
 * Standalone test: the footer signature and the header mark's size.
 *
 * Convention: the header carries the monogram alone; the full lockup (stamp
 * + wordmark + descriptor) lives once, in the footer. This pins the block's
 * render against docs/BRAND.md's stamp geometry and typography tokens, its
 * single placement in parts/footer.html, and the header mark's re-measured
 * heights (64/40, 48/32, 36/28: the glyph height the 80px raster box showed,
 * with one stroke of clear space) against the body offsets keyed to them.
 *
 * Run: php tests/footer-signature.php
 */
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', '/' ); }

$pass = 0; $fail = 0;
function ok( $c, $m ) { global $pass, $fail; if ( $c ) { $pass++; echo "PASS: $m\n"; } else { $fail++; echo "FAIL: $m\n"; } }

function esc_html__( $s ) { return htmlspecialchars( $s, ENT_QUOTES ); }
function esc_attr__( $s ) { return htmlspecialchars( $s, ENT_QUOTES ); }
function esc_url( $s ) { return $s; }
function home_url( $p = '' ) { return 'https://x.test' . $p; }

$root = dirname( __DIR__ );
ob_start(); require $root . '/blocks/footer-signature/render.php'; $html = ob_get_clean();

echo "footer-signature\n\nGroup 1: the render is the lockup, in the brand's geometry and tokens\n";
ok( 1 === preg_match( '/^<a href="https:\/\/x\.test\/" class="sn-signature" aria-label="Juan Lentino, home">/', $html ), 'one home link with one accessible name' );
ok( str_contains( $html, '<rect x="6" y="6" width="188" height="188" stroke-width="12"/>' ), 'the stamp frame is the canonical rect (6/6/188, stroke 12)' );
ok( str_contains( $html, '<g transform="translate(45 51.8) scale(0.753)" stroke-width="20"><path d="M54 10V96A22 22 0 0 1 10 96"/><path d="M94 10V118H136"/></g>' ), 'the mark inside is the canonical stamp placement (translate 45 51.8, scale 0.753, stroke 20)' );
ok( str_contains( $html, 'viewBox="0 0 200 200" fill="none" stroke="currentColor" aria-hidden="true"' ), 'the SVG is currentColor and decorative (the name is text beside it)' );
ok( str_contains( $html, '<span class="sn-signature__name">Juan Lentino</span>' ) && str_contains( $html, '<span class="sn-signature__role">Producer / Mix engineer / Strategist</span>' ), 'wordmark and descriptor are TEXT, not paths' );
ok( 1 === substr_count( $html, '<svg' ), 'exactly one SVG' );

$css = (string) file_get_contents( $root . '/assets/css/layout.css' );
ok( 1 === preg_match( '/\.sn-signature__name\s*\{[^}]*font-family:\s*var\(--wp--preset--font-family--heading\)[^}]*letter-spacing:\s*var\(--wp--custom--letter-spacing--wide\)/s', $css ), 'wordmark: Bebas (heading) at letter-spacing wide, per BRAND.md' );
ok( 1 === preg_match( '/\.sn-signature__role\s*\{[^}]*font-family:\s*var\(--wp--preset--font-family--body\)[^}]*font-weight:\s*500[^}]*letter-spacing:\s*var\(--wp--custom--letter-spacing--wide\)/s', $css ), 'descriptor: DM Mono (body) 500 at letter-spacing wide (ultra over-tracked at 11px: the descriptor came out 1.8x the wordmark; owner, 2026-09-21)' );
ok( 1 === preg_match( '/\.sn-signature\s*\{[^}]*color:\s*var\(--wp--preset--color--rust\)/s', $css ), 'the signature is rust, the footer\'s quiet colour (the mark is never drawn in an accent)' );
ok( 1 === preg_match( '/\.sn-signature__stamp\s*\{[^}]*width:\s*32px;\s*height:\s*32px/s', $css ), 'the stamp is 32px square (both axes, never one alone), taller than the two text lines so it anchors the lockup' );

echo "\nGroup 2: placed once, in the footer, before the social links\n";
$footer = (string) file_get_contents( $root . '/parts/footer.html' );
ok( 1 === substr_count( $footer, '<!-- wp:signal-noise/footer-signature /-->' ), 'the footer part places the block exactly once' );
ok( strpos( $footer, 'wp:signal-noise/footer-signature' ) < strpos( $footer, 'wp:social-links' ), 'it leads the bar: signature, then the social links' );
$header = (string) file_get_contents( $root . '/parts/header.html' );
ok( ! str_contains( $header, 'footer-signature' ) && ! str_contains( $header, 'sn-signature' ), 'the header keeps the monogram alone' );
ok( in_array( 'footer-signature', sn_slugs_from_source( $root ), true ), 'the slug is in sn_php_only_block_slugs()' );
function sn_slugs_from_source( $root ) {
	preg_match( '/function sn_php_only_block_slugs\(\) \{\s*return array\(([^)]*)\)/s', (string) file_get_contents( $root . '/inc/blocks-php-only.php' ), $m );
	return array_map( static fn( $s ) => trim( $s, " '" ), explode( ',', $m[1] ?? '' ) );
}
ok( str_contains( (string) file_get_contents( $root . '/inc/editor-block-palette.php' ), "'signal-noise/footer-signature'" ), 'the palette allowlist admits it' );

echo "\nGroup 3: the header mark's heights and the offsets keyed to them\n";
$crit = (string) file_get_contents( $root . '/assets/css/critical.css' );
$rem  = 16;
// header = 2 * (0.44rem block padding) + 2 * (link clear space) + mark; body offset keeps a 14px gap.
preg_match( '/\.sn-logo-link\s*\{[^}]*padding-block:\s*([0-9.]+)rem/s', $crit, $pb );
ok( isset( $pb[1] ), 'the home link carries clear-space padding' );
$clear = isset( $pb[1] ) ? (float) $pb[1] * $rem : 0;
preg_match( '/\.jl-mark\s*\{[^}]*height:\s*(\d+)px/s', $crit, $mh );
$mark = (int) ( $mh[1] ?? 0 );
ok( 64 === $mark, "desktop mark is 64px (got {$mark})" );
ok( $clear + 0.44 * $rem >= $mark * 20 / 128, sprintf( 'clear space above the mark (%.1fpx) is at least one stroke width (%.1fpx)', $clear + 0.44 * $rem, $mark * 20 / 128 ) );
$header_h = 2 * 0.44 * $rem + 2 * $clear + $mark;
preg_match( '/body\s*\{[^}]*padding-top:\s*(\d+)px/s', $crit, $bp );
ok( isset( $bp[1] ) && (int) $bp[1] === (int) round( $header_h + 14 ), sprintf( 'body padding-top (%s) = header %.0f + 14px gap', $bp[1] ?? '?', $header_h ) );
$base = (string) file_get_contents( $root . '/assets/css/base.css' );
ok( 1 === preg_match( '/scroll-padding-top:\s*' . (int) ( $bp[1] ?? 0 ) + 16 . 'px/', $base ), 'scroll-padding-top stays 16px past the body offset' );
preg_match_all( '/\.jl-mark\s*\{[^}]*height:\s*(\d+)px/s', $crit . (string) file_get_contents( $root . '/assets/css/responsive.css' ), $all );
ok( min( array_map( 'intval', $all[1] ) ) >= 16, 'no state or breakpoint takes the mark under 16px (smallest: ' . min( array_map( 'intval', $all[1] ) ) . 'px)' );
ok( str_contains( $crit, 'height: 48px;' ) && str_contains( $crit, 'height: 36px;' ), 'the two lower breakpoints are 48 and 36: with the clear space they equal the previous 56 and 44 header heights, so their body offsets (80, 65) are unchanged' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
