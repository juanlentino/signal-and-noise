<?php
/**
 * Signal & Noise — /contact/personal page.
 *
 * A postless virtual route at /contact/personal (a child of the /contact page)
 * that renders the "personal requests" page: the honest, Casey-Neistat-shaped
 * note explaining that synchronous-time asks (coffee, intros, mentorship, "pick
 * your brain") are a no, and why. Server-rendered, no JS. The copy lives in
 * inc/page-personal-render.php (sn_personal_content_blocks() — the edit surface),
 * authored as block markup and rendered through do_blocks so it inherits the
 * theme's typography + colour presets with no bespoke CSS.
 *
 * Why a virtual route (not a real Page): the page is theme-authored content with
 * nothing for an author to edit in wp-admin, and the project ships content as
 * theme files via Git → the WP updater (never DB edits). We match on REQUEST_URI
 * at template_redirect (priority 0) — the flush-free mechanism /about/uses,
 * /index and /humans.txt use (no add_rewrite_rule; the theme must not flush —
 * that is the plugin's job). Priority 0 also beats WP's redirect_canonical (it
 * runs at priority 10), so the nested path under the real /contact page is not
 * canonicalised away before we exit. The render file forces HTTP 200 because a
 * postless path inherits WP's handle_404() (WORDPRESS-REFERENCE gotcha #40).
 * Directly mirrors inc/page-uses-template.php (the /about/uses precedent).
 *
 * @package SignalNoise
 * @since 10.12.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Is this request for /contact/personal? The Personal page is a child of the
 * /contact page. Pure helper (takes the path) so it is testable without $_SERVER.
 * Matches /contact/personal, /contact/personal/, bare contact/personal, and
 * /contact/personal?… ; rejects near-misses like /contact/personalize,
 * /contact/personal.bak, and the bare /personal or /contact.
 *
 * @param string $uri Request URI (may carry a query string).
 * @return bool
 */
function sn_personal_is_request( $uri ) {
	$path = strtok( (string) $uri, '?' );
	$path = '/' . trim( (string) $path, '/' );
	return ( '/contact/personal' === $path );
}

/**
 * Whether the current request is the /contact/personal page (reads $_SERVER).
 *
 * @return bool
 */
function sn_personal_is_personal_request() {
	$req = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
	return sn_personal_is_request( $req );
}

/**
 * Document title for the Personal page: "Personal — <site>".
 *
 * @return string
 */
function sn_personal_title() {
	$site = get_bloginfo( 'name' );
	return $site ? 'Personal — ' . $site : 'Personal';
}

/**
 * PRIMARY override: short-circuit on template_redirect, render the page, and
 * exit so WP's template loader never runs. Priority 0. Falls through (no exit)
 * if the render file is missing on disk.
 */
function sn_personal_maybe_render() {
	if ( ! sn_personal_is_personal_request() ) {
		return;
	}
	$render = get_theme_file_path( 'inc/page-personal-render.php' );
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
function sn_personal_template_include( $template ) {
	if ( ! sn_personal_is_personal_request() ) {
		return $template;
	}
	$render = get_theme_file_path( 'inc/page-personal-render.php' );
	return file_exists( $render ) ? $render : $template;
}

/**
 * Set the document <title> for the Personal page (the request never resolves to
 * a real Page, so WP's title resolver would otherwise emit the site name alone).
 *
 * @param string $title Incoming title.
 * @return string
 */
function sn_personal_document_title( $title ) {
	return sn_personal_is_personal_request() ? sn_personal_title() : $title;
}

if ( ! defined( 'SN_PERSONAL_TEST' ) || ! SN_PERSONAL_TEST ) {
	add_action( 'template_redirect', 'sn_personal_maybe_render', 0 );
	add_filter( 'template_include', 'sn_personal_template_include', 999 );
	add_filter( 'pre_get_document_title', 'sn_personal_document_title', 999 );
}
