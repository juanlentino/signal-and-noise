<?php
/**
 * Standalone fixture tests for the /.well-known/gpc.json route (v10.19.0).
 *
 * inc/gpc-json.php serves the Global Privacy Control support resource
 * (https://globalprivacycontrol.github.io/gpc-spec/): {"gpc":true,"lastUpdate":"…"}.
 * The server-side DECLARATION counterpart to the beacon's client-side GPC bail.
 * Mirrors security-txt.php — virtual route + status_header(200) (gotcha #40).
 *
 * @since theme v10.19.0
 */

// SECURITY: CLI-only fixture.
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', '/' ); }
define( 'SN_GPC_JSON_TEST', true ); // suppress template_redirect wiring on require

ob_start();

$pass = 0; $fail = 0;
function ok( $cond, $msg ) { global $pass, $fail; if ( $cond ) { $pass++; echo "PASS: $msg\n"; } else { $fail++; echo "FAIL: $msg\n"; } }

// --- Stub WP primitives ---
$GLOBALS['__status'] = 0;
function add_action() { return true; }
function status_header( $code ) { $GLOBALS['__status'] = (int) $code; }
function wp_json_encode( $data ) { return json_encode( $data ); }

require __DIR__ . '/../inc/gpc-json.php';

// --- Route matcher ---
ok( sn_gpc_json_is_request( '/.well-known/gpc.json' ) === true, 'matches the canonical /.well-known/gpc.json' );
ok( sn_gpc_json_is_request( '/.well-known/gpc.json?x=1' ) === true, 'matches with a query string' );
ok( sn_gpc_json_is_request( '/gpc.json' ) === false, 'rejects the non-well-known top-level path' );
ok( sn_gpc_json_is_request( '/' ) === false, 'rejects site root' );

// --- Body is valid JSON declaring GPC support with a FIXED (non-request-time) lastUpdate ---
$body    = sn_gpc_json_body();
$decoded = json_decode( $body, true );
ok( is_array( $decoded ), 'body is valid JSON' );
ok( isset( $decoded['gpc'] ) && $decoded['gpc'] === true, 'declares gpc: true' );
ok( isset( $decoded['lastUpdate'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $decoded['lastUpdate'] ) === 1, 'lastUpdate is a YYYY-MM-DD date' );
ok( sn_gpc_json_body() === $body, 'body is stable across calls (not request-time derived, so it caches)' );

// --- send() sets 200 (gotcha #40) ---
ob_start(); sn_gpc_json_send(); ob_end_clean();
ok( $GLOBALS['__status'] === 200, 'send() calls status_header(200)' );

$report = ob_get_clean();
echo $report;
echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
