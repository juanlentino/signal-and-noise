<?php
/**
 * Standalone PHP test computing WCAG 2.1 relative-luminance ratios from
 * theme.json's palette. Asserts every documented docs/ACCESSIBILITY.md
 * pairing meets its threshold + tight-margin (e.g., blood-on-asphalt
 * baseline 4.60 ± 0.20).
 *
 * Why a tolerance window: a deliberate brand evolution would update BOTH
 * theme.json AND the test in the same commit. The window forces a
 * conscious decision (not silent erosion).
 *
 * ── READ THIS BEFORE TRUSTING A GREEN RUN (2026-08-11) ──────────────
 * Tests 1-5 measure the ROOT palette in theme.json. That is NOT
 * necessarily what the site serves. Activating a style variation copies
 * that variation's palette into the `wp_global_styles` CPT, and it wins.
 *
 * It bit: the live site runs the "High Contrast" variation
 * (asphalt #e0e0e0, concrete #9e9e9e, rust #333333) while theme.json
 * reads #f5f5f5/#d9d9d9/#666666. Both are correct — they are different
 * styles. But this suite reported 20 passed / 0 failed with delta 0.00 on
 * EVERY drift assertion while the ACTIVE palette carried a real AA
 * failure: blood-on-asphalt is 4.60 at root (passes) and 3.80 under High
 * Contrast (fails). Darkening a background helps dark text and hurts the
 * red accent, so a variation SOLD as higher contrast lowered it for one
 * pairing. The suite was not wrong; it was measuring one of several
 * palettes and reporting as if it were the only one.
 *
 * Hence two additions, both asserting RELATIONSHIPS rather than literals:
 *   Test 6 — the served palette must be root or a SHIPPED variation
 *            (needs WP; cannot run in CI).
 *   Test 7 — no sub-AA text pairing may exist under ANY palette the theme
 *            can present (pure static analysis; DOES run in CI).
 *
 * Algorithm: WCAG 2.1 SC 1.4.3 (Contrast Minimum). Relative luminance L =
 * 0.2126*R + 0.7152*G + 0.0722*B where each channel is the sRGB-to-linear
 * transform of the 8-bit value / 255. Contrast ratio = (L1 + 0.05) / (L2 + 0.05).
 *
 * @since theme v9.5.0
 */

if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) {
	http_response_code( 404 );
	exit;
}

// ─── WCAG primitive ────────────────────────────────────────────────

/**
 * Compute WCAG 2.1 relative luminance for an sRGB hex color.
 *
 * @param string $hex Hex color, with or without leading '#'.
 * @return float Luminance in [0.0, 1.0].
 */
function snt_test_relative_luminance( $hex ) {
	$hex = ltrim( (string) $hex, '#' );
	if ( 3 === strlen( $hex ) ) {
		// Short form (#abc) → expand to long form (#aabbcc).
		$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
	}
	$r = hexdec( substr( $hex, 0, 2 ) ) / 255;
	$g = hexdec( substr( $hex, 2, 2 ) ) / 255;
	$b = hexdec( substr( $hex, 4, 2 ) ) / 255;

	$r = ( $r <= 0.03928 ) ? $r / 12.92 : pow( ( $r + 0.055 ) / 1.055, 2.4 );
	$g = ( $g <= 0.03928 ) ? $g / 12.92 : pow( ( $g + 0.055 ) / 1.055, 2.4 );
	$b = ( $b <= 0.03928 ) ? $b / 12.92 : pow( ( $b + 0.055 ) / 1.055, 2.4 );

	return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
}

/**
 * Compute WCAG 2.1 contrast ratio between two sRGB hex colors.
 *
 * @param string $hex_a
 * @param string $hex_b
 * @return float Ratio in [1.0, 21.0].
 */
function snt_test_contrast_ratio( $hex_a, $hex_b ) {
	$l1      = snt_test_relative_luminance( $hex_a );
	$l2      = snt_test_relative_luminance( $hex_b );
	$lighter = max( $l1, $l2 );
	$darker  = min( $l1, $l2 );
	return ( $lighter + 0.05 ) / ( $darker + 0.05 );
}

