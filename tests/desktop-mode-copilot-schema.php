<?php
/**
 * Standalone fixture tests for inc/desktop-mode-copilot-schema.php (theme v10.42.3).
 *
 * WHY THIS FILE EXISTS
 *
 * Desktop Mode 0.9.4+ makes the AI Copilot's tools WordPress Abilities and
 * auto-enrols EVERY read-only ability on the site — "No opt-in: register a
 * read-only ability and the assistant can use it." There is no opt-out. Its
 * converter (includes/ai-copilot/search.php) passes each ability's input_schema
 * to the provider RAW, and three shapes that are perfectly valid JSON Schema are
 * rejected by Anthropic. ONE bad tool 400s the ENTIRE assistant, not just its
 * own tool — Ask AI dies site-wide.
 *
 * This theme registers all three shapes, and they are load-bearing:
 *   1. 'type' => array( 'object', 'null' )  — the GET/null run-path
 *   2. top-level 'anyOf'                    — "supply post_id OR slug"
 *   3. 'properties' => array()              — a no-args ability (PHP [] not {})
 *
 * Until now the theme was only safe because the COMPANION PLUGIN happened to be
 * active and normalized the whole tool list at desktop_mode_ai_tools. That is an
 * undeclared cross-dependency: deactivate the plugin (or run this theme without
 * it) and Ask AI dies, with a 400 that names a tool INDEX and never mentions
 * abilities, the theme, or the Copilot. The theme must defend its own schemas.
 *
 * So these tests deliberately DO NOT define the plugin's
 * sn_mcp_normalize_schema(). Standing alone IS the property under test.
 *
 * Upstream: WordPress/desktop-mode#362.
 *
 * Run from theme root:
 *   php tests/desktop-mode-copilot-schema.php
 *
 * @since theme v10.42.3
 */

// SECURITY: Prevent web access. Test fixture, not a runtime module.
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) {
	http_response_code( 404 );
	exit;
}

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/' );
}

// --- Harness -----------------------------------------------------------
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
	if ( $cond ) { $pass++; echo "  PASS: $msg\n"; }
	else { $fail++; echo "  FAIL: $msg\n"; }
}

// --- WP stubs ----------------------------------------------------------
// add_filter HONOURS priority, and apply_filters replays in real WP order
// (priority ascending, registration order within a priority). A stub that drops
// $p would make every ordering assertion below vacuous — it could not express
// "runs last", so the test would pass against code that runs first.
$GLOBALS['__filters'] = array();
if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( $hook, $cb, $p = 10, $a = 1 ) {
		$GLOBALS['__filters'][ $hook ][ $p ][] = $cb;
		return true;
	}
}
if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $hook, $value ) {
		$by_priority = $GLOBALS['__filters'][ $hook ] ?? array();
		ksort( $by_priority, SORT_NUMERIC );
		foreach ( $by_priority as $cbs ) {
			foreach ( $cbs as $cb ) { $value = $cb( $value ); }
		}
		return $value;
	}
}
if ( ! function_exists( 'add_action' ) ) { function add_action() {} }

// --- SUT ---------------------------------------------------------------
require_once __DIR__ . '/../inc/desktop-mode-copilot-schema.php';

// NOTE: sn_mcp_normalize_schema() (the plugin's) is deliberately NOT defined
// anywhere in this file. If the theme's protection silently delegated to it,
// everything below would fatal — which is exactly the coupling we're removing.
ha_true( ! function_exists( 'sn_mcp_normalize_schema' ),
	'the plugin\'s normalizer is absent — the theme is standing alone (the whole point)' );

echo "\n-- the module is actually LOADED by the theme --\n";
// Everything below this point require_once's the SUT directly, so it would all
// pass just as happily if functions.php never loaded the file — the module
// would be dead in production and the suite would stay green. That exact class
// of failure ("a surface silently does nothing", CI green throughout) is what
// cost the companion plugin eight releases. Assert the wiring, not just the
// logic.
$fn_php = file_get_contents( __DIR__ . '/../functions.php' );
ha_true( false !== strpos( $fn_php, "require_once __DIR__ . '/inc/desktop-mode-copilot-schema.php';" ),
	'functions.php require_once\'s the module (without this it never runs in production)' );
ha_true(
	strpos( $fn_php, "require_once __DIR__ . '/inc/desktop-mode-copilot-schema.php';" )
		> strpos( $fn_php, "require_once __DIR__ . '/inc/abilities-registration.php';" ),
	'it loads alongside the abilities it protects' );

echo "\n-- registration --\n";
ha_true( isset( $GLOBALS['__filters']['desktop_mode_ai_tools'] ),
	'the theme registers its own desktop_mode_ai_tools filter' );
ha_true( isset( $GLOBALS['__filters']['desktop_mode_ai_tools'][ PHP_INT_MAX ] ),
	'it runs at PHP_INT_MAX — nothing can inject a tool downstream of it' );

