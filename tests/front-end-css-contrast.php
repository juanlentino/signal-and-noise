<?php
/**
 * Tests: every ink/surface pair clears AA in every palette this theme serves.
 *
 * tests/front-end-css-inverts.php closes the LITERAL class and states what it
 * cannot see: a token used in the wrong ROLE passes a literal sweep cleanly.
 * That is this theme's own ink-as-chrome class — v12.0.1 converted the command
 * palette's SURFACES to the panel tokens and left the INK on them behind, so
 * every row went invisible in dark, and it was found by SCREENSHOT.
 *
 * Ported from the companion plugin, with one improvement the plugin cannot
 * have: THIS repo owns the palettes, so they are read from source
 * (`theme.json`, `styles/high-contrast.json`, and the dark override in
 * `assets/css/critical.css`) rather than pinned. There is no copy to drift.
 *
 * THREE PALETTES, ORTHOGONAL AXES. `root` and `high-contrast` are light-scheme
 * VARIATIONS; `dark` overrides whichever variation is active. A High Contrast
 * reader on a dark OS gets dark, not a blend. All three must clear.
 *
 * FINDINGS ARE SPLIT BY CAUSE, because the fix differs:
 *   - CONTRAST — the pair is real and below AA. Fix the CSS.
 *   - UNMAPPED — the ink resolves to the SAME colour as the surface this file
 *     assumed (1.00:1). Text is never deliberately invisible, so that is a wrong
 *     ASSUMPTION, not a bug in the design: the element sits on something else.
 *     Fix the map. Merging the two classes would send someone to change a colour
 *     that was never wrong.
 *
 * WHAT THIS CANNOT SEE: which element sits inside which is a fact about the
 * HTML, not the stylesheet. Anything not declaring its own background and not
 * named in $on_surface is assumed to sit on the page ground. Also invisible:
 * non-text contrast, opacity, blend modes, background images, and reachability
 * — dead CSS is checked and passes.
 *
 * @since 2026-08-20
 */
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }

$pass = 0; $fail = 0;
function ok( $c, $m ) { global $pass, $fail; if ( $c ) { ++$pass; echo "PASS: $m\n"; } else { ++$fail; echo "FAIL: $m\n"; } }
echo "every ink/surface pair clears AA in every palette this theme serves\n\n";

$root = realpath( __DIR__ . '/..' );
require_once __DIR__ . '/lib/sn-print-media.php';

