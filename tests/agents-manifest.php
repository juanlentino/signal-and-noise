<?php
/**
 * Standalone fixture tests for the /.well-known/agents.json discovery manifest
 * (sub-project A of the machine-readability program; theme v10.37.0).
 *
 * inc/agents-manifest.php serves one JSON index of every machine surface, built
 * by a pure + filterable sn_agents_surfaces(), advertised via a <head> <link>.
 * Stubs home_url/get_bloginfo/wp_json_encode/status_header/apply_filters/esc_url
 * so the pure functions run without a WP load.
 *
 * @since theme v10.37.0
 */

if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', '/' ); }
define( 'SN_AGENTS_TEST', true ); // suppress add_action wiring on require

// ── Stubs ──
if ( ! function_exists( 'home_url' ) ) { function home_url( $p = '' ) { return 'https://juanlentino.com' . $p; } }
if ( ! function_exists( 'get_bloginfo' ) ) { function get_bloginfo( $k = '' ) { return 'name' === $k ? 'Signal & Noise' : 'tagline'; } }
if ( ! function_exists( 'wp_json_encode' ) ) { function wp_json_encode( $d, $f = 0, $depth = 512 ) { return json_encode( $d, $f, $depth ); } }
if ( ! function_exists( 'status_header' ) ) { function status_header( $c ) { $GLOBALS['__status'] = (int) $c; } }
if ( ! function_exists( 'esc_url' ) ) { function esc_url( $u ) { return str_replace( array( '"', '<', '>' ), '', (string) $u ); } }
$GLOBALS['__filters'] = array();
if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $hook, $value, ...$args ) {
		if ( isset( $GLOBALS['__filters'][ $hook ] ) ) { $value = call_user_func( $GLOBALS['__filters'][ $hook ], $value ); }
		return $value;
	}
}
if ( ! function_exists( 'add_action' ) ) { function add_action() { return true; } }

require __DIR__ . '/../inc/agents-manifest.php';

$pass = 0; $fail = 0;
function ok( $c, $m ) { global $pass, $fail; if ( $c ) { $pass++; echo "PASS: $m\n"; } else { $fail++; echo "FAIL: $m\n"; } }

echo "agents.json discovery manifest — theme v10.37.0\n\n";

// ── request matcher ──
ok( sn_agents_is_request( '/.well-known/agents.json' ) === true, 'matches the well-known path' );
ok( sn_agents_is_request( '/.well-known/agents.json/' ) === true, 'matches with a trailing slash' );
ok( sn_agents_is_request( '/.well-known/agents.json?x=1' ) === true, 'matches with a query string' );
ok( sn_agents_is_request( '/.well-known/agents' ) === false, 'rejects the near-miss without .json' );
ok( sn_agents_is_request( '/agents.json' ) === false, 'rejects /agents.json outside .well-known' );
ok( sn_agents_is_request( '/' ) === false, 'rejects site root' );

// ── surfaces (pure) ──
$s = sn_agents_surfaces();
ok( is_array( $s ) && count( $s ) >= 6, 'surfaces() returns a non-trivial list' );
$types = array_column( $s, 'type' );
foreach ( array( 'llms-txt', 'llms-full', 'feed-json', 'abilities', 'provenance-verify' ) as $t ) {
	ok( in_array( $t, $types, true ), "surfaces include '$t'" );
}
$all_abs = true; $all_have_url = true;
foreach ( $s as $entry ) {
	if ( empty( $entry['type'] ) || empty( $entry['url'] ) ) { $all_have_url = false; }
	if ( 0 !== strpos( (string) ( $entry['url'] ?? '' ), 'https://' ) ) { $all_abs = false; }
}
ok( $all_have_url, 'every surface entry has type + url' );
ok( $all_abs, 'every surface url is absolute https' );

// ── provenance-verify URL pin (v10.49.0) ──
// The verify docket ships at /verify (see inc/content-json-document.php's
// per-note verify_url). The manifest briefly advertised the retired
// /provenance/verify/ path, which 404s live — and the smoke test's surface
// loop hard-fails on any advertised-404. Pin the exact URL so the manifest
// can never re-advertise a dead verify path.
$by_type = array_column( $s, 'url', 'type' );
ok( ( $by_type['provenance-verify'] ?? '' ) === 'https://juanlentino.com/verify/', 'provenance-verify surface points at the live /verify docket (never /provenance/verify/)' );

// ── surfaces filter (proves sub-project B can wire in) ──
$GLOBALS['__filters']['sn_agents_surfaces'] = static function ( $list ) {
	$list[] = array( 'type' => 'mcp', 'url' => 'https://juanlentino.com/wp-json/sn/mcp', 'format' => 'application/json' );
	return $list;
};
$s2 = sn_agents_surfaces();
ok( in_array( 'mcp', array_column( $s2, 'type' ), true ), 'a filter callback can append a surface (B wires in here)' );
// malformed entry from a bad callback is dropped
$GLOBALS['__filters']['sn_agents_surfaces'] = static function ( $list ) { $list[] = array( 'nope' => 1 ); return $list; };
$s3 = sn_agents_surfaces();
ok( ! in_array( '', array_column( $s3, 'type' ), true ) && count( $s3 ) === count( $s ), 'malformed filter entries are dropped defensively' );
unset( $GLOBALS['__filters']['sn_agents_surfaces'] );

// ── manifest + body ──
$m = sn_agents_manifest();
ok( ( $m['site']['url'] ?? '' ) === 'https://juanlentino.com', 'manifest site.url is home' );
ok( ( $m['updated'] ?? '' ) === SN_AGENTS_UPDATED, 'manifest carries the fixed updated date' );
ok( isset( $m['structured_data']['type'] ) && 'JSON-LD' === $m['structured_data']['type'], 'manifest notes the embedded JSON-LD' );
$body = sn_agents_json_body();
$decoded = json_decode( $body, true );
ok( is_array( $decoded ) && isset( $decoded['surfaces'] ), 'body is valid JSON with a surfaces array' );
ok( false !== strpos( $body, '/llms.txt' ) && false === strpos( $body, '\/llms.txt' ), 'slashes are unescaped (JSON_UNESCAPED_SLASHES)' );

// ── head link ──
ob_start(); sn_agents_head_link(); $link = ob_get_clean();
ok( false !== strpos( $link, '<link rel="alternate"' ) && false !== strpos( $link, 'type="application/json"' ), 'head link is a valid alternate/json link' );
ok( false !== strpos( $link, '/.well-known/agents.json' ), 'head link points at the manifest' );

// ── sub-project C advertises a content-json surface via the same filter seam ──
if ( ! defined( 'SN_CONTENT_JSON_TEST' ) ) { define( 'SN_CONTENT_JSON_TEST', true ); }
require_once __DIR__ . '/../inc/content-json.php';
$c_surfaces = sn_content_json_advertise_surface( sn_agents_surfaces() );
ok( in_array( 'content-json', array_column( $c_surfaces, 'type' ), true ), 'content-json surface can be advertised into the manifest' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
