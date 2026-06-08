<?php
/**
 * Signal & Noise — reader-facing Notes-scoped command palette.
 *
 * ⌘/Ctrl-K or "/" (outside form fields) opens an accessible overlay: search
 * notes (→ /notes/?s=), jump to recent notes, jump to pillar pages. Front-end
 * only — distinct from the plugin's wp-admin @wordpress/commands palette (they
 * never coexist on one document). The data island uses JSON_HEX_TAG so a note
 * titled "</script>" can't break out of the inline tag.
 *
 * @package SignalNoise
 * @since 9.11.0
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Build the JS data island. Pure + testable.
 *
 * Recent notes come from a bounded publish-only WP_Query (8 items, no_found_rows);
 * pillars from the theme's own sn_theme_pillar_descriptors() (function_exists-
 * guarded so the palette degrades to search + recent when it is absent).
 *
 * @return array{notesUrl:string, recent:array<int,array{t:string,u:string}>, pillars:array<int,array{t:string,u:string}>}
 */
function sn_cmdk_build_data() {
	$recent = array();
	$q      = new WP_Query( array(
		'post_type'           => 'post',
		'post_status'         => 'publish',
		'posts_per_page'      => 8,
		'orderby'             => 'date',
		'order'               => 'DESC',
		'no_found_rows'       => true,
		'ignore_sticky_posts' => true,
	) );
	foreach ( $q->posts as $p ) {
		$recent[] = array(
			't' => html_entity_decode( get_the_title( $p ), ENT_QUOTES ),
			'u' => get_permalink( $p ),
		);
	}
	wp_reset_postdata();

	$pillars = array();
	if ( function_exists( 'sn_theme_pillar_descriptors' ) ) {
		foreach ( sn_theme_pillar_descriptors() as $d ) {
			$pillars[] = array(
				't' => $d['title'],
				'u' => home_url( '/' . $d['slug'] . '/' ),
			);
		}
	}

	return array(
		'notesUrl' => home_url( '/notes/' ),
		'recent'   => $recent,
		'pillars'  => $pillars,
	);
}

/**
 * Enqueue palette CSS + JS site-wide (no is_singular guard — palette is global).
 * The data island is injected before the deferred module via wp_add_inline_script.
 */
function sn_cmdk_enqueue() {
	wp_enqueue_style(
		'sn-command-palette',
		get_theme_file_uri( 'assets/css/command-palette.css' ),
		array( 'sn-components' ),
		sn_asset_ver( 'assets/css/command-palette.css' )
	);
	wp_enqueue_script(
		'sn-command-palette',
		get_theme_file_uri( 'assets/js/command-palette.js' ),
		array(),
		sn_asset_ver( 'assets/js/command-palette.js' ),
		array( 'in_footer' => true, 'strategy' => 'defer' )
	);
	$json = wp_json_encode( sn_cmdk_build_data(), JSON_HEX_TAG | JSON_UNESCAPED_SLASHES );
	wp_add_inline_script( 'sn-command-palette', 'window.SN_CMDK=' . $json . ';', 'before' );
}

/**
 * Print the visible trigger button on wp_footer (no template edit needed).
 * aria-keyshortcuts advertises ⌘K / Ctrl-K / "/" to assistive tech.
 */
function sn_cmdk_print_trigger() {
	echo '<button type="button" class="sn-cmdk-trigger" aria-haspopup="dialog" aria-controls="sn-cmdk" aria-keyshortcuts="Control+K Meta+K /">'
		. '<span class="sn-cmdk-trigger-label">Search</span>'
		. '<kbd class="sn-cmdk-hint" aria-hidden="true">&#8984;K</kbd></button>' . "\n";
}

if ( ! defined( 'SN_CMDK_TEST' ) || ! SN_CMDK_TEST ) {
	add_action( 'wp_enqueue_scripts', 'sn_cmdk_enqueue', 30 );
	add_action( 'wp_footer', 'sn_cmdk_print_trigger', 5 );
}
