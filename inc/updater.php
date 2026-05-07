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
 * @return int Commits ahead of the v{Version} tag, or 0 on failure.
 */
function sn_updater_revcount( $branch ) {
	if ( ! defined( 'SN_GITHUB_TOKEN' ) || empty( SN_GITHUB_TOKEN ) ) {
		return 0;
	}

	$cache_key = 'sn_github_revcount_' . sanitize_key( $branch );
	$cached    = get_transient( $cache_key );
	if ( false !== $cached ) {
		return (int) $cached;
	}

	$base = 'v' . wp_get_theme( SN_THEME_SLUG )->get( 'Version' );
	$response = wp_remote_get(
		'https://api.github.com/repos/' . SN_GITHUB_REPO . '/compare/' . rawurlencode( $base ) . '...' . rawurlencode( $branch ),
		array(
			'headers' => array(
				'Authorization' => 'token ' . SN_GITHUB_TOKEN,
				'Accept'        => 'application/vnd.github.v3+json',
			),
			'timeout' => 10,
		)
	);

	if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
		// Tag missing, API error, or rate-limited — cache 0 briefly so we
		// don't hammer the API on every page load.
		set_transient( $cache_key, 0, 5 * MINUTE_IN_SECONDS );
		return 0;
	}

	$body  = json_decode( wp_remote_retrieve_body( $response ), true );
	$ahead = isset( $body['ahead_by'] ) ? (int) $body['ahead_by'] : 0;
	set_transient( $cache_key, $ahead, 5 * MINUTE_IN_SECONDS );
	return $ahead;
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

	$branch        = sn_updater_branch();
	$transient_key = 'sn_github_branch_' . sanitize_key( $branch );
	$cached        = get_transient( $transient_key );

	if ( false === $cached ) {
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
			return $transient;
		}
		$code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			set_transient( 'sn_github_error', 'HTTP ' . (int) $code . ' from GitHub commits API (branch: ' . esc_html( $branch ) . ')', HOUR_IN_SECONDS );
			return $transient;
		}
		$cached = json_decode( wp_remote_retrieve_body( $response ), true );
		set_transient( $transient_key, $cached, 5 * MINUTE_IN_SECONDS );
		delete_transient( 'sn_github_error' );
	}

	$remote_sha7 = substr( $cached['sha'] ?? '', 0, 7 );
	$local_sha7  = (string) get_option( 'sn_github_local_sha', '' );

	if ( $remote_sha7 && $remote_sha7 !== $local_sha7 ) {
		$local_version = wp_get_theme( SN_THEME_SLUG )->get( 'Version' );
		$rev           = sn_updater_revcount( $branch );
		$rev_suffix    = $rev > 0 ? '-r' . $rev : '';
		$transient->response[ SN_THEME_SLUG ] = array(
			'theme'       => SN_THEME_SLUG,
			// Synthetic version label — the SHA-vs-stored check above is the
			// real gate; this is what WP shows in the "Update available" row
			// so a human can identify which commit is being installed. The
			// -rN suffix is the count of commits since the v{Version} tag,
			// giving a readable consecutive sequence between milestones.
			'new_version' => $local_version . $rev_suffix . '+' . $branch . '.' . $remote_sha7,
			'url'         => 'https://github.com/' . SN_GITHUB_REPO . '/tree/' . rawurlencode( $branch ),
			'package'     => 'https://api.github.com/repos/' . SN_GITHUB_REPO . '/zipball/' . rawurlencode( $branch ),
		);
	}

	return $transient;
} );

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
 * Clear the GitHub branch cache when checking for updates manually.
 * Visiting Dashboard → Updates forces a fresh poll of the tracked
 * branch's HEAD on the next page load.
 */
add_action( 'load-update-core.php', function() {
	$branch = sanitize_key( sn_updater_branch() );
	delete_transient( 'sn_github_error' );
	delete_transient( 'sn_github_branch_' . $branch );
	delete_transient( 'sn_github_revcount_' . $branch );
	// Clear WP's own theme-update site transient too — without this, WP keeps
	// serving frozen update info from a previous filter run and never re-runs
	// our pre_set_site_transient_update_themes filter, so the displayed SHA
	// stays stale even after our custom transients are cleared.
	delete_site_transient( 'update_themes' );
} );

/**
 * Surface GitHub auto-updater state in the admin UI.
 *
 * Shown only to users who can update themes, only on Dashboard / Updates
 * / Themes screens so it lands where someone would look for an update.
 *
 * Three states:
 *   - Missing token: warning notice telling you to add SN_GITHUB_TOKEN.
 *   - Active: info notice naming the branch + SHA being tracked.
 *   - Last API call failed: error notice with the HTTP/WP_Error message
 *     (captured in the updater transient hook above).
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

	$branch    = sn_updater_branch();
	$local_sha = (string) get_option( 'sn_github_local_sha', '' );
	$sha_label = $local_sha ? ' at <code>' . esc_html( $local_sha ) . '</code>' : '';
	$mode      = ( 'main' === $branch ) ? 'default' : 'override via SN_GITHUB_BRANCH';
	$rev       = sn_updater_revcount( $branch );
	$rev_label = $rev > 0 ? ' · <code>r' . (int) $rev . '</code> commits since the last tag' : '';
	echo '<div class="notice notice-info"><p><strong>Signal &amp; Noise:</strong> Tracking branch <code>' . esc_html( $branch ) . '</code>' . $sha_label . ' (' . $mode . ')' . $rev_label . '. Updates check the branch HEAD every 5 minutes; visit Dashboard → Updates to force a fresh poll.</p></div>';

	$last_error = get_transient( 'sn_github_error' );
	if ( $last_error ) {
		echo '<div class="notice notice-error"><p><strong>Signal &amp; Noise:</strong> GitHub check failed — ' . esc_html( $last_error ) . '. Token may have expired, lost access to the repo, or GitHub is rate-limiting.</p></div>';
	}
} );
