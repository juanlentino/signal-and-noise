<?php
/**
 * Tests: docs/ACCESSIBILITY.md agrees with the palettes it claims to measure.
 *
 * WHY. The previous version of that doc recorded ratios for ONE palette,
 * described itself as covering "every brand color × background pairing in the
 * current palette" — singular — and went on saying so through the High Contrast
 * variation becoming the served one and through dark mode shipping in v12.0.0.
 * Every number in it was correct for `root` and irrelevant to what readers met.
 * Nothing contradicted it, because nothing read it.
 *
 * A document that asserts measurements is a claim like any other, and this repo
 * has a rule about those: a claim of enforcement is not enforcement. This file
 * is the enforcement. Every ratio in the doc is recomputed from
 * theme.json, styles/high-contrast.json and the dark override in critical.css,
 * and any disagreement fails — including a stale hex, a stale verdict, or a row
 * quietly deleted.
 *
 * WHAT IT CANNOT SEE: whether a pairing is REACHED by a reader. That is a fact
 * about CSS and HTML, and the doc's "which of these actually occur" section is
 * prose backed by a scan done by hand. This file checks the arithmetic, not the
 * relevance.
 *
 * @since 2026-08-20
 */
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }

$pass = 0; $fail = 0;
function ok( $c, $m ) { global $pass, $fail; if ( $c ) { ++$pass; echo "PASS: $m\n"; } else { ++$fail; echo "FAIL: $m\n"; } }
echo "docs/ACCESSIBILITY.md agrees with the palettes\n\n";

$root = realpath( __DIR__ . '/..' );

function adp_lum( $hex ) {
	$hex = ltrim( strtolower( trim( $hex ) ), '#' );
	if ( 3 === strlen( $hex ) ) { $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2]; }
	$c = array();
	foreach ( array( 0, 2, 4 ) as $i ) {
		$v = hexdec( substr( $hex, $i, 2 ) ) / 255;
		$c[] = ( $v <= 0.03928 ) ? $v / 12.92 : pow( ( $v + 0.055 ) / 1.055, 2.4 );
	}
	return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
}
function adp_ratio( $a, $b ) {
	$x = adp_lum( $a ); $y = adp_lum( $b );
	return ( max( $x, $y ) + 0.05 ) / ( min( $x, $y ) + 0.05 );
}
/** The verdict rule the doc uses, in one place so the doc and the test cannot disagree about it. */
function adp_verdict( $r ) {
	if ( $r >= 4.5 ) { return 'PASS'; }
	return $r >= 3.0 ? 'large-only' : 'FAIL';
}

// ── the palettes, from source ──────────────────────────────────────────────
$palettes = array();
foreach ( array( 'root' => '/theme.json', 'high-contrast' => '/styles/high-contrast.json' ) as $id => $rel ) {
	$json = json_decode( (string) @file_get_contents( $root . $rel ), true );
	foreach ( (array) ( $json['settings']['color']['palette'] ?? array() ) as $e ) {
		$palettes[ $id ][ (string) $e['slug'] ] = strtolower( (string) $e['color'] );
	}
}
$palettes['dark'] = $palettes['root'] ?? array();
$crit = (string) file_get_contents( $root . '/assets/css/critical.css' );
if ( preg_match( '/:root\[data-theme="dark"\]\s*\{(.*?)\n\}/s', $crit, $m ) ) {
	foreach ( explode( ';', $m[1] ) as $d ) {
		$at = strpos( $d, ':' );
		if ( false === $at ) { continue; }
		$k = trim( substr( $d, 0, $at ) );
		if ( 0 === strpos( $k, '--wp--preset--color--' ) ) {
			$palettes['dark'][ substr( $k, 21 ) ] = strtolower( trim( substr( $d, $at + 1 ) ) );
		}
	}
}
ok( 7 === count( $palettes['root'] ) && 7 === count( $palettes['high-contrast'] ), 'root and high-contrast each load 7 colours' );
ok( '#0a0a0a' === ( $palettes['dark']['void'] ?? '' ), 'the dark override loads (dark void is #0a0a0a — a copy of root would pass this file vacuously)' );

// ── the doc's rows ─────────────────────────────────────────────────────────
$doc = (string) file_get_contents( $root . '/docs/ACCESSIBILITY.md' );
$re  = '/^\|\s*`([a-z-]+)`\s*\|\s*`([a-z]+)`\s*\|\s*`([a-z]+)`\s*\|\s*`(#[0-9a-f]{6})`\s+on\s+`(#[0-9a-f]{6})`\s*\|\s*\*\*([0-9.]+)\s*:\s*1\*\*\s*\|\s*([A-Za-z-]+)\s*\|/mi';
preg_match_all( $re, $doc, $rows, PREG_SET_ORDER );

