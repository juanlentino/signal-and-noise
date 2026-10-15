<?php
/**
 * Dynamic render for signal-noise/lang-toggle (inc/lang-toggle.php). Not
 * autop'd: it renders a <nav>, and nothing at all off a paired page.
 *
 * @package SignalNoise
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped per value in sn_lang_toggle_html().
echo (string) sn_lang_toggle_markup();
