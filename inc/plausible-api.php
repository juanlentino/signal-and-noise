<?php
/**
 * Signal & Noise — Plausible Stats API client.
 *
 * Talks to the self-hosted Plausible CE instance on Railway and returns
 * transient-cached results. All admin-side visitor widgets route through
 * sn_plausible_api() — there is no direct wp_remote_get in widget code.
 *
 * Constants:
 *   SN_PLAUSIBLE_URL  — base URL of the Plausible instance (this file).
 *   SN_PLAUSIBLE_SITE — domain registered in Plausible (this file).
 *   SN_PLAUSIBLE_KEY  — API key (wp-config.php). Widgets silently
 *                       hide themselves when this is missing.
 *
 * Error handling:
 *   WP_Error and non-200 responses write the message to transient
 *   'sn_plausible_error' (1h TTL). An admin_notices hook below surfaces it
 *   on the Dashboard and theme options pages.
 *
 * @package SignalNoise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SN_PLAUSIBLE_URL', 'https://plausible-analytics-ce-production-fcb9.up.railway.app' );
define( 'SN_PLAUSIBLE_SITE', 'juanlentino.com' );

function sn_plausible_api( $endpoint, $params = array(), $cache_minutes = 15 ) {
	if ( ! defined( 'SN_PLAUSIBLE_KEY' ) ) return null;
	$cache_key = 'sn_pa_' . md5( $endpoint . serialize( $params ) );
	$cached    = get_transient( $cache_key );
	if ( false !== $cached ) return $cached;
	$url = SN_PLAUSIBLE_URL . '/api/v1/stats/' . $endpoint . '?site_id=' . SN_PLAUSIBLE_SITE;
	foreach ( $params as $k => $v ) $url .= '&' . urlencode( $k ) . '=' . urlencode( $v );
	$response = wp_remote_get( $url, array(
		'headers' => array( 'Authorization' => 'Bearer ' . SN_PLAUSIBLE_KEY ),
		'timeout' => 10,
	) );
	if ( is_wp_error( $response ) ) {
		set_transient( 'sn_plausible_error', $response->get_error_message(), HOUR_IN_SECONDS );
		return null;
	}
	$code = wp_remote_retrieve_response_code( $response );
	if ( 200 !== $code ) {
		set_transient( 'sn_plausible_error', 'HTTP ' . (int) $code . ' from Plausible API', HOUR_IN_SECONDS );
		return null;
	}
	$data = json_decode( wp_remote_retrieve_body( $response ), true );
	set_transient( $cache_key, $data, $cache_minutes * MINUTE_IN_SECONDS );
	delete_transient( 'sn_plausible_error' );
	return $data;
}

function sn_fmt( $n ) {
	if ( $n >= 1000000 ) return round( $n / 1000000, 1 ) . 'M';
	if ( $n >= 1000 ) return round( $n / 1000, 1 ) . 'K';
	return number_format( $n );
}

function sn_duration( $s ) {
	if ( $s < 60 ) return $s . 's';
	return floor( $s / 60 ) . 'm ' . ( $s % 60 ) . 's';
}

function sn_metric_card( $label, $value, $change = null ) {
	echo '<div style="flex:1;min-width:90px;text-align:center;padding:10px 6px;">';
	echo '<div style="font-size:1.5em;font-weight:600;color:#1d2327;">' . esc_html( $value ) . '</div>';
	echo '<div style="font-size:0.75em;color:#787c82;margin-top:2px;">' . esc_html( $label ) . '</div>';
	if ( null !== $change ) {
		$color = $change > 0 ? '#00a32a' : ( $change < 0 ? '#d63638' : '#787c82' );
		$arrow = $change > 0 ? '&#9650;' : ( $change < 0 ? '&#9660;' : '&#8212;' );
		echo '<div style="font-size:0.7em;color:' . $color . ';margin-top:2px;">' . $arrow . ' ' . abs( $change ) . '%</div>';
	}
	echo '</div>';
}

function sn_ranked_list( $items, $key, $metric = 'visitors' ) {
	if ( empty( $items ) ) { echo '<p style="color:#787c82;font-size:0.85em;">No data yet.</p>'; return; }
	$max = max( array_column( $items, $metric ) );
	echo '<table style="width:100%;border-collapse:collapse;">';
	foreach ( array_slice( $items, 0, 10 ) as $item ) {
		$label = $item[ $key ] ?: '(direct / none)';
		$count = $item[ $metric ];
		$pct   = $max > 0 ? round( ( $count / $max ) * 100 ) : 0;
		echo '<tr><td style="padding:4px 8px 4px 0;font-size:0.85em;position:relative;">';
		echo '<div style="position:absolute;top:0;left:0;bottom:0;width:' . $pct . '%;background:rgba(224,4,4,0.07);border-radius:2px;"></div>';
		echo '<span style="position:relative;">' . esc_html( $label ) . '</span></td>';
		echo '<td style="padding:4px 0;font-size:0.85em;text-align:right;white-space:nowrap;font-weight:500;">' . sn_fmt( $count ) . '</td></tr>';
	}
	echo '</table>';
}

/**
 * Surface Plausible API failures in the admin UI.
 *
 * Shown to manage_options users on Dashboard and the theme options page —
 * the two places where our Plausible widgets actually render.
 */
add_action( 'admin_notices', function() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || ! in_array( $screen->id, array( 'dashboard', 'appearance_page_sn-theme-options' ), true ) ) {
		return;
	}

	if ( ! defined( 'SN_PLAUSIBLE_KEY' ) ) {
		// Missing key is not an error — the widgets just don't appear. No notice needed.
		return;
	}

	$last_error = get_transient( 'sn_plausible_error' );
	if ( $last_error ) {
		echo '<div class="notice notice-warning"><p><strong>Signal &amp; Noise:</strong> Plausible analytics request failed — ' . esc_html( $last_error ) . '. Check <code>SN_PLAUSIBLE_KEY</code> and that the Plausible instance is reachable.</p></div>';
	}
} );
