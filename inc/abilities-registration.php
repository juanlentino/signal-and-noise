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
