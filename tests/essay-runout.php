<?php
/**
 * Standalone test: a pillar essay ends on a runout line (inc/essay-runout.php,
 * 2026-10-17): the signed record's own fields, after the post-content element,
 * so nothing inside the signed region changes. Real fields only, or nothing.
 *
 * Run: php tests/essay-runout.php
 */
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }
define( 'ABSPATH', '/' );
define( 'SN_ESSAY_RUNOUT_TEST', true );
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_url( $u ) { return htmlspecialchars( (string) $u, ENT_QUOTES ); }
function esc_attr__( $s, $d = null ) { return esc_attr( $s ); }
function add_query_arg( $k, $v, $u ) { return $u . ( false === strpos( $u, '?' ) ? '?' : '&' ) . $k . '=' . rawurlencode( $v ); }
function sn_pillar_eyebrow_page_id( $b, $i = null ) { return $GLOBALS['__page']; }
function sn_pillar_eyebrow_designation( $id ) { return 7 === $id ? '1.00' : ''; }
function sn_prov_view_data( $id ) { return $GLOBALS['__vm']; }
require dirname( __DIR__ ) . '/inc/essay-runout.php';

$pass = 0; $fail = 0;
function ok( $c, $m ) { global $pass, $fail; if ( $c ) { ++$pass; echo "PASS: $m\n"; } else { ++$fail; echo "FAIL: $m\n"; } }

$vm = array( 'note_uid' => 'c316c35d-f5ea-424a-a752-ef1d5feec74d', 'version' => 2, 'status' => 'anchored', 'bitcoin_block' => 918204, 'versions' => array( array( 'committed_at' => '2026-05-07T14:02:11Z' ), array( 'committed_at' => '2026-09-30T08:00:00Z' ) ), 'verify_url' => 'https://x.test/verify?kind=page' );
$GLOBALS['__vm'] = $vm;
$h = sn_essay_runout_html( 7 );
ok( 0 === strpos( $h, '<p class="sn-essay-runout">' ), 'a runout line' );
ok( false !== strpos( strip_tags( $h ), 'c316c35d' ) && false === strpos( strip_tags( $h ), 'f5ea' ), 'the record\'s own id, short' );
ok( false !== strpos( $h, 'v2' ) && false !== strpos( $h, 'cut 2026-09-30' ), 'the version and the date of the latest cut' );
ok( false !== strpos( $h, 'block 918204' ), 'the anchoring block when it is anchored' );
ok( false !== strpos( $h, 'href="https://x.test/verify?kind=page&amp;note=c316c35d-f5ea-424a-a752-ef1d5feec74d"' ), 'it links to verify this record' );
$GLOBALS['__vm'] = array_merge( $vm, array( 'status' => 'pending', 'bitcoin_block' => 0 ) );
ok( false === strpos( sn_essay_runout_html( 7 ), 'block' ) && false !== strpos( sn_essay_runout_html( 7 ), 'anchoring' ), 'no block number before there is one' );
$GLOBALS['__vm'] = array_merge( $vm, array( 'version' => 0 ) );
ok( '' === sn_essay_runout_html( 7 ), 'genesis only: nothing to verify yet, so no runout' );
$GLOBALS['__vm'] = null;
ok( '' === sn_essay_runout_html( 7 ), 'an unsigned essay has no runout' );
$GLOBALS['__vm'] = array_merge( $vm, array( 'note_uid' => '' ) );
ok( '' === sn_essay_runout_html( 7 ), 'no record id, no runout' );

$GLOBALS['__vm'] = $vm;
$GLOBALS['__page'] = 7;
$out = sn_essay_runout_filter( '<div class="wp-block-post-content">signed</div>', array( 'blockName' => 'core/post-content' ) );
ok( 0 === strpos( $out, '<div class="wp-block-post-content">signed</div><p class="sn-essay-runout">' ), 'it follows the post-content element, never inside it' );
$GLOBALS['__page'] = 8;
ok( 'x' === sn_essay_runout_filter( 'x', array() ), 'a page that is not a pillar is untouched' );
$GLOBALS['__page'] = 0;
ok( 'x' === sn_essay_runout_filter( 'x', array() ), 'a render that is not the queried page is untouched' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
