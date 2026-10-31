<?php
/**
 * Tests: the theme declares its own update source.
 *
 * Core (6.1+) sends a theme's `Update URI` with the update check, and the
 * WordPress.org server then skips it instead of matching it by slug;
 * `update_themes_{$hostname}` is where its updates come from
 * (sn_gh_theme_update_offer(), pinned in tests/updater-github-auth.php).
 * Without it, a same-named .org theme could answer.
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
