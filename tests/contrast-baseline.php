<?php
/**
 * Standalone PHP test computing WCAG 2.1 relative-luminance ratios from
 * theme.json's palette. Asserts every documented docs/ACCESSIBILITY.md
 * pairing meets its threshold + tight-margin (e.g., blood-on-asphalt
 * baseline 4.60 ± 0.20).
 *
 * Why a tolerance window: a deliberate brand evolution would update BOTH
 * theme.json AND the test in the same commit. The window forces a
 * conscious decision (not silent erosion).
 *
 * Algorithm: WCAG 2.1 SC 1.4.3 (Contrast Minimum). Relative luminance L =
 * 0.2126*R + 0.7152*G + 0.0722*B where each channel is the sRGB-to-linear
 * transform of the 8-bit value / 255. Contrast ratio = (L1 + 0.05) / (L2 + 0.05).
 *
 * @since theme v9.5.0
 */

if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) {
	http_response_code( 404 );
	exit;
}

// ─── WCAG primitive ────────────────────────────────────────────────

/**
 * Compute WCAG 2.1 relative luminance for an sRGB hex color.
 *
 * @param string $hex Hex color, with or without leading '#'.
 * @return float Luminance in [0.0, 1.0].
 */
function snt_test_relative_luminance( $hex ) {
	$hex = ltrim( (string) $hex, '#' );
	if ( 3 === strlen( $hex ) ) {
		// Short form (#abc) → expand to long form (#aabbcc).
		$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
	}
	$r = hexdec( substr( $hex, 0, 2 ) ) / 255;
	$g = hexdec( substr( $hex, 2, 2 ) ) / 255;
	$b = hexdec( substr( $hex, 4, 2 ) ) / 255;

	$r = ( $r <= 0.03928 ) ? $r / 12.92 : pow( ( $r + 0.055 ) / 1.055, 2.4 );
	$g = ( $g <= 0.03928 ) ? $g / 12.92 : pow( ( $g + 0.055 ) / 1.055, 2.4 );
	$b = ( $b <= 0.03928 ) ? $b / 12.92 : pow( ( $b + 0.055 ) / 1.055, 2.4 );

	return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
}

/**
 * Compute WCAG 2.1 contrast ratio between two sRGB hex colors.
 *
 * @param string $hex_a
 * @param string $hex_b
 * @return float Ratio in [1.0, 21.0].
 */
function snt_test_contrast_ratio( $hex_a, $hex_b ) {
	$l1      = snt_test_relative_luminance( $hex_a );
	$l2      = snt_test_relative_luminance( $hex_b );
	$lighter = max( $l1, $l2 );
	$darker  = min( $l1, $l2 );
	return ( $lighter + 0.05 ) / ( $darker + 0.05 );
}

// ─── Load palette from theme.json ──────────────────────────────────

$theme_json_path = __DIR__ . '/../theme.json';
$theme_json      = json_decode( (string) file_get_contents( $theme_json_path ), true );

if ( ! is_array( $theme_json ) ) {
	echo "FATAL: cannot read theme.json at $theme_json_path\n";
	exit( 2 );
}

$palette_raw = $theme_json['settings']['color']['palette'] ?? array();
if ( ! is_array( $palette_raw ) || empty( $palette_raw ) ) {
	echo "FATAL: theme.json has no settings.color.palette array\n";
	exit( 2 );
}

$colors = array();
foreach ( $palette_raw as $entry ) {
	if ( is_array( $entry ) && isset( $entry['slug'], $entry['color'] ) ) {
		$colors[ $entry['slug'] ] = $entry['color'];
	}
}

// ─── Harness ───────────────────────────────────────────────────────
$pass = 0; $fail = 0;

function cb_gte( $actual, $expected, $msg ) {
	global $pass, $fail;
	if ( $actual >= $expected ) {
		$pass++;
		echo "  PASS: $msg (got " . sprintf( '%.2f', $actual ) . ":1)\n";
	} else {
		$fail++;
		echo "  FAIL: $msg (expected >= " . sprintf( '%.2f', $expected ) . ", got " . sprintf( '%.2f', $actual ) . ":1)\n";
	}
}

function cb_eq_approx( $actual, $expected, $tolerance, $msg ) {
	global $pass, $fail;
	$delta = abs( $actual - $expected );
	if ( $delta < $tolerance ) {
		$pass++;
		echo "  PASS: $msg (got " . sprintf( '%.2f', $actual ) . ", baseline " . sprintf( '%.2f', $expected ) . ", delta " . sprintf( '%.2f', $delta ) . ")\n";
	} else {
		$fail++;
		echo "  FAIL: $msg (got " . sprintf( '%.2f', $actual ) . ", baseline " . sprintf( '%.2f', $expected ) . ", delta " . sprintf( '%.2f', $delta ) . " >= tolerance " . sprintf( '%.2f', $tolerance ) . ")\n";
	}
}