/**
 * Normalise a hex colour to lowercase 6-digit, no '#', so short and long
 * form never read as a difference.
 *
 * @param string $hex
 * @return string
 */
function snt_test_norm_hex( $hex ) {
	$hex = strtolower( ltrim( trim( (string) $hex ), '#' ) );
	if ( 3 === strlen( $hex ) ) {
		$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
	}
	return $hex;
}

/**
 * Every palette this theme can present: the root palette plus each shipped
 * style variation in styles/. Variations inherit any slug they do not
 * redefine, which is why each is merged over root rather than read alone —
 * a variation that overrode only `rust` would otherwise appear to have a
 * one-colour palette and silently drop out of Test 7's coverage.
 *
 * @return array<string, array<string,string>> label => (slug => normalised hex)
 */
function snt_test_all_palettes() {
	static $cache = null;
	if ( null !== $cache ) {
		return $cache;
	}

	// v12.0.0: DELEGATES to sn_theme_all_palettes() (inc/palettes.php) — the
	// same enumerator the get-design-tokens ability uses. This function used to
	// glob styles/*.json itself, which meant the suite and the runtime each had
	// their own idea of how many palettes exist. They agreed right up until
	// they did not: the ability shipped a flat one-palette contract while this
	// file was already scoring three.
	//
	// One reader, or the test eventually certifies something the site does not
	// do.
	if ( ! function_exists( 'sn_theme_all_palettes' ) ) {
		if ( ! defined( 'ABSPATH' ) ) {
			define( 'ABSPATH', 1 );
		}
		if ( ! function_exists( 'get_theme_file_path' ) ) {
			eval( 'function get_theme_file_path( $p = "" ) { return dirname( __DIR__ ) . "/" . ltrim( $p, "/" ); }' );
		}
		require_once __DIR__ . '/../inc/palettes.php';
	}

	$cache = array();
	foreach ( sn_theme_all_palettes() as $id => $meta ) {
		$label = 'root' === $id ? 'root (theme.json)' : $id . ' (' . $meta['source'] . ')';
		$norm  = array();
		foreach ( $meta['colors'] as $slug => $hex ) {
			$norm[ $slug ] = snt_test_norm_hex( $hex );
		}
		$cache[ $label ] = $norm;
	}

	return $cache;
}

// ─── Load palette from theme.json ──────────────────────────────────

$theme_json_path = __DIR__ . '/../theme.json';
$theme_json      = json_decode( (string) file_get_contents( $theme_json_path ), true );

if ( ! is_array( $theme_json ) ) {
	echo "FATAL: cannot read theme.json at $theme_json_path\n";
	exit( 2 );
}

$palette_raw = $theme_json['settings']['color']['palette'] ?? array();
if ( ! is_array( $palette_raw ) || empty( $palette_raw ) ) {
	echo "FATAL: theme.json has no settings.color.palette array\n";
	exit( 2 );
}

$colors = array();
foreach ( $palette_raw as $entry ) {
	if ( is_array( $entry ) && isset( $entry['slug'], $entry['color'] ) ) {
		$colors[ $entry['slug'] ] = $entry['color'];
	}
}

// ─── Harness ───────────────────────────────────────────────────────
$pass = 0; $fail = 0;

function cb_gte( $actual, $expected, $msg ) {
	global $pass, $fail;
	if ( $actual >= $expected ) {
		$pass++;
		echo "  PASS: $msg (got " . sprintf( '%.2f', $actual ) . ":1)\n";
	} else {
		$fail++;
		echo "  FAIL: $msg (expected >= " . sprintf( '%.2f', $expected ) . ", got " . sprintf( '%.2f', $actual ) . ":1)\n";
	}
}

function cb_eq_approx( $actual, $expected, $tolerance, $msg ) {
	global $pass, $fail;
	$delta = abs( $actual - $expected );
	if ( $delta < $tolerance ) {
		$pass++;
		echo "  PASS: $msg (got " . sprintf( '%.2f', $actual ) . ", baseline " . sprintf( '%.2f', $expected ) . ", delta " . sprintf( '%.2f', $delta ) . ")\n";
	} else {
		$fail++;
		echo "  FAIL: $msg (got " . sprintf( '%.2f', $actual ) . ", baseline " . sprintf( '%.2f', $expected ) . ", delta " . sprintf( '%.2f', $delta ) . " >= tolerance " . sprintf( '%.2f', $tolerance ) . ")\n";
	}
}

