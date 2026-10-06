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
 * style.css. The profile URLs are hardcoded in lockstep with parts/footer.html.
 * The stack lines are NOT copied: since 2026-10-06 they are the colophon's own
 * facts, read from the plugin (sn_colophon_plain_facts()), so the page and this
 * file cannot drift. Only the plugin-absent fallback is written here.
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
 * values, literals and the plugin's colophon facts, no user input.
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
	);
	// 2026-10-06: one source of truth. The colophon's facts live in the
	// companion plugin ([sn_colophon]); humans.txt prints the same words. The
	// fallback (plugin off) says nothing the colophon contradicts.
	$facts = function_exists( 'sn_colophon_plain_facts' ) ? (array) sn_colophon_plain_facts() : array(
		'Platform'         => 'WordPress with Full Site Editing, no page builder.',
		'Code'             => 'hand-written PHP, plain JavaScript and theme.json, no build step.',
		'Hosting'          => 'Cloudways, with Cloudflare for the CDN and DNS.',
		'Type'             => 'Bebas Neue for headings, DM Mono for body text.',
	);
	foreach ( $facts as $label => $text ) {
		$lines[] = $label . ': ' . $text;
	}
	array_push( $lines, '', '/* THEME */', $name . ' v' . $version, '' );

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
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- plain-text body built from theme-header values, literals and the companion plugin's colophon facts (its own literals); esc_html would corrupt the "&" in "Signal & Noise" inside a text/plain document.
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
