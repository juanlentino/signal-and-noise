<?php
/**
 * Signal & Noise — Abilities API: diagnostics abilities.
 *
 * 7 read abilities in the 'diagnostics' category:
 *   - signal-and-noise/get-active-template-structure
 *   - signal-and-noise/get-theme-version
 *   - signal-and-noise/get-design-system-summary
 *   - signal-and-noise/get-design-tokens
 *   - signal-and-noise/get-latest-theme-tag (added v9.9.0)
 *   - signal-and-noise/get-seo-route-meta (added v10.29.0)
 *   - signal-and-noise/get-llms-txt (added v10.29.0)
 *
 * Extracted from inc/abilities-registration.php by the v9.1.7 split (B-11
 * theme-side, companion to plugin v4.1.3). The first 4 impl functions are
 * co-located with their registrations; get-latest-theme-tag (v9.9.0)
 * delegates to sn_gh_latest_theme_tag() in inc/wp-update-integration.php.
 *
 * Cross-file note: sn_theme_ability_design_system_summary() internally
 * calls sn_theme_ability_design_tokens() (also in this file — same-file
 * call). No other diagnostics ability has external dependencies.
 *
 * v10.42.x: sn_theme_ability_design_tokens() unwraps wp_get_global_settings()'s
 * per-origin ('default'/'theme'/'custom') preset buckets into flat entries
 * (see sn_theme_flatten_preset_origins()) and refuses to return a hollow
 * token set (see sn_theme_design_tokens_has_content()) — both the reader
 * and the summary formatter surface a design_tokens_empty WP_Error instead
 * of a plausible-looking empty document.
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

/**
 * True when a design-tokens payload has enough real content to be usable.
 *
 * Guards BOTH sn_theme_ability_design_tokens() (dies at the source) and
 * sn_theme_ability_design_system_summary() (refuses to format a hollow
 * document) against fabricating a plausible-looking empty result when the
 * underlying theme.json read comes back with nothing real — a genuinely
 * tokenless settings tree, or the origin-bucket misread the reader's own
 * fix guards against.
 *
 * @since 10.42.1
 * @param array $tokens The sn_theme_ability_design_tokens() output shape.
 * @return bool
 */
function sn_theme_design_tokens_has_content( $tokens ) {
	if ( ! empty( $tokens['colors'] ) ) {
		return true;
	}
	$font_families = isset( $tokens['typography']['fontFamilies'] ) ? (array) $tokens['typography']['fontFamilies'] : array();
	foreach ( $font_families as $ff ) {
		if ( is_array( $ff ) && ! empty( $ff['slug'] ) ) {
			return true;
		}
	}
	$font_sizes = isset( $tokens['typography']['fontSizes'] ) ? (array) $tokens['typography']['fontSizes'] : array();
	foreach ( $font_sizes as $fs ) {
		if ( is_array( $fs ) && ! empty( $fs['slug'] ) ) {
			return true;
		}
	}
	return false;
}

/**
 * Merges a theme.json preset array across WP core's origin buckets.
 *
 * wp_get_global_settings() presets that use the "append" merge strategy
 * (color.palette, typography.fontFamilies, typography.fontSizes,
 * spacing.spacingSizes) come back keyed by ORIGIN — 'default' (core),
 * 'theme' (theme.json), 'custom' (user Global Styles) — not as a flat
 * list of entries. Reading a bucket as if it were a single preset entry
 * is how this ability shipped born-broken: colors silently dropped to
 * zero (a bucket has no 'slug'/'color' pair of its own) and fontFamilies/
 * fontSizes/spacingSizes reported a count equal to the number of origin
 * keys present, never the number of real presets.
 *
 * Core's own precedence is default -> theme -> custom: a later origin's
 * entry overrides an earlier one of the SAME slug (a theme.json palette
 * color overrides core's default of that slug; a user's Global Styles
 * pick overrides the theme's).
 *
 * Tolerates an ALREADY-flat entry list (no recognized origin key present)
 * by passing it through unchanged — defensive for callers/fixtures that
 * hand back a pre-resolved list directly.
 *
 * @since 10.42.1
 * @param mixed $value Raw preset value from wp_get_global_settings().
 * @return array<int,array> Flat list of preset entries.
 */
