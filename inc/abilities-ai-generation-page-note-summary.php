<?php
/**
 * Abilities API: the ai-page-note-summary callback.
 *
 * Moved verbatim out of inc/abilities-ai-generation.php, which loads it.
 *
 * @package SignalNoise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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
