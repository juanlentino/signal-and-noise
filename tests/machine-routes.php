<?php
/**
 * Tests: the machine files are registered with the rewrite API
 * (inc/machine-routes.php, 2026-11-10).
 *
 * Each path gets a rewrite rule and the sn_machine query var, the way core
 * registers robots.txt, so WordPress resolves it as a route, not a 404; the
 * existing template_redirect handlers still answer it. The rules take effect at the
 * plugin's next rewrite flush (the theme never flushes); until then the
 * REQUEST_URI handlers at template_redirect keep answering, so they stay.
 *
 * Run: php tests/machine-routes.php
 */
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', '/' ); }

$pass = 0; $fail = 0;
function ok( $c, $m ) { global $pass, $fail; if ( $c ) { $pass++; echo "PASS: $m\n"; } else { $fail++; echo "FAIL: $m\n"; } }

$GLOBALS['__rules'] = array(); $GLOBALS['__hooks'] = array(); $GLOBALS['__served'] = array();
function add_rewrite_rule( $re, $q, $after = 'bottom' ) { $GLOBALS['__rules'][ $re ] = array( $q, $after ); }
function add_action( $h, $cb, $p = 10, $n = 1 ) { $GLOBALS['__hooks'][ $h ][] = array( $cb, $p ); return true; }
function add_filter( $h, $cb, $p = 10, $n = 1 ) { return add_action( $h, $cb, $p, $n ); }
function flush_rewrite_rules() { $GLOBALS['__flushed'] = true; }

require __DIR__ . '/../inc/machine-routes.php';
foreach ( $GLOBALS['__hooks']['init'] ?? array() as $h ) { $h[0](); }

$want = array(
	'^opensearch\.xml$'            => 'opensearch',
	'^humans\.txt$'                => 'humans',
	'^\.well-known/security\.txt$' => 'security',
	'^llms\.txt$'                  => 'llms',
	'^llms-full\.txt$'             => 'llms-full',
);
foreach ( $want as $re => $key ) {
	ok( isset( $GLOBALS['__rules'][ $re ] ) && 'index.php?sn_machine=' . $key === $GLOBALS['__rules'][ $re ][0] && 'top' === $GLOBALS['__rules'][ $re ][1], "rule $re maps to sn_machine=$key, at the top" );
	ok( 1 === preg_match( '#' . $re . '#', ltrim( array_search( $key, array( '/opensearch.xml' => 'opensearch', '/humans.txt' => 'humans', '/.well-known/security.txt' => 'security', '/llms.txt' => 'llms', '/llms-full.txt' => 'llms-full' ), true ), '/' ) ), "rule $re matches its path" );
}
ok( 5 === count( $GLOBALS['__rules'] ), 'five rules, no more' );
ok( empty( $GLOBALS['__flushed'] ), 'the theme never flushes rewrites' );

$vars = array();
foreach ( $GLOBALS['__hooks']['query_vars'] ?? array() as $h ) { $vars = $h[0]( $vars ); }
ok( in_array( 'sn_machine', $vars, true ), 'sn_machine is a public query var' );

ok( empty( $GLOBALS['__hooks']['parse_request'] ), 'nothing answers at parse_request: the template_redirect handlers serve, after send_headers and wp (security headers, trackers)' );

$src = '';
foreach ( array( 'opensearch', 'humans-txt', 'security-txt', 'llms-txt' ) as $f ) { $src .= (string) file_get_contents( __DIR__ . "/../inc/$f.php" ); }
ok( 4 === preg_match_all( "/add_action\\( 'template_redirect', 'sn_[a-z_]+_maybe_serve', 0 \\);/", $src ), 'the REQUEST_URI fallbacks stay until the flush is confirmed live' );

echo "Result: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
