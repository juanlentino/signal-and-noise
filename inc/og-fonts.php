<?php
/**
 * Signal & Noise — OG card font paths.
 *
 * The plugin (signal-and-noise-tools v1.3.0+) owns OG card generation
 * via inc/og-card-generator.php. It calls apply_filters('sn_og_font_paths', [])
 * to learn which TTF files to embed. This file is the theme's response:
 * the brand owns the typography, so the theme provides the paths to
 * its bundled TTFs in assets/fonts/og/.
 *
 * Phase 3 (v8.4.0, 2026-05-16) — replaces the theme-side card generator
 * with a thin font-path provider. The 402-LOC PHP GD rendering pipeline
 * now lives in the plugin where it belongs (operational image processing).
 *
 * @package SignalNoise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter( 'sn_og_font_paths', function( $paths ) {
	return array(
		'bebas'  => get_theme_file_path( 'assets/fonts/og/BebasNeue-Regular.ttf' ),
		'dmmono' => get_theme_file_path( 'assets/fonts/og/DMMono-Light.ttf' ),
	);
} );
