<?php
/**
 * Standalone PHP test: every preset family theme.json declares must survive
 * core's default-slug filter.
 *
 * WHY THIS EXISTS. Since WordPress 6.6, WP_Theme_JSON::merge() drops a theme
 * preset from the THEME origin whenever its slug collides with one of core's
 * defaults, unless theme.json sets that family's `default*` flag to false
 * ("Filter out default slugs from theme presets when defaults should not be
 * overridden" -> filter_slugs()). The theme keeps its declaration; the site
 * serves core's value; nothing warns.
 *
 * That is how this theme served core's geometric spacing scale (0.44rem ..
 * 5.06rem) instead of its own (1rem .. 12rem) for its entire life, and core's
 * 13/20/36/42px for small/medium/large/x-large instead of the sizes declared
 * beside them. color.defaultPalette was set to false in v3.6.0; the spacing
 * and typography flags never were. `spacingScale: { steps: 0 }` does NOT
 * help -- it stops the theme origin generating a scale, and the collision is
 * with the DEFAULT origin's scale. Found 2026-09-05 by reading a computed
 * custom property on the live site and not believing it. (#284)
 *
 * WHAT IT CHECKS. Core's default slugs are DERIVED from a vendored copy of
 * core's own wp-includes/theme.json (tests/fixtures/core-theme-json-7.1.json)
 * rather than remembered here, so a new core default is a fixture refresh,
 * not a code change. The spacing slugs are derived from the fixture's
 * spacingScale the way core derives them (steps around a midpoint of 50, in
 * tens). For every family: if theme.json declares a slug core also declares,
 * the family's flag must be present AND false.
 *
 * Refresh the fixture from a WordPress download:
 *   cp <wp>/wp-includes/theme.json tests/fixtures/core-theme-json-<ver>.json
 * and point CORE_FIXTURE at it.
 *
 * NEGATIVE CONTROL. The checker is run against an in-memory copy of theme.json
 * with each flag removed and must report exactly the families that collide;
 * a checker that cannot go red proves nothing.
 *
 * @since 12.18.9
 */

if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) {
	http_response_code( 404 );
	exit;
}

const CORE_FIXTURE = __DIR__ . '/fixtures/core-theme-json-7.1.json';

$pass = 0;
$fail = 0;

function dp_ok( $cond, $label ) {
	global $pass, $fail;
	if ( $cond ) {
		++$pass;
		echo "  PASS: $label\n";
	} else {
		++$fail;
		echo "  FAIL: $label\n";
	}
}

/**
 * The families core guards with a prevent_override flag, as
 * [ settings path of the presets, settings path of the flag ].
 * Mirrors WP_Theme_JSON::PRESETS_METADATA (7.1).
 */
function dp_families() {
	return array(
		'color.palette'          => array( array( 'color', 'palette' ), array( 'color', 'defaultPalette' ) ),
		'color.gradients'        => array( array( 'color', 'gradients' ), array( 'color', 'defaultGradients' ) ),
		'color.duotone'          => array( array( 'color', 'duotone' ), array( 'color', 'defaultDuotone' ) ),
		'typography.fontSizes'   => array( array( 'typography', 'fontSizes' ), array( 'typography', 'defaultFontSizes' ) ),
		'spacing.spacingSizes'   => array( array( 'spacing', 'spacingSizes' ), array( 'spacing', 'defaultSpacingSizes' ) ),
		'dimensions.aspectRatios' => array( array( 'dimensions', 'aspectRatios' ), array( 'dimensions', 'defaultAspectRatios' ) ),
		'shadow.presets'         => array( array( 'shadow', 'presets' ), array( 'shadow', 'defaultPresets' ) ),
	);
}

function dp_get( array $tree, array $path, $default = null ) {
	foreach ( $path as $k ) {
		if ( ! is_array( $tree ) || ! array_key_exists( $k, $tree ) ) {
			return $default;
		}
		$tree = $tree[ $k ];
	}
	return $tree;
}

function dp_slugs( $list ) {
	$out = array();
	foreach ( (array) $list as $entry ) {
		if ( is_array( $entry ) && isset( $entry['slug'] ) ) {
			$out[] = (string) $entry['slug'];
		}
	}
	return $out;
}

/**
 * Core derives its spacing slugs from spacingScale: `steps` sizes centred on
 * slug 50, going down in tens below the midpoint and up in tens above it
 * (WP_Theme_JSON::compute_spacing_sizes). Only the SLUGS matter here.
 */
function dp_core_spacing_slugs( array $core ) {
	$declared = dp_slugs( dp_get( $core, array( 'settings', 'spacing', 'spacingSizes' ), array() ) );
	$scale    = dp_get( $core, array( 'settings', 'spacing', 'spacingScale' ), array() );
	$steps    = isset( $scale['steps'] ) && is_numeric( $scale['steps'] ) ? (int) $scale['steps'] : 0;
	if ( $steps < 1 ) {
		return $declared;
	}
	$mid   = (int) round( $steps / 2, 0 );
	$slugs = array();
	for ( $i = $mid - 1, $slug = 40; $steps > 1 && $i > 0 && $slug > 0; $i--, $slug -= 10 ) {
		$slugs[] = (string) $slug;
	}
	$slugs[] = '50';
	for ( $i = 0, $slug = 60; $i < $steps - $mid; $i++, $slug += 10 ) {
		$slugs[] = (string) $slug;
	}
	$slugs = array_values( array_unique( array_merge( $declared, $slugs ) ) );
	sort( $slugs, SORT_NATURAL );
	return $slugs;
}

