<?php
/**
 * The home hero's pulse: the last 24 hours of the site's own traffic, drawn
 * as a hairline waveform along the bottom of the hero.
 *
 * The data is the companion plugin's cookieless analytics: hourly pageviews
 * per day in its buckets table (inc/analytics-buckets.php in the plugin,
 * metric 'hour', refreshed every few minutes by the analytics Worker's cron).
 * No plugin function returns a date-keyed hourly series (its grid accessor
 * folds days into weekdays), so this reads the table the same way the
 * plugin's own accessor does, only when that accessor is loaded. Every class
 * is summed: it is the server's pulse, not the human count.
 *
 * The window is the 24 completed UTC hours ending at the last full hour; the
 * hour now running would always read as a dip. Each hour draws four
 * equal-height hairlines, so every line is a real reading and none is
 * interpolated. Real data only: no plugin, no rows, all zero or all equal
 * (normalized, that is a solid block) draws nothing at all.
 *
 * Only shape reaches the page: heights normalized to a 100-unit box, no
 * counts, labels or times. The rule is cached ten minutes, the empty answer
 * too, so a render costs at most one read and a dormant plugin none.
 *
 * It is decoration in <head>, not content: the front page's Page and the hero
 * pattern are untouched. The static half (position, size, the stepped
 * draw-in, forced colors) is in assets/css/critical.css; the pseudo has no
 * `content` there, so without this rule it never renders.
 *
 * @package SignalNoise
 * @since 2026.10
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const SN_HERO_PULSE_TRANSIENT = 'sn_hero_pulse_css';
const SN_HERO_PULSE_LINES     = 4; // Hairlines per hour.

/**
 * The UTC days the 24-hour window spans: yesterday and today.
 *
 * @param int $now Unix time.
 * @return array{day:string, last:string}
 */
function sn_hero_pulse_days( $now ) {
	$last = sn_hero_pulse_last_hour( $now );
	return array(
		'day'  => gmdate( 'Y-m-d', $last - 23 * HOUR_IN_SECONDS ),
		'last' => gmdate( 'Y-m-d', $last ),
	);
}

/**
 * Start of the last completed UTC hour.
 *
 * @param int $now Unix time.
 * @return int
 */
function sn_hero_pulse_last_hour( $now ) {
	return (int) ( floor( (int) $now / HOUR_IN_SECONDS ) * HOUR_IN_SECONDS ) - HOUR_IN_SECONDS;
}

/**
 * Hourly rows for the window, every class summed, or null without the plugin.
 *
 * @param int $now Unix time.
 * @return array<int, array<string, mixed>>|null
 */
function sn_hero_pulse_rows( $now ) {
	if ( ! function_exists( 'sn_analytics_hour_dow_grid' ) || ! defined( 'SN_ANALYTICS_BUCKETS_TABLE' ) ) {
		return null;
	}
	global $wpdb;
	$days  = sn_hero_pulse_days( $now );
	$table = $wpdb->prefix . constant( 'SN_ANALYTICS_BUCKETS_TABLE' );
	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- the plugin's own table name from its constant; cached below for ten minutes.
	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT day, bucket, SUM(views) AS views FROM {$table} WHERE day >= %s AND day <= %s AND metric = 'hour' GROUP BY day, bucket",
			$days['day'],
			$days['last']
		),
		ARRAY_A
	);
	// phpcs:enable
	return is_array( $rows ) ? $rows : array();
}

/**
 * The 24 completed hours ending at the last full hour, oldest first. An hour
 * with no row is zero.
 *
 * @param array<int, array<string, mixed>> $rows Rows of day, bucket, views.
 * @param int                              $now  Unix time.
 * @return array<int, int>
 */
