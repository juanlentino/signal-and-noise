<?php
/**
 * Standalone fixture tests for inc/purge-verify.php.
 *
 * Tier-1 of the verified-purge arc (owner-approved 2026-07-03): the render-epoch
 * marker + the durable per-leg purge report. NOT the Tier-2 worker probe /
 * escalate loop (deferred — workers.dev is off + box->edge reachability is an
 * untestable on-box empirical).
 *
 *   1. sn_render_epoch() seeds at 1; sn_bump_render_epoch() increments and
 *      persists as a NON-autoloaded option; sn_render_epoch_meta() emits the
 *      <meta name="sn-render-epoch"> tag on wp_head.
 *   2. The epoch bumps at the START of an edge-affecting purge (sn_before_cache_flush)
 *      and NOT on a pure object-cache-only purge.
 *   3. sn_write_purge_report() records a durable sn_last_purge_report OPTION (not a
 *      transient — the purge deletes _transient_sn_%), capturing per-leg state
 *      (Cloudways Varnish + confirmed/dispatched CF), and is written only on
 *      edge-affecting purges.
 *
 * Run: php tests/purge-verify.php
 *
 * @since theme v10.23.0
 */

// SECURITY: Prevent web access. Test fixture, not a runtime module.
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) {
	http_response_code( 404 );
	exit;
}

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/' );
}

$theme_root = realpath( __DIR__ . '/..' );

// ── Stub state ──
$GLOBALS['__options']       = array(); // option_name => value
$GLOBALS['__option_writes'] = array(); // option_name => [ 'autoload' => bool ] (last write)
$GLOBALS['__actions']       = array();
$GLOBALS['__filters']       = array();

if ( ! function_exists( 'add_action' ) ) {
	function add_action( $hook, $cb, $priority = 10, $accepted_args = 1 ) {
		$GLOBALS['__actions'][ $hook ][] = $cb;
	}
}
if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( $hook, $cb, $priority = 10, $accepted_args = 1 ) {
		$GLOBALS['__filters'][ $hook ][] = $cb;
	}
}
if ( ! function_exists( 'has_action' ) ) {
	function has_action( $hook, $cb = false ) {
		return ! empty( $GLOBALS['__actions'][ $hook ] );
	}
}
if ( ! function_exists( 'get_option' ) ) {
	function get_option( $name, $default = false ) {
		return array_key_exists( $name, $GLOBALS['__options'] ) ? $GLOBALS['__options'][ $name ] : $default;
	}
}
if ( ! function_exists( 'update_option' ) ) {
	function update_option( $name, $value, $autoload = null ) {
		$GLOBALS['__options'][ $name ]       = $value;
		$GLOBALS['__option_writes'][ $name ] = array( 'autoload' => $autoload );
		return true;
	}
}
if ( ! function_exists( 'delete_option' ) ) {
	function delete_option( $name ) {
		unset( $GLOBALS['__options'][ $name ] );
		return true;
	}
}
if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $s ) {
		return htmlspecialchars( (string) $s, ENT_QUOTES );
	}
}
// ── Tier-2 stubs: filters, home_url, and a queued wp_remote_get ──
if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $tag, $value ) {
		foreach ( $GLOBALS['__filters'][ $tag ] ?? array() as $cb ) { $value = call_user_func( $cb, $value ); }
		return $value;
	}
}
if ( ! function_exists( 'home_url' ) ) {
	function home_url( $path = '' ) { return 'https://example.test' . $path; }
}
if ( ! class_exists( 'WP_Error' ) ) { class WP_Error {} }
if ( ! function_exists( 'is_wp_error' ) ) { function is_wp_error( $t ) { return $t instanceof WP_Error; } }
// FIFO queue of responses; each wp_remote_get shifts one. A response is either a
// WP_Error or array( 'code'=>int, 'body'=>html, 'headers'=>[lowercase=>val] ).
$GLOBALS['__http_queue'] = array();
$GLOBALS['__http_gets']  = array(); // recorded request URLs
function wp_remote_get( $url, $args = array() ) {
	$GLOBALS['__http_gets'][] = $url;
	return $GLOBALS['__http_queue'] ? array_shift( $GLOBALS['__http_queue'] ) : array( 'code' => 200, 'body' => '', 'headers' => array() );
}
function wp_remote_retrieve_response_code( $r ) { return is_array( $r ) ? (int) ( $r['code'] ?? 0 ) : 0; }
function wp_remote_retrieve_body( $r ) { return is_array( $r ) ? (string) ( $r['body'] ?? '' ) : ''; }
function wp_remote_retrieve_header( $r, $h ) { return is_array( $r ) ? (string) ( $r['headers'][ strtolower( $h ) ] ?? '' ) : ''; }
// Plugin-side blocking CF purge — a counter so the escalate's re-evict is observable.
$GLOBALS['__cf_verified'] = 0;
if ( ! function_exists( 'sn_cf_purge_everything_verified' ) ) {
	function sn_cf_purge_everything_verified() { $GLOBALS['__cf_verified']++; return array( 'accepted' => true, 'http' => 200, 'cf_success' => true ); }
}
// A page body carrying a render-epoch meta (what the probe parses out of the edge HTML).
function epoch_html( $n ) { return "<html><head><meta name=\"sn-render-epoch\" content=\"$n\">\n</head><body>x</body></html>"; }