// ── colour maths (WCAG 2.x) ────────────────────────────────────────────────
function sn_fecc_rgb( $v ) {
	$v = trim( strtolower( (string) $v ) );
	if ( preg_match( '/^#([0-9a-f]{3})$/', $v, $m ) ) {
		$v = '#' . $m[1][0] . $m[1][0] . $m[1][1] . $m[1][1] . $m[1][2] . $m[1][2];
	}
	if ( preg_match( '/^#([0-9a-f]{6})$/', $v, $m ) ) {
		return array( hexdec( substr( $m[1], 0, 2 ) ), hexdec( substr( $m[1], 2, 2 ) ), hexdec( substr( $m[1], 4, 2 ) ) );
	}
	if ( preg_match( '/^rgba?\(\s*([0-9]+)[,\s]+([0-9]+)[,\s]+([0-9]+)/', $v, $m ) ) {
		return array( (int) $m[1], (int) $m[2], (int) $m[3] );
	}
	return null;
}
function sn_fecc_lum( $rgb ) {
	$c = array();
	foreach ( $rgb as $ch ) { $s = $ch / 255; $c[] = ( $s <= 0.03928 ) ? $s / 12.92 : pow( ( $s + 0.055 ) / 1.055, 2.4 ); }
	return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
}
function sn_fecc_ratio( $a, $b ) {
	$ra = sn_fecc_rgb( $a ); $rb = sn_fecc_rgb( $b );
	if ( null === $ra || null === $rb ) { return null; }
	$l1 = sn_fecc_lum( $ra ); $l2 = sn_fecc_lum( $rb );
	if ( $l1 < $l2 ) { $t = $l1; $l1 = $l2; $l2 = $t; }
	return ( $l1 + 0.05 ) / ( $l2 + 0.05 );
}
/** A var() resolves to the token when defined, to its FALLBACK when not — as a browser does. */
function sn_fecc_resolve( $value, $tokens, $depth = 0 ) {
	$value = trim( (string) $value );
	if ( $depth > 6 ) { return null; }
	if ( preg_match( '/^var\(\s*(--[a-zA-Z0-9-]+)\s*(?:,\s*(.*))?\)$/s', $value, $m ) ) {
		$name = $m[1];
		$slug = preg_replace( '/^--wp--preset--color--/', '', $name );
		if ( isset( $tokens[ $slug ] ) ) { return sn_fecc_resolve( $tokens[ $slug ], $tokens, $depth + 1 ); }
		if ( isset( $tokens[ $name ] ) ) { return sn_fecc_resolve( $tokens[ $name ], $tokens, $depth + 1 ); }
		return isset( $m[2] ) && '' !== trim( $m[2] ) ? sn_fecc_resolve( $m[2], $tokens, $depth + 1 ) : null;
	}
	return sn_fecc_rgb( $value ) ? $value : null;
}
/** Flatten to [selector, body], descending into at-rules and CARRYING their condition. */
function sn_fecc_rules( $css ) {
	$css = (string) preg_replace( '#/\*.*?\*/#s', '', $css );
	$out = array(); $n = strlen( $css ); $i = 0; $start = 0;
	while ( $i < $n ) {
		if ( '{' === $css[ $i ] ) {
			$sel = trim( substr( $css, $start, $i - $start ) );
			$depth = 0;
			for ( $j = $i; $j < $n; $j++ ) {
				if ( '{' === $css[ $j ] ) { ++$depth; }
				if ( '}' === $css[ $j ] ) { --$depth; if ( 0 === $depth ) { break; } }
			}
			$body = substr( $css, $i + 1, $j - $i - 1 );
			if ( '' !== $sel && '@' === $sel[0] ) {
				foreach ( sn_fecc_rules( $body ) as $inner ) { $out[] = array( $sel . ' ' . $inner[0], $inner[1] ); }
			} else {
				$out[] = array( $sel, $body );
			}
			$i = $j + 1; $start = $i; continue;
		}
		++$i;
	}
	return $out;
}
function sn_fecc_decls( $body ) {
	$out = array();
	foreach ( explode( ';', (string) $body ) as $d ) {
		$at = strpos( $d, ':' );
		if ( false === $at ) { continue; }
		// `!important` is cascade weight, not part of the value. Left on, it
		// makes every declaration carrying it unresolvable — and an unresolvable
		// pair is one nobody measured.
		$val = trim( preg_replace( '/\s*!\s*important\s*$/i', '', trim( substr( $d, $at + 1 ) ) ) );
		$out[ strtolower( trim( substr( $d, 0, $at ) ) ) ] = $val;
	}
	return $out;
}
/** The background a named selector paints, read from the stylesheet itself. */
function sn_fecc_bg_of_selector( array $rules, $selector ) {
	foreach ( $rules as $rule ) {
		if ( trim( preg_replace( '/\s+/', ' ', $rule[0] ) ) !== $selector ) { continue; }
		$d  = sn_fecc_decls( $rule[1] );
		$bg = $d['background-color'] ?? ( $d['background'] ?? '' );
		if ( '' !== $bg ) { return $bg; }
	}
	return null;
}

