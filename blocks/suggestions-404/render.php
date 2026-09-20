<?php
/**
 * Dynamic render for signal-noise/suggestions-404: wpautop( [shortcode] ), the
 * string the core/shortcode block used to produce (inc/blocks-php-only.php has
 * the contract; tests/blocks-php-only.php pins it, tests/404-recovery.php pins
 * the bytes against the old template path).
 *
 * @package SignalNoise
 * @since the cut after 13.4.0 (#386)
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the renderer's own markup, wpautop'd, exactly what core/shortcode emitted.
echo sn_php_block_output( sn_404_suggestions_shortcode(), __( '404 suggestions', 'signal-and-noise' ) );
