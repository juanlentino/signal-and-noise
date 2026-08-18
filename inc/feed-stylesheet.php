<?php
/**
 * Signal & Noise — RSS feed stylesheet.
 *
 * Points the RSS2 feed at an XSL stylesheet so a browser renders it as a
 * readable page. The site names RSS as its ONLY endorsed channel ("No
 * subscription form. No schedule. Notes via RSS"), and a raw-XML feed reads as
 * a broken page to anyone without a reader installed.
 *
 * WHY THE STYLESHEET IS SERVED BY PHP AND NOT AS A STATIC FILE (v11.9.3):
 * shipping assets/feed.xsl statically returned `Content-Type:
 * application/octet-stream` — Apache has no MIME mapping for .xsl — and the
 * site sends `X-Content-Type-Options: nosniff`, which forbids the browser from
 * guessing a better type. A browser applies XSLT only for text/xsl,
 * application/xslt+xml or an XML type, so the stylesheet was fetched and then
 * ignored. Serving it through add_feed() lets us state the type outright and
 * does not depend on server MIME configuration.
 *
 * Routed with add_feed('xsl', …) exactly as inc/feed-json.php routes JSON Feed:
 * `?feed=xsl` resolves immediately with NO rewrite flush (the theme must never
 * flush — see JF-1 in feed-json.php), so it is live on a cold deploy.
 *
 * `rss_tag_pre` is a core do_action() that fires BETWEEN the XML declaration
 * and the opening <rss> tag (wp-includes/feed-rss2.php) — the only legal
 * position for an <?xml-stylesheet?> processing instruction. It is passed the
 * feed type, so comment feeds ('rss2-comments') are excluded.
 *
 * PRESENTATION ONLY, and it cannot break subscription: feed READERS parse the
 * XML and never fetch a stylesheet, and a browser without XSLT support shows
 * the same raw XML it showed before. The failure mode is the previous
 * behaviour — which is precisely what the octet-stream bug produced.
 *
 * @package SignalNoise
 * @since 11.9.2
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Absolute URL of the stylesheet route.
 *
 * Query-arg form on purpose: it resolves on every install without a flush,
 * mirroring feed-json.php's `?feed=json`.
 *
 * @return string
 */
function sn_feed_stylesheet_url() {
	/**
	 * Filter the RSS stylesheet URL. Return an empty string to serve the feed
	 * unstyled — the pre-11.9.2 behaviour.
	 *
	 * @param string $url Absolute URL of the stylesheet route.
	 */
	return (string) apply_filters( 'sn_feed_stylesheet_url', home_url( '/?feed=xsl' ) );
}

/**
 * The stylesheet body, with asset tokens resolved. Pure + testable: no headers,
 * no exit, no filesystem side effects beyond the read.
 *
 * {{THEME_URI}} must become an ABSOLUTE base — relative URLs inside XSLT output
 * resolve against the SOURCE document (the feed), not the stylesheet.
 *
 * @param string $raw Raw stylesheet source.
 * @param string $base Theme file base URI, trailing-slashed.
 * @return string
 */
function sn_feed_stylesheet_body( $raw, $base ) {
	return str_replace( '{{THEME_URI}}', trailingslashit( $base ), (string) $raw );
}

/**
 * add_feed('xsl') callback: serve the stylesheet with a type a browser will
 * actually apply.
 *
 * @return void
 */
function sn_feed_stylesheet_render() {
	$path = get_theme_file_path( 'assets/feed.xsl' );
	if ( ! file_exists( $path ) ) {
		status_header( 404 );
		exit;
	}
	header( 'Content-Type: text/xsl; charset=' . get_option( 'blog_charset' ) );
	header( 'X-Robots-Tag: noindex' );
	echo sn_feed_stylesheet_body( file_get_contents( $path ), get_theme_file_uri( '' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static theme asset, not user input.
	exit;
}

/**
 * init: register the feed route.
 *
 * @return void
 */
function sn_feed_stylesheet_register() {
	add_feed( 'xsl', 'sn_feed_stylesheet_render' );
}

/**
 * rss_tag_pre action: emit the stylesheet processing instruction.
 *
 * @param string $type Feed type passed by core ('rss2', 'rss2-comments', …).
 * @return void
 */
function sn_feed_stylesheet_pi( $type = '' ) {
	if ( 'rss2' !== $type ) {
		return;
	}
	$url = sn_feed_stylesheet_url();
	if ( '' === $url ) {
		return;
	}
	echo "\n" . '<?xml-stylesheet type="text/xsl" href="' . esc_url( $url ) . '"?>';
}

if ( ! defined( 'SN_FEED_STYLESHEET_TEST' ) || ! SN_FEED_STYLESHEET_TEST ) {
	add_action( 'init', 'sn_feed_stylesheet_register' );
	add_action( 'rss_tag_pre', 'sn_feed_stylesheet_pi' );
}
