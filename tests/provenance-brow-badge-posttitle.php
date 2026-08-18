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

echo "\nGroup: shape B — no brow exists, so one is CREATED\n";
$title = '<h1 class="wp-block-post-title">Start Here</h1>';
$GLOBALS['__content'] = '<!-- wp:paragraph --><p>Body copy, no eyebrow.</p><!-- /wp:paragraph -->';
$out = sn_prov_brow_title_filter( $title );
ok( false !== strpos( $out, 'sn-catalog-eyebrow' ), 'a brow line is created, reusing the site brow class' );
ok( false !== strpos( $out, 'sn-prov-brow-solo' ), 'and marked solo, so a reader of the CSS knows which case this is' );
ok( strpos( $out, 'sn-catalog-eyebrow' ) < strpos( $out, '<h1' ), 'the brow renders ABOVE the title, not after it' );
ok( false !== strpos( $out, 'Verified v1' ), 'the badge is in it' );
ok( false === strpos( $out, 'sn-prov-brow-sep' ), 'no leading separator — there is nothing to separate it from' );
ok( sn_prov_brow_placed(), 'placement recorded, so the plugin foot append stands down' );
ok( substr_count( $out, 'sn-catalog-eyebrow' ) === 1, 'exactly one brow' );

echo "\nGroup: it refuses to stack a SECOND brow\n";
// A post-title block renders BEFORE content paragraphs, so this filter cannot
// rely on the placed-flag to know an authored brow is coming. It asks the
// content directly. Fresh flag state is simulated by the content check alone.
$GLOBALS['__content'] = '<!-- wp:paragraph --><p class="sn-catalog-eyebrow">Dossier · Who I Am</p><!-- /wp:paragraph -->';
$out2 = sn_prov_brow_title_filter( $title );
ok( $out2 === $title, 'a page WITH an authored brow is left untouched here — the paragraph filter owns it' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
