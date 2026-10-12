<?php
/**
 * The compare-columns cards fit their column at every width.
 *
 * WHY (2026.10, live audit P1-3): `.sn-compare__col { padding: 1.5rem }` on a
 * stacked column whose flex-basis is 100% with the default content-box sizing
 * made each card 375 + 2 x 24 = 423px wide at a 375px viewport (and 812px at
 * 768). body's overflow-x: hidden clipped the overflow silently, so the
 * published note read "Paid on the data your distributor or labe|". The
 * padding must be inside the width. This is a static pin on the rule; the
 * rendered check is the 375px measurement in the PR.
 *
 * Run: php tests/compare-columns-fit.php
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

$css   = (string) file_get_contents( dirname( __DIR__ ) . '/assets/css/article.css' );
$css   = preg_replace( '~/\*.*?\*/~s', '', $css );
$rules = array();
preg_match_all( '/(^|})\s*([^{}@]*\.sn-compare__col\s*)\{([^}]*)\}/', $css, $m, PREG_SET_ORDER );
foreach ( $m as $r ) {
	$rules[] = $r[3];
}
$decl = implode( ';', $rules );
ok( array() !== $rules, 'found the .sn-compare__col rule in article.css' );
ok( (bool) preg_match( '/padding\s*:/', $decl ), 'the card is padded (the reason it needs border-box)' );
ok( (bool) preg_match( '/box-sizing\s*:\s*border-box/', $decl ), 'the padding sits inside the width: box-sizing: border-box on .sn-compare__col' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
