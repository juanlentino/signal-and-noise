<?php
/**
 * Standalone test: four site-wide design details from the 2026-10-16 research
 * (docs/research/2026-10-16-site-wide-design-ideas.md, ideas 4, 7, 8 and 9),
 * built after the owner approved the rendered mockups on 2026-10-17.
 *
 *   4. Hairline-true display type: Bebas display heads trim to cap height and
 *      baseline, so they sit on the rule above and below them.
 *   7. Noise floor: the film grain settles to half strength over the first
 *      screen of scrolling.
 *   8. Crop-mark focus: a focused button shows four blood corner ticks
 *      outside the existing outline.
 *   9. Signal lock: a prose link's underline tunes in on hover and focus.
 *      At rest the link is unchanged (color only, the settled design).
 *
 * Run: php tests/signal-detail.php
 */

if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }

$root = dirname( __DIR__ );
$css  = static function ( $f ) use ( $root ) {
	// Comments stripped so a selector named in a comment never satisfies a pin.
	return (string) preg_replace( '#/\*.*?\*/#s', '', (string) file_get_contents( $root . '/assets/css/' . $f ) );
};
$crit    = $css( 'critical.css' );
$base    = $css( 'base.css' );
$article = $css( 'article.css' );

$pass = 0; $fail = 0;
function ok( $c, $m ) { global $pass, $fail; if ( $c ) { ++$pass; echo "PASS: $m\n"; } else { ++$fail; echo "FAIL: $m\n"; } }

echo "Group 4: hairline-true display type\n";
ok( 1 === preg_match( '/@supports\s*\(\s*text-box:\s*trim-both cap alphabetic\s*\)\s*\{[^{}]*\.sn-note-title[^{}]*\{[^}]*text-box:\s*trim-both cap alphabetic/s', $crit ), 'critical.css trims the note title, inside @supports so other browsers keep today\'s spacing' );
ok( 1 === preg_match( '/@supports\s*\(\s*text-box:[^)]*\)\s*\{[^{}]*:is\(h1,\s*h2\)\.wp-block-heading:not\(\.sn-hero-title\)/s', $crit ), '...and page h1/h2 display heads, never the home hero title (settled design)' );

echo "\nGroup 7: noise floor\n";
ok( 1 === preg_match( '/@supports\s*\(\s*animation-timeline:\s*scroll\(\)\s*\)\s*\{\s*@media\s*\(prefers-reduced-motion:\s*no-preference\)\s*\{\s*body::before\s*\{[^}]*animation:\s*sn-noise-floor[^}]*animation-timeline:\s*scroll\(root\)[^}]*animation-range:\s*0\s+100vh/s', $crit ), 'the grain settles on the root scroll timeline over the first screen, only with no-preference motion and only where scroll timelines exist' );
ok( 1 === preg_match( '/@keyframes\s+sn-noise-floor\s*\{\s*to\s*\{\s*filter:\s*opacity\(0\.5\)/s', $crit ), 'it halves the grain with filter: opacity(), so it scales whatever opacity a breakpoint sets (0.035 desktop, 0.015 phone)' );
ok( 0 === preg_match( '/@keyframes\s+sn-noise-floor\s*\{[^}]*\{[^}]*\bopacity\s*:/s', $crit ), '...and never animates opacity itself, which would overwrite the phone value' );

echo "\nGroup 8: crop-mark focus\n";
$sel = '.wp-block-button:has(> .wp-block-button__link:focus-visible)';
ok( false !== strpos( $base, $sel . '::before' ), 'the ticks hang on the button wrapper: the link itself clips with overflow: hidden' );
ok( 8 === preg_match_all( '/linear-gradient\(var\(--sn-crop\),\s*var\(--sn-crop\)\)\s*(top|bottom)\s+(left|right)/', $base ), 'four corners, two strokes each' );
ok( 1 === preg_match( '/--sn-crop:\s*var\(--wp--preset--color--blood\)/', $base ), 'drawn in the blood token, so dark mode recalibrates them' );
ok( 1 === preg_match( '/@media\s*\(forced-colors:\s*active\)\s*\{[^@]*' . preg_quote( $sel, '/' ) . '::before\s*\{[^}]*display:\s*none/s', $base ), 'forced colors drop the ticks and keep the Highlight outline' );
ok( 1 === preg_match( '/a:focus-visible,[^{]*\.wp-block-button__link:focus-visible[^{]*\{[^}]*outline:\s*2px solid var\(--wp--preset--color--blood\)/s', $base ), 'the existing 2px focus outline is untouched: the ticks are additive' );

echo "\nGroup 9: signal lock links\n";
$lnk = '.wp-block-post-content p:not(.has-rust-color, .wp-block-signal-noise-sidenote) a:not(.wp-element-button, sup a)';
ok( false !== strpos( $article, $lnk . ' {' ), 'scoped to prose links, never rust paragraphs or sidenotes (underlined at rest already) or footnote markers' );
ok( 1 === preg_match( '/' . preg_quote( $lnk, '/' ) . '\s*\{[^}]*text-decoration-color:\s*transparent/s', $article ), 'at rest the underline is drawn transparent: the link looks exactly as it does today' );
ok( 1 === preg_match( '/' . preg_quote( $lnk, '/' ) . ':focus-visible\s*\{[^}]*text-decoration-color:\s*currentColor[^}]*text-underline-offset:\s*3px/s', $article ), 'keyboard focus locks it in at the house 3px offset' );
ok( 1 === preg_match( '/@media\s*\(hover:\s*hover\)\s*\{\s*' . preg_quote( $lnk, '/' ) . ':hover\s*\{[^}]*text-decoration-color:\s*currentColor[^}]*text-underline-offset:\s*3px/s', $article ), '...and so does hover, only where the pointer hovers (no sticky tap on touch)' );
ok( 1 === preg_match( '/' . preg_quote( $lnk, '/' ) . '\s*\{[^}]*transition:[^}]*steps\(3/s', $article ), 'it tunes in over three hard steps, not a glide' );

echo "\n$pass passed, $fail failed\n";
exit( $fail > 0 ? 1 : 0 );
