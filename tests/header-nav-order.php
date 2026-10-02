<?php
/**
 * Guard: the header navigation order (theme 14.8.0, owner-approved 2026-10-02).
 * Resume and Music sit beside About so a visitor of any of the three audiences
 * (hiring, artists, research) finds their page early; still seven items.
 *
 * Run: php tests/header-nav-order.php
 */
if ( PHP_SAPI !== 'cli' ) { exit; }
$pass = 0; $fail = 0;
function ok( $c, $m ) { global $pass, $fail; if ( $c ) { $pass++; echo "PASS: $m\n"; } else { $fail++; echo "FAIL: $m\n"; } }

preg_match_all( '/wp:navigation-link \{"label":"([^"]+)","url":"([^"]+)"/', (string) file_get_contents( __DIR__ . '/../parts/header.html' ), $m );
ok( array( 'Home', 'About', 'Resume', 'Music', 'Services', 'Notes', 'Contact' ) === $m[1], 'header order: Home, About, Resume, Music, Services, Notes, Contact (' . implode( ', ', $m[1] ) . ')' );
ok( array( '/', '/about', '/resume', '/music', '/services', '/notes', '/contact' ) === $m[2], 'each item keeps its URL' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
