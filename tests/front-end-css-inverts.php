<?php
/**
 * Tests: no stylesheet may paint a hardcoded colour.
 *
 * Dark mode here is a TOKEN LAYER: `assets/css/critical.css` redefines the
 * `--wp--preset--color--*` custom properties under `:root[data-theme="dark"]`
 * and under `prefers-color-scheme`. Anything referencing those tokens inverts
 * for free. A HARDCODED literal cannot, by construction.
 *
 * Ported from the companion plugin (its v12.2.0), where the same sweep was cut
 * after /stats rendered its charts as solid white blocks in dark mode. THIS
 * repo is where dark mode actually lives, and it had no such sweep — the theme
 * found its own instances of this class by SCREENSHOT (the command palette,
 * v12.0.3).
 *
 * TWO EXEMPTIONS, BOTH DERIVED RATHER THAN LISTED:
 *
 * 1. CUSTOM-PROPERTY DECLARATIONS. A token definition is the one place a
 *    literal belongs — it is what every var() resolves to, and this theme OWNS
 *    the palette, so critical.css is nothing but literals by design.
 *
 * 2. PRINT STYLESHEETS. Paper is white; dark mode must not follow the reader
 *    onto it, and `color:#000` there is correct rather than tolerated. Which
 *    stylesheets those are is read from the ENQUEUE — `wp_enqueue_style()`
 *    calls whose media argument is 'print' — not from a filename list here.
 *    Deriving it means a new print stylesheet is exempt automatically and a
 *    screen stylesheet can never be exempted by being named.
 *
 *    NOT to be confused with the media='print' onload="this.media='all'" trick
 *    in the same file: that is an async-LOADING hack applied to
 *    `wp-block-library` and `trp-language-switcher` via style_loader_tag, not
 *    an enqueue argument, and it must never grant an exemption. Asserted below.
 *
 * WHAT THIS CANNOT SEE: it reads stylesheets, not rendered pages. A token used
 * in the wrong ROLE — an ink token used as a surface — passes cleanly. That is
 * the theme's own ink-as-chrome class, and it needs a real render or a
 * per-palette contrast pass.
 *
 * @since 2026-08-20
 */
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }

$pass = 0; $fail = 0;
function ok( $c, $m ) { global $pass, $fail; if ( $c ) { ++$pass; echo "PASS: $m\n"; } else { ++$fail; echo "FAIL: $m\n"; } }
echo "no stylesheet paints a hardcoded colour\n\n";

$root = realpath( __DIR__ . '/..' );

/** Colours not inside a var() fallback, an allow-listed rule, a comment, or a token definition. */
function sn_naked_colours( $css ) {
	// A fallback literal is the point of a fallback.
	$css = preg_replace( '/var\(\s*--[a-zA-Z0-9-]+\s*,[^)]*\)/', 'VAR', $css );
	// Rules a human has justified by name.
	$css = preg_replace( '#/\*[^*]*sn-allow-literal.*?\*/[^}]*\}#s', '', $css );
	// Prose paints nothing, and this file's own commentary quotes hex values.
	$css = preg_replace( '#/\*.*?\*/#s', '', $css );
	// Token DEFINITIONS: where a literal belongs.
	$css = preg_replace( '/--[a-zA-Z0-9-]+\s*:[^;}]*/', '', $css );
	preg_match_all( '/#[0-9a-fA-F]{3,8}|rgba?\([^)]*\)/', $css, $m );
	return $m[0];
}

/**
 * Stylesheets the theme enqueues with media="print", read from the enqueue.
 *
 * Matches wp_enqueue_style() calls that name a file under assets/css/ AND pass
 * 'print' as the media argument. The onload-swap trick lives in a
 * style_loader_tag filter and touches no assets/css/ path, so it cannot leak in.
 */
