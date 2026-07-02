<?php
/**
 * Standalone fixture tests for inc/asset-combine.php (v10.21.6).
 *
 * Covers the combined-stylesheet builder:
 *   - sn_css_minify(): strips comments, collapses whitespace, trims around
 *     braces/semicolons, drops trailing semicolons — and PRESERVES calc()
 *     operator spacing (removing spaces around calc's +/- breaks the value).
 *   - sn_css_combine_signature(): non-null when all sources exist, null when
 *     any source is missing, changes when a source's mtime changes.
 *   - sn_css_ensure_combined(): builds uploads/sn-css/sn-styles-<hash>.css
 *     with every source minified IN CASCADE ORDER; returns url + ver.
 *   - Relative url() guard: a source with a relative url() aborts the combine
 *     (null → callers fall back to separate files) — moving CSS to uploads/
 *     would break relative references, so fail open until a rewriter exists.
 *   - Idempotency: an existing target file is never rewritten.
 *   - Cleanup: stale sn-styles-*.css siblings are removed after a build.
 *
 * Run: php tests/asset-combine.php
 *
 * @since theme v10.21.6
 */

// SECURITY: Prevent web access. CLI / WP-CLI only.
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) {
	http_response_code( 404 );
	exit;
}

define( 'ABSPATH', '/' );

// ─── WP stubs ─────────────────────────────────────────────────────────
$GLOBALS['__test_root'] = sys_get_temp_dir() . '/sn-asset-combine-' . getmypid();
if ( ! function_exists( 'get_theme_file_path' ) ) {
	function get_theme_file_path( $rel ) { return $GLOBALS['__test_root'] . '/theme/' . ltrim( $rel, '/' ); }
}
if ( ! function_exists( 'wp_upload_dir' ) ) {
	function wp_upload_dir() {
		return array(
			'basedir' => $GLOBALS['__test_root'] . '/uploads',
			'baseurl' => 'https://juanlentino.com/wp-content/uploads',
			'error'   => false,
		);
	}
}
if ( ! function_exists( 'wp_mkdir_p' ) ) {
	function wp_mkdir_p( $dir ) { return is_dir( $dir ) || mkdir( $dir, 0777, true ); }
}

require_once __DIR__ . '/../inc/asset-combine.php';

// ─── Fixture setup ────────────────────────────────────────────────────
$css_dir = $GLOBALS['__test_root'] . '/theme/assets/css';
mkdir( $css_dir, 0777, true );
$fixtures = array(
	'base.css'            => "/* base comment */\nbody {\n\tcolor : red ;\n}\n",
	'layout.css'          => ".grid {\n  width: calc(100% - 20px);\n}\n",
	'components.css'      => "/* big\n multiline\n comment */\n.card { margin : 0 ; }\n.icon { mask: url('data:image/svg+xml;utf8,<svg/>'); }\n.hero { background: url(\"https://juanlentino.com/x.png\"); }\n.badge { background: url('/wp-content/uploads/y.png'); }\n",
	'responsive.css'      => "@media (max-width: 600px) {\n  .card { margin: 4px; }\n}\n",
	'command-palette.css' => ".sn-cmdk {\n  display: none;\n}\n",
);
foreach ( $fixtures as $name => $css ) {
	file_put_contents( $css_dir . '/' . $name, $css );
}

$pass = 0;
$fail = 0;
function ac_eq( $e, $a, $msg ) {
	global $pass, $fail;
	if ( $e === $a ) { $pass++; echo "  PASS: $msg\n"; }
	else { $fail++; echo "  FAIL: $msg\n    Expected: " . var_export( $e, true ) . "\n    Actual:   " . var_export( $a, true ) . "\n"; }
}
function ac_true( $c, $msg ) {
	global $pass, $fail;
	if ( $c ) { $pass++; echo "  PASS: $msg\n"; } else { $fail++; echo "  FAIL: $msg\n"; }
}
function ac_reset_memo() { unset( $GLOBALS['sn_css_combined_memo'] ); }

// ─── Test 1: minifier ─────────────────────────────────────────────────
echo "\nTest 1: sn_css_minify basics\n";
$min = sn_css_minify( "/* gone */\n.a {\n\tcolor : red ;\n}\n\n.b{margin:calc(100% - 20px);}" );
ac_true( false === strpos( $min, '/*' ), 'comments stripped' );
ac_true( false === strpos( $min, '  ' ), 'whitespace runs collapsed' );
ac_true( false === strpos( $min, ';}' ), 'trailing semicolons dropped' );
ac_true( false !== strpos( $min, 'calc(100% - 20px)' ), 'calc() operator spacing preserved' );
ac_true( false !== strpos( $min, '.a{' ), 'space trimmed around braces' );

