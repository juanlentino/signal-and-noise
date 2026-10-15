<?php
/**
 * Dynamic render for signal-noise/lang-toggle: the EN | ES switch in the
 * header (inc/lang-toggle.php). Never autop'd.
 *
 * @package SignalNoise
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped per value in the builders.
echo (string) sn_lang_toggle_markup();
