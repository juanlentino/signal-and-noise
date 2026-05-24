<?php
/**
 * Signal & Noise — Theme-owned WP 7.0 Abilities API registration.
 *
 * Registers 12 abilities exposing theme design knowledge + brand-aware
 * generative capabilities. Categories registered defensively because
 * the companion plugin (signal-and-noise-tools v2.0.4+) also registers
 * the same 3 categories — per upstream source at
 * class-wp-ability-categories-registry.php:57-67, calling
 * wp_register_ability_category() on an already-registered slug fires
 * _doing_it_wrong. The wp_has_ability_category guard handles all 3
 * install states (theme-only, plugin-only, both).
 *
 * Abilities registered (12):
 *   Read abilities (7):
 *     - signal-noise/get-design-tokens
 *     - signal-noise/list-block-patterns
 *     - signal-noise/get-active-template-structure
 *     - signal-noise/get-theme-version
 *     - signal-noise/get-page-notes-pillars
 *     - signal-noise/get-reading-time-for-slug
 *     - signal-noise/get-design-system-summary
 *   Generative abilities (5; require plugin's AI helper):
 *     - signal-noise/ai-generate-page-note-summary
 *     - signal-noise/ai-suggest-block-pattern
 *     - signal-noise/ai-validate-brand-alignment
 *     - signal-noise/ai-generate-pattern-content
 *     - signal-noise/ai-rewrite-in-brand-voice
 *
 * Plugin dependency: generative abilities call
 * snt_ai_generate_with_constraints() (plugin v3.7.x+). The function is
 * guarded with function_exists; if the plugin is missing, generative
 * abilities return WP_Error('ai_helper_unavailable') with status 503.
 *
 * Cross-package filter contract: NOT extended. The 3 existing filters
 * (sn_purge_all_caches_result, sn_clear_template_overrides_result,
 * sn_og_font_paths) stay at 3. Theme→plugin coupling here is a
 * function-call, not a filter.
 *
 * Spec: docs/superpowers/specs/2026-05-24-theme-ai-abilities-design.md
 *
 * @package SignalNoise
 * @since 9.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Brand-voice system instruction shared by ai-validate-brand-alignment
 * and ai-rewrite-in-brand-voice. Single source of truth for "what the
 * SN voice sounds like" — keeps the two abilities aligned.
 *
 * Edit this constant if the brand voice evolves; both abilities pick
 * up the change without further edits.
 */
const SN_THEME_BRAND_VOICE_SYSTEM = "You are a brand-voice expert for Signal & Noise — a brutalist, white-first, industrial-catalog brand inspired by Nine Inch Nails (nin.com) and clinical engineering documentation. The site's identity: stripped-back, terminal-mono typography (Bebas Neue + DM Mono), black text on white, blood-red (#e00404) accents reserved for emphasis. Tone: direct, technical, declarative; no marketing fluff; no exclamation points; no second-person hype. Sentences are short and load-bearing. Vocabulary leans toward engineering nouns (substrate, fingerprint, provenance, signal, noise, dossier, catalog) over consumer-facing verbs (discover, explore, unlock). Lists are spec-sheet-style with verb-leading items. The voice never apologizes, never qualifies, never asks the reader for time. It states. Use this voice when validating brand alignment or rewriting external copy.";

/**
 * Voice system instruction specifically tuned for /notes catalog
 * summaries — shorter, more catalog-row-oriented than the general
 * brand voice. Used by ai-generate-page-note-summary.
 */
const SN_THEME_NOTES_VOICE_SYSTEM = "You write entries for the Signal & Noise /notes catalog — a brutalist directory of essays styled like an industrial parts catalog. Summaries are single sentences, declarative, present-tense, technical. Lead with the noun (the subject under discussion), not a verb or pronoun. No 'this post argues' framing. No 'we' or 'I'. Vocabulary: provenance, substrate, signal, noise, fingerprint, drift, anchor, catalog, dossier, primitive, contract. Target length: 18–35 words. Output ONLY the summary sentence — no preamble, no explanation, no trailing punctuation beyond a period. Example shape: 'Provenance treats music as a forensic substrate where origin is proven by cryptographic fingerprint, not claimed by metadata.'";

