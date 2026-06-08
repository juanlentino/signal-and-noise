<?php
/**
 * Standalone fixture tests for the theme's block-style variations (v9.10.0).
 *
 * Verifies inc/block-styles.php registers two opt-in block-style variations
 * on the `init` hook:
 *   1. core/separator -> "hairline" (sharp 1px concrete rule)
 *   2. core/quote     -> "signal"   (brutalist blood-accent quote)
 *
 * Each must register against the right block name, with the right style
 * `name`, and a NON-EMPTY `inline_style` that uses the theme's CSS
 * custom properties (var(--wp--preset--color--*)). Neither is a default
 * (opt-in only) — assert no `is_default` key leaks into the registration.
 *
 * The module registers via add_action('init', 'callback'); the stub below
 * invokes the closure immediately so the registrations land in
 * __test_registered_block_styles.
 *
 * Run from theme root:  php tests/block-styles.php
 *
 * @since theme v9.10.0
 */

// SECURITY: CLI / WP-CLI only. Direct HTTP GET would leak internal
// structure. Mirrors the guard in every other tests/*.php fixture.
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) {
	http_response_code( 404 );
	exit;
}

define( 'ABSPATH', '/' );

// --- WP function stubs --------------------------------------------------
// add_action: immediately invoke any init closure so its register_block_style
// calls fire during require_once below.
if ( ! function_exists( 'add_action' ) ) {
	function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		if ( 'init' === $hook && is_callable( $callback ) ) {
			call_user_func( $callback );
		}
	}
}

// register_block_style: capture each registration keyed by block name.
$GLOBALS['__test_registered_block_styles'] = array();
if ( ! function_exists( 'register_block_style' ) ) {
	function register_block_style( $block_name, $style_properties ) {
		$GLOBALS['__test_registered_block_styles'][] = array(
			'block' => $block_name,
			'props' => $style_properties,
		);
		return true;
	}
}

// Load the module under test — its add_action fires immediately via the stub.
require_once __DIR__ . '/../inc/block-styles.php';

// --- Harness (matches theme convention) --------------------------------
$pass = 0; $fail = 0;
function ha_eq( $expected, $actual, $msg ) {
	global $pass, $fail;
	if ( $expected === $actual ) {
		$pass++;
		echo "  PASS: $msg\n";
	} else {
		$fail++;
		echo "  FAIL: $msg\n    Expected: " . var_export( $expected, true ) . "\n    Actual:   " . var_export( $actual, true ) . "\n";
	}
}
function ha_true( $cond, $msg ) {
	global $pass, $fail;
	if ( $cond ) { $pass++; echo "  PASS: $msg\n"; } else { $fail++; echo "  FAIL: $msg\n"; }
}

// Helper: find a captured registration by block name + style name.
function ha_find_style( $block, $name ) {
	foreach ( $GLOBALS['__test_registered_block_styles'] as $reg ) {
		if ( $reg['block'] === $block && ( $reg['props']['name'] ?? null ) === $name ) {
			return $reg;
		}
	}
	return null;
}

// Real theme.json palette (slug => hex). Lets the COLOR-INTENT assertions
// resolve var(--wp--preset--color--SLUG) the way WP would at render time, so
// the test catches a light/dark INVERSION — not just CSS shape. Keep in sync
// with theme.json settings.color.palette.
$GLOBALS['__palette'] = array(
	'void'     => '#ffffff', // White
	'asphalt'  => '#f5f5f5', // Smoke
	'concrete' => '#d9d9d9', // Concrete
	'rust'     => '#666666', // Steel
	'bone'     => '#000000', // Black
	'blood'    => '#e00404', // Red
	'signal'   => '#ff4c47', // Signal
);

// Resolve every var(--wp--preset--color--SLUG) token in a CSS string to its
// theme.json hex, so assertions can reason about actual rendered colour.
function ha_resolve_colors( $css ) {
	return preg_replace_callback(
		'/var\(--wp--preset--color--([a-z0-9-]+)\)/',
		function ( $m ) {
			return $GLOBALS['__palette'][ $m[1] ] ?? $m[0];
		},
		(string) $css
	);
}

// Perceived luminance (0=black..1=white) of a #rrggbb hex.
function ha_luminance( $hex ) {
	$hex = ltrim( (string) $hex, '#' );
	if ( 6 !== strlen( $hex ) ) {
		return -1.0;
	}
	$r = hexdec( substr( $hex, 0, 2 ) );
	$g = hexdec( substr( $hex, 2, 2 ) );
	$b = hexdec( substr( $hex, 4, 2 ) );
	return ( 0.2126 * $r + 0.7152 * $g + 0.0722 * $b ) / 255.0;
}

