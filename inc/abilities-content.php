<?php
/**
 * Signal & Noise — Abilities API: content-facing abilities.
 *
 * 3 read abilities in the 'content' category:
 *   - signal-and-noise/list-block-patterns
 *   - signal-and-noise/get-page-notes-pillars
 *   - signal-and-noise/get-reading-time-for-slug
 *
 * Extracted from inc/abilities-registration.php by the v9.1.7 split (B-11
 * theme-side, companion to plugin v4.1.3). The 3 impl functions are
 * co-located with their registrations.
 *
 * @package SignalNoise
 * @since 9.1.7 (content from 9.1.0)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function sn_theme_register_content_abilities() {
	if ( ! function_exists( 'wp_register_ability' ) ) {
		return;
	}

	wp_register_ability( 'signal-and-noise/list-block-patterns', array(
		'label'               => 'List block patterns',
		'description'         => 'Enumerates all registered block patterns with category + keywords + viewport hints. Optional `category` input filters to a single pattern category.',
		'category'            => 'content',
		'permission_callback' => 'sn_theme_perm_read',
		'execute_callback'    => 'sn_theme_ability_list_block_patterns',
		'input_schema'        => array(
			'type'                 => 'object',
			'properties'           => array(
				'category' => array(
					'type'        => 'string',
					'description' => 'Optional filter to a single pattern category slug.',
				),
			),
			'additionalProperties' => false,
		),
		'output_schema'       => array(
			'type'     => 'object',
			'required' => array( 'patterns', 'categories' ),
			'properties' => array(
				'patterns'   => array(
					'type'        => 'array',
					'description' => 'Registered block patterns with metadata.',
					'items'       => array(
						'type'       => 'object',
						'properties' => array(
							'name'           => array( 'type' => 'string', 'description' => 'Unique pattern identifier (namespace/slug).' ),
							'title'          => array( 'type' => 'string', 'description' => 'Human-readable pattern title.' ),
							'description'    => array( 'type' => 'string', 'description' => 'Pattern description.' ),
							'categories'     => array( 'type' => 'array', 'description' => 'Pattern category slugs.', 'items' => array( 'type' => 'string' ) ),
							'keywords'       => array( 'type' => 'array', 'description' => 'Search keywords for the pattern.', 'items' => array( 'type' => 'string' ) ),
							'viewport_width' => array( 'type' => 'integer', 'description' => 'Pattern viewport width in pixels; 0 if unset.' ),
						),
					),
				),
				'categories' => array(
					'type'        => 'array',
					'description' => 'Registered block pattern categories.',
					'items'       => array(
						'type'       => 'object',
						'properties' => array(
							'name'  => array( 'type' => 'string', 'description' => 'Category slug.' ),
							'label' => array( 'type' => 'string', 'description' => 'Human-readable category label.' ),
						),
					),
				),
			),
		),
		'meta'                => array(
			'show_in_rest' => true,
			'annotations'  => array(
				'idempotent'      => true,
				'open_world_hint' => false,
				'readonly'        => true,
			),
		),
	) );

	wp_register_ability( 'signal-and-noise/get-page-notes-pillars', array(
		'label'               => 'List /notes pillar essays',
		'description'         => "Returns metadata for the SN /notes catalog pillar essays — slug, title, URL, summary dek, reading time, last modified. The pillars are project-defined in inc/page-notes-render.php and frame the /notes index.",
		'category'            => 'content',
		'permission_callback' => 'sn_theme_perm_read',
		'execute_callback'    => 'sn_theme_ability_page_notes_pillars',
		'input_schema'        => array(
			'type'                 => 'object',
			'properties'           => array(),
			'additionalProperties' => false,
		),
		'output_schema'       => array(
			'type'     => 'object',
			'required' => array( 'pillars' ),
			'properties' => array(
				'pillars' => array(
					'type'        => 'array',
					'description' => 'Curated /notes pillar essays with computed reading time + last-modified.',
					'items'       => array(
						'type'       => 'object',
						'properties' => array(
							'slug'                 => array( 'type' => 'string', 'description' => 'Pillar essay path slug (e.g., "provenance/over-detection").' ),
							'title'                => array( 'type' => 'string', 'description' => 'Pillar essay title.' ),
							'url'                  => array( 'type' => 'string', 'format' => 'uri', 'description' => 'Absolute URL to the pillar essay.' ),
							'summary'              => array( 'type' => 'string', 'description' => 'Editorial dek summarizing the pillar.' ),
							'reading_time_minutes' => array( 'type' => 'integer', 'description' => 'Estimated reading time in minutes; 0 if the slug does not resolve to a post.', 'minimum' => 0 ),
							'last_modified'        => array( 'type' => 'string', 'description' => 'YYYY-MM-DD of the resolved post\'s last modification; empty string if no matching post was found.' ),
						),
					),
				),
			),
		),
		'meta'                => array(
			'show_in_rest' => true,
			'annotations'  => array(
				'idempotent'      => true,
				'open_world_hint' => false,
				'readonly'        => true,
			),
		),
	) );

	wp_register_ability( 'signal-and-noise/get-reading-time-for-slug', array(
		'label'               => 'Get reading time for slug',
		'description'         => 'Returns the computed reading-time minutes for a post identified by slug. Wraps sn_notes_reading_time_for_slug() (the same helper that powers the [sn_reading_time] shortcode). Returns minutes=0 if the slug does not resolve.',
		'category'            => 'content',
		'permission_callback' => 'sn_theme_perm_read',
		'execute_callback'    => 'sn_theme_ability_reading_time_for_slug',
		'input_schema'        => array(
			'type'       => 'object',
			'required'   => array( 'slug' ),
			'properties' => array(
				'slug' => array(
					'type'      => 'string',
					'minLength' => 1,
					'examples'  => array( 'notes-pillar-audio-engineering-101', 'mastering-loudness-targets' ),
				),
			),
			'additionalProperties' => false,
		),
		'output_schema'       => array(
			'type'     => 'object',
			'required' => array( 'slug', 'minutes' ),
			'properties' => array(
				'slug'      => array( 'type' => 'string' ),
				'minutes'   => array( 'type' => 'integer', 'minimum' => 0 ),
				'wpm_basis' => array( 'type' => 'integer' ),
			),
		),
		'meta'                => array(
			'show_in_rest' => true,
			'annotations'  => array(
				'idempotent'      => true,
				'open_world_hint' => false,
				'readonly'        => true,
			),
		),
	) );
}
add_action( 'wp_abilities_api_init', 'sn_theme_register_content_abilities' );

/**
 * Execute callback: signal-and-noise/list-block-patterns.
 *
 * Enumerates the block-pattern + pattern-category registries. Optional
 * input.category filters to a single category slug.
 *
 * @since 9.1.0
 * @param array|null $input { category?: string }
 * @return array|WP_Error { patterns: array, categories: array }
 */
