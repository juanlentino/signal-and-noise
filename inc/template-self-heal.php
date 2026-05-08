<?php
/**
 * Signal & Noise — Template self-heal.
 *
 * Detects drift between specific theme template files on disk and the
 * canonical version in GitHub `main`, and overwrites the local copy
 * when drift is detected. Acts as a safety net against the failure
 * mode where the WP self-updater extracts a new theme zip but
 * silently misses some files (e.g., a Cloudways file lock or a
 * permission issue on a specific path).
 *
 * Why this exists: on 2026-05-07 a deploy quietly skipped
 * `templates/page-notes.html` while updating every other theme file.
 * The site rendered OLD template content for hours despite multiple
 * theme updates, with no error reported anywhere — Cloudflare wasn't
 * caching, Breeze wasn't caching, the file just wasn't being
 * overwritten. Diagnosis was slow precisely because the failure was
 * silent. This module makes that class of failure recoverable
 * without requiring SSH/SFTP intervention.
 *
 * How it works:
 *   - On admin_init (rate-limited via 5-min option), iterate the
 *     monitored file list (default: all .html files under templates/
 *     and parts/, filterable via 'sn_self_heal_files').
 *   - For each file, fetch the canonical version from GitHub via the
 *     Contents API using the existing SN_GITHUB_TOKEN. The request
 *     uses the `application/vnd.github.v3.raw` accept header so
 *     GitHub returns the raw file content (no base64-in-JSON
 *     wrapping).
 *   - Byte-for-byte comparison against the local file.
 *   - On drift, overwrite the local file using WP_Filesystem (the
 *     same write API the WP self-updater itself uses, so anything WP
 *     can write, this can write).
 *   - On any successful write, fire sn_purge_all_caches() so the new
 *     content is served immediately — no waiting for the next
 *     deploy-time cache invalidation.
 *
 * Defensive properties:
 *   - Rate-limited (5 min between checks) so admin pageviews don't
 *     hit GitHub on every load.
 *   - Per-file failure counter: after 3 consecutive write failures,
 *     the file enters a 1-hour cooldown to avoid retry storms when
 *     the underlying issue (file lock, permission) hasn't been
 *     resolved.
 *   - Capability gate (manage_options) plus admin-only execution
 *     (admin_init); never runs on front-end requests.
 *   - Graceful degradation: if SN_GITHUB_TOKEN isn't set, GitHub is
 *     unreachable, or any other transient failure, no-op silently.
 *   - Whitelist-based scope: only files explicitly in the monitored
 *     list are touched. Default whitelist is templates/*.html and
 *     parts/*.html — small, scoped, and high-value (these files
 *     control rendering).
 *   - Admin notice on every check that performed a write or
 *     encountered a write failure, so the user knows the module is
 *     working and can spot persistent problems.
 *
 * @package SignalNoise
 * @since 7.0.x
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const SN_SELF_HEAL_LAST_CHECK_OPT  = 'sn_self_heal_last_check';
const SN_SELF_HEAL_FAILURES_OPT    = 'sn_self_heal_failures';
const SN_SELF_HEAL_CHECK_INTERVAL  = 5 * MINUTE_IN_SECONDS;
const SN_SELF_HEAL_MAX_RETRIES     = 3;
const SN_SELF_HEAL_RETRY_COOLDOWN  = HOUR_IN_SECONDS;

/**
 * Resolve the list of theme files to monitor. Defaults to every .html
 * file under templates/ and parts/. Filterable so future modules or
 * one-off configurations can add or remove paths without modifying
 * this file.
 *
 * Returned paths are relative to the theme root.
 *
 * @return string[]
 */
function sn_self_heal_files() {
	$files = array();
	foreach ( array( 'templates', 'parts' ) as $dir ) {
		$abs = get_theme_file_path( $dir );
		if ( ! is_dir( $abs ) ) {
			continue;
		}
		foreach ( (array) glob( $abs . '/*.html' ) as $f ) {
			$files[] = $dir . '/' . basename( $f );
		}
	}
	return apply_filters( 'sn_self_heal_files', $files );
}

/**
 * Run the self-heal check on admin pageviews. Rate-limited via the
 * SN_SELF_HEAL_CHECK_INTERVAL option so we don't hit GitHub on every
 * admin pageview. Capability-gated so only admins trigger it.
 *
 * Priority 20 so this runs AFTER the cache-purge / mtime check at
 * priority 10 — that way if the mtime check just cleared overrides,
 * the self-heal can verify file content right after.
 */
add_action( 'admin_init', 'sn_self_heal_run', 20 );

