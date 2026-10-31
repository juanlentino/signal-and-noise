<?php
/**
 * Abilities API: the ai-suggest-block-pattern callback.
 *
 * Moved verbatim out of inc/abilities-ai-generation.php, which loads it.
 *
 * @package SignalNoise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
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
