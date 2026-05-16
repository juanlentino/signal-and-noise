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
 * Theme setup: register editor styles to match the frontend cascade.
 *
 * i18n bootstrap intentionally absent — see v8.1.1 hygiene pass:
 * single-author surface, no translation files will ever be produced,
 * and the prior `load_theme_textdomain()` call pointed at a non-existent
 * directory. The `Text Domain: signal-noise` header in style.css is
 * retained as passive metadata.
 */
function signal_noise_after_setup_theme() {
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