function sn_theme_flatten_preset_origins( $value ) {
	$value = (array) $value;
	if ( empty( $value ) ) {
		return array();
	}

	$origin_order    = array( 'default', 'theme', 'custom' );
	$is_origin_keyed = false;
	foreach ( $origin_order as $origin ) {
		if ( array_key_exists( $origin, $value ) ) {
			$is_origin_keyed = true;
			break;
		}
	}

	if ( ! $is_origin_keyed ) {
		return array_values( $value );
	}

	$merged = array();
	foreach ( $origin_order as $origin ) {
		if ( ! isset( $value[ $origin ] ) || ! is_array( $value[ $origin ] ) ) {
			continue;
		}
		foreach ( $value[ $origin ] as $entry ) {
			if ( is_array( $entry ) && isset( $entry['slug'] ) ) {
				$merged[ (string) $entry['slug'] ] = $entry;
			}
		}
	}
	return array_values( $merged );
}

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

		$colors      = array();
		$palette_raw = isset( $settings['color']['palette'] ) ? $settings['color']['palette'] : array();
		foreach ( sn_theme_flatten_preset_origins( $palette_raw ) as $entry ) {
			if ( is_array( $entry ) && isset( $entry['slug'], $entry['color'] ) ) {
				$colors[ (string) $entry['slug'] ] = (string) $entry['color'];
			}
		}

		$typography = isset( $settings['typography'] ) ? (array) $settings['typography'] : array();
		$spacing    = isset( $settings['spacing'] )    ? (array) $settings['spacing']    : array();

		// spacingScale is a single resolved SETTING VALUE, not an append-merge
		// preset list — origins override it outright rather than appending, so
		// wp_get_global_settings() already hands back one value. No origin
		// unwrap needed here; pass through as before.
		$spacing_scale = isset( $spacing['spacingScale'] ) ? (array) $spacing['spacingScale'] : array();

		$theme   = function_exists( 'wp_get_theme' ) ? wp_get_theme() : null;
		$version = $theme && method_exists( $theme, 'get' ) ? (string) $theme->get( 'Version' ) : '';

		$tokens = array(
			'colors'     => $colors,
			'typography' => array(
				'fontFamilies' => sn_theme_flatten_preset_origins( isset( $typography['fontFamilies'] ) ? $typography['fontFamilies'] : array() ),
				'fontSizes'    => sn_theme_flatten_preset_origins( isset( $typography['fontSizes'] )    ? $typography['fontSizes']    : array() ),
			),
			'spacing'    => array(
				'spacingScale' => $spacing_scale,
				'spacingSizes' => sn_theme_flatten_preset_origins( isset( $spacing['spacingSizes'] ) ? $spacing['spacingSizes'] : array() ),
			),
			'version'    => $version,
		);

		// The read must fail LOUDLY, not hand back a plausible-looking empty
		// token set: a genuinely hollow theme.json (or a future regression of
		// the origin unwrap above) is a read failure, not an empty design
		// system. Dies at the source so every consumer — get-design-tokens
		// callers AND get-design-system-summary — sees the same signal.
		if ( ! sn_theme_design_tokens_has_content( $tokens ) ) {
			return new WP_Error(
				'design_tokens_empty',
				'Design tokens read as empty: theme.json presets (color.palette, typography.fontFamilies, typography.fontSizes) resolved to zero real entries. Treating this as a read failure rather than an empty design system.',
				array( 'status' => 500 )
			);
		}

		return $tokens;
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

		// v10.0.0: the WP 7.0 floor guarantees wp_get_wp_version() (added in 6.7).
		$wp_version = (string) wp_get_wp_version();

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

		// Never format a plausible-looking empty document. A hollow read (no
		// colors, no named font family/size) is a failure of the read, not an
		// empty design system — surface it as the SAME design_tokens_empty
		// error sn_theme_ability_design_tokens() itself guards against, so
		// this holds even if that guard is ever bypassed or regresses.
		if ( ! sn_theme_design_tokens_has_content( $tokens ) ) {
			return new WP_Error(
				'design_tokens_empty',
				'Cannot summarize: the design-tokens read came back empty (no colors, font families, or font sizes). Refusing to format a plausible-looking empty summary.',
				array( 'status' => 500 )
			);
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

/**
 * Ability execute_callback for signal-and-noise/get-latest-theme-tag.
 *
 * Delegates to sn_gh_latest_theme_tag() in inc/wp-update-integration.php.
 * That function handles the GitHub API call + caching + filter dispatch
 * to the plugin (via sn_gh_latest_theme_tag_result contract listener).
 *
 * @since 9.9.0
 * @param mixed $input { force_refresh?: bool } or null.
 * @return array{ok:bool,tag:?string}
 */
function sn_theme_ability_get_latest_theme_tag( $input ) {
	if ( ! function_exists( 'sn_gh_latest_theme_tag' ) ) {
		return array( 'ok' => false, 'tag' => null );
	}
	// force_refresh triggers a fresh outbound GitHub API call. Honor it ONLY for
	// operators (manage_options) so a read-capable subscriber cannot drive
	// unthrottled outbound calls via the core /wp-abilities run-path. Everyone
	// else gets the cached tag, keeping the ability readable for agents.
	$force_refresh = is_array( $input ) && ! empty( $input['force_refresh'] ) && current_user_can( 'manage_options' );
	$tag = sn_gh_latest_theme_tag( $force_refresh );
	if ( ! is_string( $tag ) || '' === $tag ) {
		return array( 'ok' => false, 'tag' => null );
	}
	return array( 'ok' => true, 'tag' => $tag );
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
		$body  = (string) sn_llms_txt_body( $full, $notes );
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
