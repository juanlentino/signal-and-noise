<?php
/**
 * Standalone fixture tests for /notes pagination helpers (Release 1).
 *
 * Stubs apply_filters + get_query_var so the pure helpers in
 * inc/page-notes-render.php can be exercised without a WP load.
 *
 * @since theme v9.6.0
 */

// SECURITY: Prevent web access. Test fixture, not a runtime module.
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) {
	http_response_code( 404 );
	exit;
}

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/' );
}

// ── Controllable stub state ──
$GLOBALS['__filters']    = array(); // filter name => return value
$GLOBALS['__query_vars'] = array(); // var name => value

if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $hook, $value ) {
		return array_key_exists( $hook, $GLOBALS['__filters'] )
			? $GLOBALS['__filters'][ $hook ]
			: $value;
	}
}
if ( ! function_exists( 'get_query_var' ) ) {
	function get_query_var( $var, $default = '' ) {
		return $GLOBALS['__query_vars'][ $var ] ?? $default;
	}
}

// Pull in ONLY the helper functions. page-notes-render.php is a full
// render file that echoes HTML + calls WP_Query at load, so we cannot
// require it directly. Instead, the helpers live in a guarded block at
// the top of that file that returns early when SN_NOTES_RENDER_TEST is
// defined (see Task 1 Step 3). Define the sentinel, then require.
define( 'SN_NOTES_RENDER_TEST', true );
require __DIR__ . '/../inc/page-notes-render.php';

$pass = 0; $fail = 0;
function ok( $cond, $label ) {
	global $pass, $fail;
	if ( $cond ) { $pass++; echo "PASS: $label\n"; }
	else { $fail++; echo "FAIL: $label\n"; }
}

// ── sn_notes_per_page(): default + clamp ──
$GLOBALS['__filters'] = array();
ok( sn_notes_per_page() === 20, 'default per-page is 20 when no filter' );

$GLOBALS['__filters'] = array( 'sn_notes_per_page' => 5 );
ok( sn_notes_per_page() === 5, 'filter override respected (5)' );

$GLOBALS['__filters'] = array( 'sn_notes_per_page' => 0 );
ok( sn_notes_per_page() === 1, 'clamp floor: 0 -> 1' );

$GLOBALS['__filters'] = array( 'sn_notes_per_page' => 999 );
ok( sn_notes_per_page() === 100, 'clamp ceiling: 999 -> 100' );

$GLOBALS['__filters'] = array( 'sn_notes_per_page' => '15abc' );
ok( sn_notes_per_page() === 15, 'cast non-int return to int (15abc -> 15)' );

// ── sn_notes_current_page(): query var + $_GET fallback + floor ──
$GLOBALS['__query_vars'] = array(); unset( $_GET['paged'] );
ok( sn_notes_current_page() === 1, 'default page is 1 (nothing set)' );

$GLOBALS['__query_vars'] = array( 'paged' => 3 ); unset( $_GET['paged'] );
ok( sn_notes_current_page() === 3, 'reads get_query_var(paged)=3' );

$GLOBALS['__query_vars'] = array(); $_GET['paged'] = '2';
ok( sn_notes_current_page() === 2, 'falls back to $_GET[paged]=2 when query var is 0/empty' );

$GLOBALS['__query_vars'] = array( 'paged' => 0 ); $_GET['paged'] = '4';
ok( sn_notes_current_page() === 4, 'query var 0 -> uses $_GET fallback (4)' );

$GLOBALS['__query_vars'] = array(); $_GET['paged'] = '-5';
ok( sn_notes_current_page() === 1, 'floor at 1 for negative $_GET' );
unset( $_GET['paged'] );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