function cb_required_color( $slug ) {
	global $colors, $pass, $fail;
	if ( isset( $colors[ $slug ] ) ) {
		$pass++;
		echo "  PASS: palette has '$slug' = {$colors[$slug]}\n";
	} else {
		$fail++;
		echo "  FAIL: palette missing required slug '$slug'\n";
	}
}

echo "WCAG 2.1 contrast baseline suite — theme v9.5.0\n";

// ─── Test 1: required palette slugs ────────────────────────────────
echo "\nTest 1: required palette slugs\n";
cb_required_color( 'bone' );
cb_required_color( 'void' );
cb_required_color( 'asphalt' );
cb_required_color( 'rust' );
cb_required_color( 'blood' );
cb_required_color( 'signal' );

if ( $fail > 0 ) {
	echo "\nFATAL: required palette slugs missing — aborting subsequent contrast tests.\n";
	echo "Result: $pass passed, $fail failed.\n";
	exit( 1 );
}

// ─── Test 2: AA-normal text pairings (>= 4.5) ──────────────────────
echo "\nTest 2: AA normal-text pairings (>= 4.5)\n";
cb_gte( snt_test_contrast_ratio( $colors['bone'], $colors['void'] ),     4.5, 'bone on void: AA normal text' );
cb_gte( snt_test_contrast_ratio( $colors['bone'], $colors['asphalt'] ),  4.5, 'bone on asphalt: AA normal text' );
cb_gte( snt_test_contrast_ratio( $colors['rust'], $colors['void'] ),     4.5, 'rust on void: AA normal text (secondary)' );
cb_gte( snt_test_contrast_ratio( $colors['rust'], $colors['asphalt'] ),  4.5, 'rust on asphalt: AA normal text (secondary on cards)' );
cb_gte( snt_test_contrast_ratio( $colors['blood'], $colors['void'] ),    4.5, 'blood on void: AA normal text (brand accent)' );
// blood-on-asphalt (4.60 at root, 3.80 under High Contrast) is NOT asserted
// here any more, for two reasons. It is no longer a rendered pairing — as of
// 2026-08-11 every blood-text-on-asphalt surface moved to void (core/code,
// .sn-pillar-card, .sn-footnote-popover, .sn-notes-pillar) — and a
// single-palette assertion could never have caught it anyway, since it
// passes at root and only fails under a variation. Test 7 replaces it and
// checks every shipped palette.

// ─── Test 3: AA large-text + non-text pairings (>= 3.0) ────────────
echo "\nTest 3: AA large-text / non-text pairings (>= 3.0)\n";
// THE THRESHOLD HERE IS 3.0 AND THAT IS DELIBERATE — see Test 8.
//
// The history is worth keeping because it took two wrong turns. Originally
// this asserted 3.0 with a note that the hover UNDERLINE carried it; that
// reasoning was wrong, because the underline satisfies SC 1.4.1 (Use of
// Color) and does nothing for SC 1.4.3 (Contrast Minimum). v11.7.1 then
// darkened signal to #bf3935 so it could clear 4.5 as text — which worked
// and still treated a symptom, because the real defect was using a 3.29:1
// accent for link hover at all.
//
// v11.7.2 settles it: signal is an OUTLINE colour, never a word. So the
// governing criterion is SC 1.4.11 (Non-text Contrast) at 3:1, which is the
// correct bar for a fill, a focus ring or a status dot — and Test 8 enforces
// that it is never text, which is what makes 3.0 the honest number here
// rather than a lowered one.
cb_gte( snt_test_contrast_ratio( $colors['signal'], $colors['void'] ),    3.0, 'signal on void: SC 1.4.11 non-text (fills, dots, outlines) — never text, see Test 8' );
// signal-on-asphalt (2.49:1) is NOT asserted here any more. The comment it
// carried ("hover state on cards") was stale: the only two signal-on-asphalt
// uses are 7px status dots (.sn-music-featured__dot, .sn-availability__dot),
// both aria-hidden="true" and both sitting beside their own text label — so
// they are decorative and outside SC 1.4.11, which covers graphics REQUIRED
// to understand the content. Test 7 catches it if signal ever becomes real
// text on an asphalt surface.

