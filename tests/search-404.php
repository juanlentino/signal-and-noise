<?php
/**
 * Tests: the 404 notes search is core/search, dressed by inc/search-404.php.
 * Real core HTML API; the input is what core 7.1's render_block_core_search()
 * prints for the template's attributes.
 *
 * Run: php tests/search-404.php
 */
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', '/' ); }
$pass = 0; $fail = 0;
function ok( $c, $m ) { global $pass, $fail; if ( $c ) { $pass++; echo "PASS: $m\n"; } else { $fail++; echo "FAIL: $m\n"; } }
$GLOBALS['__f'] = array();
function add_filter( $h, $cb, $p = 10, $n = 1 ) { $GLOBALS['__f'][ $h ] = $cb; return true; }
function home_url( $p = '/' ) { return 'https://x.test' . $p; }
require __DIR__ . '/lib/wp-html-api.php';
snt_require_wp_html_api();
require __DIR__ . '/../inc/search-404.php';

$core = '<search class="wp-block-search__button-outside wp-block-search__text-button sn-404-search wp-block-search"><form method="get" action="https://x.test/"><label class="wp-block-search__label screen-reader-text" for="wp-block-search__input-1">Search notes</label><div class="wp-block-search__inside-wrapper"><input class="wp-block-search__input" id="wp-block-search__input-1" placeholder="Search notes…" value="" type="search" name="s" required /><button aria-label="Search" class="wp-block-search__button wp-element-button" type="submit">Search</button></div></form></search>';
$out  = $GLOBALS['__f']['render_block_core/search']( $core, array( 'attrs' => array( 'className' => 'sn-404-search' ) ) );
$t    = new WP_HTML_Tag_Processor( $out );
$t->next_tag( 'SEARCH' );
ok( $t->has_class( 'sn-404-search' ), 'a <search> landmark carrying sn-404-search' );
$t->next_tag( 'FORM' );
ok( 'https://x.test/notes/' === $t->get_attribute( 'action' ) && 'get' === $t->get_attribute( 'method' ), 'the form submits to /notes/, where the theme answers ?s=' );
$t->next_tag( 'LABEL' );
ok( $t->has_class( 'screen-reader-text' ) && 'wp-block-search__input-1' === $t->get_attribute( 'for' ), 'the label is visually hidden and names the field' );
$t->next_tag( 'INPUT' );
ok( $t->has_class( 'sn-404-search__input' ) && 's' === $t->get_attribute( 'name' ) && 'Search notes…' === $t->get_attribute( 'placeholder' ), 'the field keeps the theme class, name=s and its placeholder' );
$t->next_tag( 'BUTTON' );
ok( $t->has_class( 'sn-404-search__submit' ) && ! $t->has_class( 'wp-element-button' ), 'the button takes the theme class and drops the element-button class' );
ok( false !== strpos( $out, '>Search</button>' ), 'the button still says Search' );
$other = '<search class="wp-block-search"><form action="https://x.test/"></form></search>';
ok( $other === $GLOBALS['__f']['render_block_core/search']( $other, array( 'attrs' => array() ) ), 'another search block is left alone' );
echo "Result: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
