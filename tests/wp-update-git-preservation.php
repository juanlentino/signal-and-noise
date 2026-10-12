<?php
/**
 * Fixture tests: inc/wp-update-git-preservation.php keeps the theme's .git
 * through a wp-admin "Update Now", and its admin_init self-recovery cannot
 * race an update that is still running.
 *
 * The race (theme audit P2-2, 2026.10): upgrader_pre_install renames .git to
 * the backup path; admin_init also fires on admin-ajax.php, so a Heartbeat
 * from another open wp-admin tab could land before core's clear_destination(),
 * move .git back into the OLD theme dir, and have it deleted with that dir.
 * post_install then found no backup and .git was gone silently. A transient
 * held from pre_install to post_install now tells the recovery to wait.
 *
 * Real temp dirs, so rename() is real.
 *
 * Run: php tests/wp-update-git-preservation.php
 */

if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) {
	http_response_code( 404 );
	exit;
}

define( 'ABSPATH', '/' );
define( 'MINUTE_IN_SECONDS', 60 );
$root = sys_get_temp_dir() . '/sn-git-pres-' . getmypid();
define( 'WP_CONTENT_DIR', $root . '/wp-content' );
define( 'SN_GH_THEME_STYLESHEET', 'signal-and-noise' );
define( 'SN_GH_THEME_OWNER', 'juanlentino' );
define( 'SN_GH_THEME_REPO', 'signal-and-noise' );

$GLOBALS['hooks']      = array();
$GLOBALS['transients'] = array();
function add_filter( $tag, $cb ) { $GLOBALS['hooks'][ $tag ] = $cb; }
function add_action( $tag, $cb ) { $GLOBALS['hooks'][ $tag ] = $cb; }
function trailingslashit( $p ) { return rtrim( $p, '/' ) . '/'; }
function get_theme_root( $t = '' ) { return WP_CONTENT_DIR . '/themes'; }
function wp_mkdir_p( $d ) { return is_dir( $d ) || mkdir( $d, 0777, true ); }
function is_wp_error( $x ) { return $x instanceof WP_Error; }
function esc_html( $s ) { return $s; }
function set_transient( $k, $v, $ttl = 0 ) { $GLOBALS['transients'][ $k ] = $v; return true; }
function get_transient( $k ) { return $GLOBALS['transients'][ $k ] ?? false; }
function delete_transient( $k ) { unset( $GLOBALS['transients'][ $k ] ); return true; }
class WP_Error { public function __construct( $c = '', $m = '' ) {} }

require __DIR__ . '/../inc/wp-update-git-preservation.php';

$pass = 0;
$fail = 0;
function ok( $c, $m ) {
	global $pass, $fail;
	if ( $c ) { ++$pass; echo "PASS: $m\n"; } else { ++$fail; echo "FAIL: $m\n"; }
}

$theme_dir = WP_CONTENT_DIR . '/themes/signal-and-noise';
$git       = $theme_dir . '/.git';
$backup    = sn_theme_git_backup_path();
$extra     = array( 'theme' => 'signal-and-noise' );

function fresh_theme( $theme_dir ) {
	exec( 'rm -rf ' . escapeshellarg( dirname( dirname( $theme_dir ) ) ) );
	mkdir( $theme_dir . '/.git', 0777, true );
	file_put_contents( $theme_dir . '/.git/HEAD', "ref: refs/heads/main\n" );
	$GLOBALS['transients'] = array();
}

// ── A: Heartbeat mid-update must not restore .git into the doomed dir ──
fresh_theme( $theme_dir );
$GLOBALS['hooks']['upgrader_pre_install']( true, $extra );
ok( ! is_dir( $git ) && is_dir( $backup ), 'A1 pre_install moves .git to the backup path' );
$GLOBALS['hooks']['admin_init'](); // a Heartbeat from another tab, before clear_destination()
ok( is_dir( $backup ) && ! is_dir( $git ), 'A2 admin_init recovery WAITS while the update holds its lock (no restore into the dir core is about to delete)' );
$GLOBALS['hooks']['upgrader_post_install']( true, $extra, array( 'destination' => $theme_dir ) );
ok( is_dir( $git ) && ! is_dir( $backup ), 'A3 post_install restores .git into the new install' );
ok( false === get_transient( 'sn_theme_git_update_lock' ), 'A4 post_install releases the lock' );

// ── B: a crashed update (post_install never fired) still recovers ──
fresh_theme( $theme_dir );
$GLOBALS['hooks']['upgrader_pre_install']( true, $extra );
ok( false !== get_transient( 'sn_theme_git_update_lock' ), 'B1 pre_install takes the lock' );
delete_transient( 'sn_theme_git_update_lock' ); // the TTL ran out after a PHP timeout
$GLOBALS['hooks']['admin_init']();
ok( is_dir( $git ) && ! is_dir( $backup ), 'B2 with the lock expired, admin_init restores the orphaned backup' );

// ── C: a theme with no .git takes no lock ──
exec( 'rm -rf ' . escapeshellarg( $root ) );
mkdir( $theme_dir, 0777, true );
$GLOBALS['transients'] = array();
$GLOBALS['hooks']['upgrader_pre_install']( true, $extra );
ok( false === get_transient( 'sn_theme_git_update_lock' ), 'C1 no .git to preserve: no lock is held' );

exec( 'rm -rf ' . escapeshellarg( $root ) );
echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
