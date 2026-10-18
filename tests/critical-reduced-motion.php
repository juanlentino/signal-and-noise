<?php
/**
 * Guard: critical.css carries its own prefers-reduced-motion override for
 * `html { scroll-behavior: smooth }`, AFTER the smooth rule.
 *
 * The inline <style id="sn-critical-inline"> prints at wp_head priority 50,
 * after the combined stylesheet, so base.css's reduce override (same
 * specificity, earlier in the cascade) lost to it: the 2026-10-18 sweep
 * measured smooth scrolling surviving prefers-reduced-motion: reduce. Only an
 * override inside critical.css, after the smooth rule, wins.
 *
 * Run: php tests/critical-reduced-motion.php
 */
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }
$pass = 0; $fail = 0;
function ok( $c, $m ) { global $pass, $fail; if ( $c ) { $pass++; echo "PASS: $m\n"; } else { $fail++; echo "FAIL: $m\n"; } }
$css    = (string) preg_replace( '#/\*.*?\*/#s', '', (string) file_get_contents( dirname( __DIR__ ) . '/assets/css/critical.css' ) );
$smooth = preg_match( '/(?:^|\})\s*html\s*\{[^}]*scroll-behavior:\s*smooth/', $css, $m, PREG_OFFSET_CAPTURE ) ? $m[0][1] : -1;
ok( $smooth >= 0, 'critical.css sets html { scroll-behavior: smooth } (the rule this guard exists for)' );
$reduce = preg_match( '/@media[^{]*prefers-reduced-motion:\s*reduce[^{]*\{\s*html\s*\{[^}]*scroll-behavior:\s*auto/', $css, $r, PREG_OFFSET_CAPTURE ) ? $r[0][1] : -1;
ok( $reduce >= 0, 'critical.css has a reduce block setting html { scroll-behavior: auto }' );
ok( $reduce > $smooth, 'the override comes AFTER the smooth rule, so it wins the cascade' );
$base = (string) file_get_contents( dirname( __DIR__ ) . '/assets/css/base.css' );
ok( false === strpos( $base, 'so this override wins' ), 'base.css no longer claims its override beats the inline critical copy' );
echo "Result: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
