<?php
/**
 * Signal & Noise — GitHub self-updater.
 *
 * Tracks `main` directly by commit SHA. Push to main, WP polls every 5
 * minutes (or click into Dashboard → Updates to force a fresh poll), click
 * Update — no version bump required, no GitHub release per iteration.
 *
 * Tagged releases are still useful for milestone correlation and changelog
 * UX in GitHub's Releases tab, but they no longer drive the update
 * mechanism. The mtime-based asset cache-busting (sn_asset_ver in
 * inc/assets-frontend.php) means CSS/JS changes propagate without theme
 * Version: bumps too, so version bumps are reserved for actual milestones
 * the maintainer wants to mark.
 *
 * Synthetic update label format: `{Version}{-rN}+{branch}.{sha7}`
 *   - {Version}    Theme Version: header (e.g. "6.5.5").
 *   - -rN          Optional. Count of commits ahead of the v{Version} tag,
 *                  giving a consecutive sequence between milestones (r1, r2,
 *                  r3, …). Resets to 0 each time the maintainer ships a
 *                  milestone (bumps Version + tags). Suppressed when 0 or
 *                  unavailable. Computed via GitHub's compare API.
 *   - +branch.sha7 Tracked branch + 7-char commit SHA being offered.
 *
 * Example: `6.5.5-r3+main.a1b2c3d` reads as "3rd commit on main since
 * v6.5.5 was tagged, at SHA a1b2c3d".
 *
 * Override: define SN_GITHUB_BRANCH in wp-config.php to track a different
 * branch (e.g. for testing). Defaults to 'main' when undefined.
 *
 * Requires: SN_GITHUB_TOKEN constant in wp-config.php
 *   define( 'SN_GITHUB_TOKEN', 'github_pat_...' );
 *
 * The token needs only "contents: read" permission on
 * the juanlentino/signal-and-noise repo (fine-grained PAT).
 *
 * Error handling: WP_Error and non-200 responses capture into transient
 * 'sn_github_error' (1h TTL). An admin_notices hook below surfaces the
 * error on Dashboard / Updates / Themes screens.
 *
 * @package SignalNoise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SN_GITHUB_REPO', 'juanlentino/signal-and-noise' );
define( 'SN_THEME_SLUG',  'signal-and-noise' );

/**
 * Resolve which branch to track. Defaults to 'main'; SN_GITHUB_BRANCH
 * constant overrides for cases like staging or testing branches.
 */
function sn_updater_branch() {
	return ( defined( 'SN_GITHUB_BRANCH' ) && SN_GITHUB_BRANCH ) ? SN_GITHUB_BRANCH : 'main';
}

/**
 * Count commits on the tracked branch since the last tagged release
 * matching the current theme Version. Used to surface a consecutive
 * "revision" number in the synthetic update-available label so the
 * maintainer can read at a glance which iteration is being offered
 * (e.g. -r3 = 3rd commit since v6.5.4 was tagged). Counter resets to
 * 0 each time the maintainer ships a milestone (bumps Version + tags).
 *
 * Uses GitHub's compare API: the `ahead_by` field on a v{Version}
 * → branch comparison is exactly the number of commits the branch is
 * ahead of the tag. Cached 5 min to align with the existing branch-
 * HEAD cache. Returns 0 on any failure (missing tag, API error, rate
 * limit) so the synthetic label gracefully degrades to "no -rN suffix"
 * rather than blocking the update.
 *
 * @param string $branch Branch name being tracked.
 * @param string $branch                 Branch being tracked (e.g., "main").
 * @param string $version_for_compare   Optional. Version to use as the base
 *                                       tag for the compare API. Defaults to
 *                                       the locally-installed `Version:`
 *                                       header. Pass the remote Version when
 *                                       building update labels so a remote
 *                                       bump (e.g. v6.5.5 → v7.0.0 on main)
 *                                       resets the -rN counter visually,
 *                                       instead of showing "6.5.5-rN" while
 *                                       the destination commit is tagged
 *                                       v7.0.0.
 * @return int Commits ahead of the v{Version} tag, or 0 on failure.
 */
