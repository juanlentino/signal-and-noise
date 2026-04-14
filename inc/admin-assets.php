<?php
/**
 * Signal & Noise — Admin-side asset registration.
 *
 * Registers vendor libraries (jsvectormap, Chart.js) and the three theme-owned
 * admin JS files, then enqueues only what each screen actually needs.
 *
 * Vendor scripts are pinned to specific versions and carry SRI integrity +
 * crossorigin attributes via the *_loader_tag filters below. If you bump a
 * CDN version, regenerate the hash with:
 *
 *   openssl dgst -sha384 -binary FILE | openssl base64 -A
 *
 * @package SignalNoise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_enqueue_scripts', function( $hook ) {
	$is_dashboard = ( 'index.php' === $hook ) && defined( 'SN_PLAUSIBLE_KEY' );
	$is_options   = ( 'appearance_page_sn-theme-options' === $hook );
	if ( ! $is_dashboard && ! $is_options ) {
		return;
	}

	$ver = wp_get_theme()->get( 'Version' );

	// Vendor — jsvectormap 1.6.0
	wp_register_style(
		'sn-jsvectormap-css',
		'https://cdn.jsdelivr.net/npm/jsvectormap@1.6.0/dist/jsvectormap.min.css',
		array(),
		'1.6.0'
	);
	wp_register_script(
		'sn-jsvectormap',
		'https://cdn.jsdelivr.net/npm/jsvectormap@1.6.0/dist/jsvectormap.min.js',
		array(),
		'1.6.0',
		true
	);
	wp_register_script(
		'sn-jsvectormap-world',
		'https://cdn.jsdelivr.net/npm/jsvectormap@1.6.0/dist/maps/world.js',
		array( 'sn-jsvectormap' ),
		'1.6.0',
		true
	);
	// Vendor — Chart.js 4.4.4
	wp_register_script(
		'sn-chartjs',
		'https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js',
		array(),
		'4.4.4',
		true
	);

	// Theme-owned admin JS
	wp_register_script(
		'sn-admin-map',
		get_theme_file_uri( 'assets/js/admin-map.js' ),
		array( 'sn-jsvectormap-world' ),
		$ver,
		true
	);
	wp_register_script(
		'sn-admin-tabs',
		get_theme_file_uri( 'assets/js/admin-tabs.js' ),
		array(),
		$ver,
		true
	);
	wp_register_script(
		'sn-admin-chart',
		get_theme_file_uri( 'assets/js/admin-chart.js' ),
		array( 'sn-chartjs' ),
		$ver,
		true
	);

	if ( $is_dashboard ) {
		wp_enqueue_style(  'sn-jsvectormap-css' );
		wp_enqueue_script( 'sn-admin-map' );
		wp_enqueue_script( 'sn-admin-tabs' );
	}
	if ( $is_options ) {
		wp_enqueue_style(  'sn-jsvectormap-css' );
		wp_enqueue_script( 'sn-admin-map' );
		wp_enqueue_script( 'sn-admin-tabs' );
		wp_enqueue_script( 'sn-admin-chart' );
	}
} );

/**
 * SRI integrity + crossorigin on vendor scripts pinned above.
 * Regenerate hashes if versions change.
 */
add_filter( 'script_loader_tag', function( $tag, $handle ) {
	$hashes = array(
		'sn-jsvectormap'       => 'sha384-BEJncmOheJY/jyZrAd3+piL709jKDBV0+sZY3wDAfoj3Q7nxojiTAIu+R9+i6+tE',
		'sn-jsvectormap-world' => 'sha384-sGlkSOVF9H+QNNQrLV9xrMIyghEgioD3M3gPd9/tV0mh9qJX75AyCl0l7+OVJ3CD',
		'sn-chartjs'           => 'sha384-NrKB+u6Ts6AtkIhwPixiKTzgSKNblyhlk0Sohlgar9UHUBzai/sgnNNWWd291xqt',
	);
	if ( ! isset( $hashes[ $handle ] ) ) {
		return $tag;
	}
	$attrs = ' integrity="' . esc_attr( $hashes[ $handle ] ) . '" crossorigin="anonymous"';
	return preg_replace( '#(<script\b)#', '$1' . $attrs, $tag, 1 );
}, 10, 2 );

add_filter( 'style_loader_tag', function( $tag, $handle ) {
	$hashes = array(
		'sn-jsvectormap-css' => 'sha384-MrwTqJxj6y8ZHkNV+BHWg1mH0FyrEsFczIkX+QL81NLXjYCH74i1lwh4BbSUEj6a',
	);
	if ( ! isset( $hashes[ $handle ] ) ) {
		return $tag;
	}
	$attrs = ' integrity="' . esc_attr( $hashes[ $handle ] ) . '" crossorigin="anonymous"';
	return preg_replace( '#(<link\b)#', '$1' . $attrs, $tag, 1 );
}, 10, 2 );