/**
 * Returns true if the plugin's AI helper is callable in this request.
 *
 * Centralizes the function_exists guard for the 5 generative abilities.
 * Tests can force the false branch via $GLOBALS['__test_ai_helper_disabled'].
 *
 * @since 9.1.0
 */
function sn_theme_ai_helper_available() {
	if ( ! empty( $GLOBALS['__test_ai_helper_disabled'] ) ) {
		return false;
	}
	return function_exists( 'snt_ai_generate_with_constraints' );
}

/**
 * Returns the standard WP_Error returned when generative abilities are
 * invoked but the plugin's AI helper is missing.
 *
 * @since 9.1.0
 */
function sn_theme_ai_unavailable_error() {
	return new WP_Error(
		'ai_helper_unavailable',
		'AI helper not available. Install or update signal-and-noise-tools plugin to v3.7.x+.',
		array( 'status' => 503 )
	);
}

/**
 * Strip optional markdown code fences from an AI response and parse
 * as JSON. Per the v3.7.0 Task B lesson — models sometimes wrap JSON
 * in ```json ... ``` fences regardless of system instructions.
 *
 * @since 9.1.0
 * @param string $raw Raw AI text.
 * @return array|null Parsed array on success, null on parse failure.
 */
function sn_theme_parse_ai_json( $raw ) {
	$text = trim( preg_replace( '/^```(?:json)?\s*|\s*```$/i', '', (string) $raw ) );
	$parsed = json_decode( $text, true );
	return is_array( $parsed ) ? $parsed : null;
}

/**
 * Phase 1: register the 3 ability categories defensively.
 *
 * Per source-verified upstream behavior, calling
 * wp_register_ability_category() on an already-registered slug fires
 * _doing_it_wrong. The plugin (signal-and-noise-tools) typically
 * registers these first via the same hook; this theme-side
 * registration is the fallback for theme-only installs.
 *
 * Public function (not anonymous) so the test harness can invoke it
 * directly without depending on do_action.
 *
 * @since 9.1.0
 */
function sn_theme_register_ability_categories() {
	if ( ! function_exists( 'wp_register_ability_category' ) ) {
		return;
	}
	if ( ! function_exists( 'wp_has_ability_category' ) ) {
		return;
	}

	if ( ! wp_has_ability_category( 'diagnostics' ) ) {
		wp_register_ability_category( 'diagnostics', array(
			'label'       => 'Diagnostics',
			'description' => "Read-only inspection of the theme + plugin pair's state.",
		) );
	}
	if ( ! wp_has_ability_category( 'content' ) ) {
		wp_register_ability_category( 'content', array(
			'label'       => 'Content',
			'description' => 'Per-post content artifacts (OG cards, schema, etc.).',
		) );
	}
	if ( ! wp_has_ability_category( 'ai-generation' ) ) {
		wp_register_ability_category( 'ai-generation', array(
			'label'       => 'AI Generation',
			'description' => 'AI-generated content artifacts (meta descriptions, summaries, etc.).',
		) );
	}
}
add_action( 'wp_abilities_api_categories_init', 'sn_theme_register_ability_categories' );

/**
 * Phase 2: register the 12 abilities.
 *
 * Public function (not anonymous) so the test harness can invoke
 * directly. Each ability registration is a single wp_register_ability
 * call; execute_callbacks are top-level functions defined below.
 *
 * @since 9.1.0
 */
