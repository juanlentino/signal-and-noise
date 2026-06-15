<?php
/**
 * Signal & Noise — /index whole-site dossier (C3).
 *
 * A postless virtual route that renders a single brutalist "dossier" page
 * listing the whole site — the Notes corpus, the standalone Pages, and the
 * discography — in the tabular row idiom. Server-rendered, no JS required.
 *
 * Why a virtual route (not a real Page): the listing is theme-generated from
 * live queries, so there is nothing for an author to edit. We match on
 * REQUEST_URI at template_redirect (priority 0) — the same flush-free
 * mechanism /humans.txt and /notes use (no add_rewrite_rule; the theme must
 * not flush rewrites — that is the plugin's job). The render file forces HTTP
 * 200 because a postless path inherits WP's handle_404() (gotcha #40).
 *
 * @package SignalNoise
 * @since 10.7.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Is this request for /index? Pure helper (takes the path) so it is testable
 * without $_SERVER. Matches /index, /index/, bare index, and /index?… ; rejects
 * near-misses like /indexes or /index.bak.
 *
 * @param string $uri Request URI (may carry a query string).
 * @return bool
 */
function sn_index_is_request( $uri ) {
	$path = strtok( (string) $uri, '?' );
	$path = '/' . trim( (string) $path, '/' );
	return ( '/index' === $path );
}

/**
 * Whether the current request is the /index dossier (reads $_SERVER).
 *
 * @return bool
 */
function sn_index_is_index_request() {
	$req = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
	return sn_index_is_request( $req );
}

/**
 * Document title for the dossier: "Index — <site>".
 *
 * @return string
 */
function sn_index_title() {
	$site = get_bloginfo( 'name' );
	return $site ? 'Index — ' . $site : 'Index';
}

/**
 * PRIMARY override: short-circuit on template_redirect, render the dossier,
 * and exit so WP's template loader never runs. Priority 0 to beat anything
 * else. Falls through (no exit) if the render file is missing on disk.
 */
function sn_index_maybe_render() {
	if ( ! sn_index_is_index_request() ) {
		return;
	}
	$render = get_theme_file_path( 'inc/page-index-render.php' );
	if ( ! file_exists( $render ) ) {
		return;
	}
	include $render;
	exit;
}

/**
 * Belt-and-suspenders: also hook template_include (priority 999) for any code
 * path that reaches WP's template loader without going through template_redirect.
 *
 * @param string $template Resolved template path.
 * @return string
 */
function sn_index_template_include( $template ) {
	if ( ! sn_index_is_index_request() ) {
		return $template;
	}
	$render = get_theme_file_path( 'inc/page-index-render.php' );
	return file_exists( $render ) ? $render : $template;
}

/**
 * Set the document <title> for the dossier (the request never resolves to a
 * real Page, so WP's title resolver would otherwise emit the site name alone).
 *
 * @param string $title Incoming title.
 * @return string
 */
function sn_index_document_title( $title ) {
	return sn_index_is_index_request() ? sn_index_title() : $title;
}

/**
 * Enqueue the dossier stylesheet on the /index route only. Fired during the
 * render file's wp_head() (which triggers wp_enqueue_scripts), gated by the
 * route so it never loads elsewhere. Depends on sn-components for the shared
 * design tokens.
 */
function sn_index_enqueue() {
	if ( ! sn_index_is_index_request() ) {
		return;
	}
	wp_enqueue_style(
		'sn-index',
		get_theme_file_uri( 'assets/css/index.css' ),
		array( 'sn-components' ),
		sn_asset_ver( 'assets/css/index.css' )
	);
}

if ( ! defined( 'SN_INDEX_TEST' ) || ! SN_INDEX_TEST ) {
	add_action( 'template_redirect', 'sn_index_maybe_render', 0 );
	add_filter( 'template_include', 'sn_index_template_include', 999 );
	add_filter( 'pre_get_document_title', 'sn_index_document_title', 999 );
	add_action( 'wp_enqueue_scripts', 'sn_index_enqueue', 30 );
}