// ── the palettes, READ FROM SOURCE (this repo owns them; nothing is pinned) ──
echo "Group: the three palettes load from the sources this repo owns\n";
$palettes = array();
foreach ( array( 'root' => '/theme.json', 'high-contrast' => '/styles/high-contrast.json' ) as $id => $rel ) {
	$json = json_decode( (string) @file_get_contents( $root . $rel ), true );
	foreach ( (array) ( $json['settings']['color']['palette'] ?? array() ) as $e ) {
		$palettes[ $id ][ (string) $e['slug'] ] = strtolower( (string) $e['color'] );
	}
	ok( 7 === count( $palettes[ $id ] ?? array() ), "`$id` loads 7 colours from $rel" );
}
// dark is `root` with the critical.css override applied on top — that is exactly
// how a browser resolves it, and it is why dark is a SCHEME and not a variation.
$crit = (string) file_get_contents( $root . '/assets/css/critical.css' );
$palettes['dark'] = $palettes['root'];
$dark_found = 0;
// Found with the SAME parser the sweep uses, not a bespoke regex. The first
// version of this used `/:root\[data-theme="dark"\][^{]*\{(.*?)\n\}/s` and
// matched NOTHING, so `dark` stayed a copy of `root` and the whole sweep passed
// by comparing light against light. The "palettes really differ" assertion
// below is what caught it; without that guard this file would have shipped
// green and blind to an entire scheme.
foreach ( sn_fecc_rules( $crit ) as $rule ) {
	if ( ':root[data-theme="dark"]' !== trim( preg_replace( '/\s+/', ' ', $rule[0] ) ) ) { continue; }
	foreach ( sn_fecc_decls( $rule[1] ) as $k => $v ) {
		if ( 0 === strpos( $k, '--wp--preset--color--' ) ) { $palettes['dark'][ substr( $k, 21 ) ] = strtolower( $v ); ++$dark_found; }
	}
}
ok( 7 === $dark_found, "`dark` reads $dark_found colour overrides from critical.css (expects all 7)" );
ok( '#0a0a0a' === ( $palettes['dark']['void'] ?? '' ) && '#ffffff' === ( $palettes['root']['void'] ?? '' ), 'the palettes really differ — dark void is #0a0a0a, light is #ffffff (guard: three identical palettes would pass vacuously)' );

// ── surfaces that are NOT the page ground ──────────────────────────────────
// Each names the RULE THAT PAINTS the ground, never a colour: a colour here
// would be a second copy of a fact the stylesheet already states, and copies
// drift. Resolved at run time, so a CSS change moves the measurement.
$on_surface = array(
	'.sn-cmdk-input::placeholder'                     => array( array( 'from' => '.sn-cmdk-input' ) ),
	'.sn-cmdk-panel .sn-cmdk-option__label'           => array( array( 'from' => '.sn-cmdk-panel' ) ),
	'.sn-cmdk-panel .sn-cmdk-option__sub'             => array( array( 'from' => '.sn-cmdk-panel' ) ),
	'.sn-cmdk-option.is-active .sn-cmdk-option__label, .sn-cmdk-option.is-active .sn-cmdk-option__sub'
		=> array( array( 'from' => '.sn-cmdk-option.is-active' ) ),
	'.sn-kbdn-title'                                  => array( array( 'from' => '.sn-kbdn-panel' ) ),
	'.sn-kbdn-list dd'                                => array( array( 'from' => '.sn-kbdn-panel' ) ),
);

$skip_ink = array( 'currentcolor', 'inherit', 'transparent', 'unset', 'initial', 'revert' );

/**
 * Theme-owned `--sn-*` tokens, per scheme, collected across EVERY stylesheet.
 *
 * NOT per file. `--sn-panel` and its family are declared once in critical.css
 * and consumed in command-palette.css and keyboard-nav.css. Collected per file,
 * those consumers resolve the token to NULL, and a null pair is skipped — so
 * the sweep silently stopped measuring the very surfaces this theme's
 * ink-as-chrome bug lived on, and reported a clean run. Caught by mutation:
 * reintroducing the real v12.0.1 defect produced no finding at all.
 */
function sn_fecc_theme_tokens( array $files ) {
	$local = array( 'light' => array(), 'dark' => array() );
	foreach ( $files as $file ) {
		foreach ( sn_fecc_rules( (string) file_get_contents( $file ) ) as $rule ) {
			list( $sel, $body ) = $rule;
			$is_dark = ( false !== strpos( $sel, 'data-theme="dark"' ) ) || ( false !== strpos( str_replace( ' ', '', $sel ), 'prefers-color-scheme:dark' ) );
			if ( false === strpos( $sel, ':root' ) && ! $is_dark ) { continue; }
			foreach ( sn_fecc_decls( $body ) as $p => $v ) {
				if ( 0 === strpos( $p, '--' ) ) { $local[ $is_dark ? 'dark' : 'light' ][ $p ] = $v; }
			}
		}
	}
	return $local;
}

