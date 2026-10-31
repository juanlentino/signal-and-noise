<?php
/**
 * Signal & Noise: the shared keyboard helper script (assets/js/sn-keys.js),
 * registered once for the two scripts that depend on it: the search palette
 * (inc/command-palette.php, every page) and the note shortcuts
 * (inc/keyboard-nav.php, single notes). See the script's header for why it
 * is one file (one single-key shortcut switch for both, WCAG 2.1.4).
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
function sn_keys_register() {
	wp_register_script(
		'sn-keys',
		get_theme_file_uri( 'assets/js/sn-keys.js' ),
		array(),
		sn_asset_ver( 'assets/js/sn-keys.js' ),
		array(
			'in_footer' => true,
			'strategy'  => 'defer',
		)
	);
}
