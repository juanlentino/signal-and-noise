<?php
/**
 * Standalone fixture tests for the WebSub feed advertisement (D4, theme v10.9.0).
 *
 * inc/feed-websub.php advertises a WebSub hub in the RSS2 + Atom feeds via
 * <atom:link rel="hub"> / <link rel="hub">, so feed readers can subscribe for
 * push. The hub default + the `sn_websub_hub` filter MUST match the companion
 * plugin's inc/websub.php (which reads the same filter to ping that hub), so the
 * advertised hub and the pinged hub stay in sync. Hub filtered to '' → nothing
 * advertised (opt-out). The href is esc_url()'d at the output sink.
 *
 * @since theme v10.9.0
 */

if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', '/' ); }
define( 'SN_FEED_WEBSUB_TEST', true ); // suppress rss2_head/atom_head wiring on require

$pass = 0; $fail = 0;
function ok( $cond, $msg ) { global $pass, $fail; if ( $cond ) { $pass++; echo "PASS: $msg\n"; } else { $fail++; echo "FAIL: $msg\n"; } }

$GLOBALS['__filters'] = array();
function add_action() { return true; }
function apply_filters( $h, $v ) { return array_key_exists( $h, $GLOBALS['__filters'] ) ? $GLOBALS['__filters'][ $h ] : $v; }
function esc_url( $u ) { return str_replace( array( '"', ' ', '<', '>' ), '', (string) $u ); }

require __DIR__ . '/../inc/feed-websub.php';

function ws_rss2() { ob_start(); sn_feed_websub_rss2_head(); return ob_get_clean(); }
function ws_atom() { ob_start(); sn_feed_websub_atom_head(); return ob_get_clean(); }

ok( function_exists( 'sn_feed_websub_hub' ), 'sn_feed_websub_hub() is defined' );

// HUB default + override. The default MUST equal the plugin's literal.
$GLOBALS['__filters'] = array();
ok( sn_feed_websub_hub() === 'https://pubsubhubbub.appspot.com/', 'hub: default is the public hub (matches plugin)' );
$GLOBALS['__filters'] = array( 'sn_websub_hub' => 'https://hub.example.net/' );
ok( sn_feed_websub_hub() === 'https://hub.example.net/', 'hub: sn_websub_hub filter override honored (shared with plugin)' );
$GLOBALS['__filters'] = array();

// RSS2 advertises an atom:link rel=hub.
$out = ws_rss2();
ok( strpos( $out, '<atom:link rel="hub"' ) !== false, 'rss2: emits <atom:link rel="hub">' );
ok( strpos( $out, 'href="https://pubsubhubbub.appspot.com/"' ) !== false, 'rss2: href is the hub' );

// Atom advertises a bare link rel=hub.
$out = ws_atom();
ok( strpos( $out, '<link rel="hub"' ) !== false, 'atom: emits <link rel="hub">' );
ok( strpos( $out, '<atom:link' ) === false, 'atom: uses a bare <link>, not the atom: prefix' );

// Hub filtered to '' → nothing advertised (opt-out).
$GLOBALS['__filters'] = array( 'sn_websub_hub' => '' );
ok( ws_rss2() === '' && ws_atom() === '', 'opt-out: empty hub advertises nothing' );

// Escaping: a hostile hub is esc_url()'d at output.
$GLOBALS['__filters'] = array( 'sn_websub_hub' => 'https://evil.test/" rel="x' );
ok( strpos( ws_rss2(), '" rel="x' ) === false, 'escaping: attribute breakout stripped' );
$GLOBALS['__filters'] = array();

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
