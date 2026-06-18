<?php
/**
 * Fixture test: the self-updater's GitHub tag-fetch must send an
 * Authorization header when SNT_GITHUB_TOKEN is defined (5000/h authenticated)
 * and must omit it when the constant is absent (60/h unauthenticated fallback).
 *
 * Context: the updater (sn_gh_latest_theme_tag) historically sent only
 * Accept + User-Agent, so every WP update-check spent from the shared-IP 60/h
 * unauthenticated GitHub pool. When that pool exhausts, the tag-fetch gets a 403
 * → returns null → the Updates page shows "no update available" even when one
 * exists. github-actions-api.php (the deploy poller) already authenticates with
 * the same wp-config SNT_GITHUB_TOKEN constant; this brings the updater to parity.
 *
 * Run: php tests/updater-github-auth.php
 *
 * @since plugin v9.5.2
 */

if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) {
    http_response_code( 404 );
    exit;
}

define( 'ABSPATH', '/' );
define( 'HOUR_IN_SECONDS', 3600 );

// ── Capture buffer: every wp_remote_get records its $args here. ──
$GLOBALS['__captured_requests'] = array();
$GLOBALS['__transients']        = array();

if ( ! function_exists( 'add_filter' ) )  { function add_filter() {} }
if ( ! function_exists( 'add_action' ) )  { function add_action() {} }
if ( ! function_exists( 'home_url' ) )    { function home_url( $p = '' ) { return 'https://example.test' . $p; } }
if ( ! function_exists( 'get_site_transient' ) ) {
    function get_site_transient( $k ) { return $GLOBALS['__transients'][ $k ] ?? false; }
}
if ( ! function_exists( 'set_site_transient' ) ) {
    function set_site_transient( $k, $v, $ttl = 0 ) { $GLOBALS['__transients'][ $k ] = $v; return true; }
}
if ( ! function_exists( 'is_wp_error' ) ) { function is_wp_error( $t ) { return $t instanceof WP_Error; } }
if ( ! function_exists( 'wp_remote_get' ) ) {
    function wp_remote_get( $url, $args = array() ) {
        $GLOBALS['__captured_requests'][] = array( 'url' => $url, 'args' => $args );
        // Return one matching tag so the function completes its happy path.
        return array( 'response' => array( 'code' => 200 ), 'body' => json_encode( array( array( 'name' => 'v9.9.9' ) ) ) );
    }
}
if ( ! function_exists( 'wp_remote_retrieve_response_code' ) ) {
    function wp_remote_retrieve_response_code( $r ) { return $r['response']['code'] ?? 0; }
}
if ( ! function_exists( 'wp_remote_retrieve_body' ) ) {
    function wp_remote_retrieve_body( $r ) { return $r['body'] ?? ''; }
}
if ( ! function_exists( 'remove_filter' ) ) { function remove_filter() {} }
if ( ! function_exists( 'download_url' ) ) {
    function download_url( $url ) { $GLOBALS['__download_url'] = $url; return '/tmp/fake.zip'; }
}
$GLOBALS['__caps'] = array(); // cap => bool, controllable
if ( ! function_exists( 'current_user_can' ) ) {
    function current_user_can( $cap ) { return ! empty( $GLOBALS['__caps'][ $cap ] ); }
}

require __DIR__ . '/../inc/wp-update-integration.php';

$pass = 0; $fail = 0;
function ok( $cond, $label ) {
    global $pass, $fail;
    if ( $cond ) { $pass++; echo "PASS: $label\n"; }
    else { $fail++; echo "FAIL: $label\n"; }
}

/** Fetch the most recent captured request's headers, after a forced (uncached) call. */
function last_headers() {
    $GLOBALS['__captured_requests'] = array();
    sn_gh_latest_theme_tag( true ); // force_refresh = bypass cache
    $reqs = $GLOBALS['__captured_requests'];
    return $reqs ? ( $reqs[ count( $reqs ) - 1 ]['args']['headers'] ?? array() ) : array();
}

// ── Package URL: NO token → public auto-generated archive (unchanged) ──
ok( sn_gh_theme_package_url( 'v9.9.9' ) === 'https://github.com/juanlentino/signal-and-noise/archive/refs/tags/v9.9.9.zip',
    'no token → public archive package URL' );

