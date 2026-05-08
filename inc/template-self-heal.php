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
 *   - On admin_init (capability + rate-limit gated), schedule a single
 *     WP-Cron event for the next non-blocking spawn_cron() loopback.
 *     The admin pageview returns immediately; the actual GitHub
 *     fetches run in a parallel process. (Since v7.2.7 — previously
 *     this loop ran inline at admin_init and could synchronously
 *     block the admin pageview for up to N×10s on cold cache.)
 *   - In the cron callback, iterate the monitored file list (default:
 *     all .html files under templates/ and parts/, filterable via
 *     'sn_self_heal_files').
 *   - For each file, fetch the canonical version from GitHub via the
 *     Contents API using the existing SN_GITHUB_TOKEN.
 *   - Byte-for-byte comparison against the local file.
 *   - On drift, overwrite the local file using WP_Filesystem (the
 *     same write API the WP self-updater itself uses, so anything WP
 *     can write, this can write).
 *   - On any successful write, fire sn_purge_all_caches() so the new
 *     content is served immediately — no waiting for the next
 *     deploy-time cache invalidation.
 *   - The button-click + post-update force-run path (sn_self_heal_force_run)
 *     remains synchronous by design: the user is staring at the upgrader
 *     UI / Heal Now button and expects an immediate result.
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
 * Schedule a self-heal pass for the next non-blocking WP-Cron loopback
 * tick. Runs on admin pageviews, capability-gated, rate-limited via
 * SN_SELF_HEAL_CHECK_INTERVAL.
 *
 * Since v7.2.7: only schedules; the actual GitHub Contents API loop
 * runs in sn_self_heal_cron() via spawn_cron()'s non-blocking loopback.
 * Previously this called sn_self_heal_execute() inline at admin_init,
 * which on a cold cache could synchronously fetch every monitored
 * template via wp_remote_get (10s timeout each) — N templates × 10s
 * blocked the admin pageview every 5 min. Now the user's admin page
 * returns instantly while the heal runs in a parallel process.
 *
 * Priority 5 so the schedule call happens BEFORE wp_loaded fires —
 * wp_cron() picks up the just-scheduled event in the same request and
 * dispatches the loopback before the admin response is sent. (Same
 * timing trick used by the Plausible warmer in inc/plausible-api.php.)
 *
 * The user_id is stashed in the cron event args so the per-user notice
 * transient lands under the admin who triggered the schedule, not
 * under user 0 (cron context has no current user).
 */
add_action( 'admin_init', 'sn_self_heal_run', 5 );

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

	// Rate limit: bail if we've already checked recently. Mirrors the gate
	// inside sn_self_heal_execute() so we don't even bother scheduling a
	// no-op cron event.
	$last = (int) get_option( SN_SELF_HEAL_LAST_CHECK_OPT, 0 );
	if ( time() - $last < SN_SELF_HEAL_CHECK_INTERVAL ) {
		return;
	}

	// Don't pile up duplicate cron events when several admins hit the
	// dashboard within the same rate-limit window.
	if ( wp_next_scheduled( 'sn_self_heal_cron' ) ) {
		return;
	}

	wp_schedule_single_event( time(), 'sn_self_heal_cron', array( get_current_user_id() ) );
}

/**
 * Cron-driven ambient heal pass. Wraps the same execute() logic the
 * synchronous force-run uses, but with the rate-limit gate active
 * and the notice routed to the admin who triggered the schedule.
 */
add_action( 'sn_self_heal_cron', 'sn_self_heal_cron' );

function sn_self_heal_cron( $user_id = 0 ) {
	sn_self_heal_execute( false, (int) $user_id );
}