function sn_hero_pulse_hours( array $rows, $now ) {
	$first = sn_hero_pulse_last_hour( $now ) - 23 * HOUR_IN_SECONDS;
	$hours = array_fill( 0, 24, 0 );
	foreach ( $rows as $row ) {
		$day    = (string) ( $row['day'] ?? '' );
		$bucket = (string) ( $row['bucket'] ?? '' );
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $day ) || ! preg_match( '/^\d{2}$/', $bucket ) ) {
			continue;
		}
		$ts = strtotime( $day . ' ' . $bucket . ':00:00 UTC' );
		$i  = false === $ts ? -1 : intdiv( $ts - $first, HOUR_IN_SECONDS );
		if ( $i >= 0 && $i < 24 && 0 === ( $ts - $first ) % HOUR_IN_SECONDS ) {
			$hours[ $i ] += max( 0, (int) ( $row['views'] ?? 0 ) );
		}
	}
	return $hours;
}

/**
 * The waveform SVG for a 24-point series, or '' when there is no shape.
 * Heights are normalized to a 100-unit box around a center line.
 *
 * @param array<int, int> $series 24 hourly totals.
 * @return string
 */
function sn_hero_pulse_svg( array $series ) {
	if ( 24 !== count( $series ) ) {
		return '';
	}
	$max = max( $series );
	if ( $max <= 0 || min( $series ) === $max ) {
		return '';
	}
	$pitch = 10;
	$rects = '';
	foreach ( array_values( $series ) as $h => $value ) {
		$height = max( 2.0, round( (int) $value / $max * 100, 1 ) );
		$y      = round( 50 - $height / 2, 2 );
		for ( $l = 0; $l < SN_HERO_PULSE_LINES; $l++ ) {
			$x      = ( $h * SN_HERO_PULSE_LINES + $l ) * $pitch;
			$rects .= "<rect x='" . $x . "' y='" . $y . "' width='4' height='" . $height . "'/>";
		}
	}
	$width = 24 * SN_HERO_PULSE_LINES * $pitch;
	return "<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 " . $width . " 100' preserveAspectRatio='none'>" . $rects . '</svg>';
}

/**
 * The inline rule that makes the pulse render: content plus the mask.
 *
 * @param string $svg Waveform SVG, or ''.
 * @return string
 */
function sn_hero_pulse_css( $svg ) {
	if ( '' === $svg ) {
		return '';
	}
	$url = 'url("data:image/svg+xml,' . rawurlencode( $svg ) . '")';
	return '.sn-hero::after{content:"";-webkit-mask-image:' . $url . ';mask-image:' . $url . '}';
}

/**
 * Whether this request shows the home hero: the front page, or the Spanish
 * home the plugin pairs with it.
 *
 * @return bool
 */
function sn_hero_pulse_is_home() {
	if ( is_front_page() ) {
		return true;
	}
	return function_exists( 'sn_lang_en_counterpart' ) && is_page()
		&& (int) get_option( 'page_on_front' ) === (int) sn_lang_en_counterpart( get_queried_object_id() );
}

/**
 * Print the pulse rule on the home page, from a ten-minute cache.
 */
function sn_hero_pulse_print() {
	if ( ! sn_hero_pulse_is_home() ) {
		return;
	}
	$css = get_transient( SN_HERO_PULSE_TRANSIENT );
	if ( false === $css ) {
		$now  = time();
		$rows = sn_hero_pulse_rows( $now );
		$css  = is_array( $rows ) ? sn_hero_pulse_css( sn_hero_pulse_svg( sn_hero_pulse_hours( $rows, $now ) ) ) : '';
		set_transient( SN_HERO_PULSE_TRANSIENT, $css, 10 * MINUTE_IN_SECONDS );
	}
	if ( '' === $css ) {
		return;
	}
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built here from integers and a fixed template; the SVG is rawurlencoded, so it holds no quote or angle bracket. Escaping would corrupt the CSS.
	echo '<style id="sn-hero-pulse">' . $css . "</style>\n";
}
add_action( 'wp_head', 'sn_hero_pulse_print', 51 );
