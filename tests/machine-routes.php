<?php
/**
 * Tests: the machine files are registered with the rewrite API
 * (inc/machine-routes.php, 2026-11-10).
 *
 * Each path gets a rewrite rule and the sn_machine query var, the way core
 * registers robots.txt, so WordPress resolves it as a route, not a 404; the
 * template_redirect handlers answer it on the query var alone (the REQUEST_URI
 * fallbacks are gone, 2026-11-10). The theme flushes once when its rule set
 * changes, never on every request.
 *
 * Run: php tests/machine-routes.php
 */
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', '/' ); }

$pass = 0; $fail = 0;
function ok( $c, $m ) { global $pass, $fail; if ( $c ) { $pass++; echo "PASS: $m\n"; } else { $fail++; echo "FAIL: $m\n"; } }

$GLOBALS['__rules'] = array(); $GLOBALS['__hooks'] = array(); $GLOBALS['__served'] = array(); $GLOBALS['__opts'] = array(); $GLOBALS['__flushed'] = 0;
function add_rewrite_rule( $re, $q, $after = 'bottom' ) { $GLOBALS['__rules'][ $re ] = array( $q, $after ); }
function add_action( $h, $cb, $p = 10, $n = 1 ) { $GLOBALS['__hooks'][ $h ][] = array( $cb, $p ); return true; }
function add_filter( $h, $cb, $p = 10, $n = 1 ) { return add_action( $h, $cb, $p, $n ); }
function flush_rewrite_rules( $hard = true ) { $GLOBALS['__flushed']++; $GLOBALS['__hard'] = $hard; }
function get_option( $k, $d = false ) { return $GLOBALS['__opts'][ $k ] ?? $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['__opts'][ $k ] = $v; return true; }
function run_hook( $h ) { foreach ( $GLOBALS['__hooks'][ $h ] ?? array() as $cb ) { $cb[0](); } }

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
ok( 0 === $GLOBALS['__flushed'], 'registering the rules does not flush' );

// Flush safety: once when the rule set is new or changed, then never again.
run_hook( 'wp_loaded' );
ok( 1 === $GLOBALS['__flushed'] && false === $GLOBALS['__hard'], 'a fresh install flushes once, soft (no .htaccess write), after every init rule is in' );
run_hook( 'wp_loaded' ); run_hook( 'wp_loaded' );
ok( 1 === $GLOBALS['__flushed'], 'later requests do not flush again' );
$GLOBALS['__opts'][ SN_MACHINE_ROUTES_OPTION ] = 'an older rule set';
run_hook( 'wp_loaded' );
ok( 2 === $GLOBALS['__flushed'], 'a changed rule set flushes once more' );
run_hook( 'wp_loaded' );
ok( 2 === $GLOBALS['__flushed'], 'and then stops' );

$vars = array();
foreach ( $GLOBALS['__hooks']['query_vars'] ?? array() as $h ) { $vars = $h[0]( $vars ); }
ok( in_array( 'sn_machine', $vars, true ), 'sn_machine is a public query var' );

ok( empty( $GLOBALS['__hooks']['parse_request'] ), 'nothing answers at parse_request: the template_redirect handlers serve, after send_headers and wp (security headers, trackers)' );

$src = '';
foreach ( array( 'opensearch', 'humans-txt', 'security-txt', 'llms-txt' ) as $f ) { $src .= (string) file_get_contents( __DIR__ . "/../inc/$f.php" ); }
ok( 4 === preg_match_all( "/add_action\\( 'template_redirect', 'sn_[a-z_]+_maybe_serve', 0 \\);/", $src ), 'the four template_redirect handlers still serve' );
ok( false === strpos( $src, 'REQUEST_URI' ), 'no machine-file handler reads REQUEST_URI: the fallback is gone' );
ok( 4 === substr_count( $src, "get_query_var( 'sn_machine' )" ), 'each handler reads the sn_machine query var' );
ok( false === strpos( $src, 'status_header(' ), 'no machine-file handler forces status_header( 200 ): the route is not a 404' );

echo "Result: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
