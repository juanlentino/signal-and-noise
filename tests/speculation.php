<?php
/**
 * Tests: speculative loading raised to prerender/moderate. Search and
 * pagination need no exclusion list of our own — under pretty permalinks
 * core already excludes every URL with a query string
 * (`wp-includes/speculative-loading.php`), and `/notes` paginates via
 * `?paged=N`, already a query string. Filter contract pinned against
 * `wp_speculation_rules_configuration`: it receives the array|null config
 * and returns the same shape (null disables the feature and must be
 * respected, never overridden).
 * @since theme v13.1.0
 */
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', '/' ); }
$pass = 0; $fail = 0;
function ok( $c, $m ) { global $pass, $fail; if ( $c ) { $pass++; echo "PASS: $m\n"; } else { $fail++; echo "FAIL: $m\n"; } }

// ── WP stubs ──
$GLOBALS['__filters'] = array();
function add_filter( $hook, $cb, $priority = 10, $accepted_args = 1 ) {
	$GLOBALS['__filters'][ $hook ][] = array( $cb, $priority, $accepted_args );
	return true;
}

require_once __DIR__ . '/../inc/speculation.php';

function sn_test_filter_entry( $hook ) {
	return $GLOBALS['__filters'][ $hook ][0] ?? null;
}

echo "Group: registration\n";
$config_entry = sn_test_filter_entry( 'wp_speculation_rules_configuration' );
ok( is_array( $config_entry ) && is_callable( $config_entry[0] ), 'wp_speculation_rules_configuration is filtered' );
ok( ! isset( $GLOBALS['__filters']['wp_speculation_rules_href_exclude_paths'] ), 'no href_exclude_paths filter is registered — core already excludes every query-string URL under pretty permalinks' );

$config_cb = $config_entry[0];

echo "\nGroup: config — raised to prerender/moderate\n";
$raised = $config_cb( array( 'mode' => 'prefetch', 'eagerness' => 'conservative' ) );
ok( 'prerender' === ( $raised['mode'] ?? null ), "mode raised to 'prerender'" );
ok( 'moderate' === ( $raised['eagerness'] ?? null ), "eagerness raised to 'moderate'" );

echo "\nGroup: config — null (disabled elsewhere) is respected, never overridden\n";
ok( null === $config_cb( null ), 'null in → null out (another plugin/filter disabling the feature wins)' );

echo "\nGroup: config — negative control, a non-array/non-null input passes through untouched\n";
ok( 'nonsense' === $config_cb( 'nonsense' ), "a stray non-array, non-null value is returned as is, not coerced" );

echo "\nGroup: negative control — the module registers no href-exclude filter at all\n";
$src = (string) file_get_contents( __DIR__ . '/../inc/speculation.php' );
ok( false === strpos( $src, 'href_exclude_paths' ), 'the module source does not reference wp_speculation_rules_href_exclude_paths' );

$total = $pass + $fail;
echo "\n$pass passed, $fail failed\n";
