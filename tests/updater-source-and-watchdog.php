<?php
/**
 * Standalone test: the theme updater must claim its own UPLOAD, and its
 * cache watchdog must be reachable outside wp-admin.
 * Run: php tests/updater-source-and-watchdog.php
 *
 * WHY THIS FILE EXISTS (2026-08-24)
 *
 * Both behaviours here were, until now, provided by an untracked must-use
 * plugin on the live server — `wp-content/mu-plugins/sn-theme-updater.php`,
 * present in no repository, carrying no version, reviewed by no CI, loading on
 * every request and updatable by nothing. It is being retired, so the half of
 * it that was actually earning its keep moves here.
 *
 * WHAT WAS WORTH KEEPING — the upload rename.
 *
 * `upgrader_source_selection` below gated on `$hook_extra['theme']`. That key
 * is set for an UPDATE and unset for a manual Upload Theme, so the gate could
 * never match an upload and GitHub's version-suffixed directory
 * (`signal-and-noise-12.6.1/`) survived into wp-content/themes/ under the wrong
 * slug. The mu-plugin covered that case by sniffing the unpacked style.css.
 *
 * WHAT WAS NOT — an unscoped removal of a core safety guard.
 *
 * The mu-plugin also filtered `upgrader_package_options` and, for ANY package
 * whose `hook_extra['action']` was `install`, set `clear_destination = true`
 * and `abort_if_destination_exists = false`. That filter runs in
 * `WP_Upgrader::run()` for every install and update of every plugin, theme,
 * core and language pack — the branch had no scope check at all. Core's
 * default `abort_if_destination_exists => true` is what returns
 * `WP_Error('folder_exists')` instead of overwriting something already
 * installed; switching it off site-wide meant any install whose directory
 * already existed silently recursive-deleted it first. That is NOT ported.
 *
 * The sniff here is also tightened. The mu-plugin matched
 * `strpos( $data['Name'], 'Signal' )`, which would also claim "Signal Boost"
 * or "Signals". This keys on the exact Text Domain instead.
 *
 * @package SignalNoise
 */

if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }

define( 'ABSPATH', '/' );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'MINUTE_IN_SECONDS', 60 );

// ── WP seams ───────────────────────────────────────────────────────────────
$GLOBALS['__filters'] = array();
$GLOBALS['__actions'] = array();
function add_filter( $h, $c = null, $p = 10, $a = 1 ) { $GLOBALS['__filters'][ $h ][] = $c; }
function add_action( $h, $c = null, $p = 10, $a = 1 ) { $GLOBALS['__actions'][ $h ][] = $c; }
function apply_filters( $h, $v ) { return $v; }
function remove_filter( $h, $c, $p = 10 ) {}

function trailingslashit( $s ) { return rtrim( (string) $s, '/\\' ) . '/'; }
function untrailingslashit( $s ) { return rtrim( (string) $s, '/\\' ); }
function is_wp_error( $t ) { return $t instanceof WP_Error; }
class WP_Error {
	private $code; private $message;
	public function __construct( $c = '', $m = '' ) { $this->code = $c; $this->message = $m; }
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
}
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function __( $s, $d = null ) { return $s; }
function home_url( $p = '' ) { return 'https://example.test' . $p; }

// Real header parse against a real file — the producer under test reads a real
// style.css, so a fixture that invented the shape would prove nothing.
function get_file_data( $file, $fields, $context = '' ) {
	$out = array_fill_keys( array_keys( $fields ), '' );
	$src = (string) @file_get_contents( $file );
	foreach ( $fields as $key => $label ) {
		if ( preg_match( '/^[ \t\/*#@]*' . preg_quote( $label, '/' ) . ':(.*)$/mi', $src, $m ) ) {
			$out[ $key ] = trim( $m[1] );
		}
	}
	return $out;
}

$GLOBALS['__theme_version'] = '12.6.1';
function wp_get_theme( $s = null ) {
	return new class {
		public function get( $k ) { return 'Version' === $k ? $GLOBALS['__theme_version'] : ''; }
	};
}
$GLOBALS['__options'] = array();
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['__options'] ) ? $GLOBALS['__options'][ $k ] : $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['__options'][ $k ] = $v; return true; }
$GLOBALS['__site_trans'] = array();
$GLOBALS['__deleted']    = array();
function get_site_transient( $k ) { return $GLOBALS['__site_trans'][ $k ] ?? false; }
function set_site_transient( $k, $v, $t = 0 ) { $GLOBALS['__site_trans'][ $k ] = $v; return true; }
function delete_site_transient( $k ) { $GLOBALS['__deleted'][] = $k; unset( $GLOBALS['__site_trans'][ $k ] ); return true; }
$GLOBALS['__clean_themes'] = 0;
function wp_clean_themes_cache( $clear_update_cache = true ) { $GLOBALS['__clean_themes']++; }

function wp_remote_get( $u, $a = array() ) { return array( 'response' => array( 'code' => 200 ), 'body' => json_encode( array( array( 'name' => 'v12.6.1' ) ) ) ); }
function wp_remote_retrieve_response_code( $r ) { return is_array( $r ) ? (int) ( $r['response']['code'] ?? 0 ) : 0; }
function wp_remote_retrieve_body( $r ) { return is_array( $r ) ? (string) ( $r['body'] ?? '' ) : ''; }
function wp_remote_retrieve_header( $r, $h ) { return ''; }
function download_url( $u ) { return '/tmp/x.zip'; }

// A filesystem that really moves directories, so "renamed" means renamed.
class SN_Test_FS {
	public function move( $from, $to, $overwrite = false ) { return @rename( $from, $to ); }
}
$GLOBALS['wp_filesystem'] = new SN_Test_FS();

