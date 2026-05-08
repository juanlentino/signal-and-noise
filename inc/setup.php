<?php
/**
 * Signal & Noise — Theme setup.
 *
 * Editor styles, shortcodes, and the render_block filter that lets shortcodes
 * resolve inside block template parts.
 *
 * @package SignalNoise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Theme setup: register the text domain and editor styles together at
 * after_setup_theme. load_theme_textdomain() points at the languages/
 * directory we'll create when (if) translations are produced. Even
 * with no translation files present this call is harmless and makes
 * subsequent __() / esc_html__() calls behave consistently with WPCS
 * conventions; without it, sprinkled translation calls work by
 * fall-through rather than by registered intent.
 */
function signal_noise_after_setup_theme() {
	load_theme_textdomain( 'signal-noise', get_theme_file_path( 'languages' ) );

	// Editor styles — same five modular stylesheets used on the public
	// side, in the same cascade order. Keep this list in sync with the
	// wp_enqueue chain in inc/assets-frontend.php.
	add_editor_style( array(
		'assets/css/base.css',
		'assets/css/layout.css',
		'assets/css/components.css',
		'assets/css/forms.css',
		'assets/css/responsive.css',
	) );
}
add_action( 'after_setup_theme', 'signal_noise_after_setup_theme' );

/**
 * Shortcode: [current_year]
 *
 * Uses wp_date() (not date()) so the year respects the WordPress site
 * timezone setting, not the server timezone. On a US-hosted WordPress
 * configured with a non-US timezone, plain date() can disagree with
 * wp_date() for a few hours each year around 2026-12-31 / 2027-01-01.
 */
function signal_noise_current_year() {
	return wp_date( 'Y' );
}
add_shortcode( 'current_year', 'signal_noise_current_year' );

/**
 * Process shortcodes inside block template parts.
 */
add_filter( 'render_block', function( $block_content, $block ) {
	if ( strpos( $block_content, '[current_year]' ) !== false ) {
		$block_content = do_shortcode( $block_content );
	}
	return $block_content;
}, 10, 2 );
