<?php
/**
 * Standalone PHP test pinning `settings.viewport` in theme.json against the
 * breakpoints assets/css/responsive.css actually uses.
 *
 * WHY THIS EXISTS. WordPress 7.1 lets a theme declare its responsive
 * breakpoints (dev note: "Responsive block styles and configurable viewports in
 * WordPress 7.1", 2026-08-05). Core generates range-syntax media queries from
 * them and uses them for responsive block styles AND for block visibility:
 *
 *   mobile: "480px", tablet: "781px"
 *     -> @media (width <= 480px)              // mobile
 *     -> @media (480px < width <= 781px)      // tablet
 *
 * The upper bound is INCLUSIVE (`<=`), which is the whole reason this file
 * exists. This theme's responsive tiers live in assets/css/responsive.css,
 * which is organised under two literal section banners:
 *
 *   RESPONSIVE — TABLET (max-width: 781px)
 *   RESPONSIVE — MOBILE (max-width: 480px)
 *
 * So the theme's real tablet ceiling is 781px, not WordPress's 782px default.
 * Taking the default would have declared a boundary the stylesheet does not
 * honour: at exactly 782px core would consider the viewport "tablet" while
 * responsive.css treats it as desktop. A 1px band, invisible, permanent, and
 * only reachable through a feature nothing uses yet — which is precisely how a
 * seam survives. Declaring 781px makes the declaration TRUE.
 *
 * WHY A TEST AND NOT A COMMENT. theme.json admits no comments, so the numbers
 * sit there unexplained and unenforced. Before this file the tier system
 * existed only as a section banner in a stylesheet: a human convention two
 * files apart, with nothing connecting them. A declaration that can drift from
 * the CSS it describes is documentation, and stale documentation about
 * breakpoints is worse than none — the editor would offer authors a Tablet
 * preview at a width the site does not break at. This test makes the two
 * agree by construction: move responsive.css to 768px without touching
 * theme.json and CI fails.
 *
 * WHAT IT DOES NOT COVER. The theme has ten OTHER breakpoints across eleven
 * files (720px ×5, 768px, 640px, 600px ×2, 1440px, 1280px, 1200px, 899.98px,
 * 1279.98px). Those are deliberate local adjustments in feature CSS, not tiers,
 * and settings.viewport has exactly two slots — so they are out of scope here
 * BY DESIGN, not by oversight. This file pins the two canonical tiers only. If
 * the breakpoint sprawl is ever unified, this test is where the contract for
 * the survivors belongs.
 *
 * @since theme (un-versioned; landed alongside the WP 7.1 readiness work)
 */

if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) {
	http_response_code( 404 );
	exit;
}

$pass = 0;
$fail = 0;

function vp_ok( $cond, $label ) {
	global $pass, $fail;
	if ( $cond ) {
		++$pass;
		echo "  PASS: $label\n";
	} else {
		++$fail;
		echo "  FAIL: $label\n";
	}
}

function vp_eq( $expected, $actual, $label ) {
	vp_ok( $expected === $actual, $label . ' (expected ' . var_export( $expected, true ) . ', got ' . var_export( $actual, true ) . ')' );
}

// ─── Load both sides of the contract ───────────────────────────────

$theme_json_path = __DIR__ . '/../theme.json';
$css_path        = __DIR__ . '/../assets/css/responsive.css';

$theme_json = json_decode( (string) file_get_contents( $theme_json_path ), true );
if ( ! is_array( $theme_json ) ) {
	echo "FATAL: cannot read/parse theme.json at $theme_json_path\n";
	echo "Result: 0 passed, 1 failed.\n";
	exit( 1 );
}
$css = (string) file_get_contents( $css_path );
if ( '' === $css ) {
	echo "FATAL: cannot read $css_path\n";
	echo "Result: 0 passed, 1 failed.\n";
	exit( 1 );
}

echo "theme.json settings.viewport parity suite\n";

// ─── 1. The declaration exists and is shaped as core requires ──────

echo "\nTest 1: settings.viewport is declared and well-formed\n";

$viewport = $theme_json['settings']['viewport'] ?? null;
vp_ok( is_array( $viewport ), '1.1: settings.viewport is declared' );

$mobile = is_array( $viewport ) ? ( $viewport['mobile'] ?? null ) : null;
$tablet = is_array( $viewport ) ? ( $viewport['tablet'] ?? null ) : null;