function sn_theme_register_abilities() {
	if ( ! function_exists( 'wp_register_ability' ) ) {
		return;
	}

	// Permission helpers (private to this function).
	$permission_read       = function() { return is_user_logged_in() ? true : current_user_can( 'read' ); };
	$permission_edit_posts = function() { return current_user_can( 'edit_posts' ); };

	// Ability registrations are appended here in subsequent tasks (3-14).
	// Stubs intentionally omitted — tests will fail until each task
	// completes its corresponding registration.

	wp_register_ability( 'signal-noise/list-block-patterns', array(
		'label'               => 'List block patterns',
		'description'         => 'Enumerates all registered block patterns with category + keywords + viewport hints. Optional `category` input filters to a single pattern category.',
		'category'            => 'content',
		'permission_callback' => $permission_read,
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
				'patterns'   => array( 'type' => 'array' ),
				'categories' => array( 'type' => 'array' ),
			),
		),
		'meta'                => array(
			'show_in_rest' => true,
			'annotations'  => array(
				'idempotent'      => true,
				'open_world_hint' => false,
				'read_only'       => true,
			),
		),
	) );

	wp_register_ability( 'signal-noise/get-active-template-structure', array(
		'label'               => 'Inspect active template structure',
		'description'         => 'Returns the FSE template slug + a shallow block tree (blockName + attrs + innerBlocks count) for a given post by ID or slug. Does not recurse into innerBlocks beyond a count — keeps payload bounded.',
		'category'            => 'diagnostics',
		'permission_callback' => $permission_read,
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
				'template_slug'      => array( 'type' => 'string' ),
				'template_part_slugs' => array( 'type' => 'array' ),
				'blocks'             => array( 'type' => 'array' ),
			),
		),
		'meta'                => array(
			'show_in_rest' => true,
			'annotations'  => array(
				'idempotent'      => true,
				'open_world_hint' => false,
				'read_only'       => true,
			),
		),
	) );

	wp_register_ability( 'signal-noise/get-theme-version', array(
		'label'               => 'Get theme + WP version',
		'description'         => 'Returns the active theme name + version + parent template + is_block_theme flag + WP version. Use to detect drift between published roadmap docs and the live site.',
		'category'            => 'diagnostics',
		'permission_callback' => $permission_read,
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
				'read_only'       => true,
			),
		),
	) );

	wp_register_ability( 'signal-noise/get-page-notes-pillars', array(
		'label'               => 'List /notes pillar essays',
		'description'         => "Returns metadata for the SN /notes catalog pillar essays — slug, title, URL, summary dek, reading time, last modified. The pillars are project-defined in inc/page-notes-render.php and frame the /notes index.",
		'category'            => 'content',
		'permission_callback' => $permission_read,
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
				'pillars' => array( 'type' => 'array' ),
			),
		),
		'meta'                => array(
			'show_in_rest' => true,
			'annotations'  => array(
				'idempotent'      => true,
				'open_world_hint' => false,
				'read_only'       => true,
			),
		),
	) );

	wp_register_ability( 'signal-noise/get-reading-time-for-slug', array(
		'label'               => 'Get reading time for slug',
		'description'         => 'Returns the computed reading-time minutes for a post identified by slug. Wraps sn_notes_reading_time_for_slug() (the same helper that powers the [sn_reading_time] shortcode). Returns minutes=0 if the slug does not resolve.',
		'category'            => 'content',
		'permission_callback' => $permission_read,
		'execute_callback'    => 'sn_theme_ability_reading_time_for_slug',
		'input_schema'        => array(
			'type'       => 'object',
			'required'   => array( 'slug' ),
			'properties' => array(
				'slug' => array(
					'type'      => 'string',
					'minLength' => 1,
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
				'read_only'       => true,
			),
		),
	) );

	wp_register_ability( 'signal-noise/get-design-system-summary', array(
		'label'               => 'Get design-system summary (AI-prompt formatted)',
		'description'         => 'Formats the design tokens for AI prompt embedding. format=markdown (default) for structured prose, format=compact-text for minimum-token single-line embedding, format=json for full passthrough. Typical 70-80% token reduction vs raw get-design-tokens JSON on compact-text.',
		'category'            => 'diagnostics',
		'permission_callback' => $permission_read,
		'execute_callback'    => 'sn_theme_ability_design_system_summary',
		'input_schema'        => array(
			'type'                 => 'object',
			'properties'           => array(
				'format' => array(
					'type'    => 'string',
					'enum'    => array( 'markdown', 'compact-text', 'json' ),
					'default' => 'markdown',
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
				'read_only'       => true,
			),
		),
	) );

	wp_register_ability( 'signal-noise/ai-generate-page-note-summary', array(
		'label'               => 'Generate /notes-voice summary',
		'description'         => "Generates a brand-voiced single-sentence summary of a post in the SN /notes catalog vocabulary. Calls the plugin's AI helper (Sonnet 4.6 pinned via plugin v3.7.2+). Requires signal-and-noise-tools plugin.",
		'category'            => 'ai-generation',
		'permission_callback' => $permission_edit_posts,
		'execute_callback'    => 'sn_theme_ability_ai_page_note_summary',
		'input_schema'        => array(
			'type'       => 'object',
			'required'   => array( 'post_id' ),
			'properties' => array(
				'post_id'   => array( 'type' => 'integer', 'minimum' => 1 ),
				'max_words' => array(
					'type'    => 'integer',
					'minimum' => 10,
					'maximum' => 60,
					'default' => 30,
				),
			),
			'additionalProperties' => false,
		),
		'output_schema'       => array(
			'type'     => 'object',
			'required' => array( 'summary', 'post_id' ),
			'properties' => array(
				'summary'     => array( 'type' => 'string' ),
				'post_id'     => array( 'type' => 'integer' ),
				'tokens_used' => array( 'type' => 'integer' ),
			),
		),
		'meta'                => array(
			'show_in_rest' => true,
			'annotations'  => array(
				'idempotent'      => false,
				'open_world_hint' => false,
				'read_only'       => false,
			),
		),
	) );

	wp_register_ability( 'signal-noise/ai-suggest-block-pattern', array(
		'label'               => 'Suggest block pattern for draft',
		'description'         => "AI recommends 1–3 SN block patterns that fit a draft. Caller supplies the draft content; ability fetches the SN pattern catalog and asks the AI to pick the best matches. Requires signal-and-noise-tools plugin.",
		'category'            => 'ai-generation',
		'permission_callback' => $permission_edit_posts,
		'execute_callback'    => 'sn_theme_ability_ai_suggest_block_pattern',
		'input_schema'        => array(
			'type'       => 'object',
			'required'   => array( 'draft_content' ),
			'properties' => array(
				'draft_content' => array(
					'type'      => 'string',
					'minLength' => 20,
					'maxLength' => 4000,
				),
				'topic_hint'    => array(
					'type'      => 'string',
					'maxLength' => 200,
				),
			),
			'additionalProperties' => false,
		),
		'output_schema'       => array(
			'type'     => 'object',
			'required' => array( 'suggestions' ),
			'properties' => array(
				'suggestions' => array(
					'type'     => 'array',
					'minItems' => 1,
					'maxItems' => 3,
				),
				'tokens_used' => array( 'type' => 'integer' ),
			),
		),
		'meta'                => array(
			'show_in_rest' => true,
			'annotations'  => array(
				'idempotent'      => false,
				'open_world_hint' => false,
				'read_only'       => false,
			),
		),
	) );

	wp_register_ability( 'signal-noise/get-design-tokens', array(
		'label'               => 'Get design tokens',
		'description'         => "Returns the SN theme's color palette, typography (font families + sizes), and spacing scale from theme.json. Read-only.",
		'category'            => 'diagnostics',
		'permission_callback' => $permission_read,
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
					'properties' => array(
						'fontFamilies' => array( 'type' => 'array' ),
						'fontSizes'    => array( 'type' => 'array' ),
					),
				),
				'spacing'    => array(
					'type'       => 'object',
					'properties' => array(
						'spacingScale' => array( 'type' => 'object' ),
						'spacingSizes' => array( 'type' => 'array' ),
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
				'read_only'       => true,
			),
		),
	) );
}
add_action( 'wp_abilities_api_init', 'sn_theme_register_abilities' );

