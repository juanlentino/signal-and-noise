<?php
/**
 * Transmission-register motion (2026.10, docs/research/2026-10-16-site-wide-design-ideas.md,
 * ideas 3 and 5, picked by the owner).
 *
 * 3. CHANNEL CHANGE. Page navigation reveals the new page's content top to
 *    bottom in eight hard steps, a raster refresh, instead of the crossfade.
 *    Header and footer keep their own names and stay still. The class that
 *    opts `main` in is never set on the front page, so arriving at the home
 *    page keeps today's transition (owner rule: Page 383 is off-limits). Only
 *    inside prefers-reduced-motion: no-preference.
 *
 * 5. SIGNAL ACQUIRED. A text fragment (#:~:text=) is highlighted in house red,
 *    not the browser's yellow (::target-text, Baseline since December 2024),
 *    and a heading reached by #fragment gets a 2px blood rule at its left that
 *    fades in once per arrival. Off the front page, color from tokens only,
 *    the fade only under no-preference.
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
ok( 1 === preg_match( '/body:not\(\.home\) main\s*\{\s*view-transition-class:\s*sn-raster;\s*\}/', $cc ), '3: only main opts in, and never on the front page (body:not(.home))' );
ok( 1 === preg_match( '/::view-transition-new\(\*\.sn-raster\)\s*\{[^}]*animation:\s*sn-raster\s+\d+ms\s+steps\(8[^;]*;/', $cc ), '3: the new page reveals in eight hard steps' );
ok( 1 === preg_match( '/::view-transition-old\(\*\.sn-raster\)\s*\{\s*animation:\s*none;\s*\}/', $cc ), '3: the old page holds still under the reveal, no crossfade' );
ok( 1 === preg_match( '/@keyframes sn-raster\s*\{\s*from\s*\{\s*clip-path:\s*inset\(0 0 100% 0\);\s*\}/', $a ), '3: the reveal runs top to bottom (clip-path inset)' );
ok( 0 === preg_match( '/\.sn-(header|footer)[^{]*\{[^}]*view-transition-class/', $a ), '3: header and footer never join the raster class' );
ok( false !== strpos( $a, '.sn-header { view-transition-name: sn-header; }' ) && false !== strpos( $a, '.sn-footer { view-transition-name: sn-footer; }' ), '3: header and footer keep their own names, so they stay still' );
ok( 0 === preg_match( '/(^|[\s,}])body\.home[^{]*\{[^}]*sn-raster/', $a ), '3: no rule turns the raster on for the front page' );

// Group 2: signal acquired (base.css).
$b = $read( 'base.css' );
ok( 1 === preg_match( '/body:not\(\.home\) ::target-text\s*\{([^}]*)\}/', $b, $m ), '5: text fragments get a house highlight, off the front page' );
$tt = isset( $m[1] ) ? $m[1] : '';
ok( false !== strpos( $tt, 'var(--wp--preset--color--blood)' ) && false !== strpos( $tt, 'var(--wp--preset--color--void)' ), '5: the highlight is blood with void ink, from tokens (the ::selection pair)' );
ok( 0 === preg_match( '/#[0-9a-f]{3,8}\b|rgba?\(/i', $tt ), '5: no literal colors in the highlight (both palettes)' );
ok( 1 === preg_match( '/body:not\(\.home\) :is\(h2, h3, h4\)\[id\]:target::before\s*\{([^}]*)\}/', $b, $h ), '5: a heading reached by #fragment gets a rule, off the front page' );
$hd = isset( $h[1] ) ? $h[1] : '';
ok( 1 === preg_match( '/width:\s*2px;/', $hd ) && false !== strpos( $hd, 'background-color: var(--wp--preset--color--blood)' ), '5: the rule is a 2px blood stroke from the token' );
ok( 1 === preg_match( '/position:\s*absolute;/', $hd ) && 1 === preg_match( '/inset-inline-start:\s*calc\(-1/', $hd ), '5: the rule is drawn in the margin, so the heading text does not move' );
ok( 0 === preg_match( '/:target\s*\{[^}]*(margin|padding|border)/', $b ), '5: the heading box itself gains no margin, padding or border' );
$ac = tm_no_preference_block( $b, 'sn-acquire' );
ok( 1 === preg_match( '/:target::before\s*\{\s*animation:\s*sn-acquire\s+[0-9.]+m?s[^;]*\b1;/', $ac ), '5: the rule fades in once per arrival, only under no-preference' );
ok( 1 === preg_match( '/@keyframes sn-acquire\s*\{\s*from\s*\{\s*opacity:\s*0;\s*\}\s*\}/', $b ), '5: the arrival is an opacity fade, nothing moves' );
ok( 1 === preg_match( '/@media \(forced-colors: active\)\s*\{\s*body:not\(\.home\) :is\(h2, h3, h4\)\[id\]:target::before\s*\{[^}]*CanvasText/', $b ), '5: forced colors keep the rule in CanvasText' );

echo sprintf( "\nResult: %d passed, %d failed.\n", $pass, $fail );
exit( $fail ? 1 : 0 );
