<?php
/**
 * Tests: the CSP hash of #sn-theme-init (inc/dark-mode.php) matches its bytes.
 *
 * The edge Content-Security-Policy (set at Cloudflare, not in this repo) is
 * meant to drop 'unsafe-inline' from script-src and allow the one blocking
 * head script by its sha256 instead. A changed byte in that script without a
 * changed hash means the browser refuses it and every reader who chose a theme
 * sees the other one flash first. This suite renders the real wp_head callback,
 * hashes exactly what the browser hashes (the text between the tags), and
 * fails unless the computed hash, SN_THEME_INIT_SHA256 and the hash quoted in
 * the doc comment are one value. (2026-10-16 CSP prep.)
 *
 * Run: php tests/theme-init-csp-hash.php
 */
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', '/' ); }

$pass = 0; $fail = 0;
function ok( $c, $m ) { global $pass, $fail; if ( $c ) { $pass++; echo "PASS: $m\n"; } else { $fail++; echo "FAIL: $m\n"; } }

$GLOBALS['__actions'] = array();
function add_action( $hook, $cb = null, $pri = 10, $args = 1 ) { $GLOBALS['__actions'][] = array( 'hook' => $hook, 'cb' => $cb, 'pri' => $pri ); return true; }
function add_filter() { return true; }
function add_shortcode() { return true; }
// Core's esc_js() leaves [a-z-] alone, which is all SN_THEME_STORAGE_KEY holds;
// the suite asserts that below rather than trusting the stub.
function esc_js( $s ) { return (string) $s; }
function esc_url( $u ) { return (string) $u; }
function esc_attr( $a ) { return htmlspecialchars( (string) $a, ENT_QUOTES ); }
function get_theme_file_uri( $p = '' ) { return 'https://x.test/' . $p; }
function sn_asset_ver( $p ) { return '1'; }
function add_query_arg( $k, $v, $u ) { return $u . '?' . $k . '=' . $v; }

$src = (string) file_get_contents( __DIR__ . '/../inc/dark-mode.php' );
require __DIR__ . '/../inc/dark-mode.php';

$head = '';
foreach ( $GLOBALS['__actions'] as $a ) {
	if ( 'wp_head' === $a['hook'] && 1 === (int) $a['pri'] && is_callable( $a['cb'] ) ) {
		ob_start();
		call_user_func( $a['cb'] );
		$head .= ob_get_clean();
	}
}

ok( 1 === preg_match( '/^[a-z-]+$/', SN_THEME_STORAGE_KEY ), 'the storage key is [a-z-] only, so esc_js() cannot change its bytes' );
ok( 1 === preg_match_all( '#<script id="sn-theme-init">(.*?)</script>#s', $head, $m ), 'exactly one #sn-theme-init in the priority-1 wp_head output' );
$inner    = $m[1][0] ?? '';
$computed = 'sha256-' . base64_encode( hash( 'sha256', $inner, true ) );
ok( false !== strpos( $inner, "localStorage.getItem('sn-theme')" ), 'the script reads the storage key (sanity: the right script was hashed)' );
ok( $computed === SN_THEME_INIT_SHA256, "SN_THEME_INIT_SHA256 matches the script's bytes — computed $computed" );
ok( 2 === substr_count( $src, $computed ), "the hash appears twice in inc/dark-mode.php: the doc comment and the constant — computed $computed" );
ok( 1 === preg_match( '/<script id="sn-theme-init">/', $head ) && false === strpos( $head, 'nonce=' ), 'the tag stays static: no nonce, nothing per request' );

echo "Result: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
