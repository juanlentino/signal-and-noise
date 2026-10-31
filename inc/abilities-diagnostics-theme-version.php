<?php
/**
 * Abilities API: the get-theme-version callback.
 *
 * Moved verbatim out of inc/abilities-diagnostics.php, which loads it.
 *
 * @package SignalNoise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Execute callback: signal-and-noise/get-theme-version.
 *
 * @since 9.1.0
 */
function sn_theme_ability_theme_version() {
	try {
		if ( ! function_exists( 'wp_get_theme' ) ) {
			return new WP_Error(
				'theme_dependency_missing',
				'wp_get_theme() not available.',
				array( 'status' => 503 )
			);
		}

		$theme         = wp_get_theme();
		$theme_version = method_exists( $theme, 'get' ) ? (string) $theme->get( 'Version' ) : '';
		$theme_name    = method_exists( $theme, 'get' ) ? (string) $theme->get( 'Name' )    : '';
		$template      = method_exists( $theme, 'get_template' ) ? (string) $theme->get_template() : '';

		$is_block = function_exists( 'wp_is_block_theme' ) ? (bool) wp_is_block_theme() : false;

		// v10.0.0: the WP 7.0 floor guarantees wp_get_wp_version() (added in 6.7).
		$wp_version = (string) wp_get_wp_version();

		return array(
			'theme_version'  => $theme_version,
			'theme_name'     => $theme_name,
			'theme_template' => $template,
			'is_block_theme' => $is_block,
			'supports_fse'   => $is_block,
			'wp_version'     => $wp_version,
		);
	} catch ( \Throwable $e ) {
		error_log( 'SN theme ability error in get-theme-version: ' . $e->getMessage() );
		return new WP_Error(
			'theme_ability_error',
			sprintf( 'Theme ability failed: %s', $e->getMessage() ),
			array( 'status' => 500 )
		);
	}
}
