<?php
/**
 * Signal & Noise — Frontend output modifications.
 *
 * - Skip-to-content link (a11y)
 * - oEmbed filter forcing dark theme + square corners on Spotify embeds
 *   (through WP_HTML_Tag_Processor since #383)
 * - Strip WordPress's generator meta tag (fingerprinting reduction): the
 *   documented way, remove_action + the_generator, and nothing else. The
 *   page-wide output buffer that used to regex the rendered page for a
 *   second copy went with #383: it stripped nothing (no active plugin emits
 *   one, and /notes/ routes never ran it), and an output-buffer callback is
 *   the one shape that can send an empty 200 when its rewrite fails, which
 *   /provenance/ suffered twice (13.3.1). No such callback exists now.
 * - core/social-link path-relative URL shim (upstream core bug workaround)
 *
 * v10.49.0: every hook callback here is a NAMED function (they were
 * anonymous closures) so the behavior-bearing seams are testable; pinned in
 * tests/frontend-filters.php.
 *
 * @package SignalNoise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Accessibility: skip-to-content link, first element in <body>.
 */
function sn_skip_link() {
	echo '<a class="sn-skip-link" href="#wp--skip-link--target">Skip to content</a>';
}

/**
 * Force Spotify embeds to use dark theme and remove border-radius.
 *
 * #383: the iframe is read and written through WP_HTML_Tag_Processor, core's
 * own HTML API, where a regex on the src and a str_replace over the whole
 * markup used to do it. set_attribute() runs a URI attribute through
 * esc_url(), so the src serialises with `&#038;theme=0`; a browser reads it
 * as `&theme=0`, the same request as before.
 *
 * @param string $html oEmbed markup.
 * @param string $url  The embedded URL.
 * @return string
 */
function sn_spotify_embed_dark( $html, $url ) {
	if ( strpos( (string) $url, 'spotify.com' ) === false ) {
		return $html;
	}
	$tags = new WP_HTML_Tag_Processor( (string) $html );
	while ( $tags->next_tag( 'IFRAME' ) ) {
		// Add theme=0 (dark) to the iframe src.
		$src = $tags->get_attribute( 'src' );
		if ( is_string( $src ) && false !== strpos( $src, 'spotify' ) ) {
			$tags->set_attribute( 'src', $src . '&theme=0' );
		}
		// Square the inline border-radius.
		$style = $tags->get_attribute( 'style' );
		if ( is_string( $style ) ) {
			$squared = str_replace( 'border-radius: 12px', 'border-radius: 0', $style );
			if ( $squared !== $style ) {
				$tags->set_attribute( 'style', $squared );
			}
		}
	}
	return $tags->get_updated_html();
}

/**
 * Work around a WordPress core bug in render_block_core_social_link()
 * (wp-includes/blocks/social-link.php). Core prepends "https://" to any
 * URL that has no scheme and doesn't start with "//" or "#" — but it
 * misses path-relative URLs (starting with a single "/"). Result: a
 * social-link with url="/notes/feed/" renders as href="https:///notes/feed/"
 * (three slashes, empty host), which browsers normalize to "https://notes/feed/"
 * and route to a non-existent server.
 *
 * Fix: filter the parsed block attributes BEFORE core's render callback
 * runs. If we find a path-relative URL on a core/social-link block, swap
 * it for home_url($path) — which carries the correct scheme + host for
 * the current environment (dev, staging, prod). Core then sees a complete
 * URL and skips its broken prepend branch entirely.
 *
 * This is upstream-bug shaped: when WP core fixes their scheme check to
 * recognize the "starts with /" case, this filter becomes a no-op and
 * can be removed. Tracked at /docs (no upstream issue filed yet — file
 * one if you touch this again).
 *
 * @param array $parsed_block Parsed block (render_block_data).
 * @return array
 */
function sn_social_link_relative_url( $parsed_block ) {
	if ( 'core/social-link' !== ( $parsed_block['blockName'] ?? '' ) ) {
		return $parsed_block;
	}
	$url = $parsed_block['attrs']['url'] ?? '';
	// Match a single leading "/" (path-relative). Protocol-relative "//"
	// and fragment "#" URLs are already handled correctly by core.
	if ( '' !== $url && '/' === $url[0] && ( ! isset( $url[1] ) || '/' !== $url[1] ) ) {
		$parsed_block['attrs']['url'] = home_url( $url );
	}
	return $parsed_block;
}

if ( ! defined( 'SN_FRONTEND_FILTERS_TEST' ) || ! SN_FRONTEND_FILTERS_TEST ) {
	add_action( 'wp_body_open', 'sn_skip_link' );
	add_filter( 'embed_oembed_html', 'sn_spotify_embed_dark', 10, 2 );
	add_filter( 'render_block_data', 'sn_social_link_relative_url' );

	/**
	 * Security: strip WordPress's generator meta tag, the documented way.
	 * Unconditional, so it covers the /notes/ routes too (their
	 * template_redirect include-and-exit runs before any later hook).
	 */
	remove_action( 'wp_head', 'wp_generator' );
	add_filter( 'the_generator', '__return_empty_string' );

	/**
	 * Head-sweep (A4): drop the legacy xmlrpc-era discovery links core still
	 * emits at wp_head priority 10. Both advertise surfaces this site does not
	 * serve:
	 *   - rsd_link()         → <link rel="EditURI" … xmlrpc.php?rsd> (Really Simple
	 *                          Discovery — points at the disabled xmlrpc.php).
	 *   - wlwmanifest_link() → <link rel="wlwmanifest" … wlwmanifest.xml> (Windows
	 *                          Live Writer, retired in 2017).
	 * No explicit priority arg is needed: core registers both at the default
	 * priority 10, which is what remove_action() matches when omitted.
	 */
	remove_action( 'wp_head', 'rsd_link' );
	remove_action( 'wp_head', 'wlwmanifest_link' );
}
