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
 * Enqueue editor styles so the Site Editor matches the front end.
 *
 * Passes the same five modular stylesheets used on the public side, in
 * the same cascade order. Keep this list in sync with the wp_enqueue
 * chain in inc/assets-frontend.php.
 */
function signal_noise_editor_styles() {
	add_editor_style( array(
		'assets/css/base.css',
		'assets/css/layout.css',
		'assets/css/components.css',
		'assets/css/forms.css',
		'assets/css/responsive.css',
	) );
}
add_action( 'after_setup_theme', 'signal_noise_editor_styles' );

/**
 * Shortcode: [current_year]
 */
function signal_noise_current_year() {
	return date( 'Y' );
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
