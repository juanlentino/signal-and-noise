<?php
/**
 * Signal & Noise — Frontend output modifications.
 *
 * - Skip-to-content link (a11y)
 * - oEmbed filter forcing dark theme + square corners on Spotify embeds
 * - Strip WordPress + plugin generator meta tags (fingerprinting reduction)
 *
 * @package SignalNoise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Accessibility: Add skip-to-content link as first element in body.
 */
add_action( 'wp_body_open', function() {
	echo '<a class="sn-skip-link" href="#wp--skip-link--target">Skip to content</a>';
} );

/**
 * Force Spotify embeds to use dark theme and remove border-radius.
 */
add_filter( 'embed_oembed_html', function( $html, $url ) {
	if ( strpos( $url, 'spotify.com' ) !== false ) {
		// Add theme=0 (dark) to iframe src
		$html = preg_replace(
			'/src="([^"]*spotify[^"]*)"/',
			'src="$1&theme=0"',
			$html
		);
		// Strip inline border-radius
		$html = str_replace( 'border-radius: 12px', 'border-radius: 0', $html );
	}
	return $html;
}, 10, 2 );

/**
 * Security: Strip WordPress and plugin generator meta tags.
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

/**
 * Work around a WordPress core bug in render_block_core_social_link()
 * (wp-includes/blocks/social-link.php). Core prepends "https://" to any
 * URL that has no scheme and doesn't start with "//" or "#" — but it
 * misses path-relative URLs (starting with a single "/"). Result: a
 * social-link with url="/notes/feed/" renders as href="https:///notes/feed/"
 * (three slashes, empty host), which browsers normalise to "https://notes/feed/"
 * and route to a non-existent server.
 *
 * Fix: filter the parsed block attributes BEFORE core's render callback
 * runs. If we find a path-relative URL on a core/social-link block, swap
 * it for home_url($path) — which carries the correct scheme + host for
 * the current environment (dev, staging, prod). Core then sees a complete
 * URL and skips its broken prepend branch entirely.
 *
 * This is upstream-bug shaped: when WP core fixes their scheme check to
 * recognise the "starts with /" case, this filter becomes a no-op and
 * can be removed. Tracked at /docs (no upstream issue filed yet — file
 * one if you touch this again).
 */
add_filter( 'render_block_data', function( $parsed_block ) {
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
} );

/**
 * Output buffer: strip remaining generator meta tags from plugins that emit
 * raw <meta name="generator"> inline rather than via the_generator().
 *
 * Caveat: inc/page-notes-template.php registers a template_redirect at
 * priority 0 that `include + exit`s, bypassing every later template_redirect
 * hook — including this ob_start — so this buffer does not run on /notes/
 * routes. The wp_head generator strip above (remove_action + the_generator
 * filter) is unconditional and covers those routes regardless.
 */
add_action( 'template_redirect', function() {
	ob_start( function( $html ) {
		$html = preg_replace( '/<meta name="generator"[^>]*>\n?/i', '', $html );
		return $html;
	});
} );
