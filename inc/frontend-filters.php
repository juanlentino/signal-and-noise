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
