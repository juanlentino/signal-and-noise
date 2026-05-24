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
}
add_action( 'wp_abilities_api_init', 'sn_theme_register_abilities' );
