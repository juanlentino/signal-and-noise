<?php
/**
 * Dynamic render for signal-noise/pull-quote.
 *
 * Emits the .sn-pull-quote aside (the class assets/css/critical.css targets —
 * NOT the pattern's .sn-pattern-pull-quote). Each slot is omitted when empty so
 * an unattributed quote renders no empty attribution line. Both slots wp_kses_post'd.
 *
 * @package SignalNoise
 * @since 9.11.0
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
$wrapper = get_block_wrapper_attributes( array( 'class' => 'sn-pull-quote' ) );
$body    = wp_kses_post( $attributes['body'] ?? '' );
$attr    = wp_kses_post( $attributes['attribution'] ?? '' );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $wrapper is core-escaped attribute markup from get_block_wrapper_attributes().
echo '<aside ' . $wrapper . '>';
if ( '' !== $body ) {
	echo '<p class="sn-pull-quote__body">' . wp_kses_post( $body ) . '</p>';
}
if ( '' !== $attr ) {
	echo '<p class="sn-pull-quote__attribution">' . wp_kses_post( $attr ) . '</p>';
}
echo '</aside>';
