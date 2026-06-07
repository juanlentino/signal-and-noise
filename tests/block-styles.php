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
}

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
