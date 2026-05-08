<?php
/**
 * Signal & Noise — Theme bootstrap.
 *
 * Loads the modular theme code under inc/. Keep this file small — it should
 * only compose the theme, not implement anything.
 *
 * Module map:
 *   inc/setup.php                — editor styles, shortcodes
 *   inc/assets-frontend.php      — frontend CSS/JS/fonts/favicons + defer filters
 *   inc/seo.php                  — meta description + Breeze excludes
 *   inc/frontend-filters.php     — skip link, oEmbed, generator-tag stripping, output buffer
 *   inc/security-headers.php     — security headers + WP hardening (XML-RPC, REST users, ?author=N)
 *   inc/notes-and-provenance.php — Notes content surface + Provenance pillar page
 *   inc/reading-time.php         — Cached reading-time calc + [sn_reading_time] + cleanup
 *   inc/og-image.php             — Per-post OG/Twitter card generator (GD, Bebas + DM Mono)
 *   inc/cloudflare-purge.php     — Auto-purge CF edge cache on post save / theme update
 *   inc/template-maintenance.php — FSE template-override purge + version sync
 *   inc/template-self-heal.php   — Detect file-on-disk drift vs GitHub main + auto-fix
 *   inc/admin-page.php           — Appearance → Signal & Noise options page
 *   inc/admin-bar.php            — Top-bar quick-action dropdown (purge, etc.)
 *   inc/plausible-api.php        — Plausible Stats API client + cache layers
 *   inc/plausible-widget.php     — Dashboard widget set (snapshot/realtime/pages/sources)
 *   inc/plausible-admin.php      — S&N Settings → Plausible tab (Stats API key storage)
 *   inc/updater.php              — GitHub self-updater + admin notice
 *
 * @package SignalNoise
 * @since 1.0.0
 * @version 7.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/inc/setup.php';
require_once __DIR__ . '/inc/assets-frontend.php';
require_once __DIR__ . '/inc/seo.php';
require_once __DIR__ . '/inc/frontend-filters.php';
require_once __DIR__ . '/inc/security-headers.php';
require_once __DIR__ . '/inc/notes-and-provenance.php';
require_once __DIR__ . '/inc/reading-time.php';
require_once __DIR__ . '/inc/og-image.php';
require_once __DIR__ . '/inc/cloudflare-purge.php';

require_once __DIR__ . '/inc/template-maintenance.php';
require_once __DIR__ . '/inc/template-self-heal.php';
require_once __DIR__ . '/inc/page-notes-template.php';
require_once __DIR__ . '/inc/admin-page.php';
require_once __DIR__ . '/inc/admin-bar.php';
require_once __DIR__ . '/inc/plausible-api.php';
require_once __DIR__ . '/inc/plausible-widget.php';
require_once __DIR__ . '/inc/plausible-admin.php';
require_once __DIR__ . '/inc/updater.php';
