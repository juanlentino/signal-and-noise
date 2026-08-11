<?php
/**
 * Standalone fixture tests for the theme's Style Variation JSON files
 * (styles/high-contrast.json), introduced in v9.10.0.
 *
 * styles/monolith.json was REMOVED 2026-08-11 as vestigial — it shipped in
 * v9.10.0, was never activated, and remapped blood -> #000000, which turned
 * every bone-text-on-blood-background component (the active command-palette
 * row, kbd chips, buttons) into black-on-black at 1.00:1. Surfaced by
 * contrast-baseline.php Test 7, which evaluates every shipped palette rather
 * than only root. Do not reintroduce a monochrome variation without checking
 * the components that paint themselves with `blood`.
 *
 * WordPress contract (verified vs the Theme Handbook "Create theme style
 * variations" page + Gutenberg's WP_Theme_JSON_Resolver merge logic, trunk
 * 2026-06-07):
 *   - Variation files live in the theme's /styles folder, one JSON per file.
 *   - Each is a partial theme.json: $schema + version (MUST match the root
 *     theme.json, here v3 / wp/7.0 schema) + an optional-but-recommended
 *     title + settings/styles.
 *   - Variations merge into the base theme.json via array_replace_recursive.
 *     Because settings.color.palette is an INDEXED array, that deep merge
 *     replaces palette entries BY POSITION, not by slug. Therefore each
 *     variation must declare the FULL palette in the SAME ORDER as the root
 *     theme.json so every index lines up with its intended slug. These tests
 *     enforce that ordering invariant (the load-bearing correctness check).
 *
 * Run from theme root:  php tests/style-variations.php
 *
 * @since theme v9.10.0
 */

// SECURITY: CLI / WP-CLI only. A direct HTTP GET would leak internal
// structure; this is a test fixture, not a runtime module.
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) {
	http_response_code( 404 );
	exit;
}

// ─── Harness (theme convention) ────────────────────────────────────────
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

/**
 * Pull a palette as slug => color map from a decoded variation/theme.json.
 *
 * @param array $data Decoded JSON.
 * @return array<string,string>
 */
function sv_palette_map( $data ) {
	$out = array();
	$palette = $data['settings']['color']['palette'] ?? array();
	foreach ( $palette as $entry ) {
		if ( isset( $entry['slug'], $entry['color'] ) ) {
			$out[ $entry['slug'] ] = $entry['color'];
		}
	}
	return $out;
}

/**
 * Pull the ordered slug list from a decoded variation/theme.json palette.
 *
 * @param array $data Decoded JSON.
 * @return string[]
 */
function sv_palette_order( $data ) {
	$out = array();
	$palette = $data['settings']['color']['palette'] ?? array();
	foreach ( $palette as $entry ) {
		if ( isset( $entry['slug'] ) ) {
			$out[] = $entry['slug'];
		}
	}
	return $out;
}

// ─── Load the root theme.json for the ordering invariant ───────────────
$root_path = __DIR__ . '/../theme.json';
$root_json = json_decode( (string) file_get_contents( $root_path ), true );
$root_order = sv_palette_order( $root_json );

echo "\nTest: root theme.json palette is the v3 baseline\n";
ha_true( is_array( $root_json ), 'theme.json decodes' );
ha_eq( 3, $root_json['version'] ?? null, 'theme.json version is 3' );
ha_eq(
	array( 'void', 'asphalt', 'concrete', 'rust', 'bone', 'blood', 'signal' ),
	$root_order,
	'theme.json palette order is the documented 7-slug sequence'
);

// ─── Per-variation shared assertions ───────────────────────────────────
$variations = array(
	'high-contrast.json' => 'High Contrast',
);

$decoded = array();

foreach ( $variations as $file => $expected_title ) {
	$path = __DIR__ . '/../styles/' . $file;
	echo "\nTest: styles/{$file}\n";

	ha_true( file_exists( $path ), 'file exists' );
	if ( ! file_exists( $path ) ) { continue; }

	$raw = (string) file_get_contents( $path );
	$data = json_decode( $raw, true );
	ha_true( null !== $data && JSON_ERROR_NONE === json_last_error(), 'valid JSON (' . json_last_error_msg() . ')' );
	if ( null === $data ) { continue; }
	$decoded[ $file ] = $data;

	// Required contract keys.
	ha_eq(
		'https://schemas.wp.org/wp/7.0/theme.json',
		$data['$schema'] ?? null,
		'$schema is the wp/7.0 theme.json schema'
	);
	ha_eq( 3, $data['version'] ?? null, 'version is 3 (matches root theme.json)' );
	ha_eq( $expected_title, $data['title'] ?? null, "title is \"{$expected_title}\"" );

	// Palette present + full + correctly ordered (array_replace_recursive
	// merges by index, so order MUST mirror the root theme.json).
	$pal = $data['settings']['color']['palette'] ?? null;
	ha_true( is_array( $pal ) && count( $pal ) > 0, 'settings.color.palette is a non-empty array' );
	ha_eq(
		$root_order,
		sv_palette_order( $data ),
		'palette order mirrors root theme.json (index-merge safe)'
	);
}

