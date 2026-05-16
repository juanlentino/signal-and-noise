# Phase 3 — theme-coupled file moves

**Date:** 2026-05-16
**Status:** Approved (brainstorm complete; writing-plans next)
**Releases:**
- Theme `signal-and-noise` — `8.3.0` → **`8.4.0`** (minor — content surface ownership transferred to plugin; theme renderer adapts via new filter contract)
- Plugin `signal-and-noise-tools` — `1.2.0` → **`1.3.0`** (minor — three new modules absorbing content surfaces, OG card generation, and reading-time data)

## Context

Phase 1 (v8.2.0 / Tools v1.0.0) split the project into two repos but deferred five `inc/` files because they were "presentation-coupled and need real judgment calls per file" (per the original Phase 1 spec). Three sessions of deploy-infrastructure work later (Phase 2a/2b/2c), the architecture is stable enough to revisit them with a clearer principle than was available then: **what should survive a theme swap?**

Content surfaces (the Notes category, the /provenance pillar page, the permalink structure that makes `/notes/foo` resolve) are below-the-fold data — if the theme were replaced tomorrow, those URLs and that category need to keep working. Reading-time minutes are analytics-adjacent data, cached against post meta — same survival requirement. OG card generation is operational image processing that happens to bake brand fonts into PNGs — the *rendering pipeline* is operational, the *typeface choice* is presentation.