/** @return array{contrast:array,unmapped:array,unresolved:array} */
function sn_fecc_audit( $file, $palettes, $on_surface, $skip_ink, $root, $local ) {
	$rules = sn_fecc_rules( (string) file_get_contents( $file ) );
	$rel   = 'assets/css/' . basename( $file );
	$out   = array( 'contrast' => array(), 'unmapped' => array(), 'unresolved' => array() );

	foreach ( $rules as $rule ) {
		list( $sel, $body ) = $rule;
		if ( false !== strpos( $sel, ':root' ) ) { continue; }
		$flat  = trim( preg_replace( '/\s+/', ' ', $sel ) );
		$decls = sn_fecc_decls( $body );
		$ink   = $decls['color'] ?? '';
		if ( '' === $ink || in_array( strtolower( $ink ), $skip_ink, true ) ) { continue; }

		$own = $decls['background-color'] ?? ( $decls['background'] ?? '' );
		if ( '' !== $own && ( false !== strpos( $own, 'var(' ) || null !== sn_fecc_rgb( $own ) ) ) {
			$surfaces = array( $own );                  // self-contained: no assumption at all
		} elseif ( isset( $on_surface[ $flat ] ) ) {
			$surfaces = $on_surface[ $flat ];           // mapped
		} else {
			$surfaces = array( 'var(--wp--preset--color--void)' ); // assumed page ground
		}

		foreach ( $surfaces as $surface ) {
			if ( is_array( $surface ) && isset( $surface['from'] ) ) {
				$resolved = sn_fecc_bg_of_selector( $rules, $surface['from'] );
				if ( null === $resolved ) {
					$out['contrast'][] = $rel . ' :: ' . $flat . ' :: $on_surface names `' . $surface['from'] . '`, which paints nothing';
					continue;
				}
				$surface = $resolved;
			}
			foreach ( $palettes as $id => $tokens ) {
				$map = array_merge( $tokens, $local['light'], 'dark' === $id ? $local['dark'] : array() );
				$a   = sn_fecc_resolve( $ink, $map );
				$b   = sn_fecc_resolve( $surface, $map );
				if ( null === $a || null === $b ) {
					// A pair this file cannot resolve is a pair it did not
					// measure. Silently skipping is how the sweep reported a
					// clean run over the surfaces the real bug lived on.
					$out['unresolved'][] = sprintf( '%s :: %s [%s] ink=%s surface=%s', $rel, $flat, $id, $ink, $surface );
					continue;
				}
				$r = sn_fecc_ratio( $a, $b );
				if ( null === $r || $r >= 4.5 ) { continue; }
				$line = sprintf( '%s :: %s [%s] %s on %s = %.2f:1', $rel, $flat, $id, $a, $b, $r );
				// Text is never deliberately invisible. 1.00:1 means the ASSUMED
				// surface is wrong, not that a colour is.
				if ( $r < 1.005 ) { $out['unmapped'][] = $line; } else { $out['contrast'][] = $line; }
			}
		}
	}
	return $out;
}


/**
 * Alpha of a colour value: rgba(), or a color-mix() against `transparent`.
 *
 * `color-mix( in srgb, var(--x) 38%, transparent )` is this theme's idiom for a
 * token at partial alpha — five uses before today. Treated as opaque it scores
 * a ratio no reader meets, which is the same error as ignoring rgba()'s alpha.
 */
function sn_fecc_alpha( $value ) {
	$v = trim( (string) $value );
	if ( preg_match( '/^rgba\(\s*[0-9]+[,\s]+[0-9]+[,\s]+[0-9]+[,\s\/]+([0-9.]+)\s*\)$/i', $v, $m ) ) {
		return (float) $m[1];
	}
	if ( preg_match( '/color-mix\(\s*in\s+srgb\s*,(.+?)\s+([0-9.]+)%\s*,\s*transparent\s*\)/i', $v, $m ) ) {
		return ( (float) $m[2] ) / 100;
	}
	return 1.0;
}