// ─── Test 4: baseline drift tolerance (watch the tight margin) ─────
echo "\nTest 4: baseline drift tolerance\n";

$current_blood_void     = snt_test_contrast_ratio( $colors['blood'], $colors['void'] );
$current_blood_asphalt  = snt_test_contrast_ratio( $colors['blood'], $colors['asphalt'] );
$current_signal_void    = snt_test_contrast_ratio( $colors['signal'], $colors['void'] );
$current_signal_asphalt = snt_test_contrast_ratio( $colors['signal'], $colors['asphalt'] );
$current_rust_void      = snt_test_contrast_ratio( $colors['rust'], $colors['void'] );

// Baselines from docs/ACCESSIBILITY.md (measured 2026-05-26). These are
// the ROOT palette — the theme's default style. They are NOT what the live
// site serves whenever a style variation is active; see Test 6.
// Tolerance ±0.20 → any meaningful palette tweak fails the test, forcing
// an explicit decision (update theme.json AND this test in the same commit).
cb_eq_approx( $current_blood_void,     5.01, 0.20, 'blood-on-void baseline drift within tolerance (baseline 5.01)' );
cb_eq_approx( $current_blood_asphalt,  4.60, 0.20, 'blood-on-asphalt baseline drift within tolerance (baseline 4.60 — TIGHT)' );
cb_eq_approx( $current_signal_void,    3.29, 0.20, 'signal-on-void baseline drift within tolerance (baseline 3.29 — brand accent restored in v11.7.2)' );
cb_eq_approx( $current_signal_asphalt, 3.02, 0.20, 'signal-on-asphalt baseline drift within tolerance (baseline 3.02 — brand accent restored in v11.7.2)' );
cb_eq_approx( $current_rust_void,      5.74, 0.20, 'rust-on-void baseline drift within tolerance (baseline 5.74)' );

// ─── Test 5: maximum-contrast sanity check ─────────────────────────
echo "\nTest 5: maximum-contrast sanity\n";
$bone_void_ratio = snt_test_contrast_ratio( $colors['bone'], $colors['void'] );
cb_gte( $bone_void_ratio, 20.0, 'bone-on-void approaches WCAG max (21.0) — sanity check that #000 / #fff is configured' );

// ─── Test 6: the SERVED palette is root or a shipped variation ─────
//
// NOT "served == theme.json". A style variation is a legitimate, shipped
// alternative palette, and activating one makes served differ from root
// by design — asserting equality would fail on correct configuration.
//
// The real invariant: whatever the site serves must be a palette this
// repo actually ships. That still catches the thing worth catching (an
// ad-hoc Site Editor edit, a stale deploy, a rogue filter) while treating
// an active variation as the normal state it is. It also NAMES the active
// style, which is the fact Tests 1-5 silently assumed away.
//
// Requires WP. Under plain CLI it SKIPS rather than passing vacuously.
echo "\nTest 6: served palette is root or a shipped variation (requires WP)\n";

