<?php
/**
 * Standalone fixture tests for the custom blocks (v9.11.0).
 *
 * Validates the two dynamic blocks (signal-noise/sidenote, signal-noise/pull-quote):
 * block.json field correctness (apiVersion 3, registered editorScript handle —
 * NOT a file: path that loads with empty deps → "wp is undefined"), behavioral
 * render output (.sn-sidenote / .sn-pull-quote, slot omission when empty), and the
 * registration wiring (editor-script handle + deps, both block dirs, block category).
 * Mirrors tests/patterns-registry.php.
 *
 * @since theme v9.11.0
 */

// SECURITY: Prevent web access. Test fixture, not a runtime module.
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', '/' ); }
define( 'SN_BLOCKS_TEST', true );
$pass = 0; $fail = 0;
function ok( $c, $m ) { global $pass, $fail; if ( $c ) { $pass++; echo "PASS: $m\n"; } else { $fail++; echo "FAIL: $m\n"; } }

$GLOBALS['__reg_blocks'] = array(); $GLOBALS['__reg_scripts'] = array();
function add_action( $h, $cb, $p = 10, $a = 1 ) { return true; }
function add_filter( $h, $cb, $p = 10, $a = 1 ) { return true; }
function register_block_type( $dir ) { $GLOBALS['__reg_blocks'][] = $dir; return true; }
function wp_register_script( $h, $src, $deps = array(), $v = false, $f = false ) { $GLOBALS['__reg_scripts'][ $h ] = $deps; return true; }
function get_theme_file_uri( $p = '' ) { return 'https://x.test/' . $p; }
function wp_get_theme() { return new class { public function get( $k ) { return '9.11.0'; } }; }
function get_block_wrapper_attributes( $a = array() ) { return 'class="' . ( $a['class'] ?? '' ) . '"'; }
function wp_kses_post( $s ) { return $s; }

$blocks_dir = __DIR__ . '/../blocks';

// block.json validity + fields.
foreach ( array( 'sidenote', 'pull-quote' ) as $slug ) {
	$json = json_decode( file_get_contents( "$blocks_dir/$slug/block.json" ), true );
	ok( is_array( $json ), "$slug/block.json parses as JSON" );
	ok( $json['name'] === "signal-noise/$slug", "$slug name is signal-noise/$slug" );
	ok( (int) $json['apiVersion'] === 3, "$slug apiVersion 3" );
	ok( $json['editorScript'] === 'signal-noise-blocks-editor', "$slug editorScript is the registered handle (not a file: path)" );
	ok( $json['render'] === 'file:./render.php', "$slug uses a dynamic render.php" );
	ok( $json['category'] === 'signal-noise', "$slug in the signal-noise block category" );
	ok( strpos( $json['editorScript'], 'file:' ) === false, "$slug editorScript is NOT a file: path (empty-deps trap)" );
}

// Behavioral render: sidenote.
$attributes = array( 'content' => 'A margin note' ); $content = ''; $block = null;
ob_start(); include "$blocks_dir/sidenote/render.php"; $out = ob_get_clean();
ok( strpos( $out, 'sn-sidenote' ) !== false && strpos( $out, 'A margin note' ) !== false, 'sidenote render emits .sn-sidenote with content' );

// Behavioral render: pull-quote with both fields, then empty fields.
$attributes = array( 'body' => 'Thesis', 'attribution' => 'Author' );
ob_start(); include "$blocks_dir/pull-quote/render.php"; $pq = ob_get_clean();
ok( strpos( $pq, 'sn-pull-quote__body' ) !== false && strpos( $pq, 'sn-pull-quote__attribution' ) !== false, 'pull-quote renders both slots when set' );
ok( strpos( $pq, 'sn-pull-quote' ) !== false && strpos( $pq, 'sn-pattern-pull-quote' ) === false, 'pull-quote block uses .sn-pull-quote (CSS-matching), not the pattern class' );
$attributes = array( 'body' => 'Only body', 'attribution' => '' );
ob_start(); include "$blocks_dir/pull-quote/render.php"; $pq2 = ob_get_clean();
ok( strpos( $pq2, 'sn-pull-quote__attribution' ) === false, 'pull-quote omits the attribution slot when empty' );

// Registration wiring.
require __DIR__ . '/../inc/blocks-register.php';
signal_noise_register_block_editor_script();
signal_noise_register_blocks();
ok( isset( $GLOBALS['__reg_scripts']['signal-noise-blocks-editor'] ), 'editor script handle registered' );
$deps = $GLOBALS['__reg_scripts']['signal-noise-blocks-editor'];
foreach ( array( 'wp-blocks', 'wp-element', 'wp-block-editor' ) as $d ) {
	ok( in_array( $d, $deps, true ), "editor script depends on $d" );
}
ok( count( $GLOBALS['__reg_blocks'] ) === 2, 'both block dirs registered' );
$cats = signal_noise_block_category( array() );
ok( ! empty( array_filter( $cats, fn( $c ) => ( $c['slug'] ?? '' ) === 'signal-noise' ) ), 'block_categories_all adds a signal-noise category' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
