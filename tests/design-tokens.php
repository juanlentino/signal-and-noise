<?php
/**
 * Standalone fixture tests for the get-design-tokens origin-unwrap fix +
 * the get-design-system-summary hollow-fabrication guard (both in
 * inc/abilities-diagnostics.php).
 *
 * Root causes fixed here (see ~/.claude/session-data/mcp-six-fixes-spec.md,
 * lanes T1/T2):
 *
 *   - sn_theme_ability_design_tokens() iterated wp_get_global_settings()
 *     presets WITHOUT unwrapping WP core's per-ORIGIN buckets
 *     ('default'/'theme'/'custom'). Reading a bucket as if it were a
 *     single preset entry meant colors always came back empty and
 *     fontFamilies/fontSizes/spacingSizes reported a count equal to the
 *     number of origin keys present, never the number of real presets.
 *     Born broken — never returned a real token through any client.
 *   - sn_theme_ability_design_system_summary() never checked whether the
 *     tokens it was about to format were real: a hollow read produced a
 *     well-formed, plausible-looking EMPTY document instead of surfacing
 *     the read failure.
 *
 * This file loads inc/abilities-diagnostics.php DIRECTLY (not through the
 * inc/abilities-registration.php loader) — get-design-tokens and
 * get-design-system-summary have no cross-file dependencies, so the
 * minimal WP stub surface below is enough to exercise both execute
 * callbacks by calling them straight (no wp_get_ability() dispatch
 * needed, matching tests/abilities-registration.php's direct-call style).
 *
 * Run: php tests/design-tokens.php
 *
 * @since 10.42.1
 */

// SECURITY: Prevent web access. This file is a test fixture, not a runtime
// module. Allow only CLI / WP-CLI invocations.
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) {
	http_response_code( 404 );
	exit;
}

define( 'ABSPATH', '/' );

// ─── Minimal WP stubs ────────────────────────────────────────────────
if ( ! function_exists( 'add_action' ) ) { function add_action() {} }
if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $v ) { return json_encode( $v ); }
}

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		public $code;
		public $message;
		public $data;
		public function __construct( $c = '', $m = '', $d = array() ) {
			$this->code    = $c;
			$this->message = $m;
			$this->data    = $d;
		}
		public function get_error_code()    { return $this->code; }
		public function get_error_message() { return $this->message; }
		public function get_error_data()    { return $this->data; }
	}
}
if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $v ) { return $v instanceof WP_Error; }
}

// wp_get_global_settings() fixture — controllable per test block.
$GLOBALS['__test_global_settings'] = array();
if ( ! function_exists( 'wp_get_global_settings' ) ) {
	function wp_get_global_settings() { return $GLOBALS['__test_global_settings']; }
}

if ( ! class_exists( 'SN_Test_Theme' ) ) {
	class SN_Test_Theme {
		public function get( $key ) {
			$map = array( 'Name' => 'Signal & Noise', 'Version' => '10.42.1' );
			return isset( $map[ $key ] ) ? $map[ $key ] : '';
		}
	}
}
if ( ! function_exists( 'wp_get_theme' ) ) { function wp_get_theme() { return new SN_Test_Theme(); } }

// ─── Load the SUT ────────────────────────────────────────────────────
require_once __DIR__ . '/../inc/abilities-diagnostics.php';

// ─── Harness ─────────────────────────────────────────────────────────
$pass = 0; $fail = 0;
function dtk_eq( $expected, $actual, $msg ) {
	global $pass, $fail;
	if ( $expected === $actual ) { $pass++; echo "  PASS: $msg\n"; }
	else { $fail++; echo "  FAIL: $msg\n    Expected: " . var_export( $expected, true ) . "\n    Actual:   " . var_export( $actual, true ) . "\n"; }
}
function dtk_true( $cond, $msg ) {
	global $pass, $fail;
	if ( $cond ) { $pass++; echo "  PASS: $msg\n"; } else { $fail++; echo "  FAIL: $msg\n"; }
}

// ════════════════════════════════════════════════════════════════════
// Category: get-design-tokens — origin unwrapping (T2)
// ════════════════════════════════════════════════════════════════════

echo "\nCategory: get-design-tokens — origin unwrapping\n";

