<?php
/**
 * Two transmission-register details (2026.10, docs/research/2026-10-16-site-wide-design-ideas.md):
 *
 * 1. TUNED CHANNEL. inc/nav-current.php marks the header link for the page you
 *    are on with aria-current="page"; before this, nothing showed it on screen.
 *    The nav's blood hover rule now stays drawn on that link. It has to live in
 *    BOTH critical.css (first paint) and layout.css (the deferred twin), and it
 *    needs a forced-colors fallback or the bar paints to the page color.
 *
 * 2. TEST CARD 404. The decorative digits sit on a calibration strip built only
 *    from palette tokens (so dark mode recalibrates it), flicker once with the
 *    existing glitchFlicker keyframes, and only when the reader allows motion.
 *
 * Run: php tests/tuned-channel-test-card.php
 */

if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) {
	http_response_code( 404 );
	exit;
}

$pass = 0;
$fail = 0;
function ok( $c, $m ) {
	global $pass, $fail;
	if ( $c ) { ++$pass; echo "PASS: $m\n"; } else { ++$fail; echo "FAIL: $m\n"; }
}

$css = static function ( $file ) {
	return (string) file_get_contents( dirname( __DIR__ ) . '/assets/css/' . $file );
};

// Group 1: the tuned channel, in both sheets.
$current = '/\.wp-block-navigation a\[aria-current="page"\]::after\s*\{\s*width:\s*100%;\s*\}/';
$forced  = '/@media \(forced-colors: active\)\s*\{\s*\.wp-block-navigation a::after\s*\{[^}]*forced-color-adjust:\s*none;[^}]*background-color:\s*CanvasText;/';
foreach ( array( 'critical.css', 'layout.css' ) as $sheet ) {
	$s = $css( $sheet );
	ok( 1 === preg_match( $current, $s ), "$sheet: the aria-current link keeps the full-width rule" );
	ok( 1 === preg_match( $forced, $s ), "$sheet: forced colors paint the rule in CanvasText" );
	ok( false === strpos( preg_replace( '/\/\*.*?\*\//s', '', $s ), 'body.home .wp-block-navigation' ), "$sheet: no home-page selector (the home page is never marked)" );
}

// Group 2: the test card.
$c = $css( 'components.css' );
ok( 1 === preg_match( '/\.sn-404-digits::before\s*\{(.*?)\n\}/s', $c, $m ), 'the 404 digits carry a ::before strip' );
$strip = isset( $m[1] ) ? $m[1] : '';
foreach ( array( 'void', 'asphalt', 'concrete', 'rust', 'bone', 'blood', 'signal' ) as $slug ) {
	ok( false !== strpos( $strip, "var(--wp--preset--color--$slug)" ), "the strip includes the $slug swatch as a token" );
}
ok( 0 === preg_match( '/#[0-9a-f]{3,8}\b|rgba?\(/i', $strip ), 'no literal colors in the strip (dark mode must recalibrate it)' );
ok(
	1 === preg_match( '/@media \(prefers-reduced-motion: no-preference\)\s*\{\s*\.sn-404-digits\s*\{\s*animation:\s*glitchFlicker[^;]*\b1;/', $c ),
	'the flicker runs once and only inside prefers-reduced-motion: no-preference'
);
ok( 1 === preg_match( '/@keyframes glitchFlicker\s*\{/', $css( 'base.css' ) ), 'the glitchFlicker keyframes it reuses still exist in base.css' );
ok( 1 === preg_match( '/@media \(forced-colors: active\)\s*\{\s*\.sn-404-digits::before\s*\{\s*display:\s*none;/', $c ), 'forced colors drop the strip' );

$tpl = (string) file_get_contents( dirname( __DIR__ ) . '/templates/404.html' );
ok( false !== strpos( $tpl, 'class="sn-404-digits has-concrete-color' ), 'the template still carries the classes the card selects on' );

echo sprintf( "\nResult: %d passed, %d failed.\n", $pass, $fail );
exit( $fail ? 1 : 0 );
