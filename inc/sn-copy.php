<?php
/**
 * Signal & Noise: the shared clipboard helper (assets/js/sn-copy.js),
 * registered once for the two scripts that copy: the share row
 * (assets/js/note-share.js) and the section links (assets/js/article-toc.js).
 *
 * @package SignalNoise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the helper; a dependent's enqueue then prints it first. Calling it
 * twice is harmless: core refuses a duplicate handle.
 *
 * @return void
 */
function sn_copy_register() {
	wp_register_script(
		'sn-copy',
		get_theme_file_uri( 'assets/js/sn-copy.js' ),
		array(),
		sn_asset_ver( 'assets/js/sn-copy.js' ),
		array(
			'in_footer' => true,
			'strategy'  => 'defer',
		)
	);
}
