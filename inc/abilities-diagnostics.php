<?php
/**
 * Signal & Noise — Abilities API: diagnostics abilities.
 *
 * 4 read abilities in the 'diagnostics' category:
 *   - signal-and-noise/get-active-template-structure
 *   - signal-and-noise/get-theme-version
 *   - signal-and-noise/get-design-system-summary
 *   - signal-and-noise/get-design-tokens
 *
 * Extracted from inc/abilities-registration.php by the v9.1.7 split (B-11
 * theme-side, companion to plugin v4.1.3). The 4 impl functions are
 * co-located with their registrations.
 *
 * Cross-file note: sn_theme_ability_design_system_summary() internally
 * calls sn_theme_ability_design_tokens() (also in this file — same-file
 * call). No other diagnostics ability has external dependencies.
 *
 * @package SignalNoise
 * @since 9.1.7 (content from 9.1.0)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function sn_theme_register_diagnostics_abilities() {
	if ( ! function_exists( 'wp_register_ability' ) ) {
		return;
	}

	wp_register_ability( 'signal-and-noise/get-active-template-structure', array(
		'label'               => 'Inspect active template structure',
		'description'         => 'Returns the FSE template slug + a shallow block tree (blockName + attrs + innerBlocks count) for a given post by ID or slug. Does not recurse into innerBlocks beyond a count — keeps payload bounded.',
		'category'            => 'diagnostics',
		'permission_callback' => 'sn_theme_perm_read',
		'execute_callback'    => 'sn_theme_ability_active_template_structure',
		'input_schema'        => array(
			'type'       => 'object',
			'properties' => array(
				'post_id'   => array( 'type' => 'integer', 'minimum' => 1 ),
				'post_type' => array( 'type' => 'string', 'enum' => array( 'post', 'page' ) ),
				'slug'      => array( 'type' => 'string' ),
			),
			'anyOf' => array(
				array( 'required' => array( 'post_id' ) ),
				array( 'required' => array( 'slug' ) ),
			),
			'additionalProperties' => false,
		),
		'output_schema'       => array(
			'type'     => 'object',
			'required' => array( 'template_slug', 'blocks' ),
			'properties' => array(
				'template_slug'       => array( 'type' => 'string', 'description' => 'Resolved FSE template slug (e.g., "page", "single").' ),
				'template_part_slugs' => array( 'type' => 'array', 'description' => 'Slugs of core/template-part blocks referenced at the top level of the template.', 'items' => array( 'type' => 'string' ) ),
				'blocks'              => array(
					'type'        => 'array',
					'description' => 'Shallow summary of the template\'s top-level blocks. Does not recurse into innerBlocks; nested structure is reported as a count only.',
					'items'       => array(
						'type'       => 'object',
						'properties' => array(
							'blockName'        => array( 'type' => 'string', 'description' => 'Block type identifier (e.g., "core/group", "core/template-part").' ),
							'attrs'            => array( 'type' => 'object', 'description' => 'Top-level block attributes as parsed from the template.' ),
							'innerBlocksCount' => array( 'type' => 'integer', 'description' => 'Number of direct child blocks; nested children are not recursed into.', 'minimum' => 0 ),
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

	wp_register_ability( 'signal-and-noise/get-theme-version', array(
		'label'               => 'Get theme + WP version',
		'description'         => 'Returns the active theme name + version + parent template + is_block_theme flag + WP version. Use to detect drift between published roadmap docs and the live site.',
		'category'            => 'diagnostics',
		'permission_callback' => 'sn_theme_perm_read',
		'execute_callback'    => 'sn_theme_ability_theme_version',
		'input_schema'        => array(
			'type'                 => 'object',
			'properties'           => array(),
			'additionalProperties' => false,
		),
		'output_schema'       => array(
			'type'     => 'object',
			'required' => array( 'theme_version', 'theme_name', 'is_block_theme', 'wp_version' ),
			'properties' => array(
				'theme_version'  => array( 'type' => 'string' ),
				'theme_name'     => array( 'type' => 'string' ),
				'theme_template' => array( 'type' => 'string' ),
				'is_block_theme' => array( 'type' => 'boolean' ),
				'supports_fse'   => array( 'type' => 'boolean' ),
				'wp_version'     => array( 'type' => 'string' ),
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

	wp_register_ability( 'signal-and-noise/get-design-system-summary', array(
		'label'               => 'Get design-system summary (AI-prompt formatted)',
		'description'         => 'Formats the design tokens for AI prompt embedding. format=markdown (default) for structured prose, format=compact-text for minimum-token single-line embedding, format=json for full passthrough. Typical 70-80% token reduction vs raw get-design-tokens JSON on compact-text.',
		'category'            => 'diagnostics',
		'permission_callback' => 'sn_theme_perm_read',
		'execute_callback'    => 'sn_theme_ability_design_system_summary',
		'input_schema'        => array(
			'type'                 => 'object',
			'properties'           => array(
				'format' => array(
					'type'     => 'string',
					'enum'     => array( 'markdown', 'compact-text', 'json' ),
					'default'  => 'markdown',
					'examples' => array( 'markdown', 'compact-text' ),
				),
			),
			'additionalProperties' => false,
		),
		'output_schema'       => array(
			'type'     => 'object',
			'required' => array( 'format', 'summary', 'token_estimate' ),
			'properties' => array(
				'format'         => array( 'type' => 'string', 'enum' => array( 'markdown', 'compact-text', 'json' ) ),
				'summary'        => array( 'type' => 'string' ),
				'token_estimate' => array( 'type' => 'integer' ),
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

	wp_register_ability( 'signal-and-noise/get-design-tokens', array(
		'label'               => 'Get design tokens',
		'description'         => "Returns the SN theme's color palette, typography (font families + sizes), and spacing scale from theme.json. Read-only.",
		'category'            => 'diagnostics',
		'permission_callback' => 'sn_theme_perm_read',
		'execute_callback'    => 'sn_theme_ability_design_tokens',
		'input_schema'        => array(
			'type'                 => 'object',
			'properties'           => array(),
			'additionalProperties' => false,
		),
		'output_schema'       => array(
			'type'     => 'object',
			'required' => array( 'colors', 'typography', 'spacing', 'version' ),
			'properties' => array(
				'colors'     => array(
					'type'                 => 'object',
					'description'          => 'Named brand colors from theme.json color.palette.',
					'additionalProperties' => array( 'type' => 'string', 'format' => 'color-hex' ),
				),
				'typography' => array(
					'type'       => 'object',
					'description' => 'theme.json typography presets.',
					'properties' => array(
						'fontFamilies' => array(
							'type'        => 'array',
							'description' => 'Font-family presets from theme.json typography.fontFamilies.',
							'items'       => array(
								'type'       => 'object',
								'properties' => array(
									'slug'       => array( 'type' => 'string', 'description' => 'Preset slug used in style attributes.' ),
									'name'       => array( 'type' => 'string', 'description' => 'Human-readable name shown in the editor.' ),
									'fontFamily' => array( 'type' => 'string', 'description' => 'CSS font-family declaration value.' ),
								),
							),
						),
						'fontSizes'    => array(
							'type'        => 'array',
							'description' => 'Font-size presets from theme.json typography.fontSizes.',
							'items'       => array(
								'type'       => 'object',
								'properties' => array(
									'slug' => array( 'type' => 'string', 'description' => 'Preset slug used in style attributes.' ),
									'name' => array( 'type' => 'string', 'description' => 'Human-readable name shown in the editor.' ),
									'size' => array( 'type' => 'string', 'description' => 'CSS size value (e.g., "1rem", "clamp(...)").' ),
								),
							),
						),
					),
				),
				'spacing'    => array(
					'type'        => 'object',
					'description' => 'theme.json spacing scale + named spacing sizes.',
					'properties'  => array(
						'spacingScale' => array(
							'type'        => 'object',
							'description' => 'Programmatic spacing scale (operator + increment + steps + mediumStep + unit).',
							'properties'  => array(
								'operator'   => array( 'type' => 'string', 'description' => 'Math operator applied between steps (e.g., "*", "+").' ),
								'increment'  => array( 'type' => 'number', 'description' => 'Step delta.' ),
								'steps'      => array( 'type' => 'integer', 'description' => 'Number of scale steps generated.' ),
								'mediumStep' => array( 'type' => 'number', 'description' => 'Base value for the middle step.' ),
								'unit'       => array( 'type' => 'string', 'description' => 'CSS length unit (e.g., "rem").' ),
							),
						),
						'spacingSizes' => array(
							'type'        => 'array',
							'description' => 'Named spacing presets from theme.json spacing.spacingSizes.',
							'items'       => array(
								'type'       => 'object',
								'properties' => array(
									'slug' => array( 'type' => 'string', 'description' => 'Preset slug.' ),
									'name' => array( 'type' => 'string', 'description' => 'Human-readable name shown in the editor.' ),
									'size' => array( 'type' => 'string', 'description' => 'CSS size value.' ),
								),
							),
						),
					),
				),
				'version'    => array( 'type' => 'string' ),
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
add_action( 'wp_abilities_api_init', 'sn_theme_register_diagnostics_abilities' );

/**
 * Execute callback: signal-and-noise/get-design-tokens.
 *
 * @since 9.1.0
 * @return array|WP_Error
 */
