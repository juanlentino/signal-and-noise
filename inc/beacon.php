<?php
/**
 * Signal & Noise — analytics beacon enqueue (P1).
 *
 * Loads assets/js/sn-beacon.js on the front end and prints its config
 * (endpoint + public site token + post id) as a JSON data island,
 * <script type="application/json" id="sn-beacon-data">, which the script
 * reads with JSON.parse. A data island is not executable, so it needs no
 * 'unsafe-inline' in the CSP script-src (2026-10-16), mirroring
 * inc/command-palette.php. The token
 * is PUBLIC by design (it ships in page source) — it is a spam deterrent
 * the Cloudflare Worker validates, not a secret. Cookieless; the script
 * no-ops under DNT/GPC.
 *
 * @package SignalNoise
 * @since theme v10.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** The id of the JSON data island assets/js/sn-beacon.js reads. */
const SN_BEACON_ISLAND_ID = 'sn-beacon-data';

/**
 * Whether the analytics beacon is active. Default true; the companion
 * plugin can toggle it via the sn_beacon_enabled filter.
 *
 * @return bool
 */
function sn_beacon_enabled() {
	return (bool) apply_filters( 'sn_beacon_enabled', true );
}

/**
 * The public site token the Worker validates. Override in wp-config.php via
 * define( 'SN_BEACON_TOKEN', '…' ) — must equal the Worker's SN_PX_TOKEN
 * secret. Filterable for the companion plugin.
 *
 * @return string
 */
function sn_beacon_token() {
	$token = defined( 'SN_BEACON_TOKEN' ) ? (string) SN_BEACON_TOKEN : '';
	return (string) apply_filters( 'sn_beacon_token', $token );
}

/**
 * Enqueue the beacon site-wide (deferred, footer) and inject its config
 * island before the module runs.
 *
 * @return void
 */
function sn_beacon_enqueue() {
	if ( ! sn_beacon_enabled() ) {
		return;
	}
	// web-vitals (vendored, self-hosted) powers the beacon's field-CWV section: it
	// must define window.webVitals before the beacon runs, so the beacon depends on
	// it. Both deferred + footer → they execute in dependency order. (v10.14.0)
	wp_enqueue_script(
		'sn-web-vitals',
		get_theme_file_uri( 'assets/js/web-vitals.iife.js' ),
		array(),
		sn_asset_ver( 'assets/js/web-vitals.iife.js' ),
		array( 'in_footer' => true, 'strategy' => 'defer' )
	);
	wp_enqueue_script(
		'sn-beacon',
		get_theme_file_uri( 'assets/js/sn-beacon.js' ),
		array( 'sn-web-vitals' ),
		sn_asset_ver( 'assets/js/sn-beacon.js' ),
		array( 'in_footer' => true, 'strategy' => 'defer' )
	);
	$cfg  = array(
		'endpoint' => '/_sn/px',
		'k'        => sn_beacon_token(),
		'id'       => (int) get_the_ID(),
	);
	// JSON_HEX_TAG keeps "<" out of the island, so no value can close it.
	// Built here, at enqueue time and before the loop, so get_the_ID() is the
	// queried page (0 on archives), and printed at wp_footer priority 1, ahead
	// of the deferred script that reads it.
	$json = wp_json_encode( $cfg, JSON_HEX_TAG | JSON_UNESCAPED_SLASHES );
	if ( false === $json ) {
		return;
	}
	$tag = wp_get_inline_script_tag( $json, array( 'type' => 'application/json', 'id' => SN_BEACON_ISLAND_ID ) );
	add_action(
		'wp_footer',
		static function () use ( $tag ) {
			echo $tag; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_get_inline_script_tag() output; JSON_HEX_TAG data.
		},
		1
	);
}

if ( ! defined( 'SN_BEACON_TEST' ) || ! SN_BEACON_TEST ) {
	add_action( 'wp_enqueue_scripts', 'sn_beacon_enqueue', 30 );
}
