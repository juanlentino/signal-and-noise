<?php
/**
 * v10.0.0 manifest-floor guard — pins the WP 7.0 hard-raise + the 10.0.0
 * version in style.css (the theme's single manifest source of truth).
 *
 * @since 10.0.0
 */

if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) {
	http_response_code( 404 );
	exit;
}

$pass = 0;
$fail = 0;
function mf_check( $cond, $msg ) {
	global $pass, $fail;
	if ( $cond ) { $pass++; echo "PASS: $msg\n"; }
	else { $fail++; echo "FAIL: $msg\n"; }
}

$css = (string) file_get_contents( dirname( __DIR__ ) . '/style.css' );

preg_match( '/Requires at least:\s*([0-9.]+)/', $css, $m );
mf_check( isset( $m[1] ) && '7.0' === $m[1], 'style.css "Requires at least: 7.0" (got ' . ( $m[1] ?? '?' ) . ')' );

preg_match( '/\nVersion:\s*([0-9.]+)/', $css, $v );
mf_check( isset( $v[1] ) && version_compare( $v[1], '10.0.0', '>=' ), 'theme Version >= 10.0.0 (got ' . ( $v[1] ?? '?' ) . ')' );

echo "\n$pass passed, $fail failed\n";
exit( $fail > 0 ? 1 : 0 );