function sn_updater_revcount( $branch, $version_for_compare = null ) {
	$base_version = ( null !== $version_for_compare && '' !== $version_for_compare )
		? $version_for_compare
		: wp_get_theme( SN_THEME_SLUG )->get( 'Version' );

	// Read-only cache accessor since v7.3.1. Returns 0 when no cached
	// value is available (cron warmup hasn't run yet, or rate-limited
	// / API failure populated 0). The cache is written by
	// sn_updater_refresh_cache() running in a non-blocking spawn_cron()
	// loopback dispatched by the admin_init warmer below — see that
	// function for the actual GitHub Compare API call.
	$cache_key = 'sn_github_revcount_' . sanitize_key( $branch ) . '_' . sanitize_key( $base_version );
	$cached    = get_transient( $cache_key );
	return ( false !== $cached ) ? (int) $cached : 0;
}

/**
 * Fetch the `Version:` header from the remote branch's `style.css` so the
 * update-offer label can reflect the version of the commit being installed,
 * not the version of the locally-installed theme. Without this, a tagged
 * release like v7.0.0 on a site running 6.5.5 produces a label of
 * "6.5.5-rN+main.<sha>" — mathematically correct (N commits ahead of v6.5.5)
 * but semantically misleading because the destination IS v7.0.0.
 *
 * Cached 5 min like the other GitHub calls. Empty on failure (caller falls
 * back to local Version).
 *
 * @param string $branch
 * @return string Version string from remote style.css, or '' on failure.
 */
function sn_updater_remote_version( $branch ) {
	$branch = sanitize_key( $branch );
	if ( '' === $branch ) {
		return '';
	}
	// Read-only cache accessor since v7.3.1. The cache is written by
	// sn_updater_refresh_cache() via WP-Cron — see that function for
	// the actual GitHub raw style.css fetch and Version header regex.
	$cached = get_transient( 'sn_github_remote_version_' . $branch );
	return ( false !== $cached ) ? (string) $cached : '';
}

/**
 * Check GitHub for a new HEAD commit on the tracked branch and inject it
 * into WP's update system. SHA-comparison against `sn_github_local_sha`
 * (set after a successful upgrade) is the real gate; the version label
 * shown in the admin UI is synthetic.
 */
add_filter( 'pre_set_site_transient_update_themes', function( $transient ) {
	if ( empty( $transient->checked ) ) {
		return $transient;
	}

	if ( ! defined( 'SN_GITHUB_TOKEN' ) || empty( SN_GITHUB_TOKEN ) ) {
		return $transient;
	}

	// Read-only since v7.3.1. The branch HEAD cache is warmed by
	// sn_updater_refresh_cache() running in a non-blocking
	// spawn_cron() loopback dispatched by the admin_init warmer below.
	// If the cache is empty (cron hasn't populated it yet, or this is
	// the very first request after a fresh install / cache flush), we
	// return the transient unchanged — WP doesn't think there's an
	// update this cycle and will retry on its own schedule once the
	// cache lands. The filter never blocks on wp_remote_get; previously
	// a cold cache stalled WP's update_themes refresh for up to 25s.
	$branch = sn_updater_branch();
	$cached = get_transient( 'sn_github_branch_' . sanitize_key( $branch ) );
	if ( ! is_array( $cached ) || empty( $cached['sha'] ) ) {
		return $transient;
	}

	$remote_sha7 = substr( $cached['sha'], 0, 7 );
	$local_sha7  = (string) get_option( 'sn_github_local_sha', '' );

	if ( $remote_sha7 && $remote_sha7 !== $local_sha7 ) {
		$local_version  = wp_get_theme( SN_THEME_SLUG )->get( 'Version' );
		$remote_version = sn_updater_remote_version( $branch );

		// Use remote version if we got one, otherwise fall back to local.
		// Why this matters: when a tagged release bumps Version: on main
		// (e.g., v6.5.5 → v7.0.0), the LOCAL Version is still 6.5.5 until
		// the user clicks Update. Using local for the label produces
		// "6.5.5-rN+main.<sha>" pointing at a v7.0.0 commit, which reads
		// as misleading even though the destination is correct.
		$label_version = '' !== $remote_version ? $remote_version : $local_version;

		// Compute -rN against v{label_version} so a remote bump resets the
		// counter visually. If the v{label_version} tag exists and HEAD is
		// AT that tag (the bump commit), ahead_by = 0 → no -rN suffix and
		// the label reads as plain "{version}+{branch}.<sha>" (or just the
		// version when sha equals the tag's commit, but WP's UI shows our
		// label verbatim regardless).
		$rev           = sn_updater_revcount( $branch, $label_version );
		$rev_suffix    = $rev > 0 ? '-r' . $rev : '';

		$transient->response[ SN_THEME_SLUG ] = array(
			'theme'       => SN_THEME_SLUG,
			// Synthetic version label — the SHA-vs-stored check above is the
			// real gate; this is what WP shows in the "Update available" row
			// so a human can identify which commit is being installed. The
			// -rN suffix is the count of commits since the v{label_version}
			// tag, giving a readable consecutive sequence between milestones.
			'new_version' => $label_version . $rev_suffix . '+' . $branch . '.' . $remote_sha7,
			'url'         => 'https://github.com/' . SN_GITHUB_REPO . '/tree/' . rawurlencode( $branch ),
			'package'     => 'https://api.github.com/repos/' . SN_GITHUB_REPO . '/zipball/' . rawurlencode( $branch ),
		);
	}

	return $transient;
} );

