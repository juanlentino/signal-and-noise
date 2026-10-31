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

// The parts, in the original top-to-bottom order.
require_once __DIR__ . '/abilities-ai-generation-register.php';
require_once __DIR__ . '/abilities-ai-generation-page-note-summary.php';
require_once __DIR__ . '/abilities-ai-generation-suggest-block-pattern.php';
require_once __DIR__ . '/abilities-ai-generation-validate-brand-alignment.php';
require_once __DIR__ . '/abilities-ai-generation-generate-pattern-content.php';
require_once __DIR__ . '/abilities-ai-generation-rewrite-in-brand-voice.php';
