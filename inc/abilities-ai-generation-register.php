<?php
/**
 * Abilities API: the AI generation ability registrations.
 *
 * Moved verbatim out of inc/abilities-ai-generation.php, which loads it.
 *
 * @package SignalNoise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function sn_theme_register_ai_generation_abilities() {
	if ( ! function_exists( 'wp_register_ability' ) ) {
		return;
	}

	wp_register_ability( 'signal-and-noise/ai-generate-page-note-summary', array(
		'label'               => 'Generate /notes-voice summary',
		'description'         => "Generates a brand-voiced single-sentence summary of a post in the SN /notes catalog vocabulary. Calls the plugin's AI helper (model configurable via the plugin's Front-End settings; defaults to Claude Sonnet 5). Requires the signal-and-noise-tools plugin.",
		'category'            => 'ai-generation',
		// Per-post gate (v9.15.3): this ability reads a specific post's body, so
		// it must check edit_post(post_id), not the blanket edit_posts — otherwise
		// any contributor could summarize/exfiltrate arbitrary drafts via post_id.
		'permission_callback' => 'sn_theme_perm_edit_post',
		'execute_callback'    => 'sn_theme_ability_ai_page_note_summary',
		'input_schema'        => array(
			'type'       => 'object',
			'required'   => array( 'post_id' ),
			'properties' => array(
				'post_id'   => array( 'type' => 'integer', 'minimum' => 1, 'examples' => array( 42, 1023 ) ),
				'max_words' => array( 'type' => 'integer', 'minimum' => 10, 'maximum' => 60, 'default' => 30, 'examples' => array( 30, 45 ) ),
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
				'readonly'        => false,
			),
		),
	) );

	wp_register_ability( 'signal-and-noise/ai-suggest-block-pattern', array(
		'label'               => 'Suggest block pattern for draft',
		'description'         => "AI recommends 1–3 SN block patterns that fit a draft. Caller supplies the draft content; ability fetches the SN pattern catalog and asks the AI to pick the best matches. Requires signal-and-noise-tools plugin.",
		'category'            => 'ai-generation',
		'permission_callback' => 'sn_theme_perm_edit_posts',
		'execute_callback'    => 'sn_theme_ability_ai_suggest_block_pattern',
		'input_schema'        => array(
			'type'       => 'object',
			'required'   => array( 'draft_content' ),
			'properties' => array(
				'draft_content' => array(
					'type'      => 'string',
					'minLength' => 20,
					'maxLength' => 4000,
					'examples'  => array( "# Mastering loudness\n\nLet me walk you through how -14 LUFS became the streaming target." ),
				),
				'topic_hint'    => array(
					'type'      => 'string',
					'maxLength' => 200,
					'examples'  => array( 'audio-engineering tutorial', 'gear review' ),
				),
			),
			'additionalProperties' => false,
		),
		'output_schema'       => array(
			'type'     => 'object',
			'required' => array( 'suggestions' ),
			'properties' => array(
				'suggestions' => array(
					'type'        => 'array',
					'description' => 'Recommended block patterns ranked by AI fit assessment; capped at 3.',
					'minItems'    => 1,
					'maxItems'    => 3,
					'items'       => array(
						'type'       => 'object',
						'properties' => array(
							'pattern_name' => array( 'type' => 'string', 'description' => 'Pattern slug; guaranteed to exist in the block pattern registry at response time.' ),
							'reasoning'    => array( 'type' => 'string', 'description' => 'AI rationale for why this pattern fits the draft.' ),
							'confidence'   => array( 'type' => 'string', 'enum' => array( 'high', 'medium', 'low' ), 'description' => 'AI confidence band; invalid values from the model are sanitized to "medium".' ),
						),
					),
				),
				'tokens_used' => array( 'type' => 'integer' ),
			),
		),
		'meta'                => array(
			'show_in_rest' => true,
			'annotations'  => array(
				'idempotent'      => false,
				'open_world_hint' => false,
				'readonly'        => false,
			),
		),
	) );

	wp_register_ability( 'signal-and-noise/ai-validate-brand-alignment', array(
		'label'               => 'Validate brand alignment',
		'description'         => "AI scores content (0-100) for fit with the SN brand: voice, tone, vocabulary, palette references, structure. Returns score + per-dimension findings with verdict (aligned|drift|off-brand) + note. Uses the shared brand-voice constant.",
		'category'            => 'ai-generation',
		'permission_callback' => 'sn_theme_perm_edit_posts',
		'execute_callback'    => 'sn_theme_ability_ai_validate_brand_alignment',
		'input_schema'        => array(
			'type'       => 'object',
			'required'   => array( 'content' ),
			'properties' => array(
				'content'      => array(
					'type'      => 'string',
					'minLength' => 50,
					'maxLength' => 8000,
					'examples'  => array( 'Welcome to Signal & Noise — a destination for audio engineering insights and mastering reference content.' ),
				),
				'content_type' => array(
					'type'     => 'string',
					'enum'     => array( 'copy', 'title', 'summary', 'longform' ),
					'default'  => 'copy',
					'examples' => array( 'copy', 'longform' ),
				),
			),
			'additionalProperties' => false,
		),
		'output_schema'       => array(
			'type'     => 'object',
			'required' => array( 'overall_score', 'findings' ),
			'properties' => array(
				'overall_score' => array( 'type' => 'integer', 'minimum' => 0, 'maximum' => 100 ),
				'findings'      => array(
					'type'        => 'array',
					'description' => 'Per-dimension brand-alignment findings from the AI evaluation.',
					'items'       => array(
						'type'       => 'object',
						'properties' => array(
							'dimension' => array( 'type' => 'string', 'enum' => array( 'voice', 'tone', 'vocabulary', 'palette_fit', 'structure' ), 'description' => 'Brand-alignment dimension; invalid values from the model are sanitized to "voice".' ),
							'verdict'   => array( 'type' => 'string', 'enum' => array( 'aligned', 'drift', 'off-brand' ), 'description' => 'Per-dimension verdict; invalid values from the model are sanitized to "drift" (safe pessimistic default).' ),
							'note'      => array( 'type' => 'string', 'description' => 'AI rationale for the verdict on this dimension.' ),
						),
					),
				),
				'tokens_used'   => array( 'type' => 'integer' ),
			),
		),
		'meta'                => array(
			'show_in_rest' => true,
			'annotations'  => array(
				'idempotent'      => false,
				'open_world_hint' => false,
				'readonly'        => false,
			),
		),
	) );

	wp_register_ability( 'signal-and-noise/ai-generate-pattern-content', array(
		'label'               => 'Generate pattern content',
		'description'         => "Fills a chosen SN block pattern's shell with brand-voiced copy on a given topic. Returns ready-to-paste serialized Gutenberg block markup. Does NOT save anything — caller decides whether to use the markup.",
		'category'            => 'ai-generation',
		'permission_callback' => 'sn_theme_perm_edit_posts',
		'execute_callback'    => 'sn_theme_ability_ai_generate_pattern_content',
		'input_schema'        => array(
			'type'       => 'object',
			'required'   => array( 'pattern_name', 'topic' ),
			'properties' => array(
				'pattern_name' => array(
					'type'        => 'string',
					'description' => 'Pattern slug from list-block-patterns; must exist in registry.',
					'examples'    => array( 'signal-noise/hero-dossier', 'signal-noise/section-constrained' ),
				),
				'topic'        => array(
					'type'      => 'string',
					'minLength' => 5,
					'maxLength' => 500,
					'examples'  => array( 'mastering for streaming platforms', 'monitor calibration in untreated rooms' ),
				),
				'tone_hint'    => array(
					'type'     => 'string',
					'enum'     => array( 'technical', 'narrative', 'manifesto', 'spec-sheet' ),
					'default'  => 'spec-sheet',
					'examples' => array( 'spec-sheet', 'technical' ),
				),
			),
			'additionalProperties' => false,
		),
		'output_schema'       => array(
			'type'     => 'object',
			'required' => array( 'block_markup', 'pattern_name' ),
			'properties' => array(
				'block_markup' => array( 'type' => 'string' ),
				'pattern_name' => array( 'type' => 'string' ),
				'tokens_used'  => array( 'type' => 'integer' ),
				'warnings'     => array(
					'type'        => 'array',
					'description' => 'Non-fatal advisories surfaced during generation (e.g., parse_blocks failed to validate the output).',
					'items'       => array( 'type' => 'string', 'description' => 'Human-readable warning message.' ),
				),
			),
		),
		'meta'                => array(
			'show_in_rest' => true,
			'annotations'  => array(
				'idempotent'      => false,
				'open_world_hint' => false,
				'readonly'        => false,
			),
		),
	) );

	wp_register_ability( 'signal-and-noise/ai-rewrite-in-brand-voice', array(
		'label'               => 'Rewrite in brand voice',
		'description'         => "Transforms external/generic copy into the SN voice register. Intensity controls aggression (light: vocabulary swaps; medium: sentence restructure; full: full rewrite). Preserves links + list structures when flagged. Net-new vs ai/ai's Editorial Notes which only flag grammar/SEO/a11y — this changes voice.",
		'category'            => 'ai-generation',
		'permission_callback' => 'sn_theme_perm_edit_posts',
		'execute_callback'    => 'sn_theme_ability_ai_rewrite_in_brand_voice',
		'input_schema'        => array(
			'type'       => 'object',
			'required'   => array( 'source_text' ),
			'properties' => array(
				'source_text'    => array(
					'type'      => 'string',
					'minLength' => 20,
					'maxLength' => 8000,
					'examples'  => array( 'Welcome to our blog! We post weekly updates about music production tips.' ),
				),
				'preserve_links' => array( 'type' => 'boolean', 'default' => true ),
				'preserve_lists' => array( 'type' => 'boolean', 'default' => true ),
				'intensity'      => array(
					'type'     => 'string',
					'enum'     => array( 'light', 'medium', 'full' ),
					'default'  => 'medium',
					'examples' => array( 'medium', 'full' ),
				),
			),
			'additionalProperties' => false,
		),
		'output_schema'       => array(
			'type'     => 'object',
			'required' => array( 'rewritten_text', 'summary_of_changes' ),
			'properties' => array(
				'rewritten_text'     => array( 'type' => 'string' ),
				'summary_of_changes' => array( 'type' => 'string' ),
				'preserved_elements' => array( 'type' => 'object' ),
				'tokens_used'        => array( 'type' => 'integer' ),
			),
		),
		'meta'                => array(
			'show_in_rest' => true,
			'annotations'  => array(
				'idempotent'      => false,
				'open_world_hint' => false,
				'readonly'        => false,
			),
		),
	) );
}
add_action( 'wp_abilities_api_init', 'sn_theme_register_ai_generation_abilities' );
