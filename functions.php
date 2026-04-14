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
 *   inc/seo.php                  — meta description, analytics loaders, Breeze excludes
 *   inc/frontend-filters.php     — skip link, oEmbed, generator-tag stripping, output buffer
 *   inc/plausible-api.php        — Plausible Stats API client + admin error notice
 *   inc/admin-assets.php         — admin-only script/style registration + SRI hashes
 *   inc/dashboard-widgets.php    — WP Dashboard widgets (Plausible-backed)
 *   inc/template-maintenance.php — FSE template-override purge + version sync
 *   inc/admin-page.php           — Appearance → Signal & Noise options page
 *   inc/updater.php              — GitHub self-updater + admin notice
 *
 * @package SignalNoise
 * @since 1.0.0
 * @version 5.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/inc/setup.php';
require_once __DIR__ . '/inc/assets-frontend.php';
require_once __DIR__ . '/inc/seo.php';
require_once __DIR__ . '/inc/frontend-filters.php';

require_once __DIR__ . '/inc/plausible-api.php';

require_once __DIR__ . '/inc/admin-assets.php';
require_once __DIR__ . '/inc/dashboard-widgets.php';
require_once __DIR__ . '/inc/template-maintenance.php';
require_once __DIR__ . '/inc/admin-page.php';
require_once __DIR__ . '/inc/updater.php';
