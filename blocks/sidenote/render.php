<?php
/**
 * Dynamic render for signal-noise/sidenote.
 *
 * $attributes is supplied by core's block render context. Emits the .sn-sidenote
 * paragraph that ./style.css targets (float-right at >=1280px / inline
 * hairline below). Content is wp_kses_post'd, rich inline text allowed.
 *
 * The sheet sits beside this file and block.json declares it (#391), so the
 * pointer cannot go stale the way "critical.css" (until 12.20.4) and
 * "article.css" (until #391) did: each sent the next reader to the wrong
 * file to debug a device whose whole behaviour lives in two media queries.
 *
 * @package SignalNoise
 * @since 9.11.0
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
$wrapper = get_block_wrapper_attributes( array( 'class' => 'sn-sidenote' ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $wrapper is core-escaped attribute markup from get_block_wrapper_attributes(); content is wp_kses_post()'d inline.
printf( '<p %s>%s</p>', $wrapper, wp_kses_post( $attributes['content'] ?? '' ) );
