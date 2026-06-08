<?php
/**
 * Signal & Noise — Curated editor block palette (v9.10.0).
 *
 * Narrows the post/page inserter to the blocks this theme actually renders,
 * plus the core authoring primitives a writer reaches for, plus the site's
 * contact-form block. A smaller inserter = less noise, fewer "why is this
 * here" blocks that the brutalist cascade never styled.
 *
 * THE FIREWALL (why this is safe): the curated list is applied ONLY in the
 * post/page CONTENT editor. Two firewall checks, BOTH required:
 *
 *   1. Site Editor by NAME (`$context->name === 'core/edit-site'`) — editing a
 *      PAGE in the Site Editor sets `$context->post` to that page object, so an
 *      `empty($context->post)` check alone is NOT enough: the curated list
 *      would wrongly starve the Site Editor (which needs every block for
 *      templates). The name check is the real Site-Editor firewall.
 *   2. Post-less contexts (`empty($context->post)`) — the post-list-screen
 *      context, widgets, etc.
 *
 * Verified vs WordPress/WordPress trunk (class-wp-block-editor-context.php:
 * `public $name = 'core/edit-post'; public $post = null;` — documented $name
 * values: core/edit-post, core/edit-site, core/edit-widgets,
 * core/customize-widgets).
 *
 * We also bail when `$allowed` is already an array — never clobber a peer
 * plugin that has already narrowed the inserter.
 *
 * CONSERVATIVE BY DESIGN: missing a block the theme uses would break editing
 * of a template-derived page. The `$used` list below is the enumerated union
 * of every block in templates/*.html, parts/*.html, and patterns/*.php.
 *
 * @package SignalNoise
 * @since   theme v9.10.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Curate the allowed block types for the post/page editor.
 *
 * @param bool|string[]           $allowed Array of block slugs, or boolean to
 *                                         enable/disable all (default true).
 * @param WP_Block_Editor_Context $context The current block editor context.
 * @return bool|string[] The curated allowlist in the post editor; $allowed
 *                       unchanged everywhere else.
 */
function sn_theme_allowed_blocks( $allowed, $context ) {
	// Firewall 1: the Site Editor (core/edit-site) keeps the full registered
	// palette so template editing is never starved. Checked by NAME because
	// editing a PAGE in the Site Editor sets $context->post — an empty($post)
	// check alone would wrongly curate it. (WP_Block_Editor_Context->name,
	// verified vs trunk; default 'core/edit-post'.)
	if ( isset( $context->name ) && 'core/edit-site' === $context->name ) {
		return $allowed;
	}

	// Firewall 2: post-less contexts (widgets, customizer-widgets, the
	// post-list screen) keep the full registered palette too.
	if ( empty( $context->post ) ) {
		return $allowed;
	}

	// Never clobber a peer plugin that already curated the inserter.
	if ( is_array( $allowed ) ) {
		return $allowed;
	}

	// Every block the theme's templates / parts / patterns actually render.
	// Enumerated from templates/*.html, parts/*.html, patterns/*.php — keep in
	// sync when those gain a new block, or that block vanishes from the inserter.
	$used = array(
		'core/paragraph',
		'core/heading',
		'core/list',
		'core/list-item',
		'core/image',
		'core/html',
		'core/group',
		'core/columns',
		'core/column',
		'core/buttons',
		'core/button',
		'core/separator',
		'core/spacer',
		'core/shortcode',
		'core/social-links',
		'core/social-link',
		'core/navigation',
		'core/navigation-link',
		'core/template-part',
		'core/post-content',
		'core/post-title',
		'core/post-date',
		'core/post-excerpt',
		'core/post-terms',
		'core/post-template',
		'core/post-navigation-link',
		'core/query',
		'core/query-no-results',
		'core/query-pagination',
		'core/query-pagination-next',
		'core/query-pagination-previous',
	);

	// Core authoring primitives a writer reasonably reaches for in post content
	// even though no template hard-codes them — quotes, code, tables, media.
	$authoring = array(
		'core/quote',
		'core/pullquote',
		'core/code',
		'core/preformatted',
		'core/verse',
		'core/table',
		'core/details',
		'core/footnotes',
		'core/media-text',
		'core/cover',
		'core/gallery',
		'core/video',
		'core/audio',
		'core/file',
		'core/embed',
		'core/more',
		'core/nextpage',
		'core/page-list',
		'core/site-logo',
		// Reusable / synced Patterns block — without it, synced patterns the
		// author has created would silently vanish from the inserter.
		'core/block',
	);

	// The site's contact-form block (Fluent Forms). Verified from its source:
	// app/Services/Blocks/GutenbergBlock.php → register_block_type('fluentfom/guten-block', ...).
	// (The "fluentfom" spelling is Fluent Forms' own namespace, not a typo here.)
	$contact = array( 'fluentfom/guten-block' );

	return array_values( array_unique( array_merge( $used, $authoring, $contact ) ) );
}
add_filter( 'allowed_block_types_all', 'sn_theme_allowed_blocks', 10, 2 );
