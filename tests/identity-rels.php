<?php
/**
 * Standalone fixture tests for rel="me" identity links (A3, theme v10.5.0).
 *
 * inc/identity-rels.php emits one <link rel="me"> in <head> per configured
 * social profile, for IndieAuth / Mastodon verification. The profile URLs
 * live in the companion plugin's `sn_settings` option (subtree social.same_as);
 * the theme reads that option directly and passes it THROUGH the documented
 * `sn_schema_same_as` filter as an override hook.
 *
 * CONTRACT NOTE (load-bearing): the plugin applies sn_schema_same_as with its
 * default passed INLINE — it registers no standing callback that injects URLs.
 * So a consumer calling apply_filters('sn_schema_same_as', array()) gets back
 * empty even when the plugin is active. The theme must read the OPTION itself.
 * T2 below proves a populated option yields links with NO filter callback set —
 * the exact case the naive filter-only approach would silently drop.
 *
 * @since theme v10.5.0
 */

// SECURITY: CLI-only fixture.
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', '/' ); }
define( 'SN_IDENTITY_RELS_TEST', true ); // suppress wp_head wiring on require

$pass = 0; $fail = 0;
function ok( $cond, $msg ) { global $pass, $fail; if ( $cond ) { $pass++; echo "PASS: $msg\n"; } else { $fail++; echo "FAIL: $msg\n"; } }

// --- Controllable WP stubs ---
$GLOBALS['__options'] = array(); // get_option() source
$GLOBALS['__filters'] = array(); // apply_filters() override registry

function add_action() { return true; }
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['__options'] ) ? $GLOBALS['__options'][ $k ] : $d; }
function apply_filters( $h, $v ) { return array_key_exists( $h, $GLOBALS['__filters'] ) ? $GLOBALS['__filters'][ $h ] : $v; }
function esc_url( $u ) { return str_replace( array( '"', ' ', '<', '>' ), '', (string) $u ); }
function esc_url_raw( $u ) { return str_replace( array( '"', ' ', '<', '>' ), '', (string) $u ); }

require __DIR__ . '/../inc/identity-rels.php';

/** Capture the head-link output for the current stub state. */
function rels_output() {
	ob_start();
	sn_identity_rels_head_link();
	return ob_get_clean();
}
/** Count occurrences of a substring. */
function count_substr( $haystack, $needle ) {
	return substr_count( $haystack, $needle );
}

ok( function_exists( 'sn_identity_rels_urls' ), 'sn_identity_rels_urls() is defined' );
ok( function_exists( 'sn_identity_rels_head_link' ), 'sn_identity_rels_head_link() is defined' );

// T1 — plugin/option ABSENT → no output, no fatal (graceful degrade).
$GLOBALS['__options'] = array();
$GLOBALS['__filters'] = array();
ok( rels_output() === '', 'T1 degrade: no <link rel=me> when sn_settings option is absent' );

// T2 — populated OPTION, NO filter callback → links emitted (the contract test).
$GLOBALS['__options'] = array( 'sn_settings' => array( 'social' => array( 'same_as' => array(
	'https://mastodon.social/@juanlentino',
	'https://github.com/juanlentino',
) ) ) );
$GLOBALS['__filters'] = array();
$out = rels_output();
ok( count_substr( $out, '<link rel="me"' ) === 2, 'T2 contract: populated option yields one rel=me per profile (no filter callback needed)' );
ok( strpos( $out, 'href="https://mastodon.social/@juanlentino"' ) !== false, 'T2: mastodon profile present' );
ok( strpos( $out, 'href="https://github.com/juanlentino"' ) !== false, 'T2: github profile present' );

// T3 — dedupe: a duplicate URL in the option appears exactly once.
$GLOBALS['__options'] = array( 'sn_settings' => array( 'social' => array( 'same_as' => array(
	'https://example.com/me',
	'https://example.com/me',
) ) ) );
ok( count_substr( rels_output(), 'href="https://example.com/me"' ) === 1, 'T3 dedupe: duplicate profile emitted once' );

// T4 — escaping: a hostile URL is esc_url()'d at output (no raw quote/space).
$GLOBALS['__options'] = array( 'sn_settings' => array( 'social' => array( 'same_as' => array(
	'https://evil.test/" onload="x',
) ) ) );
$out = rels_output();
ok( strpos( $out, '" onload="' ) === false, 'T4 escaping: injected attribute breakout is stripped' );

// T5 — filter override REPLACES the option list (passthrough hook honored).
$GLOBALS['__options'] = array( 'sn_settings' => array( 'social' => array( 'same_as' => array( 'https://from-option.test' ) ) ) );
$GLOBALS['__filters'] = array( 'sn_schema_same_as' => array( 'https://from-filter.test' ) );
$out = rels_output();
ok( strpos( $out, 'from-filter.test' ) !== false && strpos( $out, 'from-option.test' ) === false, 'T5 passthrough: sn_schema_same_as filter can override the list' );

// T6 — malformed subtree (not an array) → degrade, no fatal.
$GLOBALS['__options'] = array( 'sn_settings' => array( 'social' => array( 'same_as' => 'not-an-array' ) ) );
$GLOBALS['__filters'] = array();
ok( rels_output() === '', 'T6 robustness: non-array same_as degrades to no output' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
