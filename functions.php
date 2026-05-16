<?php
/**
 * Signal & Noise — Theme bootstrap.
 *
 * Loads the modular theme code under inc/. Keep this file small — it should
 * only compose the theme, not implement anything.
 *
 * Module map (post-Phase 3, v8.4.0):
 *   inc/setup.php                — editor styles, shortcodes
 *   inc/assets-frontend.php      — frontend CSS/JS/fonts/favicons + defer filters
 *   inc/frontend-filters.php     — skip link, oEmbed, generator-tag stripping, output buffer
 *   inc/og-fonts.php             — registers sn_og_font_paths filter (theme brand fonts → plugin's OG generator)
 *   inc/template-maintenance.php — FSE template-override purge + sn_purge_all_caches_result/sn_clear_template_overrides_result filter listeners
 *   inc/page-notes-template.php  — template_include override for /notes route (theme-specific defense)
 *   inc/page-notes-render.php    — full PHP render of /notes index (theme-specific aesthetic)
 *   inc/patterns.php             — Block Pattern category registration
 *
 * Operational tooling — REST surface, Plausible integration, admin UI, security
 * headers, Cloudflare purge, OG card generation, reading-time, content surfaces +
 * migrations — lives in the
 * [signal-and-noise-tools companion plugin](https://github.com/juanlentino/signal-and-noise-tools).
 * See [docs/WORDPRESS-REFERENCE.md](docs/WORDPRESS-REFERENCE.md) §10.0 for the
 * cross-package contract surface (3 hooks since v8.4.0).
 *
 * @package SignalNoise
 * @since 1.0.0
 * @version 8.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/inc/setup.php';
require_once __DIR__ . '/inc/assets-frontend.php';
require_once __DIR__ . '/inc/frontend-filters.php';
require_once __DIR__ . '/inc/og-fonts.php';
require_once __DIR__ . '/inc/wp-update-integration.php';

require_once __DIR__ . '/inc/template-maintenance.php';
require_once __DIR__ . '/inc/page-notes-template.php';
require_once __DIR__ . '/inc/patterns.php';
