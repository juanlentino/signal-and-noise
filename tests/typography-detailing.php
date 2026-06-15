<?php
/**
 * Static-guard tests for typographic detailing (C1, theme v10.5.0).
 *
 * C1 is pure CSS, verified visually by headless-Chrome computed style. This
 * fixture is the in-repo regression guard (mirrors tests/view-transitions.php
 * and tests/style-variations.php): it asserts the declarations are present in
 * assets/css/critical.css and that the hanging-punctuation rule was broadened
 * beyond the old single-post-only scope.
 *
 * @since theme v10.5.0
 */

// SECURITY: CLI-only fixture.
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }

$pass = 0; $fail = 0;
function ok( $cond, $msg ) { global $pass, $fail; if ( $cond ) { $pass++; echo "PASS: $msg\n"; } else { $fail++; echo "FAIL: $msg\n"; } }

$css = (string) file_get_contents( realpath( __DIR__ . '/..' ) . '/assets/css/critical.css' );
ok( '' !== $css, 'critical.css is readable' );

// Hanging punctuation present + broadened to the pull-quote prose root.
ok( strpos( $css, 'hanging-punctuation:' ) !== false, 'hanging-punctuation declared' );
ok(
	preg_match( '/\.wp-block-post-content,\s*\.sn-pull-quote__body\s*\{\s*hanging-punctuation:/', $css ) === 1,
	'hanging-punctuation broadened to .wp-block-post-content + .sn-pull-quote__body (not single-post only)'
);

// Tabular figures on numeric columns (forward-compat intent on the monospace body).
ok( strpos( $css, 'font-variant-numeric: tabular-nums' ) !== false, 'tabular-nums declared' );
ok( strpos( $css, '.sn-notes-pagination' ) !== false, 'tabular-nums targets a real number column (.sn-notes-pagination)' );
ok( strpos( $css, '.sn-disco-count' ) !== false, 'tabular-nums targets discography counts' );

// Opt-in diagonal-fractions utility (DM Mono carries `frac`).
ok( strpos( $css, '.sn-frac' ) !== false, '.sn-frac utility present' );
ok( strpos( $css, 'font-variant-numeric: diagonal-fractions' ) !== false, 'diagonal-fractions declared on the opt-in utility' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