require $theme_root . '/inc/purge-verify.php';

$pass = 0; $fail = 0;
function ok( $cond, $label ) {
	global $pass, $fail;
	if ( $cond ) { $pass++; echo "PASS: $label\n"; }
	else { $fail++; echo "FAIL: $label\n"; }
}
// Invoke every callback registered on a hook, as WordPress would.
function fire( $hook, ...$args ) {
	foreach ( $GLOBALS['__actions'][ $hook ] ?? array() as $cb ) {
		call_user_func_array( $cb, $args );
	}
}

// ── 1. Epoch defaults to 1 ──
echo "Scenario 1: render epoch marker\n";
ok( 1 === sn_render_epoch(), 'epoch seeds at 1 when unset' );

// ── 2. Bump increments + persists non-autoloaded ──
$next = sn_bump_render_epoch();
ok( 2 === $next, 'first bump returns 2' );
ok( 2 === sn_render_epoch(), 'epoch reads back as 2 after bump' );
ok( isset( $GLOBALS['__option_writes']['sn_render_epoch'] )
	&& false === $GLOBALS['__option_writes']['sn_render_epoch']['autoload'],
	'epoch option is written NON-autoloaded' );
ok( 3 === sn_bump_render_epoch(), 'second bump returns 3' );

// ── 3. The meta tag emits with the current epoch, escaped ──
ob_start();
sn_render_epoch_meta();
$meta = trim( ob_get_clean() );
ok( false !== strpos( $meta, 'name="sn-render-epoch"' ), 'meta carries the sn-render-epoch name' );
ok( false !== strpos( $meta, 'content="3"' ), 'meta carries the current epoch value' );
ok( 0 === strpos( $meta, '<meta' ), 'output is a <meta> element' );

// ── 4. The marker is registered on wp_head ──
ok( ! empty( $GLOBALS['__actions']['wp_head'] ), 'render-epoch meta registered on wp_head' );

// ── 5. Epoch bumps at the START of an edge-affecting purge, not otherwise ──
echo "\nScenario 5: sn_before_cache_flush bumps the epoch\n";
ok( ! empty( $GLOBALS['__actions']['sn_before_cache_flush'] ), 'epoch bump registered on sn_before_cache_flush' );

$before = sn_render_epoch();
fire( 'sn_before_cache_flush', array( 'origin_html' => true, 'cloudflare' => true ) );
ok( sn_render_epoch() === $before + 1, 'an origin+CDN purge bumps the epoch' );

$before = sn_render_epoch();
fire( 'sn_before_cache_flush', array( 'origin_html' => false, 'cloudflare' => true ) );
ok( sn_render_epoch() === $before + 1, 'a CDN-only purge still bumps the epoch' );

$before = sn_render_epoch();
fire( 'sn_before_cache_flush', array( 'object_cache' => true, 'origin_html' => false, 'cloudflare' => false ) );
ok( sn_render_epoch() === $before, 'a pure object-cache flush does NOT bump the epoch' );

// ── 6. Report writer: durable option, per-leg, verified vs auto ──
echo "\nScenario 6: durable sn_last_purge_report\n";
ok( ! empty( $GLOBALS['__actions']['sn_after_full_cache_flush'] ), 'report writer registered on sn_after_full_cache_flush' );

// Verified purge: Cloudways Varnish leg accepted + a confirmed CF result.
$GLOBALS['__options']['sn_cloudways_last_purge'] = array( 'time' => 111, 'ok' => true, 'http' => 200, 'operation_id' => 98765 );
$GLOBALS['sn_cf_verified_result']                = array( 'accepted' => true, 'http' => 200, 'cf_success' => true );
fire( 'sn_after_full_cache_flush', array( 'origin_html' => true, 'cloudflare' => true, 'verified' => true ), 4 );

