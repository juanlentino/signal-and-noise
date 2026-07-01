<?php
/**
 * Signal & Noise — /accessibility statement page.
 *
 * A postless virtual route at /accessibility that renders the site's
 * accessibility statement: conformance target, measures actually shipped,
 * honest limitations, and the feedback channel. Server-rendered, no JS.
 * No data file — the statement content is static render code
 * (inc/page-accessibility-render.php), owner copy-reviewed at release.
 *
 * Same virtual-route mechanism as /now and /about/uses: REQUEST_URI match at
 * template_redirect priority 0 (flush-free; the theme must not flush — that
 * is the plugin's job). The render file forces HTTP 200 because a postless
 * path inherits WP's handle_404() (WORDPRESS-REFERENCE gotcha #40).
 *
 * @package SignalNoise
 * @since 10.21.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Is this request for /accessibility? Pure helper (takes the path) so it is
 * testable without $_SERVER. Matches /accessibility, /accessibility/, bare
 * accessibility, and /accessibility?… ; rejects near-misses and nested paths.
 *
 * @param string $uri Request URI (may carry a query string).
 * @return bool
 */
function sn_a11y_is_request( $uri ) {
	$path = strtok( (string) $uri, '?' );
	$path = '/' . trim( (string) $path, '/' );
	return ( '/accessibility' === $path );
}

/**
 * Whether the current request is the /accessibility page (reads $_SERVER).
 *
 * @return bool
 */
function sn_a11y_is_a11y_request() {
	$req = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
	return sn_a11y_is_request( $req );
}

/**
 * Document title for the statement page: "Accessibility — <site>".
 *
 * @return string
 */
function sn_a11y_title() {
	$site = get_bloginfo( 'name' );
	return $site ? 'Accessibility — ' . $site : 'Accessibility';
}

/**
 * PRIMARY override: short-circuit on template_redirect, render the page, and
 * exit so WP's template loader never runs. Priority 0. Falls through (no exit)
 * if the render file is missing on disk.
 */
function sn_a11y_maybe_render() {
	if ( ! sn_a11y_is_a11y_request() ) {
		return;
	}
	$render = get_theme_file_path( 'inc/page-accessibility-render.php' );
	if ( ! file_exists( $render ) ) {
		return;
	}
	include $render;
	exit;
}

/**
 * Belt-and-suspenders: also hook template_include (priority 999) for any path
 * that reaches WP's template loader without going through template_redirect.
 *
 * @param string $template Resolved template path.
 * @return string
 */
function sn_a11y_template_include( $template ) {
	if ( ! sn_a11y_is_a11y_request() ) {
		return $template;
	}
	$render = get_theme_file_path( 'inc/page-accessibility-render.php' );
	return file_exists( $render ) ? $render : $template;
}

/**
 * Set the document <title> for the statement page (the request never resolves
 * to a real Page, so WP's title resolver would otherwise emit the site name alone).
 *
 * @param string $title Incoming title.
 * @return string
 */
function sn_a11y_document_title( $title ) {
	return sn_a11y_is_a11y_request() ? sn_a11y_title() : $title;
}

/**
 * Enqueue the statement stylesheet on the /accessibility route only. Fired
 * during the render file's wp_head() (which triggers wp_enqueue_scripts),
 * gated by the route so it never loads elsewhere. Depends on sn-components
 * for the shared design tokens.
 */
function sn_a11y_enqueue() {
	if ( ! sn_a11y_is_a11y_request() ) {
		return;
	}
	wp_enqueue_style(
		'sn-a11y',
		get_theme_file_uri( 'assets/css/accessibility.css' ),
		array( 'sn-components' ),
		sn_asset_ver( 'assets/css/accessibility.css' )
	);
}

if ( ! defined( 'SN_A11Y_TEST' ) || ! SN_A11Y_TEST ) {
	add_action( 'template_redirect', 'sn_a11y_maybe_render', 0 );
	add_filter( 'template_include', 'sn_a11y_template_include', 999 );
	add_filter( 'pre_get_document_title', 'sn_a11y_document_title', 999 );
	add_action( 'wp_enqueue_scripts', 'sn_a11y_enqueue', 30 );
}
