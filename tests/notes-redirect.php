<?php
/**
 * Standalone fixture tests for the /?s= -> /notes/?s= search funnel
 * helper in inc/page-notes-template.php (v9.8.0).
 *
 * inc/page-notes-template.php registers hooks at load, so we stub the
 * registrars (add_action/add_filter) as no-ops and exercise the pure
 * URL-builder directly.
 *
 * @since theme v9.8.0
 */

// SECURITY: Prevent web access. Test fixture, not a runtime module.
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) {
	http_response_code( 404 );
	exit;
}

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/' );
}

if ( ! function_exists( 'add_action' ) ) {
	function add_action() {
		return true;
	}
}
if ( ! function_exists( 'add_filter' ) ) {
	function add_filter() {
		return true;
	}
}
if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $s ) {
		return trim( preg_replace( '/<[^>]*>/', '', (string) $s ) );
	}
}
if ( ! function_exists( 'home_url' ) ) {
	function home_url( $path = '' ) {
		return 'https://example.test' . $path;
	}
}
if ( ! function_exists( 'add_query_arg' ) ) {
	// Minimal stub: WP's real add_query_arg does NOT urlencode values
	// (urlencode=false), so the helper pre-encodes with rawurlencode().
	function add_query_arg( $key, $value, $url ) {
		$sep = ( strpos( $url, '?' ) === false ) ? '?' : '&';
		return $url . $sep . $key . '=' . $value;
	}
}

require __DIR__ . '/../inc/page-notes-template.php';

$pass = 0; $fail = 0;
function ok( $cond, $label ) {
	global $pass, $fail;
	if ( $cond ) { $pass++; echo "PASS: $label\n"; }
	else { $fail++; echo "FAIL: $label\n"; }
}

ok( sn_notes_search_redirect_target( '' ) === 'https://example.test/notes/', 'empty term -> bare /notes/' );
ok( sn_notes_search_redirect_target( 'provenance' ) === 'https://example.test/notes/?s=provenance', 'term -> /notes/?s=term' );
ok( strpos( sn_notes_search_redirect_target( 'hello world' ), 's=hello%20world' ) !== false, 'multi-word term is url-encoded' );
ok( strpos( sn_notes_search_redirect_target( '<b>x</b>' ), 's=x' ) !== false, 'tags stripped before redirect' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
