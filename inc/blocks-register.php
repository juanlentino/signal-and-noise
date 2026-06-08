<?php
/**
 * Signal & Noise — custom block registration (sidenote, pull-quote).
 *
 * Buildless dynamic blocks. editorScript is a manually-registered handle with
 * explicit deps — NOT a file: path (which would load with empty deps and throw
 * 'wp is undefined' because there is no .asset.php sidecar in a no-build theme).
 * Block categories use the block_categories_all filter — separate from the
 * pattern-category registry in inc/patterns.php.
 *
 * @package SignalNoise
 * @since 9.11.0
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function signal_noise_register_block_editor_script() {
	wp_register_script(
		'signal-noise-blocks-editor',
		get_theme_file_uri( 'blocks/editor.js' ),
		array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components' ),
		wp_get_theme()->get( 'Version' ),
		true
	);
}

function signal_noise_register_blocks() {
	register_block_type( __DIR__ . '/../blocks/sidenote' );
	register_block_type( __DIR__ . '/../blocks/pull-quote' );
}

function signal_noise_block_category( $categories ) {
	return array_merge( $categories, array( array(
		'slug'  => 'signal-noise',
		'title' => 'Signal & Noise',
	) ) );
}

if ( ! defined( 'SN_BLOCKS_TEST' ) || ! SN_BLOCKS_TEST ) {
	add_action( 'init', 'signal_noise_register_block_editor_script' );
	add_action( 'init', 'signal_noise_register_blocks' );
	add_filter( 'block_categories_all', 'signal_noise_block_category' );
}
