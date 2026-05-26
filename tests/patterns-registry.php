<?php
/**
 * Standalone fixture tests for the theme's block-pattern registry (v9.2.0).
 *
 * Verifies:
 *   1. The signal-noise pattern category is registered (by inc/patterns.php).
 *   2. All 5 patterns are present with required header fields:
 *      - signal-noise/hero-dossier   (pre-existing, v7.5.0)
 *      - signal-noise/section-constrained (pre-existing, v7.5.0)
 *      - signal-noise/pull-quote     (new in v9.2.0)
 *      - signal-noise/compare-columns (new in v9.2.0)
 *      - signal-noise/steps-enumerated (new in v9.2.0)
 *   3. The parts/post-closing.html template part file exists and parses
 *      as valid WordPress block markup. (Will fail until Task 5 ships it.)
 *   4. templates/single.html references the post-closing template part.
 *      (Will fail until Task 5 ships it.)
 *
 * Pattern files are PHP with a header docblock; this test parses them
 * directly rather than invoking WordPress's pattern registry (which
 * would require a full WP bootstrap).
 *
 * Run from theme root:  php tests/patterns-registry.php
 *
 * @since theme v9.2.0
 */

// SECURITY: Prevent web access. This file is a test fixture, not a runtime
// module. Direct HTTP GET would leak internal structure (function names,
// ability slugs, capability matrices). Allow only CLI / WP-CLI invocations.
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) {
    http_response_code( 404 );
    exit;
}

define( 'ABSPATH', '/' );

// --- WP function stubs --------------------------------------------------
// add_action stub: immediately invoke the closure (the inc/patterns.php
// file registers categories via add_action('init', closure); by invoking
// the closure here, the captured registrations land in __test_registered_categories.
$GLOBALS['__test_registered_categories'] = array();
if ( ! function_exists( 'add_action' ) ) {
	function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		if ( 'init' === $hook && is_callable( $callback ) ) {
			call_user_func( $callback );
		}
	}
}
if ( ! function_exists( 'register_block_pattern_category' ) ) {
	function register_block_pattern_category( $slug, $args ) {
		$GLOBALS['__test_registered_categories'][ $slug ] = $args;
		return true;
	}
}

// Load inc/patterns.php — its add_action fires immediately via our stub,
// populating __test_registered_categories.
require_once __DIR__ . '/../inc/patterns.php';

// --- Harness (matches theme convention from tests/abilities-registration.php) ---
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

// --- Test 1: signal-noise category is registered -----------------------
echo "\nTest: signal-noise pattern category is registered (inc/patterns.php)\n";
ha_true(
	isset( $GLOBALS['__test_registered_categories']['signal-noise'] ),
	'signal-noise category present'
);
ha_eq(
	'Signal & Noise',
	$GLOBALS['__test_registered_categories']['signal-noise']['label'] ?? null,
	'category label is "Signal & Noise"'
);

// --- Test 2: All 5 pattern files exist + have required headers ---------
$expected_patterns = array(
	'hero-dossier'        => array( 'Slug' => 'signal-noise/hero-dossier' ),
	'section-constrained' => array( 'Slug' => 'signal-noise/section-constrained' ),
	'pull-quote'          => array( 'Slug' => 'signal-noise/pull-quote' ),
	'compare-columns'     => array( 'Slug' => 'signal-noise/compare-columns' ),
	'steps-enumerated'    => array( 'Slug' => 'signal-noise/steps-enumerated' ),
);

foreach ( $expected_patterns as $basename => $expected ) {
	$path = __DIR__ . '/../patterns/' . $basename . '.php';
	echo "\nTest: patterns/{$basename}.php\n";
	ha_true( file_exists( $path ), "file exists" );
	if ( ! file_exists( $path ) ) { continue; }

	$contents = file_get_contents( $path );

	// Parse the docblock header fields (WP uses get_file_data; we mimic).
	$required_headers = array( 'Title', 'Slug', 'Categories', 'Description' );
	foreach ( $required_headers as $header ) {
		if ( preg_match( '/^[ \t\/*#@]*' . $header . ':(.*)$/mi', $contents, $m ) ) {
			ha_true( trim( $m[1] ) !== '', "header '{$header}' has a non-empty value" );
		} else {
			ha_true( false, "header '{$header}' present" );
		}
	}

	// Slug must match expected.
	if ( preg_match( '/^[ \t\/*#@]*Slug:(.*)$/mi', $contents, $m ) ) {
		ha_eq( $expected['Slug'], trim( $m[1] ), "Slug matches expected" );
	}

	// Categories must include signal-noise.
	if ( preg_match( '/^[ \t\/*#@]*Categories:(.*)$/mi', $contents, $m ) ) {
		ha_true(
			false !== strpos( $m[1], 'signal-noise' ),
			'Categories includes signal-noise'
		);
	}
}

// --- Test 3: parts/post-closing.html exists and parses -----------------
echo "\nTest: parts/post-closing.html\n";
$part_path = __DIR__ . '/../parts/post-closing.html';
ha_true( file_exists( $part_path ), 'template part file exists' );

if ( file_exists( $part_path ) ) {
	$part_contents = file_get_contents( $part_path );
	ha_true(
		false !== strpos( $part_contents, '<!-- wp:' ),
		'contains WordPress block markup (<!-- wp:)'
	);
	ha_true(
		false !== strpos( $part_contents, 'wp:post-terms' ) || false !== strpos( $part_contents, 'wp:post-date' ),
		'contains a dynamic post-data block (post-terms or post-date)'
	);
}

// --- Test 4: templates/single.html includes post-closing ---------------
echo "\nTest: templates/single.html includes post-closing template part\n";
$single_path = __DIR__ . '/../templates/single.html';
ha_true( file_exists( $single_path ), 'single.html exists' );
if ( file_exists( $single_path ) ) {
	$single = file_get_contents( $single_path );
	ha_true(
		preg_match( '/wp:template-part\s*\{[^}]*"slug"\s*:\s*"post-closing"/i', $single ) === 1,
		'single.html references wp:template-part with slug "post-closing"'
	);
}

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