if ( ! function_exists( 'wp_get_global_stylesheet' ) ) {
	echo "  SKIP: WordPress not loaded — run via `wp eval-file " . basename( __FILE__ ) . "`.\n";
	echo "        This check CANNOT run in CI (no WP, no database). Test 7 is the\n";
	echo "        CI-runnable half; it needs no WP and covers every shipped palette.\n";
} else {
	$served_css = (string) wp_get_global_stylesheet();
	$served     = array();
	if ( preg_match_all( '/--wp--preset--color--([a-z0-9-]+)\s*:\s*([^;]+);/i', $served_css, $m, PREG_SET_ORDER ) ) {
		foreach ( $m as $hit ) {
			$served[ strtolower( $hit[1] ) ] = snt_test_norm_hex( $hit[2] );
		}
	}

	if ( empty( $served ) ) {
		$fail++;
		echo "  FAIL: parsed no --wp--preset--color--* from wp_get_global_stylesheet()\n";
		echo "        (positive-control failure — broken probe, NOT 'no drift')\n";
	} else {
		// Restrict to slugs this theme owns; WP may serve others.
		$served = array_intersect_key( $served, $colors );
		$matched = null;
		foreach ( snt_test_all_palettes() as $label => $palette ) {
			if ( count( $palette ) === count( $served ) && ! array_diff_assoc( $palette, $served ) ) {
				$matched = $label;
				break;
			}
		}

		if ( null !== $matched ) {
			$pass++;
			echo "  PASS: served palette matches a shipped style — ACTIVE STYLE: $matched\n";
			if ( 'root (theme.json)' !== $matched ) {
				echo "        NOTE: Tests 1-5 above measured ROOT, not this. Test 7 covers all styles.\n";
			}
		} else {
			$fail++;
			echo "  FAIL: served palette matches NO shipped style — ad-hoc override or stale deploy\n";
			foreach ( $served as $slug => $hex ) {
				$root = isset( $colors[ $slug ] ) ? snt_test_norm_hex( $colors[ $slug ] ) : '(absent)';
				if ( $root !== $hex ) {
					echo "        $slug: served #$hex, root #$root\n";
				}
			}
		}
	}
}

// ─── Test 7: no sub-AA text pairing under ANY shipped palette ──────
//
// Replaces the retired blood-on-asphalt / signal-on-asphalt ratio
// assertions. Those asserted a NUMBER under ONE palette, which is exactly
// how the High Contrast regression stayed invisible: at root,
// blood-on-asphalt is 4.60 and passes, so a root-only check reports green
// while the ACTIVE style renders it at 3.80.
//
// So this scans the CSS for real text-on-surface pairings and evaluates
// each one under root AND every shipped variation, failing if any palette
// puts it below AA. Needs no WP, so it is the CI-runnable half of the
// enforcement Test 6 can only do with a database.
echo "\nTest 7: no sub-AA text pairing under ANY shipped palette (static)\n";

$css_files = array();
foreach ( array( '/../assets/css', '/../blocks' ) as $rel ) {
	$root_dir = realpath( __DIR__ . $rel );
	if ( ! $root_dir ) {
		continue;
	}
	foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root_dir ) ) as $file ) {
		if ( $file->isFile() && 'css' === strtolower( $file->getExtension() ) ) {
			$css_files[] = $file->getPathname();
		}
	}
}

