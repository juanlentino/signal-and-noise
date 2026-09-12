<?php
/**
 * Test: the sticky discography controls rail sticks under the SCROLLED
 * header height, not the unscrolled one (issue #322).
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
ok(
	(bool) preg_match( '/\.sn-disco-controls\s*\{[^}]*top:\s*61px/s', $css ),
	'#322: .sn-disco-controls sticks at 61px, the scrolled header height'
);
// The two phone/tablet breakpoint overrides are a different, already-correct
// staircase (issue only flags the desktop value) — leave them alone.
ok( strpos( $css, '.sn-disco-controls   { top: 80px; }' ) !== false, 'the tablet override is unchanged' );
ok( strpos( $css, '.sn-disco-controls   { top: 65px; }' ) !== false, 'the mobile override is unchanged' );

echo sprintf( "\nResult: %d passed, %d failed.\n", $pass, $fail );
exit( $fail > 0 ? 1 : 0 );