/** The colour inside a color-mix(...) against transparent, or the value itself. */
function sn_fecc_base_colour( $value ) {
	if ( preg_match( '/color-mix\(\s*in\s+srgb\s*,(.+?)\s+[0-9.]+%\s*,\s*transparent\s*\)/i', (string) $value, $m ) ) {
		return trim( $m[1] );
	}
	return $value;
}

/** Composite a possibly-translucent colour over an opaque ground: c = a*fg + (1-a)*bg. */
function sn_fecc_over( $value, $surface, $tokens ) {
	$fg = sn_fecc_rgb( sn_fecc_resolve( sn_fecc_base_colour( $value ), $tokens ) );
	$bg = sn_fecc_rgb( $surface );
	if ( null === $fg || null === $bg ) { return null; }
	$a   = sn_fecc_alpha( $value );
	$out = array();
	foreach ( array( 0, 1, 2 ) as $i ) { $out[] = (int) round( $a * $fg[ $i ] + ( 1 - $a ) * $bg[ $i ] ); }
	return 'rgb(' . implode( ', ', $out ) . ')';
}

/**
 * Non-text contrast (WCAG 2.2 1.4.11, 3:1) for the marks that carry MEANING.
 *
 * SCOPE IS DERIVED FROM WHAT THE SELECTOR MEANS, not from a file list:
 *   - every OUTLINE with a colour, because a focus indicator is never
 *     decorative — it is the only thing telling a keyboard user where they are;
 *   - every BORDER on a rule whose selector carries a STATE (:focus, :hover,
 *     .is-active, [aria-current], :target, .is-selected), because 1.4.11 covers
 *     "visual information required to identify components and their states".
 * A plain hairline under a table row is decoration and is not checked — WCAG
 * exempts decoration, and a blanket rule would red every rule in the codebase
 * against a standard that does not apply to it.
 *
 * AN OUTLINE AND A BORDER DO NOT SIT ON THE SAME THING. An outline is drawn
 * OUTSIDE the border edge, so it meets whatever the ancestor painted. A border
 * is drawn over the element's own background (`background-clip` is border-box
 * by default). Using one rule for both is wrong twice over, and the companion
 * plugin shipped exactly that error in its v12.5.0.
 */