function sn_theme_ability_list_block_patterns( $input = array() ) {
	try {
		if ( ! class_exists( 'WP_Block_Patterns_Registry' )
			|| ! class_exists( 'WP_Block_Pattern_Categories_Registry' ) ) {
			return new WP_Error(
				'theme_dependency_missing',
				'WP_Block_Patterns_Registry not available — requires WP 5.5+.',
				array( 'status' => 503 )
			);
		}

		$filter_cat = '';
		if ( is_array( $input ) && isset( $input['category'] ) ) {
			$filter_cat = (string) $input['category'];
		}

		$raw_patterns = WP_Block_Patterns_Registry::get_instance()->get_all_registered();
		$patterns     = array();
		foreach ( (array) $raw_patterns as $p ) {
			$p_cats = isset( $p['categories'] ) ? (array) $p['categories'] : array();
			if ( '' !== $filter_cat && ! in_array( $filter_cat, $p_cats, true ) ) {
				continue;
			}
			$patterns[] = array(
				'name'           => isset( $p['name'] )           ? (string) $p['name']           : '',
				'title'          => isset( $p['title'] )          ? (string) $p['title']          : '',
				'description'    => isset( $p['description'] )    ? (string) $p['description']    : '',
				'categories'     => $p_cats,
				'keywords'       => isset( $p['keywords'] )       ? (array) $p['keywords']        : array(),
				'viewport_width' => isset( $p['viewport_width'] ) ? (int) $p['viewport_width']    : 0,
			);
		}

		$raw_cats = WP_Block_Pattern_Categories_Registry::get_instance()->get_all_registered();
		$categories = array();
		foreach ( (array) $raw_cats as $c ) {
			$categories[] = array(
				'name'  => isset( $c['name'] )  ? (string) $c['name']  : '',
				'label' => isset( $c['label'] ) ? (string) $c['label'] : '',
			);
		}

		return array(
			'patterns'   => $patterns,
			'categories' => $categories,
		);
	} catch ( \Throwable $e ) {
		error_log( 'SN theme ability error in list-block-patterns: ' . $e->getMessage() );
		return new WP_Error(
			'theme_ability_error',
			sprintf( 'Theme ability failed: %s', $e->getMessage() ),
			array( 'status' => 500 )
		);
	}
}