function sn_self_heal_run() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	if ( ! defined( 'SN_GITHUB_TOKEN' ) || empty( SN_GITHUB_TOKEN ) ) {
		return;
	}
	if ( ! defined( 'SN_GITHUB_REPO' ) || empty( SN_GITHUB_REPO ) ) {
		return;
	}

	// Rate limit: bail if we've checked recently.
	$last = (int) get_option( SN_SELF_HEAL_LAST_CHECK_OPT, 0 );
	if ( time() - $last < SN_SELF_HEAL_CHECK_INTERVAL ) {
		return;
	}
	// Mark check time BEFORE running so a slow run doesn't stack.
	update_option( SN_SELF_HEAL_LAST_CHECK_OPT, time(), false );

	$branch = function_exists( 'sn_updater_branch' ) ? sn_updater_branch() : 'main';
	$files  = sn_self_heal_files();
	$fixed  = array();
	$failed = array();

	foreach ( $files as $relpath ) {
		$result = sn_self_heal_check_one( $relpath, $branch );
		if ( 'fixed' === $result ) {
			$fixed[] = $relpath;
		} elseif ( 'failed' === $result ) {
			$failed[] = $relpath;
		}
	}

	// If we wrote any files, purge caches so the new content is served
	// immediately. Without this, the file change is on disk but Breeze /
	// object cache might still serve old rendered HTML.
	if ( $fixed && function_exists( 'sn_purge_all_caches' ) ) {
		sn_purge_all_caches();
	}

	if ( $fixed || $failed ) {
		set_transient(
			'sn_self_heal_notice_' . get_current_user_id(),
			array(
				'fixed'  => $fixed,
				'failed' => $failed,
				'when'   => time(),
			),
			5 * MINUTE_IN_SECONDS
		);
	}
}

/**
 * Check a single file: fetch from GitHub, compare to local, write
 * local if different.
 *
 * Fetch strategy: explicit JSON request (default GitHub Contents API
 * format) + base64 decode of the `content` field. Earlier versions of
 * this code used the `application/vnd.github.v3.raw` Accept header
 * trying to get raw bytes back — but that header was deprecated by
 * GitHub at some point, and when not honored, GitHub silently fell
 * back to returning the JSON metadata response. Without response-
 * shape validation, that JSON got written into the local files,
 * corrupting every monitored template (incident: this commit fixes
 * it). The base64-decode approach is more reliable: the response
 * format is well-known, the encoding field is an explicit signal,
 * and we can size-check the decoded bytes against the API's
 * declared `size` value as a final integrity gate.
 *
 * Validation gates before any write happens:
 *   1. HTTP 200 required
 *   2. Response is parseable JSON
 *   3. JSON has `content` and `encoding == "base64"`
 *   4. Base64 decodes successfully
 *   5. Decoded length matches the API's declared `size`
 *   6. For .html files, decoded content starts with `<` (must look
 *      like HTML/XML; rules out JSON, base64, or other accidents)
 *   7. Decoded content differs from local content
 *
 * Only if ALL of the above pass do we write. If any fail, we return
 * 'skipped' and never touch the local file.
 *
 * @param string $relpath Path relative to theme root.
 * @param string $branch  GitHub branch to compare against.
 * @return string One of: 'ok' (match), 'fixed' (wrote local),
 *                'failed' (write attempted but failed),
 *                'cooldown' (in failure cooldown), 'skipped'
 *                (transient error, local file missing, or any
 *                validation failure on the remote content).
 */