function dp_core_slugs( array $core, $family ) {
	list( $presets_path ) = dp_families()[ $family ];
	if ( 'spacing.spacingSizes' === $family ) {
		return dp_core_spacing_slugs( $core );
	}
	return dp_slugs( dp_get( $core, array_merge( array( 'settings' ), $presets_path ), array() ) );
}

/**
 * @return array family => [ colliding slugs ] for every family that declares
 *               a colliding slug WITHOUT the flag set to false.
 */
function dp_violations( array $theme, array $core ) {
	$out = array();
	foreach ( dp_families() as $family => list( $presets_path, $flag_path ) ) {
		$mine      = dp_slugs( dp_get( $theme, array_merge( array( 'settings' ), $presets_path ), array() ) );
		$colliding = array_values( array_intersect( $mine, dp_core_slugs( $core, $family ) ) );
		if ( array() === $colliding ) {
			continue;
		}
		$flag = dp_get( $theme, array_merge( array( 'settings' ), $flag_path ), 'absent' );
		if ( false !== $flag ) {
			$out[ $family ] = $colliding;
		}
	}
	return $out;
}

echo "theme-json-default-presets: theme presets survive core's default-slug filter\n";

$core  = json_decode( (string) file_get_contents( CORE_FIXTURE ), true );
$theme = json_decode( (string) file_get_contents( dirname( __DIR__ ) . '/theme.json' ), true );
dp_ok( is_array( $core ) && isset( $core['settings'] ), 'core fixture parses and has settings' );
dp_ok( is_array( $theme ) && isset( $theme['settings'] ), 'theme.json parses and has settings' );

echo "\nGroup: the fixture really carries core's defaults (a fixture that lost them would make every check vacuous)\n";
$core_spacing = dp_core_spacing_slugs( $core );
dp_ok( array( '20', '30', '40', '50', '60', '70', '80' ) === array_values( array_filter( $core_spacing, 'is_numeric' ) ), 'core spacing slugs derived from spacingScale: 20..80 (got ' . implode( ',', $core_spacing ) . ')' );
$core_fs = dp_core_slugs( $core, 'typography.fontSizes' );
dp_ok( array() !== array_intersect( array( 'small', 'medium', 'large', 'x-large' ), $core_fs ), 'core font-size slugs include small/medium/large/x-large (got ' . implode( ',', $core_fs ) . ')' );
dp_ok( count( dp_core_slugs( $core, 'color.palette' ) ) >= 10, 'core palette has its dozen default slugs' );

echo "\nGroup: theme.json today\n";
$violations = dp_violations( $theme, $core );
dp_ok( array() === $violations, 'no family declares a core slug without its default* flag set to false' . ( $violations ? ' -- violations: ' . json_encode( $violations ) : '' ) );

foreach ( dp_families() as $family => list( $presets_path, $flag_path ) ) {
	$mine      = dp_slugs( dp_get( $theme, array_merge( array( 'settings' ), $presets_path ), array() ) );
	$colliding = array_values( array_intersect( $mine, dp_core_slugs( $core, $family ) ) );
	if ( array() === $colliding ) {
		continue;
	}
	$flag = dp_get( $theme, array_merge( array( 'settings' ), $flag_path ), 'absent' );
	dp_ok( false === $flag, "$family collides on " . implode( ',', $colliding ) . ' -> ' . implode( '.', $flag_path ) . ' is false (got ' . var_export( $flag, true ) . ')' );
}

echo "\nGroup: the two families this was built from are still declared (a theme that stopped declaring them would pass by absence)\n";
dp_ok( in_array( '60', dp_slugs( dp_get( $theme, array( 'settings', 'spacing', 'spacingSizes' ), array() ) ), true ), 'theme declares spacing slug 60' );
dp_ok( in_array( 'large', dp_slugs( dp_get( $theme, array( 'settings', 'typography', 'fontSizes' ), array() ) ), true ), 'theme declares font-size slug large' );

echo "\nGroup: negative control -- the checker can go red\n";
$mutant = $theme;
unset( $mutant['settings']['spacing']['defaultSpacingSizes'] );
$v = dp_violations( $mutant, $core );
dp_ok( isset( $v['spacing.spacingSizes'] ) && in_array( '60', $v['spacing.spacingSizes'], true ), 'removing spacing.defaultSpacingSizes is reported, naming slug 60' );
dp_ok( ! isset( $v['typography.fontSizes'] ), 'the typography family is NOT reported when only the spacing flag is removed' );
$mutant = $theme;
$mutant['settings']['typography']['defaultFontSizes'] = true;
$v = dp_violations( $mutant, $core );
dp_ok( isset( $v['typography.fontSizes'] ) && in_array( 'large', $v['typography.fontSizes'], true ), 'defaultFontSizes: true (not merely absent) is reported, naming slug large' );
$mutant = $theme;
unset( $mutant['settings']['color']['defaultPalette'] );
$v = dp_violations( $mutant, $core );
dp_ok( ! isset( $v['color.palette'] ), 'removing defaultPalette is NOT reported: the theme palette slugs (void, bone, ...) do not collide with core' );
$mutant = $theme;
$mutant['settings']['color']['palette'][] = array( 'slug' => 'black', 'color' => '#000', 'name' => 'Black' );
unset( $mutant['settings']['color']['defaultPalette'] );
$v = dp_violations( $mutant, $core );
dp_ok( isset( $v['color.palette'] ) && array( 'black' ) === $v['color.palette'], 'declaring a core palette slug without the flag IS reported' );

echo "\n$pass passed, $fail failed\n";
exit( $fail > 0 ? 1 : 0 );
