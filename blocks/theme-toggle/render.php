<?php
/**
 * Dynamic render for signal-noise/theme-toggle. Placed in the header and the
 * footer; it was a wp:html block, never autop'd, so no wpautop here. An unknown
 * placement falls back to footer, never passes through.
 *
 * @package SignalNoise
 * @since 13.2.3
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
$sn_placement = ( isset( $attributes['placement'] ) && 'header' === $attributes['placement'] ) ? 'header' : 'footer';
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the toggle's own escaped markup (inc/dark-mode.php).
echo (string) sn_dark_mode_toggle_markup( array( 'placement' => $sn_placement ) );
