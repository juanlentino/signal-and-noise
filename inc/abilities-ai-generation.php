<?php
/**
 * Signal & Noise — Abilities API: AI generation abilities.
 *
 * 5 generative abilities in the 'ai-generation' category:
 *   - signal-and-noise/ai-generate-page-note-summary
 *   - signal-and-noise/ai-suggest-block-pattern
 *   - signal-and-noise/ai-validate-brand-alignment
 *   - signal-and-noise/ai-generate-pattern-content
 *   - signal-and-noise/ai-rewrite-in-brand-voice
 *
 * Extracted from inc/abilities-registration.php by the v9.1.7 split (B-11
 * theme-side, companion to plugin v4.1.3).
 *
 * Cross-file dependencies (these are why the helpers + content + diagnostics
 * files must be required BEFORE this one in the orchestrator):
 *   - SN_THEME_BRAND_VOICE_SYSTEM, SN_THEME_NOTES_VOICE_SYSTEM constants
 *     from inc/abilities-helpers.php
 *   - sn_theme_ai_helper_available(), sn_theme_ai_unavailable_error(),
 *     sn_theme_parse_ai_json() from inc/abilities-helpers.php
 *   - sn_theme_ability_list_block_patterns() from inc/abilities-content.php
 *     (called by ai-suggest-block-pattern + ai-generate-pattern-content)
 *   - sn_theme_ability_design_tokens() from inc/abilities-diagnostics.php
 *     (called by ai-validate-brand-alignment)
 *
 * PHP function resolution is by global name regardless of which file
 * defined it — as long as all files are required before any of the
 * hooks fire, cross-file calls work transparently.
 *
 * @package SignalNoise
 * @since 9.1.7 (content from 9.1.0)
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
		'description'         => "Generates a brand-voiced single-sentence summary of a post in the SN /notes catalog vocabulary. Calls the plugin's AI helper (Sonnet 4.6 pinned via plugin v3.7.2+). Requires signal-and-noise-tools plugin.",
		'category'            => 'ai-generation',
		'permission_callback' => 'sn_theme_perm_edit_posts',
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

/**
 * Execute callback: signal-and-noise/ai-generate-page-note-summary.
 *
 * @since 9.1.0
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
 * Execute callback: signal-and-noise/ai-suggest-block-pattern.
 *
 * @since 9.1.0
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

/**
 * Execute callback: signal-and-noise/ai-validate-brand-alignment.
 *
 * @since 9.1.0
 */
function sn_theme_ability_ai_validate_brand_alignment( $input ) {
	try {
		if ( ! sn_theme_ai_helper_available() ) {
			return sn_theme_ai_unavailable_error();
		}

		$content      = isset( $input['content'] ) ? (string) $input['content'] : '';
		$content_type = isset( $input['content_type'] ) ? (string) $input['content_type'] : 'copy';
		if ( ! in_array( $content_type, array( 'copy', 'title', 'summary', 'longform' ), true ) ) {
			$content_type = 'copy';
		}

		// Include design tokens for palette-fit context.
		$tokens = sn_theme_ability_design_tokens();
		$palette_summary = is_array( $tokens ) && isset( $tokens['colors'] )
			? implode( ',', array_keys( (array) $tokens['colors'] ) )
			: '';

		$system = SN_THEME_BRAND_VOICE_SYSTEM . "\n\nYou MUST return ONLY valid JSON of shape "
			. '{"overall_score": 0-100, "findings": [{"dimension": "voice|tone|vocabulary|palette_fit|structure", "verdict": "aligned|drift|off-brand", "note": "..."}]}'
			. ' No prose, no markdown.';

		$prompt = "Content type: $content_type\n"
			. "Brand palette slugs: $palette_summary\n\n"
			. "CONTENT TO EVALUATE:\n" . $content;

		$raw = snt_ai_generate_with_constraints( $prompt, $system, 1024 );
		if ( is_wp_error( $raw ) ) {
			return $raw;
		}

		$parsed = sn_theme_parse_ai_json( $raw );
		if ( null === $parsed || ! isset( $parsed['overall_score'], $parsed['findings'] ) || ! is_array( $parsed['findings'] ) ) {
			error_log( 'SN ai-validate-brand-alignment: malformed JSON: ' . substr( (string) $raw, 0, 200 ) );
			return new WP_Error(
				'ai_malformed_response',
				'AI returned malformed JSON.',
				array( 'status' => 502 )
			);
		}

		$score = (int) $parsed['overall_score'];
		if ( $score < 0 )   { $score = 0; }
		if ( $score > 100 ) { $score = 100; }

		$allowed_dims     = array( 'voice', 'tone', 'vocabulary', 'palette_fit', 'structure' );
		$allowed_verdicts = array( 'aligned', 'drift', 'off-brand' );

		$findings = array();
		foreach ( (array) $parsed['findings'] as $f ) {
			$dim = isset( $f['dimension'] ) && in_array( $f['dimension'], $allowed_dims, true )
				? (string) $f['dimension']
				: 'voice';
			$verdict = isset( $f['verdict'] ) && in_array( $f['verdict'], $allowed_verdicts, true )
				? (string) $f['verdict']
				: 'drift';
			$findings[] = array(
				'dimension' => $dim,
				'verdict'   => $verdict,
				'note'      => isset( $f['note'] ) ? (string) $f['note'] : '',
			);
		}

		return array(
			'overall_score' => $score,
			'findings'      => $findings,
			'tokens_used'   => (int) ceil( strlen( (string) $raw ) / 4 ),
		);
	} catch ( \Throwable $e ) {
		error_log( 'SN theme ability error in ai-validate-brand-alignment: ' . $e->getMessage() );
		return new WP_Error(
			'theme_ability_error',
			sprintf( 'Theme ability failed: %s', $e->getMessage() ),
			array( 'status' => 500 )
		);
	}
}

