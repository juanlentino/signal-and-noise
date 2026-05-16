<?php
/**
 * Signal & Noise — Theme bootstrap.
 *
 * Loads the modular theme code under inc/. Keep this file small — it should
 * only compose the theme, not implement anything.
 *
 * Module map (since v8.2.0 — the Phase 1 split):
 *   inc/setup.php                — editor styles, shortcodes
 *   inc/assets-frontend.php      — frontend CSS/JS/fonts/favicons + defer filters
 *   inc/frontend-filters.php     — skip link, oEmbed, generator-tag stripping, output buffer
 *   inc/notes-and-provenance.php — Notes content surface + Provenance pillar page
 *   inc/reading-time.php         — Cached reading-time calc + [sn_reading_time] + cleanup
 *   inc/og-image.php             — Per-post OG/Twitter card generator (GD, Bebas + DM Mono)
 *   inc/template-maintenance.php — FSE template-override purge + version sync (also hosts sn_purge_all_caches_result + sn_clear_template_overrides_result filter listeners for the companion plugin)
 *   inc/template-self-heal.php   — Detect file-on-disk drift vs GitHub main + auto-fix (also hosts sn_self_heal_force_run_result filter listener)
 *   inc/page-notes-template.php  — template_include override for /notes route
 *   inc/patterns.php             — Block Pattern category registration (patterns/ dir auto-discovers)
 *   inc/updater.php              — GitHub self-updater + admin notice (also hosts sn_updater_branch / sn_updater_revcount / sn_updater_force_check / sn_updater_clear_error contract listeners)
 *
 * Operational tooling (REST surface, Plausible integration, admin UI, security
 * headers, Cloudflare purge) lives in the
 * [signal-and-noise-tools companion plugin](https://github.com/juanlentino/signal-and-noise-tools)
 * since v8.2.0. See [docs/WORDPRESS-REFERENCE.md](docs/WORDPRESS-REFERENCE.md)
 * §10.0 for the full contract surface between the two packages.
 *
 * @package SignalNoise
 * @since 1.0.0
 * @version 8.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/inc/setup.php';
require_once __DIR__ . '/inc/assets-frontend.php';
require_once __DIR__ . '/inc/frontend-filters.php';
require_once __DIR__ . '/inc/notes-and-provenance.php';
require_once __DIR__ . '/inc/reading-time.php';
require_once __DIR__ . '/inc/og-image.php';

require_once __DIR__ . '/inc/template-maintenance.php';
require_once __DIR__ . '/inc/template-self-heal.php';
require_once __DIR__ . '/inc/page-notes-template.php';
require_once __DIR__ . '/inc/patterns.php';
require_once __DIR__ . '/inc/updater.php';