echo "\n-- the three shapes that 400 the provider --\n";
$out = apply_filters( 'desktop_mode_ai_tools', array(
	// Shape 1 — the union type (21 plugin + several theme abilities).
	array( 'type' => 'function', 'name' => 'union', 'parameters' => array(
		'type'       => array( 'object', 'null' ),
		'properties' => array( 'a' => array( 'type' => 'string' ) ),
	) ),
	// Shape 2 — the theme's REAL get-active-template-structure schema.
	array( 'type' => 'function', 'name' => 'tmpl', 'parameters' => array(
		'type'       => 'object',
		'properties' => array(
			'post_id'   => array( 'type' => 'integer', 'minimum' => 1 ),
			'post_type' => array( 'type' => 'string', 'enum' => array( 'post', 'page' ) ),
			'slug'      => array( 'type' => 'string' ),
		),
		'anyOf'                => array(
			array( 'required' => array( 'post_id' ) ),
			array( 'required' => array( 'slug' ) ),
		),
		'additionalProperties' => false,
	) ),
	// Shape 3 — a no-args ability: PHP array() encodes to [] but needs {}.
	array( 'type' => 'function', 'name' => 'noargs', 'parameters' => array(
		'type'       => 'object',
		'properties' => array(),
	) ),
) );

ha_eq( 'object', $out[0]['parameters']['type'],
	'shape 1: union ["object","null"] is forced to the literal "object"' );
ha_true( ! isset( $out[1]['parameters']['anyOf'] ),
	'shape 2: the theme\'s real top-level anyOf is stripped' );
ha_true( $out[2]['parameters']['properties'] instanceof stdClass,
	'shape 3: empty properties become {} (stdClass), not []' );

echo "\n-- normalizing must not weaken the contract --\n";
ha_true( isset( $out[1]['parameters']['properties']['post_id'], $out[1]['parameters']['properties']['slug'] ),
	'stripping anyOf keeps every property — the tool still takes its arguments' );
ha_eq( false, $out[1]['parameters']['additionalProperties'],
	'the rest of the schema survives untouched' );
ha_eq( 'string', $out[0]['parameters']['properties']['a']['type'],
	'forcing the type leaves properties alone' );

echo "\n-- only the TOP level is a provider constraint --\n";
$nested = apply_filters( 'desktop_mode_ai_tools', array(
	array( 'type' => 'function', 'name' => 'nested', 'parameters' => array(
		'type'       => 'object',
		'properties' => array(
			'when' => array( 'anyOf' => array( array( 'type' => 'string' ), array( 'type' => 'integer' ) ) ),
		),
	) ),
) );
ha_true( isset( $nested[0]['parameters']['properties']['when']['anyOf'] ),
	'a NESTED anyOf is preserved — the provider accepts it, and rewriting it would silently narrow the ability' );

echo "\n-- idempotence (it runs on every request; it must be a no-op the 2nd time) --\n";
$once  = apply_filters( 'desktop_mode_ai_tools', array(
	array( 'type' => 'function', 'name' => 'x', 'parameters' => array( 'type' => array( 'object', 'null' ), 'properties' => array() ) ),
) );
$twice = apply_filters( 'desktop_mode_ai_tools', $once );
ha_eq( wp_json_encode_stub( $once ), wp_json_encode_stub( $twice ),
	'a conformant schema goes in and comes out identical' );

echo "\n-- never fabricate a schema --\n";
$noparams = apply_filters( 'desktop_mode_ai_tools', array(
	array( 'type' => 'function', 'name' => 'bare' ),
) );
ha_true( ! isset( $noparams[0]['parameters'] ),
	'a tool that declares no parameters is left alone, not given an invented schema' );

echo "\n-- garbage in, no fatal out --\n";
ha_true( is_array( apply_filters( 'desktop_mode_ai_tools', array( 'not-an-array', 42, null ) ) ),
	'non-array tool entries are skipped without fataling the request' );

echo "\n-- the reason for PHP_INT_MAX: a LATE plugin injecting a synthetic tool --\n";
// desktop_mode_ai_tools' own docblock invites exactly this ("injecting synthetic
// command tools"). We cannot know who else hooks it, so we simply run last.
$GLOBALS['__filters']['desktop_mode_ai_tools'][ 999 ][] = static function ( $tools ) {
	$tools[] = array( 'type' => 'function', 'name' => 'late', 'parameters' => array(
		'type'       => array( 'object', 'null' ),
		'properties' => array(),
		'anyOf'      => array( array( 'required' => array( 'x' ) ) ),
	) );
	return $tools;
};
$late = apply_filters( 'desktop_mode_ai_tools', array() );
$inj  = null;
foreach ( $late as $t ) { if ( 'late' === ( $t['name'] ?? '' ) ) { $inj = $t; } }
ha_true( null !== $inj, 'harness sanity: the late-injected tool reaches the list' );
ha_eq( 'object', $inj['parameters']['type'] ?? null,
	'a tool injected at priority 999 is STILL normalized (we run after it)' );
ha_true( ! isset( $inj['parameters']['anyOf'] ),
	'…and its top-level anyOf is stripped too' );
unset( $GLOBALS['__filters']['desktop_mode_ai_tools'][ 999 ] );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );

/**
 * Local JSON encoder for the idempotence comparison. Deliberately NOT named
 * wp_json_encode(): this fixture must never shadow a real WP function.
 */
function wp_json_encode_stub( $v ) {
	return json_encode( $v ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- CLI fixture, no WP loaded.
}
