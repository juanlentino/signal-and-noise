<?php
/**
 * Standalone fixture tests for the RSS stylesheet route (v11.9.3).
 *
 * The load-bearing assertions:
 *  1. TYPE GUARD — a comment feed shares rss_tag_pre and must get nothing.
 *  2. NAMESPACE COMPLETENESS on assets/feed.xsl — every prefix used in an XPath
 *     expression must be declared on the stylesheet element. An undeclared
 *     prefix is a runtime transform failure the XML parser cannot catch,
 *     because it lives inside an attribute VALUE. This file shipped with
 *     exactly that bug in its first draft (atom: used, never declared).
 *  3. TOKEN RESOLUTION — no {{THEME_URI}} may survive into the served body, and
 *     what replaces it must be ABSOLUTE, because relative URLs in XSLT output
 *     resolve against the SOURCE document (the feed), not the stylesheet.
 *
 * Content-Type cannot be asserted here (PHP's header() is unstubbable), which
 * is exactly how the v11.9.2 octet-stream bug survived a green suite. The
 * header is verified live against the deployed route instead.
 *
 * @since theme v11.9.3
 */

if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', '/' ); }
define( 'SN_FEED_STYLESHEET_TEST', true );
$pass = 0; $fail = 0;
function ok( $c, $m ) { global $pass, $fail; if ( $c ) { $pass++; echo "PASS: $m\n"; } else { $fail++; echo "FAIL: $m\n"; } }

function add_action( $h, $cb, $p = 10, $a = 1 ) { return true; }
function add_feed( $n, $cb ) { $GLOBALS['__feeds'][ $n ] = $cb; return $n; }
function esc_url( $u ) { return htmlspecialchars( (string) $u, ENT_QUOTES ); }
function home_url( $p = '' ) { return 'https://x.test' . $p; }
function trailingslashit( $s ) { return rtrim( (string) $s, '/\\' ) . '/'; }
function get_theme_file_uri( $p = '' ) { return 'https://x.test/wp-content/themes/sn/' . $p; }
function get_theme_file_path( $p = '' ) { return __DIR__ . '/../' . $p; }
$GLOBALS['__filter'] = null;
function apply_filters( $tag, $value ) { return null === $GLOBALS['__filter'] ? $value : $GLOBALS['__filter']; }

require __DIR__ . '/../inc/feed-stylesheet.php';

echo "RSS feed stylesheet route (v11.9.3)\n\n";

// --- the PI --------------------------------------------------------------
ob_start(); sn_feed_stylesheet_pi( 'rss2' ); $out = ob_get_clean();
ok( strpos( $out, '<?xml-stylesheet' ) !== false, 'rss2 feed gets an xml-stylesheet PI' );
ok( strpos( $out, 'type="text/xsl"' ) !== false, 'PI declares type="text/xsl"' );
ok( strpos( $out, 'feed=xsl' ) !== false, 'PI points at the ?feed=xsl ROUTE, not a static .xsl file' );
ok( strpos( $out, '.xsl"?>' ) === false, 'PI no longer points at a static asset path (the octet-stream bug)' );

// --- the type guard ------------------------------------------------------
foreach ( array( 'rss2-comments', 'rss', 'atom', '' ) as $type ) {
	ob_start(); sn_feed_stylesheet_pi( $type ); $o = ob_get_clean();
	ok( '' === $o, "feed type '" . ( '' === $type ? '(empty)' : $type ) . "' emits NOTHING" );
}

// --- route registration --------------------------------------------------
$GLOBALS['__feeds'] = array();
sn_feed_stylesheet_register();
ok( isset( $GLOBALS['__feeds']['xsl'] ) && 'sn_feed_stylesheet_render' === $GLOBALS['__feeds']['xsl'],
	'init registers add_feed("xsl") — flush-free, live on a cold deploy' );

// --- the escape hatch ----------------------------------------------------
$GLOBALS['__filter'] = '';
ob_start(); sn_feed_stylesheet_pi( 'rss2' ); $o = ob_get_clean();
ok( '' === $o, 'filter returning an empty string restores the unstyled feed' );
$GLOBALS['__filter'] = 'https://x.test/a"b';
ob_start(); sn_feed_stylesheet_pi( 'rss2' ); $o = ob_get_clean();
ok( strpos( $o, 'a"b' ) === false, 'the href is escaped (a quote cannot break out of the attribute)' );
$GLOBALS['__filter'] = null;

// --- the stylesheet ------------------------------------------------------
$xsl_path = __DIR__ . '/../assets/feed.xsl';
ok( file_exists( $xsl_path ), 'assets/feed.xsl exists' );
$xsl = (string) file_get_contents( $xsl_path );
libxml_use_internal_errors( true );
$doc = new DOMDocument();
ok( $doc->loadXML( $xsl ), 'assets/feed.xsl is well-formed XML' );
libxml_clear_errors();

// --- TOKEN RESOLUTION ----------------------------------------------------
ok( strpos( $xsl, '{{THEME_URI}}' ) !== false, 'source carries the {{THEME_URI}} token' );
ok( strpos( $xsl, '/wp-content/themes/signal-and-noise/' ) === false,
	'source hardcodes no theme slug — paths follow the theme wherever it lives' );
$body = sn_feed_stylesheet_body( $xsl, get_theme_file_uri( '' ) );
ok( strpos( $body, '{{THEME_URI}}' ) === false, 'NO token survives into the served body' );
ok( substr_count( $body, 'https://x.test/wp-content/themes/sn/assets/fonts/' ) >= 1,
	'token resolves to an ABSOLUTE font base (relative would resolve against the FEED url)' );
preg_match_all( "/url\('([^']+)'\)/", $body, $um );
$rel = array_filter( $um[1], function ( $u ) { return 0 !== strpos( $u, 'http' ); } );
ok( array() === $rel, 'every url() in the served body is absolute' . ( $rel ? ' (relative: ' . implode( ',', $rel ) . ')' : '' ) );

// --- NAMESPACE COMPLETENESS ---------------------------------------------
preg_match_all( '/(?:select|test|match)="([^"]*)"/', $xsl, $m );
$used = array();
foreach ( $m[1] as $expr ) {
	if ( preg_match_all( '/([a-zA-Z][\w.-]*):[a-zA-Z]/', $expr, $pm ) ) {
		foreach ( $pm[1] as $p ) { $used[ $p ] = true; }
	}
}
preg_match_all( '/xmlns:([a-zA-Z][\w.-]*)=/', $xsl, $dm );
$missing = array_diff_key( $used, array_flip( $dm[1] ) );
ok( array() === $missing,
	'every XPath prefix used in feed.xsl is declared' . ( $missing ? ' (MISSING: ' . implode( ',', array_keys( $missing ) ) . ')' : '' ) );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