/**
 * Execute callback: signal-noise/get-design-tokens.
 *
 * Flattens theme.json palette into a name→hex map for cheap consumption,
 * passes typography + spacing through verbatim, and includes the theme
 * version that produced these tokens.
 *
 * @since 9.1.0
 * @return array|WP_Error Shaped tokens, or WP_Error if WP < 5.9.
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
 * Execute callback: signal-noise/list-block-patterns.
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
 * Execute callback: signal-noise/get-active-template-structure.
 *
 * Resolves the active FSE template for a given post (by id OR slug) and
 * returns a shallow summary of its block tree — blockName, attrs, and
 * innerBlocks count per top-level block. Does NOT recurse, keeping the
 * payload small and predictable for AI prompt embedding.
 *
 * @since 9.1.0
 * @param array $input { post_id?: int, post_type?: 'post'|'page', slug?: string }
 * @return array|WP_Error
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
 * Execute callback: signal-noise/get-theme-version.
 *
 * Returns theme + WP environment metadata. supports_fse is currently
 * aliased to is_block_theme — they're the same flag on WP 5.9+ but
 * separated for forward compatibility if FSE diverges from
 * block-theme status.
 *
 * @since 9.1.0
 * @return array|WP_Error
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
 * Returns the canonical SN /notes pillar essay descriptors.
 *
 * Hardcoded here (not derived from a DB query) because the pillars
 * are intentionally curated, not editorial — they frame the /notes
 * catalog and are mirrored in inc/page-notes-render.php HTML.
 * Mirroring them in PHP rather than parsing the HTML keeps both
 * surfaces editable and authoritative.
 *
 * @since 9.1.0
 * @return array<int, array{slug:string, title:string, dek:string, last_path:string}>
 */
