<?php
/**
 * Abilities API: the ai-validate-brand-alignment callback.
 *
 * Moved verbatim out of inc/abilities-ai-generation.php, which loads it.
 *
 * @package SignalNoise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
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