// --- Test 0: exactly two styles registered -----------------------------
echo "\nTest: two block-style variations registered\n";
ha_eq( 2, count( $GLOBALS['__test_registered_block_styles'] ), 'exactly 2 register_block_style calls' );

// --- Test 1: core/separator -> hairline --------------------------------
echo "\nTest: core/separator \"hairline\"\n";
$hairline = ha_find_style( 'core/separator', 'hairline' );
ha_true( null !== $hairline, 'registered against core/separator with name "hairline"' );
if ( $hairline ) {
	ha_eq( 'Hairline', $hairline['props']['label'] ?? null, 'label is "Hairline"' );
	ha_true(
		isset( $hairline['props']['inline_style'] ) && '' !== trim( (string) $hairline['props']['inline_style'] ),
		'inline_style is non-empty'
	);
	ha_true(
		false !== strpos( (string) ( $hairline['props']['inline_style'] ?? '' ), 'is-style-hairline' ),
		'inline_style targets .is-style-hairline'
	);
	ha_true(
		false !== strpos( (string) ( $hairline['props']['inline_style'] ?? '' ), 'var(--wp--preset--color--' ),
		'inline_style uses a theme color custom property'
	);
	ha_true( ! isset( $hairline['props']['is_default'] ), 'opt-in: no is_default key' );

	// T6-02: the Hairline must own its border colour. assets/css/components.css
	// sets `.wp-block-separator{border-color:concrete !important}`, which an
	// ordinary declaration can never beat — so the Hairline's colour was
	// silently dictated by that unrelated rule. The style must now declare its
	// own border colour with !important so it actually controls it.
	$hcss = (string) ( $hairline['props']['inline_style'] ?? '' );
	ha_true(
		(bool) preg_match( '/border-top-color\s*:[^;]*var\(--wp--preset--color--concrete\)\s*!important/', $hcss ),
		'T6-02: Hairline owns its border colour via an !important border-top-color (beats the base .wp-block-separator !important rule)'
	);
}

// --- Test 2: core/quote -> signal --------------------------------------
echo "\nTest: core/quote \"signal\"\n";
$signal = ha_find_style( 'core/quote', 'signal' );
ha_true( null !== $signal, 'registered against core/quote with name "signal"' );
if ( $signal ) {
	ha_eq( 'Signal', $signal['props']['label'] ?? null, 'label is "Signal"' );
	ha_true(
		isset( $signal['props']['inline_style'] ) && '' !== trim( (string) $signal['props']['inline_style'] ),
		'inline_style is non-empty'
	);
	ha_true(
		false !== strpos( (string) ( $signal['props']['inline_style'] ?? '' ), 'is-style-signal' ),
		'inline_style targets .is-style-signal'
	);
	ha_true(
		false !== strpos( (string) ( $signal['props']['inline_style'] ?? '' ), 'var(--wp--preset--color--blood)' ),
		'inline_style uses the blood accent color'
	);
	ha_true( ! isset( $signal['props']['is_default'] ), 'opt-in: no is_default key' );

	// FIX 4: COLOUR INTENT — white-first brutalist = LIGHT field / DARK text.
	// The previous registration used bone field (#000 black) + void text
	// (#fff white): a black box with white text, the exact inverse. Resolve
	// the var() tokens to theme.json hex and assert the field is lighter than
	// the text (not just that *some* colour token is present).
	$scss    = ha_resolve_colors( (string) ( $signal['props']['inline_style'] ?? '' ) );
	$has_bg  = preg_match( '/background-color\s*:\s*(#[0-9a-fA-F]{6})/', $scss, $bg );
	$has_txt = preg_match( '/(?<![-\w])color\s*:\s*(#[0-9a-fA-F]{6})/', $scss, $tx );
	ha_true( 1 === $has_bg, 'Signal: background-color resolves to a concrete hex' );
	ha_true( 1 === $has_txt, 'Signal: text color resolves to a concrete hex' );
	if ( $has_bg && $has_txt ) {
		$bg_lum = ha_luminance( $bg[1] );
		$tx_lum = ha_luminance( $tx[1] );
		ha_true(
			$bg_lum > 0.5,
			"FIX 4: Signal FIELD is light (luminance {$bg[1]}=" . round( $bg_lum, 2 ) . ' > 0.5)'
		);
		ha_true(
			$tx_lum < 0.5,
			"FIX 4: Signal TEXT is dark (luminance {$tx[1]}=" . round( $tx_lum, 2 ) . ' < 0.5)'
		);
		ha_true(
			$bg_lum > $tx_lum,
			'FIX 4: Signal is light-field/dark-text (NOT the old inverted black-box/white-text)'
		);
	}
}

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