const SN_UPDATER_REFRESH_HOOK     = 'sn_updater_refresh_cache';
const SN_UPDATER_FRESHNESS        = 5 * MINUTE_IN_SECONDS;       // matches the prior on-render cache TTL
const SN_UPDATER_RETENTION        = DAY_IN_SECONDS;              // long survival so stale data is always visible
const SN_UPDATER_RETENTION_SHORT  = 15 * MINUTE_IN_SECONDS;      // for empty/error sentinels (revcount=0, version='')

/**
 * Cron-driven refresh of all three GitHub-derived caches the updater
 * relies on. Runs in a non-blocking spawn_cron() loopback dispatched
 * by the admin_init warmer below — never on the page-render path.
 *
 * Fetches in sequence:
 *   1. /repos/X/commits/{branch}  — branch HEAD (drives the SHA-vs-stored
 *                                   gate that decides whether to offer an
 *                                   update at all). Long retention; the
 *                                   embedded `fetched` field is the
 *                                   freshness gate.
 *   2. /raw/style.css             — Version: header from the remote branch
 *                                   (used for the synthetic update label).
 *   3. /repos/X/compare/v{ver}... — ahead-by count for the -rN suffix.
 *
 * On any HTTP error, we either preserve the prior cache (so the admin
 * keeps seeing the last known state) or write a short-TTL empty
 * sentinel (so we don't re-fetch on every cron tick during a sustained
 * outage). The 'sn_github_error' transient is the human-readable
 * surface for the failure — already wired to admin_notices for the
 * Dashboard / Updates / Themes screens.
 */