$report = get_option( 'sn_last_purge_report', null );
ok( is_array( $report ), 'report written as an option' );
ok( 'verified' === ( $report['mode'] ?? '' ), 'mode records a verified purge' );
ok( (int) $report['epoch'] === sn_render_epoch(), 'report carries the current epoch' );
ok( 'cloudways' === ( $report['legs']['varnish']['via'] ?? '' ) && ! empty( $report['legs']['varnish']['ok'] ), 'Varnish leg reads the Cloudways confirmation' );
ok( 98765 === ( $report['legs']['varnish']['operation_id'] ?? 0 ), 'Varnish leg carries the Cloudways operation id' );
ok( true === ( $report['legs']['cf']['cf_success'] ?? null ), 'CF leg records the confirmed success' );
ok( 4 === ( $report['template_overrides'] ?? -1 ), 'report carries the template-override count' );
ok( false === $GLOBALS['__option_writes']['sn_last_purge_report']['autoload'], 'report option is NON-autoloaded' );
ok( ! isset( $GLOBALS['sn_cf_verified_result'] ), 'the stashed CF result is consumed (unset) after the report' );

// Auto (non-verified) purge: CF is dispatched but unconfirmed.
$GLOBALS['__options']['sn_cloudways_last_purge'] = array( 'time' => 222, 'ok' => true, 'http' => 200, 'operation_id' => 55 );
fire( 'sn_after_full_cache_flush', array( 'origin_html' => true, 'cloudflare' => true ), 0 );
$report = get_option( 'sn_last_purge_report', null );
ok( 'auto' === ( $report['mode'] ?? '' ), 'mode records an auto purge' );
ok( true === ( $report['legs']['cf']['dispatched'] ?? null )
	&& array_key_exists( 'confirmed', $report['legs']['cf'] )
	&& null === $report['legs']['cf']['confirmed'],
	'auto CF leg is dispatched-but-unconfirmed' );

// Non-edge-affecting purge does NOT overwrite the report.
$prev = get_option( 'sn_last_purge_report', null );
fire( 'sn_after_full_cache_flush', array( 'origin_html' => false, 'cloudflare' => false ), 0 );
ok( get_option( 'sn_last_purge_report', null ) === $prev, 'a pure object-cache flush leaves the last report untouched' );

// Regression: the report is an OPTION, so pruning every _transient_sn_% (what the
// purge itself does) must NOT erase it.
$GLOBALS['__options']['_transient_sn_demo'] = 'x';
foreach ( array_keys( $GLOBALS['__options'] ) as $k ) {
	if ( 0 === strpos( $k, '_transient_sn_' ) ) { unset( $GLOBALS['__options'][ $k ] ); }
}
ok( is_array( get_option( 'sn_last_purge_report', null ) ), 'report survives a _transient_sn_% prune (it is an option, not a transient)' );

// ── 7. Verified route list (filterable, normalized) ──
echo "\nScenario 7: verified-purge route list\n";
$routes = sn_verified_purge_routes();
ok( in_array( '/', $routes, true ) && in_array( '/notes/', $routes, true ) && in_array( '/provenance/', $routes, true ), 'default routes cover the cache-critical set' );
$GLOBALS['__filters']['sn_verified_purge_routes'][] = function ( $r ) { $r[] = '/uses/'; $r[] = ''; $r[] = '/'; return $r; };
$routes2 = sn_verified_purge_routes();
ok( in_array( '/uses/', $routes2, true ), 'route list is filterable' );
ok( ! in_array( '', $routes2, true ) && count( $routes2 ) === count( array_unique( $routes2 ) ), 'routes normalized (no empties, unique)' );

// ── 8. Box-direct freshness probe ──
echo "\nScenario 8: box-direct freshness probe\n";
$GLOBALS['__http_queue'] = array( array( 'code' => 200, 'body' => epoch_html( 5 ), 'headers' => array( 'cf-cache-status' => 'HIT' ) ) );
$p = sn_purge_probe( 'https://example.test/notes/', 5 );
ok( true === $p['fresh'] && 5 === $p['epoch_seen'] && 'HIT' === $p['cf_cache_status'], 'epoch>=expected + reads cf-cache-status => fresh' );
$GLOBALS['__http_queue'] = array( array( 'code' => 200, 'body' => epoch_html( 4 ), 'headers' => array() ) );
ok( false === sn_purge_probe( 'https://example.test/', 5 )['fresh'], 'epoch behind expected => stale' );
$GLOBALS['__http_queue'] = array( array( 'code' => 200, 'body' => '<html></html>', 'headers' => array() ) );
$u = sn_purge_probe( 'https://example.test/', 5 );
ok( null === $u['fresh'] && null === $u['epoch_seen'], 'no epoch meta => unknown (never coerced to fresh)' );
$GLOBALS['__http_queue'] = array( new WP_Error() );
ok( null === sn_purge_probe( 'https://example.test/', 5 )['fresh'], 'WP_Error => unknown, never a false pass' );

