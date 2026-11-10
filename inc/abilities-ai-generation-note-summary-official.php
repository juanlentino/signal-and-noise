<?php
/**
 * Abilities API: ai-generate-page-note-summary on the official AI plugin.
 *
 * When the AI plugin registers ai/summarization, the theme ability runs it and
 * attaches the /notes voice through the official system-instruction filter.
 * Loaded by inc/abilities-ai-generation.php.
 *
 * @package SignalNoise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The /notes voice and word cap for the one ai/summarization call the alias
 * makes. Set to a cap to attach, 0 to detach; null reads.
 *
 * @param int|null $set Word cap, 0, or null.
 * @return int
 */
function sn_theme_ai_note_summary_voice_cap( $set = null ) {
	static $cap = 0;
	if ( null !== $set ) {
		$cap = (int) $set;
	}
	return $cap;
}

/**
 * Appends the /notes voice to the official summarization instruction while
 * the alias runs. Appended, not substituted: the official text keeps its own
 * rules. Other callers of ai/summarization are left alone.
 *
 * @param string $instruction The official system instruction.
 * @return string
 */
function sn_theme_ai_note_summary_voice( $instruction ) {
	$cap = sn_theme_ai_note_summary_voice_cap();
	if ( $cap < 1 ) {
		return $instruction;
	}
	return rtrim( (string) $instruction ) . "\n\nHouse rules for this site, which take precedence on wording and length:\n"
		. SN_THEME_NOTES_VOICE_SYSTEM . "\nHard limit: $cap words, one sentence.";
}
add_filter( 'wpai_summarization_system_instruction', 'sn_theme_ai_note_summary_voice' );

/**
 * Runs the summary through the official ai/summarization ability when the AI
 * plugin registers it. Returns null when it does not, so the caller keeps the
 * theme's own helper path. An error from the official ability is returned as
 * is: no second, silent generation.
 *
 * @param array $input The theme ability's input.
 * @return array|WP_Error|null
 */
function sn_theme_ai_official_note_summary( $input ) {
	// By name: the AI plugin is optional and registers this ability only when
	// its Summarization feature is on.
	$ability = function_exists( 'wp_get_ability' ) ? wp_get_ability( 'ai/summarization' ) : null;
	if ( ! $ability ) {
		return null;
	}

	$post_id   = isset( $input['post_id'] ) ? (int) $input['post_id'] : 0;
	$max_words = max( 10, min( 60, isset( $input['max_words'] ) ? (int) $input['max_words'] : 30 ) );
	if ( ! get_post( $post_id ) ) {
		return new WP_Error( 'post_not_found', sprintf( 'Post %d not found.', $post_id ), array( 'status' => 404 ) );
	}

	sn_theme_ai_note_summary_voice_cap( $max_words );
	try {
		$raw = $ability->execute(
			array(
				'context' => (string) $post_id,
				// The official enum is coarser than max_words: short is one
				// paragraph of up to 50 words. The word cap rides in the voice.
				'length'  => $max_words > 50 ? 'medium' : 'short',
			)
		);
	} finally {
		sn_theme_ai_note_summary_voice_cap( 0 );
	}
	if ( is_wp_error( $raw ) ) {
		return $raw;
	}

	$summary = trim( trim( (string) $raw ), "\"'" );
	return array(
		'summary'     => $summary,
		'post_id'     => $post_id,
		'tokens_used' => (int) ceil( strlen( $summary ) / 4 ),
	);
}