// A parity check over an empty set passes. Pin the shape first.
ok( count( $rows ) >= 30, sprintf( 'the doc has at least 30 measured rows (%d parsed) — a deleted table would otherwise pass silently', count( $rows ) ) );
$seen_pal = array_unique( array_map( function ( $r ) { return $r[1]; }, $rows ) );
sort( $seen_pal );
ok( array( 'dark', 'high-contrast', 'root' ) === $seen_pal, 'all three palettes appear in the tables (' . implode( ', ', $seen_pal ) . ')' );

$bad_hex = array(); $bad_ratio = array(); $bad_verdict = array(); $unknown = array();
foreach ( $rows as $r ) {
	list( , $pal, $fg, $bg, $fg_hex, $bg_hex, $ratio, $verdict ) = $r;
	if ( ! isset( $palettes[ $pal ][ $fg ], $palettes[ $pal ][ $bg ] ) ) { $unknown[] = "$pal $fg/$bg"; continue; }
	$real_fg = $palettes[ $pal ][ $fg ];
	$real_bg = $palettes[ $pal ][ $bg ];
	if ( strtolower( $fg_hex ) !== $real_fg || strtolower( $bg_hex ) !== $real_bg ) {
		$bad_hex[] = sprintf( '%s %s/%s: doc says %s on %s, palette says %s on %s', $pal, $fg, $bg, $fg_hex, $bg_hex, $real_fg, $real_bg );
		continue;
	}
	$computed = adp_ratio( $real_fg, $real_bg );
	if ( abs( $computed - (float) $ratio ) >= 0.005 ) {
		$bad_ratio[] = sprintf( '%s %s on %s: doc says %s, computes %.2f', $pal, $fg, $bg, $ratio, $computed );
	}
	if ( adp_verdict( $computed ) !== $verdict ) {
		$bad_verdict[] = sprintf( '%s %s on %s (%.2f): doc says "%s", rule says "%s"', $pal, $fg, $bg, $computed, $verdict, adp_verdict( $computed ) );
	}
}
foreach ( array_merge( $unknown, $bad_hex, $bad_ratio, $bad_verdict ) as $b ) { echo "  -> $b\n"; }
ok( empty( $unknown ), 'every row names tokens that exist in that palette (' . count( $unknown ) . ' unknown)' );
ok( empty( $bad_hex ), 'every row\'s resolved hexes match the palette (' . count( $bad_hex ) . ' stale)' );
ok( empty( $bad_ratio ), 'every recorded ratio matches the computed one (' . count( $bad_ratio ) . ' drifted)' );
ok( empty( $bad_verdict ), 'every verdict matches the threshold rule (' . count( $bad_verdict ) . ' wrong)' );

// ── the doc must not misname what enforces it ──────────────────────────────
echo "\nGroup: the enforcement list names files that exist\n";
$named = array();
if ( preg_match_all( '#`(tests/[a-z0-9-]+\.php)`#', $doc, $t ) ) { $named = array_unique( $t[1] ); }
ok( count( $named ) >= 5, 'the doc names at least 5 enforcing suites (' . count( $named ) . ')' );
$missing = array_filter( $named, function ( $f ) use ( $root ) { return ! is_file( $root . '/' . $f ); } );
foreach ( $missing as $f ) { echo "  -> named but absent: $f\n"; }
ok( empty( $missing ), 'every suite the doc names exists (' . count( $missing ) . ' missing)' );
ok( in_array( 'tests/accessibility-doc-parity.php', $named, true ), 'the doc names THIS file as what keeps it honest' );

// ── negative controls ──────────────────────────────────────────────────────
echo "\nGroup: negative controls\n";
ok( abs( adp_ratio( '#000000', '#ffffff' ) - 21.0 ) < 0.01, 'the maths agrees with the spec boundary: 21:1' );
ok( 'PASS' === adp_verdict( 4.5 ) && 'large-only' === adp_verdict( 3.0 ) && 'FAIL' === adp_verdict( 2.99 ), 'the verdict rule sits exactly on the AA and large-text boundaries' );
// Doctor a row and prove the parser would catch it.
$doctored = preg_replace( '/\*\*21\.00 : 1\*\*/', '**99.00 : 1**', $doc, 1 );
preg_match_all( $re, $doctored, $drows, PREG_SET_ORDER );
$caught = false;
foreach ( $drows as $r ) {
	if ( '99.00' === $r[6] ) {
		$caught = abs( adp_ratio( $palettes[ $r[1] ][ $r[2] ], $palettes[ $r[1] ][ $r[3] ] ) - 99.0 ) >= 0.005;
	}
}
ok( $caught, 'NEGATIVE CONTROL: a doctored ratio in the doc IS caught by the same comparison' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