require __DIR__ . '/../inc/wp-update-integration.php';

$pass = 0; $fail = 0;
function ok( $c, $m ) { global $pass, $fail; if ( $c ) { $pass++; echo "PASS: $m\n"; } else { $fail++; echo "FAIL: $m\n"; } }

// ── Build real unpacked-package directories ────────────────────────────────
$base = sys_get_temp_dir() . '/sn-src-' . getmypid();
@mkdir( $base, 0777, true );
function make_pkg( $base, $dir, $name, $domain ) {
	$p = $base . '/' . $dir;
	@mkdir( $p, 0777, true );
	file_put_contents( $p . '/style.css', "/*\nTheme Name: $name\nText Domain: $domain\nVersion: 1.0.0\n*/\n" );
	return $p . '/';
}

// ── 1. The identity check ──────────────────────────────────────────────────
ok( function_exists( 'sn_gh_theme_source_is_ours' ), 'the source identity check exists' );

if ( function_exists( 'sn_gh_theme_source_is_ours' ) ) {
	$ours = make_pkg( $base, 'signal-and-noise-12.6.1', 'Signal & Noise', 'signal-noise' );
	ok( true === sn_gh_theme_source_is_ours( $ours ), 'our own unpacked upload is recognised' );

	$near = make_pkg( $base, 'signal-boost', 'Signal Boost', 'signal-boost' );
	ok( false === sn_gh_theme_source_is_ours( $near ), 'a theme merely NAMED "Signal ..." is NOT claimed' );

	$none = $base . '/no-style/';
	@mkdir( $none, 0777, true );
	ok( false === sn_gh_theme_source_is_ours( $none ), 'a directory with no style.css is not claimed' );
}

// ── 2. The filter, on the upload path ──────────────────────────────────────
$cb = $GLOBALS['__filters']['upgrader_source_selection'][0] ?? null;
ok( is_callable( $cb ), 'the source-selection filter is registered' );

if ( is_callable( $cb ) ) {
	// UPLOAD: hook_extra carries no 'theme' key. This is the case the old gate
	// could never match, and the case the mu-plugin existed to cover.
	$src = make_pkg( $base, 'signal-and-noise-12.6.1-up', 'Signal & Noise', 'signal-noise' );
	$out = $cb( $src, $base . '/', null, array( 'type' => 'theme', 'action' => 'install' ) );
	ok(
		is_string( $out ) && basename( untrailingslashit( $out ) ) === SN_GH_THEME_STYLESHEET,
		'upload with no hook_extra[theme]: renamed to the correct stylesheet slug'
	);
	ok( is_dir( $base . '/' . SN_GH_THEME_STYLESHEET ), 'upload: the directory really moved on disk' );
	@rename( $base . '/' . SN_GH_THEME_STYLESHEET, $base . '/consumed' );

	// A foreign upload must pass straight through, untouched.
	$foreign = make_pkg( $base, 'someone-elses-theme', 'Someone Elses Theme', 'someone-else' );
	$out2    = $cb( $foreign, $base . '/', null, array( 'type' => 'theme', 'action' => 'install' ) );
	ok( $out2 === $foreign, "a foreign theme's upload is returned untouched" );
	ok( is_dir( $base . '/someone-elses-theme' ), 'a foreign theme is not moved' );

	// REGRESSION GUARD: the update path must still work.
	$upd = make_pkg( $base, 'signal-and-noise-12.6.2', 'Signal & Noise', 'signal-noise' );
	$out3 = $cb( $upd, $base . '/', null, array( 'theme' => SN_GH_THEME_STYLESHEET ) );
	ok(
		is_string( $out3 ) && basename( untrailingslashit( $out3 ) ) === SN_GH_THEME_STYLESHEET,
		'update path (hook_extra[theme] set) still renames — unchanged behaviour'
	);
}

// ── 3. The watchdog ────────────────────────────────────────────────────────
ok( ! empty( $GLOBALS['__actions']['init'] ), 'watchdog registers on init (WP-CLI, wp-cron, front end)' );
ok( function_exists( 'sn_gh_theme_version_watchdog' ), 'watchdog is a named function' );

if ( function_exists( 'sn_gh_theme_version_watchdog' ) ) {
	$GLOBALS['__options'][ SN_GH_THEME_LAST_SEEN_OPT ] = '12.5.0';
	$GLOBALS['__deleted']      = array();
	$GLOBALS['__clean_themes'] = 0;

	sn_gh_theme_version_watchdog();

	ok( in_array( SN_GH_THEME_CACHE_KEY, $GLOBALS['__deleted'], true ), 'version change: clears the GitHub tag cache' );
	ok( in_array( 'update_themes', $GLOBALS['__deleted'], true ), "version change: clears WP's update_themes transient" );
	ok( 1 === $GLOBALS['__clean_themes'], 'version change: cleans the parsed theme-header cache' );
	ok( '12.6.1' === ( $GLOBALS['__options'][ SN_GH_THEME_LAST_SEEN_OPT ] ?? '' ), 'version change: records the new version' );

	$GLOBALS['__deleted']      = array();
	$GLOBALS['__clean_themes'] = 0;
	sn_gh_theme_version_watchdog();
	ok( array() === $GLOBALS['__deleted'], 'no version change: deletes nothing (safe on every request)' );
}

// cleanup
function rrm( $d ) { if ( ! is_dir( $d ) ) return; foreach ( scandir( $d ) as $f ) { if ( '.' === $f || '..' === $f ) continue; $p = "$d/$f"; is_dir( $p ) ? rrm( $p ) : @unlink( $p ); } @rmdir( $d ); }
rrm( $base );

echo "\n$pass passed, $fail failed\n";
exit( $fail > 0 ? 1 : 0 );