/**
 * Execute callback: signal-and-noise/ai-generate-pattern-content.
 *
 * @since 9.1.0
 */
function sn_theme_ability_ai_generate_pattern_content( $input ) {
	try {
		if ( ! sn_theme_ai_helper_available() ) {
			return sn_theme_ai_unavailable_error();
		}

		$pattern_name = isset( $input['pattern_name'] ) ? (string) $input['pattern_name'] : '';
		$topic        = isset( $input['topic'] )        ? (string) $input['topic']        : '';
		$tone         = isset( $input['tone_hint'] )    ? (string) $input['tone_hint']    : 'spec-sheet';
		if ( ! in_array( $tone, array( 'technical', 'narrative', 'manifesto', 'spec-sheet' ), true ) ) {
			$tone = 'spec-sheet';
		}

		if ( '' === $pattern_name || '' === $topic ) {
			return new WP_Error(
				'invalid_input',
				'pattern_name and topic are required.',
				array( 'status' => 400 )
			);
		}

		if ( ! class_exists( 'WP_Block_Patterns_Registry' ) ) {
			return new WP_Error(
				'theme_dependency_missing',
				'WP_Block_Patterns_Registry not available.',
				array( 'status' => 503 )
			);
		}

		$pattern = WP_Block_Patterns_Registry::get_instance()->get_registered( $pattern_name );
		if ( ! $pattern || ! is_array( $pattern ) || ! isset( $pattern['content'] ) ) {
			return new WP_Error(
				'pattern_not_found',
				sprintf( 'Pattern "%s" is not registered.', $pattern_name ),
				array( 'status' => 404 )
			);
		}

		$pattern_template = (string) $pattern['content'];

		$system = SN_THEME_BRAND_VOICE_SYSTEM
			. "\n\nReplace placeholder text in the provided Gutenberg block pattern with brand-voiced copy on the user's topic."
			. " Preserve the block structure exactly. Output ONLY the modified block markup — no preamble, no fences, no explanation."
			. " Tone hint: $tone.";

		$prompt = "TOPIC: $topic\n\nPATTERN TEMPLATE:\n$pattern_template";

		$raw = snt_ai_generate_with_constraints( $prompt, $system, 2048 );
		if ( is_wp_error( $raw ) ) {
			return $raw;
		}

		$markup = trim( (string) $raw );
		// Strip optional markdown fences if the model wraps the markup.
		$markup = trim( preg_replace( '/^```(?:[a-z]+)?\s*|\s*```$/i', '', $markup ) );

		$warnings = array();
		$parsed   = function_exists( 'parse_blocks' ) ? parse_blocks( $markup ) : array();
		if ( empty( $parsed ) ) {
			$warnings[] = 'AI output did not parse as Gutenberg blocks; returned as-is.';
		}

		return array(
			'block_markup' => $markup,
			'pattern_name' => $pattern_name,
			'tokens_used'  => (int) ceil( strlen( $markup ) / 4 ),
			'warnings'     => $warnings,
		);
	} catch ( \Throwable $e ) {
		error_log( 'SN theme ability error in ai-generate-pattern-content: ' . $e->getMessage() );
		return new WP_Error(
			'theme_ability_error',
			sprintf( 'Theme ability failed: %s', $e->getMessage() ),
			array( 'status' => 500 )
		);
	}
}

