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

/* ─────────────────────────────────────────────────────────────────────────
   v12.3.0 — the keyboard focus ring is DECLARED, not only styled.

   Verified against the shipped WP stubs, not the 7.1 RC notes:
     VALID_ELEMENT_PSEUDO_SELECTORS = link + button, each accepting
       :link :any-link :visited :hover :focus :focus-visible :active
     VALID_BLOCK_PSEUDO_SELECTORS   = core/button ONLY
   So :focus-visible IS declarable at element level (the RC-era note that
   block pseudo-states also covered Navigation Link is wrong for shipped 7.1).

   WHY DECLARE IT AT ALL, when assets/css/base.css already ships the ring:
   theme.json declared 2 :hover and ZERO focus states, so the Site Editor
   showed nothing where the front end has a tested WCAG 2.4.7 treatment. The
   authoring surface was lying about what the theme does.

   WHY THE CSS RULE STAYS: that single rule also covers [role="button"],
   [role="link"], input[type=submit|button|checkbox|radio] and summary —
   none of which theme.json can express. Replacing it would cut 2.4.7
   coverage from ten selectors to two. This ADDS a declaration; it removes
   nothing. The parity assertion below is what keeps the duplication honest.
   ───────────────────────────────────────────────────────────────────────── */
$tj   = json_decode( file_get_contents( __DIR__ . '/../theme.json' ), true );
$base = file_get_contents( __DIR__ . '/../assets/css/base.css' );

foreach ( array( 'link', 'button' ) as $el ) {
	$o = $tj['styles']['elements'][ $el ][':focus-visible']['outline'] ?? null;
	tje_ok( is_array( $o ), "elements.$el declares a :focus-visible outline" );
	foreach ( array( 'width', 'style', 'color', 'offset' ) as $k ) {
		tje_ok( isset( $o[ $k ] ) && '' !== $o[ $k ], "elements.$el :focus-visible outline declares $k" );
	}
}

// PARITY with the shipped rule. Two places now describe one ring; this is the
// assertion that stops them drifting.
tje_ok(
	1 === preg_match( '/a:focus-visible,.*?\{([^}]*)\}/s', $base, $m_ring ),
	'the global :focus-visible rule was located in base.css (guard: the regex still matches)'
);
$ring = preg_replace( '/\s+/', ' ', $m_ring[1] ?? '' );
preg_match( '/outline:\s*([^;]+);/', $ring, $m_o );
preg_match( '/outline-offset:\s*([^;]+);/', $ring, $m_off );
$css_outline = trim( $m_o[1] ?? '' );
$css_offset  = trim( $m_off[1] ?? '' );
tje_ok( '' !== $css_outline && '' !== $css_offset, 'the CSS ring declares both outline and outline-offset' );

foreach ( array( 'link', 'button' ) as $el ) {
	$o = $tj['styles']['elements'][ $el ][':focus-visible']['outline'] ?? array();
	$composed = trim( ( $o['width'] ?? '' ) . ' ' . ( $o['style'] ?? '' ) . ' ' . ( $o['color'] ?? '' ) );
	tje_ok( $composed === $css_outline, "elements.$el ring matches base.css: '$composed' vs '$css_outline'" );
	tje_ok( ( $o['offset'] ?? '' ) === $css_offset, "elements.$el offset matches base.css ('$css_offset')" );
}

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
