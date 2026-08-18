<?php
/**
 * Standalone fixture tests for the RSS stylesheet PI (v11.10.0).
 *
 * Mirrors tests/feed-enrichment.php: stubs the WP primitives the callback
 * touches so inc/feed-stylesheet.php runs without a WordPress load.
 *
 * The load-bearing assertions are the TYPE GUARD (a comment feed must not get
 * the PI) and the NAMESPACE COMPLETENESS check on assets/feed.xsl — every
 * prefix used in an XPath expression must be declared on the stylesheet
 * element, or the transform fails at runtime and the reader sees nothing. That
 * is the same class of error tests/feed-enrichment.php pins as RSS-1, and it
 * was live in this file's first draft (atom: used, never declared).
 *
 * @since theme v11.10.0
 */

if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', '/' ); }
define( 'SN_FEED_STYLESHEET_TEST', true );
$pass = 0; $fail = 0;
function ok( $c, $m ) { global $pass, $fail; if ( $c ) { $pass++; echo "PASS: $m\n"; } else { $fail++; echo "FAIL: $m\n"; } }

function add_action( $h, $cb, $p = 10, $a = 1 ) { return true; }
function esc_url( $u ) { return htmlspecialchars( (string) $u, ENT_QUOTES ); }
$GLOBALS['__filter'] = null;
function apply_filters( $tag, $value ) {
	return null === $GLOBALS['__filter'] ? $value : $GLOBALS['__filter'];
}
function get_theme_file_uri( $p = '' ) { return 'https://x.test/wp-content/themes/sn/' . $p; }

require __DIR__ . '/../inc/feed-stylesheet.php';

echo "RSS feed stylesheet (v11.10.0)\n\n";

// --- the PI itself -------------------------------------------------------
ob_start(); sn_feed_stylesheet_pi( 'rss2' ); $out = ob_get_clean();
ok( strpos( $out, '<?xml-stylesheet' ) !== false, 'rss2 feed gets an xml-stylesheet PI' );
ok( strpos( $out, 'type="text/xsl"' ) !== false, 'PI declares type="text/xsl"' );
ok( strpos( $out, 'assets/feed.xsl' ) !== false, 'PI points at assets/feed.xsl' );
ok( substr( trim( $out ), -2 ) === '?>', 'PI is closed with ?>' );

// --- THE TYPE GUARD. A comment feed shares the rss_tag_pre hook. ---------
foreach ( array( 'rss2-comments', 'rss', 'atom', '' ) as $type ) {
	ob_start(); sn_feed_stylesheet_pi( $type ); $o = ob_get_clean();
	ok( '' === $o, "feed type '" . ( '' === $type ? '(empty)' : $type ) . "' emits NOTHING" );
}

// --- the escape hatch ----------------------------------------------------
$GLOBALS['__filter'] = '';
ob_start(); sn_feed_stylesheet_pi( 'rss2' ); $o = ob_get_clean();
ok( '' === $o, 'filter returning an empty string restores the unstyled feed' );
$GLOBALS['__filter'] = 'https://x.test/a"b.xsl';
ob_start(); sn_feed_stylesheet_pi( 'rss2' ); $o = ob_get_clean();
ok( strpos( $o, 'a"b.xsl' ) === false, 'the href is escaped (a quote cannot break out of the attribute)' );
$GLOBALS['__filter'] = null;

// --- the stylesheet is well-formed XML -----------------------------------
$xsl_path = __DIR__ . '/../assets/feed.xsl';
ok( file_exists( $xsl_path ), 'assets/feed.xsl exists' );
$xsl = (string) file_get_contents( $xsl_path );
libxml_use_internal_errors( true );
$doc = new DOMDocument();
ok( $doc->loadXML( $xsl ), 'assets/feed.xsl is well-formed XML' );
libxml_clear_errors();

// --- NAMESPACE COMPLETENESS (the bug this file shipped with, once) -------
// Collect every prefix used in an XPath-bearing attribute, then require each
// one to be declared on the stylesheet element. An undeclared prefix is a
// runtime transform failure the XML parser will NOT catch, because it lives
// inside an attribute VALUE.
preg_match_all( '/(?:select|test|match)="([^"]*)"/', $xsl, $m );
$used = array();
foreach ( $m[1] as $expr ) {
	if ( preg_match_all( '/([a-zA-Z][\w.-]*):[a-zA-Z]/', $expr, $pm ) ) {
		foreach ( $pm[1] as $p ) { $used[ $p ] = true; }
	}
}
preg_match_all( '/xmlns:([a-zA-Z][\w.-]*)=/', $xsl, $dm );
$declared = array_flip( $dm[1] );
$missing = array_diff_key( $used, $declared );
ok( array() === $missing,
	'every XPath prefix used in feed.xsl is declared' . ( $missing ? ' (MISSING: ' . implode( ',', array_keys( $missing ) ) . ')' : '' ) );
ok( isset( $used['sn'] ) && isset( $declared['sn'] ),
	'the sn: prefix is both used (reading time) and declared' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
