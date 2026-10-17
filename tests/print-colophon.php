<?php
/**
 * Standalone test: the print colophon (inc/print-colophon.php,
 * blocks/print-colophon/).
 *
 * A printed note carries its own proof: the full SHA-256 of the signed text,
 * the version and status, and the verify and ledger addresses written out,
 * since a link on paper is only its text. Hidden on screen, shown in print.
 * The data is the companion plugin's public view-model (sn_prov_view_data);
 * without the plugin, or for a note with no chain, nothing renders.
 *
 * Run: php tests/print-colophon.php
 */

if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }
define( 'ABSPATH', '/' );

$pass = 0; $fail = 0;
function ok( $c, $m ) { global $pass, $fail; if ( $c ) { ++$pass; echo "PASS: $m\n"; } else { ++$fail; echo "FAIL: $m\n"; } }

function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $s ) { $s = (string) $s; return preg_match( '#^https?://#', $s ) ? str_replace( array( '"', '<', '>' ), array( '%22', '%3C', '%3E' ), $s ) : ''; }
function get_the_ID() { return 5; }
function get_permalink( $id = 0 ) { return 'https://juanlentino.com/notes/market-harm/'; }
function wp_date( $f, $ts ) { return gmdate( $f, (int) $ts ); }
function __( $s, $d = null ) { return $s; }

require_once __DIR__ . '/../inc/print-colophon.php';

$root = dirname( __DIR__ );
$hash = str_repeat( 'ab12', 16 );

echo "Group 1: degrades to nothing\n";
ok( ! function_exists( 'sn_prov_view_data' ) && '' === sn_print_colophon_html(), 'companion plugin absent (no sn_prov_view_data, checked): renders nothing' );

$GLOBALS['__vm'] = null;
if ( ! function_exists( 'sn_prov_view_data' ) ) { // in a block: not hoisted, so Group 1's first call really runs without it
	function sn_prov_view_data( $id ) { return $GLOBALS['__vm']; }
}
ok( '' === sn_print_colophon_html(), 'a note with no chain (view-model null): renders nothing' );
$GLOBALS['__vm'] = array( 'note_uid' => '', 'current_hash' => $hash, 'version' => 1, 'status' => 'confirmed', 'bitcoin_block' => 0, 'versions' => array(), 'is_genesis_only' => false, 'ledger_url' => 'https://github.com/x/y/tree/main/notes/u', 'verify_url' => 'https://juanlentino.com/verify' );
ok( '' === sn_print_colophon_html(), 'a view-model with no note uid: renders nothing (no record to point at)' );

echo "\nGroup 2: what the paper carries\n";
if ( true ) {
function sn_prov_status_label( $s ) { return array( 'confirmed' => 'Verified', 'pending' => 'Pending', 'genesis' => 'Genesis', 'unanchored' => 'Recording' )[ $s ] ?? 'Recording'; }
function sn_prov_present_status( $s, $root ) { return 'genesis' === $s ? ( 'confirmed' === $root ? array( 'state' => 'confirmed', 'label' => 'Verified' ) : array( 'state' => 'genesis', 'label' => 'Genesis' ) ) : array( 'state' => $s, 'label' => sn_prov_status_label( $s ) ); }
$GLOBALS['__root'] = array( 'status' => '', 'bitcoin_block' => 0 );
function sn_prov_genesis_root_state() { return $GLOBALS['__root']; }
}

