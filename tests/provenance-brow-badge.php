<?php
/**
 * Standalone fixture tests for inc/provenance-title-badge.php (v11.11.0/.1).
 *
 * The badge used to be appended by the plugin's `the_content` filter, so on
 * /about it rendered at y=3212 of a 3382px document while the <h1> sat at 250.
 * A content filter can only append; the position was a consequence of the hook.
 *
 * Two signed Pages have OPPOSITE shapes, and one filter cannot serve both —
 * which is the thing most likely to regress here:
 *
 *   /about              authored core/heading + an authored .sn-catalog-eyebrow
 *   /notes/start-here/  a core/post-title block and NO eyebrow at all
 *
 * Run: php tests/provenance-brow-badge.php
 */
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }

define( 'ABSPATH', '/' );

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
function add_filter() { return true; }

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

echo "\nGroup: the segment\n";
$seg = sn_prov_brow_segment( 7 );
ok( false !== strpos( $seg, 'Verified v1' ), 'label and version render' );
ok( false !== strpos( $seg, 'sn-prov-brow-sep' ), 'joining an existing brow leads with a separator' );
ok( false === strpos( sn_prov_brow_segment( 7, false ), 'sn-prov-brow-sep' ), 'standalone mode drops the separator — it IS the brow, not a second segment' );
ok( false !== strpos( $seg, 'mempool.space' ), 'links to the explorer' );
$GLOBALS['__vm']['is_genesis_only'] = true;
ok( false === strpos( sn_prov_brow_segment( 7 ), 'v1' ), 'a genesis-only page shows NO version — inventing v1 would claim an edit history it does not have' );
$GLOBALS['__vm']['is_genesis_only'] = false;

echo "\nGroup: shape A — an authored brow to join (/about)\n";
$para = '<p class="sn-catalog-eyebrow wp-block-paragraph">Dossier · Who I Am</p>';
$out  = sn_prov_brow_filter( $para );
ok( false !== strpos( $out, 'Dossier' ) && false !== strpos( $out, 'Verified v1' ), 'the badge joins the existing brow line' );
ok( strpos( $out, 'Dossier' ) < strpos( $out, 'Verified' ), 'designation first, proof second' );
ok( substr_count( $out, '</p>' ) === 1, 'it stays INSIDE the paragraph — one line, not two' );
ok( sn_prov_brow_placed(), 'placement is recorded, so the plugin foot append stands down' );

// Once placed, nothing else may add a second badge.
ok( sn_prov_brow_filter( $para ) === $para, 'a second eyebrow paragraph is left alone' );
ok( sn_prov_brow_title_filter( '<h1 class="wp-block-post-title">X</h1>' ) === '<h1 class="wp-block-post-title">X</h1>', 'and the title filter stands down too' );

echo "\nGroup: gating\n";
$GLOBALS['__ctx']['admin'] = true;
ok( 0 === sn_prov_brow_page_id(), 'wp-admin: no placement' );
$GLOBALS['__ctx']['admin'] = false;
$GLOBALS['__ctx']['feed'] = true;
ok( 0 === sn_prov_brow_page_id(), 'feeds: no placement' );
$GLOBALS['__ctx']['feed'] = false;
$GLOBALS['__ctx']['main_query'] = false;
ok( 0 === sn_prov_brow_page_id(), 'non-main query: no placement' );
$GLOBALS['__ctx']['main_query'] = true;
ok( 7 === sn_prov_brow_page_id(), 'a reader-facing singular page: placement allowed' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