// Core's schema pattern: ^(?:\d+|\d*\.\d+)(?:px|em|rem)$ — no spaces, and only
// px/em/rem. A value failing it is IGNORED and the default silently substituted,
// so a typo does not error, it reverts. That is the trap this asserts against.
$pattern = '/^(?:\d+|\d*\.\d+)(?:px|em|rem)$/';
vp_ok( is_string( $mobile ) && 1 === preg_match( $pattern, $mobile ), '1.2: mobile matches core\'s length pattern (an invalid value is silently ignored, not rejected)' );
vp_ok( is_string( $tablet ) && 1 === preg_match( $pattern, $tablet ), '1.3: tablet matches core\'s length pattern' );

// Core: "If `mobile` is greater than or equal to `tablet`, only the `mobile`
// breakpoint is used." So an inverted pair silently drops the tablet tier.
$mobile_px = (float) rtrim( (string) $mobile, 'pxemr' );
$tablet_px = (float) rtrim( (string) $tablet, 'pxemr' );
vp_ok( $mobile_px < $tablet_px, '1.4: mobile < tablet (equal-or-greater silently drops the tablet tier)' );

// Both in px, so the comparison above is apples-to-apples and the CSS
// comparison below is meaningful. A future em/rem switch must revisit this file.
vp_ok( is_string( $mobile ) && str_ends_with( $mobile, 'px' ) && is_string( $tablet ) && str_ends_with( $tablet, 'px' ), '1.5: both breakpoints are px, matching the units responsive.css uses' );

// ─── 2. Parity with responsive.css — the load-bearing assertion ────

echo "\nTest 2: the declaration matches responsive.css's actual tiers\n";

/**
 * Every max-width value that responsive.css opens a media query with.
 *
 * @return string[] e.g. ['781px', '480px']
 */
function vp_css_max_widths( $css ) {
	preg_match_all( '/@media\s*\(\s*max-width:\s*([0-9.]+px)\s*\)/i', $css, $m );
	return array_values( array_unique( $m[1] ) );
}

$css_max_widths = vp_css_max_widths( $css );
vp_ok( ! empty( $css_max_widths ), '2.1: found max-width breakpoints in responsive.css (guards against a vacuous pass if the file is restructured)' );

vp_ok( in_array( (string) $tablet, $css_max_widths, true ), '2.2: declared tablet ceiling is a real breakpoint in responsive.css' );
vp_ok( in_array( (string) $mobile, $css_max_widths, true ), '2.3: declared mobile ceiling is a real breakpoint in responsive.css' );

// The tiers are NAMED in the stylesheet's section banners. Pinning against the
// banner as well as the query means a renamed-but-unmoved tier still passes,
// while a moved tier fails — the banner is how a human finds the tier, so the
// two must not drift apart either.
vp_ok( 1 === preg_match( '/RESPONSIVE\s*—\s*TABLET\s*\(max-width:\s*' . preg_quote( (string) $tablet, '/' ) . '\)/i', $css ), '2.4: the TABLET section banner names the declared tablet ceiling' );
vp_ok( 1 === preg_match( '/RESPONSIVE\s*—\s*MOBILE\s*\(max-width:\s*' . preg_quote( (string) $mobile, '/' ) . '\)/i', $css ), '2.5: the MOBILE section banner names the declared mobile ceiling' );

// ─── 3. The 782px trap, asserted explicitly ────────────────────────

echo "\nTest 3: the declaration is not WordPress's default where that default would be false\n";

// Core's default tablet is 782px and its band is INCLUSIVE: (480px < width <= 782px).
// responsive.css stops at 781px. Declaring 782 would therefore claim "tablet" at a
// width the stylesheet treats as desktop. This asserts the trap stays shut, and
// names it, so a future editor who "fixes" 781 to match WP's convention sees why not.
vp_ok( '782px' !== (string) $tablet, '3.1: tablet is NOT 782px — core\'s inclusive upper bound would open a 1px band responsive.css does not honour' );
vp_ok( ! in_array( '782px', $css_max_widths, true ), '3.2: responsive.css does not use 782px either (so 781 remains the honest ceiling)' );

// If someone DOES migrate the stylesheet to 782px, 2.2 fails and this points at why.
vp_ok( in_array( '781px', $css_max_widths, true ), '3.3: responsive.css still uses 781px (if this fails, the stylesheet moved and theme.json must follow)' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
