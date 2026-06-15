<?php
/**
 * Standalone fixture tests for the live colophon build meta (C2, v10.5.0).
 *
 * inc/colophon-meta.php reads the theme's .git directly (no shell-out — exec is
 * disabled on Cloudways; .git is preserved on-server by
 * inc/wp-update-git-preservation.php) plus the theme/plugin versions, and
 * exposes the assembled build line through a [sn_build] shortcode resolved in
 * the /colophon pattern via the render_block bridge.
 *
 * The local checkout's own .git is a worktree FILE, so the git reader is tested
 * against synthesized real-dir fixtures (the production case): symbolic HEAD →
 * loose ref, symbolic HEAD → packed-refs, detached HEAD, and a missing HEAD.
 *
 * @since theme v10.5.0
 */

// SECURITY: CLI-only fixture.
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', '/' ); }
define( 'SN_COLOPHON_META_TEST', true ); // suppress add_shortcode wiring on require

$pass = 0; $fail = 0;
function ok( $cond, $msg ) { global $pass, $fail; if ( $cond ) { $pass++; echo "PASS: $msg\n"; } else { $fail++; echo "FAIL: $msg\n"; } }

// --- Stubs ---
$GLOBALS['__tpl_dir'] = ''; // controllable get_template_directory()
function add_shortcode() { return true; }
function add_filter() { return true; }
function get_template_directory() { return $GLOBALS['__tpl_dir']; }
function wp_get_theme() {
	return new class {
		public function get( $key ) { return 'Version' === $key ? '10.5.0' : ''; }
	};
}
function wp_date( $fmt, $ts = null ) { return date( $fmt, $ts ?? time() ); }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }

require __DIR__ . '/../inc/colophon-meta.php';

// --- Fixture helper: build a temp .git dir from an assoc of relative path => contents ---
$GLOBALS['__fixtures'] = array();
function mkfix( $files ) {
	$root = sys_get_temp_dir() . '/sn-cf-' . uniqid( '', true );
	foreach ( $files as $rel => $contents ) {
		$path = $root . '/' . $rel;
		@mkdir( dirname( $path ), 0777, true );
		file_put_contents( $path, $contents );
	}
	$GLOBALS['__fixtures'][] = $root;
	return $root . '/.git';
}

$SHA_A = 'a1b2c3d4e5f60718293a4b5c6d7e8f9012345678';
$SHA_B = '0fedcba987654321fedcba9876543210abcdef12';

// --- sn_colophon_read_git_dir(): the four production cases ---
ok( function_exists( 'sn_colophon_read_git_dir' ), 'sn_colophon_read_git_dir() is defined' );

// 1) Symbolic HEAD → loose ref.
$g1 = mkfix( array( '.git/HEAD' => "ref: refs/heads/main\n", '.git/refs/heads/main' => $SHA_A . "\n" ) );
$m1 = sn_colophon_read_git_dir( $g1 );
ok( ( $m1['sha'] ?? '' ) === substr( $SHA_A, 0, 7 ), 'loose ref: 7-char short SHA' );
ok( ( $m1['branch'] ?? '' ) === 'main', 'loose ref: branch resolved' );
ok( ( $m1['mtime'] ?? 0 ) > 0, 'loose ref: ref-file mtime captured' );

// 2) Symbolic HEAD → packed-refs (no loose ref file).
$packed = "# pack-refs with: peeled fully-peeled sorted\n" . $SHA_B . " refs/heads/release\n" . $SHA_A . " refs/tags/v1\n";
$g2 = mkfix( array( '.git/HEAD' => "ref: refs/heads/release\n", '.git/packed-refs' => $packed ) );
$m2 = sn_colophon_read_git_dir( $g2 );
ok( ( $m2['sha'] ?? '' ) === substr( $SHA_B, 0, 7 ), 'packed-refs: short SHA for the HEAD ref' );
ok( ( $m2['branch'] ?? '' ) === 'release', 'packed-refs: branch resolved' );

// 3) Detached HEAD (bare SHA — e.g. a tag checkout deploy).
$g3 = mkfix( array( '.git/HEAD' => $SHA_A . "\n" ) );
$m3 = sn_colophon_read_git_dir( $g3 );
ok( ( $m3['sha'] ?? '' ) === substr( $SHA_A, 0, 7 ), 'detached HEAD: short SHA from bare HEAD' );
ok( ( $m3['branch'] ?? 'x' ) === '', 'detached HEAD: empty branch' );

