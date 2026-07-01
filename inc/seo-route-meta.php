<?php
/**
 * Signal & Noise — SEO meta for theme-owned routes.
 *
 * The companion plugin owns SEO/JSON-LD emission, but it can only describe what
 * WordPress hands it: real-Page conditionals (is_page) and excerpts. Two classes
 * of theme route fall through that net, so the plugin (v6.24.0) exposes filters
 * the theme answers here — the route COPY lives with the routes:
 *
 *   - sn_seo_singular_description — template-driven Pages (/about, /contact,
 *     /colophon, /music) carry their content in FSE templates, not post_content,
 *     so they have no excerpt and shipped with no meta/og description. We supply
 *     one per slug.
 *   - sn_seo_route_meta — the postless virtual route /about/uses (no WP post at
 *     all) supplies its full title/description/url/breadcrumb so the plugin emits
 *     og + canonical + a connected JSON-LD @graph for it.
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
			'about'    => 'Music producer, mix engineer, and creative strategist based in Buenos Aires — the person behind the work, the studio, and the notes.',
			'contact'  => 'How to reach Juan Lentino for mixing, production, and creative work — direct, no forms, no noise.',
			'colophon' => 'How this site is built: the typography, tools, and engineering behind juanlentino.com.',
			'music'    => 'Selected discography — releases produced, mixed, and engineered by Juan Lentino, with credits and streaming links.',
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

/**
 * sn_seo_route_meta: full meta for the postless /about/uses virtual route.
 * Returns null for any other request so the plugin falls back to WP conditionals.
 *
 * @param array<string,mixed>|null $meta Meta resolved so far (null = unresolved).
 * @return array<string,mixed>|null
 */
function sn_seo_route_meta_for_uses( $meta ) {
	if ( null !== $meta ) {
		return $meta;
	}
	if ( ! function_exists( 'sn_uses_is_uses_request' ) || ! sn_uses_is_uses_request() ) {
		return null;
	}
	return array(
		'title'       => function_exists( 'sn_uses_title' ) ? sn_uses_title() : 'Uses',
		'description' => 'The hardware, software, and instruments behind the work — what Juan Lentino actually uses, grouped and listed.',
		'url'         => home_url( '/about/uses' ),
		'breadcrumb'  => array(
			array( 'name' => 'About', 'url' => home_url( '/about/' ) ),
			array( 'name' => 'Uses',  'url' => home_url( '/about/uses' ) ),
		),
	);
}

/**
 * sn_seo_route_meta: full meta for the postless /now virtual route (v10.21.0).
 *
 * @param array<string,mixed>|null $meta Meta resolved so far (null = unresolved).
 * @return array<string,mixed>|null
 */
function sn_seo_route_meta_for_now( $meta ) {
	if ( null !== $meta ) {
		return $meta;
	}
	if ( ! function_exists( 'sn_now_is_now_request' ) || ! sn_now_is_now_request() ) {
		return null;
	}
	return array(
		'title'       => function_exists( 'sn_now_title' ) ? sn_now_title() : 'Now',
		'description' => 'What Juan Lentino is focused on right now — current projects, writing, and inputs. Updated whenever it changes.',
		'url'         => home_url( '/now' ),
		'breadcrumb'  => array(
			array( 'name' => 'Now', 'url' => home_url( '/now' ) ),
		),
	);
}

/**
 * sn_seo_route_meta: full meta for the postless /accessibility virtual route (v10.21.0).
 *
 * @param array<string,mixed>|null $meta Meta resolved so far (null = unresolved).
 * @return array<string,mixed>|null
 */
function sn_seo_route_meta_for_accessibility( $meta ) {
	if ( null !== $meta ) {
		return $meta;
	}
	if ( ! function_exists( 'sn_a11y_is_a11y_request' ) || ! sn_a11y_is_a11y_request() ) {
		return null;
	}
	return array(
		'title'       => function_exists( 'sn_a11y_title' ) ? sn_a11y_title() : 'Accessibility',
		'description' => 'Accessibility statement for juanlentino.com — WCAG 2.1 AA target, measures in place, known limitations, and how to report problems.',
		'url'         => home_url( '/accessibility' ),
		'breadcrumb'  => array(
			array( 'name' => 'Accessibility', 'url' => home_url( '/accessibility' ) ),
		),
	);
}

if ( ! defined( 'SN_SEO_ROUTE_META_TEST' ) || ! SN_SEO_ROUTE_META_TEST ) {
	add_filter( 'sn_seo_singular_description', 'sn_seo_route_singular_description', 10, 2 );
	add_filter( 'sn_seo_route_meta', 'sn_seo_route_meta_for_uses' );
	add_filter( 'sn_seo_route_meta', 'sn_seo_route_meta_for_now' );
	add_filter( 'sn_seo_route_meta', 'sn_seo_route_meta_for_accessibility' );
}
