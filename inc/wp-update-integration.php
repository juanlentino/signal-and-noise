<?php
/**
 * Signal & Noise — WP-native update integration.
 *
 * Hooks into WordPress's standard update system so this theme appears
 * in wp-admin/update-core.php and Appearance → Themes alongside other
 * themes. Polls the GitHub Tags API every 12h (cached in a site
 * transient) to compare local version against the latest tagged release.
 *
 * Both install paths now coexist (since v8.5.2):
 *   - **Canonical:** `gh workflow run deploy.yml --ref vX.Y.Z --repo juanlentino/signal-and-noise`
 *     → Cloudways /api/v1/git/pull → fast, well-understood, preserves .git
 *   - **WP UI:** wp-admin → Updates → Update Now
 *     → WP downloads GitHub tag ZIP → upgrader_source_selection rename
 *     (below) drops the version suffix → install + .git preserved by
 *     inc/wp-update-git-preservation.php pre/post-install filters
 *
 * The .git preservation work was the missing piece. Without it, clicking
 * "Update Now" destroyed the .git directory (via WP_Upgrader's recursive
 * clear_destination) and broke the next gh workflow_dispatch deploy. The
 * paths shared a destination dir but no coordination protocol. v8.5.0
 * added a WP_Error gate to block WP UI installs entirely; v8.5.1 removed
 * the gate without solving the underlying problem; v8.5.2 added the
 * pre/post-install filter pair that backs up + restores .git atomically.
 *
 * What this file provides:
 *   - Version visibility in wp-admin (badge + "Up to date" / "Update
 *     Available" on update-core.php and Appearance → Themes).
 *   - Health signal — local Version != GitHub latest means no update
 *     has landed yet via either path.
 *   - GitHub tag → install via upgrader_source_selection rename so WP
 *     installs to the correct stylesheet slug.
 *
 * Added in v8.5.0 (2026-05-16). Reworked in v8.5.1 (2026-05-16) to
 * remove the WP_Error gate. v8.5.2 (2026-05-16) extracted .git
 * preservation into inc/wp-update-git-preservation.php — the missing
 * piece that makes the WP UI install path actually safe.
 *
 * @package SignalNoise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const SN_GH_THEME_OWNER         = 'juanlentino';
const SN_GH_THEME_REPO          = 'signal-and-noise';
const SN_GH_THEME_CACHE_KEY     = 'sn_gh_latest_theme';
const SN_GH_THEME_CACHE_TTL     = HOUR_IN_SECONDS; // v8.5.3: 12h → 1h (mirrors plugin v1.11.1)
const SN_GH_THEME_STYLESHEET    = 'signal-and-noise';
const SN_GH_THEME_LAST_SEEN_OPT = 'sn_last_seen_theme_version';
/**
 * Why the last tag fetch failed, in prose, for the Dashboard card. Lives beside
 * the negative cache and shares its lifetime.
 *
 * @since theme v10.43.0
 */
const SN_GH_THEME_ERROR_KEY     = 'sn_gh_latest_theme_error';
/**
 * Failure-cache TTLs, split by whether re-asking could get a different answer.
 * Transient mirrors the companion plugin's SN_GH_FAIL_TTL_TRANSIENT and
 * github-actions-api.php's SNT_GH_RUNS_FAIL_TTL — the value that rode out the
 * 2026-07-16 GitHub incident without ever going dark.
 *
 * @since theme v10.43.0
 */
const SN_GH_THEME_FAIL_TTL_TRANSIENT = 5 * MINUTE_IN_SECONDS;
const SN_GH_THEME_FAIL_TTL_DURABLE   = HOUR_IN_SECONDS;

/**
 * Will this failure plausibly have fixed itself in five minutes?
 *
 * WHY THIS EXISTS (theme v10.43.0 — porting plugin v9.54.1)
 *
 * 2026-07-16 22:51 UTC GitHub declared "Degraded REST API Availability" — ~35%
 * of REST requests failing. Both S&N version cards went red four minutes later.
 * The 503 was GitHub's; the SIXTY MINUTES of blindness were ours. Every failure
 * cached the empty sentinel for HOUR_IN_SECONDS, so a one-second blip cost an
 * hour, and the next hourly poll had another ~35% chance of re-arming it.
 *
 * The tell was on the same dashboard: "Recent deploys" stayed live throughout
 * because its fetch caches failures for FIVE minutes and self-heals. Same host,
 * same token, same timeout — only the failure TTL differed.
 *
 * So classify by whether re-asking could plausibly get a different answer:
 *   - 5xx / 429 / network / timeout → the far end is unwell; it recovers.
 *   - 401 / 404                     → nothing changes in an hour. Don't hammer.
 *
 * @since theme v10.43.0
 * @param int|null $code HTTP status, 0 for a WP_Error, null when unknown.
 * @return bool
 */
