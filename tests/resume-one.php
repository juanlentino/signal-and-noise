<?php
/**
 * Guard: /resume is one résumé, theme side (2026-11-12). The plugin no longer
 * emits a lane switch, hidden lanes, edition marks or hunks, so the theme
 * carries none of their rules. What survives (the hero's headline and subhead,
 * receipts, "What I've Built", the formats line) lives in resume.css with its
 * declarations unchanged, and keeps the specificity it needs without the lane
 * wrapper that used to scope it.
 *
 * Run: php tests/resume-one.php
 */
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }

$pass = 0; $fail = 0;
function ok( $c, $m ) { global $pass, $fail; if ( $c ) { $pass++; echo "PASS: $m\n"; } else { $fail++; echo "FAIL: $m\n"; } }

// Specificity per Selectors 4, from the shared resolver.
require_once __DIR__ . '/lib/css-cascade.php';

$root  = dirname( __DIR__ );
$strip = static fn( $s ) => (string) preg_replace( '#/\*.*?\*/#s', '', (string) $s );
$css   = $strip( file_get_contents( "$root/assets/css/resume.css" ) );
$print = $strip( file_get_contents( "$root/assets/css/print.css" ) );

// The body of the rule whose selector list is exactly $sel (any spacing after
// a comma), or null.
$rule = static function ( $src, $sel ) {
	$re = str_replace( ',', ',\s*', preg_quote( $sel, '/' ) );
	return 1 === preg_match( '/(?:^|[{}])\s*' . $re . '\s*\{([^}]*)\}/', $src, $m ) ? $m[1] : null;
};
// The text of the first @media block opened by $head, braces balanced.
$block = static function ( $src, $head ) {
	$at = strpos( $src, $head );
	if ( false === $at ) { return ''; }
	$open = strpos( $src, '{', $at );
	for ( $i = $open, $d = 0, $n = strlen( $src ); $i < $n; $i++ ) {
		$d += ( '{' === $src[ $i ] ) - ( '}' === $src[ $i ] );
		if ( 0 === $d ) { return substr( $src, $open + 1, $i - $open - 1 ); }
	}
	return '';
};

echo "No lane rules left\n";
ok( ! file_exists( "$root/assets/css/resume-switch.css" ) && ! file_exists( "$root/assets/css/resume-marks.css" ), 'resume-switch.css and resume-marks.css are gone' );
$all = '';
foreach ( glob( "$root/assets/css/*.css" ) as $f ) { $all .= $strip( file_get_contents( $f ) ); }
ok( 0 === preg_match( '/\.sn-resume-(switch|variant|only|legend|hunk|head-split)\b|data-lane/', $all ), 'no sheet styles the switch, a lane, an edition mark, the legend, a hunk or the head split' );
ok( '' !== $all && false !== strpos( $all, '.sn-resume-lane' ), 'the role-timeline lanes (.sn-resume-lane, unrelated) are still styled, so the check above read real sheets' );

echo "\nThe hero's headline and subhead\n";
$head = $rule( $css, '.sn-resume-headline' );
ok( null !== $head && 1 === preg_match( '/font-size:\s*clamp\(1\.75rem, 3\.5vw, 2\.6rem\)/', $head ) && 1 === preg_match( '/color:\s*var\(--wp--preset--color--bone\)/', $head ), 'the headline is the Bebas step between the role heads and the section h2s, bone' );
$box_sel = '.sn-resume-hero-split .sn-resume-headline.wp-block-heading';
$box     = $rule( $css, $box_sel );
$crit    = $strip( file_get_contents( "$root/assets/css/critical.css" ) );
$trim    = 'main :is(h1, h2).wp-block-heading:not(.sn-hero-title)';
ok( false !== strpos( $crit, $trim ), 'critical.css still trims h2.wp-block-heading to cap height (or the next check would pass on nothing)' );
ok( null !== $box && 1 === preg_match( '/text-box:\s*normal/', $box ) && sn_css_specificity( $box_sel ) > sn_css_specificity( $trim ), 'an h2 headline keeps its box: the hero-split scope (' . implode( ',', sn_css_specificity( $box_sel ) ) . ') outranks critical.css\'s trim (' . implode( ',', sn_css_specificity( $trim ) ) . ')' );
ok( null !== $rule( $css, '.sn-resume-tagline' ) && 1 === preg_match( '/margin:\s*0\.6rem 0 0/', (string) $rule( $css, '.sn-resume-tagline' ) ), 'the subhead sits 0.6rem under the headline' );
ok( 1 === preg_match( '/body:has\(\.sn-resume-hero-split\) main \.sn-resume-headline\s*\{[^}]*font-size:\s*8\.5pt !important;[^}]*border-bottom:\s*0;[^}]*text-transform:\s*none;/', $print ), 'on paper the headline prints as body text, not as a ruled section head' );

