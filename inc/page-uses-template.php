<?php
/**
 * Signal & Noise — /uses gear page (D6).
 *
 * A postless virtual route at /about/uses (a child of the About/bio page) that
 * renders the indie-web "what I use" page: the hardware + software behind the
 * work, grouped, in the brutalist row idiom. Server-rendered, no JS. The gear
 * list lives in inc/uses-data.php (the edit surface), filterable via
 * `sn_uses_groups`.
 *
 * Why a virtual route (not a real Page): the list is theme-generated from the
 * data file, so there is nothing for an author to edit in wp-admin. We match on
 * REQUEST_URI at template_redirect (priority 0) — the flush-free mechanism
 * /index and /humans.txt use (no add_rewrite_rule; the theme must not flush —
 * that is the plugin's job). Priority 0 also beats WP's redirect_canonical (it
 * runs at priority 10), so the nested path under the real /about page is not
 * canonicalised away before we exit. The render file forces HTTP 200 because a
 * postless path inherits WP's handle_404() (WORDPRESS-REFERENCE gotcha #40).
 *
 * @package SignalNoise
 * @since 10.10.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Is this request for /about/uses? The /uses page is a child of the About (bio)
 * page. Pure helper (takes the path) so it is testable without $_SERVER. Matches
 * /about/uses, /about/uses/, bare about/uses, and /about/uses?… ; rejects
 * near-misses like /about/usesful, /about/uses.bak, and the bare /uses or /about.
 *
 * @param string $uri Request URI (may carry a query string).
 * @return bool
 */
function sn_uses_is_request( $uri ) {
	$path = strtok( (string) $uri, '?' );
	$path = '/' . trim( (string) $path, '/' );
	return ( '/about/uses' === $path );
}

/**
 * Whether the current request is the /about/uses page (reads $_SERVER).
 *
 * @return bool
 */
function sn_uses_is_uses_request() {
	$req = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
	return sn_uses_is_request( $req );
}

/**
 * Document title for the gear page: "Uses — <site>".
 *
 * @return string
 */
function sn_uses_title() {
	$site = get_bloginfo( 'name' );
	return $site ? 'Uses — ' . $site : 'Uses';
}

/**
 * PRIMARY override: short-circuit on template_redirect, render the page, and
 * exit so WP's template loader never runs. Priority 0. Falls through (no exit)
 * if the render file is missing on disk.
 */
function sn_uses_maybe_render() {
	if ( ! sn_uses_is_uses_request() ) {
		return;
	}
	$render = get_theme_file_path( 'inc/page-uses-render.php' );
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
function sn_uses_template_include( $template ) {
	if ( ! sn_uses_is_uses_request() ) {
		return $template;
	}
	$render = get_theme_file_path( 'inc/page-uses-render.php' );
	return file_exists( $render ) ? $render : $template;
}

/**
 * Set the document <title> for the gear page (the request never resolves to a
 * real Page, so WP's title resolver would otherwise emit the site name alone).
 *
 * @param string $title Incoming title.
 * @return string
 */
function sn_uses_document_title( $title ) {
	return sn_uses_is_uses_request() ? sn_uses_title() : $title;
}

/**
 * Enqueue the gear-page stylesheet on the /uses route only. Fired during the
 * render file's wp_head() (which triggers wp_enqueue_scripts), gated by the
 * route so it never loads elsewhere. Depends on sn-components for the shared
 * design tokens.
 */
function sn_uses_enqueue() {
	if ( ! sn_uses_is_uses_request() ) {
		return;
	}
	wp_enqueue_style(
		'sn-uses',
		get_theme_file_uri( 'assets/css/uses.css' ),
		array( 'sn-components' ),
		sn_asset_ver( 'assets/css/uses.css' )
	);
}

if ( ! defined( 'SN_USES_TEST' ) || ! SN_USES_TEST ) {
	add_action( 'template_redirect', 'sn_uses_maybe_render', 0 );
	add_filter( 'template_include', 'sn_uses_template_include', 999 );
	add_filter( 'pre_get_document_title', 'sn_uses_document_title', 999 );
	add_action( 'wp_enqueue_scripts', 'sn_uses_enqueue', 30 );
}
