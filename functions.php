<?php
/**
 * Signal & Noise — Theme bootstrap.
 *
 * Loads the modular theme code under inc/. Keep this file small — it should
 * only compose the theme, not implement anything.
 *
 * Module map (load order; regenerated against the actual require list in the
 * v10.49.0 doc sweep — it had drifted to ~33 of the 56 requires):
 *   inc/setup.php                — theme supports, editor styles, shortcodes
 *   inc/editor-block-palette.php — editor block-palette curation
 *   inc/asset-combine.php        — combined+minified stylesheet delivery, fail-open to per-file enqueues (v10.21.6)
 *   inc/assets-frontend.php      — frontend CSS/JS/fonts/favicons + defer filters (critical.css inline; article.css rides the combined cascade since v10.49.0)
 *   inc/frontend-filters.php     — skip link, Spotify oEmbed, generator-tag stripping (named callbacks v10.49.0), social-link URL shim
 *   inc/og-fonts.php             — registers sn_og_font_paths filter (theme brand fonts → plugin's OG generator)
 *   inc/notes-og-card.php        — bespoke 1200x630 /notes-index share card via the plugin's sn_og_image_url seam (v10.39.0, plugin-guarded)
 *   inc/wp-update-integration.php       — registers theme with WP's update transient (version visibility in wp-admin)
 *   inc/wp-update-git-preservation.php  — backs up/restores .git through WP UI installs (v8.5.2+)
 *   inc/template-maintenance.php — FSE template-override purge + sn_purge_all_caches_result/sn_clear_template_overrides_result filter listeners
 *   inc/purge-verify.php         — render-epoch marker + durable per-leg purge report (verified-purge Tier-1, v10.23.0)
 *   inc/purge-verify-cron.php    — deferred WP-Cron route verify for auto-purges (v10.25.0)
 *   inc/notes-reading-time.php   — reading-time helper, REST/MCP-available (extracted v10.42.2)
 *   inc/notes-index-helpers.php  — /notes index pure helpers (extracted from the renderer v10.49.0; loads BEFORE the template router)
 *   inc/page-notes-template.php  — template_include override for /notes route; includes page-notes-render.php
 *   inc/page-index-template.php  — /index whole-site dossier virtual route; loads inc/page-index-render.php (C3, v10.7.0)
 *   inc/cms-page-styles.php      — per-Page bespoke stylesheets: now/uses/accessibility (v10.36.0)
 *   inc/patterns.php             — Block Pattern category registration
 *   inc/blocks-register.php      — custom sidenote + pull-quote + pillar-essays dynamic blocks (v9.11.0, pillar-essays v10.47.0)
 *   inc/block-styles.php         — block style variations
 *   inc/blocks-view-transitions.php — view-transition opt-in for block markup
 *   inc/abilities-registration.php — WP 7.0 Abilities registration (theme-owned read + generative; splits into abilities-*.php)
 *   inc/desktop-mode-copilot-schema.php — keeps ability tool schemas Copilot-legal even plugin-absent (v10.42.3, desktop-mode#362)
 *   inc/post-frontmatter.php     — long-form post frontmatter rendering
 *   inc/pillar-title-eyebrow.php — designation eyebrow on flagged essay Pages (v10.48.0)
 *   inc/block-bindings.php       — signal-noise/post-field Block Bindings source (reading_time|pillar|canonical|og_title) (v9.11.0)
 *   inc/post-updated-date.php    — [sn_updated_date] "Updated YYYY.MM.DD" line for materially-revised notes (v9.10.0)
 *   inc/provenance-surface.php   — [sn_prov_chip] byline pill + [sn_prov_panel] record, plugin-guarded (v10.30.0)
 *   inc/related-notes.php        — related-notes footer block on single notes
 *   inc/cited-by.php             — [sn_cited_by] reverse-link footer (v10.21.0)
 *   inc/404-recovery.php         — helpful 404: search + recent-notes suggestions ([sn_404_suggestions])
 *   inc/post-share.php           — [sn_note_share] copy-permalink + native share row
 *   inc/article-toc.php          — in-article TOC + reading-progress bar (the_content filter, single notes ≥3 H2s)
 *   inc/feed-json.php            — JSON Feed 1.1 for the Notes corpus (v9.11.0; provenance extension v10.48.0)
 *   inc/feed-enrichment.php      — RSS media:content + reading-time + noteUid enrichment, plugin-guarded (v9.11.0)
 *   inc/feed-subscribe-page.php  — /notes/subscribe/, the human page the RSS link points at (v11.9.4)
 *   inc/command-palette.php      — reader-facing Notes-scoped ⌘K/"/" command palette (v9.11.0)
 *   inc/keyboard-nav.php         — single-note j/k prev/next + "?" keyboard cheat-sheet (C5, v10.7.0)
 *   inc/discography-render.php   — [sn_discography] timeline (reads the plugin's sn_discography_entries filter)
 *   inc/music-featured-render.php — [sn_music_featured] hero player (reads sn_music_featured filter, v9.15.0)
 *   inc/beacon.php               — first-party edge analytics beacon enqueue (P1)
 *   inc/identity-rels.php        — <link rel="me"> head links from sn_settings social.same_as (A4, v10.5.0)
 *   inc/humans-txt.php           — /humans.txt virtual route + rel=author autodiscovery + maker's-mark comment (C4, v10.5.0)
 *   inc/security-txt.php         — /.well-known/security.txt virtual route (RFC 9116, v10.13.0)
 *   inc/llms-txt.php             — /llms.txt + /llms-full.txt AEO discoverability virtual routes (v10.19.0; pillar section v10.49.0)
 *   inc/gpc-json.php             — /.well-known/gpc.json Global Privacy Control declaration (v10.19.0)
 *   inc/opensearch.php           — /opensearch.xml OSDD + rel=search autodiscovery over /notes/?s= (v10.19.0)
 *   inc/agents-manifest.php      — /.well-known/agents.json machine-surfaces discovery manifest + <head> alternate link (v10.37.0)
 *   inc/note-uid.php             — canonical lowercase+trim read of the plugin-owned _sn_prov_uid meta (v10.49.0)
 *   inc/content-json-document.php — content-as-data JSON document builder (machine-readability sub-project C, v10.38.0)
 *   inc/content-json.php         — /<url>.json virtual route: every Note/Page reachable as JSON + <head> alternate link (v10.38.0)
 *   inc/disable-smart-quotes.php — straight quotes: disable wptexturize site-wide (v10.13.2)
 *   inc/seo-route-meta.php       — template-driven Page descriptions for the plugin's sn_seo_singular_description filter (v10.13.0)
 *   inc/colophon-meta.php        — [sn_build] live colophon line: theme+plugin version, git short SHA, deploy time (C2, v10.5.0)
 *   inc/availability.php         — [sn_availability] line in the /contact + /services heroes (D5, v10.9.0)
 *   inc/contact-email.php        — [sn_email] scraper-resistant /contact aliases (v10.16.0)
 *   inc/feed-websub.php          — WebSub rel="hub" advertisement in the RSS2 + Atom feeds (D4, v10.9.0)
 *   (inc/page-notes-render.php   — full PHP render of /notes index; loaded by page-notes-template.php, not here)
 *   (inc/page-index-render.php   — full PHP render of /index; loaded by page-index-template.php, not here)
 *   (inc/abilities-*.php          — helpers/categories/content/diagnostics/ai-generation; loaded by abilities-registration.php)
 *
 * Operational tooling — REST surface, Plausible integration, admin UI, security
 * headers, Cloudflare purge, OG card generation, reading-time, content surfaces +
 * migrations — lives in the
 * [signal-and-noise-tools companion plugin](https://github.com/juanlentino/signal-and-noise-tools).
 * See [docs/WORDPRESS-REFERENCE.md](docs/WORDPRESS-REFERENCE.md) §10.0 for the
 * cross-package contract surface (9 theme-listener hooks as of the v11.4.5 sweep).
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
require_once __DIR__ . '/inc/notes-og-card.php'; // v10.39.0: bespoke 1200x630 /notes-index share card via the plugin's sn_og_image_url seam (plugin-guarded)
require_once __DIR__ . '/inc/wp-update-integration.php';
require_once __DIR__ . '/inc/wp-update-git-preservation.php';

require_once __DIR__ . '/inc/template-maintenance.php';
require_once __DIR__ . '/inc/purge-verify.php'; // v10.23.0: render-epoch marker + durable per-leg purge report (verified-purge Tier-1)
require_once __DIR__ . '/inc/purge-verify-cron.php'; // v10.25.0: deferred WP-Cron route verify for auto-purges (folds routes/resolved back into the report)
require_once __DIR__ . '/inc/notes-reading-time.php'; // v10.42.2: reading-time helper, extracted from page-notes-render.php so it is available in REST/MCP (the /notes renderer is template-route-only)
require_once __DIR__ . '/inc/notes-index-helpers.php'; // v10.49.0: the /notes index pure helpers, extracted from page-notes-render.php (which is render-path only now; the SN_NOTES_RENDER_TEST hack is retired). Must load BEFORE the template router below.
require_once __DIR__ . '/inc/notes-index-row.php'; // v11.10.0: /notes index row + year-spine rendering, extracted from page-notes-render.php (needs the helpers above).
require_once __DIR__ . '/inc/page-notes-template.php';
require_once __DIR__ . '/inc/page-index-template.php'; // C3 (v10.7.0): /index whole-site dossier virtual route (loads inc/page-index-render.php)
require_once __DIR__ . '/inc/cms-page-styles.php'; // v10.36.0: per-Page bespoke stylesheets (now.css on /now, uses.css on /about/uses, accessibility.css on /accessibility)
require_once __DIR__ . '/inc/patterns.php';
require_once __DIR__ . '/inc/blocks-register.php';
require_once __DIR__ . '/inc/block-styles.php';
require_once __DIR__ . '/inc/blocks-view-transitions.php';
require_once __DIR__ . '/inc/abilities-registration.php';
require_once __DIR__ . '/inc/desktop-mode-copilot-schema.php'; // v10.42.3: Desktop Mode auto-enrols every read-only ability into the AI Copilot with no opt-out, and one non-conformant tool schema 400s the WHOLE assistant. Must load whenever abilities do — the theme cannot rely on the companion plugin being active to keep its own schemas legal. See WordPress/desktop-mode#362.
require_once __DIR__ . '/inc/post-frontmatter.php';
require_once __DIR__ . '/inc/pillar-title-eyebrow.php'; // v10.48.0: designation eyebrow ("№ 1.01 · Pillar Essay" → /provenance/) on the flagged essay Page's own title
require_once __DIR__ . '/inc/provenance-title-badge.php'; // v11.11.0: the provenance badge joins the BROW on signed Pages (was appended at the content foot by the plugin's the_content filter).
require_once __DIR__ . '/inc/block-bindings.php';
require_once __DIR__ . '/inc/post-updated-date.php';
require_once __DIR__ . '/inc/provenance-surface.php'; // v10.30.0: [sn_prov_chip] byline pill + [sn_prov_panel] record — theme-side placement for the plugin's public provenance rendering (plugin-guarded)
require_once __DIR__ . '/inc/related-notes.php';
require_once __DIR__ . '/inc/cited-by.php'; // v10.21.0: [sn_cited_by] reverse-link footer (complement to deliberately-dead pingbacks)
require_once __DIR__ . '/inc/404-recovery.php';
require_once __DIR__ . '/inc/post-share.php';
require_once __DIR__ . '/inc/article-toc.php';
require_once __DIR__ . '/inc/feed-json.php';
require_once __DIR__ . '/inc/feed-enrichment.php';
require_once __DIR__ . '/inc/feed-subscribe-page.php';
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
require_once __DIR__ . '/inc/agents-manifest.php'; // v10.37.0: /.well-known/agents.json machine-surfaces discovery manifest (machine-readability program, sub-project A)
require_once __DIR__ . '/inc/note-uid.php'; // v10.49.0: canonical lowercase+trim read of the plugin-owned _sn_prov_uid meta (shared by the .json twin + both feeds; closes a trim drift)
require_once __DIR__ . '/inc/content-json-document.php'; // v10.38.0: content-as-data JSON document builder (machine-readability, sub-project C)
require_once __DIR__ . '/inc/content-json.php';          // v10.38.0: /<url>.json virtual route — every Note/Page reachable as JSON (machine-readability, sub-project C)
require_once __DIR__ . '/inc/disable-smart-quotes.php'; // v10.13.2: straight quotes — disable wptexturize site-wide
require_once __DIR__ . '/inc/seo-route-meta.php'; // v10.13.0: template-driven Page descriptions for the plugin's sn_seo_singular_description filter (today /colophon)
require_once __DIR__ . '/inc/colophon-meta.php'; // C2 (v10.5.0): [sn_build] live colophon line (theme+plugin version, git short SHA, deploy time)
require_once __DIR__ . '/inc/availability.php'; // D5 (v10.9.0): [sn_availability] line in the /contact + /services heroes (reads sn_settings identity.availability)
require_once __DIR__ . '/inc/contact-email.php'; // v10.16.0: [sn_email] scraper-resistant /contact aliases (client-side assembly, no plaintext/mailto in source)
require_once __DIR__ . '/inc/feed-websub.php'; // D4 (v10.9.0): WebSub <atom:link rel="hub"> advertisement in the RSS2 + Atom feeds
require_once __DIR__ . '/inc/reading-path-slot.php'; // v11.9.0: [sn_reading_path] block bridge — the plugin's reading-chain nav resolves on single Notes (empty slot when the plugin is absent)
require_once __DIR__ . '/inc/palettes.php'; // v12.0.0: every palette the site can present (root + variations + dark), read from the files that define them
require_once __DIR__ . '/inc/dark-mode.php'; // v11.13.0: dark palette plumbing — pre-paint data-theme stamp, per-scheme theme-color + favicon, [sn_theme_toggle]
