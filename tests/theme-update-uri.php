<?php
/**
 * Tests: the theme declares its own update source.
 *
 * Core (6.1+) reads a theme's `Update URI` header: with it, the theme is not
 * matched against WordPress.org by slug, and `update_themes_{$hostname}` is
 * where its updates come from. Without it, core sent this theme to the .org
 * check by its slug, which a same-named .org theme could answer.
 *
 * Run: php tests/theme-update-uri.php
 */
if ( PHP_SAPI !== 'cli' ) { http_response_code( 404 ); exit; }

$pass = 0; $fail = 0;
function ok( $c, $m ) { global $pass, $fail; if ( $c ) { ++$pass; echo "PASS: $m\n"; } else { ++$fail; echo "FAIL: $m\n"; } }

$head = (string) file_get_contents( __DIR__ . '/../style.css', false, null, 0, 8192 );
ok( 1 === preg_match( '/^Update URI:\s*(\S+)\s*$/mi', $head, $m ), 'style.css carries an Update URI header in its first 8 KB, where core reads headers' );
ok( 'https://github.com/juanlentino/signal-and-noise' === ( $m[1] ?? '' ), 'it names this theme\'s repository, not WordPress.org' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
