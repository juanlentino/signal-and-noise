<?php
/**
 * Guard: a reader who asks for more contrast (prefers-contrast: more) gets the
 * page without its two textures, the film grain (body::before) and the
 * scanline (body::after), so the noise-floor scroll animation on the grain
 * stops with it. Nothing else changes for them.
 *
 * The override lives in critical.css, AFTER every body::before/::after rule:
 * the inline sheet prints after the combined one, so an override anywhere else
 * loses the cascade (the trap tests/critical-reduced-motion.php documents).
 *
 * Run: php tests/critical-prefers-contrast.php
 */
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }
$pass = 0; $fail = 0;
function ok( $c, $m ) { global $pass, $fail; if ( $c ) { $pass++; echo "PASS: $m\n"; } else { $fail++; echo "FAIL: $m\n"; } }
$css = (string) preg_replace( '#/\*.*?\*/#s', '', (string) file_get_contents( dirname( __DIR__ ) . '/assets/css/critical.css' ) );
$at  = preg_match( '/@media\s*\(\s*prefers-contrast:\s*more\s*\)\s*\{\s*body::before,\s*body::after\s*\{([^}]*)\}\s*\}/', $css, $m, PREG_OFFSET_CAPTURE ) ? $m[0][1] : -1;
ok( $at >= 0, 'critical.css has a prefers-contrast: more block for body::before and body::after' );
$body = $at >= 0 ? $m[1][0] : '';
ok( 1 === preg_match( '/display:\s*none/', $body ), 'it hides the grain and the scanline' );
ok( 1 === preg_match( '/animation:\s*none/', $body ), 'and stops the noise-floor animation' );
ok( 2 === count( array_filter( array_map( 'trim', explode( ';', $body ) ) ) ), 'and nothing else' );
$end = $at >= 0 ? $at + strlen( $m[0][0] ) : 0;
ok( $at >= 0 && false === strpos( $css, 'body::before', $end ) && false === strpos( $css, 'body::after', $end ), 'the block comes after every other body::before/::after rule, so it wins the cascade' );
echo "Result: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