function sn_print_media_sheets( $php ) {
	$out = array();
	if ( preg_match_all( '/wp_enqueue_style\s*\((.*?)\);/s', $php, $calls ) ) {
		foreach ( $calls[1] as $args ) {
			if ( ! preg_match( "/'print'\s*$/", trim( $args ) ) ) { continue; }
			if ( preg_match( "#assets/css/([a-z0-9-]+\.css)#", $args, $f ) ) { $out[] = $f[1]; }
		}
	}
	return array_values( array_unique( $out ) );
}

$enqueue = (string) file_get_contents( $root . '/inc/assets-frontend.php' );
$print   = sn_print_media_sheets( $enqueue );

echo "Group: the print exemption is derived from the enqueue\n";
ok( in_array( 'print.css', $print, true ), 'print.css is discovered as a print-media stylesheet (' . implode( ', ', $print ) . ')' );
ok( 1 === count( $print ), 'exactly ONE stylesheet is print-media — if this grows, a screen sheet may have slipped into the exemption' );
ok( ! in_array( 'critical.css', $print, true ) && ! in_array( 'components.css', $print, true ), 'screen stylesheets are NOT exempt' );
ok( array() === sn_print_media_sheets( "wp_enqueue_style( 'x', get_theme_file_uri( 'assets/css/screen.css' ), array(), '1', 'all' );" ), 'NEGATIVE CONTROL: a media="all" enqueue grants no exemption' );
ok( array( 'p.css' ) === sn_print_media_sheets( "wp_enqueue_style( 'x', get_theme_file_uri( 'assets/css/p.css' ), array(), '1', 'print' );" ), 'and a genuine print enqueue IS discovered' );
// The async trick must never be read as a print stylesheet.
ok( array() === sn_print_media_sheets( "\$html = str_replace( \" media='all'\", \" media='print' onload=\\\"this.media='all'\\\"\", \$html );" ), 'NEGATIVE CONTROL: the media=print onload LOADING trick is not an enqueue and grants no exemption' );

echo "\nGroup: every screen stylesheet inverts with the palette\n";
$files = glob( $root . '/assets/css/*.css' );
ok( count( $files ) > 8, 'the sweep finds the stylesheets (guard: a glob matching nothing would pass vacuously)' );

$dirty = array();
foreach ( $files as $file ) {
	$base = basename( $file );
	if ( in_array( $base, $print, true ) ) { continue; }
	$naked = sn_naked_colours( (string) file_get_contents( $file ) );
	if ( $naked ) { $dirty[ $base ] = $naked; }
}
foreach ( $dirty as $base => $naked ) {
	echo "  -> assets/css/$base: " . implode( ' ', array_slice( array_unique( $naked ), 0, 6 ) ) . "\n";
}
ok( empty( $dirty ), sprintf( 'NO screen stylesheet paints a hardcoded colour (%d file(s) with one)', count( $dirty ) ) );

echo "\nGroup: negative controls\n";
ok( array() !== sn_naked_colours( '.x{background:#fff}' ), 'NEGATIVE CONTROL: a bare literal IS detected' );
ok( array() === sn_naked_colours( '.x{background:var(--wp--preset--color--void,#fff)}' ), 'a var() fallback is NOT flagged' );
ok( array() === sn_naked_colours( ':root{--sn-x:#12703a}' ), 'a token DEFINITION is not flagged — that is where a literal belongs' );
ok( array() !== sn_naked_colours( ':root{--sn-x:#12703a;background:#fff}' ), 'but a naked literal in the SAME rule as a definition still is' );
ok( array() === sn_naked_colours( '/* the old value was #b00303 */ .x{color:var(--y)}' ), 'a hex quoted in a COMMENT is not a paint instruction' );
ok( array() === sn_naked_colours( '.x{border:1px solid color-mix( in srgb, var(--wp--preset--color--bone) 38%, transparent )}' ), 'color-mix() on a token is tokenised — it inverts with what it points at' );
ok( array() !== sn_naked_colours( '.x{box-shadow:0 0 8px rgba(224, 4, 4, 0.2)}' ), 'NEGATIVE CONTROL: an rgba() glow of a palette colour IS detected — alpha does not make a literal a token' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
