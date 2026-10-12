<?php
/**
 * Standalone link targets on /notes/ are at least 24px tall (WCAG 2.5.8).
 *
 * WHY (2026.10, live audit P2-3): the tag links in every /notes/ row
 * (a.sn-notes-row-tags, inc/notes-index-row.php) measured 19.7px tall at
 * 375px, 102 of them on the page, and Lighthouse's target-size audit failed
 * on them. They are standalone controls in a metadata row, not links inside
 * prose, so the inline exception does not apply. A static pin on the rule;
 * the rendered measurement is in the PR.
 *
 * Run: php tests/target-size.php
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

$css  = (string) preg_replace( '~/\*.*?\*/~s', '', (string) file_get_contents( dirname( __DIR__ ) . '/assets/css/notes.css' ) );
$decl = '';
if ( preg_match_all( '/(^|})\s*([^{}@]*\.sn-notes-row-tags[^{}]*)\{([^}]*)\}/', $css, $m, PREG_SET_ORDER ) ) {
	foreach ( $m as $r ) {
		if ( ! preg_match( '/:(hover|focus)/', $r[2] ) ) {
			$decl .= $r[3] . ';';
		}
	}
}
ok( (bool) preg_match( '/min-height\s*:\s*(2[4-9]|[3-9]\d)px/', $decl ), 'a.sn-notes-row-tags holds a min-height of at least 24px' );
ok( (bool) preg_match( '/display\s*:\s*inline-flex/', $decl ) && (bool) preg_match( '/align-items\s*:\s*center/', $decl ), 'the label is centered in the taller box (inline-flex, align-items: center)' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