function sn_gh_theme_failure_is_transient( $code ) {
	$code = (int) $code;
	return 0 === $code || 429 === $code || $code >= 500;
}

/**
 * How long to hold a failure before asking GitHub again.
 *
 * @since theme v10.43.0
 * @param int|null $code
 * @return int Seconds.
 */
function sn_gh_theme_failure_ttl( $code ) {
	return sn_gh_theme_failure_is_transient( $code )
		? SN_GH_THEME_FAIL_TTL_TRANSIENT
		: SN_GH_THEME_FAIL_TTL_DURABLE;
}

/**
 * Strip anything token-shaped out of a message before it can reach a screen.
 *
 * The reason is rendered in wp-admin. An HTTP driver message is not ours and
 * could in principle quote a request header back at us. Redact defensively
 * rather than reason about whether cURL ever does: the cost is a regex, the
 * failure mode is a leaked credential.
 *
 * @since theme v10.43.0
 * @param string $message
 * @return string
 */
function sn_gh_theme_redact_secrets( $message ) {
	return (string) preg_replace(
		'/\b(gh[pousr]_[A-Za-z0-9]{16,}|github_pat_[A-Za-z0-9_]{20,}|Bearer\s+\S+)/i',
		'[redacted]',
		(string) $message
	);
}

/**
 * Turn a failed tags fetch into a short sentence a human can act on.
 *
 * WHY (theme v10.43.0): on 2026-07-16 the PLUGIN's card said "GitHub returned
 * an unexpected HTTP 503" and that one line ended an hour of confident, wrong
 * theorising (an expired token; then a response-size timeout). The THEME's card
 * said nothing — a bare red "unknown" — because this function did not exist.
 * Same outage, same second, same screen: one surface could explain itself and
 * the other could not. Never silent.
 *
 * @since theme v10.43.0
 * @param array|WP_Error $response
 * @return string Never contains a credential.
 */
function sn_gh_theme_fetch_failure_reason( $response ) {
	if ( is_wp_error( $response ) ) {
		// No HTTP response at all — timeout, DNS, TLS. Carry the real driver
		// message ("cURL error 28: Operation timed out after 8001 ms"): the
		// number in it is the actual diagnosis.
		return sn_gh_theme_redact_secrets( sprintf(
			/* translators: %s: underlying HTTP error message. */
			__( 'could not reach GitHub — %s', 'signal-noise' ),
			$response->get_error_message()
		) );
	}

	$code = (int) wp_remote_retrieve_response_code( $response );
	switch ( $code ) {
		case 401:
			return __( 'GitHub rejected the credential (401) — SNT_GITHUB_TOKEN in wp-config.php is invalid, expired, or revoked', 'signal-noise' );
		case 403:
			return __( 'GitHub refused the request (403) — usually a rate limit; set SNT_GITHUB_TOKEN in wp-config.php to raise 60/h to 5000/h', 'signal-noise' );
		case 404:
			return __( 'GitHub returned 404 — the repository was renamed, deleted, or made private', 'signal-noise' );
		case 200:
			return __( 'GitHub returned 200 but the body was not a readable tag list', 'signal-noise' );
		default:
			return sprintf(
				/* translators: %d: HTTP status code. */
				__( 'GitHub returned an unexpected HTTP %d', 'signal-noise' ),
				$code
			);
	}
}

/**
 * Record why a fetch failed, alongside the empty-sentinel negative cache, for
 * a duration proportional to how likely the failure is to persist.
 *
 * @since theme v10.43.0
 * @param string   $reason
 * @param int|null $code
 * @return null Always null — callers `return sn_gh_theme_record_fetch_failure(...)`.
 */
function sn_gh_theme_record_fetch_failure( $reason, $code = null ) {
	$ttl = sn_gh_theme_failure_ttl( $code );
	set_site_transient( SN_GH_THEME_CACHE_KEY, '', $ttl );
	set_site_transient( SN_GH_THEME_ERROR_KEY, $reason, $ttl );
	return null;
}

/**
 * Why the last theme tag fetch failed, or '' if the last one succeeded.
 *
 * @since theme v10.43.0
 * @return string
 */
function sn_gh_latest_theme_tag_error() {
	$reason = get_site_transient( SN_GH_THEME_ERROR_KEY );
	return is_string( $reason ) ? $reason : '';
}

/**
 * Answer the companion plugin's Dashboard card.
 *
 * The plugin's snt_deploy_status_for('theme') asks
 * `apply_filters( 'sn_gh_latest_theme_tag_error_result', '' )` — a seam it
 * opened in v9.54.0 and, at the time, only implemented for itself. Without a
 * listener the filter returns '' forever and the theme's card renders a bare,
 * unexplained red dot. That is precisely what the owner stared at during the
 * 2026-07-16 outage while the plugin's card, six inches away, named the cause.
 *
 * Mirrors the tag filter's contract: the theme owns its data, the plugin owns
 * the card. (The plugin never calls a theme function directly — see
 * WORDPRESS-REFERENCE.md §10 and the sn_gh_latest_theme_tag_result seam.)
 *
 * @since theme v10.43.0
 */
