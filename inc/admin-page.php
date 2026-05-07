<?php
/**
 * Signal & Noise — Theme options admin page.
 *
 * Registers the Appearance → Signal & Noise submenu and renders a tabbed
 * interface that covers theme management without overflowing into a
 * single-page-of-everything:
 *
 *   - Dashboard      — status overview + the four maintenance actions
 *                      (full reset, clear overrides, purge caches,
 *                      check for updates).
 *   - Cloudflare     — token + zone configuration, status, manual
 *                      zone purge, last-purge timestamp.
 *   - Reading Time   — legacy reading-time-string cleanup tool
 *                      (preview + apply).
 *   - Links          — external service links.
 *
 * Modules contribute their per-tab content via dedicated action hooks
 * (`sn_admin_cloudflare_tab`, `sn_admin_reading_time_tab`) so each
 * subsystem keeps its UI code colocated with its logic.
 *
 * @package SignalNoise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin page: Signal & Noise Theme Options under Appearance menu.
 */
add_action( 'admin_menu', function() {
	add_theme_page(
		'Signal & Noise',
		'Signal & Noise',
		'manage_options',
		'sn-theme-options',
		'sn_theme_options_page'
	);
} );

function sn_theme_options_page() {
	$theme         = wp_get_theme( 'signal-and-noise' );
	$local_version = $theme->get( 'Version' );
	$notices       = array();
	$valid_tabs    = array( 'dashboard', 'cloudflare', 'reading-time', 'links' );
	$active_tab    = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'dashboard';
	if ( ! in_array( $active_tab, $valid_tabs, true ) ) {
		$active_tab = 'dashboard';
	}

	// Handle form actions.
	if ( isset( $_POST['sn_action'] ) && check_admin_referer( 'sn_theme_options_nonce' ) ) {
		$action = sanitize_text_field( $_POST['sn_action'] );

		if ( 'clear_overrides' === $action ) {
			$count = sn_clear_template_overrides();
			$notices[] = array( 'success', $count . ' database override(s) cleared. Site is reading from theme files.' );
		}

		if ( 'purge_caches' === $action ) {
			// Single source of truth for "purge everything" — see
			// sn_purge_all_caches() in inc/template-maintenance.php.
			// Skip template_overrides here so the button reads as
			// "purge caches", not "also delete admin Site Editor edits".
			sn_purge_all_caches( array( 'template_overrides' => false ) );
			$notices[] = array( 'success', 'All caches purged.' );
		}

		if ( 'check_updates' === $action ) {
			delete_transient( 'sn_github_error' );
			$branch = function_exists( 'sn_updater_branch' ) ? sanitize_key( sn_updater_branch() ) : 'main';
			delete_transient( 'sn_github_branch_' . $branch );
			delete_transient( 'sn_github_remote_version_' . $branch );
			// Revcount cache is keyed by branch + base_version so we LIKE-delete
			// all variants for this branch (covers both legacy and bumped forms).
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
			delete_site_transient( 'update_themes' );
			wp_clean_themes_cache();

			// Repopulate update_themes immediately. wp_update_themes() will run
			// our pre_set_site_transient_update_themes filter, which fetches the
			// fresh main HEAD and sets $transient->response if there's drift.
			// Without this call, Dashboard → Updates renders an empty transient
			// and falsely reports "all up to date" until the next cron run.
			wp_update_themes();

			$notices[] = array( 'info', 'Update check complete. Visit <a href="' . esc_url( admin_url( 'update-core.php' ) ) . '">Dashboard &rarr; Updates</a> to install pending updates.' );
		}

		if ( 'full_reset' === $action ) {
			// Full reset = purge everything including DB template overrides.
			delete_transient( 'sn_github_error' );
			$count = sn_purge_all_caches();
			$notices[] = array( 'success', 'Full reset: ' . $count . ' override(s) cleared + all caches purged.' );
		}
	}

	// Resolve "what's on GitHub" against the *tracked branch HEAD*, not the
	// latest release tag. The updater tracks main directly (since v6.5.4),
	// so the meaningful comparison is local_sha vs main HEAD. The previous
	// release-tag check produced stale "Up to date" results whenever the
	// maintainer iterated past a tag without bumping Version: — exactly
	// the workflow the architecture was redesigned to support.
	$branch       = function_exists( 'sn_updater_branch' ) ? sn_updater_branch() : 'main';
	$local_sha    = (string) get_option( 'sn_github_local_sha', '' );
	$remote_sha   = '';
	$github_url   = 'https://github.com/' . SN_GITHUB_REPO . '/tree/' . rawurlencode( $branch );
	$rev          = function_exists( 'sn_updater_revcount' ) ? (int) sn_updater_revcount( $branch ) : 0;

	if ( defined( 'SN_GITHUB_TOKEN' ) && ! empty( SN_GITHUB_TOKEN ) ) {
		$cache_key = 'sn_github_branch_' . sanitize_key( $branch );
		$cached    = get_transient( $cache_key );
		if ( ! $cached ) {
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
			if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
				$cached = json_decode( wp_remote_retrieve_body( $response ), true );
				set_transient( $cache_key, $cached, 5 * MINUTE_IN_SECONDS );
			}
		}
		if ( is_array( $cached ) && ! empty( $cached['sha'] ) ) {
			$remote_sha = substr( $cached['sha'], 0, 7 );
		}
	}

	$rev_suffix     = $rev > 0 ? '-r' . $rev : '';
	$github_version = $local_version . $rev_suffix . ( $remote_sha ? '+' . $branch . '.' . $remote_sha : '' );
	$is_up_to_date  = ! $remote_sha || ( $local_sha && $local_sha === $remote_sha );

	$overrides = get_posts( array( 'post_type' => array( 'wp_template', 'wp_template_part', 'wp_navigation' ), 'posts_per_page' => -1, 'post_status' => 'any' ) );
	$base_url  = admin_url( 'themes.php?page=sn-theme-options' );

	// ── PAGE SHELL ──
	echo '<div class="wrap">';
	echo '<h1 style="font-size:1.6em;margin-bottom:0.2em;">Signal &amp; Noise</h1>';
	echo '<p style="color:#666;margin-top:0;margin-bottom:1em;">Theme management and maintenance.</p>';

	// Notices.
	foreach ( $notices as $n ) {
		echo '<div class="notice notice-' . $n[0] . ' is-dismissible"><p>' . $n[1] . '</p></div>';
	}

	// ── TABS ──
	$tab_labels = array(
		'dashboard'    => 'Dashboard',
		'cloudflare'   => 'Cloudflare',
		'reading-time' => 'Reading Time',
		'links'        => 'Links',
	);
	echo '<nav class="nav-tab-wrapper" style="margin-bottom:1.5em;">';
	foreach ( $tab_labels as $slug => $label ) {
		$is_active = ( $slug === $active_tab );
		echo '<a href="' . esc_url( $base_url . '&tab=' . $slug ) . '" class="nav-tab' . ( $is_active ? ' nav-tab-active' : '' ) . '">' . esc_html( $label ) . '</a>';
	}
	echo '</nav>';

	// ════════════════════════════════════════
	// TAB: DASHBOARD
	// ════════════════════════════════════════
	if ( 'dashboard' === $active_tab ) {

		// ── STATUS ──
		echo '<h2 style="font-size:1.1em;margin-bottom:0.8em;">Status</h2>';
		echo '<table class="form-table" style="max-width:500px;">';
		$installed_label = $local_version . ( $local_sha ? ' <span style="color:#666;">at ' . esc_html( $local_sha ) . '</span>' : '' );
		echo '<tr><th style="width:180px;padding:8px 10px 8px 0;">Installed version</th><td style="padding:8px 0;"><code>' . $installed_label . '</code></td></tr>';
		echo '<tr><th style="padding:8px 10px 8px 0;">Latest on GitHub</th><td style="padding:8px 0;"><code>' . esc_html( $github_version ) . '</code>';
		if ( $is_up_to_date ) {
			echo ' <span style="color:#00a32a;">&#10003; Up to date</span>';
		} else {
			$gap_label = $rev > 0 ? ' (' . (int) $rev . ' commit' . ( $rev === 1 ? '' : 's' ) . ' on main since the last tag)' : '';
			echo ' <span style="color:#d63638;">&#9650; Update available</span>' . $gap_label;
		}
		echo '</td></tr>';
		echo '<tr><th style="padding:8px 10px 8px 0;">DB overrides</th><td style="padding:8px 0;">' . count( $overrides );
		if ( count( $overrides ) > 0 ) {
			echo ' <span style="color:#dba617;">&#9888; Reading from database, not theme files</span>';
		} else {
			echo ' <span style="color:#00a32a;">&#10003; Clean</span>';
		}
		echo '</td></tr>';
		echo '<tr><th style="padding:8px 10px 8px 0;">Self-updater</th><td style="padding:8px 0;">';
		echo defined( 'SN_GITHUB_TOKEN' ) ? '<span style="color:#00a32a;">&#10003; Connected</span>' : '<span style="color:#d63638;">&#10005; SN_GITHUB_TOKEN not set</span>';
		echo '</td></tr>';
		echo '</table>';

		if ( $overrides ) {
			echo '<details style="margin-top:0.5em;"><summary style="cursor:pointer;color:#2271b1;font-size:0.85em;">View override details</summary><ul style="margin:0.5em 0 0 1.5em;">';
			foreach ( $overrides as $tpl ) {
				echo '<li><code>' . esc_html( $tpl->post_type ) . '/' . esc_html( $tpl->post_name ) . '</code></li>';
			}
			echo '</ul></details>';
		}

		echo '<hr style="margin:1.5em 0;">';

		// ── ACTIONS ──
		echo '<h2 style="font-size:1.1em;margin-bottom:0.8em;">Actions</h2>';
		echo '<form method="post">';
		wp_nonce_field( 'sn_theme_options_nonce' );

		echo '<div style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-start;">';

		echo '<div style="background:#fff;border:1px solid #c3c4c7;border-radius:4px;padding:16px 20px;max-width:260px;">';
		echo '<strong style="display:block;margin-bottom:4px;">Full Reset</strong>';
		echo '<p style="color:#666;font-size:0.85em;margin:0 0 12px;">Clears all overrides and purges every cache. Use after theme updates.</p>';
		echo '<button type="submit" name="sn_action" value="full_reset" class="button button-primary">Run Full Reset</button>';
		echo '</div>';

		echo '<div style="background:#fff;border:1px solid #c3c4c7;border-radius:4px;padding:16px 20px;max-width:260px;">';
		echo '<strong style="display:block;margin-bottom:4px;">Clear Overrides</strong>';
		echo '<p style="color:#666;font-size:0.85em;margin:0 0 12px;">Removes template, template part, and navigation DB entries.</p>';
		echo '<button type="submit" name="sn_action" value="clear_overrides" class="button">Clear Overrides</button>';
		echo '</div>';

		echo '<div style="background:#fff;border:1px solid #c3c4c7;border-radius:4px;padding:16px 20px;max-width:260px;">';
		echo '<strong style="display:block;margin-bottom:4px;">Purge Caches</strong>';
		echo '<p style="color:#666;font-size:0.85em;margin:0 0 12px;">WP object cache, transients, Breeze page/minification, Varnish.</p>';
		echo '<button type="submit" name="sn_action" value="purge_caches" class="button">Purge All Caches</button>';
		echo '</div>';

		echo '<div style="background:#fff;border:1px solid #c3c4c7;border-radius:4px;padding:16px 20px;max-width:260px;">';
		echo '<strong style="display:block;margin-bottom:4px;">Check for Updates</strong>';
		echo '<p style="color:#666;font-size:0.85em;margin:0 0 12px;">Clears GitHub cache and checks for new versions now.</p>';
		echo '<button type="submit" name="sn_action" value="check_updates" class="button">Check Now</button>';
		echo '</div>';

		echo '</div>';
		echo '</form>';

		/**
		 * Legacy hook for backward compatibility. As of v7.0.x, modules
		 * should target their dedicated tab hooks instead:
		 *   - sn_admin_cloudflare_tab    (Cloudflare tab)
		 *   - sn_admin_reading_time_tab  (Reading Time tab)
		 * This action is kept firing on the Dashboard tab so any
		 * third-party additions land somewhere visible during the
		 * transition.
		 */
		do_action( 'sn_admin_dashboard_extras' );

	// ════════════════════════════════════════
	// TAB: CLOUDFLARE
	// ════════════════════════════════════════
	} elseif ( 'cloudflare' === $active_tab ) {

		/** Module-owned UI: see inc/cloudflare-purge.php. */
		do_action( 'sn_admin_cloudflare_tab' );

	// ════════════════════════════════════════
	// TAB: READING TIME
	// ════════════════════════════════════════
	} elseif ( 'reading-time' === $active_tab ) {

		/** Module-owned UI: see inc/reading-time.php. */
		do_action( 'sn_admin_reading_time_tab' );

	// ════════════════════════════════════════
	// TAB: LINKS
	// ════════════════════════════════════════
	} elseif ( 'links' === $active_tab ) {

		echo '<table class="form-table" style="max-width:500px;">';
		echo '<tr><th style="width:180px;padding:8px 10px 8px 0;">GitHub Repository</th><td style="padding:8px 0;"><a href="https://github.com/juanlentino/signal-and-noise" target="_blank" rel="noopener">juanlentino/signal-and-noise</a></td></tr>';
		echo '<tr><th style="padding:8px 10px 8px 0;">Release History</th><td style="padding:8px 0;"><a href="https://github.com/juanlentino/signal-and-noise/releases" target="_blank" rel="noopener">All releases</a></td></tr>';
		if ( '#' !== $github_url && ! $is_up_to_date ) {
			echo '<tr><th style="padding:8px 10px 8px 0;">Latest Release</th><td style="padding:8px 0;"><a href="' . esc_url( $github_url ) . '" target="_blank" rel="noopener">v' . esc_html( $github_version ) . ' release notes</a></td></tr>';
		}
		echo '<tr><th style="padding:8px 10px 8px 0;">Cloudflare</th><td style="padding:8px 0;"><a href="https://dash.cloudflare.com" target="_blank" rel="noopener">Cloudflare Dashboard</a></td></tr>';
		echo '<tr><th style="padding:8px 10px 8px 0;">Cloudways</th><td style="padding:8px 0;"><a href="https://platform.cloudways.com" target="_blank" rel="noopener">Cloudways Platform</a></td></tr>';
		echo '</table>';

	}

	echo '</div>'; // wrap
}
