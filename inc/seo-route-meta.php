<?php
/**
 * Signal & Noise — SEO meta for theme-owned template-driven Pages.
 *
 * The companion plugin owns SEO/JSON-LD emission, but it can only describe what
 * WordPress hands it: real-Page conditionals (is_page) and excerpts. Template-
 * driven Pages carry their content in FSE templates, not post_content, so they
 * have no excerpt and shipped with no meta/og description. The plugin (v6.24.0)
 * exposes `sn_seo_singular_description`, which the theme answers here — the route
 * COPY lives with the routes.
 *
 * NOTE: the theme no longer answers `sn_seo_route_meta` (the postless-route
 * filter). Every former virtual route — /now, /about/uses, /accessibility,
 * /contact/personal — is now a real CMS Page whose Excerpt supplies its meta
 * description and whose WebPage JSON-LD the plugin builds from is_singular
 * (pages-to-CMS flip, Phases 2a–2c). Only template-driven Pages with no excerpt
 * remain here; today that is /colophon.
 *
 * @package SignalNoise
 * @since 10.13.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Meta descriptions for the theme's template-driven Pages, keyed by slug.
 * Filterable so the copy can be tuned without editing the theme.
 *
 * @return array<string,string>
 */
function sn_seo_page_descriptions() {
	return (array) apply_filters(
		'sn_seo_page_descriptions',
		array(
			'colophon' => 'How this site is built: the typography, tools, and engineering behind juanlentino.com.',
		)
	);
}

/**
 * sn_seo_singular_description: supply a description for a template-driven Page
 * that has no excerpt. Only fills when the plugin found nothing else.
 *
 * @param string       $description Description resolved so far ('' if none).
 * @param object|null  $post        The queried page.
 * @return string
 */
function sn_seo_route_singular_description( $description, $post ) {
	if ( '' !== (string) $description || ! is_object( $post ) ) {
		return $description;
	}
	$slug = isset( $post->post_name ) ? (string) $post->post_name : '';
	$map  = sn_seo_page_descriptions();
	return isset( $map[ $slug ] ) ? (string) $map[ $slug ] : $description;
}

if ( ! defined( 'SN_SEO_ROUTE_META_TEST' ) || ! SN_SEO_ROUTE_META_TEST ) {
	add_filter( 'sn_seo_singular_description', 'sn_seo_route_singular_description', 10, 2 );
}
