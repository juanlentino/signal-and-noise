<?php
/**
 * Tests: the header logo is core/site-title, dressed by inc/site-title-logo.php.
 *
 * The hand-written anchor carried class sn-wordmark on its text and a label of
 * the name, an em dash and "home". Core's block renders the name and
 * home_url(); the filter adds the class and the same label, and on a Spanish
 * page sends the
 * link to the Spanish home with the Spanish label. Real core render and real
 * HTML API.
 *
 * Run: php tests/site-title-logo.php
 */
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', '/' ); }

$pass = 0; $fail = 0;
function ok( $c, $m ) { global $pass, $fail; if ( $c ) { $pass++; echo "PASS: $m\n"; } else { $fail++; echo "FAIL: $m\n"; } }

$GLOBALS['__f'] = array();
function add_filter( $h, $cb, $p = 10, $n = 1 ) { $GLOBALS['__f'][ $h ] = $cb; return true; }
function get_bloginfo( $k ) { return 'Juan Lentino'; }
function sn_theme_is_spanish() { return ! empty( $GLOBALS['__es'] ); }
function sn_lang_spanish_url_for( $p ) { return 'https://x.test/inicio/'; }
require __DIR__ . '/lib/wp-html-api.php';
snt_require_wp_html_api();
require __DIR__ . '/../inc/site-title-logo.php';

// What core 7.1 renders for {"level":0,"isLink":true,"className":"sn-logo-link"} off the front page.
$core = '<p class="wp-block-site-title sn-logo-link"><a href="https://x.test" target="_self" rel="home">Juan Lentino</a></p>';
$cb   = $GLOBALS['__f']['render_block_core/site-title'];
$blk  = array( 'attrs' => array( 'className' => 'sn-logo-link' ) );

$en   = $cb( $core, $blk );
$tags = new WP_HTML_Tag_Processor( $en );
$tags->next_tag( 'P' );
ok( $tags->has_class( 'sn-logo-link' ), 'the wrapper keeps sn-logo-link (the padding box the header height is keyed to)' );
$tags->next_tag( 'A' );
ok( $tags->has_class( 'sn-wordmark' ), 'the link carries sn-wordmark, the class the wordmark CSS targets' );
ok( "Juan Lentino \u{2014} home" === $tags->get_attribute( 'aria-label' ), 'the accessible name is the one the old anchor had' );
ok( 'https://x.test' === $tags->get_attribute( 'href' ) && 'home' === $tags->get_attribute( 'rel' ), 'the link is home_url(), rel=home' );
ok( false !== strpos( $en, '>Juan Lentino</a>' ), 'the visible text is the site name' );

$GLOBALS['__es'] = true;
$tags = new WP_HTML_Tag_Processor( $cb( '<p class="sn-logo-link wp-block-site-title"><a rel="home" target="_self" href="https://x.test" aria-current="page">Juan Lentino</a></p>', $blk ) );
$tags->next_tag( 'A' );
ok( 'https://x.test/inicio/' === $tags->get_attribute( 'href' ) && 'Juan Lentino, inicio' === $tags->get_attribute( 'aria-label' ), 'Spanish: the Spanish home and label, whatever the attribute order' );
ok( 'page' === $tags->get_attribute( 'aria-current' ), "core's aria-current is kept" );
unset( $GLOBALS['__es'] );

$other = '<h1 class="wp-block-site-title"><a href="https://x.test">Juan Lentino</a></h1>';
ok( $other === $cb( $other, array( 'attrs' => array() ) ), 'a site-title without sn-logo-link is left alone' );

$crit = (string) file_get_contents( __DIR__ . '/../assets/css/critical.css' );
ok( 1 === preg_match( '/\.sn-wordmark\s*\{[^}]*text-decoration:\s*none/s', $crit ), 'the wordmark link is not underlined (the anchor used to carry text-decoration: none)' );

echo "Result: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
