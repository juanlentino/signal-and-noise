<?php
/**
 * Abilities API: the ai-generate-pattern-content callback.
 *
 * Moved verbatim out of inc/abilities-ai-generation.php, which loads it.
 *
 * @package SignalNoise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
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