// ─── Test 2: signature presence + mtime sensitivity ───────────────────
echo "\nTest 2: signature\n";
$sig1 = sn_css_combine_signature();
ac_true( is_string( $sig1 ) && 12 === strlen( $sig1 ), 'signature is a 12-char hash when all sources exist' );
touch( $css_dir . '/layout.css', time() + 60 );
clearstatcache();
$sig2 = sn_css_combine_signature();
ac_true( is_string( $sig2 ) && $sig2 !== $sig1, 'signature changes when a source mtime changes' );

// ─── Test 3: build + content order + envelope ─────────────────────────
echo "\nTest 3: ensure_combined builds the file\n";
ac_reset_memo();
$info = sn_css_ensure_combined();
ac_true( is_array( $info ) && isset( $info['url'], $info['ver'], $info['file'] ), 'returns file/url/ver envelope' );
ac_eq( $sig2, is_array( $info ) ? $info['ver'] : null, 'ver equals the signature' );
$built = is_array( $info ) ? (string) file_get_contents( $info['file'] ) : '';
$order_ok = true;
$last     = -1;
foreach ( array( 'body{', '.grid{', '.card{', '@media (max-width: 600px)', '.sn-cmdk{' ) as $needle ) {
	$at = strpos( $built, $needle );
	if ( false === $at || $at < $last ) { $order_ok = false; break; }
	$last = $at;
}
ac_true( $order_ok, 'all five sources present in cascade order (base → layout → components → responsive → palette)' );
ac_true( false === strpos( $built, 'base comment' ), 'sources are minified in the combined file' );

// ─── Test 4: idempotency (existing target never rewritten) ────────────
echo "\nTest 4: existing target is not rebuilt\n";
ac_reset_memo();
file_put_contents( $info['file'], '/* sentinel */' );
$again = sn_css_ensure_combined();
ac_eq( '/* sentinel */', (string) file_get_contents( $info['file'] ), 'target content untouched when it already exists' );
ac_true( is_array( $again ) && $again['ver'] === $info['ver'], 'envelope still returned for the existing target' );

// ─── Test 5: stale sibling cleanup on fresh build ─────────────────────
echo "\nTest 5: stale sn-styles-*.css siblings removed\n";
ac_reset_memo();
$stale = dirname( $info['file'] ) . '/sn-styles-deadbeef0000.css';
file_put_contents( $stale, 'old' );
unlink( $info['file'] ); // force a rebuild
sn_css_ensure_combined();
ac_true( ! file_exists( $stale ), 'stale sibling deleted after rebuild' );

// ─── Test 6: relative url() aborts the combine (fail open) ────────────
echo "\nTest 6: relative url() guard\n";
ac_reset_memo();
file_put_contents( $css_dir . '/base.css', ".logo { background: url('../images/x.png'); }" );
// Force a new signature: the suite runs within one second, so the rewrite
// alone may not change the mtime, and an unchanged hash short-circuits on
// file_exists before the build-time guard can run.
touch( $css_dir . '/base.css', time() + 120 );
clearstatcache();
$guarded = sn_css_ensure_combined();
ac_eq( null, $guarded, 'combine aborts on a relative url() reference' );
file_put_contents( $css_dir . '/base.css', $fixtures['base.css'] );
clearstatcache();

// ─── Test 6b: QUOTED safe url()s must NOT trip the guard ──────────────
// v10.21.7 regression: the v10.21.6 guard regex let its optional-quote
// backtrack to empty, so the lookahead tested the QUOTE character and the
// guard false-positived on every quoted url() — live components.css has
// quoted data: URIs, so production silently fell back to per-file
// enqueues and the combined file was never built.
echo "\nTest 6b: quoted data:/https:/absolute url()s combine fine\n";
ac_reset_memo();
touch( $css_dir . '/base.css', time() + 240 );
clearstatcache();
$info6b = sn_css_ensure_combined();
ac_true( is_array( $info6b ), 'combine proceeds with quoted data:, https:, and absolute url()s present' );
$built6b = is_array( $info6b ) ? (string) file_get_contents( $info6b['file'] ) : '';
ac_true( false !== strpos( $built6b, "url('data:image/svg+xml;utf8,<svg/>')" ), 'quoted data: URI survives into the combined file' );

// ─── Test 7: missing source → null signature (fail open) ──────────────
echo "\nTest 7: missing source\n";
ac_reset_memo();
unlink( $css_dir . '/responsive.css' );
clearstatcache();
ac_eq( null, sn_css_combine_signature(), 'signature null when a source is missing' );
ac_eq( null, sn_css_ensure_combined(), 'ensure_combined null when a source is missing' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
