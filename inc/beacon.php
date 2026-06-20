<?php
/**
 * Signal & Noise — analytics beacon enqueue (P1).
 *
 * Loads assets/js/sn-beacon.js on the front end and injects a
 * window.SN_BEACON data island (endpoint + public site token + post id)
 * via wp_add_inline_script, mirroring inc/command-palette.php. The token
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
	$json = wp_json_encode( $cfg, JSON_HEX_TAG | JSON_UNESCAPED_SLASHES );
	if ( false === $json ) {
		return;
	}
	wp_add_inline_script( 'sn-beacon', 'window.SN_BEACON=' . $json . ';', 'before' );
}

if ( ! defined( 'SN_BEACON_TEST' ) || ! SN_BEACON_TEST ) {
	add_action( 'wp_enqueue_scripts', 'sn_beacon_enqueue', 30 );
}