// ─── Removed variations stay removed ───────────────────────────────────
// A vestigial variation is not inert: WordPress offers every file in
// /styles in the FSE switcher, so an unmaintained one is a palette any
// visitor can be shown. monolith.json remapped blood -> #000000 while CSS
// components paint bone text on blood backgrounds — black on black. Guard
// the deletion so it cannot quietly return.
echo "\nTest: retired variations are absent from /styles\n";
foreach ( array( 'monolith.json' ) as $retired ) {
	ha_true(
		! file_exists( __DIR__ . '/../styles/' . $retired ),
		"styles/{$retired} stays deleted (retired 2026-08-11 — see docblock)"
	);
}

// ─── High Contrast: keep red, push greys toward black ──────────────────
echo "\nTest: High Contrast keeps blood red + darkens greys vs root\n";
if ( isset( $decoded['high-contrast.json'] ) ) {
	$hc   = sv_palette_map( $decoded['high-contrast.json'] );
	$base = sv_palette_map( $root_json );

	// Blood stays the brand red (a chromatic red, not greyscaled).
	$blood_hex = ltrim( strtolower( (string) ( $hc['blood'] ?? '' ) ), '#' );
	$br = hexdec( substr( $blood_hex, 0, 2 ) );
	$bg = hexdec( substr( $blood_hex, 2, 2 ) );
	$bb = hexdec( substr( $blood_hex, 4, 2 ) );
	ha_true( $br > 150 && $bg < 100 && $bb < 100, 'High Contrast blood is still a strong red' );

	// Greys pushed darker than the root (lower luminance proxy = lower R).
	$lum = function ( $color ) {
		$hex = ltrim( strtolower( (string) $color ), '#' );
		return hexdec( substr( $hex, 0, 2 ) ); // R channel; greys are achromatic so R==G==B.
	};
	ha_true(
		$lum( $hc['asphalt'] ?? '#ffffff' ) < $lum( $base['asphalt'] ?? '#000000' ),
		'High Contrast asphalt is darker than root asphalt'
	);
	ha_true(
		$lum( $hc['concrete'] ?? '#ffffff' ) < $lum( $base['concrete'] ?? '#000000' ),
		'High Contrast concrete is darker than root concrete'
	);
	ha_true(
		$lum( $hc['rust'] ?? '#ffffff' ) <= $lum( $base['rust'] ?? '#000000' ),
		'High Contrast rust is no lighter than root rust'
	);
}

// ─── critical.css var()-izes selection / focus / skip-link / scrollbar ──
echo "\nTest: critical.css uses palette vars (not bare hex) for variation-propagating chrome\n";
$css_path = __DIR__ . '/../assets/css/critical.css';
$css = (string) file_get_contents( $css_path );

// Isolate the chrome rules we care about so unrelated hex elsewhere in the
// file (e.g. the SVG noise data-URI) doesn't mask a regression.
function sv_block( $css, $start_needle, $end_needle ) {
	$s = strpos( $css, $start_needle );
	if ( false === $s ) { return ''; }
	$e = strpos( $css, $end_needle, $s );
	return false === $e ? substr( $css, $s ) : substr( $css, $s, $e - $s );
}

$scrollbar = sv_block( $css, 'scrollbar-color', ';' );
ha_true(
	false !== strpos( $scrollbar, 'var(--wp--preset--color--' ),
	'html scrollbar-color uses palette vars'
);
ha_true(
	false === strpos( $scrollbar, '#' ),
	'html scrollbar-color has no bare hex'
);

$selection = sv_block( $css, '::selection', '}' );
ha_true(
	false !== strpos( $selection, 'var(--wp--preset--color--' ),
	'::selection uses palette vars'
);
ha_true(
	false === strpos( $selection, '#' ),
	'::selection has no bare hex'
);

$skip = sv_block( $css, '.sn-skip-link {', '}' );
ha_true(
	false !== strpos( $skip, 'var(--wp--preset--color--' ),
	'.sn-skip-link uses palette vars'
);
ha_true(
	false === strpos( $skip, '#' ),
	'.sn-skip-link has no bare hex'
);

$skip_focus = sv_block( $css, '.sn-skip-link:focus', '}' );
ha_true(
	false !== strpos( $skip_focus, 'var(--wp--preset--color--' ),
	'.sn-skip-link:focus uses palette vars'
);
ha_true(
	false === strpos( $skip_focus, '#' ),
	'.sn-skip-link:focus has no bare hex'
);

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