function sn_fecc_nontext_audit( $file, $palettes, $on_surface, $root, $local ) {
	$rules = sn_fecc_rules( (string) file_get_contents( $file ) );
	$rel   = 'assets/css/' . basename( $file );
	$out   = array( 'contrast' => array(), 'unresolved' => array(), 'checked' => 0, 'same_as_ground' => 0 );

	// System keywords resolve inside the OS, not the stylesheet. `Highlight` is
	// the forced-colors focus colour; scoring it here would be inventing a value.
	$skip = array( 'none', 'currentcolor', 'inherit', 'transparent', 'unset', 'initial', 'revert', 'highlight', 'canvas', 'canvastext', 'linktext', 'buttontext' );
	$state_re = '/:focus|:hover|\.is-active|\[aria-current|:target|\.is-selected/';

	foreach ( $rules as $rule ) {
		list( $sel, $body ) = $rule;
		if ( false !== strpos( $sel, ':root' ) ) { continue; }
		$flat  = trim( preg_replace( '/\s+/', ' ', $sel ) );
		$decls = sn_fecc_decls( $body );

		$marks = array();
		foreach ( array( 'outline-color', 'outline' ) as $p ) {
			if ( isset( $decls[ $p ] ) ) { $marks[] = array( 'outline', $p, $decls[ $p ] ); break; }
		}
		if ( preg_match( $state_re, $flat ) ) {
			// Every form a border colour can arrive in, including the per-side
			// SHORTHANDS. Listing only `border` and `border-*-color` missed
			// `border-bottom: 2px solid var(--x)` entirely — marks that were
			// never measured and therefore never in the pass they appeared to
			// clear.
			foreach ( array( 'border-color', 'border', 'border-top', 'border-bottom', 'border-left', 'border-right', 'border-top-color', 'border-bottom-color', 'border-left-color', 'border-right-color' ) as $p ) {
				if ( isset( $decls[ $p ] ) ) { $marks[] = array( 'border', $p, $decls[ $p ] ); }
			}
		}
		if ( ! $marks ) { continue; }

		foreach ( $marks as list( $kind, $prop, $raw ) ) {
			// Pull the colour out of a shorthand like `2px solid var(--x)`.
			if ( preg_match( '/(var\(.*\)|color-mix\(.*\)|#[0-9a-fA-F]{3,8}|rgba?\([^)]*\))/', $raw, $m ) ) {
				$value = $m[1];
			} else {
				$value = trim( (string) preg_replace( '/^[0-9.]+(px|em|rem)\s+\w+\s*/', '', $raw ) );
			}
			if ( in_array( strtolower( trim( $value ) ), $skip, true ) ) { continue; }

			// An outline meets the ancestor ground; a border meets its own fill.
			$own = $decls['background-color'] ?? ( $decls['background'] ?? '' );
			if ( 'border' === $kind && '' !== $own && ( false !== strpos( $own, 'var(' ) || null !== sn_fecc_rgb( $own ) ) ) {
				$surfaces = array( $own );
			} elseif ( isset( $on_surface[ $flat ] ) ) {
				$surfaces = $on_surface[ $flat ];
			} else {
				$surfaces = array( 'var(--wp--preset--color--void)' );
			}

			foreach ( $surfaces as $surface ) {
				if ( is_array( $surface ) && isset( $surface['from'] ) ) {
					$resolved = sn_fecc_bg_of_selector( $rules, $surface['from'] );
					if ( null === $resolved ) { continue; }
					$surface = $resolved;
				}
				foreach ( $palettes as $id => $tokens ) {
					$map = array_merge( $tokens, $local['light'], 'dark' === $id ? $local['dark'] : array() );
					$b   = sn_fecc_resolve( $surface, $map );
					if ( null === $b ) { $out['unresolved'][] = "$rel :: $flat :: $prop [$id] surface=$surface"; continue; }
					$a = sn_fecc_over( $value, $b, $map );
					if ( null === $a ) { $out['unresolved'][] = "$rel :: $flat :: $prop [$id] value=$value"; continue; }
					// A mark the same colour as its ground is not a mark — it is
					// fill. Common here: a hover state that sets `background`
					// AND `border-color` to `blood` to draw a solid chip.
					// COUNTED, not silently dropped, so the measured total below
					// is explainable rather than merely small.
					if ( strtolower( $a ) === strtolower( sn_fecc_rgb( $b ) ? 'rgb(' . implode( ', ', sn_fecc_rgb( $b ) ) . ')' : '' ) ) { ++$out['same_as_ground']; continue; }
					++$out['checked'];
					$r = sn_fecc_ratio( $a, $b );
					if ( null === $r || $r >= 3.0 ) { continue; }
					$out['contrast'][] = sprintf( '%s :: %s :: %s [%s] %s over %s = %.2f:1 (non-text needs 3.0)', $rel, $flat, $prop, $id, $a, $b, $r );
				}
			}
		}
	}
	return $out;
}

echo "\nGroup: every ink/surface pair clears AA (4.5:1) in all three palettes\n";
// Print stylesheets are excluded: paper is white and dark mode never reaches
// it, so scoring `color:#000` against the dark palette is a question about a
// medium that does not exist. Derived from the enqueue, shared with the literal
// sweep so the two cannot disagree about what "a print stylesheet" means.
$print = sn_print_media_sheets( (string) file_get_contents( $root . '/inc/assets-frontend.php' ) );
ok( in_array( 'print.css', $print, true ), 'print.css is discovered as print-media and excluded from the palette sweep' );
$files = array_values( array_filter( glob( $root . '/assets/css/*.css' ), function ( $f ) use ( $print ) {
	return ! in_array( basename( $f ), $print, true );
} ) );
ok( count( $files ) > 8, 'the sweep finds the stylesheets (guard: a glob matching nothing would pass vacuously)' );

