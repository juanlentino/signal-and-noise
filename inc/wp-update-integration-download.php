<?php
/**
 * WP update integration: the package URL and authenticated download.
 *
 * Moved verbatim out of inc/wp-update-integration.php, which loads it.
 *
 * @package SignalNoise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Build the WP-upgrader download package URL for a tag.
 *
 * With SNT_GITHUB_TOKEN defined, use GitHub's authenticated API zipball
 * endpoint — the documented way to download a PRIVATE repo's archive, so the
 * wp-admin → Updates install path keeps working when this repo is private.
 * sn_gh_theme_authenticated_download() (below) injects the Bearer token for the
 * download. Without a token, fall back to the public auto-generated tag archive
 * (unchanged behavior, correct for a public repo). The API zipball unpacks to
 * `owner-repo-<sha>/`, but the upgrader_source_selection rename below is
 * dir-name-agnostic (it renames whatever unpacked dir to the stylesheet slug),
 * so the install lands identically either way.
 *
 * @since 10.11.0
 * @param string $tag e.g. "v10.11.0".
 * @return string Package download URL.
 */
function sn_gh_theme_package_url( $tag ) {
	if ( defined( 'SNT_GITHUB_TOKEN' ) && SNT_GITHUB_TOKEN ) {
		return 'https://api.github.com/repos/' . SN_GH_THEME_OWNER . '/' . SN_GH_THEME_REPO . '/zipball/' . $tag;
	}
	return 'https://github.com/' . SN_GH_THEME_OWNER . '/' . SN_GH_THEME_REPO . '/archive/refs/tags/' . $tag . '.zip';
}

/**
 * http_request_args callback: attach the GitHub token ONLY to api.github.com
 * requests.
 *
 * SECURITY — the API zipball endpoint 302-redirects to a *pre-signed*
 * codeload.github.com URL. The token must never be forwarded to that redirect
 * target (it's already authenticated via the signed URL, and sending a PAT to a
 * different host is a credential leak). Scoping by URL prefix here, and
 * adding/removing this filter around a single download in
 * sn_gh_theme_authenticated_download(), keeps the token bound to the one host
 * that needs it.
 *
 * @since 10.11.0
 * @param array  $args wp_remote_* request args.
 * @param string $url  Request URL.
 * @return array Args, with Authorization added only for api.github.com.
 */
function sn_gh_theme_inject_token_header( $args, $url ) {
	if ( defined( 'SNT_GITHUB_TOKEN' ) && SNT_GITHUB_TOKEN && strpos( (string) $url, 'https://api.github.com/' ) === 0 ) {
		if ( ! isset( $args['headers'] ) || ! is_array( $args['headers'] ) ) {
			$args['headers'] = array();
		}
		$args['headers']['Authorization'] = 'Bearer ' . SNT_GITHUB_TOKEN;
		$args['headers']['Accept']        = 'application/vnd.github+json';
		$args['headers']['User-Agent']    = 'WordPress; ' . home_url();
	}
	return $args;
}

/**
 * upgrader_pre_download: authenticate the package download for private-repo
 * installs.
 *
 * WP core's WP_Upgrader::download_package() fetches the `package` URL with no
 * auth — fine for a public archive, but a private repo's API zipball needs a
 * Bearer token. This intercepts ONLY our zipball package (and only when a token
 * is set) and returns the temp-file path — short-circuiting WP's
 * unauthenticated fetch. Any other package, or no token, returns $reply
 * unchanged so WP proceeds normally (public-repo path is untouched).
 *
 * The redirect is resolved here, in two requests. The API zipball answers 302
 * to a pre-signed codeload.github.com URL, and download_url() follows a
 * redirect with the same request args, so a header filter attached around it
 * would be applied on the second host too. Hop 1 asks api.github.com with
 * redirection => 0 and the token filter attached, reads the Location, and
 * detaches the filter; hop 2 is a plain download_url() of that Location. With
 * no redirect the package is downloaded as before, filter attached.
 *
 * @since 10.11.0
 * @param bool|WP_Error|string $reply      Default short-circuit value (false).
 * @param string               $package    Package URL WP is about to download.
 * @param object               $upgrader   The WP_Upgrader instance (unused).
 * @param array                $hook_extra Upgrade context (unused — see note below).
 * @return bool|WP_Error|string false to proceed normally, else a temp path / WP_Error.
 */
function sn_gh_theme_authenticated_download( $reply, $package, $upgrader = null, $hook_extra = array() ) {
	// The package URL is the discriminator — it's built from our own hardcoded
	// owner/repo constants, so it uniquely identifies OUR zipball and matches
	// nothing else passing through this filter. We deliberately do NOT gate on
	// $hook_extra['theme'] (as upgrader_source_selection does): at the
	// pre-download stage that key isn't reliably populated across all install
	// flows, and the self-constructed URL prefix is the stronger guard.
	$our_prefix = 'https://api.github.com/repos/' . SN_GH_THEME_OWNER . '/' . SN_GH_THEME_REPO . '/zipball/';
	if ( ! is_string( $package ) || strpos( $package, $our_prefix ) !== 0 ) {
		return $reply;
	}
	if ( ! defined( 'SNT_GITHUB_TOKEN' ) || ! SNT_GITHUB_TOKEN ) {
		return $reply;
	}
	if ( ! function_exists( 'download_url' ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
	}
	add_filter( 'http_request_args', 'sn_gh_theme_inject_token_header', 10, 2 );
	try {
		$hop = wp_safe_remote_get(
			$package,
			array(
				'timeout'     => 30,
				'redirection' => 0,
				'headers'     => array(
					'Accept'     => 'application/vnd.github+json',
					'User-Agent' => 'WordPress; ' . home_url(),
				),
			)
		);
	} finally {
		// Always detach the token filter, even if the request throws — never
		// leave it attached for subsequent requests in this process.
		remove_filter( 'http_request_args', 'sn_gh_theme_inject_token_header', 10 );
	}
	$location = is_wp_error( $hop ) ? '' : wp_remote_retrieve_header( $hop, 'location' );
	$location = is_array( $location ) ? (string) reset( $location ) : (string) $location;
	if ( '' !== $location ) {
		return download_url( $location );
	}
	// No redirect: download the package itself, header applied to this one host.
	add_filter( 'http_request_args', 'sn_gh_theme_inject_token_header', 10, 2 );
	try {
		$file = download_url( $package );
	} finally {
		remove_filter( 'http_request_args', 'sn_gh_theme_inject_token_header', 10 );
	}
	return $file;
}
add_filter( 'upgrader_pre_download', 'sn_gh_theme_authenticated_download', 10, 4 );
