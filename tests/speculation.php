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
 *
 * What /resume prints (one résumé since 2026-11-12: core's rule only) is pinned on what core itself prints: the
 * suite loads core's speculative-loading.php and WP_Speculation_Rules (fetched
 * beside html-api/ by tools/fetch-wp-test-core.sh) and ends RED without them.
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
function add_action( $hook, $cb, $priority = 10, $accepted_args = 1 ) {
	return add_filter( $hook, $cb, $priority, $accepted_args );
}
function apply_filters( $hook, $value, ...$args ) {
	foreach ( $GLOBALS['__filters'][ $hook ] ?? array() as $entry ) {
		$value = $entry[0]( $value, ...$args );
	}
	return $value;
}
function do_action( $hook, ...$args ) {
	foreach ( $GLOBALS['__filters'][ $hook ] ?? array() as $entry ) {
		$entry[0]( ...$args );
	}
}
// The request: which page, and whether speculative loading is on at all.
$GLOBALS['__page']      = 'resume';
$GLOBALS['__logged_in'] = false;
function is_page( $slug = '' ) { return $slug === $GLOBALS['__page']; }
function is_user_logged_in() { return $GLOBALS['__logged_in']; }
function get_option( $name ) { return 'permalink_structure' === $name ? '/%postname%/' : false; }
function home_url( $path = '' ) { return 'https://juanlentino.com' . $path; }
function site_url( $path = '' ) { return 'https://juanlentino.com' . $path; }
function content_url() { return 'https://juanlentino.com/wp-content'; }
function plugins_url() { return 'https://juanlentino.com/wp-content/plugins'; }
function wp_upload_dir( $time = null, $create = true ) { return array( 'baseurl' => 'https://juanlentino.com/wp-content/uploads' ); }
function get_template_directory_uri() { return 'https://juanlentino.com/wp-content/themes/signal-and-noise'; }
function get_stylesheet_directory_uri() { return get_template_directory_uri(); }
function trailingslashit( $s ) { return rtrim( (string) $s, '/\\' ) . '/'; }
function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }
function esc_html( $s ) { return (string) $s; }
function wp_json_encode( $data, $flags = 0 ) { return json_encode( $data, $flags ); }
// What core prints: wp_print_speculation_rules() hands its JSON here.
function wp_print_inline_script_tag( $data, $attributes = array() ) { $GLOBALS['__printed'][] = array( $data, $attributes ); }

require_once __DIR__ . '/lib/wp-html-api.php';
snt_require_wp_speculation_rules();

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

// What /resume prints. Run through core itself:
// wp_print_speculation_rules() builds the rules, fires wp_load_speculation_rules,
// and prints the JSON; these pins read what it printed.
function sn_test_printed_rules( $page ) {
	$GLOBALS['__page']    = $page;
	$GLOBALS['__printed'] = array();
	wp_print_speculation_rules();
	$printed = $GLOBALS['__printed'][0] ?? null;
	return null === $printed ? null : array( 'json' => $printed[0], 'type' => $printed[1]['type'] ?? '', 'rules' => json_decode( $printed[0], true ) );
}
echo "\nGroup: /resume is one résumé (2026-11-12), so it adds no rule of its own\n";
$resume = sn_test_printed_rules( 'resume' );
ok( is_array( $resume ) && 'speculationrules' === $resume['type'] && 1 === count( $resume['rules']['prerender'] ?? array() ) && false === strpos( $resume['json'], 'sn-resume' ) && false === strpos( $resume['json'], 'data-lane' ), 'on /resume only core\'s rule is printed: no lane rule' );
ok( ! function_exists( 'sn_speculation_resume_lanes' ), 'the lane prerender hook is gone' );

echo "\nGroup: speculative loading off means no rules at all\n";
$GLOBALS['__logged_in'] = true;
ok( null === sn_test_printed_rules( 'resume' ), 'a signed-in visitor gets no rules' );
$GLOBALS['__logged_in'] = false;

$total = $pass + $fail;
echo "\n$pass passed, $fail failed\n";
