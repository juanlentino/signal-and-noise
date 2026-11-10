<?php
/**
 * Standalone fixture tests for the security.txt route (RFC 9116, v10.13.0).
 *
 * inc/security-txt.php serves a flat /.well-known/security.txt (and the legacy
 * /security.txt) via a template_redirect virtual route, with the mandatory
 * Expires field derived ~1 year ahead of request time. Mirrors humans-txt.php.
 *
 * @since theme v10.13.0
 */

// SECURITY: CLI-only fixture.
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', '/' ); }
define( 'SN_SECURITY_TXT_TEST', true ); // suppress template_redirect wiring on require

ob_start(); // buffer so the real header() in send() doesn't warn after PASS lines

$pass = 0; $fail = 0;
function ok( $cond, $msg ) { global $pass, $fail; if ( $cond ) { $pass++; echo "PASS: $msg\n"; } else { $fail++; echo "FAIL: $msg\n"; } }

// --- Stub WP primitives the module touches ---
$GLOBALS['__status'] = 0;
function add_action() { return true; }
function status_header( $code ) { $GLOBALS['__status'] = (int) $code; }
function get_option( $k, $d = false ) { return 'blog_charset' === $k ? 'UTF-8' : $d; }
function home_url( $p = '' ) { return 'https://juanlentino.com' . $p; }

require __DIR__ . '/../inc/security-txt.php';

// --- Route matcher (pure helper) ---
ok( sn_security_txt_is_request( 'security' ) === true, 'matches the sn_machine=security query var (the /.well-known/security.txt rule)' );
ok( sn_security_txt_is_request( '/.well-known/security.txt' ) === false, 'the REQUEST_URI path alone no longer matches (fallback removed)' );
ok( sn_security_txt_is_request( 'humans' ) === false, 'rejects another machine file' );
ok( sn_security_txt_is_request( '' ) === false, 'rejects an empty query var' );

// --- Expires (pure, deterministic with injected $now) ---
$now = 1750000000; // fixed
$exp = sn_security_txt_expires( $now );
ok( (bool) preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $exp ), 'Expires is an ISO-8601 UTC timestamp' );
ok( strtotime( $exp ) > $now, 'Expires is in the future' );
ok( strtotime( $exp ) < $now + 31536000, 'Expires is under a year out (RFC 9116 guidance)' );
ok( strtotime( $exp ) > $now + ( 300 * 86400 ), 'Expires is close to a year out (~350 days)' );

// --- Body (pure builder) ---
$body = sn_security_txt_body( $now );
ok( is_string( $body ) && '' !== $body, 'body is a non-empty string' );
ok( strpos( $body, 'Contact: https://juanlentino.com/contact/' ) !== false, 'body carries the mandatory Contact field' );
ok( strpos( $body, 'Expires: ' . $exp ) !== false, 'body carries the mandatory Expires field' );
ok( strpos( $body, 'Canonical: https://juanlentino.com/.well-known/security.txt' ) !== false, 'body carries the Canonical field at the RFC location' );
ok( strpos( $body, 'Preferred-Languages: en, es' ) !== false, 'body advertises preferred languages' );

// --- Serve path forces HTTP 200 (postless virtual route would otherwise 404) ---
$GLOBALS['__status'] = 0;
ob_start();
sn_security_txt_send();
$served = ob_get_clean();
ok( 0 === $GLOBALS['__status'], 'serve path leaves the status to core (the rewrite route resolves as 200, not a 404)' );
ok( strpos( $served, 'Contact:' ) !== false, 'serve path emits the security.txt body' );

ok( 1 === preg_match( '/function sn_security_txt_send\(\).*?sn_edge_cache_send_machine\(\)/s', (string) file_get_contents( dirname( __DIR__ ) . '/inc/security-txt.php' ) ), 'security.txt sends the machine cache lifetime: since the rewrite rule serves it, core no longer sends its 404 no-cache headers' );
echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
