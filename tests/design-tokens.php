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
//
// v12.0.4: the stub now HONOURS THE PATH ARGUMENT, as the real function does.
// It used to ignore it and return the whole tree, which is fine for the caller
// that reads $settings['color']['palette'] itself but silently wrong for one
// that asks for array('color','palette') — it got a tree where it expected a
// list. A stub that is more forgiving than the real function does not test the
// caller, it tests the stub.
$GLOBALS['__test_global_settings'] = array();
if ( ! function_exists( 'wp_get_global_settings' ) ) {
	function wp_get_global_settings( $path = array() ) {
		$node = $GLOBALS['__test_global_settings'];
		foreach ( (array) $path as $key ) {
			if ( ! is_array( $node ) || ! array_key_exists( $key, $node ) ) {
				return array();
			}
			$node = $node[ $key ];
		}
		return $node;
	}
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
// v12.0.0: the ability enumerates every palette the site can present via
// inc/palettes.php. Loaded here with a real theme root so the fixture reads the
// SHIPPED theme.json, styles/*.json and critical.css — the same files the
// runtime reads. Stubbing the palettes instead would make this suite agree with
// a fixture rather than with the theme, which is the failure mode that let a
// flat one-palette contract survive three shipped palettes.
if ( ! function_exists( 'get_theme_file_path' ) ) {
	function get_theme_file_path( $p = '' ) { return dirname( __DIR__ ) . '/' . ltrim( $p, '/' ); }
}
require_once __DIR__ . '/../inc/palettes.php';

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

// v12.0.0: `colors` is scheme-keyed. The origin-flattening behaviour these
// assertions were written for is unchanged — it now applies within `light`.
// v12.0.0: `colors` is a struct — served / resolved / palettes. `resolved` is
// what WordPress hands back for THIS request, so the origin-flattening these
// assertions were written for now applies there.
dtk_eq( array( 'served', 'resolved', 'palettes' ), array_keys( $tokens['colors'] ), 'colors: served + resolved + palettes' );
dtk_eq( 2, count( $tokens['colors']['resolved'] ), 'colors.resolved: 2 flattened entries (not the 2 origin BUCKETS misread as entries)' );
dtk_eq( '#ffffff', $tokens['colors']['resolved']['void'], 'colors.resolved: void unique to default, passes through' );
dtk_eq( '#e00404', $tokens['colors']['resolved']['bone'], 'colors.resolved: bone — theme origin wins over default (same slug)' );

// THE FLAT MAP IS GONE, not aliased. An alias would let every existing consumer
// keep reading one palette and never discover the others, which is the entire
// failure this release exists to close.
dtk_eq( false, isset( $tokens['colors']['void'] ), 'the old FLAT colors map is removed, not kept as an alias' );

// KEYED BY IDENTITY, NOT BY SCHEME. `{light,dark}` was the shape this release
// nearly shipped, and it is a trap: high-contrast is itself a LIGHT palette, so
// it has nowhere to live, and adding it later would be a SECOND break to the
// same field. Identity keys make a fourth palette additive. Asserted because
// the whole point is that this cannot quietly regress to two.
$ids = array_keys( (array) $tokens['colors']['palettes'] );
dtk_eq( true, in_array( 'root', $ids, true ), 'palettes: root is enumerated' );
dtk_eq( true, in_array( 'high-contrast', $ids, true ), 'palettes: the SHIPPED VARIATION is enumerated — the ability was blind to it until v12.0.0' );
dtk_eq( true, in_array( 'dark', $ids, true ), 'palettes: dark is enumerated' );
dtk_eq( false, in_array( 'light', $ids, true ), 'palettes: NOT keyed by scheme — "light" is not a palette identity' );
dtk_eq( 'light', $tokens['colors']['palettes']['high-contrast']['scheme'], 'high-contrast carries scheme=light (a variation is not an alternative to dark)' );
dtk_eq( 'dark', $tokens['colors']['palettes']['dark']['scheme'], 'dark carries scheme=dark' );
// Every entry is a COMPLETE palette, so no consumer needs to know the
// variation-overrides-root fallback rule to read one correctly.
foreach ( $tokens['colors']['palettes'] as $pid => $pmeta ) {
	dtk_eq( 7, count( $pmeta['colors'] ), "palette '$pid' is complete (all 7 slugs), not a partial override" );
}

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
dtk_eq( '#e00404', $flat_result['colors']['resolved']['blood'], 'flat fixture: colors flattened as before, under resolved' );
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

// ── THE PAYLOAD MUST SATISFY ITS OWN DECLARED SCHEMA (v12.0.1) ─────────────
// v12.0.0 reshaped `colors` and left `output_schema` describing the old shape.
// The Abilities API validates execute results against that schema, so the
// ability failed on the LIVE SITE the moment the release installed — while
// 2,349 assertions stayed green, because every test in this file calls
// sn_theme_ability_design_tokens() directly and never goes through
// registration. The payload was asserted. The payload against its own
// DECLARATION was not, and that is the gap that shipped a dark flagship.
//
// sn-site-facts degrades one failing fact to {error:"unavailable"} and returns
// its siblings normally, by design — so nothing went red. The only way to see
// it was to ask for that one fact.
//
// This walks the registered schema rather than restating it, so it keeps
// working through any future reshape: change one side and it fails.
echo "\nGroup: payload conforms to the registered output_schema\n";

function dtk_schema_check( $value, $schema, $path, &$errors ) {
	$type = $schema['type'] ?? null;
	if ( 'object' === $type ) {
		if ( ! is_array( $value ) ) {
			$errors[] = "$path: expected object, got " . gettype( $value );
			return;
		}
		foreach ( (array) ( $schema['required'] ?? array() ) as $req ) {
			if ( ! array_key_exists( $req, $value ) ) {
				$errors[] = "$path.$req: required but absent";
			}
		}
		foreach ( (array) ( $schema['properties'] ?? array() ) as $k => $sub ) {
			if ( array_key_exists( $k, $value ) ) {
				dtk_schema_check( $value[ $k ], $sub, "$path.$k", $errors );
			}
		}
		$extra = $schema['additionalProperties'] ?? null;
		if ( is_array( $extra ) ) {
			$declared = array_keys( (array) ( $schema['properties'] ?? array() ) );
			foreach ( $value as $k => $v ) {
				if ( ! in_array( $k, $declared, true ) ) {
					dtk_schema_check( $v, $extra, "$path.$k", $errors );
				}
			}
		}
		return;
	}
	if ( 'array' === $type ) {
		if ( ! is_array( $value ) ) {
			$errors[] = "$path: expected array, got " . gettype( $value );
			return;
		}
		foreach ( $value as $i => $item ) {
			if ( isset( $schema['items'] ) ) {
				dtk_schema_check( $item, $schema['items'], "$path\[$i]", $errors );
			}
		}
		return;
	}
	if ( 'string' === $type ) {
		if ( ! is_string( $value ) ) {
			$errors[] = "$path: expected string, got " . gettype( $value );
			return;
		}
		if ( isset( $schema['enum'] ) && ! in_array( $value, (array) $schema['enum'], true ) ) {
			$errors[] = "$path: '$value' not in enum(" . implode( '|', $schema['enum'] ) . ')';
		}
		if ( 'color-hex' === ( $schema['format'] ?? '' ) && preg_match( '/^#[0-9a-fA-F]{3,8}$/', $value ) !== 1 ) {
			$errors[] = "$path: '$value' is not a hex colour";
		}
	}
}

// Capture the real registration by invoking the real registrar. The schema is
// READ from it, never restated here — a second copy would keep agreeing with
// itself while the shipped one drifted, which is the failure this whole group
// exists to close.
if ( ! function_exists( 'wp_register_ability' ) ) {
	function wp_register_ability( $slug, $args ) {
		$GLOBALS['__registered_abilities'][ $slug ] = $args;
		return true;
	}
}
if ( ! function_exists( 'wp_register_ability_category' ) ) {
	function wp_register_ability_category( $slug, $args ) { return true; }
}
if ( ! function_exists( 'sn_theme_perm_read' ) ) {
	function sn_theme_perm_read() { return true; }
}
$GLOBALS['__registered_abilities'] = array();
if ( function_exists( 'sn_theme_register_diagnostics_abilities' ) ) {
	sn_theme_register_diagnostics_abilities();
}

$reg = $GLOBALS['__registered_abilities']['signal-and-noise/get-design-tokens'] ?? null;
dtk_eq( true, is_array( $reg ), 'the ability registration was captured' );
$schema = $reg['output_schema'] ?? null;
dtk_eq( true, is_array( $schema ), 'it declares an output_schema' );

$live   = sn_theme_ability_design_tokens();
$errors = array();
dtk_eq( false, is_wp_error( $live ), 'the ability returns a payload, not a WP_Error' );
if ( is_array( $live ) && is_array( $schema ) ) {
	dtk_schema_check( $live, $schema, 'tokens', $errors );
}
dtk_eq( array(), $errors, 'THE PAYLOAD VALIDATES AGAINST ITS OWN DECLARED SCHEMA' . ( $errors ? ': ' . implode( '; ', $errors ) : '' ) );

// Negative control: the check must actually be able to fail, or it is decoration.
$broken = $live;
$broken['colors']['resolved'] = 'not-an-object';
$neg = array();
dtk_schema_check( $broken, $schema, 'tokens', $neg );
dtk_eq( false, empty( $neg ), 'negative control: a wrong-typed payload IS rejected' );

// ── `served` MUST SURVIVE WORDPRESS'S OWN SLUGS (v12.0.4) ──────────────────
// v12.0.3 shipped `served` as a two-way array_diff_assoc, which required the
// resolved palette and a shipped one to be IDENTICAL. On the live site that can
// never hold: wp_get_global_settings() returns the theme palette PLUS
// WordPress's twelve core defaults. So a site running High Contrast reported
// `served: "custom"` — the field that answers "what is actually live" claiming
// the owner had hand-edited colours when they had not.
//
// It passed because the fixture here contained ONLY theme slugs. The fixture was
// tidier than reality, and tidiness was the bug. This one carries the core slugs
// exactly as WordPress hands them over.
echo "\nGroup: served survives WordPress's core palette slugs\n";

$hc = array();
foreach ( ( json_decode( (string) file_get_contents( dirname( __DIR__ ) . '/styles/high-contrast.json' ), true )['settings']['color']['palette'] ?? array() ) as $e ) {
	$hc[ $e['slug'] ] = strtolower( $e['color'] );
}
// WordPress core defaults, present on every real site and declared by no
// shipped palette.
$core = array(
	'black' => '#000000', 'cyan-bluish-gray' => '#abb8c3', 'white' => '#ffffff',
	'pale-pink' => '#f78da7', 'vivid-red' => '#cf2e2e', 'luminous-vivid-orange' => '#ff6900',
);
$GLOBALS['__test_global_settings'] = array(
	'color' => array( 'palette' => array( 'default' => array(), 'theme' => array() ) ),
);
foreach ( $core as $slug => $hex ) {
	$GLOBALS['__test_global_settings']['color']['palette']['default'][] = array( 'slug' => $slug, 'color' => $hex );
}
foreach ( $hc as $slug => $hex ) {
	$GLOBALS['__test_global_settings']['color']['palette']['theme'][] = array( 'slug' => $slug, 'color' => $hex );
}

dtk_eq( 'high-contrast', sn_theme_served_palette_id(),
	'a High Contrast site reports served=high-contrast even though WordPress adds its own slugs' );

// And it must still be able to say `custom` when a theme slug genuinely differs.
$GLOBALS['__test_global_settings']['color']['palette']['theme'][0]['color'] = '#123456';
dtk_eq( 'custom', sn_theme_served_palette_id(),
	'negative control: one altered THEME slug still reports custom' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
