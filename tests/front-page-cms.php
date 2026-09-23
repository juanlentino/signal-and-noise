<?php
/**
 * 14.3.0: the front page renders its Page's content (editable like every page),
 * with the hero pattern as the fallback while that Page is empty.
 * Run: php tests/front-page-cms.php
 */
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }
define( 'ABSPATH', '/' );
$pass = 0; $fail = 0;
function ok( $c, $m ) { global $pass, $fail; if ( $c ) { $pass++; echo "PASS: $m\n"; } else { $fail++; echo "FAIL: $m\n"; } }
$root = dirname( __DIR__ );

$tpl = (string) file_get_contents( "$root/templates/front-page.html" );
ok( false !== strpos( $tpl, '<!-- wp:post-content' ), 'front-page.html renders the Page content' );
ok( false === strpos( $tpl, 'class="sn-hero' ), 'no hero markup left in the template' );

$pat = (string) file_get_contents( "$root/patterns/home-hero.php" );
ok( false !== strpos( $pat, 'Slug: signal-noise/home-hero' ) && false !== strpos( $pat, 'Inserter: no' ), 'the hero pattern is registered and kept out of the inserter' );
ok( false !== strpos( $pat, 'I BUILD THINGS<br>THAT SOUND RIGHT.' ) && false !== strpos( $pat, 'class="sn-hero-subtitle' ) && false !== strpos( $pat, 'href="/services"' ) && false !== strpos( $pat, 'href="/about"' ) && false !== strpos( $pat, 'sn-hero-accent' ), 'the pattern carries the whole hero: title, subtitle, line, both buttons' );
ok( false !== strpos( $pat, '<!-- wp:group {"className":"sn-hero-inner"' ) && false === strpos( $pat, '<div class="sn-hero-inner">' ), 'the inner wrapper is a Group, not an unbalanced raw-HTML div' );
$body = substr( $pat, strpos( $pat, '?>' ) + 2 );
ok( substr_count( $body, '<div' ) === substr_count( $body, '</div>' ) && substr_count( $body, '<!-- wp:group' ) === substr_count( $body, '<!-- /wp:group -->' ), 'the pattern balances' );

// The fallback.
$GLOBALS['front'] = true; $GLOBALS['content'] = '';
function is_front_page() { return $GLOBALS['front']; }
function get_queried_object_id() { return 383; }
function get_post_field( $f, $id ) { return $GLOBALS['content']; }
function do_blocks( $c ) { return 'RENDERED:' . $c; }
function add_filter() {}
class WP_Block_Patterns_Registry {
	public static function get_instance() { return new self(); }
	public function get_registered( $slug ) { return 'signal-noise/home-hero' === $slug ? array( 'content' => 'HERO' ) : null; }
}
require "$root/inc/front-page-fallback.php";
$empty = '<div class="entry-content wp-block-post-content"></div>';
ok( 'RENDERED:HERO' === sn_front_page_hero_fallback( $empty, array() ), 'empty front page: the hero pattern renders' );
$GLOBALS['content'] = '<!-- wp:image {"id":1} --><figure><img src="x.jpg" alt=""></figure><!-- /wp:image -->';
ok( $empty === sn_front_page_hero_fallback( $empty, array() ), 'a front page with content (even image-only) renders as saved' );
$GLOBALS['content'] = ''; $GLOBALS['front'] = false;
ok( $empty === sn_front_page_hero_fallback( $empty, array() ), 'any other page is untouched' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