function sn_updater_refresh_cache() {
	if ( ! defined( 'SN_GITHUB_TOKEN' ) || empty( SN_GITHUB_TOKEN ) ) {
		return;
	}

	$branch     = sn_updater_branch();
	$branch_key = sanitize_key( $branch );

	// 1. Branch HEAD ────────────────────────────────────────────────
	$response = wp_remote_get(
		'https://api.github.com/repos/' . SN_GITHUB_REPO . '/commits/' . rawurlencode( $branch ),
		array(
			'headers' => array(
				'Authorization' => 'token ' . SN_GITHUB_TOKEN,
				'Accept'        => 'application/vnd.github.v3+json',
			),
			'timeout' => 10,
		)
	);
	if ( is_wp_error( $response ) ) {
		set_transient( 'sn_github_error', $response->get_error_message(), HOUR_IN_SECONDS );
		return;
	}
	$code = wp_remote_retrieve_response_code( $response );
	if ( 200 !== $code ) {
		set_transient( 'sn_github_error', 'HTTP ' . (int) $code . ' from GitHub commits API (branch: ' . esc_html( $branch ) . ')', HOUR_IN_SECONDS );
		return;
	}
	$commits = json_decode( wp_remote_retrieve_body( $response ), true );
	if ( ! is_array( $commits ) || empty( $commits['sha'] ) ) {
		set_transient( 'sn_github_error', 'Malformed GitHub commits response (branch: ' . esc_html( $branch ) . ')', HOUR_IN_SECONDS );
		return;
	}
	$commits['fetched'] = time();
	set_transient( 'sn_github_branch_' . $branch_key, $commits, SN_UPDATER_RETENTION );
	delete_transient( 'sn_github_error' );

	// 2. Remote Version: header ─────────────────────────────────────
	$remote_version = '';
	$response       = wp_remote_get(
		'https://raw.githubusercontent.com/' . SN_GITHUB_REPO . '/' . rawurlencode( $branch ) . '/style.css',
		array( 'timeout' => 5 )
	);
	if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
		$body = wp_remote_retrieve_body( $response );
		// Match WP's standard theme header parser: optional leading
		// whitespace, case-insensitive `Version:` followed by value to EOL.
		if ( preg_match( '/^[ \t\/*#@]*Version:\s*(.+)$/mi', $body, $m ) ) {
			$remote_version = trim( $m[1] );
		}
	}
	// Always write — even an empty string — so the read accessor returns
	// something quickly and we don't re-fetch on every cron tick during
	// a sustained outage. Short TTL on the empty sentinel.
	set_transient(
		'sn_github_remote_version_' . $branch_key,
		$remote_version,
		'' === $remote_version ? SN_UPDATER_RETENTION_SHORT : SN_UPDATER_RETENTION
	);

	// 3. Revcount via Compare API ───────────────────────────────────
	// Cache key includes base version so concurrent caches for different
	// base tags don't alias during a deploy transition.
	$base_version = '' !== $remote_version
		? $remote_version
		: wp_get_theme( SN_THEME_SLUG )->get( 'Version' );
	$cache_key    = 'sn_github_revcount_' . $branch_key . '_' . sanitize_key( $base_version );
	$ahead        = 0;
	$response     = wp_remote_get(
		'https://api.github.com/repos/' . SN_GITHUB_REPO . '/compare/' . rawurlencode( 'v' . $base_version ) . '...' . rawurlencode( $branch ),
		array(
			'headers' => array(
				'Authorization' => 'token ' . SN_GITHUB_TOKEN,
				'Accept'        => 'application/vnd.github.v3+json',
			),
			'timeout' => 10,
		)
	);
	if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
		$body  = json_decode( wp_remote_retrieve_body( $response ), true );
		$ahead = isset( $body['ahead_by'] ) ? (int) $body['ahead_by'] : 0;
	}
	set_transient(
		$cache_key,
		$ahead,
		0 === $ahead ? SN_UPDATER_RETENTION_SHORT : SN_UPDATER_RETENTION
	);
}
add_action( SN_UPDATER_REFRESH_HOOK, 'sn_updater_refresh_cache' );

/**
 * Admin warmer: on every admin pageview, age-check the branch HEAD
 * cache via the embedded `fetched` field and schedule a non-blocking
 * background refresh if it's older than the freshness target. Hooked
 * at admin_init priority 5 so the schedule lands BEFORE wp_loaded
 * fires — wp_cron() picks up the event in the same request and
 * spawn_cron() dispatches the loopback before the response is sent.
 *
 * Capability gate matches WP's own update-check UI surfaces.
 */
add_action( 'admin_init', function() {
	if ( ! current_user_can( 'update_themes' ) ) {
		return;
	}
	if ( ! defined( 'SN_GITHUB_TOKEN' ) || empty( SN_GITHUB_TOKEN ) ) {
		return;
	}
	$branch = sanitize_key( sn_updater_branch() );
	$cached = get_transient( 'sn_github_branch_' . $branch );
	$age    = ( is_array( $cached ) && isset( $cached['fetched'] ) )
		? ( time() - (int) $cached['fetched'] )
		: PHP_INT_MAX;
	if ( $age > SN_UPDATER_FRESHNESS && ! wp_next_scheduled( SN_UPDATER_REFRESH_HOOK ) ) {
		wp_schedule_single_event( time(), SN_UPDATER_REFRESH_HOOK );
	}
}, 5 );

/**
 * After a successful theme upgrade, store the tracked branch's HEAD SHA
 * so the next poll knows we're already at that commit and doesn't re-
 * offer the same update.
 */
