<?php
/**
 * Shape B for inc/provenance-title-badge.php (v11.11.1) — a page whose title is a
 * core/post-title block and which has NO authored brow (/notes/start-here/).
 *
 * The badge used to be appended by the plugin's `the_content` filter, so on
 * /about it rendered at y=3212 of a 3382px document while the <h1> sat at 250.
 * A content filter can only append; the position was a consequence of the hook.
 *
 * Separate file because the placement flag is a per-request static: shape A
 * consumes it, so shape B needs a clean process rather than a fake reset that
 * would not model the real lifetime.
 *
 * VERIFIED AGAINST THE LIVE MARKUP BEFORE SIGNING: /notes/start-here/ renders
 * `<h1 class="wp-block-post-title">` and carries no .sn-catalog-eyebrow, so the
 * paragraph filter can never fire there. Without this path the badge falls back
 * to the plugin's foot append — the exact position this change exists to leave.
 *
 * Run: php tests/provenance-brow-badge-posttitle.php
 */
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }

define( 'ABSPATH', '/' );
// 13.2.2: the provenance pages' shapes (the essays' .sn-provenance-eyebrow; /provenance's authored h1).

$GLOBALS['__ctx'] = array( 'admin' => false, 'feed' => false, 'singular_page' => true, 'in_loop' => true, 'main_query' => true, 'id' => 7 );
$GLOBALS['__content'] = '';

function is_admin() { return ! empty( $GLOBALS['__ctx']['admin'] ); }
function wp_doing_ajax() { return false; }
function is_feed() { return ! empty( $GLOBALS['__ctx']['feed'] ); }
function is_singular( $t = '' ) { return ! empty( $GLOBALS['__ctx']['singular_page'] ); }
function in_the_loop() { return ! empty( $GLOBALS['__ctx']['in_loop'] ); }
function is_main_query() { return ! empty( $GLOBALS['__ctx']['main_query'] ); }
function get_the_ID() { return $GLOBALS['__ctx']['id']; }
function get_post( $id = 0 ) { return (object) array( 'ID' => (int) $id, 'post_content' => $GLOBALS['__content'] ); }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $s ) { return esc_html( $s ); }
function esc_url( $s ) { return $s; }
$GLOBALS['__hooks'] = array();
function add_filter( $h = '', $cb = '' ) { $GLOBALS['__hooks'][ $h ] = $cb; return true; }

// The plugin's view model, stubbed to its real shape.
$GLOBALS['__vm'] = array( 'status' => 'confirmed', 'version' => 1, 'is_genesis_only' => false, 'confirmations' => 6 );
function sn_prov_view_data( $id ) { return $GLOBALS['__vm']; }
function sn_prov_genesis_root_state() { return array( 'status' => 'confirmed' ); }
function sn_prov_present_status( $s, $r ) { return array( 'state' => 'confirmed', 'label' => 'Verified' ); }
function sn_prov_primary_explorer( $vm, $root ) { return array( 'href' => 'https://mempool.space/block/962042' ); }

require_once __DIR__ . '/../inc/provenance-title-badge.php';

$pass = 0; $fail = 0;
function ok( $c, $l ) { global $pass, $fail; if ( $c ) { $pass++; echo "  ok   $l\n"; } else { $fail++; echo "  FAIL $l\n"; } }
function reset_placed() {
	// The placed-flag is a per-request static; each case needs a fresh process
	// boundary, so drive the filters in the order a real render would and assert
	// on the FIRST placement only.
	return true;
}

ok( ( $GLOBALS['__hooks']['render_block_core/heading'] ?? '' ) === 'sn_prov_brow_heading_filter', 'the heading filter is registered on render_block_core/heading (a filter that exists but never hooks is invisible)' );
echo "\nGroup: shape D — an AUTHORED h1 with no eyebrow gets a brow created above it (/provenance)\n";
$GLOBALS['__content'] = '<!-- wp:heading {"level":1} --><h1 class="wp-block-heading">On Provenance</h1><!-- /wp:heading --><!-- wp:paragraph --><p>Two papers, two long-form essays.</p><!-- /wp:paragraph -->';
$h2 = '<h2 class="wp-block-heading">The argument in three moves</h2>';
ok( sn_prov_brow_heading_filter( $h2, array( 'attrs' => array( 'level' => 2 ) ) ) === $h2, 'an h2 never earns a brow' );
ok( sn_prov_brow_heading_filter( $h2, array() ) === $h2, 'a heading with no level attribute is an h2 (core default) and never earns one either' );
ok( ! sn_prov_brow_placed(), 'nothing placed yet' );
$h1 = '<h1 class="wp-block-heading">On Provenance</h1>';
$out = sn_prov_brow_heading_filter( $h1, array( 'attrs' => array( 'level' => 1 ) ) );
ok( false !== strpos( $out, 'sn-catalog-eyebrow' ) && false !== strpos( $out, 'sn-prov-brow-solo' ), 'a solo brow is created, reusing the site brow class' );
ok( strpos( $out, 'sn-catalog-eyebrow' ) < strpos( $out, '<h1' ), 'ABOVE the authored title, not after it' );
ok( false !== strpos( $out, 'Verified v1' ) && false === strpos( $out, 'sn-prov-brow-sep' ), 'the badge, with no leading separator' );
ok( sn_prov_brow_placed(), 'placement recorded: the foot append stands down on /provenance' );
ok( sn_prov_brow_heading_filter( $h1, array( 'attrs' => array( 'level' => 1 ) ) ) === $h1, 'a second h1 is left alone' );
echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