$tokens_all = sn_fecc_theme_tokens( $files );
ok( isset( $tokens_all['light']['--sn-panel'], $tokens_all['dark']['--sn-panel'] ), 'theme tokens are collected across ALL stylesheets — --sn-panel is declared in critical.css and consumed elsewhere' );

$contrast = array(); $unmapped = array(); $unresolved = array();
foreach ( $files as $file ) {
	$a = sn_fecc_audit( $file, $palettes, $on_surface, $skip_ink, $root, $tokens_all );
	$contrast   = array_merge( $contrast, $a['contrast'] );
	$unmapped   = array_merge( $unmapped, $a['unmapped'] );
	$unresolved = array_merge( $unresolved, $a['unresolved'] );
}
foreach ( array_slice( $unresolved, 0, 8 ) as $u ) { echo "  -> UNRESOLVED (not measured): $u\n"; }
ok( empty( $unresolved ), sprintf( 'every ink/surface pair RESOLVED and was actually measured (%d unresolved)', count( $unresolved ) ) );
foreach ( $unmapped as $u ) { echo "  -> UNMAPPED (fix the map, not the colour): $u\n"; }
foreach ( $contrast as $c ) { echo "  -> CONTRAST: $c\n"; }
ok( empty( $unmapped ), sprintf( 'no ink resolves to its own assumed surface — every nested surface is mapped (%d unmapped)', count( $unmapped ) ) );
ok( empty( $contrast ), sprintf( 'no text falls below AA on the surface it sits on (%d finding(s))', count( $contrast ) ) );


// ── non-text contrast (3:1) for focus indicators and state marks ───────────
echo "\nGroup: focus indicators and state marks clear 3:1, composited\n";
$nt = array( 'contrast' => array(), 'unresolved' => array(), 'checked' => 0, 'same_as_ground' => 0 );
foreach ( $files as $file ) {
	$a = sn_fecc_nontext_audit( $file, $palettes, $on_surface, $root, $tokens_all );
	$nt['contrast']   = array_merge( $nt['contrast'], $a['contrast'] );
	$nt['unresolved'] = array_merge( $nt['unresolved'], $a['unresolved'] );
	$nt['checked']   += $a['checked'];
	$nt['same_as_ground'] += $a['same_as_ground'];
}
echo sprintf( "  (%d comparisons measured, %d skipped as mark-equals-its-own-fill)\n", $nt['checked'], $nt['same_as_ground'] );
// A pass over nothing is not a pass. The scope rule is narrow BY DESIGN, so the
// count is the only thing separating "every mark cleared 3:1" from "the scope
// matched no marks at all" — and those read identically from the outside.
// Floor set BELOW the observed 42 with room for ordinary CSS edits, not at it —
// the job is to catch the scope rule collapsing to nothing, not to break on the
// next hover state someone adds or removes.
ok( $nt['checked'] >= 30, sprintf( 'the non-text pass actually measured marks (%d comparisons across 3 palettes)', $nt['checked'] ) );
foreach ( array_slice( $nt['unresolved'], 0, 6 ) as $u ) { echo "  -> UNRESOLVED (not measured): $u\n"; }
foreach ( $nt['contrast'] as $c ) { echo "  -> $c\n"; }
ok( empty( $nt['unresolved'] ), sprintf( 'every non-text mark RESOLVED and was actually measured (%d unresolved)', count( $nt['unresolved'] ) ) );
ok( empty( $nt['contrast'] ), sprintf( 'no focus indicator or state mark falls below 3:1 (%d finding(s))', count( $nt['contrast'] ) ) );

