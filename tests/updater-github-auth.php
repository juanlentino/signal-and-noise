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
// v10.43.0: the transient/durable failure TTLs are expressed in minutes.
define( 'MINUTE_IN_SECONDS', 60 );

// ── Capture buffer: every wp_remote_get records its $args here. ──
$GLOBALS['__captured_requests'] = array();
$GLOBALS['__transients']        = array();

// Stateful hook stubs: the download step attaches the token filter around ONE
// request, and the pins below read which requests saw it attached.
$GLOBALS['__filters'] = array();
if ( ! function_exists( 'add_filter' ) )  { function add_filter( $tag, $cb = null ) { $GLOBALS['__filters'][ $tag ][] = $cb; } }
if ( ! function_exists( 'remove_filter' ) ) {
    function remove_filter( $tag, $cb = null ) {
        $GLOBALS['__filters'][ $tag ] = array_values( array_filter( $GLOBALS['__filters'][ $tag ] ?? array(), function ( $c ) use ( $cb ) { return $c !== $cb; } ) );
        return true;
    }
}
function token_filter_attached() { return in_array( 'sn_gh_theme_inject_token_header', $GLOBALS['__filters']['http_request_args'] ?? array(), true ); }
if ( ! function_exists( 'add_action' ) )  { function add_action() {} }
if ( ! function_exists( 'home_url' ) )    { function home_url( $p = '' ) { return 'https://example.test' . $p; } }
if ( ! function_exists( 'get_site_transient' ) ) {
    function get_site_transient( $k ) { return $GLOBALS['__transients'][ $k ] ?? false; }
}
if ( ! function_exists( 'set_site_transient' ) ) {
    function set_site_transient( $k, $v, $ttl = 0 ) { $GLOBALS['__transients'][ $k ] = $v; return true; }
}// v10.43.0: a successful fetch now CLEARS the recorded failure reason.
if ( ! function_exists( 'delete_site_transient' ) ) {
    function delete_site_transient( $k ) { unset( $GLOBALS['__transients'][ $k ] ); return true; }
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
if ( ! function_exists( 'download_url' ) ) {
    function download_url( $url ) {
        $GLOBALS['__download_url']      = $url;
        $GLOBALS['__download_filtered'] = token_filter_attached();
        return '/tmp/fake.zip';
    }
}
// The first hop: the API zipball answers 302 to a pre-signed codeload URL.
$GLOBALS['__zipball_location'] = 'https://codeload.github.com/juanlentino/signal-and-noise/legacy.zip/refs/tags/v9.9.9?token=presigned';
if ( ! function_exists( 'wp_safe_remote_get' ) ) {
    function wp_safe_remote_get( $url, $args = array() ) {
        $GLOBALS['__hop1'] = array( 'url' => $url, 'args' => $args, 'filtered' => token_filter_attached() );
        $loc = $GLOBALS['__zipball_location'];
        return '' === $loc
            ? array( 'response' => array( 'code' => 200 ), 'headers' => array(), 'body' => 'zipbytes' )
            : array( 'response' => array( 'code' => 302 ), 'headers' => array( 'location' => $loc ), 'body' => '' );
    }
}
if ( ! function_exists( 'wp_remote_retrieve_header' ) ) {
    function wp_remote_retrieve_header( $r, $h ) { return $r['headers'][ strtolower( $h ) ] ?? ''; }
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
    unset( $GLOBALS['sn_gh_theme_forced_tag_memo'] ); // #332: the forced fetch is memoized per request.
    sn_gh_latest_theme_tag( true ); // force_refresh = bypass cache
    $reqs = $GLOBALS['__captured_requests'];
    return $reqs ? ( $reqs[ count( $reqs ) - 1 ]['args']['headers'] ?? array() ) : array();
}

/** Fetch the most recent captured request's full $args, after a forced (uncached) call. */
function last_args() {
    $GLOBALS['__captured_requests'] = array();
    unset( $GLOBALS['sn_gh_theme_forced_tag_memo'] );
    sn_gh_latest_theme_tag( true ); // force_refresh = bypass cache
    $reqs = $GLOBALS['__captured_requests'];
    return $reqs ? ( $reqs[ count( $reqs ) - 1 ]['args'] ?? array() ) : array();
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

// ── v10.16.3 hardening (audit LOW-1): the /tags poll must pin redirection => 0 so the
//    Bearer token can NEVER be forwarded to a 3xx redirect target. WP's HTTP layer otherwise
//    re-sends the same $args (incl. Authorization) on a redirect. Mirrors the plugin's
//    outbound peers and this file's own host-scoped download path (sn_gh_theme_inject_token_header). ──
$a = last_args();
ok( array_key_exists( 'redirection', $a ) && (int) $a['redirection'] === 0,
    'tag-fetch pins redirection => 0 (Bearer token cannot leak via a redirect)' );

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

// ── The download step resolves the release redirect ITSELF. download_url()
//    follows redirects with the same request args, so a header filter attached
//    around it is re-applied on the 302 hop to codeload. Hop 1 (api.github.com,
//    redirection => 0) runs with the filter attached; hop 2 (the pre-signed
//    Location) runs through download_url() with the filter detached. ──
ok( isset( $GLOBALS['__hop1'] ) && 0 === strpos( $GLOBALS['__hop1']['url'], 'https://api.github.com/' ),
    'download: hop 1 is a request to the api.github.com zipball' );
ok( isset( $GLOBALS['__hop1']['args']['redirection'] ) && 0 === (int) $GLOBALS['__hop1']['args']['redirection'],
    'download: hop 1 pins redirection => 0 (the redirect is read, not followed)' );
ok( ! empty( $GLOBALS['__hop1']['filtered'] ),
    'download: the token filter is attached for hop 1' );
ok( $GLOBALS['__download_url'] === $GLOBALS['__zipball_location'],
    'download: hop 2 fetches the Location the zipball answered with' );
ok( empty( $GLOBALS['__download_filtered'] ),
    'download: the token filter is DETACHED for hop 2 (the pre-signed codeload URL)' );
ok( ! token_filter_attached(), 'download: the filter is not left attached afterwards' );

// No redirect → the current path: download_url() on the package itself, filter attached.
$GLOBALS['__zipball_location'] = '';
unset( $GLOBALS['__download_url'], $GLOBALS['__download_filtered'] );
ok( sn_gh_theme_authenticated_download( false, 'https://api.github.com/repos/juanlentino/signal-and-noise/zipball/v9.9.9' ) === '/tmp/fake.zip',
    'download: no redirect → still returns the temp path' );
ok( $GLOBALS['__download_url'] === 'https://api.github.com/repos/juanlentino/signal-and-noise/zipball/v9.9.9' && ! empty( $GLOBALS['__download_filtered'] ),
    'download: no redirect → download_url() on the package with the filter attached (the fallback)' );
ok( ! token_filter_attached(), 'download: the filter is not left attached after the fallback either' );

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

// #332: WP_FORCE_UPDATE_CHECK is not a core constant — "Check Again" is
// update-core.php?force-check=1, caught by the query-string branch above. The
// dead branch is gone from the source; defining the constant changes nothing.
define( 'WP_FORCE_UPDATE_CHECK', true );
$GLOBALS['__caps'] = array();
ok( sn_gh_theme_force_refresh_requested() === false, 'force-refresh: WP_FORCE_UPDATE_CHECK (not a core constant) does not force (#332)' );
ok( strpos( $src, 'WP_FORCE_UPDATE_CHECK' ) === false, 'the dead WP_FORCE_UPDATE_CHECK branch and its comments are gone from the source (#332)' );

// #332: wp_update_themes() writes the transient twice per run, so the
// pre_set_site_transient_update_themes filter runs twice; a forced check must
// fetch ONCE per request, not once per write.
unset( $GLOBALS['sn_gh_theme_forced_tag_memo'] );
$GLOBALS['__captured_requests'] = array();
$first  = sn_gh_latest_theme_tag( true );
$second = sn_gh_latest_theme_tag( true );
ok( 'v9.9.9' === $first && $first === $second, 'two forced calls in one request agree' );
ok( 1 === count( $GLOBALS['__captured_requests'] ), 'two forced calls in one request make ONE tag fetch (#332)' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