if ( empty( $css_files ) ) {
	$fail++;
	echo "  FAIL: found no stylesheets to scan (broken probe, NOT 'no violations')\n";
} else {
	$rules = array();
	foreach ( $css_files as $file ) {
		$css = (string) file_get_contents( $file );
		if ( preg_match_all( '/([^{}]+)\{([^{}]*)\}/s', $css, $m, PREG_SET_ORDER ) ) {
			foreach ( $m as $r ) {
				$sel_lines = preg_split( '/\R/', trim( $r[1] ) );
				$rules[]   = array(
					'file' => str_replace( realpath( __DIR__ . '/..' ) . '/', '', $file ),
					'sel'  => trim( (string) end( $sel_lines ) ),
					'body' => $r[2],
				);
			}
		}
	}

	// Surfaces and text are BOTH restricted to pseudo-free rules.
	//
	// Deliberately conservative, and the limitation is the point. State
	// matters: `.x { background: transparent; color: bone }` and
	// `.x:hover { background: blood; color: void }` are two different
	// contexts. An earlier cut of this test stripped `:hover` so it could
	// match more, and promptly paired the hover BACKGROUND with the resting
	// TEXT colour — reporting `.sn-cmdk-trigger` as bone-on-blood when it is
	// actually bone-on-transparent. Correctly modelling cascade and state is
	// a real CSS linter, not a test helper.
	//
	// So: resting state only. It catches the regression class that actually
	// shipped (a surface token changed, or text moved onto a darker fill)
	// with zero false positives. Hover/focus pairings are NOT covered here —
	// they remain a manual review item, noted in docs/ACCESSIBILITY.md.
	$is_plain = function ( $sel ) {
		return false === strpos( $sel, ':' );
	};

	$surfaces = array();
	foreach ( $rules as $r ) {
		if ( ! $is_plain( $r['sel'] ) ) {
			continue;
		}
		if ( ! preg_match( '/background(-color)?:\s*var\(--wp--preset--color--([a-z0-9-]+)\)/', $r['body'], $bg ) ) {
			continue;
		}
		foreach ( explode( ',', $r['sel'] ) as $one ) {
			$one = trim( $one );
			if ( '' !== $one ) {
				$surfaces[ $one ] = $bg[2];
			}
		}
	}

	$palettes = snt_test_all_palettes();
	echo '  palettes evaluated: ' . implode( ', ', array_keys( $palettes ) ) . "\n";
	echo '  resting-state surfaces: ' . count( $surfaces ) . "\n";

	// Longest first, so a child's own surface beats an ancestor's.
	uksort( $surfaces, function ( $x, $y ) {
		return strlen( $y ) - strlen( $x );
	} );

	$violations = 0;
	foreach ( $rules as $r ) {
		if ( ! $is_plain( $r['sel'] ) ) {
			continue;
		}
		if ( ! preg_match( '/(?<![a-z-])color:\s*var\(--wp--preset--color--([a-z0-9-]+)\)/', $r['body'], $c ) ) {
			continue;
		}

		$bg_slug = null;
		if ( preg_match( '/background(-color)?:\s*var\(--wp--preset--color--([a-z0-9-]+)\)/', $r['body'], $own ) ) {
			$bg_slug = $own[2];
		} else {
			foreach ( $surfaces as $surface => $slug ) {
				if ( $r['sel'] === $surface
					|| 0 === strpos( $r['sel'], $surface . ' ' )
					|| 0 === strpos( $r['sel'], $surface . '-' )
					|| false !== strpos( $r['sel'], ' ' . $surface . ' ' ) ) {
					$bg_slug = $slug;
					break;
				}
			}
		}
		if ( null === $bg_slug || $bg_slug === $c[1] ) {
			continue;
		}

		foreach ( $palettes as $label => $palette ) {
			if ( ! isset( $palette[ $c[1] ], $palette[ $bg_slug ] ) ) {
				continue;
			}
			$ratio = snt_test_contrast_ratio( $palette[ $c[1] ], $palette[ $bg_slug ] );
			if ( $ratio >= 4.5 ) {
				continue;
			}
			$violations++;
			$fail++;
			echo '  FAIL: ' . $c[1] . ' on ' . $bg_slug . ' = ' . sprintf( '%.2f', $ratio ) . ":1 under $label\n";
			echo "        {$r['sel']}  [{$r['file']}]\n";
		}
	}

	if ( 0 === $violations ) {
		$pass++;
		echo '  PASS: every text-on-surface pairing clears AA under all ' . count( $palettes ) . " shipped palettes\n";
	}
}

// ─── Test 8: signal is an OUTLINE colour, never a word ─────────────
//
// The rule the palette now encodes, and the reason v11.7.1's approach was
// wrong. That release darkened signal #ff4c47 -> #bf3935 SO THAT it could
// be text at AA. It worked, and it treated a symptom: the real defect was
// that a 3.29:1 accent was being used for link hover at all.
//
// Reverting to #ff4c47 restores the brand accent AND restores a property
// worth more than the fix: at 3.29:1 the token CANNOT pass as body text,
// so misuse is arithmetically impossible to hide. #bf3935 would have let
// signal-as-text sail through every contrast check forever.
//
// WHY THIS TEST AND NOT TEST 7. Test 7 is resting-state only (a hover
// background paired with resting text produced ~60 false positives, so it
// refuses to model state). Every signal-as-text use in this theme's
// history has been a :hover rule — precisely the shape Test 7 skips. A
// source-level check has no such blind spot: it does not care about state,
// only about whether the token appears after `color:`.
echo "\nTest 8: signal is an outline colour, never a word\n";