function sn_theme_pillar_descriptors() {
	return array(
		array(
			'slug'      => 'provenance/over-detection',
			'title'     => 'Provenance Over Detection',
			'dek'       => "Detection chases what isn't. Provenance proves what is.",
			'last_path' => 'over-detection',
		),
		array(
			'slug'      => 'provenance/as-substrate',
			'title'     => 'Provenance as Substrate',
			'dek'       => 'Music files need fingerprints, not name tags.',
			'last_path' => 'as-substrate',
		),
	);
}

/**
 * Execute callback: signal-noise/get-page-notes-pillars.
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
 * Execute callback: signal-noise/get-reading-time-for-slug.
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

/**
 * Execute callback: signal-noise/get-design-system-summary.
 *
 * Calls sn_theme_ability_design_tokens() internally for the raw data,
 * then formats per the input.format. token_estimate uses the chars/4
 * heuristic that matches Anthropic's typical token density.
 *
 * @since 9.1.0
 * @param array $input { format?: 'markdown'|'compact-text'|'json' }
 * @return array|WP_Error
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

/**
 * Execute callback: signal-noise/ai-generate-page-note-summary.
 *
 * Composes a /notes-voice summary of a post. Uses the plugin's
 * snt_ai_extract_post_text (defensive guard) for the input and
 * snt_ai_generate_with_constraints for the AI call. The plugin's
 * helper pins Sonnet 4.6 (v3.7.2+) — theme inherits the pin.
 *
 * @since 9.1.0
 * @param array $input { post_id: int, max_words?: int }
 * @return array|WP_Error
 */
function sn_theme_ability_ai_page_note_summary( $input ) {
	try {
		if ( ! sn_theme_ai_helper_available() ) {
			return sn_theme_ai_unavailable_error();
		}
		if ( ! function_exists( 'snt_ai_extract_post_text' ) ) {
			return sn_theme_ai_unavailable_error();
		}

		$post_id   = isset( $input['post_id'] ) ? (int) $input['post_id'] : 0;
		$max_words = isset( $input['max_words'] ) ? (int) $input['max_words'] : 30;
		$max_words = max( 10, min( 60, $max_words ) );

		$post = function_exists( 'get_post' ) ? get_post( $post_id ) : null;
		if ( ! $post ) {
			return new WP_Error(
				'post_not_found',
				sprintf( 'Post %d not found.', $post_id ),
				array( 'status' => 404 )
			);
		}

		$body = (string) snt_ai_extract_post_text( $post_id, 1000 );
		if ( '' === trim( $body ) ) {
			return new WP_Error(
				'post_empty',
				'Post has no extractable text content.',
				array( 'status' => 422 )
			);
		}

		$prompt = "Summarize this post in the Signal & Noise /notes catalog voice. "
			. "Hard limit: $max_words words. Output the summary sentence only.\n\n"
			. "POST:\n" . $body;

		$max_tokens = max( 32, $max_words * 2 );
		$raw = snt_ai_generate_with_constraints( $prompt, SN_THEME_NOTES_VOICE_SYSTEM, $max_tokens );

		if ( is_wp_error( $raw ) ) {
			return $raw;
		}

		$summary = trim( (string) $raw );
		// Strip any leading/trailing quotes the model may wrap the
		// sentence in.
		$summary = trim( $summary, "\"'" );

		return array(
			'summary'     => $summary,
			'post_id'     => $post_id,
			'tokens_used' => (int) ceil( strlen( $summary ) / 4 ),
		);
	} catch ( \Throwable $e ) {
		error_log( 'SN theme ability error in ai-generate-page-note-summary: ' . $e->getMessage() );
		return new WP_Error(
			'theme_ability_error',
			sprintf( 'Theme ability failed: %s', $e->getMessage() ),
			array( 'status' => 500 )
		);
	}
}

