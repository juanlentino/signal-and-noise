<?php
/**
 * Dynamic render for signal-noise/lang-toggle. Header placement: the EN / ES
 * toggle (inc/lang-toggle.php), nothing off a paired page. Footer placement:
 * the site-wide language link (inc/lang-chrome-footer.php). Never autop'd.
 *
 * @package SignalNoise
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
$sn_footer = isset( $attributes['placement'] ) && 'footer' === $attributes['placement'];
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped per value in the builders.
echo (string) ( $sn_footer ? sn_lang_footer_link_markup() : sn_lang_toggle_markup() );
