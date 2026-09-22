<?php
/**
 * Signal & Noise - block stylesheets registered through core's own API.
 *
 * WHY THIS EXISTS. `assets/css/components.css` grew into a 73KB catch-all: the
 * service cards, the discography, the provenance pillars and the note footers
 * all lived in one file that every page downloaded whole. WordPress has an
 * answer for exactly this and the theme was already using half of it.
 *
 * `wp_enqueue_block_style( $block_name, $args )` registers a stylesheet against
 * a block. Core then:
 *
 *   - enqueues it ONLY when that block actually renders on the page, and
 *   - INLINES it into <head> when `path` is supplied, so an on-demand sheet
 *     costs no extra request.
 *
 * Theme Handbook: https://developer.wordpress.org/themes/features/block-stylesheets/
 *
 * WHY NOT `block.json` "style". Two of the theme's own blocks already declare
 * `"style": "file:./style.css"` (blocks/sidenote, blocks/pillar-essays) and that
 * is the right shape when a sheet serves exactly one block. It cannot serve two:
 * the path is relative to a single block's directory. The rules this file
 * registers are grouped selectors written for `related-notes` and `cited-by`
 * together, so they are one sheet registered against both block names under one
 * handle. Core's dependency machinery dedupes by handle, so a page rendering
 * both blocks still enqueues the file once.
 *
 * This module is the migration surface for the rest of the components.css
 * split: each future move adds a sheet under assets/css/blocks/ and one
 * registration here. Anything registered here must NOT also appear in
 * inc/asset-combine.php's file list, or it ships to every page again and the
 * on-demand gate is defeated.
 *
 * @package SignalNoise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the theme's block-scoped stylesheets.
 *
 * Runs on `init`, which is where the handbook's example registers and where the
 * blocks themselves are registered (inc/blocks-php-only.php, priority 11).
 *
 * @return void
 */
function sn_register_block_styles() {
	$related = array(
		'handle' => 'sn-related-notes',
		'src'    => get_theme_file_uri( 'assets/css/blocks/related-notes.css' ),
		'path'   => get_theme_file_path( 'assets/css/blocks/related-notes.css' ),
		'ver'    => sn_asset_ver( 'assets/css/blocks/related-notes.css' ),
	);

	// One sheet, one handle, two blocks: the rules are grouped selectors serving
	// both footers, and whichever block renders first brings the sheet in.
	wp_enqueue_block_style( 'signal-noise/related-notes', $related );
	wp_enqueue_block_style( 'signal-noise/cited-by', $related );
}
add_action( 'init', 'sn_register_block_styles' );