add_action( 'upgrader_process_complete', function( $upgrader, $hook_extra ) {
	if ( ! defined( 'SN_GITHUB_TOKEN' ) || empty( SN_GITHUB_TOKEN ) ) {
		return;
	}
	if ( empty( $hook_extra['type'] ) || 'theme' !== $hook_extra['type'] ) {
		return;
	}
	// WP passes either `themes` (bulk) or `theme` (single) — handle both.
	$themes = ! empty( $hook_extra['themes'] )
		? (array) $hook_extra['themes']
		: ( ! empty( $hook_extra['theme'] ) ? array( $hook_extra['theme'] ) : array() );
	if ( ! in_array( SN_THEME_SLUG, $themes, true ) ) {
		return;
	}

	$branch = sn_updater_branch();

	// Re-fetch the branch HEAD instead of trusting the transient (which may be
	// 5 min stale or pre-date this install). Stash the resulting SHA so the
	// next poll knows we're in sync.
	$response = wp_remote_get(
		'https://api.github.com/repos/' . SN_GITHUB_REPO . '/commits/' . rawurlencode( $branch ),
		array(
			'headers' => array(
				'Authorization' => 'token ' . SN_GITHUB_TOKEN,
				'Accept'        => 'application/vnd.github.v3+json',
			),
			'timeout' => 10,
		)
	);
	if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
		return;
	}
	$body = json_decode( wp_remote_retrieve_body( $response ), true );
	if ( ! empty( $body['sha'] ) ) {
		update_option( 'sn_github_local_sha', substr( $body['sha'], 0, 7 ) );
		set_transient( 'sn_github_branch_' . sanitize_key( $branch ), $body, 5 * MINUTE_IN_SECONDS );
	}
}, 10, 2 );

/**
 * Force-run template self-heal immediately after a successful theme
 * update.
 *
 * The diagnostic signature this addresses: a deploy completes
 * (`upgrader_process_complete` fires, SHA gets stored, asset mtimes
 * advance, every-other-route renders new content), but ONE specific
 * template file silently doesn't get overwritten on disk. Cause is
 * usually a stale file lock or permission glitch on Cloudways. The
 * symptom is invisible until someone notices a route still serves old
 * markup.
 *
 * Self-heal already exists to catch this on ambient admin pageviews,
 * but its 5-minute rate limit means recovery isn't immediate after an
 * Update click. Worse, if a previous broken self-heal run set the
 * rate-limit option, the FIXED version is locked out for up to 5 min.
 *
 * Force-running here closes the loop: every Update click now ends with
 * a verified file-content sync. Priority 20 so we run AFTER the
 * SHA-stash hook at priority 10 — order matters because self-heal logs
 * an admin notice the user should see right after the Update success
 * message.
 */
add_action( 'upgrader_process_complete', function( $upgrader, $hook_extra ) {
	if ( empty( $hook_extra['type'] ) || 'theme' !== $hook_extra['type'] ) {
		return;
	}
	$themes = ! empty( $hook_extra['themes'] )
		? (array) $hook_extra['themes']
		: ( ! empty( $hook_extra['theme'] ) ? array( $hook_extra['theme'] ) : array() );
	if ( ! in_array( SN_THEME_SLUG, $themes, true ) ) {
		return;
	}
	if ( ! function_exists( 'sn_self_heal_force_run' ) ) {
		return;
	}
	sn_self_heal_force_run();
}, 20, 2 );

/**
 * Inject GitHub token into the download request so WP can fetch
 * the zipball from a private repo.
 */
add_filter( 'http_request_args', function( $args, $url ) {
	if ( defined( 'SN_GITHUB_TOKEN' ) && strpos( $url, 'api.github.com/repos/' . SN_GITHUB_REPO ) !== false ) {
		$args['headers']['Authorization'] = 'token ' . SN_GITHUB_TOKEN;
		$args['headers']['Accept']        = 'application/vnd.github.v3+json';
	}
	return $args;
}, 10, 2 );

/**
 * Rename the extracted folder to signal-and-noise.
 * Fixes the -1 folder problem for both GitHub zipball downloads
 * (juanlentino-signal-and-noise-HASH/) and manual zip uploads.
 */
add_filter( 'upgrader_source_selection', function( $source, $remote_source, $upgrader ) {
	// Only act on theme installations/updates.
	if ( ! $upgrader instanceof Theme_Upgrader ) {
		return $source;
	}

	// Check if the extracted folder contains our theme.
	$style = trailingslashit( $source ) . 'style.css';
	if ( ! file_exists( $style ) ) {
		return $source;
	}

	$theme_data = get_file_data( $style, array( 'Name' => 'Theme Name' ) );
	if ( empty( $theme_data['Name'] ) || false === strpos( $theme_data['Name'], 'Signal' ) ) {
		return $source;
	}

	$corrected = trailingslashit( $remote_source ) . SN_THEME_SLUG . '/';
	if ( $source === $corrected ) {
		return $source;
	}

	if ( @rename( $source, $corrected ) ) {
		return $corrected;
	}

	return $source;
}, 10, 3 );

