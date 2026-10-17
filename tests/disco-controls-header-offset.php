<?php
/**
 * Test: the sticky discography controls rail sticks under the header, by the
 * header-footprint token, not a hardcoded height (issue #322, then 2026-10).
 *
 * `.sn-disco-controls { top: 108px }` used the unscrolled desktop header's
 * height (the same 108px that sets `body { padding-top: 108px }`). The
 * header shrinks to ~61px once `.is-scrolled` applies, and by the time a
 * sticky rail actually engages the header is already in that state — so a
 * 14-47px band of the grid scrolled between the rail and the header.
 *
 * Run: php tests/disco-controls-header-offset.php
 */

if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }

$pass = 0; $fail = 0;
function ok( $c, $m ) { global $pass, $fail; if ( $c ) { $pass++; echo "PASS: $m\n"; } else { $fail++; echo "FAIL: $m\n"; } }

$css = (string) file_get_contents( dirname( __DIR__ ) . '/assets/css/components.css' );
ok( '' !== $css, 'components.css is readable' );

ok(
	(bool) preg_match( '/\.sn-disco-controls\s*\{[^}]*top:\s*108px/s', $css ) === false,
	'#322: .sn-disco-controls no longer sticks at the unscrolled 108px header height'
);
// 2026-10: the header no longer shrinks (one height since), so the 61px
// "scrolled" value and its 80/65px staircase described a header that is gone.
// The rail now sticks at --sn-chrome-top, the header-footprint token body
// padding and anchor offsets read (tests/disco-sticky-rail.php owns the rest).
ok( (bool) preg_match( '/\.sn-disco-controls\s*\{[^}]*top:\s*var\(--sn-chrome-top\)/s', $css ), '.sn-disco-controls sticks at --sn-chrome-top, the header-footprint token' );
ok( 0 === preg_match( '/\.sn-disco-controls\s*\{[^}]*top:\s*\d+px/', $css ), 'no literal px top remains anywhere for the rail (the 61/80/65 staircase is gone)' );
echo sprintf( "\nResult: %d passed, %d failed.\n", $pass, $fail );
exit( $fail > 0 ? 1 : 0 );