// Controls for the alpha maths, on hand-computable values.
ok( 0.38 === sn_fecc_alpha( 'color-mix( in srgb, var(--x) 38%, transparent )' ), 'alpha is read from a color-mix() against transparent — this theme\'s idiom for a token at partial alpha' );
ok( 0.45 === sn_fecc_alpha( 'rgba(18,112,58,.45)' ), 'and from an rgba()' );
ok( 1.0 === sn_fecc_alpha( 'var(--wp--preset--color--blood)' ), 'an opaque token reports alpha 1.0' );
ok( 'rgb(255, 128, 128)' === sn_fecc_over( 'rgba(255,0,0,0.5)', '#ffffff', array() ), 'NEGATIVE CONTROL: 50% red over white composites to rgb(255, 128, 128) — hand-computable' );
ok( 'rgb(128, 128, 128)' === sn_fecc_over( 'color-mix( in srgb, #000000 50%, transparent )', '#ffffff', array() ), 'and a 50% color-mix composites identically — the two idioms agree' );
// Drive the real auditor on planted marks, so falsifiability is PINNED rather
// than demonstrated once by hand and then trusted.
$ntp = sn_fecc_nontext_audit( $root . '/tests/fixtures/theme-contrast-probe.css', $palettes, $on_surface, $root, $tokens_all );
ok( 3 === count( $ntp['contrast'] ), 'NEGATIVE CONTROL: a weak focus ring IS caught, once per palette (' . count( $ntp['contrast'] ) . ')' );
ok( 3 === $ntp['same_as_ground'], 'and a state mark the same colour as its own fill is SKIPPED as fill, once per palette (' . $ntp['same_as_ground'] . ')' );
ok( 1 === count( preg_grep( '/\[dark\]/', $ntp['contrast'] ) ), 'the planted ring is reported in the dark palette too — an outline is measured against the ANCESTOR ground, which inverts' );

echo "\nGroup: negative controls\n";
ok( abs( sn_fecc_ratio( '#000000', '#ffffff' ) - 21.0 ) < 0.01, 'the maths agrees with the spec boundary: black on white is 21:1' );
ok( '#0a0a0a' === sn_fecc_resolve( 'var(--wp--preset--color--void)', $palettes['dark'] ), 'a token resolves to its DARK value under the dark palette' );
ok( '#ffffff' === sn_fecc_resolve( 'var(--wp--preset--color--void)', $palettes['root'] ), 'and to its light value under root' );
ok( '#123456' === sn_fecc_resolve( 'var(--sn-nope,#123456)', $palettes['root'] ), 'an unknown token falls back exactly as a browser would' );
$rl = sn_fecc_rules( '@media (min-width:40em){.a{color:red}}.b{color:blue}' );
ok( 2 === count( $rl ) && false !== strpos( $rl[0][0], 'min-width' ), 'the parser descends into @media and CARRIES the condition' );
$cp = sn_fecc_rules( (string) file_get_contents( $root . '/assets/css/command-palette.css' ) );
ok( null !== sn_fecc_bg_of_selector( $cp, '.sn-cmdk-panel' ), 'a mapped ground resolves from the CSS (not copied into the map)' );
ok( null === sn_fecc_bg_of_selector( $cp, '.sn-not-a-selector' ), 'and a selector that paints nothing resolves to NULL, reported rather than skipped' );
// Drive the real auditor on a planted role error: panel ink on the PAGE.
$fx = $root . '/tests/fixtures/theme-contrast-probe.css';
$probe = sn_fecc_audit( $fx, $palettes, $on_surface, $skip_ink, $root, $tokens_all );
ok( 3 === count( $probe['contrast'] ), 'NEGATIVE CONTROL: a planted role error is caught once per palette (' . count( $probe['contrast'] ) . ')' );
ok( 3 === count( $probe['unmapped'] ), 'NEGATIVE CONTROL: an ink identical to its assumed ground is classed UNMAPPED, not CONTRAST — once per palette (' . count( $probe['unmapped'] ) . ')' );
ok( array() === array_filter( $probe['contrast'], function ( $l ) { return false !== strpos( $l, 'sn-fx-unmapped' ); } ), 'and the UNMAPPED rule never leaks into the CONTRAST class, which would send someone to change a colour that was never wrong' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
