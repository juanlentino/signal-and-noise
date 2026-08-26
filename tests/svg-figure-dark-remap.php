<?php
/**
 * Tests: the hand-authored SVG figures follow the palette into dark.
 *
 * WHAT THIS GUARDS. 13 core/html figures across 12 published notes paint no
 * background of their own, so they sit on the page ground, and their colours
 * are literal SVG presentation attributes. No instrument in this repo could
 * reach them: tests/front-end-css-contrast.php reads STYLESHEETS, and raw SVG
 * inside post_content is not a stylesheet. Measured on the dark ground,
 * fill="#222" — the figure TITLE in five notes — is 1.2:1.
 *
 * assets/css/components.css remaps those literals to tokens, dark scheme only,
 * in the two blocks this theme always writes in pairs. This file pins:
 *   1. both blocks exist and are BYTE-MIRRORED (one edited alone is the bug)
 *   2. every remap is JUSTIFIED — the original literal really does fail on the
 *      dark ground, so the map cannot quietly grow entries that fix nothing
 *   3. every remap TARGET clears AA on that ground at its role's threshold
 *   4. the documented exclusions stay excluded
 *
 * VACUOUS-PASS GUARD. front-end-css-contrast.php shipped a first version whose
 * rule regex matched NOTHING, so its dark palette stayed a copy of light and
 * the whole sweep compared light against light and passed. Every parse in this
 * file therefore asserts a non-zero count before anything is judged.
 */

if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }
$pass = 0; $fail = 0;
function ok( $c, $m ) { global $pass, $fail; if ( $c ) { $pass++; echo "PASS: $m\n"; } else { $fail++; echo "FAIL: $m\n"; } }

function svg_rgb( $v ) {
	$v = strtolower( trim( $v ) );
	if ( 'white' === $v ) { $v = '#ffffff'; }
	if ( preg_match( '/^#([0-9a-f]{3})$/', $v, $m ) ) { $v = '#' . $m[1][0] . $m[1][0] . $m[1][1] . $m[1][1] . $m[1][2] . $m[1][2]; }
	if ( ! preg_match( '/^#([0-9a-f]{6})$/', $v, $m ) ) { return null; }
	return array( hexdec( substr( $m[1], 0, 2 ) ), hexdec( substr( $m[1], 2, 2 ) ), hexdec( substr( $m[1], 4, 2 ) ) );
}
function svg_lum( $rgb ) {
	$c = array();
	foreach ( $rgb as $ch ) { $s = $ch / 255; $c[] = ( $s <= 0.03928 ) ? $s / 12.92 : pow( ( $s + 0.055 ) / 1.055, 2.4 ); }
	return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
}
function svg_ratio( $a, $b ) {
	$ra = svg_rgb( $a ); $rb = svg_rgb( $b );
	if ( null === $ra || null === $rb ) { return null; }
	$l1 = svg_lum( $ra ); $l2 = svg_lum( $rb );
	if ( $l1 < $l2 ) { $t = $l1; $l1 = $l2; $l2 = $t; }
	return ( $l1 + 0.05 ) / ( $l2 + 0.05 );
}

$root = dirname( __DIR__ );
$css  = (string) file_get_contents( $root . '/assets/css/components.css' );
$crit = (string) file_get_contents( $root . '/assets/css/critical.css' );
ok( '' !== $css && '' !== $crit, 'both stylesheets load (a missing file must never read as "no findings")' );

/* ── the dark palette, read from source ── */
$dark = array();
if ( preg_match( '/:root\[data-theme="dark"\]\s*\{(.*?)\n\}/s', $crit, $m ) ) {
	foreach ( explode( ';', $m[1] ) as $d ) {
		if ( preg_match( '/(--wp--preset--color--[a-z-]+)\s*:\s*(#[0-9a-fA-F]{3,6})/', $d, $dm ) ) {
			$dark[ $dm[1] ] = strtolower( $dm[2] );
		}
	}
}
ok( count( $dark ) >= 7, 'dark palette parsed from critical.css (' . count( $dark ) . ' tokens) — a zero here would make every ratio below meaningless' );
$ground = $dark['--wp--preset--color--void'] ?? '';
ok( '#0a0a0a' === $ground, "the dark ground parses as #0a0a0a (got '$ground')" );

