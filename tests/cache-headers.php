<?php
/**
 * inc/cache-headers.php: Cache-Control for the shared caches.
 *
 * Pure over fixtures: the two header values and their filter; who counts as
 * anonymous; which front-end response gets which header and which get none
 * (logged in, POST, a 404, search, preview, a password form, a response that
 * already set a Cache-Control); a feed trades Breeze's no-cache for the short
 * lifetime but keeps a no-store. Source pins: the three machine files call
 * the sender, and the hook sits at template_redirect priority 100.
 *
 * Run: php tests/cache-headers.php
 */

// SECURITY: CLI-only fixture.
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', '/' ); }

$pass = 0; $fail = 0;
function ok( $cond, $msg ) { global $pass, $fail; if ( $cond ) { $pass++; echo "PASS: $msg\n"; } else { $fail++; echo "FAIL: $msg\n"; } }

// --- Stub WP primitives ---
$GLOBALS['__actions'] = array();
$GLOBALS['__lifetimes'] = null; // a callable to stand in for the filter.
function add_action( $hook, $cb, $prio = 10 ) { $GLOBALS['__actions'][] = array( $hook, $cb, $prio ); }
function apply_filters( $tag, $value, ...$rest ) {
	return ( 'sn_edge_cache_lifetimes' === $tag && is_callable( $GLOBALS['__lifetimes'] ) ) ? call_user_func( $GLOBALS['__lifetimes'], $value, ...$rest ) : $value;
}

require __DIR__ . '/../inc/cache-headers.php';

echo "Group: the values\n";
ok( 'public, max-age=0, s-maxage=86400, stale-while-revalidate=86400, stale-if-error=604800' === sn_edge_cache_control( 'html' ), 'HTML: a day at the edge (what the Cache Rule does today), a day of background refresh, seven days on an origin error' );
ok( 'public, max-age=0, s-maxage=300, stale-while-revalidate=3600, stale-if-error=86400' === sn_edge_cache_control( 'machine' ), 'machine files and feeds: five minutes, an hour, a day' );
$GLOBALS['__lifetimes'] = static function ( $t, $kind ) { return 'html' === $kind ? array( 's_maxage' => 600, 'swr' => 60, 'sie' => 120 ) : array(); };
ok( 'public, max-age=0, s-maxage=600, stale-while-revalidate=60, stale-if-error=120' === sn_edge_cache_control( 'html' ), 'the filter changes the seconds, per kind' );
ok( '' === sn_edge_cache_control( 'machine' ), 'an empty filter return switches the header off for that kind' );
$GLOBALS['__lifetimes'] = static function () { return array( 's_maxage' => 0, 'swr' => 5, 'sie' => 5 ); };
ok( '' === sn_edge_cache_control( 'html' ), 'a zero s-maxage sends nothing rather than a header that caches for no time' );
$GLOBALS['__lifetimes'] = null;

echo "Group: anonymous\n";
ok( true === sn_edge_cache_is_anonymous( 'GET', array(), false ) && true === sn_edge_cache_is_anonymous( 'head', array( 'sn_theme' ), false ), 'GET and HEAD with no session cookie are anonymous' );
ok( false === sn_edge_cache_is_anonymous( 'GET', array(), true ), 'a logged-in user is not' );
ok( false === sn_edge_cache_is_anonymous( 'POST', array(), false ), 'a POST is not' );
ok( false === sn_edge_cache_is_anonymous( 'GET', array( 'wordpress_logged_in_abc' ), false ), 'a login cookie is not, whatever the login check says' );
ok( false === sn_edge_cache_is_anonymous( 'GET', array( 'wp-postpass_abc' ), false ) && false === sn_edge_cache_is_anonymous( 'GET', array( 'comment_author_abc' ), false ), 'a post-password or commenter cookie is not' );

echo "Group: the header already there\n";
ok( '' === sn_edge_cache_existing( array( 'Content-Type: text/html', 'X-Cache-Control-Ish: 1' ) ), 'no Cache-Control reads empty' );
ok( 'no-cache, must-revalidate, max-age=0, no-store, private' === sn_edge_cache_existing( array( 'cache-control: No-Cache, must-revalidate, max-age=0, no-store, private' ) ), 'found whatever the case, lowercased' );

echo "Group: which response gets which header\n";
$page = array( 'anonymous' => true, 'status' => 200, 'existing' => '', 'feed' => false );
ok( 'html' === sn_edge_cache_kind_for( $page ), 'an anonymous 200 page with no Cache-Control gets the HTML header' );
ok( '' === sn_edge_cache_kind_for( array( 'anonymous' => false ) + $page ), 'not anonymous: nothing' );
ok( '' === sn_edge_cache_kind_for( array( 'status' => 404, 'not_found' => true ) + $page ) && '' === sn_edge_cache_kind_for( array( 'status' => 503 ) + $page ), 'a 404 or a 503: nothing' );
foreach ( array( 'admin', 'preview', 'search', 'not_found', 'password', 'non_html' ) as $refusal ) {
	ok( '' === sn_edge_cache_kind_for( array( $refusal => true ) + $page ), $refusal . ': nothing' );
}
foreach ( array( 'no-store', 'private', 'no-cache', 'must-revalidate, max-age=0', 'public, max-age=60' ) as $existing ) {
	ok( '' === sn_edge_cache_kind_for( array( 'existing' => $existing ) + $page ), 'a page that already set "' . $existing . '" keeps it' );
}
$feed = array( 'feed' => true, 'existing' => 'no-cache' ) + $page;
ok( 'machine' === sn_edge_cache_kind_for( $feed ), 'a feed trades Breeze\'s no-cache for the short lifetime' );
ok( 'machine' === sn_edge_cache_kind_for( array( 'existing' => '' ) + $feed ), 'a feed with no header gets it too' );
ok( '' === sn_edge_cache_kind_for( array( 'existing' => 'no-store, private' ) + $feed ) && '' === sn_edge_cache_kind_for( array( 'existing' => 'private' ) + $feed ), 'a feed that set no-store or private keeps it' );
ok( '' === sn_edge_cache_kind_for( array( 'anonymous' => false ) + $feed ) && '' === sn_edge_cache_kind_for( array( 'status' => 404 ) + $feed ), 'a feed read with a session, or a 404 feed: nothing' );

echo "Group: wiring\n";
ok( array( array( 'template_redirect', 'sn_edge_cache_template_redirect', 100 ) ) === $GLOBALS['__actions'], 'one hook: template_redirect at 100, after the redirect handlers and the virtual routes' );
foreach ( array( 'llms-txt.php' => 'sn_llms_txt_send', 'agents-manifest.php' => 'sn_agents_send', 'opensearch.php' => 'sn_opensearch_send' ) as $file => $fn ) {
	$src  = (string) file_get_contents( __DIR__ . '/../inc/' . $file );
	$body = (string) substr( $src, (int) strpos( $src, 'function ' . $fn . '(' ) );
	$body = (string) substr( $body, 0, (int) strpos( $body, "\n}\n" ) );
	ok( false !== strpos( $body, 'sn_edge_cache_send_machine();' ), $fn . '() sends the machine lifetime' );
}
$fn_src = (string) file_get_contents( __DIR__ . '/../functions.php' );
ok( false !== strpos( $fn_src, "require_once __DIR__ . '/inc/cache-headers.php';" ), 'functions.php loads the module' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
