<?php
/**
 * Fixture test: the theme's self-updater must NAME its failures, and must not
 * let a transient upstream blip blind the Dashboard for an hour.
 *
 * WHY THIS FILE EXISTS (theme v10.43.0 — porting plugin v9.54.0 + v9.54.1)
 *
 * 2026-07-16 22:51 UTC, GitHub declared "Degraded REST API Availability":
 * ~35% of REST requests failing, "not consistently reaching the application
 * layer". Both S&N version cards went red four minutes later.
 *
 * The plugin's card said WHY — "GitHub returned an unexpected HTTP 503" — and
 * that one line ended an hour of wrong theorising (an expired token, then a
 * response-size timeout; both confidently argued, both false). The THEME's card
 * said nothing at all: just a red "unknown". Same outage, same second, same
 * screen — one surface could explain itself and the other could not.
 *
 * The plugin's v9.54.0 added the filter seam `sn_gh_latest_theme_tag_error_result`
 * for exactly this, and then only implemented its own side. This closes it.
 *
 * Two behaviours under test:
 *   1. A failure records a REASON, the card can read it via the filter, and a
 *      success CLEARS it (or a stale caption outlives the fix).
 *   2. Failures are cached in proportion to how likely they are to STILL be
 *      true. The theme cached EVERY failure for an hour, so a one-second blip
 *      cost 60 minutes — and the next hourly poll had another ~35% chance of
 *      re-arming it. Transient → retry once, 5 min. Durable → 1 hour, no retry.
 *
 * Run: php tests/updater-failure-modes.php
 *
 * @since theme v10.43.0
 */

if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) {
	http_response_code( 404 );
	exit;
}

define( 'ABSPATH', '/' );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'MINUTE_IN_SECONDS', 60 );

// ── stubs ───────────────────────────────────────────────────────────
$GLOBALS['__transients'] = array();
$GLOBALS['__filters']    = array();
$GLOBALS['__calls']      = 0;
$GLOBALS['__http']       = null;

if ( ! function_exists( 'add_action' ) ) { function add_action() {} }
if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( $tag, $cb, $p = 10, $a = 1 ) { $GLOBALS['__filters'][ $tag ][] = $cb; return true; }
}
if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $tag, $value ) {
		foreach ( $GLOBALS['__filters'][ $tag ] ?? array() as $cb ) { $value = $cb( $value ); }
		return $value;
	}
}
if ( ! function_exists( 'home_url' ) ) { function home_url( $p = '' ) { return 'https://example.test' . $p; } }
if ( ! function_exists( '__' ) ) { function __( $s, $d = null ) { return $s; } }
if ( ! function_exists( 'get_site_transient' ) ) { function get_site_transient( $k ) { return $GLOBALS['__transients'][ $k ] ?? false; } }
if ( ! function_exists( 'set_site_transient' ) ) { function set_site_transient( $k, $v, $ttl = 0 ) { $GLOBALS['__transients'][ $k ] = $v; return true; } }
if ( ! function_exists( 'delete_site_transient' ) ) { function delete_site_transient( $k ) { unset( $GLOBALS['__transients'][ $k ] ); return true; } }
// WP_Error must actually CARRY its message: a stub that swallows the
// constructor args (as the plugin's did until v9.54.0) makes every
// network-failure assertion either fatal or vacuous.
if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		private $code; private $message;
		public function __construct( $c = '', $m = '' ) { $this->code = $c; $this->message = $m; }
		public function get_error_code() { return $this->code; }
		public function get_error_message() { return $this->message; }
	}
}
if ( ! function_exists( 'is_wp_error' ) ) { function is_wp_error( $t ) { return $t instanceof WP_Error; } }
if ( ! function_exists( 'wp_remote_retrieve_response_code' ) ) { function wp_remote_retrieve_response_code( $r ) { return is_array( $r ) ? (int) ( $r['response']['code'] ?? 0 ) : 0; } }
if ( ! function_exists( 'wp_remote_retrieve_body' ) ) { function wp_remote_retrieve_body( $r ) { return is_array( $r ) ? (string) ( $r['body'] ?? '' ) : ''; } }
if ( ! function_exists( 'wp_remote_get' ) ) {
	function wp_remote_get( $url, $args = array() ) {
		$GLOBALS['__calls']++;
		$healthy = array( 'response' => array( 'code' => 200 ), 'body' => json_encode( array( array( 'name' => 'v9.9.9' ) ) ) );
		// Models the ACTUAL incident: the same URL 503s once and answers fine a
		// moment later. A stub returning one fixed response cannot express
		// "flaky", so a retry test written against it would pass without any
		// retry ever happening.
		if ( 'flaky-503-then-200' === $GLOBALS['__http'] ) {
			return $GLOBALS['__calls'] < 2
				? array( 'response' => array( 'code' => 503 ), 'body' => 'Service Unavailable' )
				: $healthy;
		}
		return null !== $GLOBALS['__http'] ? $GLOBALS['__http'] : $healthy;
	}
}

require_once __DIR__ . '/../inc/wp-update-integration.php';

$pass = 0; $fail = 0;
function ok( $cond, $label ) {
	global $pass, $fail;
	if ( $cond ) { $pass++; echo "PASS: $label\n"; }
	else { $fail++; echo "FAIL: $label\n"; }
}

