<?php
/**
 * Abilities API: the get-active-template-structure callback.
 *
 * Moved verbatim out of inc/abilities-diagnostics.php, which loads it.
 *
 * @package SignalNoise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Execute callback: signal-and-noise/get-active-template-structure.
 *
 * @since 9.1.0
 */
function sn_theme_ability_active_template_structure( $input ) {
	try {
		$post = null;

		if ( ! empty( $input['post_id'] ) ) {
			$post = function_exists( 'get_post' ) ? get_post( (int) $input['post_id'] ) : null;
		} elseif ( ! empty( $input['slug'] ) ) {
			$post_type = isset( $input['post_type'] ) ? (string) $input['post_type'] : 'page';
			$post = function_exists( 'get_page_by_path' )
				? get_page_by_path( (string) $input['slug'], OBJECT, $post_type )
				: null;
		}

		if ( ! $post || ! isset( $post->post_type ) ) {
			return new WP_Error(
				'post_not_found',
				'No post matches the given post_id or slug.',
				array( 'status' => 404 )
			);
		}

		// v9.15.4: the ability is gated only by the `read` cap (any logged-in
		// user). Without a per-post check it doubles as an existence/post_type
		// oracle — a subscriber could enumerate post_id and learn which non-public
		// posts exist (and whether they're pages) from a 200-vs-404 response.
		// Require the post be publicly viewable OR readable by the user, and on
		// failure return the SAME post_not_found as a missing post — so "exists
		// but private" is indistinguishable from "doesn't exist". No content
		// leaks either way (only the theme's template structure is returned), so
		// this is defense-in-depth, not a content-disclosure fix.
		$can_read = ( function_exists( 'is_post_publicly_viewable' ) && is_post_publicly_viewable( $post ) )
			|| ( function_exists( 'current_user_can' ) && current_user_can( 'read_post', (int) $post->ID ) );
		if ( ! $can_read ) {
			return new WP_Error(
				'post_not_found',
				'No post matches the given post_id or slug.',
				array( 'status' => 404 )
			);
		}

		// Template resolution in core's hierarchy order (#310): a slug-matched
		// template (page-about, single-post-foo) outranks the post-type one.
		// Most of this theme's page templates are slug-matched, so the
		// generic slug alone described a template the page never renders.
		$post_name  = isset( $post->post_name ) ? (string) $post->post_name : '';
		$candidates = 'page' === $post->post_type
			? array( 'page-' . $post_name, 'page' )
			: array( 'single-' . $post->post_type . '-' . $post_name, 'single-' . $post->post_type, 'single' );

		$theme = function_exists( 'wp_get_theme' ) ? wp_get_theme() : null;
		$theme_stylesheet = $theme && method_exists( $theme, 'get_stylesheet' )
			? (string) $theme->get_stylesheet()
			: 'signal-and-noise';

		$template_slug = end( $candidates );
		$template      = null;
		foreach ( $candidates as $candidate ) {
			$found = function_exists( 'get_block_template' )
				? get_block_template( $theme_stylesheet . '//' . $candidate )
				: null;
			if ( $found ) {
				$template_slug = $candidate;
				$template      = $found;
				break;
			}
		}
		$blocks_summary = array();
		$part_slugs    = array();

		if ( $template && isset( $template->content ) ) {
			$parsed = function_exists( 'parse_blocks' ) ? parse_blocks( (string) $template->content ) : array();
			foreach ( (array) $parsed as $block ) {
				if ( empty( $block['blockName'] ) ) {
					continue;
				}
				$summary = array(
					'blockName'        => (string) $block['blockName'],
					'attrs'            => isset( $block['attrs'] ) ? (array) $block['attrs'] : array(),
					'innerBlocksCount' => isset( $block['innerBlocks'] ) ? count( (array) $block['innerBlocks'] ) : 0,
				);
				$blocks_summary[] = $summary;
				if ( 'core/template-part' === $summary['blockName'] && isset( $block['attrs']['slug'] ) ) {
					$part_slugs[] = (string) $block['attrs']['slug'];
				}
			}
		}

		return array(
			'template_slug'       => $template_slug,
			'template_part_slugs' => $part_slugs,
			'blocks'              => $blocks_summary,
		);
	} catch ( \Throwable $e ) {
		error_log( 'SN theme ability error in get-active-template-structure: ' . $e->getMessage() );
		return new WP_Error(
			'theme_ability_error',
			sprintf( 'Theme ability failed: %s', $e->getMessage() ),
			array( 'status' => 500 )
		);
	}
}