$signal_text_uses = array();

// theme.json: any `text` value naming signal, at any depth.
$walk = function ( $node, $path ) use ( &$walk, &$signal_text_uses ) {
	if ( is_array( $node ) ) {
		foreach ( $node as $k => $v ) {
			if ( 'text' === $k && is_string( $v ) && false !== strpos( $v, 'color--signal' ) ) {
				$signal_text_uses[] = "theme.json $path.text";
			}
			$walk( $v, $path . '.' . $k );
		}
	}
};
$walk( $theme_json['styles'] ?? array(), 'styles' );

// Stylesheets: `color:` (never border-color, background-color) naming signal.
foreach ( array( '/../assets/css', '/../blocks' ) as $rel ) {
	$root_dir = realpath( __DIR__ . $rel );
	if ( ! $root_dir ) {
		continue;
	}
	foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root_dir ) ) as $file ) {
		if ( ! $file->isFile() || 'css' !== strtolower( $file->getExtension() ) ) {
			continue;
		}
		$css = (string) file_get_contents( $file->getPathname() );
		if ( preg_match_all( '/(?<![a-z-])color:\s*var\(\s*--wp--preset--color--signal\s*\)/i', $css, $m ) ) {
			$signal_text_uses[] = basename( $file->getPathname() ) . ' × ' . count( $m[0] );
		}
	}
}

if ( empty( $signal_text_uses ) ) {
	$pass++;
	echo "  PASS: signal is never used as a text colour (backgrounds, borders and fills are fine)\n";
} else {
	$fail++;
	echo "  FAIL: signal used as TEXT in " . count( $signal_text_uses ) . " place(s) — it is 3.29:1 on void and cannot be read\n";
	foreach ( $signal_text_uses as $where ) {
		echo "        $where\n";
	}
	echo "        Use bone for a link hover. If signal genuinely must be text, the token is wrong, not this test.\n";
}

/* ── The command-palette trigger's HOVER pair (added after v11.7.1) ────────
 * v11.7.1 FIXED this pair — bone on blood was 4.19:1 at 11.2px — but shipped it
 * unpinned, and the note above says why: this suite models RESTING pairings on
 * purpose, because an earlier cut that stripped `:hover` to match more promptly
 * paired a hover background with a resting text colour. That reasoning is right
 * and it left a live fix with nothing holding it: revert `void` to `bone` today
 * and every test still passes.
 *
 * So this pins the ONE hover pair by name rather than teaching the scanner to
 * model state generally. Narrow on purpose — it asserts what the stylesheet
 * DECLARES for that selector, and checks the resulting pair under every palette
 * the theme can present, which is this file's own standing rule. */
echo "\nGroup: the command-palette trigger inverts to VOID on hover, never bone\n";
$cmdk = (string) file_get_contents( __DIR__ . '/../assets/css/command-palette.css' );
cb_gte( (int) preg_match( '/\.sn-cmdk-trigger:hover,\s*\.sn-cmdk-trigger:focus-visible\s*\{[^}]*color:\s*var\(--wp--preset--color--void\)/s', $cmdk ), 1, 'the hover/focus label is declared --void' );
cb_gte( (int) preg_match( '/\.sn-cmdk-trigger:hover,\s*\.sn-cmdk-trigger:focus-visible\s*\{[^}]*background:\s*var\(--wp--preset--color--blood\)/s', $cmdk ), 1, 'on a --blood surface' );
foreach ( snt_test_all_palettes() as $label => $pal ) {
	$r = snt_test_contrast_ratio( $pal['void'], $pal['blood'] );
	cb_gte( $r, 4.5, sprintf( 'void on blood under %s', $label ) );
}
$bone_on_blood = snt_test_contrast_ratio( $colors['bone'], $colors['blood'] );
if ( $bone_on_blood < 4.5 ) {
	++$pass;
	printf( "  PASS: documented — the pair v11.7.1 replaced really was %.2f:1\n", $bone_on_blood );
} else {
	++$fail;
	echo "  FAIL: bone-on-blood no longer fails — the palette moved and this pin is meaningless\n";
}

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