/**
 * Execute callback: signal-noise/ai-suggest-block-pattern.
 *
 * Fetches the SN pattern catalog (via sn_theme_ability_list_block_patterns),
 * sends pattern names + descriptions + the draft to the AI, parses the
 * JSON response, validates each suggested pattern_name exists in the
 * registry, caps at 3 suggestions.
 *
 * @since 9.1.0
 * @param array $input { draft_content: string, topic_hint?: string }
 * @return array|WP_Error
 */
function sn_theme_ability_ai_suggest_block_pattern( $input ) {
	try {
		if ( ! sn_theme_ai_helper_available() ) {
			return sn_theme_ai_unavailable_error();
		}

		$draft = isset( $input['draft_content'] ) ? (string) $input['draft_content'] : '';
		$hint  = isset( $input['topic_hint'] )    ? (string) $input['topic_hint']    : '';

		$catalog = sn_theme_ability_list_block_patterns( array() );
		if ( is_wp_error( $catalog ) ) {
			return $catalog;
		}

		$valid_names = array();
		$catalog_compact = array();
		foreach ( (array) $catalog['patterns'] as $p ) {
			$valid_names[] = $p['name'];
			$catalog_compact[] = array(
				'name'        => $p['name'],
				'title'       => $p['title'],
				'description' => $p['description'],
			);
		}

		$system = "You are a block-pattern recommender for the Signal & Noise theme. Return ONLY valid JSON of shape {\"suggestions\":[{\"pattern_name\":\"...\",\"reasoning\":\"...\",\"confidence\":\"high|medium|low\"}]}. Pick 1–3 patterns. pattern_name MUST be one of the slugs in the catalog. No prose, no markdown.";

		$prompt = "CATALOG:\n" . wp_json_encode( $catalog_compact ) . "\n\nDRAFT:\n$draft";
		if ( '' !== $hint ) {
			$prompt .= "\n\nTOPIC HINT:\n$hint";
		}

		$raw = snt_ai_generate_with_constraints( $prompt, $system, 512 );
		if ( is_wp_error( $raw ) ) {
			return $raw;
		}

		$parsed = sn_theme_parse_ai_json( $raw );
		if ( null === $parsed || ! isset( $parsed['suggestions'] ) || ! is_array( $parsed['suggestions'] ) ) {
			error_log( 'SN ai-suggest-block-pattern: malformed JSON: ' . substr( (string) $raw, 0, 200 ) );
			return new WP_Error(
				'ai_malformed_response',
				'AI returned malformed JSON.',
				array( 'status' => 502 )
			);
		}

		$valid_suggestions = array();
		foreach ( $parsed['suggestions'] as $sug ) {
			if ( ! isset( $sug['pattern_name'] ) || ! in_array( $sug['pattern_name'], $valid_names, true ) ) {
				continue;
			}
			$conf = isset( $sug['confidence'] ) && in_array( $sug['confidence'], array( 'high', 'medium', 'low' ), true )
				? $sug['confidence']
				: 'medium';
			$valid_suggestions[] = array(
				'pattern_name' => (string) $sug['pattern_name'],
				'reasoning'    => isset( $sug['reasoning'] ) ? (string) $sug['reasoning'] : '',
				'confidence'   => $conf,
			);
			if ( count( $valid_suggestions ) >= 3 ) {
				break;
			}
		}

		if ( empty( $valid_suggestions ) ) {
			return new WP_Error(
				'no_valid_suggestions',
				'AI returned no suggestions matching the pattern registry.',
				array( 'status' => 502 )
			);
		}

		return array(
			'suggestions' => $valid_suggestions,
			'tokens_used' => (int) ceil( strlen( (string) $raw ) / 4 ),
		);
	} catch ( \Throwable $e ) {
		error_log( 'SN theme ability error in ai-suggest-block-pattern: ' . $e->getMessage() );
		return new WP_Error(
			'theme_ability_error',
			sprintf( 'Theme ability failed: %s', $e->getMessage() ),
			array( 'status' => 500 )
		);
	}
}
