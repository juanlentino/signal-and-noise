<?php
/**
 * Signal & Noise — Abilities API: diagnostics abilities.
 *
 * 7 read abilities in the 'diagnostics' category:
 *   - signal-and-noise/get-active-template-structure
 *   - signal-and-noise/get-theme-version
 *   - signal-and-noise/get-design-system-summary
 *   - signal-and-noise/get-design-tokens
 *   - signal-and-noise/get-latest-theme-tag (added v9.9.0)
 *   - signal-and-noise/get-seo-route-meta (added v10.29.0)
 *   - signal-and-noise/get-llms-txt (added v10.29.0)
 *
 * Extracted from inc/abilities-registration.php by the v9.1.7 split (B-11
 * theme-side, companion to plugin v4.1.3). The first 4 impl functions are
 * co-located with their registrations; get-latest-theme-tag (v9.9.0)
 * delegates to sn_gh_latest_theme_tag() in inc/wp-update-integration.php.
 *
 * Cross-file note: sn_theme_ability_design_system_summary() internally
 * calls sn_theme_ability_design_tokens() (also in this file — same-file
 * call). No other diagnostics ability has external dependencies.
 *
 * v10.42.x: sn_theme_ability_design_tokens() unwraps wp_get_global_settings()'s
 * per-origin ('default'/'theme'/'custom') preset buckets into flat entries
 * (see sn_theme_flatten_preset_origins()) and refuses to return a hollow
 * token set (see sn_theme_design_tokens_has_content()) — both the reader
 * and the summary formatter surface a design_tokens_empty WP_Error instead
 * of a plausible-looking empty document.
 *
 * @package SignalNoise
 * @since 9.1.7 (content from 9.1.0)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// The parts, in the original top-to-bottom order.
require_once __DIR__ . '/abilities-diagnostics-register.php';
require_once __DIR__ . '/abilities-diagnostics-design-tokens-helpers.php';
require_once __DIR__ . '/abilities-diagnostics-design-tokens.php';
require_once __DIR__ . '/abilities-diagnostics-template-structure.php';
require_once __DIR__ . '/abilities-diagnostics-theme-version.php';
require_once __DIR__ . '/abilities-diagnostics-design-summary.php';
require_once __DIR__ . '/abilities-diagnostics-latest-theme-tag.php';
require_once __DIR__ . '/abilities-diagnostics-seo-llms.php';