/**
 * Force-run self-heal immediately, bypassing the rate-limit gate AND
 * clearing per-file failure cooldowns. Capability check is the caller's
 * responsibility — this is the entry point used by:
 *   - The "Heal Templates Now" admin button (gated by nonce + admin capability).
 *   - The post-update hook in updater.php (runs as the admin who triggered
 *     the upgrader, capability already enforced upstream).
 *
 * Why a separate force path: the standard rate-limit (5 min between runs)
 * is correct for ambient admin pageviews, but actively wrong for the two
 * recovery scenarios above:
 *   - After a broken self-heal run set the rate-limit option but skipped
 *     writes, the FIXED version is rate-limited from running for ~5 min.
 *   - Right after a successful Update, files just got rewritten by the
 *     upgrader — verifying drift immediately is the whole point.
 *
 * @return array{fixed: string[], failed: string[]} Lists of paths.
 */
function sn_self_heal_force_run() {
	// Clear per-file failure cooldowns so files in 1-hour back-off get
	// retried. The user's intent (clicking "Heal Now" or just-finished
	// Update) is "try again now, I have new information".
	delete_option( SN_SELF_HEAL_FAILURES_OPT );
	return sn_self_heal_execute( true );
}

/**
 * Internal: actual heal loop. Caller decides whether to enforce the
 * rate-limit gate (false = ambient cron path, true = manual / post-
 * update force path) and which user the result notice is routed to.
 *
 * @param bool     $force          True to skip rate-limit; false to honor it.
 * @param int|null $notice_user_id Optional user_id for the result-notice
 *                                  transient. Defaults to the current
 *                                  user (the logged-in admin in HTTP
 *                                  contexts; 0 in cron). The cron
 *                                  caller passes the admin who triggered
 *                                  the schedule so the notice is
 *                                  visible to them on their next page.
 * @return array{fixed: string[], failed: string[]}
 */
function sn_self_heal_execute( $force, $notice_user_id = null ) {
	$result = array( 'fixed' => array(), 'failed' => array() );

	if ( ! defined( 'SN_GITHUB_TOKEN' ) || empty( SN_GITHUB_TOKEN ) ) {
		return $result;
	}
	if ( ! defined( 'SN_GITHUB_REPO' ) || empty( SN_GITHUB_REPO ) ) {
		return $result;
	}

	if ( ! $force ) {
		// Rate limit: bail if we've checked recently.
		$last = (int) get_option( SN_SELF_HEAL_LAST_CHECK_OPT, 0 );
		if ( time() - $last < SN_SELF_HEAL_CHECK_INTERVAL ) {
			return $result;
		}
	}
	// Mark check time BEFORE running so a slow run doesn't stack.
	// Force runs also update this so subsequent ambient runs don't
	// immediately re-check.
	update_option( SN_SELF_HEAL_LAST_CHECK_OPT, time(), false );

	$branch = function_exists( 'sn_updater_branch' ) ? sn_updater_branch() : 'main';
	$files  = sn_self_heal_files();

	foreach ( $files as $relpath ) {
		$check = sn_self_heal_check_one( $relpath, $branch );
		if ( 'fixed' === $check ) {
			$result['fixed'][] = $relpath;
		} elseif ( 'failed' === $check ) {
			$result['failed'][] = $relpath;
		}
	}

	// If we wrote any files, purge caches so the new content is served
	// immediately. Without this, the file change is on disk but Breeze /
	// object cache might still serve old rendered HTML.
	if ( $result['fixed'] && function_exists( 'sn_purge_all_caches' ) ) {
		sn_purge_all_caches();
	}

	if ( $result['fixed'] || $result['failed'] ) {
		// Resolve notice audience: explicit user_id from the cron caller
		// wins, otherwise fall back to the current user (HTTP context).
		// Skip writing entirely when audience resolves to 0 (no logged-in
		// admin, no scheduling user) — the notice would be invisible
		// anyway and would just clutter the options table.
		$audience = ( null !== $notice_user_id ) ? (int) $notice_user_id : get_current_user_id();
		if ( $audience > 0 ) {
			set_transient(
				'sn_self_heal_notice_' . $audience,
				array(
					'fixed'  => $result['fixed'],
					'failed' => $result['failed'],
					'when'   => time(),
				),
				5 * MINUTE_IN_SECONDS
			);
		}
	}

	return $result;
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
