<?php
/**
 * Dynamic render for signal-noise/next-essay (inc/next-essay.php).
 *
 * @package SignalNoise
 * @since 14.9.0
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the renderer escapes its own markup.
echo sn_php_block_output( sn_next_essay_html(), __( 'Next essay', 'signal-and-noise' ) );
