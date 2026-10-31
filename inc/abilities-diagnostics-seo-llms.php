<?php
/**
 * Abilities API: the get-seo-route-meta and get-llms-txt callbacks.
 *
 * Moved verbatim out of inc/abilities-diagnostics.php, which loads it.
 *
 * @package SignalNoise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Execute callback: signal-and-noise/get-seo-route-meta.
 *
 * Exposes sn_seo_page_descriptions() (inc/seo-route-meta.php) — the meta
 * descriptions the theme supplies for its template-driven Pages, which have no
 * post_content excerpt for the companion plugin's SEO layer to derive. Read-only;
 * the map is content-free public copy, safe for any `read`-capable caller.
 *
 * @since 10.29.0
 * @param mixed $input { slug?: string } or null.
 * @return array{routes:array<int,array{slug:string,path:string,description:string}>,count:int}|WP_Error
 */
function sn_theme_ability_seo_route_meta( $input = array() ) {
	try {
		if ( ! function_exists( 'sn_seo_page_descriptions' ) ) {
			return new WP_Error( 'theme_dependency_missing', 'sn_seo_page_descriptions() not available.', array( 'status' => 503 ) );
		}
		$filter = '';
		if ( is_array( $input ) && isset( $input['slug'] ) ) {
			// Accept "/services" or "services"; compare case-insensitively.
			$filter = strtolower( trim( trim( (string) $input['slug'] ), '/' ) );
		}
		$routes = array();
		foreach ( (array) sn_seo_page_descriptions() as $slug => $description ) {
			$slug = (string) $slug;
			if ( '' !== $filter && $slug !== $filter ) {
				continue;
			}
			$routes[] = array(
				'slug'        => $slug,
				'path'        => '/' . $slug,
				'description' => (string) $description,
			);
		}
		return array( 'routes' => $routes, 'count' => count( $routes ) );
	} catch ( \Throwable $e ) {
		error_log( 'SN theme ability error in get-seo-route-meta: ' . $e->getMessage() );
		return new WP_Error( 'theme_ability_error', sprintf( 'Theme ability failed: %s', $e->getMessage() ), array( 'status' => 500 ) );
	}
}

/**
 * Execute callback: signal-and-noise/get-llms-txt.
 *
 * Renders the theme's llms.txt manifest via sn_llms_txt_body() (inc/llms-txt.php)
 * — the same body the /llms.txt route serves. The full variant appends the recent
 * Notes corpus, queried only when requested (mirroring sn_llms_txt_send()).
 * Read-only; the manifest is public content.
 *
 * @since 10.29.0
 * @param mixed $input { full?: bool } or null.
 * @return array{variant:string,content:string,bytes:int}|WP_Error
 */
function sn_theme_ability_llms_txt( $input = array() ) {
	try {
		if ( ! function_exists( 'sn_llms_txt_body' ) ) {
			return new WP_Error( 'theme_dependency_missing', 'sn_llms_txt_body() not available.', array( 'status' => 503 ) );
		}
		$full  = is_array( $input ) && ! empty( $input['full'] );
		$notes = ( $full && function_exists( 'sn_llms_txt_recent_notes' ) ) ? sn_llms_txt_recent_notes() : array();
		// #311: the route passes the pillar descriptors too; without them the
		// body has no "Pillar essays" section and differs from /llms.txt.
		$pillars = function_exists( 'sn_theme_pillar_descriptors' ) ? (array) sn_theme_pillar_descriptors() : array();
		// 2026-10-10: the same inputs the route passes, so the index output is
		// the served /llms.txt byte for byte and the full output carries Topics.
		$topics  = $full && function_exists( 'sn_llms_txt_topics' ) ? sn_llms_txt_topics() : array();
		$count   = function_exists( 'sn_llms_txt_note_count' ) ? sn_llms_txt_note_count() : null;
		$body    = (string) sn_llms_txt_body( $full, $notes, $pillars, $topics, $count );
		return array(
			'variant' => $full ? 'full' : 'index',
			'content' => $body,
			'bytes'   => strlen( $body ),
		);
	} catch ( \Throwable $e ) {
		error_log( 'SN theme ability error in get-llms-txt: ' . $e->getMessage() );
		return new WP_Error( 'theme_ability_error', sprintf( 'Theme ability failed: %s', $e->getMessage() ), array( 'status' => 500 ) );
	}
}