// 4) Missing HEAD → empty.
$g4 = mkfix( array( '.git/config' => "[core]\n" ) );
ok( sn_colophon_read_git_dir( $g4 ) === array(), 'missing HEAD: empty result (degrade)' );

// --- sn_colophon_build_line(): assembly + degradation ---
ok( function_exists( 'sn_colophon_build_line' ), 'sn_colophon_build_line() is defined' );

// Full: theme version + plugin version (SNT_VERSION) + git.
define( 'SNT_VERSION', '6.12.0' );
$GLOBALS['__tpl_dir'] = dirname( $g1 ); // parent of the loose-ref fixture's .git
$line = sn_colophon_build_line();
ok( strpos( $line, 'Theme v10.5.0' ) !== false, 'build line carries the theme version' );
ok( strpos( $line, 'plugin v6.12.0' ) !== false, 'build line carries the plugin version (SNT_VERSION)' );
ok( strpos( $line, substr( $SHA_A, 0, 7 ) ) !== false, 'build line carries the git short SHA' );
ok( strpos( $line, wp_date( 'Y-m-d', $m1['mtime'] ) ) !== false, 'build line carries the deploy date (ref-file mtime — kills the segment-drop mutant)' );

// Degrade: .git absent → still shows the theme version, no fatal.
$GLOBALS['__tpl_dir'] = sys_get_temp_dir() . '/sn-cf-nonexistent-' . uniqid();
$line2 = sn_colophon_build_line();
ok( strpos( $line2, 'Theme v10.5.0' ) !== false, 'degrade: theme version still present with no .git' );
ok( strpos( $line2, substr( $SHA_A, 0, 7 ) ) === false, 'degrade: no stale SHA leaks when .git absent' );

// --- Shortcode wrapper ---
ok( function_exists( 'sn_colophon_build_shortcode' ), 'sn_colophon_build_shortcode() is defined' );
$GLOBALS['__tpl_dir'] = dirname( $g1 );
$out = sn_colophon_build_shortcode();
ok( is_string( $out ) && strpos( $out, 'Theme v10.5.0' ) !== false, 'shortcode returns the escaped build line' );

// --- Worktree .git-FILE case: resolve the WORKTREE branch via the common dir ---
// (linked worktrees keep HEAD in the per-worktree gitdir but refs/heads/* in the
// shared common dir; must NOT report the common dir's own HEAD). Finding 2.
$wtroot = sys_get_temp_dir() . '/sn-cf-wt-' . uniqid( '', true );
$theme  = $wtroot . '/theme';
$gitdir = $wtroot . '/wtgit';
$common = $wtroot . '/common';
@mkdir( $theme, 0777, true );
@mkdir( $gitdir, 0777, true );
@mkdir( $common . '/refs/heads', 0777, true );
file_put_contents( $theme . '/.git', 'gitdir: ' . $gitdir . "\n" );
file_put_contents( $gitdir . '/HEAD', "ref: refs/heads/feature\n" );
file_put_contents( $gitdir . '/commondir', $common . "\n" );
file_put_contents( $common . '/refs/heads/feature', $SHA_B . "\n" ); // the worktree's branch
file_put_contents( $common . '/HEAD', "ref: refs/heads/main\n" );    // the common HEAD (a DECOY)
file_put_contents( $common . '/refs/heads/main', $SHA_A . "\n" );    // wrong answer if we read common HEAD
$GLOBALS['__fixtures'][] = $wtroot;

$GLOBALS['__tpl_dir'] = $theme;
$wt = sn_colophon_git_meta();
ok( ( $wt['sha'] ?? '' ) === substr( $SHA_B, 0, 7 ), 'worktree: SHA resolved for the WORKTREE ref via the common dir (not the decoy common HEAD)' );
ok( ( $wt['branch'] ?? '' ) === 'feature', 'worktree: reports the checked-out branch, not the common HEAD (Finding 2)' );

// --- Integration guards: the [sn_build] token must be wired end-to-end ---
$root      = realpath( __DIR__ . '/..' );
$setup_src = (string) file_get_contents( $root . '/inc/setup.php' );
ok( strpos( $setup_src, '[sn_build]' ) !== false, 'render_block bridge (setup.php) resolves the [sn_build] token (not just [current_year])' );
$colophon_src = (string) file_get_contents( $root . '/patterns/colophon.php' );
ok( strpos( $colophon_src, '[sn_build]' ) !== false, 'colophon pattern emits the [sn_build] token' );

// --- Cleanup ---
foreach ( $GLOBALS['__fixtures'] as $root ) {
	@array_map( 'unlink', glob( $root . '/.git/{,refs/heads/}*', GLOB_BRACE ) );
}

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