function sn_self_heal_check_one( $relpath, $branch ) {
	$local_path = get_theme_file_path( $relpath );
	if ( ! file_exists( $local_path ) ) {
		return 'skipped';
	}

	// Cooldown check: if this file failed to write 3+ times in the last
	// hour, skip it for now. Avoids retry storms when the underlying
	// problem (e.g., file lock) hasn't been resolved.
	$failures = (array) get_option( SN_SELF_HEAL_FAILURES_OPT, array() );
	if ( isset( $failures[ $relpath ] ) ) {
		$f = $failures[ $relpath ];
		if ( ! empty( $f['count'] ) && (int) $f['count'] >= SN_SELF_HEAL_MAX_RETRIES
			&& time() - (int) $f['last'] < SN_SELF_HEAL_RETRY_COOLDOWN ) {
			return 'cooldown';
		}
	}

	// Fetch metadata + base64 content via Contents API in default JSON
	// format. We avoid the `Accept: vnd.github.raw` header path because
	// it silently fell back to JSON when not recognized, leading to
	// the corruption incident this commit fixes.
	$url      = 'https://api.github.com/repos/' . SN_GITHUB_REPO
		. '/contents/' . ltrim( $relpath, '/' )
		. '?ref=' . rawurlencode( $branch );
	$response = wp_remote_get( $url, array(
		'headers' => array(
			'Authorization' => 'token ' . SN_GITHUB_TOKEN,
			'Accept'        => 'application/vnd.github+json',
		),
		'timeout' => 10,
	) );

	if ( is_wp_error( $response ) ) {
		return 'skipped';
	}
	if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
		return 'skipped';
	}

	$body = wp_remote_retrieve_body( $response );
	$meta = json_decode( $body, true );

	// Validation: must be an array with the expected fields. If the
	// API ever changes shape we fail safe (skip) rather than write.
	if ( ! is_array( $meta ) || empty( $meta['content'] ) || empty( $meta['encoding'] ) ) {
		return 'skipped';
	}
	if ( 'base64' !== $meta['encoding'] ) {
		return 'skipped';
	}

	// Decode (strict: invalid base64 returns false). Strip whitespace
	// from base64 first since GitHub line-wraps the value.
	$remote_content = base64_decode( preg_replace( '/\s+/', '', $meta['content'] ), true );
	if ( false === $remote_content ) {
		return 'skipped';
	}

	// Size cross-check: API tells us the file size. If decoded bytes
	// don't match, something's wrong with the response — refuse to
	// write to avoid corrupting the local file.
	if ( isset( $meta['size'] ) && strlen( $remote_content ) !== (int) $meta['size'] ) {
		return 'skipped';
	}

	// Content-shape gate: for .html files (everything we monitor by
	// default), the content MUST start with `<`. If it doesn't —
	// indicating JSON, base64, plain text, anything other than markup
	// — we refuse to write. This is the defense-in-depth that would
	// have prevented the corruption incident even if all above
	// validations had passed wrongly.
	if ( '.html' === substr( $relpath, -5 ) ) {
		$first_non_ws = ltrim( $remote_content );
		if ( '' === $first_non_ws || '<' !== substr( $first_non_ws, 0, 1 ) ) {
			return 'skipped';
		}
	}

	$local_content = file_get_contents( $local_path );

	if ( $remote_content === $local_content ) {
		// In sync. Clear any prior failure record for this file.
		if ( isset( $failures[ $relpath ] ) ) {
			unset( $failures[ $relpath ] );
			update_option( SN_SELF_HEAL_FAILURES_OPT, $failures, false );
		}
		return 'ok';
	}

	// Drift detected AND remote content passed every validation gate.
	// Safe to write.
	if ( ! function_exists( 'WP_Filesystem' ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
	}
	WP_Filesystem();
	global $wp_filesystem;

	if ( ! $wp_filesystem ) {
		return 'failed';
	}

	$written = $wp_filesystem->put_contents( $local_path, $remote_content, FS_CHMOD_FILE );

	if ( $written ) {
		// Success. Clear the failure record.
		if ( isset( $failures[ $relpath ] ) ) {
			unset( $failures[ $relpath ] );
			update_option( SN_SELF_HEAL_FAILURES_OPT, $failures, false );
		}
		return 'fixed';
	}

	// Write failed — increment counter for cooldown logic.
	$failures[ $relpath ] = array(
		'count' => isset( $failures[ $relpath ]['count'] ) ? (int) $failures[ $relpath ]['count'] + 1 : 1,
		'last'  => time(),
	);
	update_option( SN_SELF_HEAL_FAILURES_OPT, $failures, false );
	return 'failed';
}

/**
 * Show admin notice when self-heal has fixed or failed any files.
 * Per-user transient so different admins don't see each other's
 * notices.
 */
add_action( 'admin_notices', function() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$key    = 'sn_self_heal_notice_' . get_current_user_id();
	$notice = get_transient( $key );
	if ( ! $notice ) {
		return;
	}
	delete_transient( $key );

	if ( ! empty( $notice['fixed'] ) ) {
		echo '<div class="notice notice-success is-dismissible"><p>';
		echo '<strong>Signal &amp; Noise self-heal:</strong> updated '
			. (int) count( $notice['fixed'] )
			. ' theme file(s) from GitHub: ';
		$items = array_map( 'esc_html', (array) $notice['fixed'] );
		echo '<code>' . implode( '</code>, <code>', $items ) . '</code>';
		echo '. Caches purged.';
		echo '</p></div>';
	}

	if ( ! empty( $notice['failed'] ) ) {
		echo '<div class="notice notice-error is-dismissible"><p>';
		echo '<strong>Signal &amp; Noise self-heal:</strong> drift detected but write failed for '
			. (int) count( $notice['failed'] )
			. ' file(s): ';
		$items = array_map( 'esc_html', (array) $notice['failed'] );
		echo '<code>' . implode( '</code>, <code>', $items ) . '</code>';
		echo '. Likely a file permission or lock issue — check via SFTP. ';
		echo 'Will retry every 5 min until 3 failures, then back off for 1 hour.';
		echo '</p></div>';
	}
} );
