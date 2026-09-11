<?php
/**
 * Tests: speculative loading raised to prerender/moderate; search and
 * paginated notes excluded (each is a query over the same page, not a page
 * of its own). Filter contracts pinned against
 * wp-includes/speculative-loading.php: `wp_speculation_rules_configuration`
 * receives the array|null config and returns the same shape (null disables
 * the feature and must be respected, never overridden); the exclude paths
 * from `wp_speculation_rules_href_exclude_paths` are plain, unprefixed path
 * patterns (core prefixes them itself before merging with its own defaults).
 * @since theme v13.110.0
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
$config_entry  = sn_test_filter_entry( 'wp_speculation_rules_configuration' );
$exclude_entry = sn_test_filter_entry( 'wp_speculation_rules_href_exclude_paths' );
ok( is_array( $config_entry ) && is_callable( $config_entry[0] ), 'wp_speculation_rules_configuration is filtered' );
ok( is_array( $exclude_entry ) && is_callable( $exclude_entry[0] ), 'wp_speculation_rules_href_exclude_paths is filtered' );

$config_cb  = $config_entry[0];
$exclude_cb = $exclude_entry[0];

echo "\nGroup: config — raised to prerender/moderate\n";
$raised = $config_cb( array( 'mode' => 'prefetch', 'eagerness' => 'conservative' ) );
ok( 'prerender' === ( $raised['mode'] ?? null ), "mode raised to 'prerender'" );
ok( 'moderate' === ( $raised['eagerness'] ?? null ), "eagerness raised to 'moderate'" );

echo "\nGroup: config — null (disabled elsewhere) is respected, never overridden\n";
ok( null === $config_cb( null ), 'null in → null out (another plugin/filter disabling the feature wins)' );

echo "\nGroup: config — negative control, a non-array/non-null input passes through untouched\n";
ok( 'nonsense' === $config_cb( 'nonsense' ), "a stray non-array, non-null value is returned as is, not coerced" );

echo "\nGroup: exclude paths — search and paginated notes added, base paths kept\n";
$result = $exclude_cb( array( '/wp-admin/*' ) );
ok( in_array( '/wp-admin/*', $result, true ), 'the incoming /wp-admin/* exclusion survives' );
ok( ! in_array( '/notes/?s=*', $result, true ), 'search is NOT listed: core excludes every query-string URL under pretty permalinks, and an unescaped ? is a URLPattern modifier' );
ok( in_array( '/notes/page/*', $result, true ), 'paginated notes (/notes/page/*) are excluded — a query, not a page' );
ok( 2 === count( $result ), 'exactly the incoming path plus the one addition, no surprises' );

echo "\nGroup: negative control — an empty input yields only the pagination exclusion\n";
$empty_in = $exclude_cb( array() );
sort( $empty_in );
$expected = array( '/notes/page/*' );
sort( $expected );
ok( $expected === $empty_in, 'empty input → exactly the one exclusion, nothing invented, nothing dropped' );

$total = $pass + $fail;
echo "\n$pass passed, $fail failed\n";