echo "\nReceipts\n";
$receipt = $rule( $css, '.sn-resume-receipt,.sn-resume-pub-title a.sn-resume-receipt' );
ok( null !== $receipt && 1 === preg_match( '/padding:\s*0\.3em 0\.45em;/', $receipt ), 'a receipt is about a 24px target' );
ok( null !== $receipt && 1 === preg_match( '/border:\s*1px solid color-mix\(\s*in srgb,\s*var\(--wp--preset--color--rust\) 50%,\s*transparent\s*\);/', $receipt ), 'a receipt\'s border is half rust in both themes' );
ok( 1 === preg_match( '/\.sn-resume-pub-title a\.sn-resume-receipt::after\s*\{\s*content:\s*none/', $css ), 'a receipt inside a card title drops the title link\'s arrow' );
$in_p    = '.wp-block-post-content .wp-block-group p a.sn-resume-receipt:not(.wp-element-button)';
$in_p_r  = $rule( $css, $in_p );
$article = $strip( file_get_contents( "$root/assets/css/article.css" ) );
$prose   = 1 === preg_match( '/(?:^|\})\s*([^{}]*a:not\(\.wp-element-button, sup a\))\s*\{[^}]*text-decoration-line:\s*underline/', $article, $am ) ? trim( $am[1] ) : null;
$states  = preg_match_all( '/(?:^|[{}])\s*([^{}]*a:not\(\.wp-element-button, sup a\):(?:hover|focus-visible))\s*\{([^}]*)\}/', $article, $sm ) ? $sm[2] : array();
ok( null !== $in_p_r && 1 === preg_match( '/text-decoration:\s*none/', $in_p_r ) && 1 === preg_match( '/transition:\s*color 0\.15s ease, border-color 0\.15s ease;/', $in_p_r ) && 0 === preg_match( '/(^|;)\s*color:/', $in_p_r ), 'a receipt in a paragraph keeps the line off and its own fade, and leaves color to its hover and focus rules' );
ok( null !== $prose && sn_css_specificity( $in_p ) > sn_css_specificity( $prose ), 'it outranks article.css\'s prose link (' . ( $prose ? implode( ',', sn_css_specificity( $prose ) ) : 'not found' ) . '), so no underline draws inside a receipt\'s box' );
ok( 2 === count( $states ) && 0 === preg_match( '/text-decoration(-line)?:/', implode( ';', $states ) ), 'the prose link\'s hover and focus rules only recolor and thicken the line, so the line kept off at rest stays off' );
$rail_sel = '.sn-resume-rail a.sn-resume-receipt:not(.wp-element-button)';
ok( null !== $rule( $css, $rail_sel ) && sn_css_specificity( $rail_sel ) > sn_css_specificity( '.sn-resume-rail a:not(.wp-element-button)' ) && 1 === preg_match( '/(?:^|\})\s*[^{}]*\.sn-resume-rail a:not\(\.wp-element-button\)\s*\{[^}]*text-decoration-line:\s*underline/', $crit ), 'a receipt on a rail outranks critical.css\'s rail underline' );
$reduce = $block( substr( $css, (int) strrpos( $css, '@media (prefers-reduced-motion: reduce)' ) ), '@media (prefers-reduced-motion: reduce)' );
ok( '' !== $reduce && false !== strpos( $reduce, $in_p ) && 1 === preg_match( '/transition:\s*none/', $reduce ), 'both receipt fades stop under reduced motion, the paragraph one at its own specificity' );

echo "\nWhat I've Built, the formats line\n";
ok( 1 === preg_match( '/\}\s*\.sn-resume-built-grid \{\s*margin-top: 1rem;\s*\}\s*@media \(min-width: 1280px\)/', $css ), 'the intro-to-first-card gap is 1rem at every width, set outside the three-across query' );
ok( 1 === preg_match( '/@media \(min-width: 1280px\)\s*\{\s*\.sn-resume-built-grid\s*\{[^}]*display:\s*grid;[^}]*grid-template-columns:\s*repeat\(3, minmax\(0, 1fr\)\);[^}]*\}\s*\.sn-resume-built-grid > \.sn-resume-built\s*\{\s*margin:\s*0;/', $css ), '"What I\'ve Built" sits three across from 1280px, stacked below' );
$tier = $rule( $css, '.sn-resume-formats' );
ok( null !== $tier && 1 === preg_match( '/font-size:\s*max\(0\.7rem, 11px\)/', $tier ) && 1 === preg_match( '/letter-spacing:\s*0\.14em/', $tier ) && 1 === preg_match( '/color:\s*var\(--wp--preset--color--rust\)/', $tier ), 'the formats line speaks in the caption\'s tier: mono, 11px floor, .14em, rust' );
ok( 1 === preg_match( '/color:\s*var\(--wp--preset--color--bone\)/', (string) $rule( $css, '.sn-resume-formats a' ) ), 'the formats line\'s links are bone' );
ok( 1 === preg_match( '/\.sn-resume-formats,\s*\.sn-resume-receipt,\s*\.sn-resume-download\s*\{\s*display:\s*none !important/', $print ), 'paper drops the formats line, the receipts and the Download' );
ok( 1 === preg_match( '/body:has\(\.sn-resume-hero-split\) \.sn-resume-rail a::after\s*\{\s*content:\s*""/', $print ), 'paper does not spell a contact-rail link\'s URL after its text' );

echo "\nThe arrival rule, on every page but an essay\n";
require_once __DIR__ . '/lib/components-source.php';
$sheets = array( 'components layer' => $strip( snt_components_source() ) );
foreach ( array_merge( glob( "$root/assets/css/*.css" ), glob( "$root/assets/css/blocks/*.css" ) ) as $f ) {
	if ( false === strpos( $f, '/components-' ) && 'print.css' !== basename( $f ) ) {
		$sheets[ basename( $f ) ] = $strip( file_get_contents( $f ) );
	}
}
$offs = array();
foreach ( $sheets as $name => $src ) {
	if ( preg_match_all( '/(?:^|[{}])\s*([^{}]*:target::before)\s*\{\s*content:\s*none;?\s*\}/', $src, $tm ) ) {
		foreach ( $tm[1] as $sel ) { $offs[] = array( $name, trim( $sel ) ); }
	}
}
$essay = 'main:has(> .wp-block-post-content > .sn-provenance-section) ';
ok( count( $offs ) > 0, 'the essay rule that stands the arrival rule down is still there (or this check would pass on nothing)' );
foreach ( $offs as $o ) {
	ok( 0 === strpos( $o[1], $essay ), "{$o[0]}: \"{$o[1]}\" turns the arrival rule off on essays only, so a #resume-experience link draws it on /resume" );
}

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
