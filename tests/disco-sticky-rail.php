<?php
/**
 * Standalone test: the /music controls rail (.sn-disco-controls) sticks.
 *
 * It was position: sticky and stuck nowhere. html and body both set
 * overflow-x: hidden; html's value goes to the viewport, so body's makes BODY
 * a scroll container that never scrolls, and a sticky element sticks to its
 * nearest scroll container. Measured live 2026-10-16: the rail's top went 0,
 * -600, -1500 as the page scrolled. Its top offsets (61px, 80px, 65px) also
 * dated from a header that shrank on scroll.
 *
 * The fix: body clips (overflow-x: clip is not a scroll container) on pages
 * that carry the rail, and the rail sticks at --sn-chrome-top, the header
 * footprint token body padding and anchor offsets already read, with the gap
 * under the header covered so the grid does not show through it.
 *
 * Run: php tests/disco-sticky-rail.php
 */

if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }

$pass = 0; $fail = 0;
function ok( $c, $m ) { global $pass, $fail; if ( $c ) { ++$pass; echo "PASS: $m\n"; } else { ++$fail; echo "FAIL: $m\n"; } }

$css = preg_replace( '~/\*.*?\*/~s', '', (string) file_get_contents( __DIR__ . '/../assets/css/components.css' ) );

ok( 1 === preg_match( '/body:has\(\.sn-disco-controls\)\s*\{[^}]*overflow-x:\s*clip/', $css ), 'body clips instead of hiding where the rail is, so body is not a scroll container the rail could stick to' );

preg_match_all( '/\.sn-disco-controls\s*\{([^}]*)\}/', $css, $m );
$tops = array();
foreach ( $m[1] as $decls ) {
	if ( preg_match( '/(?:^|;|\s)top:\s*([^;]+)/', $decls, $t ) ) { $tops[] = trim( $t[1] ); }
}
ok( array() !== $tops, 'the rail declares a sticky top (found ' . count( $tops ) . ')' );
ok( array( 'var(--sn-chrome-top)' ) === array_values( array_unique( $tops ) ), 'every top the rail declares is the header-footprint token, no stale literal (' . implode( ', ', $tops ) . ')' );
ok( 1 === preg_match( '/\.sn-disco-controls\s*\{[^}]*position:\s*sticky/', $css ), 'the rail is still position: sticky' );
ok( 1 === preg_match( '/\.sn-disco-controls\s*\{[^}]*box-shadow:\s*0\s+-\d+px\s+0\s+0\s+var\(--wp--preset--color--void\)/', $css ), 'the band between the header and the stuck rail is covered in the page color, so cards never show through it' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
