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
 * It must be served by PHP. A rewrite rule and the sn_machine query var
 * (inc/machine-routes.php) resolve the path, and the handler below answers it
 * at template_redirect (priority 0).
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
 * Is this request for /humans.txt? Pure helper (takes the sn_machine query
 * var, inc/machine-routes.php) so it is testable without WordPress.
 *
 * @param string $route The sn_machine query var.
 * @return bool
 */
function sn_humans_txt_is_request( $route ) {
	return 'humans' === $route;
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
	// The theme header comes back HTML-escaped ("Signal &amp; Noise"); this
	// file is plain text.
	$name    = html_entity_decode( (string) $theme->get( 'Name' ), ENT_QUOTES, 'UTF-8' );
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
		'https://orcid.org/0009-0006-8151-5920',
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
 * Emit the text/plain header + body. Split out from the template_redirect
 * handler so it is testable without exit().
 *
 * The rewrite rule in inc/machine-routes.php resolves the path as a route, not
 * a 404, so core already answers 200; the handler only reads the sn_machine
 * query var.
 */
function sn_humans_txt_send() {
	header( 'Content-Type: text/plain; charset=' . get_option( 'blog_charset', 'UTF-8' ) );
	if ( function_exists( 'sn_edge_cache_send_machine' ) ) {
		sn_edge_cache_send_machine();
	}
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- plain-text body built from theme-header values, literals and the companion plugin's colophon facts (its own literals); esc_html would corrupt the "&" in "Signal & Noise" inside a text/plain document.
	echo sn_humans_txt_body();
}

/**
 * template_redirect handler: serve /humans.txt, then exit so WP's template
 * loader never runs.
 */
function sn_humans_txt_maybe_serve() {
	$req = (string) get_query_var( 'sn_machine' );
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
