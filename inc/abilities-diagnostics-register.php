<?php
/**
 * Abilities API: the diagnostics ability registrations.
 *
 * Moved verbatim out of inc/abilities-diagnostics.php, which loads it.
 *
 * @package SignalNoise
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
		'description'         => 'Returns the active theme name + version + parent template + is_block_theme flag + supports_fse flag (alias of is_block_theme) + WP version. Use to detect drift between published roadmap docs and the live site.',
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
				// v12.0.1 — THIS SCHEMA IS THE CONTRACT, AND IT IS VALIDATED.
				// v12.0.0 reshaped `colors` from a flat slug => hex map into the
				// struct below and did NOT update this block, which still said
				// "every property is a hex string". The Abilities API validates
				// the execute result against it, so the ability failed on the
				// live site the moment the release installed — and because
				// sn-site-facts degrades a single failing fact to
				// {error:"unavailable"} and returns its siblings normally,
				// nothing went red anywhere. The one ability whose reshape
				// justified the major was the one that went dark.
				//
				// It survived 2,349 assertions because every test calls
				// sn_theme_ability_design_tokens() DIRECTLY, which bypasses
				// registration and therefore bypasses this schema. The payload
				// was asserted; the payload against its own declaration was not.
				// tests/design-tokens.php now validates one against the other.
				'colors'     => array(
					'type'        => 'object',
					'description' => 'Every palette the site can present, plus which one is live.',
					'required'    => array( 'served', 'resolved', 'palettes' ),
					'properties'  => array(
						'served'   => array(
							'type'        => 'string',
							'description' => "Id of the shipped palette WordPress actually resolved ('root', a styles/*.json basename, or 'custom' when the owner has edited colours in the Site Editor and no shipped palette matches). Empty when WordPress cannot be asked.",
						),
						'resolved' => array(
							'type'                 => 'object',
							'description'          => 'The palette in effect for THIS request, slug => hex.',
							'additionalProperties' => array( 'type' => 'string', 'format' => 'color-hex' ),
						),
						'palettes' => array(
							'type'                 => 'object',
							'description'          => 'Keyed by palette IDENTITY, so a new palette is an additive key rather than a reshape. Each entry is a COMPLETE palette (variation overrides merged over root).',
							'additionalProperties' => array(
								'type'       => 'object',
								'properties' => array(
									'scheme' => array(
										'type'        => 'string',
										'enum'        => array( 'light', 'dark' ),
										'description' => 'Colour scheme this palette belongs to. A field, not a key: variation and scheme are orthogonal, since the dark layer overrides :root and replaces whatever variation is active.',
									),
									'source' => array(
										'type'        => 'string',
										'description' => 'Theme-relative file the palette is read from.',
									),
									'colors' => array(
										'type'                 => 'object',
										'additionalProperties' => array( 'type' => 'string', 'format' => 'color-hex' ),
									),
								),
							),
						),
					),
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

		wp_register_ability( 'signal-and-noise/get-latest-theme-tag', array(
			'label'               => 'Get latest Signal & Noise theme release tag from GitHub',
			'description'         => 'Returns the latest published GitHub release tag for the Signal & Noise theme. Useful for AI agents checking whether a theme update is available. Hits the GitHub API with the standard `sn_gh_latest_theme_tag()` retry + cache pipeline. Read-only.',
			'category'            => 'diagnostics',
			'permission_callback' => 'sn_theme_perm_read',
			'execute_callback'    => 'sn_theme_ability_get_latest_theme_tag',
			'input_schema'        => array(
				'type'                 => array( 'object', 'null' ),
				'properties'           => array(
					'force_refresh' => array(
						'type'        => 'boolean',
						'description' => 'Bypass the cached tag and force a fresh GitHub API call. Honored only for callers who can manage_options; ignored (cached tag returned) otherwise. Default false.',
						'default'     => false,
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'ok'  => array( 'type' => 'boolean' ),
					'tag' => array( 'type' => array( 'string', 'null' ), 'description' => 'Tag string like "v9.5.0", or null on API failure.' ),
				),
			),
			'meta'                => array(
				'show_in_rest' => true,
				'annotations'  => array(
					'idempotent'      => true,
					'open_world_hint' => true,
					'readonly'        => true,
				),
			),
		) );
	wp_register_ability( 'signal-and-noise/get-seo-route-meta', array(
		'label'               => 'Get SEO meta for template-driven routes',
		'description'         => 'Returns the theme-supplied SEO meta description for the template-driven Pages that ship without an excerpt for WordPress to describe them with. After the pages-to-CMS flip (Phases 2a–2c) every former virtual route (/now, /about/uses, /accessibility, /contact/personal) is now a real CMS Page whose Excerpt supplies its meta description, so this map now covers only excerpt-less template Pages: today that is just /colophon. Pass `slug` to fetch one route; omit it for the full route→description map (useful for spotting a template Page shipped without a description). Read-only.',
		'category'            => 'diagnostics',
		'permission_callback' => 'sn_theme_perm_read',
		'execute_callback'    => 'sn_theme_ability_seo_route_meta',
		'input_schema'        => array(
			'type'       => array( 'object', 'null' ),
			'properties' => array(
				'slug' => array( 'type' => 'string', 'description' => 'A template-Page slug or path (e.g., "colophon" or "/colophon"). Omit to return every theme-owned route.' ),
			),
			'additionalProperties' => false,
		),
		'output_schema'       => array(
			'type'       => 'object',
			'required'   => array( 'routes', 'count' ),
			'properties' => array(
				'routes' => array(
					'type'        => 'array',
					'description' => 'Theme-owned routes and the SEO description each emits (one entry when `slug` matches, empty when it does not).',
					'items'       => array(
						'type'       => 'object',
						'properties' => array(
							'slug'        => array( 'type' => 'string', 'description' => 'Page slug.' ),
							'path'        => array( 'type' => 'string', 'description' => 'Site path, e.g. "/colophon".' ),
							'description' => array( 'type' => 'string', 'description' => 'The meta description the theme supplies for this route.' ),
						),
					),
				),
				'count'  => array( 'type' => 'integer', 'minimum' => 0, 'description' => 'Number of routes returned.' ),
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

	wp_register_ability( 'signal-and-noise/get-llms-txt', array(
		'label'               => 'Get the llms.txt AI-crawler manifest',
		'description'         => 'Returns the theme-generated llms.txt manifest — the site\'s machine-readable index and summary for LLMs and answer engines (the AEO counterpart to robots.txt / sitemap.xml). Pass `full: true` for the extended variant that appends the recent Notes corpus; omit for the concise index. Read-only.',
		'category'            => 'diagnostics',
		'permission_callback' => 'sn_theme_perm_read',
		'execute_callback'    => 'sn_theme_ability_llms_txt',
		'input_schema'        => array(
			'type'       => array( 'object', 'null' ),
			'properties' => array(
				'full' => array( 'type' => 'boolean', 'description' => 'Return the extended variant (appends the recent Notes corpus). Default false (concise index).', 'default' => false ),
			),
			'additionalProperties' => false,
		),
		'output_schema'       => array(
			'type'       => 'object',
			'required'   => array( 'variant', 'content' ),
			'properties' => array(
				'variant' => array( 'type' => 'string', 'enum' => array( 'index', 'full' ), 'description' => 'Which manifest variant was rendered.' ),
				'content' => array( 'type' => 'string', 'description' => 'The rendered llms.txt body (plain-text Markdown).' ),
				'bytes'   => array( 'type' => 'integer', 'minimum' => 0, 'description' => 'Byte length of the rendered content.' ),
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