// Realistic shape of wp_get_global_settings(): presets keyed by origin, NOT
// a flat list. 'bone' appears in both default and theme to pin precedence
// (theme wins); 'medium' font-size appears in all three to pin the full
// default -> theme -> custom chain (custom wins).
$GLOBALS['__test_global_settings'] = array(
	'color'      => array(
		'palette' => array(
			'default' => array(
				array( 'slug' => 'void', 'color' => '#ffffff', 'name' => 'Void' ),
				array( 'slug' => 'bone', 'color' => '#111111', 'name' => 'Bone (core default)' ),
			),
			'theme'   => array(
				array( 'slug' => 'bone', 'color' => '#e00404', 'name' => 'Bone (theme override)' ),
			),
		),
	),
	'typography' => array(
		'fontFamilies' => array(
			'default' => array(
				array( 'slug' => 'system', 'name' => 'System', 'fontFamily' => 'sans-serif' ),
			),
			'theme'   => array(
				array( 'slug' => 'heading', 'name' => 'Heading', 'fontFamily' => 'Bebas Neue' ),
				array( 'slug' => 'body',    'name' => 'Body',    'fontFamily' => 'DM Mono' ),
			),
		),
		'fontSizes'    => array(
			// 4 unique slugs merged from only 3 origin buckets — deliberately
			// NOT equal to the bucket count, so a bug that returns the bucket
			// count instead of the real entry count can't coincidentally pass.
			'default' => array(
				array( 'slug' => 'small',  'size' => '0.8rem', 'name' => 'Small' ),
				array( 'slug' => 'medium', 'size' => '1rem',   'name' => 'Medium (core default)' ),
				array( 'slug' => 'xlarge', 'size' => '1.5rem', 'name' => 'XLarge' ),
			),
			'theme'   => array(
				array( 'slug' => 'medium', 'size' => '1.0625rem', 'name' => 'Medium (theme)' ),
				array( 'slug' => 'large',  'size' => '1.25rem',   'name' => 'Large' ),
			),
			'custom'  => array(
				array( 'slug' => 'medium', 'size' => '1.125rem', 'name' => 'Medium (custom override)' ),
			),
		),
	),
	'spacing'    => array(
		// spacingScale is a single resolved SETTING VALUE (non-preset), never
		// origin-bucketed the way presets are — verify it passes through as-is.
		'spacingScale' => array( 'operator' => '*', 'increment' => 1.125, 'steps' => 7, 'mediumStep' => 16, 'unit' => 'rem' ),
		'spacingSizes' => array(
			// 3 unique slugs from 2 origin buckets — again deliberately
			// mismatched counts to rule out a bucket-count coincidence.
			'default' => array( array( 'slug' => '40', 'size' => '1rem',   'name' => 'Small' ) ),
			'theme'   => array(
				array( 'slug' => '50', 'size' => '1.5rem', 'name' => 'Medium' ),
				array( 'slug' => '60', 'size' => '2rem',   'name' => 'Large' ),
			),
		),
	),
);

$tokens = sn_theme_ability_design_tokens();
dtk_true( is_array( $tokens ), 'origin-keyed fixture: returns array, not WP_Error' );

dtk_eq( 2, count( $tokens['colors'] ), 'colors: 2 flattened entries (not the 2 origin BUCKETS misread as entries)' );
dtk_eq( '#ffffff', $tokens['colors']['void'], 'colors: void unique to default, passes through' );
dtk_eq( '#e00404', $tokens['colors']['bone'], 'colors: bone — theme origin wins over default (same slug)' );

dtk_eq( 3, count( $tokens['typography']['fontFamilies'] ), 'fontFamilies: 3 real entries merged across default+theme (not 2, the bucket count)' );
$ff_slugs = array_column( $tokens['typography']['fontFamilies'], 'slug' );
sort( $ff_slugs );
dtk_eq( array( 'body', 'heading', 'system' ), $ff_slugs, 'fontFamilies: every real slug present after merge' );

dtk_eq( 4, count( $tokens['typography']['fontSizes'] ), 'fontSizes: 4 unique slugs merged from 3 origin buckets (not 3, the bucket count)' );
$size_by_slug = array();
foreach ( $tokens['typography']['fontSizes'] as $fs ) { $size_by_slug[ $fs['slug'] ] = $fs['size']; }
dtk_eq( '0.8rem',   $size_by_slug['small'],  'fontSizes: small unique to default' );
dtk_eq( '1.125rem', $size_by_slug['medium'], 'fontSizes: medium — custom wins over theme wins over default (3-way precedence)' );
dtk_eq( '1.5rem',   $size_by_slug['xlarge'], 'fontSizes: xlarge unique to default' );
dtk_eq( '1.25rem',  $size_by_slug['large'],  'fontSizes: large unique to theme' );

dtk_eq( 3, count( $tokens['spacing']['spacingSizes'] ), 'spacingSizes: 3 unique slugs merged from 2 origin buckets (not 2, the bucket count)' );

dtk_eq(
	array( 'operator' => '*', 'increment' => 1.125, 'steps' => 7, 'mediumStep' => 16, 'unit' => 'rem' ),
	$tokens['spacing']['spacingScale'],
	'spacingScale: passes through unchanged (single resolved value, not a preset list — no origin unwrap needed)'
);

// ════════════════════════════════════════════════════════════════════
// Category: get-design-tokens — hollow read dies at the source (T2)
// ════════════════════════════════════════════════════════════════════

echo "\nCategory: get-design-tokens — hollow read dies at the source\n";