/**
 * Clear the GitHub branch + WP theme-update caches ONLY when the user
 * explicitly clicked "Check Again" (?force-check=1) on Dashboard →
 * Updates. The previous version cleared on every visit to update-core,
 * which had a nasty side effect: it nuked WP's `update_themes` transient
 * BEFORE the page rendered, leaving `list_theme_updates()` to read an
 * empty transient and output "Your themes are all up to date" — even
 * when our filter would have populated a real update offer.
 *
 * Themes page (`load-themes.php`) doesn't have the same hook, so it
 * triggered `_maybe_update_themes()` → `wp_update_themes()` → our filter
 * → response set → update visible. The discrepancy ("update shows in
 * theme picker but not on Updates page") was purely this hook's
 * unconditional clear.
 *
 * Force-check path is unchanged: WP itself calls `wp_update_themes()`
 * after this hook fires, which will repopulate the transient via our
 * filter with fresh data.
 */
add_action( 'load-update-core.php', function() {
	if ( empty( $_GET['force-check'] ) ) {
		return;
	}
	$branch = sanitize_key( sn_updater_branch() );
	delete_transient( 'sn_github_error' );
	delete_transient( 'sn_github_branch_' . $branch );
	delete_transient( 'sn_github_remote_version_' . $branch );
	// Revcount cache key now includes the base version (e.g.
	// sn_github_revcount_main_6.5.5 vs ..._7.0.0) so concurrent bases during
	// a Version bump transition don't alias. Clear all variants for this
	// branch with a LIKE delete — covers both the legacy form and the
	// version-suffixed form.
	global $wpdb;
	if ( $wpdb ) {
		$wpdb->query( $wpdb->prepare(
			"DELETE FROM {$wpdb->options}
			 WHERE option_name LIKE %s
			    OR option_name LIKE %s",
			$wpdb->esc_like( '_transient_sn_github_revcount_' . $branch ) . '%',
			$wpdb->esc_like( '_transient_timeout_sn_github_revcount_' . $branch ) . '%'
		) );
	}
	// Clear WP's own theme-update site transient too — without this, WP keeps
	// serving frozen update info from a previous filter run and never re-runs
	// our pre_set_site_transient_update_themes filter, so the displayed SHA
	// stays stale even after our custom transients are cleared.
	delete_site_transient( 'update_themes' );
} );

/**
 * Surface GitHub auto-updater state in the admin UI — but only when
 * there's something the maintainer needs to act on.
 *
 * The previous design also rendered a persistent "Tracking branch X at
 * <sha>" info notice on every admin page load. That was noise: when
 * state is healthy and an update IS pending, WP's native update row
 * already carries everything (the synthetic version label exposes the
 * SHA, rev count, and branch). When there's nothing to do, an "all is
 * well" banner is just clutter.
 *
 * What we render here:
 *   - Missing token: warning, every screen we run on.
 *   - Last API error: error notice, every screen.
 *
 * What we DON'T render:
 *   - "Tracking branch main at <sha>" tracking-state info — gone.
 *     If you want to inspect that, look at WP's native update row when
 *     an update is offered, or read sn_github_local_sha directly.
 */
add_action( 'admin_notices', function() {
	if ( ! current_user_can( 'update_themes' ) ) {
		return;
	}

	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || ! in_array( $screen->id, array( 'dashboard', 'update-core', 'themes' ), true ) ) {
		return;
	}

	$has_token = defined( 'SN_GITHUB_TOKEN' ) && ! empty( SN_GITHUB_TOKEN );
	if ( ! $has_token ) {
		echo '<div class="notice notice-warning"><p><strong>Signal &amp; Noise:</strong> Theme auto-updates are disabled. Define <code>SN_GITHUB_TOKEN</code> in <code>wp-config.php</code> (fine-grained PAT with <em>contents: read</em> on <code>' . esc_html( SN_GITHUB_REPO ) . '</code>) to re-enable.</p></div>';
		return;
	}

	$last_error = get_transient( 'sn_github_error' );
	if ( $last_error ) {
		echo '<div class="notice notice-error"><p><strong>Signal &amp; Noise:</strong> GitHub check failed — ' . esc_html( $last_error ) . '. Token may have expired, lost access to the repo, or GitHub is rate-limiting.</p></div>';
	}
} );
