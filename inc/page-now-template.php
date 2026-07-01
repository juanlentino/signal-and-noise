<?php
/**
 * Signal & Noise — /now page (indie-web now page).
 *
 * A postless virtual route at /now that renders what I'm focused on right
 * now: current projects, writing, and inputs. Server-rendered, no JS. The
 * content lives in inc/now-data.php (the edit surface), filterable via
 * `sn_now_sections`.
 *
 * Why a virtual route (not a real Page): the content is theme-generated from
 * the data file, so there is nothing for an author to edit in wp-admin. We
 * match on REQUEST_URI at template_redirect (priority 0) — the flush-free
 * mechanism /about/uses, /index and /humans.txt use (no add_rewrite_rule; the
 * theme must not flush — that is the plugin's job). The render file forces
 * HTTP 200 because a postless path inherits WP's handle_404()
 * (WORDPRESS-REFERENCE gotcha #40).
 *
 * @package SignalNoise
 * @since 10.21.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Is this request for /now? Pure helper (takes the path) so it is testable
 * without $_SERVER. Matches /now, /now/, bare now, and /now?… ; rejects
 * near-misses like /nowhere, /now.bak, and nested paths.
 *
 * @param string $uri Request URI (may carry a query string).
 * @return bool
 */
function sn_now_is_request( $uri ) {
	$path = strtok( (string) $uri, '?' );
	$path = '/' . trim( (string) $path, '/' );
	return ( '/now' === $path );
}

/**
 * Whether the current request is the /now page (reads $_SERVER).
 *
 * @return bool
 */
function sn_now_is_now_request() {
	$req = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
	return sn_now_is_request( $req );
}

/**
 * Document title for the now page: "Now — <site>".
 *
 * @return string
 */
function sn_now_title() {
	$site = get_bloginfo( 'name' );
	return $site ? 'Now — ' . $site : 'Now';
}

/**
 * PRIMARY override: short-circuit on template_redirect, render the page, and
 * exit so WP's template loader never runs. Priority 0. Falls through (no exit)
 * if the render file is missing on disk.
 */
function sn_now_maybe_render() {
	if ( ! sn_now_is_now_request() ) {
		return;
	}
	$render = get_theme_file_path( 'inc/page-now-render.php' );
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
function sn_now_template_include( $template ) {
	if ( ! sn_now_is_now_request() ) {
		return $template;
	}
	$render = get_theme_file_path( 'inc/page-now-render.php' );
	return file_exists( $render ) ? $render : $template;
}

/**
 * Set the document <title> for the now page (the request never resolves to a
 * real Page, so WP's title resolver would otherwise emit the site name alone).
 *
 * @param string $title Incoming title.
 * @return string
 */
function sn_now_document_title( $title ) {
	return sn_now_is_now_request() ? sn_now_title() : $title;
}

/**
 * Enqueue the now-page stylesheet on the /now route only. Fired during the
 * render file's wp_head() (which triggers wp_enqueue_scripts), gated by the
 * route so it never loads elsewhere. Depends on sn-components for the shared
 * design tokens.
 */
function sn_now_enqueue() {
	if ( ! sn_now_is_now_request() ) {
		return;
	}
	wp_enqueue_style(
		'sn-now',
		get_theme_file_uri( 'assets/css/now.css' ),
		array( 'sn-components' ),
		sn_asset_ver( 'assets/css/now.css' )
	);
}

if ( ! defined( 'SN_NOW_TEST' ) || ! SN_NOW_TEST ) {
	add_action( 'template_redirect', 'sn_now_maybe_render', 0 );
	add_filter( 'template_include', 'sn_now_template_include', 999 );
	add_filter( 'pre_get_document_title', 'sn_now_document_title', 999 );
	add_action( 'wp_enqueue_scripts', 'sn_now_enqueue', 30 );
}