// Origin buckets present but every one is empty — the class of bug this
// guards against even once the unwrap itself is correct (a genuinely
// tokenless theme.json, or a future regression of the unwrap).
$GLOBALS['__test_global_settings'] = array(
	'color'      => array( 'palette' => array( 'default' => array(), 'theme' => array() ) ),
	'typography' => array(
		'fontFamilies' => array( 'default' => array(), 'theme' => array() ),
		'fontSizes'    => array( 'default' => array(), 'theme' => array() ),
	),
	'spacing'    => array(
		'spacingScale' => array(),
		'spacingSizes' => array( 'default' => array(), 'theme' => array() ),
	),
);
$hollow = sn_theme_ability_design_tokens();
dtk_true( is_wp_error( $hollow ), 'all-empty origin buckets: WP_Error, not a hollow-but-successful array' );
dtk_eq( 'design_tokens_empty', is_wp_error( $hollow ) ? $hollow->get_error_code() : '(not-error)', 'hollow read: error code is design_tokens_empty' );

// ════════════════════════════════════════════════════════════════════
// Category: get-design-tokens — already-flat fixture stays supported
// (backward-compat regression pin: tests/abilities-registration.php and
// tests/abilities-integration.php seed wp_get_global_settings() with a
// plain flat list, not origin-keyed — the flatten helper must keep
// treating that shape as pass-through so those suites don't regress.)
// ════════════════════════════════════════════════════════════════════

echo "\nCategory: get-design-tokens — already-flat fixture (backward compat)\n";

$GLOBALS['__test_global_settings'] = array(
	'color'      => array(
		'palette' => array(
			array( 'slug' => 'void',  'color' => '#ffffff', 'name' => 'Void' ),
			array( 'slug' => 'blood', 'color' => '#e00404', 'name' => 'Blood' ),
		),
	),
	'typography' => array(
		'fontFamilies' => array( array( 'slug' => 'heading', 'name' => 'Heading', 'fontFamily' => 'Bebas Neue' ) ),
		'fontSizes'    => array( array( 'slug' => 'small', 'size' => '0.8rem', 'name' => 'Small' ) ),
	),
	'spacing'    => array(
		'spacingScale' => array( 'steps' => 7 ),
		'spacingSizes' => array( array( 'slug' => '40', 'size' => '1rem', 'name' => 'Small' ) ),
	),
);
$flat_result = sn_theme_ability_design_tokens();
dtk_true( is_array( $flat_result ), 'flat (non-origin-keyed) fixture: still returns array, not WP_Error' );
dtk_eq( '#e00404', $flat_result['colors']['blood'], 'flat fixture: colors flattened as before' );
dtk_eq( 1, count( $flat_result['typography']['fontFamilies'] ), 'flat fixture: fontFamilies passthrough count unchanged' );

// ════════════════════════════════════════════════════════════════════
// Category: get-design-system-summary — refuses to fabricate (T1)
// ════════════════════════════════════════════════════════════════════

echo "\nCategory: get-design-system-summary — refuses to fabricate a hollow document\n";

// Completely empty settings — hollow regardless of the origin-unwrap fix
// (there is nothing to unwrap), so this pins the summary's own guard.
$GLOBALS['__test_global_settings'] = array();

foreach ( array( 'markdown', 'compact-text', 'json' ) as $format ) {
	$res = sn_theme_ability_design_system_summary( array( 'format' => $format ) );
	dtk_true( is_wp_error( $res ), "format=$format: hollow tokens -> WP_Error, never a plausible empty document" );
	dtk_eq( 'design_tokens_empty', is_wp_error( $res ) ? $res->get_error_code() : '(not-error)', "format=$format: error code is design_tokens_empty" );
}

// ════════════════════════════════════════════════════════════════════
// Category: get-design-system-summary — happy path with real tokens
// (end-to-end confirmation that T1's guard doesn't false-positive once
// T2 correctly unwraps real origin-keyed data)
// ════════════════════════════════════════════════════════════════════

echo "\nCategory: get-design-system-summary — happy path (real origin-keyed tokens)\n";

$GLOBALS['__test_global_settings'] = array(
	'color'      => array( 'palette' => array( 'theme' => array( array( 'slug' => 'blood', 'color' => '#e00404', 'name' => 'Blood' ) ) ) ),
	'typography' => array(
		'fontFamilies' => array( 'theme' => array( array( 'slug' => 'heading', 'name' => 'Heading', 'fontFamily' => 'Bebas Neue' ) ) ),
		'fontSizes'    => array( 'theme' => array( array( 'slug' => 'small', 'size' => '0.8rem', 'name' => 'Small' ) ) ),
	),
	'spacing'    => array(
		'spacingScale' => array( 'steps' => 7 ),
		'spacingSizes' => array( 'theme' => array( array( 'slug' => '40', 'size' => '1rem', 'name' => 'Small' ) ) ),
	),
);
$summary = sn_theme_ability_design_system_summary( array( 'format' => 'markdown' ) );
dtk_true( is_array( $summary ), 'happy path: real origin-keyed tokens format without error' );
dtk_true( is_array( $summary ) && false !== strpos( $summary['summary'], 'blood' ), 'happy path: real color slug appears in the formatted summary' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
