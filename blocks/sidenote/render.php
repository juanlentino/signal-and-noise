<?php
/**
 * Dynamic render for signal-noise/sidenote.
 *
 * $attributes is supplied by core's block render context. Emits the .sn-sidenote
 * paragraph that assets/css/critical.css targets (float-right at wide / inline
 * hairline at narrow). Content is wp_kses_post'd — rich inline text is allowed.
 *
 * @package SignalNoise
 * @since 9.11.0
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
$wrapper = get_block_wrapper_attributes( array( 'class' => 'sn-sidenote' ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $wrapper is core-escaped attribute markup from get_block_wrapper_attributes(); content is wp_kses_post()'d inline.
printf( '<p %s>%s</p>', $wrapper, wp_kses_post( $attributes['content'] ?? '' ) );