$vm = array(
	'note_uid'        => '3f2b8c1e-0000-4000-8000-000000000001',
	'current_hash'    => $hash,
	'version'         => 3,
	'status'          => 'confirmed',
	'bitcoin_block'   => 912345,
	'versions'        => array( array( 'version' => 3, 'committed_at' => '2026-10-16T02:15:10Z' ) ),
	'is_genesis_only' => false,
	'ledger_url'      => 'https://github.com/juanlentino/signal-and-noise-provenance/tree/main/notes/3f2b8c1e-0000-4000-8000-000000000001',
	'verify_url'      => 'https://juanlentino.com/verify',
);
$GLOBALS['__vm'] = $vm;
$out = sn_print_colophon_html();
ok( 0 === strpos( $out, '<aside class="sn-print-colophon"' ), 'renders an <aside class="sn-print-colophon">' );
ok( false !== strpos( $out, '<code>' . $hash . '</code>' ), 'the FULL 64-character hash, not the panel\'s 12-character prefix' );
ok( false !== strpos( $out, 'version 3' ) && false !== strpos( $out, 'October 16, 2026' ), 'the version and the date it was recorded' );
ok( false !== strpos( $out, 'Verified' ) && false !== strpos( $out, 'block 912,345' ), 'the status label and the Bitcoin block' );
ok( false !== strpos( $out, 'https://juanlentino.com/verify?note=3f2b8c1e-0000-4000-8000-000000000001&amp;v=3' ), 'the verify address is written out with note= and v=, as text a reader can type' );
ok( false !== strpos( $out, '>https://github.com/juanlentino/signal-and-noise-provenance/tree/main/notes/3f2b8c1e-0000-4000-8000-000000000001<' ), 'the ledger address is written out' );
ok( false !== strpos( $out, '>https://juanlentino.com/notes/market-harm/<' ), 'the note\'s own address is written out' );
ok( false === strpos( $out, "\n" ), 'one line of markup, so the wpautop wrapper cannot insert <br> or <p> into it' );
ok( false === strpos( $out, "\u{2014}" ), 'no em dash in the printed copy' );

echo "\nGroup 3: the context-dependent cases\n";
$GLOBALS['__vm'] = array_merge( $vm, array( 'status' => 'genesis', 'bitcoin_block' => 0, 'version' => 0, 'is_genesis_only' => true ) );
$GLOBALS['__root'] = array( 'status' => 'confirmed', 'bitcoin_block' => 880001 );
$out = sn_print_colophon_html();
ok( false !== strpos( $out, 'Verified' ) && false === strpos( $out, 'Recording' ) && false === strpos( $out, '>Genesis<' ), 'a founding-snapshot note whose snapshot is anchored prints Verified (the panel\'s presentation), never Recording' );
ok( false !== strpos( $out, 'block 880,001' ), '   ...with the snapshot\'s Bitcoin block' );
ok( false !== strpos( $out, 'verify?note=3f2b8c1e-0000-4000-8000-000000000001' ) && false === strpos( $out, '&amp;v=' ), '   ...and a verify address without a version, as the chip does for a genesis-only note' );
$GLOBALS['__root'] = array( 'status' => '', 'bitcoin_block' => 0 );
$GLOBALS['__vm'] = array_merge( $vm, array( 'status' => 'pending', 'bitcoin_block' => 0, 'verify_url' => 'https://juanlentino.com/verify?kind=page' ) );
$out = sn_print_colophon_html();
ok( false !== strpos( $out, 'Pending' ) && false === strpos( $out, 'block ' ), 'a pending version prints Pending and no block' );
ok( false !== strpos( $out, 'verify?kind=page&amp;note=' ), 'a signed PAGE keeps its ?kind= and appends note= with &' );

echo "\nGroup 4: escaping\n";
$GLOBALS['__vm'] = array_merge( $vm, array( 'current_hash' => '<script>x</script>', 'note_uid' => 'a"><b>', 'ledger_url' => 'javascript:alert(1)' ) );
$out = sn_print_colophon_html();
ok( false === strpos( $out, '<script' ) && false === strpos( $out, '<b>' ), 'a tampered hash or uid renders as text, never as markup' );
ok( false === strpos( $out, 'javascript:' ), 'a non-http ledger URL is dropped, not printed or linked' );

echo "\nGroup 5: placement and the sheet\n";
$part = (string) file_get_contents( $root . '/parts/post-closing.html' );
ok( 1 === substr_count( $part, '<!-- wp:signal-noise/print-colophon /-->' ), 'parts/post-closing.html carries the block once' );
$bj = json_decode( (string) file_get_contents( $root . '/blocks/print-colophon/block.json' ), true );
ok( 'signal-noise/print-colophon' === ( $bj['name'] ?? '' ) && ! isset( $bj['attributes'] ) && 'file:./style.css' === ( $bj['style'] ?? '' ), 'block.json: no attributes (nothing to sign), its own sheet' );
$css = preg_replace( '~/\*.*?\*/~s', '', (string) file_get_contents( $root . '/blocks/print-colophon/style.css' ) );
ok( 1 === preg_match( '/^\s*\.sn-print-colophon\s*\{[^}]*display:\s*none/m', $css ), 'hidden on screen by default' );
ok( 1 === preg_match( '/@media\s+print\s*\{[^@]*\.sn-print-colophon\s*\{[^}]*display:\s*block/s', $css ), 'shown in print' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
