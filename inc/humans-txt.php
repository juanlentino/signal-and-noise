<?php
/**
 * Signal & Noise — humans.txt + maker's mark (C4).
 *
 * Serves a flat /humans.txt (the humanstxt.org / IndieWeb convention: "the
 * humans behind the site, and the tech"), advertises it with a rel=author
 * head link, and leaves one dry maker's-mark comment in <head>.
 *
 * WHY A VIRTUAL ROUTE. The theme is installed under wp-content/themes/, so a
 * static humans.txt shipped in the theme is never reachable at the site root.
 * It must be served by PHP. We match on REQUEST_URI at template_redirect
 * (priority 0) — the same lightweight, flush-free mechanism the /notes index
 * uses (inc/page-notes-template.php). No add_rewrite_rule: the theme must not
 * flush rewrites (that is the plugin's job).
 *
 * Owner + theme facts come from wp_get_theme() so they never drift from
 * style.css. The profile URLs and stack lines are hardcoded in lockstep with
 * parts/footer.html and patterns/colophon.php — keep all three in sync.
 *
 * @package SignalNoise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Is this request for /humans.txt? Pure helper (takes the path) so it is
 * testable without $_SERVER.
 *
 * @param string $uri Request URI (may carry a query string).
 * @return bool
 */
function sn_humans_txt_is_request( $uri ) {
	$path = strtok( (string) $uri, '?' );
	$path = '/' . trim( (string) $path, '/' );
	return ( '/humans.txt' === $path );
}

/**
 * Build the plain-text humans.txt body. Trusted by construction: theme-header
 * values + hardcoded literals, no user input.
 *
 * @return string
 */
function sn_humans_txt_body() {
	$theme   = wp_get_theme();
	$author  = (string) $theme->get( 'Author' );
	$name    = (string) $theme->get( 'Name' );
	$version = (string) $theme->get( 'Version' );

	$lines = array(
		'/* TEAM */',
		$author . ' - author, producer, audio engineer',
		'Site: https://juanlentino.com',
		'Contact: https://juanlentino.com/contact/',
		'',
		'/* PROFILES */',
		'https://open.spotify.com/intl-es/artist/2R3HjldxV2PpYp9DQwMPq0',
		'https://www.linkedin.com/in/juanlentino/',
		'https://www.instagram.com/juan_lentino/',
		'https://x.com/juan_lentino',
		'',
		'/* TECHNOLOGY */',
		'Standards - HTML5, CSS3, WordPress Full Site Editing, PHP 8.0+',
		'Type - Bebas Neue (display), DM Mono (body & UI)',
		'Build - buildless: hand-written PHP, theme.json, vanilla ES5. No bundler.',
		'Hosting - Cloudways, Cloudflare CDN & DNS',
		'Tooling - companion plugin Signal & Noise Tools for SEO, search & ops',
		'',
		'/* THEME */',
		$name . ' v' . $version,
		'',
	);

	return implode( "\n", $lines ) . "\n";
}

/**
 * Emit the 200 status + text/plain header + body. Split out from the
 * template_redirect handler so it is testable without exit().
 *
 * status_header( 200 ) is REQUIRED: /humans.txt is a virtual path with no
 * backing post, so WP's handle_404() already committed a 404 + nocache headers
 * during $wp->main(), which runs BEFORE template_redirect. Without this the
 * advertised resource would return its body under a 404 status, and
 * status-aware crawlers/IndieWeb tooling would treat it as nonexistent. (The
 * sibling /notes route avoids this only because /notes is a real published
 * Page that already resolved to 200.)
 */
function sn_humans_txt_send() {
	if ( function_exists( 'status_header' ) ) {
		status_header( 200 );
	}
	header( 'Content-Type: text/plain; charset=' . get_option( 'blog_charset', 'UTF-8' ) );
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- plain-text body built from theme-header values + hardcoded literals; esc_html would corrupt the "&" in "Signal & Noise" inside a text/plain document.
	echo sn_humans_txt_body();
}

/**
 * template_redirect handler: serve /humans.txt, then exit so WP's template
 * loader never runs.
 */
function sn_humans_txt_maybe_serve() {
	$req = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
	if ( ! sn_humans_txt_is_request( $req ) ) {
		return;
	}
	sn_humans_txt_send();
	exit;
}

/**
 * <head>: advertise humans.txt for autodiscovery (humanstxt.org convention)
 * and leave one dry maker's-mark comment. Static content — no user data.
 */
function sn_humans_txt_head_links() {
	printf( '<link rel="author" type="text/plain" href="%s">' . "\n", esc_url( home_url( '/humans.txt' ) ) );
	echo "<!-- Signal & Noise - built by Juan Lentino · juanlentino.com · /humans.txt -->\n";
}

if ( ! defined( 'SN_HUMANS_TXT_TEST' ) || ! SN_HUMANS_TXT_TEST ) {
	add_action( 'template_redirect', 'sn_humans_txt_maybe_serve', 0 );
	add_action( 'wp_head', 'sn_humans_txt_head_links' );
}
