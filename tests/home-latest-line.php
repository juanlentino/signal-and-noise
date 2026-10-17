<?php
/**
 * Standalone test: the home page's latest line, playhead and one-screen fit
 * (inc/home-latest-line.php, assets/css/critical.css, 2026-10-17). The owner
 * wanted more on home without a scroll: one line naming the newest note sits
 * on the pulse, a blood playhead marks the pulse's "now" end, and home is
 * exactly one screen.
 *
 * Run: php tests/home-latest-line.php
 */
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }
define( 'ABSPATH', '/' );
define( 'SN_HOME_LATEST_TEST', true );
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_url( $u ) { return htmlspecialchars( (string) $u, ENT_QUOTES ); }
function get_permalink( $p ) { return 'https://x.test/notes/' . $p->post_name . '/'; }
function get_the_title( $p ) { return $p->post_title; }
function get_post_time( $f, $gmt, $p ) { return gmdate( $f, strtotime( $p->post_date ) ); }
function get_posts( $a ) { $GLOBALS['__q'] = $a; return $GLOBALS['__latest']; }
function sn_notes_catalog_number( $id ) { return 55; }
function sn_theme_is_spanish() { return ! empty( $GLOBALS['__es'] ); }
function sn_hero_pulse_is_home() { return ! empty( $GLOBALS['__home'] ); }
function get_queried_object_id() { return 383; }
require dirname( __DIR__ ) . '/inc/home-latest-line.php';

$pass = 0; $fail = 0;
function ok( $c, $m ) { global $pass, $fail; if ( $c ) { ++$pass; echo "PASS: $m\n"; } else { ++$fail; echo "FAIL: $m\n"; } }

$note = (object) array( 'ID' => 9, 'post_name' => 'the-credit', 'post_title' => 'The credit that has to <stay> private', 'post_date' => '2026-10-16 09:00:00' );
$GLOBALS['__latest'] = array( $note );

$h = sn_home_latest_line_html();
ok( 0 === strpos( $h, '<a class="sn-home-latest" href="https://x.test/notes/the-credit/">' ), 'one link to the newest note' );
ok( false !== strpos( $h, '&#8470; 055' ), 'its catalog number, padded like the notes index' );
ok( false !== strpos( $h, 'The credit that has to &lt;stay&gt; private' ), 'its title, escaped' );
ok( false !== strpos( $h, '2026.10.16' ), 'its date in the index format' );
ok( false !== strpos( $h, '>Latest<' ), 'labelled Latest' );
ok( 'post' === ( $GLOBALS['__q']['post_type'] ?? '' ) && 'publish' === ( $GLOBALS['__q']['post_status'] ?? '' ) && 1 === ( $GLOBALS['__q']['posts_per_page'] ?? 0 ), 'the newest published note, one row' );
$GLOBALS['__es'] = true;
ok( false !== strpos( sn_home_latest_line_html(), '>Lo último<' ), 'the Spanish home says Lo último' );
$GLOBALS['__es'] = false;
$GLOBALS['__latest'] = array();
ok( '' === sn_home_latest_line_html(), 'no notes, no line' );
$GLOBALS['__latest'] = array( $note );

$GLOBALS['__home'] = true;
$inst = (object) array( 'context' => array( 'postId' => 383 ) );
$out  = sn_home_latest_line_filter( '<div class="wp-block-post-content">hero</div>', array(), $inst );
ok( 0 === strpos( $out, '<div class="wp-block-post-content">hero</div><a class="sn-home-latest"' ), 'it follows the post-content element, never inside it' );
ok( '<x>' === sn_home_latest_line_filter( '<x>', array(), $inst ), 'once per request' );
sn_home_latest_line_reset();
ok( '<x>' === sn_home_latest_line_filter( '<x>', array(), (object) array( 'context' => array( 'postId' => 7 ) ) ), 'a secondary loop is untouched' );
$GLOBALS['__home'] = false;
ok( '<x>' === sn_home_latest_line_filter( '<x>', array(), $inst ), 'any page but home is untouched' );

$crit = (string) file_get_contents( dirname( __DIR__ ) . '/assets/css/critical.css' );
ok( 1 === preg_match( '/\.sn-hero::before\s*\{[^}]*background(-color)?:\s*var\(--wp--preset--color--blood\)/', $crit ), 'the playhead is a blood hairline' );
ok( false === strpos( $crit, '.sn-hero::before { content' ) && false === strpos( $crit, ".sn-hero::before {\n\tcontent" ), 'the static half carries no content: the pulse rule gives it, so no pulse means no playhead' );
ok( 1 === preg_match( '/@media\s*\(forced-colors:\s*active\)\s*\{[^{}]*\.sn-hero::after,\s*\.sn-hero::before\s*\{[^}]*display:\s*none/s', $crit ), 'forced colors drop it with the pulse' );
ok( 1 === preg_match( '/body:has\(\.sn-hero\) \.wp-site-blocks\s*\{[^}]*min-height:\s*calc\(100dvh - var\(--sn-chrome-top\)\)/', $crit ), 'home is a column exactly one screen tall' );
ok( 1 === preg_match( '/body:has\(\.sn-hero\) \.sn-hero\s*\{[^}]*flex:\s*1 0 auto;[^}]*min-height:\s*0 !important/', $crit ), 'the hero fills what the chrome leaves, and never less than its content' );
ok( 1 === preg_match( '/body:has\(\.sn-hero\) \.wp-site-blocks > main\s*\{[^}]*padding-bottom:\s*var\(--sn-chrome-bottom\) !important/', $crit ), 'main reserves only the fixed footer, not 140px' );
ok( false !== strpos( $crit, '.sn-home-latest' ), 'the latest line is styled above the fold' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
