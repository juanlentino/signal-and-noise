<?php
/**
 * Signal & Noise — Theme options admin page.
 *
 * Registers the Appearance → Signal & Noise submenu and renders a three-tab
 * interface (Dashboard / Analytics / Links) that covers:
 *   - Status & maintenance actions (clear overrides, purge caches, check
 *     for updates, full reset) — all form-posted with WP nonces.
 *   - Self-hosted Plausible analytics (aggregate metrics, time series
 *     chart, visitor map, 13 ranked breakdowns).
 *   - External service links (GitHub repo/releases, Plausible, Cloudflare,
 *     Cloudways).
 *
 * Assets are registered in inc/admin-assets.php and are auto-enqueued on
 * the 'appearance_page_sn-theme-options' screen. Chart/map rendering is
 * handled entirely client-side via the admin JS layer — PHP emits
 * containers carrying JSON payloads in data attributes.
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
	$valid_tabs    = array( 'dashboard', 'analytics', 'links' );
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
			wp_cache_flush();
			delete_site_transient( 'update_themes' );
			wp_clean_themes_cache();

			// Only purge transients we own (sn_*) — leaves plugin transients alone.
			global $wpdb;
			if ( $wpdb ) {
				$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_sn\\_%' OR option_name LIKE '\\_transient\\_timeout\\_sn\\_%'" );
			}

			// Trigger Breeze purge via its own action hooks.
			do_action( 'breeze_clear_all_cache' );
			do_action( 'breeze_clear_varnish' );

			$notices[] = array( 'success', 'All caches purged.' );
		}

		if ( 'check_updates' === $action ) {
			delete_transient( 'sn_github_release' );
			delete_transient( 'sn_github_error' );
			delete_site_transient( 'update_themes' );
			wp_clean_themes_cache();
			$notices[] = array( 'info', 'Update cache cleared. Visit <a href="' . esc_url( admin_url( 'update-core.php' ) ) . '">Dashboard &rarr; Updates</a> to check for new versions.' );
		}

		if ( 'full_reset' === $action ) {
			$count = sn_clear_template_overrides();
			wp_cache_flush();
			delete_site_transient( 'update_themes' );
			delete_transient( 'sn_github_release' );
			delete_transient( 'sn_github_error' );
			wp_clean_themes_cache();

			// Only purge transients we own (sn_*) — leaves plugin transients alone.
			global $wpdb;
			if ( $wpdb ) {
				$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_sn\\_%' OR option_name LIKE '\\_transient\\_timeout\\_sn\\_%'" );
			}

			do_action( 'breeze_clear_all_cache' );
			do_action( 'breeze_clear_varnish' );

			$notices[] = array( 'success', 'Full reset: ' . $count . ' override(s) cleared + all caches purged.' );
		}
	}

	// Get GitHub latest release.
	$github_version = 'Unknown';
	$github_url     = '#';
	if ( defined( 'SN_GITHUB_TOKEN' ) ) {
		$cached = get_transient( 'sn_github_release' );
		if ( $cached ) {
			$github_version = ltrim( $cached['tag_name'] ?? 'Unknown', 'v' );
			$github_url     = $cached['html_url'] ?? '#';
		} else {
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
			if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
				$data = json_decode( wp_remote_retrieve_body( $response ), true );
				set_transient( 'sn_github_release', $data, 12 * HOUR_IN_SECONDS );
				$github_version = ltrim( $data['tag_name'] ?? 'Unknown', 'v' );
				$github_url     = $data['html_url'] ?? '#';
			}
		}
	}

	$overrides     = get_posts( array( 'post_type' => array( 'wp_template', 'wp_template_part', 'wp_navigation' ), 'posts_per_page' => -1, 'post_status' => 'any' ) );
	$is_up_to_date = version_compare( $local_version, $github_version, '>=' );
	$base_url      = admin_url( 'themes.php?page=sn-theme-options' );

	// ── PAGE SHELL ──
	echo '<div class="wrap">';
	echo '<h1 style="font-size:1.6em;margin-bottom:0.2em;">Signal &amp; Noise</h1>';
	echo '<p style="color:#666;margin-top:0;margin-bottom:1em;">Theme management, maintenance, and analytics.</p>';

	// Notices.
	foreach ( $notices as $n ) {
		echo '<div class="notice notice-' . $n[0] . ' is-dismissible"><p>' . $n[1] . '</p></div>';
	}

	// ── TABS ──
	echo '<nav class="nav-tab-wrapper" style="margin-bottom:1.5em;">';
	echo '<a href="' . esc_url( $base_url . '&tab=dashboard' ) . '" class="nav-tab' . ( 'dashboard' === $active_tab ? ' nav-tab-active' : '' ) . '">Dashboard</a>';
	echo '<a href="' . esc_url( $base_url . '&tab=analytics' ) . '" class="nav-tab' . ( 'analytics' === $active_tab ? ' nav-tab-active' : '' ) . '">Analytics</a>';
	echo '<a href="' . esc_url( $base_url . '&tab=links' ) . '" class="nav-tab' . ( 'links' === $active_tab ? ' nav-tab-active' : '' ) . '">Links</a>';
	echo '</nav>';

	// ════════════════════════════════════════
	// TAB: DASHBOARD
	// ════════════════════════════════════════
	if ( 'dashboard' === $active_tab ) {

		// ── STATUS ──
		echo '<h2 style="font-size:1.1em;margin-bottom:0.8em;">Status</h2>';
		echo '<table class="form-table" style="max-width:500px;">';
		echo '<tr><th style="width:180px;padding:8px 10px 8px 0;">Installed version</th><td style="padding:8px 0;"><code>' . esc_html( $local_version ) . '</code></td></tr>';
		echo '<tr><th style="padding:8px 10px 8px 0;">Latest on GitHub</th><td style="padding:8px 0;"><code>' . esc_html( $github_version ) . '</code>';
		if ( $is_up_to_date ) {
			echo ' <span style="color:#00a32a;">&#10003; Up to date</span>';
		} else {
			echo ' <span style="color:#d63638;">&#9650; Update available</span>';
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

	// ════════════════════════════════════════
	// TAB: ANALYTICS
	// ════════════════════════════════════════
	} elseif ( 'analytics' === $active_tab ) {

		if ( ! defined( 'SN_PLAUSIBLE_KEY' ) ) {
			echo '<p style="color:#d63638;">SN_PLAUSIBLE_KEY not defined in wp-config.php. Analytics requires a Plausible API key.</p>';
		} else {

		// ── Date range ──
		$period     = isset( $_GET['sn_period'] ) ? sanitize_text_field( $_GET['sn_period'] ) : '30d';
		$valid      = array( '7d', '30d', '6mo', '12mo' );
		if ( ! in_array( $period, $valid, true ) ) $period = '30d';
		$labels     = array( '7d' => '7 Days', '30d' => '30 Days', '6mo' => '6 Months', '12mo' => '12 Months' );
		$cache_min  = ( $period === '7d' ) ? 5 : 15;

		echo '<div style="display:flex;align-items:center;gap:8px;margin-bottom:1.5em;">';
		foreach ( $labels as $p => $l ) {
			$is_active = ( $p === $period );
			$style = $is_active
				? 'background:#1d2327;color:#fff;border-color:#1d2327;'
				: 'background:#fff;color:#1d2327;';
			echo '<a href="' . esc_url( add_query_arg( 'sn_period', $p, $base_url . '&tab=analytics' ) ) . '" class="button" style="' . $style . '">' . $l . '</a>';
		}
		echo '<span style="margin-left:auto;font-size:0.8em;color:#787c82;">Cached ' . $cache_min . ' min &middot; <a href="' . esc_url( SN_PLAUSIBLE_URL . '/' . SN_PLAUSIBLE_SITE ) . '" target="_blank" rel="noopener">Open Plausible &rarr;</a></span>';
		echo '</div>';

		// ── Aggregate metrics ──
		$agg = sn_plausible_api( 'aggregate', array(
			'period'  => $period,
			'metrics' => 'visitors,visits,pageviews,views_per_visit,bounce_rate,visit_duration',
			'compare' => 'previous_period',
		), $cache_min );
		$r = $agg['results'] ?? array();

		echo '<div style="display:flex;flex-wrap:wrap;gap:12px;margin-bottom:1.5em;">';
		$metrics = array(
			array( 'Visitors',        'visitors',        'number' ),
			array( 'Visits',          'visits',           'number' ),
			array( 'Pageviews',       'pageviews',        'number' ),
			array( 'Views / Visit',   'views_per_visit',  'decimal' ),
			array( 'Bounce Rate',     'bounce_rate',      'percent' ),
			array( 'Visit Duration',  'visit_duration',   'duration' ),
		);
		foreach ( $metrics as $m ) {
			$val    = $r[ $m[1] ]['value'] ?? 0;
			$change = $r[ $m[1] ]['change'] ?? null;
			switch ( $m[2] ) {
				case 'number':   $display = sn_fmt( $val ); break;
				case 'decimal':  $display = number_format( $val, 1 ); break;
				case 'percent':  $display = $val . '%'; break;
				case 'duration': $display = sn_duration( $val ); break;
				default:         $display = $val;
			}
			$invert = ( $m[1] === 'bounce_rate' );
			$ch     = $change;
			if ( $invert && null !== $ch ) $ch = -$ch;
			$color  = '#787c82';
			$arrow  = '&#8212;';
			if ( null !== $ch && $ch > 0 ) { $color = '#00a32a'; $arrow = '&#9650;'; }
			if ( null !== $ch && $ch < 0 ) { $color = '#d63638'; $arrow = '&#9660;'; }
			echo '<div style="flex:1;min-width:120px;background:#fff;border:1px solid #c3c4c7;border-radius:4px;padding:16px;text-align:center;">';
			echo '<div style="font-size:1.6em;font-weight:700;color:#1d2327;">' . esc_html( $display ) . '</div>';
			echo '<div style="font-size:0.75em;color:#787c82;margin-top:4px;">' . esc_html( $m[0] ) . '</div>';
			if ( null !== $change ) {
				echo '<div style="font-size:0.7em;color:' . $color . ';margin-top:4px;">' . $arrow . ' ' . abs( $change ) . '%</div>';
			}
			echo '</div>';
		}
		echo '</div>';

		// ── Time series + Map row ──
		$ts = sn_plausible_api( 'timeseries', array(
			'period'  => $period,
			'metrics' => 'visitors,pageviews',
		), $cache_min );
		$ts_results = $ts['results'] ?? array();

		echo '<div style="display:flex;gap:16px;margin-bottom:1.5em;flex-wrap:wrap;">';

		// Chart
		echo '<div style="flex:2;min-width:400px;background:#fff;border:1px solid #c3c4c7;border-radius:4px;padding:16px;">';
		echo '<h3 style="margin:0 0 12px;font-size:0.9em;color:#1d2327;">Visitor Trend</h3>';

		$ts_labels = array();
		$ts_visitors = array();
		$ts_pageviews = array();
		foreach ( $ts_results as $point ) {
			$ts_labels[]    = $point['date'] ?? '';
			$ts_visitors[]  = $point['visitors'] ?? 0;
			$ts_pageviews[] = $point['pageviews'] ?? 0;
		}

		printf(
			'<div class="sn-chart-widget" data-sn-chart="%s" style="height:260px;"><canvas></canvas></div>',
			esc_attr( wp_json_encode( array(
				'labels'    => $ts_labels,
				'visitors'  => $ts_visitors,
				'pageviews' => $ts_pageviews,
			) ) )
		);
		echo '</div>';

		// Map
		$countries_map = sn_plausible_api( 'breakdown', array( 'period' => $period, 'property' => 'visit:country', 'metrics' => 'visitors', 'limit' => '100' ), $cache_min );
		$map_results   = $countries_map['results'] ?? array();

		echo '<div style="flex:1;min-width:300px;background:#fff;border:1px solid #c3c4c7;border-radius:4px;padding:16px;">';
		echo '<h3 style="margin:0 0 12px;font-size:0.9em;color:#1d2327;">Visitor Map</h3>';

		if ( empty( $map_results ) ) {
			echo '<p style="color:#787c82;font-size:0.85em;">No data yet.</p>';
		} else {
			$map_data = array();
			foreach ( $map_results as $c ) {
				$code = $c['country'] ?? '';
				if ( $code ) $map_data[ $code ] = $c['visitors'];
			}
			printf(
				'<div class="sn-map-widget" data-sn-map="%s" style="width:100%%;height:220px;overflow:hidden;position:relative;"></div>',
				esc_attr( wp_json_encode( $map_data ) )
			);
		}
		echo '</div>';
		echo '</div>';

		// ── Breakdowns ──
		$breakdowns = array(
			'pages'       => array( 'label' => 'Pages',        'property' => 'event:page',         'key' => 'page' ),
			'entry'       => array( 'label' => 'Entry Pages',  'property' => 'visit:entry_page',   'key' => 'entry_page' ),
			'exit'        => array( 'label' => 'Exit Pages',   'property' => 'visit:exit_page',    'key' => 'exit_page' ),
			'sources'     => array( 'label' => 'Sources',      'property' => 'visit:source',       'key' => 'source' ),
			'referrers'   => array( 'label' => 'Referrers',    'property' => 'visit:referrer',     'key' => 'referrer' ),
			'utm_medium'  => array( 'label' => 'UTM Medium',   'property' => 'visit:utm_medium',   'key' => 'utm_medium' ),
			'utm_source'  => array( 'label' => 'UTM Source',   'property' => 'visit:utm_source',   'key' => 'utm_source' ),
			'utm_campaign'=> array( 'label' => 'UTM Campaign', 'property' => 'visit:utm_campaign', 'key' => 'utm_campaign' ),
			'countries'   => array( 'label' => 'Countries',    'property' => 'visit:country',      'key' => 'country' ),
			'cities'      => array( 'label' => 'Cities',       'property' => 'visit:city',         'key' => 'city' ),
			'devices'     => array( 'label' => 'Devices',      'property' => 'visit:device',       'key' => 'device' ),
			'browsers'    => array( 'label' => 'Browsers',     'property' => 'visit:browser',      'key' => 'browser' ),
			'os'          => array( 'label' => 'OS',           'property' => 'visit:os',           'key' => 'os' ),
		);

		echo '<div style="background:#fff;border:1px solid #c3c4c7;border-radius:4px;padding:16px;">';
		echo '<h3 style="margin:0 0 12px;font-size:0.9em;color:#1d2327;">Breakdowns</h3>';

		$bd_uid = 'sn_bd_' . wp_rand();

		echo '<div class="sn-tabs" role="tablist" style="display:flex;flex-wrap:wrap;border-bottom:1px solid #c3c4c7;margin-bottom:12px;gap:0;">';
		$first = true;
		foreach ( $breakdowns as $id => $bd ) {
			$tab_id   = $bd_uid . '_tab_' . $id;
			$panel_id = $bd_uid . '_panel_' . $id;
			$style    = 'padding:8px 12px;cursor:pointer;font-size:0.8em;font-weight:500;white-space:nowrap;font-family:inherit;background:transparent;border:0;border-bottom:2px solid ' . ( $first ? '#e00404' : 'transparent' ) . ';color:' . ( $first ? '#1d2327' : '#787c82' ) . ';';
			printf(
				'<button type="button" role="tab" id="%s" aria-controls="%s" aria-selected="%s" tabindex="%s" style="%s">%s</button>',
				esc_attr( $tab_id ),
				esc_attr( $panel_id ),
				$first ? 'true' : 'false',
				$first ? '0' : '-1',
				esc_attr( $style ),
				esc_html( $bd['label'] )
			);
			$first = false;
		}
		echo '</div>';

		$first = true;
		foreach ( $breakdowns as $id => $bd ) {
			$tab_id   = $bd_uid . '_tab_' . $id;
			$panel_id = $bd_uid . '_panel_' . $id;
			$data = sn_plausible_api( 'breakdown', array(
				'period'   => $period,
				'property' => $bd['property'],
				'metrics'  => 'visitors',
				'limit'    => '15',
			), $cache_min );
			printf(
				'<div id="%s" role="tabpanel" aria-labelledby="%s"%s>',
				esc_attr( $panel_id ),
				esc_attr( $tab_id ),
				$first ? '' : ' hidden'
			);
			sn_ranked_list( $data['results'] ?? array(), $bd['key'] );
			echo '</div>';
			$first = false;
		}

		echo '</div>';

		} // end SN_PLAUSIBLE_KEY check

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
		echo '<tr><th style="padding:8px 10px 8px 0;">Plausible Dashboard</th><td style="padding:8px 0;"><a href="' . esc_url( SN_PLAUSIBLE_URL . '/' . SN_PLAUSIBLE_SITE ) . '" target="_blank" rel="noopener">Open in Plausible</a></td></tr>';
		echo '<tr><th style="padding:8px 10px 8px 0;">Cloudflare</th><td style="padding:8px 0;"><a href="https://dash.cloudflare.com" target="_blank" rel="noopener">Cloudflare Dashboard</a></td></tr>';
		echo '<tr><th style="padding:8px 10px 8px 0;">Cloudways</th><td style="padding:8px 0;"><a href="https://platform.cloudways.com" target="_blank" rel="noopener">Cloudways Platform</a></td></tr>';
		echo '</table>';

	}

	echo '</div>'; // wrap
}