// ── Case 1: token DEFINED → Authorization: Bearer present ──
define( 'SNT_GITHUB_TOKEN', 'ghp_faketoken123' );
$h = last_headers();
ok( isset( $h['Authorization'] ),                          'token defined → Authorization header present' );
ok( ( $h['Authorization'] ?? '' ) === 'Bearer ghp_faketoken123', 'Authorization is "Bearer <token>"' );
ok( ( $h['Accept'] ?? '' ) === 'application/vnd.github+json',     'Accept header preserved' );
ok( isset( $h['User-Agent'] ),                            'User-Agent header preserved' );

// ── Package URL: token → authenticated API zipball (private-repo capable) ──
ok( sn_gh_theme_package_url( 'v9.9.9' ) === 'https://api.github.com/repos/juanlentino/signal-and-noise/zipball/v9.9.9',
    'token defined → authenticated API zipball package URL' );

// ── Download auth: Bearer scoped to api.github.com, NEVER forwarded to codeload ──
$api_args = sn_gh_theme_inject_token_header( array( 'headers' => array() ), 'https://api.github.com/repos/juanlentino/signal-and-noise/zipball/v9.9.9' );
ok( ( $api_args['headers']['Authorization'] ?? '' ) === 'Bearer ghp_faketoken123', 'download auth: Bearer added for api.github.com request' );
$cl_args = sn_gh_theme_inject_token_header( array( 'headers' => array() ), 'https://codeload.github.com/juanlentino/signal-and-noise/legacy.zip/refs/tags/v9.9.9' );
ok( ! isset( $cl_args['headers']['Authorization'] ), 'download auth: Bearer NOT forwarded to codeload redirect target (no token leak)' );

// ── upgrader_pre_download: intercept ONLY our zipball ──
ok( sn_gh_theme_authenticated_download( false, 'https://example.com/other.zip' ) === false,
    'pre_download: foreign package not intercepted (returns $reply)' );
ok( sn_gh_theme_authenticated_download( false, 'https://api.github.com/repos/juanlentino/signal-and-noise/zipball/v9.9.9' ) === '/tmp/fake.zip',
    'pre_download: our zipball → authenticated download returns temp path' );

// ── Case 2 (documented): when the constant is UNDEFINED, no Authorization. ──
// Can't undefine a constant mid-process, so this is asserted structurally:
// the source guards the header with `if ( defined( 'SNT_GITHUB_TOKEN' ) && ... )`,
// proven by Case 1 + the source-grep assertion below.
$src = file_get_contents( __DIR__ . '/../inc/wp-update-integration.php' );
ok( strpos( $src, "defined( 'SNT_GITHUB_TOKEN' )" ) !== false, 'token application is guarded by defined() (graceful unauth fallback)' );
ok( preg_match( '/Authorization.*Bearer.*SNT_GITHUB_TOKEN/s', $src ) === 1, 'Authorization built from SNT_GITHUB_TOKEN' );

// ── force-check must be capability-gated (no unauth GitHub API spend / CSRF cache-bust) ──
ok( function_exists( 'sn_gh_theme_force_refresh_requested' ), 'force-refresh decision helper is defined' );

unset( $_GET['force-check'] );
$GLOBALS['__caps'] = array();
ok( sn_gh_theme_force_refresh_requested() === false, 'force-refresh: false when nothing is set' );

$_GET['force-check'] = '1';
$GLOBALS['__caps'] = array(); // logged in, but lacks update_themes
ok( sn_gh_theme_force_refresh_requested() === false, 'force-refresh: ?force-check IGNORED without update_themes cap (no API spend / CSRF)' );

$_GET['force-check'] = '1';
$GLOBALS['__caps'] = array( 'update_themes' => true );
ok( sn_gh_theme_force_refresh_requested() === true, 'force-refresh: ?force-check honored WITH update_themes cap' );
unset( $_GET['force-check'] );

// WP's own "Check Again" flow is already capability-gated → the constant forces regardless.
define( 'WP_FORCE_UPDATE_CHECK', true );
$GLOBALS['__caps'] = array();
ok( sn_gh_theme_force_refresh_requested() === true, 'force-refresh: WP_FORCE_UPDATE_CHECK constant forces regardless of $_GET/caps' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
