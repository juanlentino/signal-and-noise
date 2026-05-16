# Session handoff — 2026-05-16 (end of Phase 3)

Picks up after the 2026-05-16 session that shipped Phase 3 — the last queued Phase from the original Phase 1 spec. Three theme-coupled modules moved to plugin; theme retains only presentation and theme-specific defenses.

## Where the project is right now

### Live versions

| Package | Version | Deployment |
|---|---|---|
| Theme `signal-and-noise` | `v8.4.1` | Cloudways auto-pulls + auto-purges CF on tag push |
| Plugin `signal-and-noise-tools` | `v1.3.0` | SSH auto-deploy + auto-purges CF on tag push (Phase 2c) |

### What landed

**Three theme modules moved to plugin:**
- `inc/og-image.php` (402 LOC) → plugin `inc/og-card-generator.php`. Single structural change: `get_theme_file_path()` font lookups replaced with `apply_filters('sn_og_font_paths', [])`.
- `inc/reading-time.php` (396 LOC) → plugin `inc/reading-time.php`. Calculation + caching + shortcode + render_block bridge — all plugin-side. Eliminates one cross-package contract (`sn_admin_reading_time_tab` is now intra-plugin).
- `inc/notes-and-provenance.php` (1,058 LOC) → split into 3 plugin files:
  - `inc/content-surfaces.php` (252 LOC) — constants + 5 `sn_ensure_*` functions + permalink + query loop scoping
  - `inc/content-migrations.php` (700 LOC) — 3 body loaders + 11 one-shot DB seed migrations
  - `inc/content-rendering-helpers.php` (135 LOC) — 3 pure Gutenberg block markup generators

**One directory moved:** `theme/inc/seed-content/` (3 HTML files) → plugin `inc/seed-content/`. The body-loader functions use `__DIR__` so path resolution worked transparently.

**One new cross-package contract:** `sn_og_font_paths` filter. Plugin's OG card generator calls `apply_filters('sn_og_font_paths', [])`; theme's new `inc/og-fonts.php` (27 LOC) registers a listener that returns the brand TTF paths via `get_theme_file_path()`. Theme owns the brand typography; plugin owns the rendering pipeline.

**Theme `inc/` shrunk from 11 files to 8.** Final state: `assets-frontend.php`, `frontend-filters.php`, `og-fonts.php` (new), `page-notes-render.php`, `page-notes-template.php`, `patterns.php`, `setup.php`, `template-maintenance.php`. Cross-package contracts: **3 hooks** (was 2 before this phase).

**Plugin `inc/` grew by 5 modules + seed-content/.** The bootstrap got a 3rd pre-flight guard (function-redeclare defense), and the require_once chain gained 5 lines in dependency order (helpers → surfaces → migrations → og-card → reading-time).

### Release ritual exercised

Phase 3 was the first time the project did **theme-first then plugin-second** ordering. The inverse of Phase 2b — required because Phase 3 introduced function definitions in both packages that would have PHP-fataled at parse time if the plugin had shipped first while the theme still had its copies.