/** Reset between cases. */
function reset_state( $http = null ) {
	$GLOBALS['__transients'] = array();
	$GLOBALS['__calls']      = 0;
	$GLOBALS['__http']       = $http;
}

echo "Theme self-updater — failure modes (v10.43.0)\n\n";

echo "-- the card can finally say WHY --\n";
reset_state( array( 'response' => array( 'code' => 503 ), 'body' => 'Service Unavailable' ) );
ok( null === sn_gh_latest_theme_tag( true ), '503: still returns null (update path unchanged)' );
$why = sn_gh_latest_theme_tag_error();
ok( is_string( $why ) && '' !== $why, '503: a reason is recorded at all — this is the gap the plugin fixed and the theme did not' );
ok( false !== stripos( $why, '503' ), '503: the reason names the status code (the exact line that ended the real investigation)' );

reset_state( array( 'response' => array( 'code' => 401 ), 'body' => '' ) );
sn_gh_latest_theme_tag( true );
$why401 = sn_gh_latest_theme_tag_error();
ok( false !== stripos( $why401, '401' ), '401: the reason names the status code' );
ok( false !== stripos( $why401, 'SNT_GITHUB_TOKEN' ), '401: the reason names the exact wp-config constant to rotate' );
ok( $why401 !== $why, '401 and 503 read differently — the card can tell them apart' );

reset_state( new WP_Error( 'http_request_failed', 'cURL error 28: Operation timed out after 8001 milliseconds' ) );
sn_gh_latest_theme_tag( true );
ok( false !== stripos( sn_gh_latest_theme_tag_error(), 'timed out' ),
	'WP_Error: the reason carries the real cURL message — the number in it IS the diagnosis' );

echo "\n-- the card actually READS it (the filter seam the plugin opened in v9.54.0) --\n";
// The plugin's snt_deploy_status_for('theme') does:
//   apply_filters( 'sn_gh_latest_theme_tag_error_result', '' )
// Without a listener that returns '' forever and the theme card stays a bare,
// unexplained red dot — which is exactly what the owner saw on 2026-07-16.
reset_state( array( 'response' => array( 'code' => 503 ), 'body' => '' ) );
sn_gh_latest_theme_tag( true );
ok( false !== stripos( (string) apply_filters( 'sn_gh_latest_theme_tag_error_result', '' ), '503' ),
	'the plugin-owned filter returns the theme reason (without this the card cannot render it)' );

echo "\n-- success CLEARS it, or the fix becomes the next bug --\n";
reset_state( array( 'response' => array( 'code' => 503 ), 'body' => '' ) );
sn_gh_latest_theme_tag( true );
ok( '' !== sn_gh_latest_theme_tag_error(), 'precondition: an error is on record' );
$GLOBALS['__http'] = null; // healthy
ok( 'v9.9.9' === sn_gh_latest_theme_tag( true ), 'recovery: a healthy fetch returns the tag' );
ok( '' === sn_gh_latest_theme_tag_error(), 'recovery CLEARS the reason — no stale caption after GitHub recovers' );
ok( '' === (string) apply_filters( 'sn_gh_latest_theme_tag_error_result', '' ), '…and the card renders nothing' );

echo "\n-- never leak the credential onto a screen --\n";
reset_state( new WP_Error( 'http_request_failed', 'Bearer ghp_SUPERSECRETTOKENVALUE rejected' ) );
sn_gh_latest_theme_tag( true );
ok( false === strpos( sn_gh_latest_theme_tag_error(), 'ghp_SUPERSECRETTOKENVALUE' ),
	'a token-shaped string is redacted before it can reach the Dashboard' );

echo "\n-- a blip must not cost an hour (the 60-vs-5 asymmetry) --\n";
ok( sn_gh_theme_failure_is_transient( 503 ), '503 is TRANSIENT — GitHub recovers on its own' );
ok( sn_gh_theme_failure_is_transient( 0 ), 'a network error / timeout is TRANSIENT' );
ok( ! sn_gh_theme_failure_is_transient( 401 ), '401 is DURABLE — a dead token will not fix itself' );
ok( ! sn_gh_theme_failure_is_transient( 404 ), '404 is DURABLE' );
ok( sn_gh_theme_failure_ttl( 503 ) < sn_gh_theme_failure_ttl( 401 ), 'transient is cached for LESS time than durable' );
ok( sn_gh_theme_failure_ttl( 503 ) <= 5 * 60, 'a 503 blinds the theme card for at most 5 min, not 60' );
ok( sn_gh_theme_failure_ttl( 401 ) >= 3600, 'a 401 still holds an hour — no point hammering GitHub over a dead credential' );

echo "\n-- retry: transient only --\n";
reset_state( array( 'response' => array( 'code' => 503 ), 'body' => '' ) );
sn_gh_latest_theme_tag( true );
ok( $GLOBALS['__calls'] >= 2, 'a 503 is retried once (against ~35% failures, one retry recovers most polls)' );

reset_state( array( 'response' => array( 'code' => 401 ), 'body' => '' ) );
sn_gh_latest_theme_tag( true );
ok( 1 === $GLOBALS['__calls'], 'a 401 is NOT retried — the second answer is the first answer' );

reset_state( 'flaky-503-then-200' );
ok( 'v9.9.9' === sn_gh_latest_theme_tag( true ), 'a 503 followed by a 200 RECOVERS in the same poll — the real 35% case' );
ok( '' === sn_gh_latest_theme_tag_error(), '…and recovering leaves no caption behind' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
