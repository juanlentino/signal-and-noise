<?php
/**
 * Signal & Noise — WordPress Dashboard widgets.
 *
 * Four native Plausible-backed dashboard widgets:
 *   - Visitors Today (realtime + today vs. yesterday)
 *   - Visitor Trend (30 day sparkline)
 *   - Top Stats (tabbed: pages / sources / countries / devices / browsers)
 *   - Visitor Map (jsvectormap world heatmap)
 *
 * All data comes from sn_plausible_api() in inc/plausible-api.php.
 * All JS/SVG rendering is handled by the admin JS layer in
 * inc/admin-assets.php — PHP only emits containers with data attributes.
 *
 * @package SignalNoise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'wp_dashboard_setup', function() {
	if ( ! defined( 'SN_PLAUSIBLE_KEY' ) ) return;

	// ── VISITORS TODAY ──
	wp_add_dashboard_widget( 'sn_pa_overview', 'Visitors Today', function() {
		$realtime  = sn_plausible_api( 'realtime/visitors', array(), 1 );
		$today     = sn_plausible_api( 'aggregate', array( 'period' => 'day', 'metrics' => 'visitors,pageviews,bounce_rate,visit_duration' ), 5 );
		$yesterday = sn_plausible_api( 'aggregate', array( 'period' => 'custom', 'date' => date('Y-m-d', strtotime('-1 day')) . ',' . date('Y-m-d', strtotime('-1 day')), 'metrics' => 'visitors,pageviews' ), 60 );

		$rv = is_numeric( $realtime ) ? $realtime : ( $realtime['results'] ?? 0 );
		echo '<div style="text-align:center;padding:8px 0 12px;border-bottom:1px solid #f0f0f0;margin-bottom:10px;">';
		echo '<div style="font-size:2em;font-weight:700;color:#1d2327;">' . intval( $rv ) . '</div>';
		echo '<div style="font-size:0.75em;color:#e00404;font-weight:500;">ONLINE NOW</div></div>';

		if ( $today && isset( $today['results'] ) ) {
			$r = $today['results'];
			$yv = $yesterday['results']['visitors']['value'] ?? 0;
			$yp = $yesterday['results']['pageviews']['value'] ?? 0;
			$tv = $r['visitors']['value'] ?? 0;
			$tp = $r['pageviews']['value'] ?? 0;
			$cv = $yv > 0 ? round((($tv-$yv)/$yv)*100) : null;
			$cp = $yp > 0 ? round((($tp-$yp)/$yp)*100) : null;
			echo '<div style="display:flex;flex-wrap:wrap;">';
			sn_metric_card( 'Visitors', sn_fmt($tv), $cv );
			sn_metric_card( 'Pageviews', sn_fmt($tp), $cp );
			sn_metric_card( 'Bounce', ($r['bounce_rate']['value'] ?? 0).'%' );
			sn_metric_card( 'Duration', sn_duration($r['visit_duration']['value'] ?? 0) );
			echo '</div>';
		}
		echo '<p style="margin:10px 0 0;font-size:0.8em;text-align:right;"><a href="' . esc_url( SN_PLAUSIBLE_URL . '/' . SN_PLAUSIBLE_SITE ) . '" target="_blank" rel="noopener">Full dashboard &rarr;</a></p>';
	} );

	// ── 30-DAY TREND ──
	wp_add_dashboard_widget( 'sn_pa_trend', 'Visitor Trend (30 days)', function() {
		$data = sn_plausible_api( 'timeseries', array( 'period' => '30d', 'metrics' => 'visitors' ), 30 );
		$results = $data['results'] ?? array();
		if ( empty( $results ) ) { echo '<p style="color:#787c82;font-size:0.85em;">No data yet.</p>'; return; }

		$max = max( array_column( $results, 'visitors' ) );
		$max = $max > 0 ? $max : 1;
		$total = array_sum( array_column( $results, 'visitors' ) );

		echo '<div style="margin-bottom:8px;font-size:0.85em;color:#1d2327;"><strong>' . sn_fmt( $total ) . '</strong> <span style="color:#787c82;">visitors in the last 30 days</span></div>';
		echo '<div style="display:flex;align-items:flex-end;height:80px;gap:2px;">';
		foreach ( $results as $day ) {
			$v = $day['visitors'];
			$h = max( round( ( $v / $max ) * 100 ), 2 );
			$date = date( 'M j', strtotime( $day['date'] ) );
			echo '<div title="' . esc_attr( $date . ': ' . $v . ' visitors' ) . '" style="flex:1;height:' . $h . '%;background:#e00404;border-radius:1px;min-width:4px;opacity:' . ( $v > 0 ? '1' : '0.2' ) . ';"></div>';
		}
		echo '</div>';
		echo '<div style="display:flex;justify-content:space-between;font-size:0.7em;color:#787c82;margin-top:4px;">';
		echo '<span>' . date( 'M j', strtotime( $results[0]['date'] ) ) . '</span>';
		echo '<span>' . date( 'M j', strtotime( end( $results )['date'] ) ) . '</span>';
		echo '</div>';
	} );

	// ── TOP STATS (TABBED) ──
	wp_add_dashboard_widget( 'sn_pa_topstats', 'Top Stats (30 days)', function() {
		$pages     = sn_plausible_api( 'breakdown', array( 'period' => '30d', 'property' => 'event:page', 'metrics' => 'visitors', 'limit' => '10' ), 15 );
		$sources   = sn_plausible_api( 'breakdown', array( 'period' => '30d', 'property' => 'visit:source', 'metrics' => 'visitors', 'limit' => '10' ), 15 );
		$countries = sn_plausible_api( 'breakdown', array( 'period' => '30d', 'property' => 'visit:country', 'metrics' => 'visitors', 'limit' => '10' ), 15 );
		$devices   = sn_plausible_api( 'breakdown', array( 'period' => '30d', 'property' => 'visit:device', 'metrics' => 'visitors', 'limit' => '5' ), 15 );
		$browsers  = sn_plausible_api( 'breakdown', array( 'period' => '30d', 'property' => 'visit:browser', 'metrics' => 'visitors', 'limit' => '5' ), 15 );

		$tabs = array(
			'pages'     => array( 'label' => 'Pages',     'data' => $pages['results'] ?? array(),     'key' => 'page' ),
			'sources'   => array( 'label' => 'Sources',   'data' => $sources['results'] ?? array(),   'key' => 'source' ),
			'countries' => array( 'label' => 'Countries', 'data' => $countries['results'] ?? array(), 'key' => 'country' ),
			'devices'   => array( 'label' => 'Devices',   'data' => $devices['results'] ?? array(),   'key' => 'device' ),
			'browsers'  => array( 'label' => 'Browsers',  'data' => $browsers['results'] ?? array(),  'key' => 'browser' ),
		);

		$uid = 'sn_ts_' . wp_rand();
		echo '<div class="sn-tabs" role="tablist" style="display:flex;border-bottom:1px solid #c3c4c7;margin-bottom:12px;">';
		$first = true;
		foreach ( $tabs as $id => $tab ) {
			$tab_id   = $uid . '_tab_' . $id;
			$panel_id = $uid . '_panel_' . $id;
			$style    = 'flex:1;text-align:center;padding:8px 4px;cursor:pointer;font-size:0.8em;font-weight:500;font-family:inherit;background:transparent;border:0;border-bottom:2px solid ' . ( $first ? '#e00404' : 'transparent' ) . ';color:' . ( $first ? '#1d2327' : '#787c82' ) . ';';
			printf(
				'<button type="button" role="tab" id="%s" aria-controls="%s" aria-selected="%s" tabindex="%s" style="%s">%s</button>',
				esc_attr( $tab_id ),
				esc_attr( $panel_id ),
				$first ? 'true' : 'false',
				$first ? '0' : '-1',
				esc_attr( $style ),
				esc_html( $tab['label'] )
			);
			$first = false;
		}
		echo '</div>';

		$first = true;
		foreach ( $tabs as $id => $tab ) {
			$tab_id   = $uid . '_tab_' . $id;
			$panel_id = $uid . '_panel_' . $id;
			printf(
				'<div id="%s" role="tabpanel" aria-labelledby="%s"%s>',
				esc_attr( $panel_id ),
				esc_attr( $tab_id ),
				$first ? '' : ' hidden'
			);
			sn_ranked_list( $tab['data'], $tab['key'] );
			echo '</div>';
			$first = false;
		}
	} );

	// ── VISITOR MAP ──
	wp_add_dashboard_widget( 'sn_pa_map', 'Visitor Map (30 days)', function() {
		$countries = sn_plausible_api( 'breakdown', array( 'period' => '30d', 'property' => 'visit:country', 'metrics' => 'visitors', 'limit' => '100' ), 30 );
		$results = $countries['results'] ?? array();

		if ( empty( $results ) ) { echo '<p style="color:#787c82;font-size:0.85em;">No data yet.</p>'; return; }

		$map_data = array();
		foreach ( $results as $c ) {
			$code = $c['country'] ?? '';
			if ( $code ) $map_data[ $code ] = $c['visitors'];
		}

		printf(
			'<div class="sn-map-widget" data-sn-map="%s" style="width:100%%;height:300px;overflow:hidden;position:relative;"></div>',
			esc_attr( wp_json_encode( $map_data ) )
		);
	} );
} );
