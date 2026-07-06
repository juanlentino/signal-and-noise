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
 *   inc/article-toc.php          — in-article TOC + reading-progress bar (the_content filter, single notes ≥3 H2s)
 *   inc/feed-json.php            — JSON Feed 1.1 for the Notes corpus (v9.11.0)
 *   inc/feed-enrichment.php      — RSS media:content + reading-time enrichment, plugin-guarded (v9.11.0)
 *   inc/command-palette.php      — reader-facing Notes-scoped ⌘K/"/" command palette (v9.11.0)
 *   inc/keyboard-nav.php         — single-note j/k prev/next + "?" keyboard cheat-sheet (C5, v10.7.0)
 *   inc/404-recovery.php         — helpful 404: search + recent-notes suggestions ([sn_404_suggestions])
 *   inc/identity-rels.php        — <link rel="me"> head links from sn_settings social.same_as (A4, v10.5.0)
 *   inc/humans-txt.php           — /humans.txt virtual route + rel=author autodiscovery + maker's-mark comment (C4, v10.5.0)
 *   inc/llms-txt.php             — /llms.txt + /llms-full.txt AEO discoverability virtual routes (v10.19.0)
 *   inc/gpc-json.php             — /.well-known/gpc.json Global Privacy Control declaration (v10.19.0)
 *   inc/opensearch.php           — /opensearch.xml OSDD + rel=search autodiscovery over /notes/?s= (v10.19.0)
 *   inc/colophon-meta.php        — [sn_build] live colophon line: theme+plugin version, git short SHA, deploy time (C2, v10.5.0)
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
require_once __DIR__ . '/inc/asset-combine.php'; // v10.21.6: combined+minified stylesheet delivery (fail-open to per-file enqueues)
require_once __DIR__ . '/inc/assets-frontend.php';
require_once __DIR__ . '/inc/frontend-filters.php';
require_once __DIR__ . '/inc/og-fonts.php';
require_once __DIR__ . '/inc/wp-update-integration.php';
require_once __DIR__ . '/inc/wp-update-git-preservation.php';

require_once __DIR__ . '/inc/template-maintenance.php';
require_once __DIR__ . '/inc/purge-verify.php'; // v10.23.0: render-epoch marker + durable per-leg purge report (verified-purge Tier-1)
require_once __DIR__ . '/inc/purge-verify-cron.php'; // v10.25.0: deferred WP-Cron route verify for auto-purges (folds routes/resolved back into the report)
require_once __DIR__ . '/inc/page-notes-template.php';
require_once __DIR__ . '/inc/page-index-template.php'; // C3 (v10.7.0): /index whole-site dossier virtual route (loads inc/page-index-render.php)
require_once __DIR__ . '/inc/uses-data.php'; // D6 (v10.10.0): /uses gear data + sn_uses_groups() filter seam (edit surface)
require_once __DIR__ . '/inc/page-uses-template.php'; // D6 (v10.10.0): /uses gear page virtual route (loads inc/page-uses-render.php)
require_once __DIR__ . '/inc/page-personal-template.php'; // v10.12.0: /contact/personal virtual route — child of /contact (loads inc/page-personal-render.php)
require_once __DIR__ . '/inc/patterns.php';
require_once __DIR__ . '/inc/blocks-register.php';
require_once __DIR__ . '/inc/block-styles.php';
require_once __DIR__ . '/inc/blocks-view-transitions.php';
require_once __DIR__ . '/inc/abilities-registration.php';
require_once __DIR__ . '/inc/post-frontmatter.php';
require_once __DIR__ . '/inc/block-bindings.php';
require_once __DIR__ . '/inc/post-updated-date.php';
require_once __DIR__ . '/inc/related-notes.php';
require_once __DIR__ . '/inc/cited-by.php'; // v10.21.0: [sn_cited_by] reverse-link footer (complement to deliberately-dead pingbacks)
require_once __DIR__ . '/inc/404-recovery.php';
require_once __DIR__ . '/inc/post-share.php';
require_once __DIR__ . '/inc/article-toc.php';
require_once __DIR__ . '/inc/feed-json.php';
require_once __DIR__ . '/inc/feed-enrichment.php';
require_once __DIR__ . '/inc/command-palette.php';
require_once __DIR__ . '/inc/keyboard-nav.php'; // C5 (v10.7.0): single-note j/k prev/next + "?" keyboard cheat-sheet
require_once __DIR__ . '/inc/discography-render.php';
require_once __DIR__ . '/inc/music-featured-render.php'; // v9.15.0: [sn_music_featured] hero player (reads sn_music_featured filter)
require_once __DIR__ . '/inc/beacon.php'; // P1: first-party edge analytics beacon enqueue
require_once __DIR__ . '/inc/identity-rels.php'; // A4 (v10.5.0): <link rel="me"> head links from sn_settings social.same_as
require_once __DIR__ . '/inc/humans-txt.php'; // C4 (v10.5.0): /humans.txt virtual route + rel=author autodiscovery + maker's-mark comment
require_once __DIR__ . '/inc/security-txt.php'; // v10.13.0: /.well-known/security.txt (RFC 9116) virtual route
require_once __DIR__ . '/inc/llms-txt.php'; // v10.19.0: /llms.txt + /llms-full.txt virtual routes (llmstxt.org AEO discoverability)
require_once __DIR__ . '/inc/gpc-json.php'; // v10.19.0: /.well-known/gpc.json virtual route (Global Privacy Control declaration)
require_once __DIR__ . '/inc/opensearch.php'; // v10.19.0: /opensearch.xml virtual route + rel=search autodiscovery (search provider over /notes/?s=)
require_once __DIR__ . '/inc/disable-smart-quotes.php'; // v10.13.2: straight quotes — disable wptexturize site-wide
require_once __DIR__ . '/inc/seo-route-meta.php'; // v10.13.0: route descriptions + /about/uses meta for the plugin's sn_seo_* filters
require_once __DIR__ . '/inc/now-data.php'; // v10.21.0: /now page data (owner edit surface + sn_now_sections filter seam)
require_once __DIR__ . '/inc/page-now-template.php'; // v10.21.0: /now virtual route (indie-web now page)
require_once __DIR__ . '/inc/page-accessibility-template.php'; // v10.21.0: /accessibility statement virtual route
require_once __DIR__ . '/inc/colophon-meta.php'; // C2 (v10.5.0): [sn_build] live colophon line (theme+plugin version, git short SHA, deploy time)
require_once __DIR__ . '/inc/availability.php'; // D5 (v10.9.0): [sn_availability] line in the /contact + /services heroes (reads sn_settings identity.availability)
require_once __DIR__ . '/inc/contact-email.php'; // v10.16.0: [sn_email] scraper-resistant /contact aliases (client-side assembly, no plaintext/mailto in source)
require_once __DIR__ . '/inc/feed-websub.php'; // D4 (v10.9.0): WebSub <atom:link rel="hub"> advertisement in the RSS2 + Atom feeds
