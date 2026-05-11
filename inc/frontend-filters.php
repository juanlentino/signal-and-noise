<?php
/**
 * Signal & Noise — Frontend output modifications.
 *
 * - Skip-to-content link (a11y)
 * - oEmbed filter forcing dark theme + square corners on Spotify embeds
 * - Strip WordPress + plugin generator meta tags (fingerprinting reduction)
 * - Output buffer that removes Cloudflare Turnstile off the contact page
 *   (~17 KiB render-blocking saved on everything-but-contact)
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
 * Output buffer: strip remaining generator meta tags from plugins and, on
 * non-contact pages, the Cloudflare Turnstile challenge script.
 */
add_action( 'template_redirect', function() {
	ob_start( function( $html ) {
		// Strip generator meta tags.
		$html = preg_replace( '/<meta name="generator"[^>]*>\n?/i', '', $html );

		// Strip Cloudflare Turnstile on non-contact pages (17 KiB render-blocking).
		if ( ! is_page( 'contact' ) ) {
			$html = preg_replace( '/<script[^>]*challenges\.cloudflare\.com[^>]*><\/script>\n?/i', '', $html );
			$html = preg_replace( '/<script[^>]*turnstile[^>]*><\/script>\n?/i', '', $html );
		}

		return $html;
	});
} );
