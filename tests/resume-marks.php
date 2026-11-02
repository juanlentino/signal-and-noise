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

// Specificity as (ids, classes, elements), per Selectors 4: :is, :not and :has
// count their most specific argument, :where counts nothing, a pseudo-element
// is an element. tests/lib/css-cascade.php counts :not(...) and its argument
// both, which misranks the rules compared here.
function snm_spec( $sel ) {
	$s = array( 0, 0, 0 );
	while ( preg_match( '/:(is|not|has|where)\(/', $sel, $m, PREG_OFFSET_CAPTURE ) ) {
		$open = $m[0][1] + strlen( $m[0][0] );
		for ( $i = $open, $d = 1; $d > 0; $i++ ) { $d += ( '(' === $sel[ $i ] ) - ( ')' === $sel[ $i ] ); }
		if ( 'where' !== $m[1][0] ) {
			$best = array( 0, 0, 0 );
			foreach ( preg_split( '/,(?![^(]*\))/', substr( $sel, $open, $i - 1 - $open ) ) as $arg ) { $best = max( $best, snm_spec( $arg ) ); }
			$s = array( $s[0] + $best[0], $s[1] + $best[1], $s[2] + $best[2] );
		}
		$sel = substr( $sel, 0, $m[0][1] ) . ' ' . substr( $sel, $i );
	}
	$s[2] += preg_match_all( '/::[\w-]+/', $sel );
	$sel   = (string) preg_replace( '/::[\w-]+/', '', $sel );
	$s[0] += preg_match_all( '/#[\w-]+/', $sel );
	$s[1] += preg_match_all( '/\.[\w-]+|\[[^\]]*\]|:[\w-]+/', $sel );
	$s[2] += preg_match_all( '/(?:^|[\s>+~])[a-z][\w-]*/', $sel );
	return $s;
}

$root  = dirname( __DIR__ );
$strip = static fn( $s ) => (string) preg_replace( '#/\*.*?\*/#s', '', (string) $s );
$css   = $strip( file_get_contents( "$root/assets/css/resume-marks.css" ) );
$sw    = $strip( file_get_contents( "$root/assets/css/resume-switch.css" ) );
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

echo "The file\n";
ok( '' !== $css && 0 === preg_match( '/\.sn-resume-(only|legend|hunk|formats)\b/', $sw ), 'the edition marks live in resume-marks.css alone; resume-switch.css carries none of them' );
$cms = (string) file_get_contents( "$root/inc/cms-page-styles.php" );
ok( 1 === preg_match( "/wp_enqueue_style\(\s*'sn-resume-marks',\s*get_theme_file_uri\( 'assets\/css\/resume-marks\.css' \),\s*array\( 'sn-resume-switch' \),/", $cms ), 'resume-marks.css loads after resume-switch.css (its dependency), so the receipt\'s base rules sit beneath its resets; tests/cms-page-styles.php drives the enqueue' );

echo "\nThe bar\n";
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
ok( null !== $rule( $css, '.sn-resume-formats a' ) && 1 === preg_match( '/color:\s*var\(--wp--preset--color--bone\)/', (string) $rule( $css, '.sn-resume-formats a' ) ), 'the formats line\'s links are bone' );
ok( 0 === preg_match( '/#[0-9a-fA-F]{3,8}\b|rgba?\(|hsla?\(/', $css ), 'colors are preset tokens only' );