add_filter( 'sn_gh_latest_theme_tag_error_result', function ( $reason ) {
	$own = sn_gh_latest_theme_tag_error();
	return '' !== $own ? $own : $reason;
} );

/**
 * Fetch the highest semver-formatted tag from GitHub. Returns the tag
 * string (e.g. "v8.5.0") on success, null on error / no matching tags.
 * Cached for SN_GH_THEME_CACHE_TTL; empty sentinel cached 1h on failure.
 *
 * @param bool $force_refresh When true, bypass the cache and re-fetch.
 *                            Used when WP's "Check Again" button is
 *                            clicked (WP_FORCE_UPDATE_CHECK constant
 *                            or `?force-check=1` query arg). Added v8.5.3.
 */
function sn_gh_latest_theme_tag( $force_refresh = false ) {
	if ( ! $force_refresh ) {
		$cached = get_site_transient( SN_GH_THEME_CACHE_KEY );
		if ( $cached !== false ) {
			return $cached === '' ? null : $cached;
		}
	}

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
		// 200 (no redirect), so this is behaviour-preserving; it just guarantees the
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
			__( 'GitHub returned no tags matching vX.Y.Z — nothing to compare against', 'signal-noise' ),
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

/**
 * Build the WP-upgrader download package URL for a tag.
 *
 * With SNT_GITHUB_TOKEN defined, use GitHub's authenticated API zipball
 * endpoint — the documented way to download a PRIVATE repo's archive, so the
 * wp-admin → Updates install path keeps working when this repo is private.
 * sn_gh_theme_authenticated_download() (below) injects the Bearer token for the
 * download. Without a token, fall back to the public auto-generated tag archive
 * (unchanged behaviour, correct for a public repo). The API zipball unpacks to
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
 * is set), performs an authenticated download_url() with the token scoped to
 * api.github.com, and returns the temp-file path — short-circuiting WP's
 * unauthenticated fetch. Any other package, or no token, returns $reply
 * unchanged so WP proceeds normally (public-repo path is untouched).
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
		$file = download_url( $package );
	} finally {
		// Always detach the token filter, even if download_url() throws — never
		// leave it attached for subsequent requests in this process.
		remove_filter( 'http_request_args', 'sn_gh_theme_inject_token_header', 10 );
	}
	return $file;
}
add_filter( 'upgrader_pre_download', 'sn_gh_theme_authenticated_download', 10, 4 );

/**
 * Register the theme with WP's update transient. WP renders it on
 * wp-admin/update-core.php and Appearance → Themes from this data.
 *
 * Theme update transient shape: `->response` and `->no_update` arrays
 * keyed by stylesheet, values are associative arrays (not stdClass as
 * the plugin transient uses). See WP core's _maybe_update_themes().
 */
/**
 * Whether a forced (cache-bypassing) update check was requested.
 *
 * A ?force-check= cache-bust triggers a live GitHub API call — a real side
 * effect that spends the rate-limit budget — so the query-string path is gated
 * on the update_themes capability. Without that gate, any logged-in user (or a
 * CSRF <img> pointing at an admin URL) could force repeated API calls and
 * exhaust the token's hourly budget. WP's own "Check Again" flow sets
 * WP_FORCE_UPDATE_CHECK and is already capability-gated, so that path is trusted.
 *
 * @since 10.11.2
 * @return bool
 */
function sn_gh_theme_force_refresh_requested() {
	if ( defined( 'WP_FORCE_UPDATE_CHECK' ) && WP_FORCE_UPDATE_CHECK ) {
		return true;
	}
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only cache-buster, capability-gated on this line.
	return ! empty( $_GET['force-check'] ) && current_user_can( 'update_themes' );
}

add_filter( 'pre_set_site_transient_update_themes', function( $transient ) {
	if ( empty( $transient ) || ! is_object( $transient ) ) {
		$transient = new stdClass();
	}

	// v8.5.3: honor WP's "Check Again" button (WP_FORCE_UPDATE_CHECK), and a
	// capability-gated ?force-check= cache-bust. Without this, our cached value
	// persists even when the user explicitly asks for a fresh check.
	$force_refresh = sn_gh_theme_force_refresh_requested();

	$latest_tag = sn_gh_latest_theme_tag( $force_refresh );
	if ( $latest_tag === null ) {
		return $transient;
	}

	$latest_version  = ltrim( $latest_tag, 'v' );
	$current_version = (string) wp_get_theme( SN_GH_THEME_STYLESHEET )->get( 'Version' );

	$theme_data = array(
		'theme'       => SN_GH_THEME_STYLESHEET,
		'new_version' => $latest_version,
		'url'         => 'https://github.com/' . SN_GH_THEME_OWNER . '/' . SN_GH_THEME_REPO,
		'package'     => sn_gh_theme_package_url( $latest_tag ),
	);

	if ( version_compare( $latest_version, $current_version, '>' ) ) {
		if ( ! isset( $transient->response ) || ! is_array( $transient->response ) ) {
			$transient->response = array();
		}
		$transient->response[ SN_GH_THEME_STYLESHEET ] = $theme_data;
	} else {
		if ( ! isset( $transient->no_update ) || ! is_array( $transient->no_update ) ) {
			$transient->no_update = array();
		}
		$transient->no_update[ SN_GH_THEME_STYLESHEET ] = $theme_data;
	}

	return $transient;
} );

/**
 * Rename the unpacked source directory so WP installs to the correct
 * stylesheet slug.
 *
 * GitHub's auto-generated tag archive (`/archive/refs/tags/v8.5.1.zip`)
 * unpacks to `signal-and-noise-8.5.1/` — with the version suffix but
 * without the leading 'v'. WP's installer uses the dir name to decide
 * where to install, which would end up as
 * `wp-content/themes/signal-and-noise-8.5.1/` (wrong slug, the theme
 * would deactivate on update because the active stylesheet
 * `signal-and-noise` would no longer resolve).
 *
 * The filter receives `$source` (path to the unpacked dir) and renames
 * it to drop the version suffix. Standard pattern for GitHub-hosted
 * themes that ship via auto-generated tag archives.
 *
 * Note: `$hook_extra['theme']` is the slug for theme installs (mirrors
 * the plugin-side filter's `$hook_extra['plugin']` basename). Guarding
 * on this prevents us from renaming other themes that pass through the
 * same filter during a multi-update batch.
 */
add_filter( 'upgrader_source_selection', function( $source, $remote_source, $upgrader, $hook_extra ) {
	$theme = isset( $hook_extra['theme'] ) ? (string) $hook_extra['theme'] : '';
	if ( $theme !== SN_GH_THEME_STYLESHEET ) {
		return $source;
	}

	$source         = trailingslashit( $source );
	$desired_source = trailingslashit( dirname( $source ) ) . SN_GH_THEME_STYLESHEET . '/';

	if ( $source === $desired_source ) {
		return $source;
	}

	global $wp_filesystem;
	if ( ! $wp_filesystem || ! $wp_filesystem->move( untrailingslashit( $source ), untrailingslashit( $desired_source ) ) ) {
		return new WP_Error(
			'sn_rename_source_failed',
			'Could not rename the unpacked theme directory from "' . esc_html( basename( $source ) ) . '" to "' . SN_GH_THEME_STYLESHEET . '". Manual install via SFTP may be required.'
		);
	}

	return $desired_source;
}, 10, 4 );

/**
 * On every admin pageview, check whether the on-disk theme version
 * differs from the last-seen version. If it does, clear the update
 * transient — the cached "latest" was relative to the previous
 * version and is now stale.
 *
 * Handles the upgrade-just-happened case automatically:
 * - WP UI install completes → next admin pageview clears the cache
 * - workflow_dispatch deploy lands → next admin pageview clears the cache
 *
 * Costs one get_option() call per admin pageview. Negligible.
 *
 * Added in v8.5.3 (2026-05-16). Mirrors plugin v1.11.1's admin_init
 * cache-invalidation handler.
 */
add_action( 'admin_init', function() {
	$last_seen = (string) get_option( SN_GH_THEME_LAST_SEEN_OPT, '' );
	$current   = (string) wp_get_theme( SN_GH_THEME_STYLESHEET )->get( 'Version' );
	if ( $current && $last_seen !== $current ) {
		delete_site_transient( SN_GH_THEME_CACHE_KEY );
		// Also clear WP's own theme update transient so the next poll
		// re-fetches fresh data (covers the case where WP cached our
		// pre-update version as "latest").
		delete_site_transient( 'update_themes' );
		// v8.5.4: also clear the parsed-themes-headers cache so the
		// Appearance → Themes screen renders the current theme header
		// (Name, Description, Author) instead of the cached pre-update
		// values. Required because our SSH-checkout deploy path doesn't
		// trigger WP's installer (which would call wp_clean_themes_cache
		// automatically). Bug surfaced when the theme name in style.css
		// had a literal &amp; entity that double-encoded on display;
		// fixing the header alone wasn't enough — the cache was stale.
		if ( function_exists( 'wp_clean_themes_cache' ) ) {
			wp_clean_themes_cache();
		}
		update_option( SN_GH_THEME_LAST_SEEN_OPT, $current );
	}
} );