/**
 * Execute callback: signal-and-noise/get-page-notes-pillars.
 *
 * Returns pillar metadata enriched with reading_time_minutes (computed
 * by sn_notes_reading_time_for_slug) and last_modified (read from the
 * resolved post if it exists).
 *
 * @since 9.1.0
 * @return array|WP_Error { pillars: array }
 */
function sn_theme_ability_page_notes_pillars() {
	try {
		$pillars = array();
		foreach ( sn_theme_pillar_descriptors() as $p ) {
			$reading_str = function_exists( 'sn_notes_reading_time_for_slug' )
				? (string) sn_notes_reading_time_for_slug( $p['slug'] )
				: '5 min';
			// Parse "N min" into integer minutes.
			$minutes = 0;
			if ( preg_match( '/(\d+)/', $reading_str, $m ) ) {
				$minutes = (int) $m[1];
			}

			// Last-modified is best-effort: pillars are short essays
			// stored at a path slug under /provenance/. We look up by
			// the final path segment.
			$last_modified = '';
			if ( function_exists( 'get_page_by_path' ) ) {
				$post = get_page_by_path( $p['last_path'], OBJECT, 'post' );
				if ( $post && isset( $post->post_modified ) ) {
					$last_modified = substr( (string) $post->post_modified, 0, 10 );
				}
			}

			$pillars[] = array(
				'slug'                 => $p['slug'],
				'title'                => $p['title'],
				'url'                  => function_exists( 'home_url' ) ? home_url( '/' . $p['slug'] . '/' ) : '/' . $p['slug'] . '/',
				'summary'              => $p['dek'],
				'reading_time_minutes' => $minutes,
				'last_modified'        => $last_modified,
			);
		}

		return array( 'pillars' => $pillars );
	} catch ( \Throwable $e ) {
		error_log( 'SN theme ability error in get-page-notes-pillars: ' . $e->getMessage() );
		return new WP_Error(
			'theme_ability_error',
			sprintf( 'Theme ability failed: %s', $e->getMessage() ),
			array( 'status' => 500 )
		);
	}
}

/**
 * Execute callback: signal-and-noise/get-reading-time-for-slug.
 *
 * Wraps sn_notes_reading_time_for_slug() which returns a formatted
 * string like "7 min". Parses the integer back out for a typed
 * response. wpm_basis is hardcoded to 220 — the project default
 * baked into sn_get_reading_time().
 *
 * @since 9.1.0
 * @param array $input { slug: string }
 * @return array|WP_Error
 */
function sn_theme_ability_reading_time_for_slug( $input ) {
	try {
		$slug = isset( $input['slug'] ) ? (string) $input['slug'] : '';
		if ( '' === $slug ) {
			return new WP_Error(
				'invalid_input',
				'slug is required.',
				array( 'status' => 400 )
			);
		}

		if ( ! function_exists( 'sn_notes_reading_time_for_slug' ) ) {
			return new WP_Error(
				'theme_dependency_missing',
				'sn_notes_reading_time_for_slug() unavailable — theme module not loaded.',
				array( 'status' => 503 )
			);
		}

		$raw = (string) sn_notes_reading_time_for_slug( $slug );
		$minutes = 0;
		if ( preg_match( '/(\d+)/', $raw, $m ) ) {
			$minutes = (int) $m[1];
		}

		return array(
			'slug'      => $slug,
			'minutes'   => $minutes,
			'wpm_basis' => 220,
		);
	} catch ( \Throwable $e ) {
		error_log( 'SN theme ability error in get-reading-time-for-slug: ' . $e->getMessage() );
		return new WP_Error(
			'theme_ability_error',
			sprintf( 'Theme ability failed: %s', $e->getMessage() ),
			array( 'status' => 500 )
		);
	}
}
