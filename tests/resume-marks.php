<?php
/**
 * Guard: /resume's edition marks, theme side (2026-11-02). The plugin marks
 * what only one lane carries (.sn-resume-only, with a screen-reader
 * .sn-resume-only-mark), adds a hunk band where the other lane has a section
 * this one lacks, a legend under the switch and a formats line at each lane's
 * end. The theme draws the bone bar, the hunk row and the legend, keeps all of
 * it off paper, and lets the blood arrival rule draw where a hunk lands: the
 * essay rule that turned it off at 1200px+ is scoped to essays.
 *
 * Run: php tests/resume-marks.php
 */
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }

$pass = 0; $fail = 0;
function ok( $c, $m ) { global $pass, $fail; if ( $c ) { $pass++; echo "PASS: $m\n"; } else { $fail++; echo "FAIL: $m\n"; } }

require_once __DIR__ . '/lib/css-cascade.php'; // sn_css_specificity()

$root  = dirname( __DIR__ );
$strip = static fn( $s ) => (string) preg_replace( '#/\*.*?\*/#s', '', (string) $s );
$css   = $strip( file_get_contents( "$root/assets/css/resume-switch.css" ) );
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

echo "The bar\n";
$entry = $rule( $css, '.sn-resume-variant .sn-resume-only' );
$bar   = $rule( $css, '.sn-resume-variant .sn-resume-only::before' );
ok( null !== $entry && 1 === preg_match( '/position:\s*relative/', $entry ) && 0 === preg_match( '/padding|margin/', $entry ), 'a marked entry is the bar\'s containing block and nothing more: no padding or margin, so no entry indents' );
ok( null !== $bar && 1 === preg_match( '/content:\s*""/', $bar ) && 1 === preg_match( '/position:\s*absolute/', $bar ) && 1 === preg_match( '/inset-block:\s*0/', $bar ), 'the bar is a positioned pseudo-element the full height of the entry' );
ok( null !== $bar && 1 === preg_match( '/inset-inline-start:\s*calc\(-1 \* \(2px \+ 0\.6em\)\);/', $bar ) && 1 === preg_match( '/width:\s*2px/', $bar ), 'the bar sits where base.css\'s arrival rule sits: 2px wide, 0.6em into the margin' );
ok( null !== $bar && 1 === preg_match( '/background:\s*var\(--wp--preset--color--bone\)/', $bar ) && false === strpos( $bar, 'blood' ), 'the bar is bone, never blood: blood is the arrival rule' );

