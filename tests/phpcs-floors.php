<?php
/**
 * The linter grades against the floors the theme declares.
 *
 * WHY (2026.10, theme audit P3-6): phpcs.xml.dist said minimum_wp_version 6.4
 * and testVersion 8.0- while style.css and readme.txt declare WP 7.0 and PHP
 * 8.3. So WPCS graded a function deprecated in 6.5 to 7.0 as a warning, and
 * PHPCompatibility would reject valid 8.1 to 8.3 syntax. The floors are read
 * from style.css, so a future bump there fails here until the linter follows.
 *
 * Run: php tests/phpcs-floors.php
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

$root  = dirname( __DIR__ );
$style = (string) file_get_contents( $root . '/style.css' );
$xml   = (string) file_get_contents( $root . '/phpcs.xml.dist' );
preg_match( '/^Requires at least:\s*([\d.]+)/mi', $style, $wp );
preg_match( '/^Requires PHP:\s*([\d.]+)/mi', $style, $php );
preg_match( '/name="minimum_wp_version"\s+value="([^"]+)"/', $xml, $min_wp );
preg_match( '/name="testVersion"\s+value="([^"]+)"/', $xml, $test_php );

ok( ! empty( $wp[1] ) && ! empty( $php[1] ), 'style.css declares its WP and PHP floors' );
ok( ( $min_wp[1] ?? '' ) === ( $wp[1] ?? 'x' ), 'phpcs minimum_wp_version (' . ( $min_wp[1] ?? 'none' ) . ') matches Requires at least (' . ( $wp[1] ?? '?' ) . ')' );
ok( ( $test_php[1] ?? '' ) === ( $php[1] ?? 'x' ) . '-', 'phpcs testVersion (' . ( $test_php[1] ?? 'none' ) . ') starts at Requires PHP (' . ( $php[1] ?? '?' ) . ')' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
