<?php
/**
 * inc/cache-headers.php: Cache-Control for the shared caches.
 *
 * Pure over fixtures: the Cache-Control (public, max-age=0, never an s-maxage),
 * the Cloudflare-CDN-Cache-Control values and their filter; who counts as
 * anonymous; which front-end response gets which header and which get none
 * (logged in, POST, a 404, search, preview, a password form, a response that
 * already set a Cache-Control); a feed trades Breeze's no-cache for the short
 * lifetime but keeps a no-store; a later nocache_headers() or error status
 * takes the edge header back. Source pins: the three machine files call
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
require __DIR__ . '/../inc/cache-headers-rules.php';
require __DIR__ . '/../inc/cache-headers-hooks.php';

echo "Group: the values\n";
ok( 'public, max-age=0' === SN_EDGE_BROWSER_CACHE_CONTROL, 'Cache-Control is public, max-age=0: browsers revalidate, Varnish stores nothing' );
ok( 'max-age=7200, stale-while-revalidate=86400, stale-if-error=604800' === sn_edge_cdn_cache_control( 'html' ) && 7200 === SN_EDGE_HTML_MAX_AGE, 'HTML at the edge: two hours (Cloudflare\'s default for a 200, which is what the edge does today), a day of background refresh, seven days on an origin error' );
ok( 'max-age=300, stale-while-revalidate=3600, stale-if-error=86400' === sn_edge_cdn_cache_control( 'machine' ), 'machine files and feeds at the edge: five minutes, an hour, a day' );
$GLOBALS['__lifetimes'] = static function ( $t, $kind ) { return 'html' === $kind ? array( 'max_age' => 600, 'swr' => 60, 'sie' => 120, 's_maxage' => 999 ) : array(); };
ok( 'max-age=600, stale-while-revalidate=60, stale-if-error=120' === sn_edge_cdn_cache_control( 'html' ), 'the filter changes the seconds, per kind' );
ok( '' === sn_edge_cdn_cache_control( 'machine' ), 'an empty filter return switches that kind off' );
$filtered = sn_edge_cdn_cache_control( 'html' );
$GLOBALS['__lifetimes'] = static function () { return array( 'max_age' => 0, 'swr' => 5, 'sie' => 5 ); };
ok( '' === sn_edge_cdn_cache_control( 'html' ), 'a zero max-age sends nothing rather than a header that caches for no time' );
$GLOBALS['__lifetimes'] = null;

echo "Group: no s-maxage, anywhere\n";
// s-maxage in Cache-Control would have Varnish hold HTML no save purges, and
// in either header it switches stale serving off at Cloudflare.
foreach ( array( 'Cache-Control' => SN_EDGE_BROWSER_CACHE_CONTROL, 'the HTML edge header' => sn_edge_cdn_cache_control( 'html' ), 'the machine edge header' => sn_edge_cdn_cache_control( 'machine' ), 'a filtered edge header' => $filtered ) as $what => $value ) {
	ok( '' !== $value && false === stripos( $value, 's-maxage' ) && false === stripos( $value, 'revalidate,' ) && false === stripos( $value, 'no-cache' ), $what . ' carries no s-maxage (nor must-revalidate, proxy-revalidate, no-cache)' );
}
$hooks_src = (string) file_get_contents( __DIR__ . '/../inc/cache-headers-hooks.php' );
ok( 1 === substr_count( $hooks_src, "header( 'Cache-Control: '" ) && false !== strpos( $hooks_src, "header( 'Cache-Control: ' . SN_EDGE_BROWSER_CACHE_CONTROL );" ), 'the one Cache-Control this module writes is that constant, never a built value' );
ok( false !== strpos( $hooks_src, "header( 'Cloudflare-CDN-Cache-Control: ' . \$cdn );" ) && false === strpos( $hooks_src, "header( 'CDN-Cache-Control" ), 'the edge lifetime goes in Cloudflare-CDN-Cache-Control (not passed downstream), not CDN-Cache-Control' );
ok( 'sn-render' === SN_EDGE_CACHE_TAG && false !== strpos( $hooks_src, "header( 'Cache-Tag: ' . SN_EDGE_CACHE_TAG );" ) && strpos( $hooks_src, "header( 'Cloudflare-CDN-Cache-Control: '" ) < strpos( $hooks_src, "header( 'Cache-Tag: '" ) && false !== strpos( $hooks_src, "header_remove( 'Cache-Tag' );" ), 'every response that gets the edge lifetime carries the one cache tag, and loses it with the lifetime' );

echo "Group: taking the edge header back\n";
ok( true === sn_edge_cache_still_ours( 'public, max-age=0', 200 ), 'untouched and 200: the edge header stays' );
ok( false === sn_edge_cache_still_ours( 'no-cache, must-revalidate, max-age=0, no-store, private', 200 ), 'a later nocache_headers(): the edge header goes (Cloudflare would read it INSTEAD of the no-store)' );
ok( false === sn_edge_cache_still_ours( 'public, max-age=0', 503 ) && false === sn_edge_cache_still_ours( '', 200 ), 'a late error status, or the Cache-Control removed: it goes' );
ok( false !== strpos( $hooks_src, "header_register_callback( 'sn_edge_cache_recheck' );" ) && false !== strpos( $hooks_src, "header_remove( 'Cloudflare-CDN-Cache-Control' );" ), 'emit registers the recheck, and the recheck removes the edge header' );

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
$feed = array( 'feed' => true, 'listed_feed' => true, 'existing' => 'no-cache' ) + $page;
ok( 'machine' === sn_edge_cache_kind_for( $feed ), 'a feed trades Breeze\'s no-cache for the short lifetime' );
ok( 'machine' === sn_edge_cache_kind_for( array( 'existing' => '' ) + $feed ), 'a feed with no header gets it too' );
ok( '' === sn_edge_cache_kind_for( array( 'existing' => 'no-store, private' ) + $feed ) && '' === sn_edge_cache_kind_for( array( 'existing' => 'private' ) + $feed ), 'a feed that set no-store or private keeps it' );
ok( '' === sn_edge_cache_kind_for( array( 'anonymous' => false ) + $feed ) && '' === sn_edge_cache_kind_for( array( 'status' => 404 ) + $feed ), 'a feed read with a session, or a 404 feed: nothing' );

ok( '' === sn_edge_cache_kind_for( array( 'listed_feed' => false ) + $feed ), 'a feed outside the list (comments, a tag, an odd query form) keeps Breeze\'s no-cache: it could not be purged by name' );

echo "Group: the feed list\n";
$paths = sn_edge_cache_feed_paths();
ok( 13 === count( $paths ) && array() === array_diff( array( '/feed/', '/feed/rss2/', '/feed/rss/', '/feed/rdf/', '/feed/atom/', '/feed/json/', '/notes/feed/', '/notes/feed/atom/', '/notes/feed/rdf/', '/?feed=json' ), $paths ), 'the posts feed in every core format plus JSON at both bases, and the JSON feed\'s own query URL: 13 addresses' );
ok( sn_edge_cache_is_listed_feed( '/feed/', $paths ) && sn_edge_cache_is_listed_feed( '/notes/feed/atom', $paths ) && sn_edge_cache_is_listed_feed( '/?feed=json', $paths ), 'listed, with or without the trailing slash' );
ok( ! sn_edge_cache_is_listed_feed( '/comments/feed/', $paths ) && ! sn_edge_cache_is_listed_feed( '/tag/c2pa/feed/', $paths ) && ! sn_edge_cache_is_listed_feed( '/?feed=rdf', $paths ) && ! sn_edge_cache_is_listed_feed( '/feed/?utm_source=x', $paths ), 'comment and tag feeds, other query forms and a tracked URL are not' );

echo "Group: wiring\n";
ok( array( array( 'template_redirect', 'sn_edge_cache_template_redirect', 100 ) ) === $GLOBALS['__actions'], 'one hook: template_redirect at 100, after the redirect handlers and the virtual routes' );
$notes_src = (string) file_get_contents( __DIR__ . '/../inc/page-notes-template.php' );
$notes_at  = strpos( $notes_src, 'sn_edge_cache_template_redirect();' );
ok( false !== $notes_at && $notes_at < strrpos( $notes_src, 'include $render;' ), '/notes renders at priority 0 and exits, so its handler runs the policy before the include' );
foreach ( array( 'llms-txt.php' => 'sn_llms_txt_send', 'agents-manifest.php' => 'sn_agents_send', 'opensearch.php' => 'sn_opensearch_send' ) as $file => $fn ) {
	$src  = (string) file_get_contents( __DIR__ . '/../inc/' . $file );
	$body = (string) substr( $src, (int) strpos( $src, 'function ' . $fn . '(' ) );
	$body = (string) substr( $body, 0, (int) strpos( $body, "\n}\n" ) );
	ok( false !== strpos( $body, 'sn_edge_cache_send_machine();' ), $fn . '() sends the machine lifetime' );
}
$fn_src = (string) file_get_contents( __DIR__ . '/../functions.php' );
ok( false !== strpos( $fn_src, "require_once __DIR__ . '/inc/cache-headers.php';" ) && false !== strpos( $fn_src, "require_once __DIR__ . '/inc/cache-headers-rules.php';" ) && false !== strpos( $fn_src, "require_once __DIR__ . '/inc/cache-headers-hooks.php';" ), 'functions.php loads the three files' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
