<?php
/**
 * Guard: base.css turns every transition and animation off under
 * prefers-reduced-motion: reduce with `none !important`. A declaration that is
 * itself !important and more specific outranks that reset, so every such
 * declaration must carry its own `none !important` counterpart inside a
 * reduce block for the same selector (Codex on #514: the open mobile menu's
 * link color transition kept running).
 * Run: php tests/reduced-motion-important.php
 */
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }
$pass = 0; $fail = 0;
function ok( $c, $m ) { global $pass, $fail; if ( $c ) { $pass++; echo "PASS: $m\n"; } else { $fail++; echo "FAIL: $m\n"; } }
$checked = 0;
foreach ( glob( dirname( __DIR__ ) . '/assets/css/*.css' ) as $f ) {
	$css  = (string) preg_replace( '#/\*.*?\*/#s', '', (string) file_get_contents( $f ) );
	$file = basename( $f );
	// The reduce blocks of THIS file only: critical.css paints before the
	// combined sheet loads, so another file's reset cannot cover it (Codex
	// on #514).
	$reduce = '';
	if ( preg_match_all( '/@media[^{]*prefers-reduced-motion:\s*reduce[^{]*\{((?:[^{}]*\{[^{}]*\})*)[^{}]*\}/', $css, $mm ) ) {
		$reduce = implode( "\n", $mm[1] );
	}
	if ( ! preg_match_all( '/([^{}]+)\{[^{}]*?\b(transition|animation)\s*:\s*+(?!none)[^;{}]*!important/', $css, $m, PREG_SET_ORDER ) ) {
		continue;
	}
	foreach ( $m as $x ) {
		$sel     = trim( $x[1] );
		$prop    = $x[2];
		$covered = (bool) preg_match( '/' . preg_quote( $sel, '/' ) . '\s*\{[^}]*' . $prop . '\s*:\s*none\s*!important/', $reduce );
		ok( $covered, "$file: `$sel` $prop !important has a reduce counterpart in the same file" );
		$checked++;
	}
}
ok( $checked > 0, 'the scan found !important motion to check (a silent zero is not a pass)' );
echo "Result: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