/**
 * Execute callback: signal-and-noise/ai-rewrite-in-brand-voice.
 *
 * @since 9.1.0
 */
function sn_theme_ability_ai_rewrite_in_brand_voice( $input ) {
	try {
		if ( ! sn_theme_ai_helper_available() ) {
			return sn_theme_ai_unavailable_error();
		}

		$source         = isset( $input['source_text'] )    ? (string) $input['source_text']  : '';
		$preserve_links = isset( $input['preserve_links'] ) ? (bool) $input['preserve_links'] : true;
		$preserve_lists = isset( $input['preserve_lists'] ) ? (bool) $input['preserve_lists'] : true;
		$intensity      = isset( $input['intensity'] )      ? (string) $input['intensity']    : 'medium';
		if ( ! in_array( $intensity, array( 'light', 'medium', 'full' ), true ) ) {
			$intensity = 'medium';
		}

		// Count links + list markers in the source so the response
		// reflects what was present (caller can sanity-check).
		$links_count = preg_match_all( '/https?:\/\/\S+/i', $source );
		if ( false === $links_count ) { $links_count = 0; }
		$lists_count = preg_match_all( '/(^|\n)\s*[\-\*\d+\.]\s+/m', $source );
		if ( false === $lists_count ) { $lists_count = 0; }

		$system = SN_THEME_BRAND_VOICE_SYSTEM
			. "\n\nReturn ONLY valid JSON of shape "
			. '{"rewritten_text": "...", "summary_of_changes": "..."}'
			. ' No prose, no markdown.';

		$intensity_desc = array(
			'light'  => 'Light — swap off-brand vocabulary; keep sentence shapes.',
			'medium' => 'Medium — restructure sentences and swap vocabulary.',
			'full'   => 'Full — rewrite from scratch in brand voice.',
		);

		$prompt = "INTENSITY: $intensity ({$intensity_desc[ $intensity ]})\n"
			. 'PRESERVE LINKS: ' . ( $preserve_links ? 'yes — keep all URLs verbatim.' : 'no — paraphrase is OK.' ) . "\n"
			. 'PRESERVE LISTS: ' . ( $preserve_lists ? 'yes — keep list structures.'   : 'no — prose is fine.'   ) . "\n\n"
			. "SOURCE TEXT:\n" . $source;

		$raw = snt_ai_generate_with_constraints( $prompt, $system, 2048 );
		if ( is_wp_error( $raw ) ) {
			return $raw;
		}

		$parsed = sn_theme_parse_ai_json( $raw );
		if ( null === $parsed || ! isset( $parsed['rewritten_text'] ) ) {
			error_log( 'SN ai-rewrite-in-brand-voice: malformed JSON: ' . substr( (string) $raw, 0, 200 ) );
			return new WP_Error(
				'ai_malformed_response',
				'AI returned malformed JSON.',
				array( 'status' => 502 )
			);
		}

		return array(
			'rewritten_text'     => (string) $parsed['rewritten_text'],
			'summary_of_changes' => isset( $parsed['summary_of_changes'] ) ? (string) $parsed['summary_of_changes'] : '',
			'preserved_elements' => array(
				'links_count' => (int) $links_count,
				'lists_count' => (int) $lists_count,
			),
			'tokens_used'        => (int) ceil( strlen( (string) $raw ) / 4 ),
		);
	} catch ( \Throwable $e ) {
		error_log( 'SN theme ability error in ai-rewrite-in-brand-voice: ' . $e->getMessage() );
		return new WP_Error(
			'theme_ability_error',
			sprintf( 'Theme ability failed: %s', $e->getMessage() ),
			array( 'status' => 500 )
		);
	}
}