function cb_required_color( $slug ) {
	global $colors, $pass, $fail;
	if ( isset( $colors[ $slug ] ) ) {
		$pass++;
		echo "  PASS: palette has '$slug' = {$colors[$slug]}\n";
	} else {
		$fail++;
		echo "  FAIL: palette missing required slug '$slug'\n";
	}
}

echo "WCAG 2.1 contrast baseline suite — theme v9.5.0\n";

// ─── Test 1: required palette slugs ────────────────────────────────
echo "\nTest 1: required palette slugs\n";
cb_required_color( 'bone' );
cb_required_color( 'void' );
cb_required_color( 'asphalt' );
cb_required_color( 'rust' );
cb_required_color( 'blood' );
cb_required_color( 'signal' );

if ( $fail > 0 ) {
	echo "\nFATAL: required palette slugs missing — aborting subsequent contrast tests.\n";
	echo "Result: $pass passed, $fail failed.\n";
	exit( 1 );
}

// ─── Test 2: AA-normal text pairings (>= 4.5) ──────────────────────
echo "\nTest 2: AA normal-text pairings (>= 4.5)\n";
cb_gte( snt_test_contrast_ratio( $colors['bone'], $colors['void'] ),     4.5, 'bone on void: AA normal text' );
cb_gte( snt_test_contrast_ratio( $colors['bone'], $colors['asphalt'] ),  4.5, 'bone on asphalt: AA normal text' );
cb_gte( snt_test_contrast_ratio( $colors['rust'], $colors['void'] ),     4.5, 'rust on void: AA normal text (secondary)' );
cb_gte( snt_test_contrast_ratio( $colors['rust'], $colors['asphalt'] ),  4.5, 'rust on asphalt: AA normal text (secondary on cards)' );
cb_gte( snt_test_contrast_ratio( $colors['blood'], $colors['void'] ),    4.5, 'blood on void: AA normal text (brand accent)' );
cb_gte( snt_test_contrast_ratio( $colors['blood'], $colors['asphalt'] ), 4.5, 'blood on asphalt: AA normal text (TIGHT — see ACCESSIBILITY.md Watch 1)' );

// ─── Test 3: AA large-text + non-text pairings (>= 3.0) ────────────
echo "\nTest 3: AA large-text / non-text pairings (>= 3.0)\n";
cb_gte( snt_test_contrast_ratio( $colors['signal'], $colors['void'] ),    3.0, 'signal on void: AA large text / non-text (hover state, requires underline per ACCESSIBILITY.md Watch 2)' );
cb_gte( snt_test_contrast_ratio( $colors['signal'], $colors['asphalt'] ), 3.0, 'signal on asphalt: AA large text / non-text (hover state on cards)' );

// ─── Test 4: baseline drift tolerance (watch the tight margin) ─────
echo "\nTest 4: baseline drift tolerance\n";

$current_blood_void     = snt_test_contrast_ratio( $colors['blood'], $colors['void'] );
$current_blood_asphalt  = snt_test_contrast_ratio( $colors['blood'], $colors['asphalt'] );
$current_signal_void    = snt_test_contrast_ratio( $colors['signal'], $colors['void'] );
$current_signal_asphalt = snt_test_contrast_ratio( $colors['signal'], $colors['asphalt'] );
$current_rust_void      = snt_test_contrast_ratio( $colors['rust'], $colors['void'] );

// Baselines from docs/ACCESSIBILITY.md (measured 2026-05-26).
// Tolerance ±0.20 → any meaningful palette tweak fails the test, forcing
// an explicit decision (update theme.json AND this test in the same commit).
cb_eq_approx( $current_blood_void,     5.01, 0.20, 'blood-on-void baseline drift within tolerance (baseline 5.01)' );
cb_eq_approx( $current_blood_asphalt,  4.60, 0.20, 'blood-on-asphalt baseline drift within tolerance (baseline 4.60 — TIGHT)' );
cb_eq_approx( $current_signal_void,    3.29, 0.20, 'signal-on-void baseline drift within tolerance (baseline 3.29)' );
cb_eq_approx( $current_signal_asphalt, 3.02, 0.20, 'signal-on-asphalt baseline drift within tolerance (baseline 3.02)' );
cb_eq_approx( $current_rust_void,      5.74, 0.20, 'rust-on-void baseline drift within tolerance (baseline 5.74)' );

// ─── Test 5: maximum-contrast sanity check ─────────────────────────
echo "\nTest 5: maximum-contrast sanity\n";
$bone_void_ratio = snt_test_contrast_ratio( $colors['bone'], $colors['void'] );
cb_gte( $bone_void_ratio, 20.0, 'bone-on-void approaches WCAG max (21.0) — sanity check that #000 / #fff is configured' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
