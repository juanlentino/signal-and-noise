<?php
/**
 * Standalone PHP test asserting theme.json declares brutalist element styles
 * for the `caption` (figcaption) and `cite` HTML elements.
 *
 * WP core's WP_Theme_JSON::ELEMENTS maps these element keys to real CSS
 * selectors (verified vs wp-includes/class-wp-theme-json.php master):
 *   - caption => '.wp-element-caption, .wp-block-audio figcaption,
 *                 .wp-block-embed figcaption, .wp-block-gallery figcaption,
 *                 .wp-block-image figcaption, .wp-block-table figcaption,
 *                 .wp-block-video figcaption'
 *   - cite    => 'cite'
 * Both are valid `styles.elements.*` keys in the Gutenberg theme.json v3
 * schema (stylesElementsPropertiesComplete -> stylesProperties), so the
 * typography/color we declare here is emitted to those selectors.
 *
 * The expected vocabulary mirrors the existing brutalist caption vocabulary
 * in assets/css/components.css (.sn-provenance-caption-sub): mono body family
 * (DM Mono), fontSize max(0.75rem, 11px) — honouring the 11px type floor,
 * uppercase, tracked letter-spacing, rust text color.
 *
 * @since theme v9.10.0
 */

if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) {
	http_response_code( 404 );
	exit;
}

// ─── Load theme.json ───────────────────────────────────────────────

$theme_json_path = __DIR__ . '/../theme.json';
$theme_json      = json_decode( (string) file_get_contents( $theme_json_path ), true );

if ( ! is_array( $theme_json ) ) {
	echo "FATAL: cannot read/parse theme.json at $theme_json_path\n";
	echo "Result: 0 passed, 1 failed.\n";
	exit( 1 );
}

// ─── Harness ───────────────────────────────────────────────────────
$pass = 0; $fail = 0;

function tje_ok( $cond, $msg ) {
	global $pass, $fail;
	if ( $cond ) {
		$pass++;
		echo "  PASS: $msg\n";
	} else {
		$fail++;
		echo "  FAIL: $msg\n";
	}
}

function tje_eq( $actual, $expected, $msg ) {
	global $pass, $fail;
	if ( $actual === $expected ) {
		$pass++;
		echo "  PASS: $msg (= " . var_export( $actual, true ) . ")\n";
	} else {
		$fail++;
		echo "  FAIL: $msg (expected " . var_export( $expected, true ) .
			", got " . var_export( $actual, true ) . ")\n";
	}
}

echo "theme.json caption/cite element-style suite — theme v9.10.0\n";

$elements = $theme_json['styles']['elements'] ?? array();
tje_ok( is_array( $elements ) && ! empty( $elements ), 'styles.elements exists and is non-empty' );

$mono_family = 'var(--wp--preset--font-family--body)'; // DM Mono — the brand mono.
$rust_color  = 'var(--wp--preset--color--rust)';
$font_size   = 'max(0.75rem, 11px)';                    // honours the 11px floor.

// ─── Test 1: caption element ───────────────────────────────────────
echo "\nTest 1: styles.elements.caption (figcaption vocabulary)\n";
$caption = $elements['caption'] ?? null;
tje_ok( is_array( $caption ), 'styles.elements.caption exists' );
if ( is_array( $caption ) ) {
	$ctypo = $caption['typography'] ?? array();
	tje_ok( is_array( $ctypo ), 'caption.typography is an object' );
	tje_eq( $ctypo['fontFamily'] ?? null, $mono_family, 'caption.typography.fontFamily is the mono (body) family' );
	tje_eq( $ctypo['fontSize'] ?? null, $font_size, 'caption.typography.fontSize honours the 11px floor' );
	tje_eq( $ctypo['textTransform'] ?? null, 'uppercase', 'caption.typography.textTransform is uppercase' );
	tje_ok(
		isset( $ctypo['letterSpacing'] ) && '' !== (string) $ctypo['letterSpacing'],
		'caption.typography.letterSpacing is tracked (non-empty)'
	);
	tje_eq( $caption['color']['text'] ?? null, $rust_color, 'caption.color.text is rust' );
}

// ─── Test 2: cite element ──────────────────────────────────────────
echo "\nTest 2: styles.elements.cite (quote attribution)\n";
$cite = $elements['cite'] ?? null;
tje_ok( is_array( $cite ), 'styles.elements.cite exists' );
if ( is_array( $cite ) ) {
	$ctypo = $cite['typography'] ?? array();
	tje_ok( is_array( $ctypo ), 'cite.typography is an object' );
	tje_eq( $ctypo['fontFamily'] ?? null, $mono_family, 'cite.typography.fontFamily is the mono (body) family' );
	tje_eq( $ctypo['fontSize'] ?? null, $font_size, 'cite.typography.fontSize honours the 11px floor' );
	tje_eq( $ctypo['textTransform'] ?? null, 'uppercase', 'cite.typography.textTransform is uppercase' );
	tje_ok(
		isset( $ctypo['letterSpacing'] ) && '' !== (string) $ctypo['letterSpacing'],
		'cite.typography.letterSpacing is tracked (non-empty)'
	);
	tje_eq( $cite['color']['text'] ?? null, $rust_color, 'cite.color.text is rust' );
}

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
