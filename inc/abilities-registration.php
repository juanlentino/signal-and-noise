<?php
/**
 * Signal & Noise — Abilities API orchestrator (v9.1.7 B-11 split).
 *
 * Loads the per-feature ability registration files. Was a 1814-line
 * monolith before the v9.1.7 split (B-11 theme-side companion to plugin
 * v4.1.3) — now a thin loader that requires the 5 feature-scoped files
 * below.
 *
 * Architecture:
 *   - inc/abilities-helpers.php          — Shared constants
 *     (SN_THEME_BRAND_VOICE_SYSTEM, SN_THEME_NOTES_VOICE_SYSTEM), AI
 *     helpers (sn_theme_ai_helper_available, sn_theme_ai_unavailable_error,
 *     sn_theme_parse_ai_json), pillar descriptors, and 2 named permission
 *     callables (sn_theme_perm_read, sn_theme_perm_edit_posts).
 *   - inc/abilities-categories.php       — 3 category registrations on
 *     `wp_abilities_api_categories_init` (idempotent vs. plugin).
 *   - inc/abilities-diagnostics.php      — 4 abilities: get-active-template-
 *     structure, get-theme-version, get-design-system-summary, get-design-tokens.
 *   - inc/abilities-content.php          — 3 abilities: list-block-patterns,
 *     get-page-notes-pillars, get-reading-time-for-slug.
 *   - inc/abilities-ai-generation.php    — 5 abilities: ai-generate-page-note-summary,
 *     ai-suggest-block-pattern, ai-validate-brand-alignment,
 *     ai-generate-pattern-content, ai-rewrite-in-brand-voice.
 *
 * Total: 12 abilities + 3 categories. Each feature file owns its
 * `add_action( 'wp_abilities_api_init', ... )` registration block plus
 * the impl wrappers.
 *
 * Bootstrap require at functions.php:52 — unchanged, drop-in. Helpers
 * file must be required FIRST because every other file references its
 * constants + perm callables at registration-call time (and impl-call
 * time later). Order between the 3 feature files doesn't matter
 * (WordPress queues `add_action` callbacks regardless of registration
 * order), but cross-file impl calls — e.g., ai-validate-brand-alignment
 * calling sn_theme_ability_design_tokens() from diagnostics — work
 * regardless of file order because all files are required before any
 * hook fires.
 *
 * @package SignalNoise
 * @since 9.1.0 (split in 9.1.7)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/abilities-helpers.php';
require_once __DIR__ . '/abilities-categories.php';
require_once __DIR__ . '/abilities-diagnostics.php';
require_once __DIR__ . '/abilities-content.php';
require_once __DIR__ . '/abilities-ai-generation.php';

/**
 * Back-compat shim — the v9.1.7 split refactored the monolithic
 * sn_theme_register_abilities() into 3 per-category registration
 * functions. This shim preserves the single-call entry point used by
 * tests/abilities-{integration,registration}.php (which weren't updated
 * during the split, so they've been failing at the worktree baseline
 * since v9.1.7).
 *
 * Calls the 3 abilities-registration functions. Does NOT call
 * sn_theme_register_ability_categories() because tests already call
 * that separately.
 *
 * In production the 3 underlying functions are invoked by add_action
 * hooks in their respective feature files. This shim is for tests only.
 *
 * @since 9.2.1
 */
function sn_theme_register_abilities() {
	sn_theme_register_diagnostics_abilities();
	sn_theme_register_content_abilities();
	sn_theme_register_ai_generation_abilities();
}
