<?php
/**
 * Tests: what the section copy-link button promises, in its CSS (review of #621).
 *
 * Run: php tests/section-link-css.php
 */
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }
$pass = 0; $fail = 0;
function ok( $c, $m ) { global $pass, $fail; if ( $c ) { $pass++; echo "PASS: $m\n"; } else { $fail++; echo "FAIL: $m\n"; } }
$css = (string) file_get_contents( dirname( __DIR__ ) . '/assets/css/components-12-article-toc.css' );
$rule = preg_match( '/\.sn-section-link\s*\{([^}]*)\}/', $css, $m ) ? $m[1] : '';
ok( '' !== $rule, 'the button has its rule' );
ok( 1 === preg_match( '/min-width:\s*24px/', $rule ) && 1 === preg_match( '/min-height:\s*24px/', $rule ), 'a 24px target (WCAG 2.5.8)' );
ok( 1 === preg_match( '/-webkit-user-select:\s*none/', $rule ) && 1 === preg_match( '/(?<!-)user-select:\s*none/', $rule ), 'never copied with the heading, in Safari too (it reads only the prefixed property)' );
ok( 1 === preg_match( '/position:\s*relative/', $rule ), 'the anchor for its state label' );
ok( 1 === preg_match( '/\.sn-section-link\[data-sn-state\]::after\s*\{[^}]*position:\s*absolute[^}]*\}/', $css ), 'the state label is out of flow, so the heading never reflows on a copy' );
ok( 1 === preg_match( '/\.sn-section-link\[data-sn-state\]::after\s*\{[^}]*text-transform:\s*uppercase[^}]*\}/', $css ) && false !== strpos( $css, '[data-sn-state="copied"]::after' ) && false !== strpos( $css, '[data-sn-state="failed"]::after' ), 'COPIED and COPY FAILED, in the label type' );
ok( 1 === preg_match( '/\.sn-section-link:focus-visible\s*\{[^}]*(?<![-\w])color:\s*var\(--wp--preset--color--blood\)[^}]*border-color:\s*var\(--wp--preset--color--blood\)/', $css ), 'a designed focus state: blood text and border' );
ok( 1 === preg_match( '/@media \(hover: hover\)\s*\{\s*\.sn-section-link:hover/', $css ), 'hover only where there is hover (no sticky state on touch)' );
echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
