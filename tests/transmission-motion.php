<?php
/**
 * Transmission-register motion (2026.10, docs/research/2026-10-16-site-wide-design-ideas.md,
 * ideas 3 and 5, picked by the owner).
 *
 * 3. CHANNEL CHANGE. Page navigation reveals the new page's content top to
 *    bottom in eight hard steps, a raster refresh, instead of the crossfade.
 *    The old page is wiped away by the complementary clip in lockstep, so no
 *    band of the screen ever shows both pages. It runs on the root snapshot,
 *    which is viewport-sized, so each step is an eighth of a screen whatever
 *    the page length. Header and footer keep their own names and stay still.
 *    Only inside prefers-reduced-motion: no-preference.
 *
 * 5. SIGNAL ACQUIRED. A text fragment (#:~:text=) is highlighted in house red,
 *    not the browser's yellow (::target-text, Baseline since December 2024),
 *    and a heading reached by #fragment gets a 2px blood rule at its left that
 *    fades in once per arrival. Color from tokens only, the fade only under
 *    no-preference.
 *
 * Run: php tests/transmission-motion.php
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

$read = static function ( $file ) {
	$css = (string) file_get_contents( dirname( __DIR__ ) . '/assets/css/' . $file );
	return preg_replace( '#/\*.*?\*/#s', '', $css );
};

/**
 * The body of the first `@media (prefers-reduced-motion: no-preference)` block
 * that contains $needle, or '' when no such block exists.
 */
function tm_no_preference_block( $css, $needle ) {
	$offset = 0;
	while ( false !== ( $at = strpos( $css, '@media (prefers-reduced-motion: no-preference)', $offset ) ) ) {
		$open  = strpos( $css, '{', $at );
		$depth = 1;
		$i     = $open + 1;
		$n     = strlen( $css );
		while ( $i < $n && $depth > 0 ) {
			if ( '{' === $css[ $i ] ) { ++$depth; } elseif ( '}' === $css[ $i ] ) { --$depth; }
			++$i;
		}
		$body = substr( $css, $open + 1, $i - $open - 2 );
		if ( false !== strpos( $body, $needle ) ) {
			return $body;
		}
		$offset = $i;
	}
	return '';
}

// Group 1: channel change (article.css).
$a  = $read( 'article.css' );
$cc = tm_no_preference_block( $a, 'sn-raster' );
ok( '' !== $cc, '3: the raster transition lives inside prefers-reduced-motion: no-preference' );
ok( 1 === preg_match( '/::view-transition-new\(root\)\s*\{\s*animation:\s*sn-raster-in\s+(\d+)ms\s+steps\(8, end\)\s+both;/', $cc, $in ), '3: the new page reveals in eight hard steps on the root snapshot (viewport-sized)' );
ok( 1 === preg_match( '/::view-transition-old\(root\)\s*\{\s*animation:\s*sn-raster-out\s+(\d+)ms\s+steps\(8, end\)\s+both;/', $cc, $out ), '3: the old page is wiped away by the same eight steps' );
ok( isset( $in[1], $out[1] ) && $in[1] === $out[1], '3: both wipes share one duration, so they move in lockstep (no double exposure)' );
ok( 1 === preg_match( '/@keyframes sn-raster-in\s*\{\s*from\s*\{\s*clip-path:\s*inset\(0 0 100% 0\);\s*\}\s*to\s*\{\s*clip-path:\s*inset\(0\);\s*\}/', $a ), '3: the new page is uncovered from the top down' );
ok( 1 === preg_match( '/@keyframes sn-raster-out\s*\{\s*from\s*\{\s*clip-path:\s*inset\(0\);\s*\}\s*to\s*\{\s*clip-path:\s*inset\(100% 0 0 0\);\s*\}/', $a ), '3: the old page is covered from the top down, the exact complement' );
ok( 0 === preg_match( '/(^|[\s}])main\s*\{[^}]*view-transition-name/', $a ), '3: main is not named, so its content rides the viewport-sized root snapshot (a named main is page-tall)' );
ok( false !== strpos( $a, '.sn-header { view-transition-name: sn-header; }' ) && false !== strpos( $a, '.sn-footer { view-transition-name: sn-footer; }' ), '3: header and footer keep their own names, so they stay still' );

// Group 2: signal acquired (base.css).
$b = $read( 'base.css' );
ok( 1 === preg_match( '/(^|[\s}])::target-text\s*\{([^}]*)\}/', $b, $m ), '5: text fragments get the house highlight' );
$tt = isset( $m[2] ) ? $m[2] : '';
ok( false !== strpos( $tt, 'var(--wp--preset--color--blood)' ) && false !== strpos( $tt, 'var(--wp--preset--color--void)' ), '5: the highlight is blood with void ink, from tokens (the ::selection pair)' );
ok( 0 === preg_match( '/#[0-9a-f]{3,8}\b|rgba?\(/i', $tt ), '5: no literal colors in the highlight (both palettes)' );
ok( 1 === preg_match( '/(^|[\s}]):is\(h2, h3, h4\)\[id\]:target::before\s*\{([^}]*)\}/', $b, $h ), '5: a heading reached by #fragment gets a rule' );
$hd = isset( $h[2] ) ? $h[2] : '';
ok( 1 === preg_match( '/width:\s*2px;/', $hd ) && false !== strpos( $hd, 'background-color: var(--wp--preset--color--blood)' ), '5: the rule is a 2px blood stroke from the token' );
ok( 1 === preg_match( '/position:\s*absolute;/', $hd ) && 1 === preg_match( '/inset-inline-start:\s*calc\(-1/', $hd ), '5: the rule is drawn in the margin, so the heading text does not move' );
ok( 0 === preg_match( '/:target\s*\{[^}]*(margin|padding|border)/', $b ), '5: the heading box itself gains no margin, padding or border' );
$ac = tm_no_preference_block( $b, 'sn-acquire' );
ok( 1 === preg_match( '/:target::before\s*\{\s*animation:\s*sn-acquire\s+[0-9.]+m?s[^;]*\b1;/', $ac ), '5: the rule fades in once per arrival, only under no-preference' );
ok( 1 === preg_match( '/@keyframes sn-acquire\s*\{\s*from\s*\{\s*opacity:\s*0;\s*\}\s*\}/', $b ), '5: the arrival is an opacity fade, nothing moves' );
ok( 1 === preg_match( '/@media \(forced-colors: active\)\s*\{\s*:is\(h2, h3, h4\)\[id\]:target::before\s*\{[^}]*CanvasText/', $b ), '5: forced colors keep the rule in CanvasText' );

echo sprintf( "\nResult: %d passed, %d failed.\n", $pass, $fail );
exit( $fail ? 1 : 0 );
