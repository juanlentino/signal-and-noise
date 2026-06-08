<?php
/**
 * Signal & Noise — Block Bindings source: signal-noise/post-field.
 *
 * Read-only source resolving reading_time|pillar|canonical|og_title for the
 * current post. PHP-only registration → read-only in the editor (acceptable).
 * All plugin reads function_exists-guarded; returns null to keep the block's
 * fallback markup when a value is genuinely absent.
 *
 * @package SignalNoise
 * @since 9.11.0
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * get_value_callback for the signal-noise/post-field source.
 *
 * @param array      $source_args    Binding args; expects ['key' => ...].
 * @param mixed|null $block_instance The block instance (for postId context).
 * @param string     $attribute_name The bound attribute name (unused).
 * @return string|null Resolved value, or null to keep the block fallback.
 */
function sn_post_field_binding_value( $source_args, $block_instance = null, $attribute_name = '' ) {
	$key = isset( $source_args['key'] ) ? (string) $source_args['key'] : '';
	if ( '' === $key ) {
		return null;
	}

	$post_id = 0;
	if ( $block_instance && ! empty( $block_instance->context['postId'] ) ) {
		$post_id = (int) $block_instance->context['postId'];
	} else {
		$p = get_post();
		if ( $p ) {
			$post_id = (int) $p->ID;
		}
	}
	if ( ! $post_id ) {
		return null;
	}

	switch ( $key ) {
		case 'reading_time':
			if ( ! function_exists( 'sn_get_reading_time' ) ) {
				return null;
			}
			return esc_html( sprintf( '%d min read', max( 1, (int) sn_get_reading_time( $post_id ) ) ) );
		case 'pillar':
			if ( ! function_exists( 'sn_post_pillar_shortcode' ) ) {
				return null;
			}
			$html = sn_post_pillar_shortcode();
			return '' !== $html ? $html : null;
		case 'canonical':
			if ( ! function_exists( 'sn_post_settings_get_canonical_url' ) ) {
				return null;
			}
			$v = sn_post_settings_get_canonical_url( $post_id );
			return '' !== $v ? esc_url( $v ) : null;
		case 'og_title':
			if ( ! function_exists( 'sn_post_settings_get_og_card_title' ) ) {
				return null;
			}
			$v = sn_post_settings_get_og_card_title( $post_id );
			return '' !== $v ? esc_html( $v ) : null;
	}
	return null;
}

/**
 * Register the signal-noise/post-field block bindings source on init.
 */
function sn_register_post_field_binding() {
	register_block_bindings_source(
		'signal-noise/post-field',
		array(
			'label'              => __( 'Signal & Noise: Post Field', 'signal-noise' ),
			'get_value_callback' => 'sn_post_field_binding_value',
			'uses_context'       => array( 'postId', 'postType' ),
		)
	);
}

if ( ! defined( 'SN_BINDINGS_TEST' ) || ! SN_BINDINGS_TEST ) {
	add_action( 'init', 'sn_register_post_field_binding' );
}
