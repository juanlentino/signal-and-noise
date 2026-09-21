<?php
/**
 * Signal & Noise — the template furniture as PHP-only blocks (WordPress 7.0).
 *
 * Ten renderers that were [shortcodes] inside block templates become real
 * blocks (nine in 13.1.0, the 404 suggestions list with #386): listed by name
 * in the site editor's List View and movable, with NO JavaScript build (the
 * canvas shows nothing for a post-dependent block outside a post context).
 * WordPress 7.0's `supports.autoRegister` exposes a server-rendered block to
 * the editor from its PHP registration alone.
 *
 * THE CONTRACT IS PARITY WITH `wpautop( shortcode )`, not the bare shortcode
 * string. Each `[sn_x]` used to sit in a `core/shortcode` block, whose own
 * renderer runs `wpautop()` on the expanded output — so the live HTML was
 * always `wpautop( shortcode_output )`: a `<p>` wrapper around inline markup
 * (the chip's two `<a>`s), `<br />` for embedded newlines (the panel), a
 * trailing `\n` after block-level tags. `.sn-post-frontmatter`'s flex row is
 * styled against that `<p>` wrapper, so the nine ex-`wp:shortcode` blocks
 * below each return `wpautop( (string) sn_x_shortcode() )` — every
 * render_callback returns the SAME string the `core/shortcode` block used to
 * produce for the same post, and tests/blocks-php-only.php pins it per
 * block. The toggle is the one exception: it was placed in `wp:html`, never
 * autop'd, so its callback stays a bare string. The shortcodes stay
 * registered indefinitely: they cost nothing, they render anything typed
 * into post content, and both paths call the same renderer (owner decision
 * 2026-09-12 — no retirement planned).
 *
 * Context: every renderer reads the queried/global post, exactly as its
 * shortcode did, so parity is by construction. None of these blocks is
 * placed inside a Query Loop; if one ever is, the renderer — not a wrapper —
 * has to learn to take a post id.
 *
 * @package SignalNoise
 * @since 13.1.0
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * The nine furniture blocks: wpautop( shortcode ), exactly what the
 * core/shortcode block produced — and, when the render is empty under a REST
 * request (the site editor's block-renderer preview, which has no post), a
 * labelled placeholder instead of nothing, so the block is visible and
 * selectable on the canvas. The front end never renders through REST, so a
 * published page is never touched.
 *
 * @since 13.1.1
 * @param string $html  The renderer's output.
 * @param string $label The block title, for the placeholder.
 * @return string
 */
function sn_php_block_output( $html, $label ) {
	$html = wpautop( (string) $html );
	if ( '' === $html && function_exists( 'wp_is_serving_rest_request' ) && wp_is_serving_rest_request() ) {
		return '<div class="sn-block-placeholder">' . esc_html( $label ) . '</div>';
	}
	return $html;
}

/**
 * The ten blocks, each from its own `blocks/<slug>/block.json` (the
 * Block Editor Handbook's canonical registration; 13.2.3, replacing the PHP
 * argument arrays of 13.1.0). Metadata, supports and the render file live in
 * the manifest; `supports.autoRegister` (7.0) exposes each to the editor with
 * no JavaScript. tests/blocks-registry.php pins every manifest. The tenth,
 * meta-nav, was never a shortcode: it is the footer's wp:html icon nav as a
 * named block, byte-identical, never autop'd (#388).
 */
function sn_php_only_block_slugs() {
	return array( 'prov-chip', 'prov-panel', 'related-notes', 'cited-by', 'note-share', 'note-reply', 'updated-date', 'post-pillar', 'theme-toggle', 'suggestions-404', 'meta-nav', 'footer-signature' );
}

function sn_register_php_only_blocks() {
	if ( ! function_exists( 'register_block_type' ) ) {
		return;
	}
	foreach ( sn_php_only_block_slugs() as $slug ) {
		register_block_type( __DIR__ . '/../blocks/' . $slug );
	}
}

if ( ! defined( 'SN_BLOCKS_TEST' ) || ! SN_BLOCKS_TEST ) {
	add_action( 'init', 'sn_register_php_only_blocks', 11 ); // after inc/blocks-register.php's init (priority 10) so the category exists.
}
