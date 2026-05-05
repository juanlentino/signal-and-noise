<?php
/**
 * Signal & Noise — GitHub self-updater.
 *
 * Two modes — picked automatically with no config change required.
 *
 * 1. AUTO DEV MODE.
 *    If a `dev` branch exists on the remote, the updater tracks its HEAD
 *    commit SHA instead of tracking release tags. Push commits to `dev`
 *    freely; WP polls every 5 minutes; "Update" pulls the branch zipball —
 *    no version bump, no GitHub release per iteration. When work is final,
 *    merge `dev` → `main` and DELETE the dev branch; the next poll detects
 *    the absence of `dev` and falls back to release mode automatically.
 *
 * 2. RELEASE MODE (fallback).
 *    No `dev` branch on the remote → polls `/releases/latest` for new tags
 *    and behaves like any other theme update.
 *
 * The legacy `SN_GITHUB_BRANCH` constant in wp-config.php still works as an
 * explicit override (track an arbitrary branch by name), but is no longer
 * required to enable dev mode. Most users should leave it undefined.
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
 * Check GitHub for a newer release (or branch HEAD in dev mode) and inject
 * it into WP's update system.
 */
add_filter( 'pre_set_site_transient_update_themes', function( $transient ) {
	if ( empty( $transient->checked ) ) {
		return $transient;
	}

	if ( ! defined( 'SN_GITHUB_TOKEN' ) || empty( SN_GITHUB_TOKEN ) ) {
		return $transient;
	}

	$branch = defined( 'SN_GITHUB_BRANCH' ) && SN_GITHUB_BRANCH ? SN_GITHUB_BRANCH : null;

	// Auto-detect: if no explicit override, check whether a `dev` branch
	// exists on the remote. If yes, track it; if no, fall through to
	// release-tag mode below. The detection is cached for 5 min to keep
	// API hits low, and times out automatically when the branch is
	// deleted at ship time.
	if ( ! $branch ) {
		$auto_dev = get_transient( 'sn_github_dev_exists' );
		if ( false === $auto_dev ) {
			$check = wp_remote_get(
				'https://api.github.com/repos/' . SN_GITHUB_REPO . '/branches/dev',
				array(
					'headers' => array(
						'Authorization' => 'token ' . SN_GITHUB_TOKEN,
						'Accept'        => 'application/vnd.github.v3+json',
					),
					'timeout' => 10,
				)
			);
			$auto_dev = ( ! is_wp_error( $check ) && 200 === wp_remote_retrieve_response_code( $check ) ) ? '1' : '0';
			set_transient( 'sn_github_dev_exists', $auto_dev, 5 * MINUTE_IN_SECONDS );
		}
		if ( '1' === $auto_dev ) {
			$branch = 'dev';
		}
	}

	// === DEV MODE — track branch HEAD by commit SHA ===
	if ( $branch ) {
		$transient_key = 'sn_github_branch_' . sanitize_key( $branch );
		$cached = get_transient( $transient_key );
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
			$transient->response[ SN_THEME_SLUG ] = array(
				'theme'       => SN_THEME_SLUG,
				// Synthetic version — the SHA-vs-stored-SHA check above is the real
				// gate; this string is just what WP shows in the "Update available"
				// row so a human can tell which commit is being installed.
				'new_version' => $local_version . '+' . $branch . '.' . $remote_sha7,
				'url'         => 'https://github.com/' . SN_GITHUB_REPO . '/tree/' . rawurlencode( $branch ),
				'package'     => 'https://api.github.com/repos/' . SN_GITHUB_REPO . '/zipball/' . rawurlencode( $branch ),
			);
		}
		return $transient;
	}

	// === RELEASE MODE — track latest release by tag ===
	$cached = get_transient( 'sn_github_release' );
	if ( false === $cached ) {
		$response = wp_remote_get(
			'https://api.github.com/repos/' . SN_GITHUB_REPO . '/releases/latest',
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
			set_transient( 'sn_github_error', 'HTTP ' . (int) $code . ' from GitHub API', HOUR_IN_SECONDS );
			return $transient;
		}

		$cached = json_decode( wp_remote_retrieve_body( $response ), true );
		set_transient( 'sn_github_release', $cached, 12 * HOUR_IN_SECONDS );
		delete_transient( 'sn_github_error' );
	}

	$remote_version = ltrim( $cached['tag_name'] ?? '', 'v' );
	$local_version  = wp_get_theme( SN_THEME_SLUG )->get( 'Version' );

	if ( version_compare( $remote_version, $local_version, '>' ) ) {
		$transient->response[ SN_THEME_SLUG ] = array(
			'theme'       => SN_THEME_SLUG,
			'new_version' => $remote_version,
			'url'         => $cached['html_url'] ?? '',
			'package'     => 'https://api.github.com/repos/' . SN_GITHUB_REPO . '/zipball/' . $cached['tag_name'],
		);
	}

	return $transient;
} );