/* ── the remap, parsed out of both blocks ── */
function svg_map( $css, $prefix ) {
	$map = array();
	$q = preg_quote( $prefix, '/' );
	if ( preg_match_all( '/((?:' . $q . '\s+\.wp-block-html\s+svg\s+\[(?:fill|stroke)="[^"]+"\]\s*,?\s*)+)\{\s*(fill|stroke)\s*:\s*var\((--wp--preset--color--[a-z-]+)\)/s', $css, $ms, PREG_SET_ORDER ) ) {
		foreach ( $ms as $set ) {
			preg_match_all( '/\[(fill|stroke)="([^"]+)"\]/', $set[1], $lits, PREG_SET_ORDER );
			foreach ( $lits as $l ) { $map[ $l[1] . '|' . $l[2] ] = $set[3]; }
		}
	}
	return $map;
}
$a = svg_map( $css, ':root[data-theme="dark"]' );
$b = svg_map( $css, ':root:not([data-theme="light"])' );
ok( count( $a ) > 0, 'the [data-theme="dark"] block parses ' . count( $a ) . ' remap rules (0 would be the vacuous-pass trap)' );
ok( count( $b ) > 0, 'the prefers-color-scheme block parses ' . count( $b ) . ' remap rules' );
ksort( $a ); ksort( $b );
ok( $a === $b, 'BOTH dark blocks carry an IDENTICAL map — editing one alone is the failure this theme writes them in pairs to avoid' );

/* ── every remap must be justified, and must land somewhere legible ── */
// concrete carries rules/hairlines (non-text, AA 3:1); void IS the ground.
$thresh = array( 'concrete' => 3.0, 'bone' => 4.5, 'rust' => 4.5, 'blood' => 4.5 );
foreach ( $a as $key => $token ) {
	list( $prop, $lit ) = explode( '|', $key, 2 );
	$slug = substr( $token, strlen( '--wp--preset--color--' ) );
	$val  = $dark[ $token ] ?? '';
	ok( '' !== $val, "remap target $token resolves in the dark palette (for $prop=\"$lit\")" );
	if ( 'void' === $slug ) {
		ok( $val === $ground, "$prop=\"$lit\" maps to the GROUND itself — these are the figures' own local surfaces, which must disappear into the page, not contrast with it" );
		continue;
	}
	$before = svg_ratio( $lit, $ground );
	$after  = svg_ratio( $val, $ground );
	$min    = $thresh[ $slug ] ?? 4.5;
	ok( null !== $before && $before < $min, sprintf( '%s="%s" is JUSTIFIED — it measures %.1f:1 on the dark ground, under its %.1f threshold', $prop, $lit, (float) $before, $min ) );
	ok( null !== $after && $after >= $min, sprintf( '%s="%s" -> %s lands at %.1f:1, clearing %.1f', $prop, $lit, $slug, (float) $after, $min ) );
}

/* ── documented exclusions ── */
ok( ! isset( $a['fill|currentColor'] ) && ! isset( $a['stroke|currentColor'] ), 'currentColor is NOT remapped — 65 declarations already follow the cascade' );
ok( ! isset( $a['fill|#16a34a'] ) && ! isset( $a['stroke|#16a34a'] ), 'the green is NOT remapped — it clears AA on the dark ground and this palette has no green to map it to' );
ok( svg_ratio( '#16a34a', $ground ) >= 4.5, 'and that exclusion is measured, not assumed: #16a34a clears AA on the dark ground' );

/* ── light mode is deliberately untouched ── */
ok( 0 === preg_match( '/(?<!\w)\.wp-block-html\s+svg\s+\[(?:fill|stroke)=/', preg_replace( '/:root(\[data-theme="dark"\]|:not\(\[data-theme="light"\]\))[^{]*\{[^}]*\}/s', '', $css ) ), 'no UNSCOPED .wp-block-html svg remap exists — light mode renders exactly as authored' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
