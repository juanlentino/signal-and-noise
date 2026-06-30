<?php
/**
 * Standalone fixture tests for the /opensearch.xml route + autodiscovery (v10.19.0).
 *
 * inc/opensearch.php serves an OpenSearch Description Document pointed at the
 * owned /notes/?s= search route, and emits a <link rel="search"> head tag so
 * browsers can register the site as a search provider. Virtual route +
 * status_header(200) (WORDPRESS-REFERENCE gotcha #40).
 *
 * @since theme v10.19.0
 */

// SECURITY: CLI-only fixture.
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', '/' ); }
define( 'SN_OPENSEARCH_TEST', true ); // suppress wiring on require

ob_start();

$pass = 0; $fail = 0;
function ok( $cond, $msg ) { global $pass, $fail; if ( $cond ) { $pass++; echo "PASS: $msg\n"; } else { $fail++; echo "FAIL: $msg\n"; } }

// --- Stub WP primitives ---
$GLOBALS['__status'] = 0;
function add_action() { return true; }
function status_header( $code ) { $GLOBALS['__status'] = (int) $code; }
function get_option( $k, $d = false ) { return 'blog_charset' === $k ? 'UTF-8' : $d; }
function home_url( $p = '' ) { return 'https://juanlentino.com' . $p; }
function get_bloginfo( $k = '' ) { return 'name' === $k ? 'Signal & Noise' : ''; }
function get_site_icon_url( $size = 512 ) { return ''; } // no site icon in the fixture
function esc_url( $u ) { return $u; }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }

require __DIR__ . '/../inc/opensearch.php';

// --- Route matcher ---
ok( sn_opensearch_is_request( '/opensearch.xml' ) === true, 'matches /opensearch.xml' );
ok( sn_opensearch_is_request( '/opensearch.xml?v=1' ) === true, 'matches with a query string' );
ok( sn_opensearch_is_request( '/opensearch' ) === false, 'rejects the extensionless path' );
ok( sn_opensearch_is_request( '/' ) === false, 'rejects site root' );

// --- OSDD body ---
$body = sn_opensearch_body();
ok( strpos( $body, '<OpenSearchDescription' ) !== false, 'emits an OpenSearchDescription root element' );
ok( strpos( $body, '<ShortName>Signal &amp; Noise</ShortName>' ) !== false, 'ShortName XML-escapes the ampersand' );
ok( strpos( $body, '{searchTerms}' ) !== false, 'keeps the literal {searchTerms} token (un-escaped)' );
ok( strpos( $body, '/notes/?s=' ) !== false, 'targets the owned /notes/?s= search route' );

// --- Head autodiscovery link ---
ob_start(); sn_opensearch_head_link(); $head = ob_get_clean();
ok( strpos( $head, 'rel="search"' ) !== false, 'head link advertises rel="search"' );
ok( strpos( $head, 'application/opensearchdescription+xml' ) !== false, 'head link uses the OSDD MIME type' );
ok( strpos( $head, '/opensearch.xml' ) !== false, 'head link points at /opensearch.xml' );

// --- send() sets 200 (gotcha #40) ---
ob_start(); sn_opensearch_send(); ob_end_clean();
ok( $GLOBALS['__status'] === 200, 'send() calls status_header(200)' );

$report = ob_get_clean();
echo $report;
echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
