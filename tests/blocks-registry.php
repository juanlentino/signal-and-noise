<?php
/**
 * Standalone fixture tests for the custom blocks (v9.11.0).
 *
 * Validates the theme's dynamic blocks (signal-noise/sidenote,
 * signal-noise/pull-quote, and since v10.47.0 signal-noise/pillar-essays):
 * block.json field correctness (apiVersion 3, registered editorScript handle —
 * NOT a file: path that loads with empty deps → "wp is undefined"), behavioral
 * render output (.sn-sidenote / .sn-pull-quote, slot omission when empty), and the
 * registration wiring (editor-script handle + deps, all block dirs, block category).
 * Pillar-essays render behavior lives in tests/pillar-essays-block.php.
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
foreach ( array( 'sidenote', 'pull-quote', 'pillar-essays' ) as $slug ) {
	$json = json_decode( file_get_contents( "$blocks_dir/$slug/block.json" ), true );
	ok( is_array( $json ), "$slug/block.json parses as JSON" );
	ok( $json['name'] === "signal-noise/$slug", "$slug name is signal-noise/$slug" );
	ok( (int) $json['apiVersion'] === 3, "$slug apiVersion 3" );
	ok( $json['editorScript'] === 'signal-noise-blocks-editor', "$slug editorScript is the registered handle (not a file: path)" );
	ok( $json['render'] === 'file:./render.php', "$slug uses a dynamic render.php" );
	ok( $json['category'] === 'signal-noise', "$slug in the signal-noise block category" );
	ok( strpos( $json['editorScript'], 'file:' ) === false, "$slug editorScript is NOT a file: path (empty-deps trap)" );
	// A DYNAMIC block's render_callback receives ONLY comment-delimiter attrs +
	// defaults — WP does NOT source `source:html` attributes server-side (verified
	// vs wp-includes/class-wp-block.php). A source:html attr here would arrive
	// EMPTY in render.php and drop all content on the front end. Lock it to plain.
	foreach ( (array) ( $json['attributes'] ?? array() ) as $attr_name => $attr_def ) {
		ok( ! isset( $attr_def['source'] ), "$slug/$attr_name is a plain attribute (no source:html → populated in render.php server-side)" );
	}
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

// The block emits .sn-pull-quote; the brutalist BOX (rules/padding/background)
// must target that class, not only the pattern's .sn-pattern-pull-quote — else
// the block renders as bare text. Assert critical.css carries a .sn-pull-quote
// box selector (matches ".sn-pull-quote {" or ".sn-pull-quote," — NOT __body).
$critical_css = file_get_contents( __DIR__ . '/../assets/css/critical.css' );
ok( preg_match( '/\.sn-pull-quote\s*[,{]/', $critical_css ) === 1, 'critical.css has a .sn-pull-quote box selector (block gets the brutalist frame)' );

// Registration wiring.
require __DIR__ . '/../inc/blocks-register.php';
signal_noise_register_block_editor_script();
signal_noise_register_blocks();
ok( isset( $GLOBALS['__reg_scripts']['signal-noise-blocks-editor'] ), 'editor script handle registered' );
$deps = $GLOBALS['__reg_scripts']['signal-noise-blocks-editor'];
foreach ( array( 'wp-blocks', 'wp-element', 'wp-block-editor' ) as $d ) {
	ok( in_array( $d, $deps, true ), "editor script depends on $d" );
}
ok( count( $GLOBALS['__reg_blocks'] ) === 3, 'all three block dirs registered' );
ok( ! empty( array_filter( $GLOBALS['__reg_blocks'], fn( $d ) => false !== strpos( (string) $d, 'pillar-essays' ) ) ), 'pillar-essays block dir registered' );
$cats = signal_noise_block_category( array() );
ok( ! empty( array_filter( $cats, fn( $c ) => ( $c['slug'] ?? '' ) === 'signal-noise' ) ), 'block_categories_all adds a signal-noise category' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
