<?php
/**
 * Signal & Noise — RSS feed stylesheet.
 *
 * Points the RSS2 feed at assets/feed.xsl so a browser renders it as a readable
 * page. The site names RSS as its ONLY endorsed channel ("No subscription form.
 * No schedule. Notes via RSS"), and until now clicking that link served raw XML
 * that reads as a broken page to anyone without a reader installed.
 *
 * `rss_tag_pre` is a core do_action() that fires BETWEEN the XML declaration and
 * the opening <rss> tag (wp-includes/feed-rss2.php) — the only legal position
 * for an <?xml-stylesheet?> processing instruction. It is passed the feed type,
 * so comment feeds ('rss2-comments') are excluded.
 *
 * PRESENTATION ONLY, and it cannot break subscription: feed READERS parse the
 * XML and never fetch a stylesheet, and a browser without XSLT support shows the
 * same raw XML it showed before. The failure mode is the previous behaviour.
 *
 * @package SignalNoise
 * @since 11.10.0
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

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

	/**
	 * Filter the RSS stylesheet URL. Return an empty string to serve the feed
	 * unstyled — the pre-11.10.0 behaviour.
	 *
	 * @param string $url Absolute URL of the XSL stylesheet.
	 */
	$url = apply_filters( 'sn_feed_stylesheet_url', get_theme_file_uri( 'assets/feed.xsl' ) );

	if ( ! is_string( $url ) || '' === $url ) {
		return;
	}

	echo "\n" . '<?xml-stylesheet type="text/xsl" href="' . esc_url( $url ) . '"?>';
}

if ( ! defined( 'SN_FEED_STYLESHEET_TEST' ) || ! SN_FEED_STYLESHEET_TEST ) {
	add_action( 'rss_tag_pre', 'sn_feed_stylesheet_pi' );
}
