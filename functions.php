<?php
/**
 * Signal & Noise — Theme bootstrap.
 *
 * Loads the modular theme code under inc/. Keep this file small — it should
 * only compose the theme, not implement anything.
 *
 * Module map (load order; updated v9.7.0):
 *   inc/setup.php                — editor styles, shortcodes
 *   inc/assets-frontend.php      — frontend CSS/JS/fonts/favicons + defer filters
 *   inc/frontend-filters.php     — skip link, oEmbed, generator-tag stripping, output buffer
 *   inc/og-fonts.php             — registers sn_og_font_paths filter (theme brand fonts → plugin's OG generator)
 *   inc/wp-update-integration.php       — registers theme with WP's update transient (version visibility in wp-admin)
 *   inc/wp-update-git-preservation.php  — backs up/restores .git through WP UI installs (v8.5.2+)
 *   inc/template-maintenance.php — FSE template-override purge + sn_purge_all_caches_result/sn_clear_template_overrides_result filter listeners
 *   inc/page-notes-template.php  — template_include override for /notes route; includes page-notes-render.php
 *   inc/patterns.php             — Block Pattern category registration
 *   inc/blocks-register.php      — custom sidenote + pull-quote dynamic blocks (v9.11.0)
 *   inc/blocks-view-transitions.php — view-transition opt-in for block markup
 *   inc/abilities-registration.php — 13 WP 7.0 Abilities (theme-owned: 8 read + 5 generative; v9.1.1, get-latest-theme-tag added v9.9.0)
 *   inc/post-frontmatter.php     — long-form post frontmatter rendering
 *   inc/block-bindings.php       — signal-noise/post-field Block Bindings source (reading_time|pillar|canonical|og_title) (v9.11.0)
 *   inc/post-updated-date.php    — [sn_updated_date] "Updated YYYY.MM.DD" line for materially-revised notes (v9.10.0)
 *   inc/feed-json.php            — JSON Feed 1.1 for the Notes corpus (v9.11.0)
 *   inc/feed-enrichment.php      — RSS media:content + reading-time enrichment, plugin-guarded (v9.11.0)
 *   inc/command-palette.php      — reader-facing Notes-scoped ⌘K/"/" command palette (v9.11.0)
 *   (inc/page-notes-render.php   — full PHP render of /notes index; loaded by page-notes-template.php, not here)
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
 *
 * The canonical version is the `Version:` header in style.css. Removed the
 * `@version` line here in v8.5.3 audit pass — it drifted to 8.4.0 because
 * keeping two version sources in sync is exactly the bug pattern this
 * project tries to avoid (gotcha #1 in the WP-REFERENCE doc, applied
 * recursively to ourselves).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/inc/setup.php';
require_once __DIR__ . '/inc/editor-block-palette.php';
require_once __DIR__ . '/inc/assets-frontend.php';
require_once __DIR__ . '/inc/frontend-filters.php';
require_once __DIR__ . '/inc/og-fonts.php';
require_once __DIR__ . '/inc/wp-update-integration.php';
require_once __DIR__ . '/inc/wp-update-git-preservation.php';

require_once __DIR__ . '/inc/template-maintenance.php';
require_once __DIR__ . '/inc/page-notes-template.php';
require_once __DIR__ . '/inc/patterns.php';
require_once __DIR__ . '/inc/blocks-register.php';
require_once __DIR__ . '/inc/block-styles.php';
require_once __DIR__ . '/inc/blocks-view-transitions.php';
require_once __DIR__ . '/inc/abilities-registration.php';
require_once __DIR__ . '/inc/post-frontmatter.php';
require_once __DIR__ . '/inc/block-bindings.php';
require_once __DIR__ . '/inc/post-updated-date.php';
require_once __DIR__ . '/inc/related-notes.php';
require_once __DIR__ . '/inc/post-share.php';
require_once __DIR__ . '/inc/feed-json.php';
require_once __DIR__ . '/inc/feed-enrichment.php';
require_once __DIR__ . '/inc/command-palette.php';
require_once __DIR__ . '/inc/discography-render.php';
require_once __DIR__ . '/inc/music-featured-render.php'; // v9.15.0: [sn_music_featured] hero player (reads sn_music_featured filter)