echo "\nA receipt and a hunk link inside a paragraph keep their own look\n";
$receipt_sel = '.wp-block-post-content .sn-resume-variant p a.sn-resume-receipt:not(.wp-element-button)';
$hunk_sel    = '.wp-block-post-content .sn-resume-variant p.sn-resume-hunk a:not(.wp-element-button)';
$receipt_r   = $rule( $css, $receipt_sel );
$hunk_r      = $rule( $css, $hunk_sel );
$own_only    = static fn( $b, $fade ) => null !== $b && 1 === preg_match( '/text-decoration:\s*none/', $b ) && 1 === preg_match( '/transition:\s*' . preg_quote( $fade, '/' ) . ';/', $b ) && 0 === preg_match( '/(^|;)\s*color:/', $b );
ok( $own_only( $receipt_r, 'color 0.15s ease, border-color 0.15s ease' ), 'a receipt in a paragraph keeps the line off and its own color and border fade, and leaves color to its hover and focus rules' );
ok( $own_only( $hunk_r, 'color 0.15s ease, background-color 0.15s ease' ), 'a hunk link keeps the line off and its own fill fade, and leaves color to its hover and focus rules' );
$article = $strip( file_get_contents( "$root/assets/css/article.css" ) );
$prose   = 1 === preg_match( '/(?:^|\})\s*([^{}]*a:not\(\.wp-element-button, sup a\))\s*\{[^}]*text-decoration-line:\s*underline/', $article, $am ) ? trim( $am[1] ) : null;
ok( null !== $prose && snm_spec( $receipt_sel ) > snm_spec( $prose ) && snm_spec( $hunk_sel ) > snm_spec( $prose ), 'both outrank article.css\'s prose link (' . ( $prose ? implode( ',', snm_spec( $prose ) ) : 'not found' ) . '), so its underline never draws inside a receipt\'s box' );
$states = preg_match_all( '/(?:^|[{}])\s*([^{}]*a:not\(\.wp-element-button, sup a\):(?:hover|focus-visible))\s*\{([^}]*)\}/', $article, $sm ) ? $sm[2] : array();
ok( 2 === count( $states ) && 0 === preg_match( '/text-decoration(-line)?:/', implode( ';', $states ) ), 'the prose link\'s hover and focus rules (0,4,2) only recolor and thicken the line, so the line kept off at rest stays off' );
$critical = $strip( file_get_contents( "$root/assets/css/critical.css" ) );
$rail     = 1 === preg_match( '/(?:^|\})\s*([^{}]*\.sn-resume-rail a[^{}]*)\{[^}]*text-decoration-line:\s*underline/', $critical, $cm ) ? array_map( 'trim', explode( ',', $cm[1] ) ) : array();
$rail_max = array( 0, 0, 0 );
foreach ( $rail as $sel ) { $rail_max = max( $rail_max, snm_spec( $sel ) ); }
ok( count( $rail ) > 0 && snm_spec( $receipt_sel ) > $rail_max, 'a receipt on a contact or employer rail outranks critical.css\'s dim-text underline, so none shows at rest' );
$rail_sel = '.sn-resume-rail a.sn-resume-receipt:not(.wp-element-button)';
$rail_r   = $rule( $css, $rail_sel );
$rail_own = array( 0, 0, 0 );
foreach ( $rail as $sel ) { if ( false !== strpos( $sel, '.sn-resume-rail' ) ) { $rail_own = max( $rail_own, snm_spec( $sel ) ); } }
ok( null !== $rail_r && 1 === preg_match( '/text-decoration:\s*none/', $rail_r ) && 0 === preg_match( '/(^|;)\s*color:/', $rail_r ) && array( 0, 0, 0 ) !== $rail_own && snm_spec( $rail_sel ) > $rail_own, 'a receipt in a rail that is not a <p> keeps the line off too: the rail reset (' . implode( ',', snm_spec( $rail_sel ) ) . ') outranks critical.css\'s rail underline (' . implode( ',', $rail_own ) . ') and leaves color alone' );
$reduce = preg_match( '/([^{}]*)\{\s*transition:\s*none;?\s*\}/', $block( $css, '@media (prefers-reduced-motion: reduce)' ), $rm ) ? array_map( 'trim', explode( ",\n", trim( $rm[1] ) ) ) : array();
ok( in_array( $receipt_sel, $reduce, true ) && in_array( $hunk_sel, $reduce, true ), 'both restated fades stop under reduced motion, in resume-marks.css\'s own pairing at their own specificity' );

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
ok( null !== $paper_sel && snm_spec( $paper_sel ) > snm_spec( $screen_sel ), 'paper drops the bar with a selector that outranks the screen one: resume-marks.css loads after print.css, so a tie would print the bar' );

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
