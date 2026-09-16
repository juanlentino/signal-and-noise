<?php
/**
 * Dynamic render for signal-noise/prov-chip: wpautop( [shortcode] ), the string
 * the core/shortcode block used to produce (inc/blocks-php-only.php has the
 * contract; tests/blocks-php-only.php pins it).
 *
 * @package SignalNoise
 * @since 13.2.3
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the renderer's own markup, wpautop'd, exactly what core/shortcode emitted.
echo sn_php_block_output( sn_prov_chip_shortcode(), __( 'Provenance chip', 'signal-and-noise' ) );