The two `page-notes-*` files are the inverse: theme-specific defenses (`page-notes-template.php` exists because of three documented incidents where WordPress's block-template resolution drifted) and theme-specific rendering (`page-notes-render.php` outputs the entire HTML with brand-specific aesthetic vocabulary). Both stay.

## Goal

After this phase lands:

1. The plugin owns content surfaces. The Notes category, the /provenance page, permalink rewrites — all defined in plugin code. A future theme swap leaves the URL structure intact.
2. The plugin owns reading-time data. Calculation, caching, the `[sn_reading_time]` shortcode — all plugin-side. Theme renderers that need it call the same shortcode or read the same post meta key.
3. The plugin owns OG card generation. PHP GD rendering, Yoast filter integration, the migration backfill — all plugin-side. Theme provides the fonts via a new filter contract.
4. The theme keeps the two `page-notes-*` files. They're theme-specific workarounds + theme-specific rendering; moving them would require the plugin to ship a hardcoded fallback template, which is exactly the layering inversion we're trying to avoid.
5. The 1,058-line `notes-and-provenance.php` is split into three smaller files in the plugin (content surfaces, migrations, rendering helpers), each within the project's ~300-LOC ceiling.

## Architecture

### File movement matrix

```
[ Theme repo: inc/ ]                          [ Plugin repo: inc/ ]
─────────────────────                         ─────────────────────
og-image.php ............... DEL              content-surfaces.php ......... NEW (~250 LOC, from notes-and-provenance.php)
reading-time.php ........... DEL              content-migrations.php ....... NEW (~700 LOC, from notes-and-provenance.php)
notes-and-provenance.php ... DEL              content-rendering-helpers.php  NEW (~100 LOC, from notes-and-provenance.php)
page-notes-template.php .... STAY             og-card-generator.php ........ NEW (from theme og-image.php)
page-notes-render.php ...... STAY             reading-time.php ............. NEW (from theme reading-time.php)

functions.php (3 require_once lines) ... EDIT  signal-and-noise-tools.php ... EDIT (add 5 require_once lines)
style.css (Version field) .............. EDIT  signal-and-noise-tools.php ... EDIT (Version field)
CHANGELOG.md ........................... EDIT  CHANGELOG.md ................. EDIT
```

The plugin's `inc/` grows from 10 modules to 15. The theme's `inc/` shrinks from 11 modules + `seed-content/` to 8 modules + `seed-content/`.

### New cross-package contract

One new filter — total goes from 2 to 3.

| Hook | Type | Direction | Purpose |
|---|---|---|---|
| `sn_og_font_paths` | filter | plugin → theme listener | Plugin's OG card generator calls `apply_filters('sn_og_font_paths', $defaults)`. Theme listens and returns `['bebas' => get_theme_file_path('assets/fonts/og/BebasNeue-Regular.ttf'), 'dmmono' => get_theme_file_path('assets/fonts/og/DMMono-Light.ttf')]`. If no listener present, plugin uses its own defaults (TTF files NOT bundled — falls back to a basic font and logs a notice). |

The two existing contracts (`sn_purge_all_caches_result`, `sn_clear_template_overrides_result`) are unchanged.

### Data dependencies (no contract needed)

- **Reading-time meta key `_sn_reading_time_minutes`.** Plugin's reading-time module reads/writes this post meta. Theme's `page-notes-render.php` reads it directly via `get_post_meta()` (already does today, line 73-ish — `sn_notes_render_reading_time` function). After the move, theme keeps reading the same key; plugin keeps writing it. No filter/action needed — they share a stable meta key.
- **Shortcode `[sn_reading_time]`.** Plugin registers it. Theme templates use it. WordPress's shortcode dispatch is global — same behavior regardless of which package registered.
- **Notes category constants (`SN_NOTES_CATEGORY_SLUG = 'notes'`).** These were defined at the top of `notes-and-provenance.php`. After the move, they live in the plugin's `content-surfaces.php`. Theme code that referenced them (if any — needs audit during execution) would need to use the string literal `'notes'` or define its own constant.

### What stays in theme (after Phase 3)

`inc/` contents:
- `assets-frontend.php` — frontend asset enqueueing
- `frontend-filters.php` — render_block + social-link filters
- `page-notes-render.php` — full HTML renderer for /notes
- `page-notes-template.php` — template_include override
- `patterns.php` — block patterns
- `seed-content/` — content seeds for new installs
- `setup.php` — theme bootstrap (supports, image sizes, etc.)
- `template-maintenance.php` — cache purge + clear-overrides functions + filter listeners

8 files + `seed-content/`. Pure presentation layer.

## Components

### 1. Move `og-image.php` to plugin

**Source:** theme `inc/og-image.php` (402 LOC)
**Destination:** plugin `inc/og-card-generator.php` (same LOC, minimal modifications)

**Modifications during the move:**
- Line 160-161: replace `get_theme_file_path(...)` calls with:
  ```php
  $font_paths = apply_filters( 'sn_og_font_paths', array() );
  $bebas_path = $font_paths['bebas'] ?? '';
  $dmmono_path = $font_paths['dmmono'] ?? '';
  if ( ! $bebas_path || ! file_exists( $bebas_path ) ) {
      // Log + bail: caller gets false, falls back to featured image
      return false;
  }
  ```
- Update file-level docblock to reference plugin context.
- Function names stay the same (`sn_generate_og_card`, `sn_og_image_url_for_post`, etc.) — they were already prefixed `sn_` and don't collide.

**Theme-side new listener** in `inc/setup.php` or a new tiny `inc/og-fonts.php`:
```php
add_filter( 'sn_og_font_paths', function( $paths ) {
    return array(
        'bebas'  => get_theme_file_path( 'assets/fonts/og/BebasNeue-Regular.ttf' ),
        'dmmono' => get_theme_file_path( 'assets/fonts/og/DMMono-Light.ttf' ),
    );
} );
```

**Decision: add this as a new `inc/og-fonts.php` file (15 LOC).** Keeps `setup.php` focused, makes the contract surface visually obvious in the theme.

### 2. Move `reading-time.php` to plugin

**Source:** theme `inc/reading-time.php` (396 LOC)
**Destination:** plugin `inc/reading-time.php` (same name, ~same LOC)

**Modifications during the move:**
- Line 330: `add_action( 'sn_admin_reading_time_tab', ...)` already hooks into a plugin-side action. After the move, this becomes intra-plugin (same file, no boundary crossed). The "cleanup" is automatic from the relocation.
- Update file-level docblock.

**No theme-side residual.** The shortcode + post meta key are globally addressable; theme renderers call them the same way regardless of which package owns them.

### 3. Split `notes-and-provenance.php` into 3 plugin modules

**Source:** theme `inc/notes-and-provenance.php` (1,058 LOC)

**Destinations** (in plugin `inc/`):

#### 3a. `content-surfaces.php` (~250 LOC)
Holds:
- All the constant definitions (lines ~13-40 in current file): `SN_NOTES_CATEGORY_SLUG`, `SN_NOTES_PAGE_SLUG`, `SN_PROVENANCE_SLUG`, `SN_OVER_DETECTION_SLUG`, `SN_AS_SUBSTRATE_SLUG`, `SN_PERMALINK_STRUCTURE`, plus all 9 `SN_*_MIGR_OPT` flags.
- `sn_seed_content_surfaces()` (line 63) — the master orchestrator.
- `sn_ensure_notes_category()` (line 79).
- `sn_ensure_notes_page()` (line 94).
- `sn_ensure_provenance_page()` (line 121).
- `sn_ensure_over_detection_page()` (line 146).
- `sn_ensure_as_substrate_page()` (line 173).
- `sn_ensure_permalink_structure()` (line 422).
- `sn_sync_default_category()` (line 456).
- `add_action('after_switch_theme', ...)` (line 54).
- `add_action('admin_init', 'sn_seed_content_surfaces')` (line 61).
- The `query_loop_block_query_vars` filter (line 472).

This is the "what content surfaces exist" file. ~250 LOC target.

#### 3b. `content-migrations.php` (~700 LOC)
Holds:
- `sn_load_provenance_body()` + `sn_load_over_detection_body()` + `sn_load_as_substrate_body()` (lines 199-217 — these load HTML from `seed-content/`).
- All 9 `sn_migrate_provenance_*` functions + their corresponding `add_action('admin_init', ...)` registrations:
  - `sn_migrate_provenance_body` (line 236)
  - `sn_migrate_provenance_refinements` (line 286)
  - `sn_migrate_provenance_byline_reading_time` (line 347)
  - `sn_migrate_provenance_split` (line 508)
  - `sn_migrate_as_substrate_seed` (line 667)
  - `sn_migrate_provenance_card2_longform` (line 708)
  - `sn_migrate_provenance_card_readtimes_dynamic` (line 766)
  - `sn_migrate_provenance_catalog_numbers` (line 819)
  - `sn_migrate_as_substrate_post_date_displaytype` (line 875)
  - `sn_migrate_over_detection_eyebrow_dynamic` (line 937)
  - `sn_migrate_clear_notes_template_override` (line 1015)

This is the "one-shot DB seed migrations" file. Each migration is gated by its corresponding `*_MIGR_OPT` flag from `content-surfaces.php`.

**Note:** the `seed-content/` directory currently lives in the theme (`inc/seed-content/`). It contains the HTML bodies these migrations load. **Move it to plugin alongside `content-migrations.php`** (`signal-and-noise-tools/inc/seed-content/`). Update path references in the `sn_load_*_body()` functions from `__DIR__ . '/seed-content/'` (works as-is since `__DIR__` resolves relative to the file's new location).

#### 3c. `content-rendering-helpers.php` (~100 LOC)
Holds:
- `sn_provenance_byline_reading_time_markup()` (line 395).
- `sn_provenance_toc_block_markup()` (line 408).
- `sn_provenance_papers_index_markup()` (line 568).

These are pure functions that return Gutenberg block markup strings. Called from migrations. Putting them in their own file makes the migrations file shorter and these helpers easier to find when content-tweaking.

### 4. Move `seed-content/` to plugin

**Source:** theme `inc/seed-content/`
**Destination:** plugin `inc/seed-content/`

This is theme-shipped HTML right now but it's content, not presentation. Migrations consume it once on `admin_init`. Plugin should own it for the same reasons content-surfaces moves to plugin.

### 5. Theme: register `sn_og_font_paths` listener

**New file:** theme `inc/og-fonts.php` (~15 LOC)

```php
<?php
/**
 * Signal & Noise — provides OG card font paths to the plugin's
 * card generator. The plugin owns the rendering pipeline; the theme
 * owns the brand typography.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_filter( 'sn_og_font_paths', function( $paths ) {
    return array(
        'bebas'  => get_theme_file_path( 'assets/fonts/og/BebasNeue-Regular.ttf' ),
        'dmmono' => get_theme_file_path( 'assets/fonts/og/DMMono-Light.ttf' ),
    );
} );
```

Add `require_once __DIR__ . '/inc/og-fonts.php';` to `functions.php`.

### 6. Theme: remove require_once lines

In `functions.php`:
- Delete `require_once __DIR__ . '/inc/og-image.php';`
- Delete `require_once __DIR__ . '/inc/reading-time.php';`
- Delete `require_once __DIR__ . '/inc/notes-and-provenance.php';`
- ADD `require_once __DIR__ . '/inc/og-fonts.php';`

Net: theme's `functions.php` loses 3 require_once + gains 1.

### 7. Plugin: add require_once lines

In `signal-and-noise-tools.php`:
- ADD `require_once __DIR__ . '/inc/og-card-generator.php';`
- ADD `require_once __DIR__ . '/inc/reading-time.php';`
- ADD `require_once __DIR__ . '/inc/content-surfaces.php';`
- ADD `require_once __DIR__ . '/inc/content-migrations.php';`
- ADD `require_once __DIR__ . '/inc/content-rendering-helpers.php';`

Order matters: `content-surfaces.php` first (defines constants), then `content-rendering-helpers.php` (helpers used by migrations), then `content-migrations.php`. `og-card-generator.php` and `reading-time.php` can go anywhere.

### 8. Update `docs/WORDPRESS-REFERENCE.md`

Section §10.0 cross-package contract table grows from 2 hooks to 3:
- Add `sn_og_font_paths` row.

Section §10.4 (`/notes` route) stays unchanged — those files stay in theme.

### 9. Plugin: pre-flight guard (function-redeclare defense)

Add to `signal-and-noise-tools.php` bootstrap, alongside the existing guards #1 and #2 (from v1.0.1 and v1.1.0):

**Guard #3:** before the plugin's `require_once` chain runs, check whether any of the theme's now-retired modules still exist on disk. If yes, bail with an admin notice instructing the maintainer to ship theme v8.4.0 first.

```php
// Guard #3 (added v1.3.0): if the theme still ships modules whose functions
// we now declare, requiring our copies would PHP-fatal at load time
// (per WORDPRESS-REFERENCE.md §13 gotcha #12). Bail with a clear notice.
$theme_dir = get_template_directory();
$retired_in_theme = array(
    $theme_dir . '/inc/og-image.php',
    $theme_dir . '/inc/reading-time.php',
    $theme_dir . '/inc/notes-and-provenance.php',
);
foreach ( $retired_in_theme as $f ) {
    if ( file_exists( $f ) ) {
        add_action( 'admin_notices', function() use ( $f ) {
            $rel = str_replace( ABSPATH, '', $f );
            echo '<div class="notice notice-error"><p><strong>Signal & Noise Tools v1.3.0:</strong> theme still ships <code>' . esc_html( $rel ) . '</code>. Update the theme to v8.4.0+ first to avoid function-redeclare fatals.</p></div>';
        } );
        return; // Skip require_once chain; plugin is "loaded" but dormant.
    }
}
```

This guard never fires in normal operation (theme v8.4.0 ships first, so the files don't exist by the time plugin v1.3.0 activates). It's defense-in-depth against accidental install-order inversion.

### 10. Version bumps + CHANGELOGs

**Theme** `style.css`: `Version: 8.3.0` → `Version: 8.4.0`.

**Theme CHANGELOG** (prepend):
```markdown
## [8.4.0] - 2026-05-16

### Removed
- `inc/og-image.php` — moved to plugin `inc/og-card-generator.php`. Plugin generates OG cards; theme provides fonts via new `sn_og_font_paths` filter.
- `inc/reading-time.php` — moved to plugin `inc/reading-time.php`. Calculation + caching + shortcode + render_block bridge all plugin-side.
- `inc/notes-and-provenance.php` — moved to plugin and split into `inc/content-surfaces.php`, `inc/content-migrations.php`, `inc/content-rendering-helpers.php`.
- `inc/seed-content/` directory — moved to plugin alongside the migrations that consume it.

### Added
- `inc/og-fonts.php` — registers theme's typefaces as the response to the plugin's `sn_og_font_paths` filter.

### Changed
- Cross-package contract surface grows from 2 hooks to 3 (added `sn_og_font_paths`).
- `docs/WORDPRESS-REFERENCE.md §10.0` updated.

### Notes
- Requires plugin v1.3.0+. If installed against plugin v1.2.0, OG card generation stops and the /notes content surface is no longer self-seeding.
```

**Plugin** `signal-and-noise-tools.php`: `Version: 1.2.0` → `Version: 1.3.0`.

**Plugin CHANGELOG** (prepend):
```markdown
## [1.3.0] - 2026-05-16

### Added
- `inc/og-card-generator.php` — OG/Twitter card PHP GD generation, caching, Yoast filter integration. Fonts provided by the theme via `sn_og_font_paths` filter.
- `inc/reading-time.php` — reading time calculation, caching in `_sn_reading_time_minutes` post meta, `[sn_reading_time]` shortcode, render_block bridge for block-context shortcodes.
- `inc/content-surfaces.php` — Notes category, /notes Page, /provenance + /over-detection + /as-substrate Pages, permalink structure, query loop scoping.
- `inc/content-migrations.php` — 11 one-shot content seed migrations for the Provenance pillar (body, refinements, byline reading time, split, AS substrate seed, card2 longform, card readtimes dynamic, catalog numbers, post-date displaytype, eyebrow dynamic, clear notes template override).
- `inc/content-rendering-helpers.php` — block markup generators called from migrations.
- `inc/seed-content/` — HTML bodies consumed by content migrations.

### Notes
- Requires theme v8.4.0+. If installed against an older theme, OG card generation falls back to "no card" (Yoast then renders featured image only); the `/notes` route continues to render via the theme's `page-notes-render.php` regardless.
```

## Sequence of operations (release order)

Same plugin-first pattern as Phase 2b:

1. **Plugin v1.3.0 first** (auto-deploys via Phase 2c workflow).
   - Adds the 5 new modules + seed-content directory.
   - At this point theme still has its own copies — both packages run the same code in parallel. This is fine because:
     - The functions are idempotent: `sn_ensure_notes_category()` checks for existence first; `sn_migrate_*` functions are gated by `*_MIGR_OPT` option flags.
     - PHP function redeclaration is the danger from Phase 1's lesson (memory: gotcha #12 in WP-REFERENCE.md). **The plugin's modules must use the same function names** so when the theme's copies disappear in step 2, the plugin's copies take over seamlessly.
   - **Problem: function-redeclare fatal.** Plugin v1.3.0 installing while theme still ships `inc/og-image.php`, `inc/reading-time.php`, `inc/notes-and-provenance.php` would fatal at parse time with "Cannot redeclare function."
   - **Fix:** plugin v1.3.0 ships with pre-flight guards similar to v1.0.1's guard #1 — checks for the theme's `inc/og-image.php`, `inc/reading-time.php`, `inc/notes-and-provenance.php` and bails activation with an admin notice if any are found. Forces the maintainer to ship theme v8.4.0 BEFORE plugin v1.3.0.

2. **Inversion of normal release order, courtesy of the redeclare problem:** **Theme v8.4.0 first**, plugin v1.3.0 second.

   This is the opposite of Phase 2b. The reason: Phase 2b's plugin v1.2.0 didn't introduce new functions — it just trimmed UI/REST surfaces that read from theme-side filters. The theme-side filter listeners stayed until the theme update.

   Phase 3 introduces FUNCTIONS that exist in both packages until cutover. Function names collide → redeclare fatal. Theme must drop its copies first.

   **But:** between "theme v8.4.0 ships" and "plugin v1.3.0 ships," the live site has NO `sn_og_*` functions, NO `sn_reading_time_*` functions, NO content-surfaces seeding code. Effects during the gap:
   - OG cards: existing cached PNGs in `wp-content/uploads/sn-og/` keep being served (the URL filter no longer overrides Yoast, but Yoast falls back to featured image — acceptable).
   - Reading time: `[sn_reading_time]` shortcode becomes unregistered, renders as the literal `[sn_reading_time]` string in any page that uses it. **VISIBLE BREAKAGE during the gap.**
   - Content surfaces: existing Notes category + Pages remain (they're DB rows, not code). Migrations don't re-run (already gated). Permalink structure: WordPress remembers the active permalink structure in the DB even if the code that set it disappears.

   The reading-time shortcode breakage is the critical issue during the gap.

3. **Mitigation: ship plugin v1.3.0 immediately after theme v8.4.0.** Since both auto-deploy on tag push, the gap can be reduced to ~30 seconds with back-to-back tag pushes:
   ```bash
   # In theme repo:
   git push origin v8.4.0
   # Wait for theme deploy to complete (~30s)
   # In plugin repo:
   git push origin v1.3.0
   ```

   The /provenance page byline (which uses `[sn_reading_time]` inline) will show the literal shortcode string for ~30 seconds. Any visitor in that window sees `[sn_reading_time]` instead of `5 min read`. Cosmetic, recoverable.

4. **Better mitigation: ship plugin v1.3.0 FIRST with the function names changed to non-colliding suffixes** (e.g., `sn_calculate_reading_time_v2`), then ship theme v8.4.0 that removes the v1 names AND adds aliasing for backward compat, then ship plugin v1.3.1 that drops the v2 suffix. Three releases, no breakage window.

   **Too complex.** This is single-author tooling on a personal site. 30 seconds of shortcode rendering as `[sn_reading_time]` on /provenance is recoverable; engineering around it isn't worth it.

5. **Decided approach:** ship theme v8.4.0 first, plugin v1.3.0 immediately after (target gap < 60s). Document the visible-breakage window in the v8.4.0 CHANGELOG note. Both repos auto-deploy on tag push so back-to-back is fast.

## Acceptance criteria

1. ☐ Theme v8.4.0 ships; the `Deploy to Cloudways` workflow run completes successfully.
2. ☐ Plugin v1.3.0 ships immediately after; the plugin's `Deploy to Cloudways` workflow run completes successfully (and the post-deploy /purge-cache call returns 200).
3. ☐ After both ship, `wp-content/themes/signal-and-noise/inc/` contains exactly 8 files: `assets-frontend.php`, `frontend-filters.php`, `og-fonts.php`, `page-notes-render.php`, `page-notes-template.php`, `patterns.php`, `setup.php`, `template-maintenance.php`. Plus `seed-content/` is **gone**.
4. ☐ After both ship, `wp-content/plugins/signal-and-noise-tools/inc/` contains the 5 new modules: `og-card-generator.php`, `reading-time.php`, `content-surfaces.php`, `content-migrations.php`, `content-rendering-helpers.php`. Plus `seed-content/` is **present**.
5. ☐ OG card regeneration works end-to-end: edit a post, save, watch `wp-content/uploads/sn-og/<slug>.png` get rewritten. Inspect: the card uses Bebas Neue + DM Mono (theme-provided fonts via filter).
6. ☐ `[sn_reading_time]` shortcode in /provenance page renders as `N min read` after plugin v1.3.0 lands.
7. ☐ /notes route still renders correctly (theme's `page-notes-render.php` is unaffected — it reads `_sn_reading_time_minutes` post meta, which plugin v1.3.0 writes).
8. ☐ `docs/WORDPRESS-REFERENCE.md §10.0` lists 3 cross-package hooks (added `sn_og_font_paths`).

## Risks + mitigations

| Risk | Mitigation |
|---|---|
| Plugin v1.3.0 deployed before theme v8.4.0 → function-redeclare fatal | Plugin v1.3.0 ships with a pre-flight guard checking for `inc/og-image.php`, `inc/reading-time.php`, `inc/notes-and-provenance.php` in theme; bails activation with admin notice if any found. |
| Theme v8.4.0 deployed before plugin v1.3.0 → 30-60s window where `[sn_reading_time]` shortcode renders as literal string in /provenance page | Back-to-back tag pushes (target gap < 60s). Documented in v8.4.0 CHANGELOG. Cosmetic breakage, recoverable on next pageload after plugin v1.3.0 lands. |
| Content migration option flags (`SN_*_MIGR_OPT`) live in `wp_options` table — if both packages define these as constants and they drift, migrations could re-run | Plugin v1.3.0 owns the canonical constant values. Theme v8.4.0 doesn't define them anymore (since `notes-and-provenance.php` is gone). No drift possible. |
| Theme's `page-notes-render.php` calls something defined in the deleted theme modules | Audit during execution. Likely just `_sn_reading_time_minutes` meta reads (which plugin v1.3.0 continues to populate) and category slug `'notes'` (literal string, no dependency). |
| `seed-content/` directory move breaks something | `sn_load_*_body()` functions use `__DIR__ . '/seed-content/'` — works as-is after the file moves to plugin (the `__DIR__` resolves relative to the new file location). Verify during execution: `find . -name "*.html" -path "*seed-content*"` confirms files arrive in plugin. |
| OG card generation falls back to no-card when `sn_og_font_paths` filter has no listener | Defensive: if `$font_paths['bebas']` doesn't exist or font file is missing, `sn_generate_og_card()` returns false. Yoast falls back to featured image. Acceptable degradation. |
| Cloudflare cache holds stale post pages that reference now-broken shortcode during the 30-60s gap | Both deploys trigger CF purge automatically (Phase 2b workflow step). Stale content TTL is bounded. |

## Out of scope

- **Renaming `_sn_reading_time_minutes` post meta key.** Stays as-is. Plugin reads + writes; theme renderer reads. Stable contract.
- **Adding tests.** Neither repo has test infrastructure. Verification is hand-execution + smoke-test per the established pattern.
- **Repointing `sn_*` function names to namespaced equivalents** (`SignalNoise\OG\generate_card`). Tempting cleanup but unrelated scope; the project's PHP code is procedural by convention.
- **Cleaning up orphaned `wp_options` rows** from the now-gone theme constants. Old `SN_*_MIGR_OPT` values persist in the DB; they prevent migrations from re-running (which is correct behavior). No cleanup needed.

## References

- Phase 2c handoff: [docs/superpowers/handoffs/2026-05-16-end-of-phase-2c-handoff.md](../handoffs/2026-05-16-end-of-phase-2c-handoff.md)
- Phase 2b spec (for the plugin-first ordering precedent): [docs/superpowers/specs/2026-05-15-phase-2b-cleanup-design.md](2026-05-15-phase-2b-cleanup-design.md)
- WP-REFERENCE §10.0 cross-package contract surface: [docs/WORDPRESS-REFERENCE.md](../../WORDPRESS-REFERENCE.md)
- WP-REFERENCE §10.4 /notes route — stays unchanged
- WP-REFERENCE §13 gotcha #12 — function redeclare fatal (motivates pre-flight guard)
- Phase 1 plugin's existing pre-flight guard pattern: [signal-and-noise-tools.php:guard #1](https://github.com/juanlentino/signal-and-noise-tools/blob/main/signal-and-noise-tools.php)