// ── 9. Verify + bounded escalate ──
echo "\nScenario 9: verify + bounded escalate\n";
$GLOBALS['__filters']['sn_verified_purge_routes'] = array( function () { return array( '/' ); } );
$GLOBALS['__cf_verified'] = 0;
$GLOBALS['__http_queue']  = array( array( 'code' => 200, 'body' => epoch_html( 7 ), 'headers' => array( 'cf-cache-status' => 'MISS' ) ) );
$v = sn_purge_verify_routes( 7, 3, 0 );
ok( true === $v['resolved'] && 1 === count( $v['routes'] ) && true === $v['routes'][0]['fresh'], 'all-fresh => resolved, no escalation' );
ok( 0 === $GLOBALS['__cf_verified'], 'a fresh route does not re-evict CF' );

$GLOBALS['__cf_verified'] = 0;
$GLOBALS['__http_queue']  = array(
	array( 'code' => 200, 'body' => epoch_html( 6 ), 'headers' => array() ), // attempt 1: stale
	array( 'code' => 200, 'body' => epoch_html( 7 ), 'headers' => array() ), // attempt 2 (post re-evict): fresh
);
$v = sn_purge_verify_routes( 7, 3, 0 );
ok( true === $v['resolved'] && true === $v['routes'][0]['fresh'], 'stale-then-fresh escalation resolves' );
ok( $GLOBALS['__cf_verified'] >= 1, 'a stale route re-evicts CF on escalation' );

$GLOBALS['__cf_verified'] = 0;
$GLOBALS['__http_queue']  = array(
	array( 'code' => 200, 'body' => epoch_html( 5 ), 'headers' => array() ),
	array( 'code' => 200, 'body' => epoch_html( 5 ), 'headers' => array() ),
	array( 'code' => 200, 'body' => epoch_html( 5 ), 'headers' => array() ),
);
$v = sn_purge_verify_routes( 7, 3, 0 );
ok( false === $v['resolved'] && false === $v['routes'][0]['fresh'], 'a persistently-stale route reports resolved:false' );

// ── 10. Verified purge report carries routes + resolved; auto defers ──
echo "\nScenario 10: verified report carries routes; auto defers the probe\n";
$GLOBALS['__filters']['sn_verified_purge_routes']   = array( function () { return array( '/' ); } );
$GLOBALS['__filters']['sn_purge_verify_backoff_us'] = array( function () { return 0; } );
$GLOBALS['__options']['sn_render_epoch']            = 9;
$GLOBALS['__options']['sn_cloudways_last_purge']    = array( 'ok' => true, 'http' => 200, 'operation_id' => 1 );
$GLOBALS['sn_cf_verified_result']                   = array( 'accepted' => true, 'http' => 200, 'cf_success' => true );
$GLOBALS['__http_queue']                            = array( array( 'code' => 200, 'body' => epoch_html( 9 ), 'headers' => array( 'cf-cache-status' => 'MISS' ) ) );
fire( 'sn_after_full_cache_flush', array( 'origin_html' => true, 'cloudflare' => true, 'verified' => true ), 0 );
$report = get_option( 'sn_last_purge_report', null );
ok( isset( $report['routes'] ) && is_array( $report['routes'] ) && 1 === count( $report['routes'] ), 'verified report includes per-route probe results' );
ok( true === ( $report['resolved'] ?? null ), 'verified report carries the resolved verdict' );

$GLOBALS['__http_gets'] = array();
fire( 'sn_after_full_cache_flush', array( 'origin_html' => true, 'cloudflare' => true ), 0 );
$report = get_option( 'sn_last_purge_report', null );
ok( ! isset( $report['routes'] ), 'an auto purge does not probe inline (routes deferred)' );
ok( empty( $GLOBALS['__http_gets'] ), 'auto purge fires no probe HTTP' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
