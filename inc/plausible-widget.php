<?php
/**
 * Signal & Noise — Plausible dashboard widget set.
 *
 * Registers four discrete dashboard widgets, each surfacing one slice of
 * Plausible Stats API data:
 *
 *   - sn_plausible_snapshot — 7-day aggregate (visitors, pageviews,
 *                              bounce rate, average visit duration)
 *   - sn_plausible_realtime — visitors right now (30-sec cache, distinct
 *                              from the 5-min batched cache the others use)
 *   - sn_plausible_pages    — top 7 pages, last 7 days
 *   - sn_plausible_sources  — top 7 referrers, last 7 days
 *
 * Why four widgets instead of one big panel: WP dashboard widgets are
 * draggable + per-user hideable via Screen Options, so admins can
 * arrange / hide them independently. The shared cache in
 * inc/plausible-api.php means four widgets cost one batched API fetch
 * every 5 min, not four.
 *
 * @package SignalNoise
 * @since 7.2.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'wp_dashboard_setup', function() {
	if ( ! current_user_can( 'view_stats' ) && ! current_user_can( 'manage_options' ) ) {
		return;
	}
	wp_add_dashboard_widget( 'sn_plausible_snapshot', 'Plausible — Last 7 days',     'sn_pl_widget_snapshot' );
	wp_add_dashboard_widget( 'sn_plausible_realtime', 'Plausible — Right now',       'sn_pl_widget_realtime' );
	wp_add_dashboard_widget( 'sn_plausible_pages',    'Plausible — Top pages (7d)',  'sn_pl_widget_pages' );
	wp_add_dashboard_widget( 'sn_plausible_sources',  'Plausible — Top sources (7d)', 'sn_pl_widget_sources' );
} );

/**
 * Inline CSS, printed once per pageload (the first widget that renders
 * triggers it; subsequent calls are no-ops via the static guard).
 */