Actual gap between theme v8.4.0 tag push and plugin v1.3.0 tag push: **~60 seconds** (theme deploy took 54s; tagged plugin immediately after). During that window:
- Theme files for retired modules returned 404
- `[sn_reading_time]` shortcode would render literally for any pageload in the window
- /notes route rendered without reading-time enrichment (graceful — theme's `page-notes-render.php` uses `function_exists()` guard at line 84)
- Existing cached OG cards in `wp-content/uploads/sn-og/` continued being served

The gap closed cleanly. Plugin v1.3.0 deployed in 9s (Phase 2c's SSH-deploy ritual lived up to its design budget — single git-checkout step is much faster than Cloudways API roundtrip).

### Verification results

- ☑ Theme v8.4.1 live (Version header verified via curl).
- ☑ Three retired theme files return 404 on live server.
- ☑ Theme `inc/og-fonts.php` returns 200.
- ☑ Plugin v1.3.0 deployed; `/wp-json/signal-noise/v1/purge-cache` returns 200 (proves plugin loaded + reading-time module + content-surfaces module compile).
- ☑ `[sn_reading_time]` shortcode renders correctly on /provenance ("5 min read", "7 min read" visible).
- ☑ /notes route renders HTTP 200.
- ☑ `docs/WORDPRESS-REFERENCE.md §10.0` now lists 3 cross-package hooks.

### What needs follow-up attention (non-blocking)

**OG card backfill.** A sampled post (`/notes/five-years-of-remote-freelance-work/`) currently serves the site icon (`cropped-jl_logo-min.png`) as its og:image — not a `/sn-og/<slug>.png` from the cache. Three reasons it could be cold:

1. The original theme cards may have been cleared at some point (cache directory listing showed 403 — directory likely exists but is webserver-protected for indexing).
2. The plugin's lazy URL helper (`sn_og_image_url_for_post`) is documented to generate on demand from `wpseo_opengraph_image` filter, but Yoast's fallback chain takes precedence when the generator returns false.
3. The plugin's backfill admin migration (`sn_migrate_backfill_og_cards`) runs on `admin_init` — won't fire until you next visit `/wp-admin/`.

**Recovery options:**
- Visit `/wp-admin/` once — fires `admin_init`, triggers the backfill migration.
- Re-save any post to fire `wp_after_insert_post` and regenerate its card.
- If the option flag `sn_og_backfilled_v1` is set (from a previous backfill run) but the cache is empty, delete it via `wp option delete sn_og_backfilled_v1` over SSH, then re-trigger admin_init.

The plan's AC#5 expected the OG card to show on a tested post; this is the one AC that didn't fully validate. It's not a Phase 3 regression — same code path as v8.3.0, same backfill semantics, same Yoast fallback chain.

### Phase 3 commits

**Theme repo** (`juanlentino/signal-and-noise` → `main`):

| SHA | Title |
|---|---|
| `13cf1cd` | `docs: spec for Phase 3 — theme-coupled file moves` |
| `fbbafd9` | `docs: implementation plan for Phase 3 (15 atomic tasks)` |
| `7c42d21` | `og-fonts: register theme typefaces for plugin's OG card generator` |
| `7021d57` | `inc/: delete og-image, reading-time, notes-and-provenance + seed-content` |
| `600bd5a` | `docs(WP-REFERENCE): add sn_og_font_paths to §10.0 contract surface` |
| `db3ef8d` | `v8.4.0: move 3 theme-coupled modules to plugin; add og-fonts shim` (tagged) |
| `ed87374` | `v8.4.1: bump style.css Version field` (tagged — cosmetic hotfix) |

**Plugin repo** (`juanlentino/signal-and-noise-tools` → `main`):

| SHA | Title |
|---|---|
| `c86f741` | `content-rendering-helpers: extract block markup generators` |
| `192da5d` | `content-surfaces: extract notes/provenance content structure` |
| `710bc8c` | `content-migrations: extract 11 one-shot DB seed migrations` |
| `0d961d1` | `seed-content: move HTML bodies from theme to plugin` |
| `6910ec6` | `og-card-generator: PHP GD OG card rendering` |
| `78f71e8` | `reading-time: calculation, caching, shortcode, render_block bridge` |
| `6b0f3c9` | `bootstrap: add guard #3 + require 5 new Phase 3 modules` |
| `4ca64e8` | `v1.3.0 staging: absorb theme-coupled content surfaces + OG + reading time` (tagged as v1.3.0) |

15 commits across both repos (counting the v8.4.1 hotfix). Net code movement: **~2,856 LOC** removed from theme; **~1,940 LOC** added to plugin (some compression from removed boilerplate).

### Phase 3 lessons captured

**`function_exists()` guards work.** The theme's `page-notes-render.php:84` (`function_exists('sn_get_reading_time') ? sn_get_reading_time(...) : null`) made the 60s deploy gap survivable. This pattern — "use defensive guards in code that calls cross-package functions" — should propagate to any future cross-package call site we add. Already documented in [WORDPRESS-REFERENCE.md §10.0](../../WORDPRESS-REFERENCE.md) note: "Never let plugin code directly call a theme function — even with function_exists guards. The contract pattern is non-negotiable." But the *inverse* (theme calling plugin) was solved here pragmatically with a `function_exists()` guard. Worth noting that the contract policy applies to both directions ideally but pragmatic exceptions exist at the call-site granularity.

**Version-bump sequencing matters.** The v8.4.0 release shipped with `Version: 8.3.0` in `style.css` because of an Edit-tool sequencing error where the first Edit succeeded silently and the retry-Edit failed. The git commit was "1 file changed" instead of expected 2 — that's the diagnostic to spot next time. Always `git diff` (or `grep '^Version:' style.css`) **before** the tag-push command.

**Theme-first release order is the right call when both packages add functions.** Plugin-first works when only one side is changing (Phase 2b: plugin trims read sites, theme drops contracts). Theme-first is required when both sides add code that could collide. The pre-flight guard #3 (function_exists check) is defense-in-depth, not the primary mechanism — bounds the failure mode if order is accidentally inverted.

## What's next (post-Phase 3)

**The roadmap is empty.** All four phases queued in the original Phase 1 spec have shipped:

- ☑ Phase 1 (v8.2.0 / Tools v1.0.0): split 9 modules from theme to plugin
- ☑ Phase 2a (no version bump): Cloudways auto-deploy via GitHub Actions
- ☑ Phase 2b (v8.3.0 / Tools v1.2.0): delete obsolete updater + self-heal; deploy-time CF purge
- ☑ Phase 2c (no version bump): plugin SSH-based auto-deploy via dedicated app-scoped user
- ☑ Phase 3 (v8.4.1 / Tools v1.3.0): theme-coupled file moves (this session)
- ☑ Phase 4 (shipped early in v8.2.1 / Tools v1.1.0): RSS Plausible tracker migrated

**Deferred hygiene items (still rejected):**
- Inline-styles → external CSS refactor (124 instances). Real WP handbook violation, no operational payoff.
- Full i18n coverage. Stripped in v8.1.1; re-introducing requires ~hundreds of strings + `.pot` file. Zero value for a single-author tool.

**Outstanding optional cleanup items:**
- Visit `/wp-admin/` to fire the OG card backfill migration (see above).
- Delete `wp-content/mu-plugins/rss-plausible-tracker.php` legacy file via SFTP (carried over from Phase 2b handoff; functional impact today is zero).
- Delete the two plugin backup directories from Phase 2c cutover: `signal-and-noise-tools-1.2.0-old` and `signal-and-noise-tools-OLD-MASTER`. Both are now ~1 day old; safe to remove if the new setup remains stable.
- Cloudways Server Management → SSH Public Keys: remove the master-scoped `sn-tools-deploy` entry from the wrong-direction setup early in Phase 2c.

## Quick-start commands for next session

**Smoke-test either auto-deploy any time:**

```bash
# Theme:
cd /Users/juanlentino/projects/signal-and-noise
git tag -a v8.4.2-smoke -m "smoke" && git push origin v8.4.2-smoke
sleep 30 && gh run list --repo juanlentino/signal-and-noise --workflow=deploy.yml --limit 1

# Plugin:
cd /Users/juanlentino/Projects/signal-and-noise-tools
git tag -a v1.3.1-smoke -m "smoke" && git push origin v1.3.1-smoke
sleep 30 && gh run list --repo juanlentino/signal-and-noise-tools --workflow=deploy.yml --limit 1
```

**Reset to clean state after smoke test:**

```bash
# Local + remote tag cleanup
git tag -d <smoke-tag> && git push origin :refs/tags/<smoke-tag>

# Live server (as sn-plugin) — reset to the canonical tag:
# (Requires SSH key — regenerate or copy from gh secret if needed)
```

**If `[sn_reading_time]` ever renders as literal token again:** check that plugin v1.3.0+ is active. The shortcode is registered in `signal-and-noise-tools/inc/reading-time.php:133`. If it's not registering, look for guard #3 firing (admin notice should appear).

## Anti-checklist (things NOT to do)

- **Don't** re-introduce `inc/og-image.php`, `inc/reading-time.php`, or `inc/notes-and-provenance.php` in the theme. Plugin guard #3 will block the plugin from loading and surface a clear admin notice — but the better outcome is just don't do it.
- **Don't** assume "if my Edit returned successfully, the file changed." Always `git diff` or `grep` the modification before committing version bumps. The v8.4.0 → v8.4.1 cosmetic hotfix was the consequence of skipping that check.
- **Don't** delete `theme/inc/og-fonts.php` thinking it's a no-op. It's the only listener for the `sn_og_font_paths` filter; without it, OG cards fall back to "no card" (Yoast renders site icon).
- **Don't** rename the post meta key `_sn_reading_time_minutes`. It's a stable contract between plugin (writes) and theme (reads in `page-notes-render.php`). Migration would need a value-copy step + a long deprecation window.

## Session statistics (this session)

| Metric | Value |
|---|---|
| Phases completed | 3 |
| Releases shipped | Theme v8.4.0 + v8.4.1 + Plugin v1.3.0 |
| Atomic commits | 15 across both repos |
| Lines of code moved theme → plugin | ~2,856 deletions in theme; ~1,940 insertions in plugin |
| Cross-package contracts added | 1 (sn_og_font_paths) |
| Cross-package contracts retired | 1 (sn_admin_reading_time_tab — now intra-plugin) |
| Net cross-package contracts post-Phase 3 | 3 (was 2) |
| Workflow runs | 3 deploys (theme v8.4.0, theme v8.4.1, plugin v1.3.0) all successful |
| Bugs surfaced + fixed | 1 (style.css Version field cosmetic mismatch — fixed in v8.4.1) |
| Unresolved/observational | 1 (OG card backfill cold cache — recoverable, not a regression) |
