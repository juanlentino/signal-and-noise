<?php
/**
 * Dynamic render for signal-noise/sidenote.
 *
 * $attributes is supplied by core's block render context. Emits the .sn-sidenote
 * paragraph that assets/css/article.css targets (float-right at >=1280px /
 * inline hairline below). Content is wp_kses_post'd — rich inline text allowed.
 *
 * v12.20.4: this said critical.css. The rules are in article.css and are the
 * only two in the theme — a stale pointer sends the next reader to the wrong
 * file to debug a device whose whole behaviour lives in two media queries.
 * Verified by grepping every stylesheet, not by trusting either comment.
 *
 * @package SignalNoise
 * @since 9.11.0
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
$wrapper = get_block_wrapper_attributes( array( 'class' => 'sn-sidenote' ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $wrapper is core-escaped attribute markup from get_block_wrapper_attributes(); content is wp_kses_post()'d inline.
printf( '<p %s>%s</p>', $wrapper, wp_kses_post( $attributes['content'] ?? '' ) );
