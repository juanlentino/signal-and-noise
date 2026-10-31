<?php
/**
 * WP update integration: the latest-tag fetch and its plugin filter.
 *
 * Moved verbatim out of inc/wp-update-integration.php, which loads it.
 *
 * @package SignalNoise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fetch the highest semver-formatted tag from GitHub. Returns the tag
 * string (e.g. "v8.5.0") on success, null on error / no matching tags.
 * Cached for SN_GH_THEME_CACHE_TTL; empty sentinel cached 1h on failure.
 *
 * @param bool $force_refresh When true, bypass the cache and re-fetch.
 *                            Used when WP's "Check Again" button is
 *                            clicked (`?force-check=1`). Added v8.5.3.
 *                            Memoized per request (#332): one request can
 *                            ask more than once (the update offer, the
 *                            get-latest-theme-tag ability, the plugin's
 *                            seam); one click is one fetch.
 */
function sn_gh_latest_theme_tag( $force_refresh = false ) {
	if ( ! $force_refresh ) {
		$cached = get_site_transient( SN_GH_THEME_CACHE_KEY );
		if ( $cached !== false ) {
			return $cached === '' ? null : $cached;
		}
	} elseif ( array_key_exists( 'sn_gh_theme_forced_tag_memo', $GLOBALS ) ) {
		return $GLOBALS['sn_gh_theme_forced_tag_memo'];
	}
	// A global, not a static, so the standalone tests can reset it between
	// scenarios (the sn_css_combined_memo convention).
	$GLOBALS['sn_gh_theme_forced_tag_memo'] = sn_gh_latest_theme_tag_fetch();
	return $GLOBALS['sn_gh_theme_forced_tag_memo'];
}

/**
 * The uncached tag fetch behind sn_gh_latest_theme_tag().
 *
 * @since 13.1.2
 * @return string|null Highest vX.Y.Z tag, or null on failure / no tags.
 */
function sn_gh_latest_theme_tag_fetch() {

	$url     = 'https://api.github.com/repos/' . SN_GH_THEME_OWNER . '/' . SN_GH_THEME_REPO . '/tags?per_page=100';
	$headers = array(
		'Accept'     => 'application/vnd.github+json',
		'User-Agent' => 'WordPress; ' . home_url(),
	);
	// v9.5.2: authenticate the tag-fetch when SNT_GITHUB_TOKEN is defined in
	// wp-config.php — raises the GitHub limit from 60/h (unauthenticated, shared
	// per-server-IP) to 5000/h. Without this, a busy/shared IP can exhaust the
	// 60/h pool, the fetch 403s, sn_gh_latest_theme_tag() returns null, and the
	// Updates page silently shows "no update available" even when one exists.
	// SNT_GITHUB_TOKEN is the same wp-config constant the plugin uses (both run
	// in one WP process). Conditional → unauthenticated fallback unchanged when absent.
	if ( defined( 'SNT_GITHUB_TOKEN' ) && SNT_GITHUB_TOKEN ) {
		$headers['Authorization'] = 'Bearer ' . SNT_GITHUB_TOKEN;
	}
	$args = array(
		'timeout' => 8,
		'headers' => $headers,
		// v10.16.3 (audit LOW-1): pin to a single hop. On a 3xx, WP's HTTP layer
		// re-issues the request with the SAME $args — including the Bearer header
		// above — to whatever host the redirect names. api.github.com/tags returns
		// 200 (no redirect), so this is behavior-preserving; it just guarantees the
		// SNT_GITHUB_TOKEN can never be forwarded off-host. Mirrors the host-scoped
		// download path (sn_gh_theme_inject_token_header) + the plugin's outbound peers.
		'redirection' => 0,
	);

	$response = wp_remote_get( $url, $args );
	$code     = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );

	// v10.43.0: ONE retry, transient failures only. During the 2026-07-16 GitHub
	// incident (~35% of REST requests failing, independently) a single retry
	// recovers ~65% of the polls that would otherwise blind the card. Durable
	// failures (401/404) are never retried — the second answer is the first.
	if ( 200 !== $code && sn_gh_theme_failure_is_transient( $code ) ) {
		$response = wp_remote_get( $url, $args );
		$code     = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );
	}

	if ( 200 !== $code ) {
		return sn_gh_theme_record_fetch_failure( sn_gh_theme_fetch_failure_reason( $response ), $code );
	}

	$tags = json_decode( wp_remote_retrieve_body( $response ), true );
	if ( ! is_array( $tags ) ) {
		// A 200 whose body isn't a tag list means we reached SOMETHING that
		// wasn't GitHub's API — an intermediary, an incident error page. Far
		// likelier a blip than a permanent contract change, so classify
		// TRANSIENT (0 = "no usable answer"). The reason still reports the
		// literal 200 we saw; the code only drives retry policy.
		return sn_gh_theme_record_fetch_failure( sn_gh_theme_fetch_failure_reason( $response ), 0 );
	}

	$highest = '';
	foreach ( $tags as $tag ) {
		$name = isset( $tag['name'] ) ? (string) $tag['name'] : '';
		if ( ! preg_match( '/^v\d+\.\d+\.\d+$/', $name ) ) {
			continue;
		}
		if ( $highest === '' || version_compare( ltrim( $name, 'v' ), ltrim( $highest, 'v' ), '>' ) ) {
			$highest = $name;
		}
	}

	if ( $highest === '' ) {
		// DURABLE, and distinct from "no update available": we reached GitHub,
		// it answered correctly, and the repo simply has nothing tagged vX.Y.Z.
		// Re-asking in five minutes gets the same answer.
		return sn_gh_theme_record_fetch_failure(
			__( 'GitHub returned no tags matching vX.Y.Z — nothing to compare against', 'signal-and-noise' ),
			200
		);
	}

	// Success CLEARS the reason. Without this the fix becomes the next bug: a
	// stale caption would sit on the card after GitHub recovered, and the owner
	// would go hunting for a problem that had already resolved itself.
	delete_site_transient( SN_GH_THEME_ERROR_KEY );
	set_site_transient( SN_GH_THEME_CACHE_KEY, $highest, SN_GH_THEME_CACHE_TTL );
	return $highest;
}

/**
 * v4.1.1 (X-01 in plugin's audit): expose sn_gh_latest_theme_tag() as a
 * filter so the companion plugin (signal-and-noise-tools) can fetch the
 * latest theme tag without calling a theme function directly. Per
 * WORDPRESS-REFERENCE.md §10, plugin → theme calls go through filter/action
 * contracts — never function_exists guards on theme functions. The plugin
 * dispatches apply_filters('sn_gh_latest_theme_tag_result', null) and
 * this filter substitutes the GitHub tag. Returns null when the call fails
 * (same shape as the function itself), so the plugin's deploy-status card
 * degrades gracefully if the theme is absent/inactive.
 */
add_filter( 'sn_gh_latest_theme_tag_result', 'sn_gh_latest_theme_tag' );
