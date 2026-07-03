<?php
/**
 * Standalone fixture tests for inc/template-maintenance.php's automatic
 * purge triggers.
 *
 * 2026-07-02 incident: installing theme v10.21.9 and deleting a Styles
 * Additional-CSS rule left three cache layers (Breeze file cache → Varnish
 * → Cloudflare) serving a morning-stale render through FOUR manual
 * layer-by-layer purges — the coordinated sn_purge_all_caches() chain
 * existed (correct inner→outer order, wired to the admin-bar button) but
 * NOTHING fired it automatically. These tests lock the trigger wiring:
 *
 *   1. upgrader_process_complete for OUR theme or plugin update → full
 *      chain (minus template overrides — an update must never nuke Site
 *      Editor edits), once per request.
 *   2. save_post_wp_global_styles (Site Editor Styles saves, incl.
 *      Additional CSS) → focused origin-HTML + CDN purge.
 *   3. Unrelated themes/plugins and non-update actions never purge.
 *
 * Run: php tests/template-maintenance.php
 *
 * @since theme v10.22.0
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

// ── Captured hook registries + call counters ──
$GLOBALS['__actions']       = array();
$GLOBALS['__filters']       = array();
$GLOBALS['__fired_actions'] = array();  // do_action recorder
$GLOBALS['__cache_flushes'] = 0;
$GLOBALS['__cf_purges']     = 0;
$GLOBALS['__cf_verified']   = 0;        // sn_cf_purge_everything_verified calls
$GLOBALS['__theme_updates'] = 0;        // wp_update_themes calls
$GLOBALS['__posts_deleted'] = 0;

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
if ( ! function_exists( 'do_action' ) ) {
	function do_action( $hook, ...$args ) {
		$GLOBALS['__fired_actions'][] = $hook;
	}
}
if ( ! function_exists( 'wp_parse_args' ) ) {
	function wp_parse_args( $args, $defaults ) {
		return array_merge( $defaults, (array) $args );
	}
}
if ( ! function_exists( 'wp_cache_flush' ) ) {
	function wp_cache_flush() {
		$GLOBALS['__cache_flushes']++;
	}
}
if ( ! function_exists( 'delete_site_transient' ) ) {
	function delete_site_transient( $key ) {}
}
if ( ! function_exists( 'wp_clean_themes_cache' ) ) {
	function wp_clean_themes_cache() {}
}
if ( ! function_exists( 'wp_clean_plugins_cache' ) ) {
	function wp_clean_plugins_cache() {}
}
if ( ! function_exists( 'sn_cf_purge_everything' ) ) {
	// Plugin-side function — stubbed as a counter so the theme module's
	// function_exists() gate passes and we can assert the CDN purge fired.
	function sn_cf_purge_everything() {
		$GLOBALS['__cf_purges']++;
		return true;
	}
}
if ( ! function_exists( 'sn_cf_purge_everything_verified' ) ) {
	// Plugin-side blocking variant (v8.7.0) — the verified purge path routes CF
	// here instead of the non-blocking fn so it can read a real accept-confirmation.
	function sn_cf_purge_everything_verified() {
		$GLOBALS['__cf_verified']++;
		return array( 'accepted' => true, 'http' => 200, 'cf_success' => true );
	}
}
if ( ! function_exists( 'wp_update_themes' ) ) {
	function wp_update_themes() {
		$GLOBALS['__theme_updates']++;
	}
}
if ( ! function_exists( 'get_posts' ) ) {
	function get_posts( $args = array() ) {
		return array();
	}
}
if ( ! function_exists( 'wp_delete_post' ) ) {
	function wp_delete_post( $id, $force = false ) {
		$GLOBALS['__posts_deleted']++;
	}
}
if ( ! function_exists( 'get_stylesheet' ) ) {
	function get_stylesheet() {
		return 'signal-and-noise';
	}
}
if ( ! function_exists( 'get_template' ) ) {
	function get_template() {
		return 'signal-and-noise';
	}
}

require $theme_root . '/inc/template-maintenance.php';

$pass = 0; $fail = 0;
function ok( $cond, $label ) {
	global $pass, $fail;
	if ( $cond ) { $pass++; echo "PASS: $label\n"; }
	else { $fail++; echo "FAIL: $label\n"; }
}
function purge_counts() {
	return array(
		'breeze'  => count( array_keys( $GLOBALS['__fired_actions'], 'breeze_clear_all_cache', true ) ),
		'varnish' => count( array_keys( $GLOBALS['__fired_actions'], 'breeze_clear_varnish', true ) ),
		'cf'      => $GLOBALS['__cf_purges'],
		'cf_verified' => $GLOBALS['__cf_verified'],
		'flush'   => $GLOBALS['__cache_flushes'],
		'repop'   => $GLOBALS['__theme_updates'],
		'deleted' => $GLOBALS['__posts_deleted'],
	);
}
function reset_counters() {
	$GLOBALS['__fired_actions'] = array();
	$GLOBALS['__cache_flushes'] = 0;
	$GLOBALS['__cf_purges']     = 0;
	$GLOBALS['__cf_verified']   = 0;
	$GLOBALS['__theme_updates'] = 0;
	$GLOBALS['__posts_deleted'] = 0;
	unset( $GLOBALS['sn_auto_purge_done'] );
	unset( $GLOBALS['sn_cf_verified_result'] );
}
function fire_upgrader( $hook_extra ) {
	foreach ( $GLOBALS['__actions']['upgrader_process_complete'] ?? array() as $cb ) {
		call_user_func( $cb, new stdClass(), $hook_extra );
	}
}
function fire_styles_save() {
	foreach ( $GLOBALS['__actions']['save_post_wp_global_styles'] ?? array() as $cb ) {
		call_user_func( $cb, 123, new stdClass() );
	}
}

// ── 1. The triggers are registered ──
echo "Scenario 1: hook registration\n";
ok( ! empty( $GLOBALS['__actions']['upgrader_process_complete'] ), 'upgrader_process_complete trigger registered' );
ok( ! empty( $GLOBALS['__actions']['save_post_wp_global_styles'] ), 'save_post_wp_global_styles trigger registered' );

// ── 2. Our THEME update → full chain, overrides untouched ──
echo "\nScenario 2: our theme update purges the full chain\n";
reset_counters();
fire_upgrader( array( 'type' => 'theme', 'action' => 'update', 'themes' => array( 'signal-and-noise' ) ) );
$c = purge_counts();
ok( 1 === $c['breeze'], 'Breeze file cache purge fired' );
ok( 1 === $c['varnish'], 'Varnish purge fired' );
ok( 1 === $c['cf'], 'Cloudflare zone purge fired' );
ok( 1 === $c['flush'], 'object cache flushed' );
ok( 1 === $c['repop'], 'update_themes repopulated' );
ok( 0 === $c['deleted'], 'Site Editor template overrides NOT touched by an update purge' );

// ── 3. Once per request (a theme+plugin batch fires the hook per package) ──
echo "\nScenario 3: debounce\n";
fire_upgrader( array( 'type' => 'plugin', 'action' => 'update', 'plugins' => array( 'signal-and-noise-tools/signal-and-noise-tools.php' ) ) );
$c = purge_counts();
ok( 1 === $c['cf'], 'second qualifying update in the same request does not re-purge' );

// ── 4. Unrelated packages / non-update actions → no purge ──
echo "\nScenario 4: negatives\n";
reset_counters();
fire_upgrader( array( 'type' => 'theme', 'action' => 'update', 'themes' => array( 'twentytwentyfive' ) ) );
fire_upgrader( array( 'type' => 'plugin', 'action' => 'update', 'plugins' => array( 'akismet/akismet.php' ) ) );
fire_upgrader( array( 'type' => 'theme', 'action' => 'install', 'themes' => array( 'signal-and-noise' ) ) );
fire_upgrader( array( 'type' => 'translation', 'action' => 'update' ) );
fire_upgrader( 'not-an-array' );
$c = purge_counts();
ok( 0 === $c['cf'] && 0 === $c['breeze'] && 0 === $c['flush'], 'unrelated packages, installs, translations, and malformed hook_extra never purge' );

// ── 5. Our PLUGIN update → full chain ──
echo "\nScenario 5: our plugin update purges the full chain\n";
reset_counters();
fire_upgrader( array( 'type' => 'plugin', 'action' => 'update', 'plugins' => array( 'signal-and-noise-tools/signal-and-noise-tools.php' ) ) );
$c = purge_counts();
ok( 1 === $c['breeze'] && 1 === $c['varnish'] && 1 === $c['cf'], 'plugin update fires Breeze + Varnish + Cloudflare' );

// ── 6. Global-styles save → focused origin+CDN purge ──
echo "\nScenario 6: Styles save (incl. Additional CSS) purges origin + CDN only\n";
reset_counters();
fire_styles_save();
$c = purge_counts();
ok( 1 === $c['breeze'], 'Breeze purge fired on styles save' );
ok( 1 === $c['varnish'], 'Varnish purge fired on styles save' );
ok( 1 === $c['cf'], 'Cloudflare purge fired on styles save' );
ok( 0 === $c['flush'], 'styles save does not flush the object cache' );
ok( 0 === $c['repop'], 'styles save does not re-run update_themes' );
ok( 0 === $c['deleted'], 'styles save never touches template overrides' );

// ── 7. Verified purge routes CF to the blocking variant + fires the seams ──
echo "\nScenario 7: a verified purge confirms CF + fires the before/after seams\n";
reset_counters();
$ret = sn_purge_all_caches( array( 'verified' => true, 'template_overrides' => false ) );
$c   = purge_counts();
$before_fired = count( array_keys( $GLOBALS['__fired_actions'], 'sn_before_cache_flush', true ) );
$after_fired  = count( array_keys( $GLOBALS['__fired_actions'], 'sn_after_full_cache_flush', true ) );
ok( is_int( $ret ), 'sn_purge_all_caches still returns an int (contract preserved)' );
ok( 1 === $before_fired, 'sn_before_cache_flush seam fired once' );
ok( 1 === $after_fired, 'sn_after_full_cache_flush seam fired once' );
ok( 1 === $c['cf_verified'], 'verified purge calls the blocking CF variant' );
ok( 0 === $c['cf'], 'verified purge does NOT call the non-blocking CF fn' );
ok( isset( $GLOBALS['sn_cf_verified_result'] ) && ! empty( $GLOBALS['sn_cf_verified_result']['cf_success'] ),
	'the blocking CF result is stashed for the report writer' );

// ── 8. A non-verified purge keeps the fast non-blocking CF, still fires the seam ──
echo "\nScenario 8: an auto (non-verified) purge stays fast\n";
reset_counters();
sn_purge_all_caches( array( 'template_overrides' => false ) );
$c = purge_counts();
ok( 1 === $c['cf'], 'auto purge uses the fast non-blocking CF fn' );
ok( 0 === $c['cf_verified'], 'auto purge does NOT block on CF' );
ok( 1 === count( array_keys( $GLOBALS['__fired_actions'], 'sn_before_cache_flush', true ) ), 'the before seam fires on auto purges too' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
