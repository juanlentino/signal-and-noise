<?php
/**
 * Standalone fixture tests for inc/purge-verify-cron.php.
 *
 * Tier-2 follow-up (the "auto-purge cron verify" slice): the inline route probe in
 * inc/purge-verify.php runs only on a VERIFIED (manual) purge; an auto-purge writes its
 * report fast and skips the probe. This module closes that gap by scheduling a one-shot
 * WP-Cron event ~75s after an auto edge-purge, which runs the SAME
 * sn_purge_verify_routes() probe/escalate loop and folds the result back into the
 * durable sn_last_purge_report.
 *
 * Coverage:
 *   1. Registration: scheduler on sn_after_full_cache_flush (priority 20, after the
 *      report writer), handler on the cron hook.
 *   2. An auto edge-purge schedules EXACTLY ONE verify event, carrying the purge's
 *      epoch as a cron arg, ~delay seconds out, and defers the probe (no inline HTTP).
 *   3. No stacking: a repeat auto-purge for the same epoch is idempotent; a later
 *      auto-purge (newer epoch) REPLACES the pending event (latest epoch wins, one event).
 *   4. A verified purge (probes inline) and a non-edge flush schedule NOTHING.
 *   5. The cron handler runs the probe and merges routes/resolved/verify/verified_at
 *      onto the matching report.
 *   6. The handler SKIPS when a newer report has since been written (epoch advanced).
 *   7. The handler no-ops cleanly when routes are unreachable: fresh=null is recorded
 *      honestly, never coerced to a pass.
 *
 * Run: php tests/purge-verify-cron.php
 *
 * @since theme v10.25.0
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
$GLOBALS['__actions']       = array(); // hook => [ [cb, priority], ... ]
$GLOBALS['__filters']       = array();

if ( ! function_exists( 'add_action' ) ) {
	function add_action( $hook, $cb, $priority = 10, $accepted_args = 1 ) {
		$GLOBALS['__actions'][ $hook ][] = array( 'cb' => $cb, 'priority' => (int) $priority );
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
if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $s ) {
		return htmlspecialchars( (string) $s, ENT_QUOTES );
	}
}
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

// FIFO queue of HTTP responses; each wp_remote_get shifts one.
$GLOBALS['__http_queue'] = array();
$GLOBALS['__http_gets']  = array();
function wp_remote_get( $url, $args = array() ) {
	$GLOBALS['__http_gets'][] = $url;
	return $GLOBALS['__http_queue'] ? array_shift( $GLOBALS['__http_queue'] ) : array( 'code' => 200, 'body' => '', 'headers' => array() );
}
function wp_remote_retrieve_response_code( $r ) { return is_array( $r ) ? (int) ( $r['code'] ?? 0 ) : 0; }
function wp_remote_retrieve_body( $r ) { return is_array( $r ) ? (string) ( $r['body'] ?? '' ) : ''; }
function wp_remote_retrieve_header( $r, $h ) { return is_array( $r ) ? (string) ( $r['headers'][ strtolower( $h ) ] ?? '' ) : ''; }

$GLOBALS['__cf_verified'] = 0;
if ( ! function_exists( 'sn_cf_purge_everything_verified' ) ) {
	function sn_cf_purge_everything_verified() { $GLOBALS['__cf_verified']++; return array( 'accepted' => true, 'http' => 200, 'cf_success' => true ); }
}
function epoch_html( $n ) { return "<html><head><meta name=\"sn-render-epoch\" content=\"$n\">\n</head><body>x</body></html>"; }

// ── WP-Cron stubs (arg-identity aware, like core) ──
// Each event: [ 'hook' => string, 'args' => array, 'ts' => int ].
$GLOBALS['__cron'] = array();
function wp_next_scheduled( $hook, $args = array() ) {
	foreach ( $GLOBALS['__cron'] as $e ) {
		// WP keys events by md5(serialize($args)); loose == on same-order scalar args is equivalent.
		if ( $e['hook'] === $hook && $e['args'] == $args ) { return $e['ts']; }
	}
	return false;
}
function wp_schedule_single_event( $ts, $hook, $args = array() ) {
	$GLOBALS['__cron'][] = array( 'hook' => $hook, 'args' => $args, 'ts' => (int) $ts );
	return true;
}
// wp_unschedule_hook drops EVERY event for the hook regardless of args (unlike
// wp_clear_scheduled_hook, which only matches the empty-args key).
function wp_unschedule_hook( $hook ) {
	$before = count( $GLOBALS['__cron'] );
	$GLOBALS['__cron'] = array_values( array_filter( $GLOBALS['__cron'], static function ( $e ) use ( $hook ) {
		return $e['hook'] !== $hook;
	} ) );
	return $before - count( $GLOBALS['__cron'] );
}
function cron_events( $hook ) {
	return array_values( array_filter( $GLOBALS['__cron'], static function ( $e ) use ( $hook ) {
		return $e['hook'] === $hook;
	} ) );
}

require $theme_root . '/inc/purge-verify.php';       // sn_purge_verify_routes, report writer (priority 10), constants
require $theme_root . '/inc/purge-verify-cron.php';  // the module under test

$pass = 0; $fail = 0;
function ok( $cond, $label ) {
	global $pass, $fail;
	if ( $cond ) { $pass++; echo "PASS: $label\n"; }
	else { $fail++; echo "FAIL: $label\n"; }
}
// Invoke every callback on a hook in registration order (both modules register on
// sn_after_full_cache_flush; priority ordering is asserted separately).
function fire( $hook, ...$args ) {
	$cbs = $GLOBALS['__actions'][ $hook ] ?? array();
	usort( $cbs, static function ( $a, $b ) { return $a['priority'] <=> $b['priority']; } );
	foreach ( $cbs as $entry ) {
		call_user_func_array( $entry['cb'], $args );
	}
}

$HOOK = 'sn_verify_auto_purge';

// Keep the escalation backoff at 0 in every probe path so no test really sleeps.
$GLOBALS['__filters']['sn_purge_verify_backoff_us'] = array( function () { return 0; } );

// ── 1. Registration ──
echo "Scenario 1: registration\n";
$after = $GLOBALS['__actions']['sn_after_full_cache_flush'] ?? array();
$sched_priority = null;
foreach ( $after as $e ) {
	if ( 'sn_after_purge_schedule_verify' === $e['cb'] ) { $sched_priority = $e['priority']; }
}
ok( null !== $sched_priority, 'scheduler registered on sn_after_full_cache_flush' );
ok( 20 === $sched_priority, 'scheduler runs at priority 20 (after the report writer @10)' );
ok( ! empty( $GLOBALS['__actions'][ $HOOK ] ), 'cron handler registered on the verify hook' );

// ── 2. Auto edge-purge schedules exactly one verify event, epoch as arg, deferred probe ──
echo "\nScenario 2: an auto edge-purge schedules one deferred verify\n";
$GLOBALS['__cron']                              = array();
$GLOBALS['__http_gets']                         = array();
$GLOBALS['__options']['sn_render_epoch']        = 5;
$GLOBALS['__options']['sn_cloudways_last_purge'] = array( 'ok' => true, 'http' => 200, 'operation_id' => 1 );
$t0 = time();
fire( 'sn_after_full_cache_flush', array( 'origin_html' => true, 'cloudflare' => true ), 0 );

$events = cron_events( $HOOK );
ok( 1 === count( $events ), 'exactly one verify event scheduled' );
ok( array( 5 ) === $events[0]['args'], 'the event carries the purge epoch as a cron arg' );
ok( $events[0]['ts'] >= $t0 + 60 && $events[0]['ts'] <= $t0 + 95, 'the event fires ~60-90s out (edge propagation window)' );
$report = get_option( 'sn_last_purge_report', null );
ok( is_array( $report ) && 'auto' === ( $report['mode'] ?? '' ), 'the auto report was still written by the priority-10 writer' );
ok( ! isset( $report['routes'] ), 'the auto purge does NOT probe inline (routes are deferred to cron)' );
ok( empty( $GLOBALS['__http_gets'] ), 'the auto purge fires no inline probe HTTP' );

// ── 3. No stacking: idempotent for same epoch, replaced for a newer epoch ──
echo "\nScenario 3: rapid auto-purges never stack\n";
fire( 'sn_after_full_cache_flush', array( 'origin_html' => true, 'cloudflare' => true ), 0 ); // same epoch 5
ok( 1 === count( cron_events( $HOOK ) ), 'a repeat purge for the same epoch does not stack a second event' );

$GLOBALS['__options']['sn_render_epoch'] = 6; // a later auto-purge bumped the epoch
fire( 'sn_after_full_cache_flush', array( 'origin_html' => true, 'cloudflare' => true ), 0 );
$events = cron_events( $HOOK );
ok( 1 === count( $events ), 'a newer auto-purge still leaves exactly one event (replace, not stack)' );
ok( array( 6 ) === $events[0]['args'], 'the surviving event targets the LATEST epoch' );

// ── 4. Verified and non-edge purges schedule nothing ──
echo "\nScenario 4: verified + non-edge purges never defer to cron\n";
$GLOBALS['__cron']                       = array();
$GLOBALS['__filters']['sn_verified_purge_routes'] = array( function () { return array( '/' ); } );
$GLOBALS['__options']['sn_render_epoch'] = 7;
$GLOBALS['sn_cf_verified_result']        = array( 'accepted' => true, 'http' => 200, 'cf_success' => true );
$GLOBALS['__http_queue']                 = array( array( 'code' => 200, 'body' => epoch_html( 7 ), 'headers' => array( 'cf-cache-status' => 'MISS' ) ) );
fire( 'sn_after_full_cache_flush', array( 'origin_html' => true, 'cloudflare' => true, 'verified' => true ), 0 );
ok( 0 === count( cron_events( $HOOK ) ), 'a verified purge whose inline probe RESOLVED schedules no cron verify' );

fire( 'sn_after_full_cache_flush', array( 'origin_html' => false, 'cloudflare' => false ), 0 );
ok( 0 === count( cron_events( $HOOK ) ), 'a pure object-cache flush schedules no cron verify' );

// ── 4b. A verified purge that did NOT resolve inline DOES defer (v12.18.2) ──
// The inline probe runs in the request that dispatched the zone purge, so it
// samples one moment of propagation. Manual purges used to return early on
// `verified` outright and therefore had no second look at all — measured, four
// of eleven recorded stale, including 04:09:13 fresh then 04:09:42 stale, while
// every AUTO purge over the same window resolved because it took this path.
echo "\nScenario 4b: a non-resolving verified purge defers like an auto purge\n";
$GLOBALS['__cron']                       = array();
$GLOBALS['__options']['sn_render_epoch'] = 11;
$GLOBALS['sn_cf_verified_result']        = array( 'accepted' => true, 'http' => 200, 'cf_success' => true );
// Every inline attempt sees the OLD epoch: the edge has not caught up yet.
$GLOBALS['__http_queue']                 = array(
	array( 'code' => 200, 'body' => epoch_html( 10 ), 'headers' => array() ),
	array( 'code' => 200, 'body' => epoch_html( 10 ), 'headers' => array() ),
	array( 'code' => 200, 'body' => epoch_html( 10 ), 'headers' => array() ),
);
fire( 'sn_after_full_cache_flush', array( 'origin_html' => true, 'cloudflare' => true, 'verified' => true ), 0 );
$ev = cron_events( $HOOK );
ok( 1 === count( $ev ), 'a verified purge whose inline probe did NOT resolve schedules the deferred verify' );
ok( ! empty( $ev ) && array( 11 ) === $ev[0]['args'], 'and it is bound to THIS purge epoch, so a later purge supersedes it' );

// The report it defers on must actually say unresolved — otherwise the branch
// above could be firing for some unrelated reason and this suite would not know.
$rep = $GLOBALS['__options'][ SN_LAST_PURGE_REPORT_OPT ] ?? array();
ok( isset( $rep['resolved'] ) && false === $rep['resolved'], 'sanity: the inline probe really did record resolved:false here' );

// ── 5. The handler merges the probe result onto the matching report ──
echo "\nScenario 5: the cron handler verifies + merges onto the report\n";
$GLOBALS['__options']['sn_render_epoch']         = 8;
$GLOBALS['__options']['sn_last_purge_report']    = array( 'time' => 100, 'mode' => 'auto', 'epoch' => 8, 'legs' => array( 'cf' => array( 'dispatched' => true, 'confirmed' => null ) ) );
$GLOBALS['__filters']['sn_verified_purge_routes'] = array( function () { return array( '/' ); } );
$GLOBALS['__http_queue']                         = array( array( 'code' => 200, 'body' => epoch_html( 8 ), 'headers' => array( 'cf-cache-status' => 'HIT' ) ) );
sn_verify_auto_purge_cron( 8 );
$report = get_option( 'sn_last_purge_report', null );
ok( isset( $report['routes'] ) && 1 === count( $report['routes'] ) && true === $report['routes'][0]['fresh'], 'handler records the per-route probe result' );
ok( true === ( $report['resolved'] ?? null ), 'handler records the resolved verdict' );
ok( 'cron' === ( $report['verify'] ?? '' ), 'handler stamps verify => cron' );
ok( ! empty( $report['verified_at'] ) && is_int( $report['verified_at'] ), 'handler stamps a verified_at timestamp' );
ok( 'auto' === ( $report['mode'] ?? '' ) && array_key_exists( 'confirmed', $report['legs']['cf'] ), 'handler preserves the existing report schema (mode + legs)' );

// ── 6. The handler skips a report that a newer purge has since replaced ──
echo "\nScenario 6: handler skips when a newer report exists\n";
$GLOBALS['__http_gets']                       = array();
$GLOBALS['__options']['sn_last_purge_report'] = array( 'time' => 200, 'mode' => 'verified', 'epoch' => 9, 'resolved' => true, 'routes' => array() ); // newer
sn_verify_auto_purge_cron( 8 ); // our stale scheduled epoch
$report = get_option( 'sn_last_purge_report', null );
ok( 9 === ( $report['epoch'] ?? 0 ) && 'verified' === $report['mode'], 'the newer report is left untouched' );
ok( ! isset( $report['verify'] ), 'the handler did not stamp the newer report' );
ok( empty( $GLOBALS['__http_gets'] ), 'the handler probed nothing once the epoch no longer matched' );

// ── 7. Unreachable routes: fresh=null recorded honestly, never coerced to a pass ──
echo "\nScenario 7: unreachable route => honest null, never a pass\n";
$GLOBALS['__options']['sn_render_epoch']         = 10;
$GLOBALS['__options']['sn_last_purge_report']    = array( 'time' => 300, 'mode' => 'auto', 'epoch' => 10, 'legs' => array( 'cf' => array( 'dispatched' => true, 'confirmed' => null ) ) );
$GLOBALS['__filters']['sn_verified_purge_routes'] = array( function () { return array( '/' ); } );
$GLOBALS['__http_queue']                         = array( new WP_Error() ); // transport failure
sn_verify_auto_purge_cron( 10 );
$report = get_option( 'sn_last_purge_report', null );
ok( isset( $report['routes'] ) && null === $report['routes'][0]['fresh'], 'an unreachable route records fresh=null' );
ok( true !== $report['routes'][0]['fresh'], 'an unreachable route is NEVER coerced to fresh=true' );
ok( 'cron' === ( $report['verify'] ?? '' ), 'the handler still stamped the run (clean no-op, no crash)' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
