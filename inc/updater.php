<?php
/**
 * Signal & Noise — GitHub self-updater.
 *
 * Polls GitHub releases for new versions and plugs into WordPress's native
 * theme-update system. Click "Update" in Appearance → Themes and it behaves
 * like any other theme update — no manual zip uploads.
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
 * Check GitHub for a newer release and inject it into WP's update system.
 */
add_filter( 'pre_set_site_transient_update_themes', function( $transient ) {
	if ( empty( $transient->checked ) ) {
		return $transient;
	}

	if ( ! defined( 'SN_GITHUB_TOKEN' ) || empty( SN_GITHUB_TOKEN ) ) {
		return $transient;
	}

	// Check cache first (12 hour TTL).
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
 * Clear the GitHub release cache when checking for updates manually.
 */
add_action( 'load-update-core.php', function() {
	delete_transient( 'sn_github_release' );
	delete_transient( 'sn_github_error' );
} );

/**
 * Surface GitHub auto-updater problems in the admin UI.
 *
 * Shown only to users who can update themes, only on Dashboard / Updates /
 * Themes screens so it lands where someone would look for an update.
 *
 * Two states:
 *   - Missing token: warning notice telling you to add SN_GITHUB_TOKEN.
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

	$last_error = get_transient( 'sn_github_error' );
	if ( $last_error ) {
		echo '<div class="notice notice-error"><p><strong>Signal &amp; Noise:</strong> GitHub release check failed — ' . esc_html( $last_error ) . '. Token may have expired, lost access to the repo, or GitHub is rate-limiting.</p></div>';
	}
} );