function sn_pl_styles() {
	static $printed = false;
	if ( $printed ) {
		return;
	}
	$printed = true;
	?>
	<style>
	.sn-pl-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:10px;margin:0;}
	.sn-pl-stat{background:#f5f5f5;padding:10px 12px;border-left:3px solid #e00404;}
	.sn-pl-stat-n{font-family:"Bebas Neue",Impact,sans-serif;font-size:1.75rem;line-height:1;color:#000;}
	.sn-pl-stat-l{font-family:"DM Mono",monospace;font-size:0.65rem;letter-spacing:0.12em;text-transform:uppercase;color:#666;margin-top:4px;}
	.sn-pl-big{font-family:"Bebas Neue",Impact,sans-serif;font-size:4.5rem;line-height:0.9;color:#e00404;text-align:center;padding:6px 0 4px;}
	.sn-pl-big-l{font-family:"DM Mono",monospace;font-size:0.7rem;letter-spacing:0.18em;text-transform:uppercase;color:#666;text-align:center;}
	.sn-pl-list{list-style:none;margin:0;padding:0;font-size:0.85em;}
	.sn-pl-list li{display:flex;justify-content:space-between;padding:5px 0;border-bottom:1px solid #eee;gap:10px;}
	.sn-pl-list li:last-child{border-bottom:0;}
	.sn-pl-list .k{color:#000;flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
	.sn-pl-list .v{font-family:"DM Mono",monospace;font-size:0.85em;color:#666;flex-shrink:0;}
	.sn-pl-foot{margin-top:10px;font-family:"DM Mono",monospace;font-size:0.65rem;letter-spacing:0.1em;color:#999;}
	.sn-pl-empty{color:#666;font-size:0.85em;font-style:italic;margin:0;}
	.sn-pl-err{color:#d63638;font-size:0.9em;margin:0;}
	</style>
	<?php
}

/**
 * Common preamble: ensure styles are printed and resolve the batched
 * dataset (or print an error and return null).
 */
function sn_pl_preamble() {
	sn_pl_styles();
	$data = sn_plausible_dashboard_data();
	if ( ! $data ) {
		echo '<p class="sn-pl-err">Plausible plugin not configured — set domain + Plugin Token in <em>Settings → Plausible Analytics</em>.</p>';
		return null;
	}
	return $data;
}

function sn_pl_widget_snapshot() {
	$data = sn_pl_preamble();
	if ( ! $data ) {
		return;
	}
	$a = $data['aggregate'];
	echo '<div class="sn-pl-grid">';
	sn_pl_stat( 'Visitors',  $a['visitors']['value']  ?? null );
	sn_pl_stat( 'Pageviews', $a['pageviews']['value'] ?? null );
	sn_pl_stat( 'Bounce',    isset( $a['bounce_rate']['value'] ) ? $a['bounce_rate']['value'] . '%' : null );
	sn_pl_stat( 'Avg time',  isset( $a['visit_duration']['value'] ) ? sn_pl_duration( $a['visit_duration']['value'] ) : null );
	echo '</div>';
	sn_pl_footer( $data, '7d' );
}

function sn_pl_widget_realtime() {
	sn_pl_styles();
	$cfg = sn_plausible_config();
	if ( ! $cfg ) {
		echo '<p class="sn-pl-err">Plausible plugin not configured — set domain + Plugin Token in <em>Settings → Plausible Analytics</em>.</p>';
		return;
	}
	$n = sn_plausible_realtime();
	echo '<div class="sn-pl-big-l">Visitors right now</div>';
	echo '<div class="sn-pl-big">' . esc_html( null === $n ? '—' : number_format_i18n( (int) $n ) ) . '</div>';
	$dash = $cfg['base'] . '/' . rawurlencode( $cfg['domain'] );
	echo '<p class="sn-pl-foot">Last 5 min · refreshes every 30 s · <a href="' . esc_url( $dash ) . '" target="_blank" rel="noopener">Open dashboard ↗</a></p>';
}

function sn_pl_widget_pages() {
	$data = sn_pl_preamble();
	if ( ! $data ) {
		return;
	}
	sn_pl_breakdown( $data['pages'], 'page', 'No traffic in the last 7 days.' );
	sn_pl_footer( $data, '7d' );
}

function sn_pl_widget_sources() {
	$data = sn_pl_preamble();
	if ( ! $data ) {
		return;
	}
	sn_pl_breakdown( $data['sources'], 'source', 'No referrers tracked in the last 7 days.', 'Direct / None' );
	sn_pl_footer( $data, '7d' );
}

function sn_pl_breakdown( $rows, $key, $empty_msg, $blank_label = '' ) {
	if ( empty( $rows ) ) {
		echo '<p class="sn-pl-empty">' . esc_html( $empty_msg ) . '</p>';
		return;
	}
	echo '<ul class="sn-pl-list">';
	foreach ( $rows as $row ) {
		$k = (string) ( $row[ $key ] ?? '' );
		$v = (int)    ( $row['visitors'] ?? 0 );
		if ( '' === $k && '' !== $blank_label ) {
			$k = $blank_label;
		}
		echo '<li><span class="k">' . esc_html( $k ) . '</span><span class="v">' . esc_html( number_format_i18n( $v ) ) . '</span></li>';
	}
	echo '</ul>';
}

function sn_pl_stat( $label, $value ) {
	$display = ( null === $value || '' === $value )
		? '—'
		: ( is_numeric( $value ) ? number_format_i18n( (float) $value ) : (string) $value );
	echo '<div class="sn-pl-stat"><div class="sn-pl-stat-n">' . esc_html( $display ) . '</div><div class="sn-pl-stat-l">' . esc_html( $label ) . '</div></div>';
}

function sn_pl_duration( $seconds ) {
	$seconds = (int) $seconds;
	if ( $seconds < 60 ) {
		return $seconds . 's';
	}
	$m = (int) floor( $seconds / 60 );
	$s = $seconds % 60;
	return $m . 'm ' . str_pad( (string) $s, 2, '0', STR_PAD_LEFT ) . 's';
}

function sn_pl_footer( $data, $period_label ) {
	$cfg = sn_plausible_config();
	if ( ! $cfg ) {
		return;
	}
	$dash = $cfg['base'] . '/' . rawurlencode( $cfg['domain'] );
	$ago  = human_time_diff( (int) $data['fetched'], time() );
	echo '<p class="sn-pl-foot">' . esc_html( $period_label ) . ' · cached ' . esc_html( $ago ) . ' ago · <a href="' . esc_url( $dash ) . '" target="_blank" rel="noopener">Open dashboard ↗</a></p>';
}
