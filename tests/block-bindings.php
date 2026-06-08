<?php
/**
 * Standalone fixture tests for the signal-noise/post-field Block Bindings source.
 * Stubs WP + plugin primitives; behavioral assertions on sn_post_field_binding_value().
 * @since theme v9.11.0
 */
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', '/' ); }
define( 'SN_BINDINGS_TEST', true );
$pass = 0; $fail = 0;
function ok( $c, $m ) { global $pass, $fail; if ( $c ) { $pass++; echo "PASS: $m\n"; } else { $fail++; echo "FAIL: $m\n"; } }

$GLOBALS['__sources'] = array();
function register_block_bindings_source( $name, $props ) { $GLOBALS['__sources'][ $name ] = $props; return true; }
function add_action( $h, $cb, $p = 10, $a = 1 ) { return true; }
function __( $s, $d = null ) { return $s; }
function esc_html( $s ) { return $s; }
function esc_url( $s ) { return $s; }
function get_post() { return (object) array( 'ID' => 7 ); }
// Plugin-side stubs (toggle-able via globals).
function sn_get_reading_time( $id = null ) { return $GLOBALS['__rt']; }
function sn_post_pillar_html( $post_id = 0 ) { $GLOBALS['__pillar_arg'] = $post_id; return $GLOBALS['__pillar']; }
function sn_post_settings_get_canonical_url( $id ) { return $GLOBALS['__canon']; }
function sn_post_settings_get_og_card_title( $id ) { return $GLOBALS['__ogt']; }

require __DIR__ . '/../inc/block-bindings.php';

// Registration capture.
sn_register_post_field_binding();
$src = $GLOBALS['__sources']['signal-noise/post-field'] ?? null;
ok( is_array( $src ), 'signal-noise/post-field registered' );
ok( $src['get_value_callback'] === 'sn_post_field_binding_value', 'callback wired' );
ok( in_array( 'postId', $src['uses_context'], true ), 'uses_context includes postId' );

// reading_time.
$GLOBALS['__rt'] = 5;
ok( sn_post_field_binding_value( array( 'key' => 'reading_time' ) ) === '5 min read', 'reading_time formats' );
$GLOBALS['__rt'] = 0;
ok( sn_post_field_binding_value( array( 'key' => 'reading_time' ) ) === '1 min read', 'reading_time min-1 floor' );

// pillar: anchor passes through; empty → null (keep fallback).
$GLOBALS['__pillar'] = '<a class="sn-post-frontmatter__pillar" href="/provenance/x/">PROVENANCE</a>';
ok( strpos( (string) sn_post_field_binding_value( array( 'key' => 'pillar' ) ), 'sn-post-frontmatter__pillar' ) !== false, 'pillar returns the anchor' );
$GLOBALS['__pillar'] = '';
ok( sn_post_field_binding_value( array( 'key' => 'pillar' ) ) === null, 'pillar returns null when no pillar (keep fallback)' );

// canonical / og_title.
$GLOBALS['__canon'] = 'https://x.test/canon/';
ok( sn_post_field_binding_value( array( 'key' => 'canonical' ) ) === 'https://x.test/canon/', 'canonical resolves' );
$GLOBALS['__canon'] = '';
ok( sn_post_field_binding_value( array( 'key' => 'canonical' ) ) === null, 'canonical null when empty' );
$GLOBALS['__ogt'] = 'OG Title';
ok( sn_post_field_binding_value( array( 'key' => 'og_title' ) ) === 'OG Title', 'og_title resolves' );

// Edge cases.
ok( sn_post_field_binding_value( array( 'key' => 'bogus' ) ) === null, 'unknown key → null' );
ok( sn_post_field_binding_value( array() ) === null, 'missing key → null' );

// postId-context precedence (get_post returns null here, context supplies id).
$blk = (object) array( 'context' => array( 'postId' => 42 ) );
$GLOBALS['__rt'] = 9;
ok( sn_post_field_binding_value( array( 'key' => 'reading_time' ), $blk ) === '9 min read', 'uses block context postId' );

// pillar must honor the SAME resolved postId, not the global get_post() — BND-4.
$GLOBALS['__pillar'] = '<a class="sn-post-frontmatter__pillar">P</a>';
$GLOBALS['__pillar_arg'] = -1;
sn_post_field_binding_value( array( 'key' => 'pillar' ), $blk );
ok( $GLOBALS['__pillar_arg'] === 42, 'pillar resolver receives the resolved context postId (not the global)' );

echo "Result: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
