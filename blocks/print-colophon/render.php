<?php
/**
 * Dynamic render for signal-noise/print-colophon (inc/print-colophon.php has
 * the contract; tests/print-colophon.php pins it).
 *
 * @package SignalNoise
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sn_print_colophon_html() escapes every value it prints (esc_html / esc_url).
echo sn_php_block_output( sn_print_colophon_html(), __( 'Print colophon', 'signal-and-noise' ) );
