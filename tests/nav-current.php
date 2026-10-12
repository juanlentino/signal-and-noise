<?php
/**
 * The header nav marks the page you are on with aria-current="page".
 *
 * WHY (2026.10, live audit P3-2): core sets aria-current only for
 * navigation-link blocks of kind "post-type" with an id. The header's links
 * are kind "custom" (parts/header.html), so no page ever announced itself as
 * current: a screen reader user on /about/ heard "About, link" like any other.
 *
 * Run: php tests/nav-current.php
 */

if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) {
	http_response_code( 404 );
	exit;
}

define( 'ABSPATH', '/' );
require __DIR__ . '/lib/wp-html-api.php';
snt_require_wp_html_api();

$GLOBALS['filters'] = array();
function add_filter( $tag, $cb, $p = 10, $a = 1 ) { $GLOBALS['filters'][ $tag ] = $cb; }
function home_url( $path = '' ) { return 'https://juanlentino.com' . $path; }
function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }
function wp_unslash( $v ) { return $v; }

require __DIR__ . '/../inc/nav-current.php';

$pass = 0;
$fail = 0;
function ok( $c, $m ) {
	global $pass, $fail;
	if ( $c ) { ++$pass; echo "PASS: $m\n"; } else { ++$fail; echo "FAIL: $m\n"; }
}

function nav_link( $url, $request ) {
	$_SERVER['REQUEST_URI'] = $request;
	$html  = '<li class="wp-block-navigation-item wp-block-navigation-link"><a class="wp-block-navigation-item__content"  href="' . $url . '"><span class="wp-block-navigation-item__label">X</span></a></li>';
	$block = array( 'blockName' => 'core/navigation-link', 'attrs' => array( 'url' => $url, 'kind' => 'custom' ) );
	return $GLOBALS['filters']['render_block_core/navigation-link']( $html, $block );
}
function is_current( $html ) {
	$p = new WP_HTML_Tag_Processor( $html );
	$p->next_tag( 'a' );
	return 'page' === $p->get_attribute( 'aria-current' );
}

ok( isset( $GLOBALS['filters']['render_block_core/navigation-link'] ), 'the filter is registered on render_block_core/navigation-link' );
ok( is_current( nav_link( '/about', '/about/' ) ), '/about link on /about/ is aria-current="page" (trailing slash ignored)' );
ok( is_current( nav_link( '/about', '/about/?utm_source=x' ) ), 'a query string does not change which page you are on' );
ok( ! is_current( nav_link( '/about', '/music/' ) ), '/about link on /music/ is not current' );
ok( ! is_current( nav_link( '/notes', '/notes/some-note/' ) ), 'a child URL is not "this page": /notes is not current on a single note' );
ok( ! is_current( nav_link( '/', '/about/' ) ), '"/" is never a prefix of everything' );
ok( '/' === sn_nav_current_path( '/' ) && nav_link( '/', '/' ) === nav_link( '/', '/about/' ), 'the home page renders untouched: no aria-current on any link there (owner rule: Page 383 is off-limits, even inside a site-wide change)' );
ok( ! is_current( nav_link( '/about', '/?x=1' ) ), 'nothing is marked on the front page, even with a query string' );
ok( ! is_current( nav_link( 'https://example.com/about', '/about/' ) ), 'an off-site link with the same path is never current' );
ok( is_current( nav_link( 'https://juanlentino.com/about/', '/about/' ) ), 'an absolute on-site link matches like a relative one' );
ok( 1 === substr_count( nav_link( '/about', '/about/' ), 'aria-current' ), 'set once, on the link only' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
