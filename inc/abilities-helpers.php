<?php
/**
 * Signal & Noise — Abilities API shared helpers + constants.
 *
 * Pulled out of inc/abilities-registration.php by the v9.1.7 split (B-11
 * theme-side companion to plugin v4.1.3). Holds:
 *
 *   - Brand-voice system instructions (SN_THEME_BRAND_VOICE_SYSTEM +
 *     SN_THEME_NOTES_VOICE_SYSTEM) — referenced by every AI ability so a
 *     voice tweak lands in one place.
 *   - sn_theme_ai_helper_available() / sn_theme_ai_unavailable_error() —
 *     the function_exists guard for the plugin AI helper, centralized so
 *     all 5 generative abilities branch identically.
 *   - sn_theme_parse_ai_json() — strips optional markdown fences then
 *     json_decodes; safe to call on any model output.
 *   - sn_theme_pillar_descriptors() — canonical pillar list (consumed by
 *     abilities-content.php for get-page-notes-pillars).
 *   - sn_theme_perm_read() / sn_theme_perm_edit_posts() — named permission
 *     callables replacing the closure pattern. Lets every split file
 *     reference 'sn_theme_perm_read' as a string callable in
 *     permission_callback (the original file used local $permission_*
 *     closures inside the registration function, which doesn't survive
 *     the split into multiple registration functions).
 *
 * @package SignalNoise
 * @since 9.1.7 (content from 9.1.0)
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
 * Returns the canonical SN /notes pillar essay descriptors.
 *
 * Hardcoded here (not derived from a DB query) because the pillars
 * are intentionally curated, not editorial — they frame the /notes
 * catalog and are mirrored in inc/page-notes-render.php HTML.
 * Mirroring them in PHP rather than parsing the HTML keeps both
 * surfaces editable and authoritative.
 *
 * Consumed by sn_theme_ability_page_notes_pillars() in
 * inc/abilities-content.php after the v9.1.7 split.
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
 * Named permission callable: `read` capability.
 *
 * Used by all 7 read abilities. Replaces the `$permission_read` closure
 * from inc/abilities-registration.php pre-v9.1.7. The `read` cap is held
 * by every registered WP user (subscribers up); anonymous visitors are
 * rejected.
 *
 * @since 9.1.7
 * @return bool
 */
function sn_theme_perm_read() {
	return current_user_can( 'read' );
}

/**
 * Named permission callable: `edit_posts` capability.
 *
 * Used by all 5 generative abilities. Replaces the `$permission_edit_posts`
 * closure from inc/abilities-registration.php pre-v9.1.7. `edit_posts` is
 * the contributor-and-up cap — appropriate for AI-generative endpoints
 * since they can produce content destined for posts.
 *
 * @since 9.1.7
 * @return bool
 */
function sn_theme_perm_edit_posts() {
	return current_user_can( 'edit_posts' );
}
