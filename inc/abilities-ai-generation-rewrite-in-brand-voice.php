<?php
/**
 * Abilities API: the ai-rewrite-in-brand-voice callback.
 *
 * Moved verbatim out of inc/abilities-ai-generation.php, which loads it.
 *
 * @package SignalNoise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
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
		// #315: a bullet (-, *, +) or an ordered marker (digits + dot). The
		// previous single character class matched one character, so "1." never
		// counted and a lone digit did.
		$lists_count = preg_match_all( '/(^|\n)\s*(?:[-*+]|\d+\.)\s+/', $source );
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