echo "\nThe legend, the hunk row, the formats line\n";
$tier = $rule( $css, '.sn-resume-legend,.sn-resume-hunk,.sn-resume-formats' );
ok( null !== $tier && 1 === preg_match( '/font-size:\s*max\(0\.7rem, 11px\)/', $tier ) && 1 === preg_match( '/text-transform:\s*uppercase/', $tier ) && 1 === preg_match( '/letter-spacing:\s*0\.14em/', $tier ) && 1 === preg_match( '/color:\s*var\(--wp--preset--color--rust\)/', $tier ), 'legend, hunk and formats line speak in the caption\'s tier: mono, 11px floor, uppercase, .14em, rust' );
$sample = $rule( $css, '.sn-resume-legend::before' );
ok( null !== $sample && 1 === preg_match( '/content:\s*""/', $sample ) && 1 === preg_match( '/width:\s*2px/', $sample ) && 1 === preg_match( '/background:\s*var\(--wp--preset--color--bone\)/', $sample ), 'the legend draws a 2px bone sample of the bar it explains' );
$legend = $rule( $css, '.sn-resume-legend' );
ok( null !== $legend && 1 === preg_match( '/flex-basis:\s*100%/', $legend ) && 1 === preg_match( '/display:\s*flex/', $legend ), 'the legend takes its own line, after the switch\'s nav or inside it' );
$hunk = $rule( $css, '.sn-resume-hunk' );
ok( null !== $hunk && 1 === preg_match( '/min-height:\s*44px/', $hunk ), 'a hunk row is 44px tall at least' );
ok( null !== $hunk && 1 === preg_match( '/border-top:\s*1px solid color-mix\(\s*in srgb,\s*var\(--wp--preset--color--bone\) 38%,\s*transparent\s*\)/', $hunk ) && 0 === preg_match( '/border(-bottom)?:/', $hunk ), 'a hunk row has the switch\'s hairline above only; the next band\'s rule closes it' );
$link = $rule( $css, '.sn-resume-hunk a' );
ok( null !== $link && 1 === preg_match( '/color:\s*var\(--wp--preset--color--bone\)/', $link ), 'a hunk link is bone' );
$focus = $rule( $css, '.sn-resume-hunk a:focus-visible' );
ok( null !== $focus && 1 === preg_match( '/background:\s*var\(--wp--preset--color--blood\)/', $focus ) && 1 === preg_match( '/outline:\s*2px solid/', $focus ), 'a focused hunk link fills blood with a ring, as the switch does' );
ok( 1 === substr_count( $css, '.sn-resume-hunk a:hover' ) && 1 === preg_match( '/@media \(hover: hover\)\s*\{\s*\.sn-resume-hunk a:hover\s*\{[^}]*background:\s*var\(--wp--preset--color--blood\)/', $css ), 'the blood hover fill lives only inside @media (hover: hover), so a tap never leaves it stuck' );
ok( 1 === preg_match( '/,\s*\.sn-resume-hunk a\s*\{\s*transition:\s*none/', $block( $css, '@media (prefers-reduced-motion: reduce)' ) ), 'a hunk link\'s transition stops under reduced motion, in the switch\'s own pairing' );
ok( null !== $rule( $css, '.sn-resume-formats a' ) && 1 === preg_match( '/color:\s*var\(--wp--preset--color--bone\)/', (string) $rule( $css, '.sn-resume-formats a' ) ), 'the formats line\'s links are bone' );
ok( 0 === preg_match( '/#[0-9a-fA-F]{3,8}\b|rgba?\(|hsla?\(/', $css ), 'colors are preset tokens only' );

echo "\nForced colors\n";
$fc = (string) $rule( $block( $css, '@media (forced-colors: active)' ), '.sn-resume-variant .sn-resume-only::before,.sn-resume-legend::before' );
ok( 1 === preg_match( '/forced-color-adjust:\s*none/', $fc ) && 1 === preg_match( '/background:\s*CanvasText/', $fc ), 'under forced colors the bar and the legend\'s sample draw in CanvasText instead of vanishing with their background' );

echo "\nPaper\n";
$hidden = 1 === preg_match( '/(?:^|\})\s*([^{}]*\.sn-resume-switch,[^{}]*)\{\s*display:\s*none !important;\s*\}/', $print, $m ) ? array_map( 'trim', explode( ',', $m[1] ) ) : array();
foreach ( array( '.sn-resume-hunk-band', '.sn-resume-hunk', '.sn-resume-legend', '.sn-resume-only-mark', '.sn-resume-formats' ) as $sel ) {
	ok( in_array( $sel, $hidden, true ), "paper drops $sel (the browser-printed text layer stays clean)" );
}
$screen_sel = '.sn-resume-variant .sn-resume-only::before';
$paper_sel  = null;
if ( preg_match_all( '/(?:^|[{}])\s*([^{}]*\.sn-resume-only::before)\s*\{\s*content:\s*none;?\s*\}/', $print, $pm ) ) {
	$paper_sel = trim( end( $pm[1] ) );
}
ok( null !== $paper_sel && sn_css_specificity( $paper_sel ) > sn_css_specificity( $screen_sel ), 'paper drops the bar with a selector that outranks the screen one: resume-switch.css loads after print.css, so a tie would print the bar' );

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
	ok( 0 === strpos( $o[1], $essay ), "{$o[0]}: \"{$o[1]}\" turns the arrival rule off on essays only, so a hunk landing on /resume draws it at 1200px+" );
}

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