function sn_theme_ability_design_tokens() {
	try {
		if ( ! function_exists( 'wp_get_global_settings' ) ) {
			return new WP_Error(
				'theme_dependency_missing',
				'wp_get_global_settings() not available — requires WP 5.9+.',
				array( 'status' => 503 )
			);
		}

		$settings = wp_get_global_settings();

		$colors = array();
		$palette = isset( $settings['color']['palette'] ) ? (array) $settings['color']['palette'] : array();
		foreach ( $palette as $entry ) {
			if ( isset( $entry['slug'], $entry['color'] ) ) {
				$colors[ (string) $entry['slug'] ] = (string) $entry['color'];
			}
		}

		$typography = isset( $settings['typography'] ) ? (array) $settings['typography'] : array();
		$spacing    = isset( $settings['spacing'] )    ? (array) $settings['spacing']    : array();

		$theme   = function_exists( 'wp_get_theme' ) ? wp_get_theme() : null;
		$version = $theme && method_exists( $theme, 'get' ) ? (string) $theme->get( 'Version' ) : '';

		return array(
			'colors'     => $colors,
			'typography' => array(
				'fontFamilies' => isset( $typography['fontFamilies'] ) ? array_values( (array) $typography['fontFamilies'] ) : array(),
				'fontSizes'    => isset( $typography['fontSizes'] )    ? array_values( (array) $typography['fontSizes'] )    : array(),
			),
			'spacing'    => array(
				'spacingScale' => isset( $spacing['spacingScale'] ) ? (array) $spacing['spacingScale'] : array(),
				'spacingSizes' => isset( $spacing['spacingSizes'] ) ? array_values( (array) $spacing['spacingSizes'] ) : array(),
			),
			'version'    => $version,
		);
	} catch ( \Throwable $e ) {
		error_log( 'SN theme ability error in get-design-tokens: ' . $e->getMessage() );
		return new WP_Error(
			'theme_ability_error',
			sprintf( 'Theme ability failed: %s', $e->getMessage() ),
			array( 'status' => 500 )
		);
	}
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

		// Best-effort template resolution. WP's logic for picking the
		// template for a post is complex; for the diagnostics surface a
		// simple post_type-based slug is sufficient and matches what the
		// FSE engine resolves to in 90%+ of cases.
		$template_slug = 'page' === $post->post_type ? 'page' : 'single';

		$theme = function_exists( 'wp_get_theme' ) ? wp_get_theme() : null;
		$theme_stylesheet = $theme && method_exists( $theme, 'get_stylesheet' )
			? (string) $theme->get_stylesheet()
			: 'signal-and-noise';

		$template_id   = $theme_stylesheet . '//' . $template_slug;
		$template      = function_exists( 'get_block_template' ) ? get_block_template( $template_id ) : null;
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

/**
 * Execute callback: signal-and-noise/get-theme-version.
 *
 * @since 9.1.0
 */
function sn_theme_ability_theme_version() {
	try {
		if ( ! function_exists( 'wp_get_theme' ) ) {
			return new WP_Error(
				'theme_dependency_missing',
				'wp_get_theme() not available.',
				array( 'status' => 503 )
			);
		}

		$theme         = wp_get_theme();
		$theme_version = method_exists( $theme, 'get' ) ? (string) $theme->get( 'Version' ) : '';
		$theme_name    = method_exists( $theme, 'get' ) ? (string) $theme->get( 'Name' )    : '';
		$template      = method_exists( $theme, 'get_template' ) ? (string) $theme->get_template() : '';

		$is_block = function_exists( 'wp_is_block_theme' ) ? (bool) wp_is_block_theme() : false;

		// wp_get_wp_version() exists on WP 6.7+; fall back to $wp_version global.
		if ( function_exists( 'wp_get_wp_version' ) ) {
			$wp_version = (string) wp_get_wp_version();
		} else {
			$wp_version = isset( $GLOBALS['wp_version'] ) ? (string) $GLOBALS['wp_version'] : '';
		}

		return array(
			'theme_version'  => $theme_version,
			'theme_name'     => $theme_name,
			'theme_template' => $template,
			'is_block_theme' => $is_block,
			'supports_fse'   => $is_block,
			'wp_version'     => $wp_version,
		);
	} catch ( \Throwable $e ) {
		error_log( 'SN theme ability error in get-theme-version: ' . $e->getMessage() );
		return new WP_Error(
			'theme_ability_error',
			sprintf( 'Theme ability failed: %s', $e->getMessage() ),
			array( 'status' => 500 )
		);
	}
}

/**
 * Execute callback: signal-and-noise/get-design-system-summary.
 *
 * @since 9.1.0
 */
function sn_theme_ability_design_system_summary( $input = array() ) {
	try {
		$format = isset( $input['format'] ) ? (string) $input['format'] : 'markdown';
		if ( ! in_array( $format, array( 'markdown', 'compact-text', 'json' ), true ) ) {
			$format = 'markdown';
		}

		$tokens = sn_theme_ability_design_tokens();
		if ( is_wp_error( $tokens ) ) {
			return $tokens;
		}

		$summary = '';
		switch ( $format ) {
			case 'compact-text':
				$color_pairs = array();
				foreach ( (array) $tokens['colors'] as $slug => $hex ) {
					$color_pairs[] = $slug . $hex;
				}
				$font_slugs = array();
				foreach ( (array) $tokens['typography']['fontFamilies'] as $ff ) {
					if ( isset( $ff['slug'] ) ) { $font_slugs[] = (string) $ff['slug']; }
				}
				$size_slugs = array();
				foreach ( (array) $tokens['typography']['fontSizes'] as $fs ) {
					if ( isset( $fs['slug'] ) ) { $size_slugs[] = (string) $fs['slug']; }
				}
				$summary = sprintf(
					'colors:%s; fonts:%s; sizes:%s',
					implode( ',', $color_pairs ),
					implode( ',', $font_slugs ),
					implode( ',', $size_slugs )
				);
				break;

			case 'json':
				$summary = (string) wp_json_encode( $tokens );
				break;

			case 'markdown':
			default:
				$lines = array();
				$lines[] = '# Signal & Noise design system';
				$lines[] = '';
				$lines[] = '## Colors';
				foreach ( (array) $tokens['colors'] as $slug => $hex ) {
					$lines[] = "- `$slug` — $hex";
				}
				$lines[] = '';
				$lines[] = '## Typography';
				$lines[] = '';
				$lines[] = '### Font families';
				foreach ( (array) $tokens['typography']['fontFamilies'] as $ff ) {
					$slug = isset( $ff['slug'] ) ? (string) $ff['slug'] : '';
					$name = isset( $ff['name'] ) ? (string) $ff['name'] : '';
					$fam  = isset( $ff['fontFamily'] ) ? (string) $ff['fontFamily'] : '';
					$lines[] = "- `$slug` ($name) — $fam";
				}
				$lines[] = '';
				$lines[] = '### Font sizes';
				foreach ( (array) $tokens['typography']['fontSizes'] as $fs ) {
					$slug = isset( $fs['slug'] ) ? (string) $fs['slug'] : '';
					$size = isset( $fs['size'] ) ? (string) $fs['size'] : '';
					$lines[] = "- `$slug` — $size";
				}
				$lines[] = '';
				$lines[] = '## Spacing';
				if ( ! empty( $tokens['spacing']['spacingSizes'] ) ) {
					foreach ( (array) $tokens['spacing']['spacingSizes'] as $sp ) {
						$slug = isset( $sp['slug'] ) ? (string) $sp['slug'] : '';
						$size = isset( $sp['size'] ) ? (string) $sp['size'] : '';
						$lines[] = "- `$slug` — $size";
					}
				}
				$summary = implode( "\n", $lines );
				break;
		}

		// Chars/4 heuristic for token estimate (matches Anthropic's docs).
		$token_estimate = (int) ceil( strlen( $summary ) / 4 );

		return array(
			'format'         => $format,
			'summary'        => $summary,
			'token_estimate' => $token_estimate,
		);
	} catch ( \Throwable $e ) {
		error_log( 'SN theme ability error in get-design-system-summary: ' . $e->getMessage() );
		return new WP_Error(
			'theme_ability_error',
			sprintf( 'Theme ability failed: %s', $e->getMessage() ),
			array( 'status' => 500 )
		);
	}
}