/**
 * Dev mode: after a successful theme upgrade, store the branch HEAD SHA so
 * the next poll knows we're already at that commit and doesn't re-offer the
 * same update.
 */
add_action( 'upgrader_process_complete', function( $upgrader, $hook_extra ) {
	if ( ! defined( 'SN_GITHUB_BRANCH' ) || ! SN_GITHUB_BRANCH ) {
		return;
	}
	if ( empty( $hook_extra['type'] ) || 'theme' !== $hook_extra['type'] ) {
		return;
	}
	if ( empty( $hook_extra['themes'] ) || ! in_array( SN_THEME_SLUG, (array) $hook_extra['themes'], true ) ) {
		return;
	}

	$cached = get_transient( 'sn_github_branch_' . sanitize_key( SN_GITHUB_BRANCH ) );
	if ( $cached && ! empty( $cached['sha'] ) ) {
		update_option( 'sn_github_local_sha', substr( $cached['sha'], 0, 7 ) );
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
 * Clear the GitHub release / branch cache when checking for updates manually.
 */
add_action( 'load-update-core.php', function() {
	delete_transient( 'sn_github_release' );
	delete_transient( 'sn_github_error' );
	delete_transient( 'sn_github_dev_exists' );
	delete_transient( 'sn_github_branch_dev' );
	if ( defined( 'SN_GITHUB_BRANCH' ) && SN_GITHUB_BRANCH ) {
		delete_transient( 'sn_github_branch_' . sanitize_key( SN_GITHUB_BRANCH ) );
	}
} );

/**
 * Surface GitHub auto-updater state in the admin UI.
 *
 * Shown only to users who can update themes, only on Dashboard / Updates /
 * Themes screens so it lands where someone would look for an update.
 *
 * Three states:
 *   - Missing token: warning notice telling you to add SN_GITHUB_TOKEN.
 *   - Dev mode active: info notice naming the branch + SHA being tracked.
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

	$explicit_branch = defined( 'SN_GITHUB_BRANCH' ) && SN_GITHUB_BRANCH ? SN_GITHUB_BRANCH : null;
	$auto_dev_active = ( ! $explicit_branch ) && '1' === get_transient( 'sn_github_dev_exists' );

	if ( $explicit_branch || $auto_dev_active ) {
		$branch    = $explicit_branch ?: 'dev';
		$local_sha = (string) get_option( 'sn_github_local_sha', '' );
		$sha_label = $local_sha ? ' at <code>' . esc_html( $local_sha ) . '</code>' : '';
		$mode      = $explicit_branch ? 'explicit override' : 'auto-detected — branch will disappear when merged + deleted';
		echo '<div class="notice notice-info"><p><strong>Signal &amp; Noise — Dev Mode:</strong> Tracking branch <code>' . esc_html( $branch ) . '</code>' . $sha_label . ' (' . $mode . '). Updates check the branch HEAD every 5 minutes.</p></div>';
	}

	$last_error = get_transient( 'sn_github_error' );
	if ( $last_error ) {
		echo '<div class="notice notice-error"><p><strong>Signal &amp; Noise:</strong> GitHub release check failed — ' . esc_html( $last_error ) . '. Token may have expired, lost access to the repo, or GitHub is rate-limiting.</p></div>';
	}
} );
