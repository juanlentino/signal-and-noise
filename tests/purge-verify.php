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

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
