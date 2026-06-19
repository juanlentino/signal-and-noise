<?php
/**
 * Standalone fixture test for the wptexturize disable (v10.13.2).
 *
 * inc/disable-smart-quotes.php forces the run_wptexturize gate to false so
 * WordPress renders the straight quotes / dashes the source actually contains,
 * instead of auto-converting them to curly "smart" quotes + en/em-dashes.
 *
 * @since theme v10.13.2
 */

if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', '/' ); }

$pass = 0; $fail = 0;
function ok( $c, $m ) { global $pass, $fail; if ( $c ) { $pass++; echo "PASS: $m\n"; } else { $fail++; echo "FAIL: $m\n"; } }

// Capture filter registrations; provide the WP no-op callback the module wires.
$GLOBALS['__filters'] = array();
function add_filter( $hook, $cb, $p = 10, $a = 1 ) { $GLOBALS['__filters'][ $hook ][] = $cb; return true; }
if ( ! function_exists( '__return_false' ) ) { function __return_false() { return false; } }

require __DIR__ . '/../inc/disable-smart-quotes.php';

ok( isset( $GLOBALS['__filters']['run_wptexturize'] ), 'registers a run_wptexturize filter' );
$cb = $GLOBALS['__filters']['run_wptexturize'][0] ?? null;
ok( null !== $cb && false === call_user_func( $cb, true ), 'the gate returns false for a true input (texturize becomes a no-op)' );
ok( null !== $cb && false === call_user_func( $cb, 'anything' ), 'the gate returns false regardless of input' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
