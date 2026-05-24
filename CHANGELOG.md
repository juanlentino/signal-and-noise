# Changelog

All notable changes to Signal & Noise are documented here.

## [9.1.0] - 2026-05-24

### Added — Theme-owned WP 7.0 Abilities API surface (12 new abilities)

The Signal & Noise theme becomes a first-class WP 7.0 Abilities API consumer-surface. Twelve new abilities expose the theme's design knowledge and brand-aware generative capabilities to any AI consumer (the companion plugin's AI features, the WP 7.0 AI Copilot, WP-CLI agents, future integrations) — making every AI-driven feature brand-aware instead of brand-blind.

**Read abilities (7):**

1. `signal-noise/get-design-tokens` — theme.json palette + typography + spacing scale (flattened name→hex map).
2. `signal-noise/list-block-patterns` — enumerates all registered block patterns + categories; optional category filter.
3. `signal-noise/get-active-template-structure` — shallow FSE block tree for a given post (by ID or slug).
4. `signal-noise/get-theme-version` — theme + WP environment metadata for drift detection.
5. `signal-noise/get-page-notes-pillars` — `/notes` pillar essay descriptors with reading time + last-modified.
6. `signal-noise/get-reading-time-for-slug` — wraps `sn_notes_reading_time_for_slug()` with typed integer output.
7. `signal-noise/get-design-system-summary` — pre-formats design tokens for AI prompt embedding (markdown / compact-text / json formats — typical 70-80% token reduction vs raw token JSON on compact-text).

**Generative abilities (5):** all call the companion plugin's `snt_ai_generate_with_constraints` helper (Sonnet 4.6 pinned via plugin v3.7.2+). Guarded with `function_exists` — if the plugin is missing, generative abilities return `WP_Error('ai_helper_unavailable')` with status 503 and a clear remediation message.

8. `signal-noise/ai-generate-page-note-summary` — single-sentence /notes-voice summary of a post.
9. `signal-noise/ai-suggest-block-pattern` — AI recommends 1-3 SN patterns for a draft; validates suggestions against the live registry.
10. `signal-noise/ai-validate-brand-alignment` — scores content (0-100) for fit with SN voice + palette across 5 dimensions.
11. `signal-noise/ai-generate-pattern-content` — fills a chosen pattern's shell with brand-voiced copy (no DB writes).
12. `signal-noise/ai-rewrite-in-brand-voice` — transforms external copy into SN voice; intensity + preservation flags.

### Theme test harness — new

`tests/abilities-registration.php` is the theme's FIRST test file. Establishes a standalone PHP test harness matching the companion plugin's `tests/health-checks.php` pattern: ~130+ assertions across the 12 abilities, no PHPUnit dependency, runs via `php tests/abilities-registration.php`. Covers happy paths, schema-validation, helper-unavailable fallbacks, markdown-fence stripping (per v3.7.0 Task B lesson), and the `error_log` instrumentation at every catch site (per v3.7.1 lesson).

### Architecture decisions

- **Theme-owned registration, not plugin-proxied.** Theme-domain knowledge (design tokens, patterns, /notes pillars) belongs in the theme. Lifecycle coupling makes natural sense: swap themes, the abilities swap with them. See [`docs/superpowers/specs/2026-05-24-theme-ai-abilities-design.md`](docs/superpowers/specs/2026-05-24-theme-ai-abilities-design.md) §3 for the full rationale.
- **Defensive category registration via `wp_has_ability_category` guard.** Per source-verified WP behavior at `class-wp-ability-categories-registry.php:57-67`, double-registration fires `_doing_it_wrong`. The plugin also registers `diagnostics`, `content`, `ai-generation`; the theme's first-mover guard handles theme-only / plugin-only / both-installed install states cleanly.
- **`function_exists('snt_ai_generate_with_constraints')` guard for generative abilities.** Theme→plugin coupling is a one-directional function call, not a filter. Brief windows where the theme ships before the plugin produce clean `ai_helper_unavailable` errors instead of fatals.
- **Cross-package filter contract stays at 3.** No new filters added. The existing `sn_purge_all_caches_result`, `sn_clear_template_overrides_result`, `sn_og_font_paths` from v8.4.0 are unchanged. See [`docs/WORDPRESS-REFERENCE.md`](docs/WORDPRESS-REFERENCE.md) §10.0.
- **Model pinning inherited from plugin.** Theme abilities don't re-pin the AI model — `snt_ai_generate_with_constraints` already pins Sonnet 4.6 (v3.7.2+). One source of truth, theme inherits.

### Files

- **Created:** `inc/abilities-registration.php` (12 abilities, 3 categories, 2 voice constants), `tests/abilities-registration.php` (harness + ~130 assertions)
- **Modified:** `functions.php` (+2 lines: module map + require_once), `style.css` (Version: 9.0.0 → 9.1.0), `CHANGELOG.md`

### Companion plugin v3.7.4 (separate release)

Command Palette (⌘K) commands for all 12 abilities ship in companion plugin v3.7.4 (separate release). WP-CLI access via `wp ability run signal-noise/*` works automatically once the theme is installed — no companion plugin update required for CLI consumption.

### Release-cap status

Minor cap is 5 per major (project override). v9.0 → v9.1 is well within the cap.

### Process

`superpowers:brainstorming` (architecture re-evaluated mid-session from plugin-proxied to theme-owned) → spec at `docs/superpowers/specs/2026-05-24-theme-ai-abilities-design.md` → plan at `docs/superpowers/plans/2026-05-24-theme-v9.1.0-ai-abilities.md` → TDD execution with subagent-driven development.

## [9.0.0] - 2026-05-20

### Added — WP 7.0 alignment + browser-native modernization

Three additive features for the WP 7.0 "Armstrong" launch day (also 2026-05-20):

1. **`settings.dimensions` opt-in** (`theme.json`): `width: true`, `height: true`, and 4 `dimensionSizes` presets (Hairline / Short / Medium / Tall — sizes `1px / 20rem / 32rem / 48rem`). Editors gain block-level width + height controls + a size-picker matching the SN spacing scale. Reference: [Dimensions Support Enhancements in WordPress 7.0](https://make.wordpress.org/core/2026/03/15/dimensions-support-enhancements-in-wordpress-7-0/).

2. **`settings.typography.textIndent: true`** (`theme.json`): Paragraph block gains a text-indent typography control. Reference: [New Block Support: textIndent](https://make.wordpress.org/core/2026/03/15/new-block-support-text-indent-textindent/).

3. **Cross-document View Transitions** (`assets/css/critical.css` +30 LOC): browser-native CSS `@view-transition { navigation: auto; }` rule + `view-transition-name` annotations on `.sn-header`, `.sn-footer`, `main`. Subtle fade between page navigations on Chrome/Edge 111+ and Safari 18+; silently no-op elsewhere. `prefers-reduced-motion: reduce` disables it via the standard media query.

**Note on View Transitions:** WP 7.0's own View Transitions are **admin-only** (smooth dashboard nav). The frontend opt-in here is the **browser CSS feature** ([CSS View Transitions Module Level 2](https://drafts.csswg.org/css-view-transitions-2/)) — same primitive WP itself uses, but for the site frontend instead of wp-admin. Theme-side adoption is independent of WP version.

### Cap rollover note

**v9.0.0 is a minor + patch cap rollover, NOT a semantic breaking change.** v8.x consumed patch 7/7 + minor 5/5 (per CLAUDE.md versioning rules), so the next functional theme change MUST roll to a new major. This release ships zero breaking changes — all settings additions are additive, no template / part / PHP changes, no removed CSS. Existing content renders identically.

### Files

- **Modified:** `theme.json` (+18 LOC for dimensions + 1 LOC for textIndent), `assets/css/critical.css` (+32 LOC for View Transitions), `style.css` (Version: 8.5.7 → 9.0.0), `CHANGELOG.md`

### Explicit non-changes

No template changes. No new PHP or `inc/` modules. No new JS. No new blocks. No removed CSS rules. No changes to existing dimensions or typography styles (only additions).

### Process

`superpowers:brainstorming` (with WP 7.0 Field Guide + 3 specific dev notes read mid-session) → spec written at `docs/superpowers/specs/2026-05-20-theme-v9.0.0-design.md` → executed inline due to small scope (~70 LOC across 2 files). Source-reading during the brainstorm caught a substantive scope error: my original bundle proposed "View Transitions + Block Visibility + Dimensions" as WP-7.0-native, but the dev notes revealed View Transitions are admin-only in 7.0 and Block Visibility's `theme.json` integration is deferred to 7.1. Corrected bundle ships actual adoptable features.

## [8.5.7] - 2026-05-18

### Hotfix — restore `.is-menu-open` styles to critical.css

User reported the 404 page looked "messed up" on a fresh incognito visit. Root cause: the v8.5.6 critical.css pruning removed the `.wp-block-navigation__responsive-container.is-menu-open` cascade on the theory that "it only renders after the hamburger tap, so it's below-the-fold." That conflates **render timing** with **interaction timing**. Tapping the hamburger can happen at any moment — including milliseconds after first paint, before deferred `layout.css` has fetched on slow/empty-cache connections. When that race happens, the menu renders with WP's default right-aligned vertical nav instead of the centered brutalist overlay.

### Fixed

- **Restored the 77-LOC `.is-menu-open` cascade to [assets/css/critical.css](assets/css/critical.css)** verbatim from layout.css. Critical-path styles must cover any user-triggered state that can fire before deferred CSS loads, regardless of where the visual element sits on initial paint.

### Kept

- The other v8.5.6 pruning stays:
  - Animations block (`@keyframes` + block-level entrance) — animations are progressive enhancement; missing them on first paint just removes the entrance fade, not the layout
  - `.wp-block-button__link` resting + hover — buttons can paint without hover styles, hover happens after mouseover (already past first paint window)
  - CF7 form rules — forms are deep below the fold; CSS race is improbable
- WCAG fixes from v8.5.6 (form `:focus-visible`, contrast darkening, 404 heading restructure) remain.

### CLAUDE.md correction

**Theme deploy is `workflow_dispatch:` only since v8.5.1** — not "auto-deploys on annotated-tag push" as CLAUDE.md previously claimed. Updated to match the workflow file. The canonical install path for theme updates is wp-admin → Dashboard → Updates (same as the plugin since v1.10.1).

### Notes

- **PATCH within `8.5.x`.** Patch headroom: 6/7 → **7/7 on 8.5.x — last patch slot used. Next change rolls to v9.0.0.**
- Lesson logged: critical CSS scope = "what can render before the deferred stylesheet roundtrip completes," not "what's above the fold geometrically." Different time horizons.

## [8.5.6] - 2026-05-17

### Audit consolidation — critical.css pruning + WCAG 2.1 AA fixes

Two parallel subagents (critical.css size review + WCAG 2.1 AA accessibility audit) both flagged work in the same files. Bundled into one patch.

### Fixed — critical.css pruning (176 LOC, ~5 KB removed)

[assets/css/critical.css](assets/css/critical.css) was 504 LOC; pure duplication or below-the-fold content accounted for ~35% of it. Four blocks removed:

- **Animations block (~41 LOC)** — 5 @keyframes + block-level entrance animation. Keyframes only need to load before the animation fires; block-level entrances animate content below the fold (`.wp-block-group`, etc.). Full definitions live in `assets/css/base.css` (deferred).
- **Button hover + transform (~26 LOC)** — `.wp-block-button__link` resting + hover + outline-style variants. Button hover is not first-paint critical. Full definitions in `assets/css/components.css` (deferred).
- **Mobile nav overlay (~77 LOC, ~40 `!important` declarations)** — the entire `.is-menu-open` cascade. Definitionally not above-the-fold — it only renders AFTER the hamburger tap. Full definitions in `assets/css/layout.css` (deferred).
- **CF7 form rules (~32 LOC)** — submit button + label styling. Forms are never above the fold. Full definitions in `assets/css/forms.css` (deferred).

Result: 504 → ~328 LOC. Further pruning of grain/scanline/scrollbar/skip-link sections deferred to a follow-up patch (medium-risk; needs visual verification to confirm zero FOUC).

### Fixed — WCAG 2.1 AA compliance (1 critical + 4 serious findings)

1. **Form fields now have proper `:focus-visible` outline** ([assets/css/forms.css](assets/css/forms.css)) — was bare `outline: none` on plain `:focus` (WCAG 2.4.7 critical fail; mouse clicks suppressed the indicator). Now: keep the border-color flourish for visual feedback, ADD a real outline only on `:focus-visible` (keyboard navigation).
2. **Global `:focus-visible` rule added** ([assets/css/base.css](assets/css/base.css)) — covers `a`, `button`, `[role="button"]`, `[role="link"]`, `input[type=submit|button|checkbox|radio]`, `.wp-block-button__link`, `summary`. Brand red (`var(--wp--preset--color--blood)`) outline with 3px offset, consistent across the theme. Replaces browser UA blue rings.
3. **Placeholder + Akismet notice color** `#999` → `#767676` ([assets/css/forms.css](assets/css/forms.css)) — was 2.85:1 contrast (fails WCAG 1.4.3 normal-text 4.5:1). Now 4.54:1 (passes).
4. **Form input borders** `#d9d9d9` → `#949494` ([assets/css/forms.css:72,177](assets/css/forms.css)) — was 1.39:1 (fails WCAG 1.4.11 non-text 3:1 for interactive UI components). Now 3.02:1 (passes). Surgical: only the input-element borders changed; `concrete` (`#d9d9d9`) stays the brand color for decorative separators where 3:1 doesn't apply.
5. **404 template heading hierarchy** ([templates/404.html](templates/404.html)) — the giant "404" digits were marked `<h1>` in concrete color (1.39:1 contrast, unreadable AND structurally wrong since they're decorative). "SIGNAL LOST" — the actual page identity — was an `<h2>`. Now: 404 digits are a decorative `<p aria-hidden="true">`, and SIGNAL LOST is promoted to `<h1>`. Fixes both WCAG 1.4.3 and 1.3.1.

### Notes

- **PATCH bump within `8.5.x`.** Patch headroom: 5/7 → **6/7 on 8.5.x**. Next minor still rolls to v9.0.0.
- The link-hover `#ff4c47` (3.4:1) finding was reviewed and accepted as-is — underline carries the affordance per WCAG 1.4.1 (color is not the only indicator); hover is transient. Could revisit if needed.
- Verified against actual WP Theme Handbook + WCAG 2.1 AA criteria. Visual treatment unchanged for sighted mouse users; keyboard + screen-reader experience materially improved.

## [8.5.5] - 2026-05-17

### Added
- **`add_theme_support('title-tag')` in [inc/setup.php](inc/setup.php).** Block themes do NOT auto-declare title-tag support — verified against [WordPress/wp-includes/theme.php on trunk](https://raw.githubusercontent.com/WordPress/WordPress/master/wp-includes/theme.php); no auto-declaration logic exists for block themes. Until now, The SEO Framework plugin was the only source of the `<title>` tag in `<head>`. With Phase 13 TSF cutover landing (companion plugin v2.0.0), WP core's `_wp_render_title_tag()` needs explicit theme support declared to take over title emission. Companion to plugin v2.0.0's `document_title_parts` filter, which controls the title format (still `Page Name — Site Name` matching what TSF emitted).

### Why this matters
- Without this declaration, deactivating TSF would leave the page with **no `<title>` tag at all**. That's an SEO catastrophe — title is one of the most-weighted on-page signals.
- The plugin's `document_title_parts` filter cooperates with WP-native title rendering rather than fighting it. Both pieces together produce the same brand format TSF was emitting, with zero user-visible change at cutover.

### Notes
- **PATCH bump within `8.5.x`.** From a user-visible perspective the page still has a `<title>` tag after this change — no new capability, no behavior shift. Pure infrastructure restoration of a capability TSF was previously providing externally.
- Cap headroom: 4/7 → **5/7 patches on 8.5.x**. Two patches of headroom remaining before next minor would roll to v9.0.0.
- Companion release: plugin v2.0.0 (MAJOR — TSF dependency dropped) shipping in the same session.

## [8.5.4] - 2026-05-16

### Fixed
- **`style.css` `Theme Name` header had a literal `&amp;` HTML entity** (`Theme Name: Signal &amp; Noise`) instead of a plain `&`. WP reads the header raw and renders it through its own escaping pipeline, so the entity got double-encoded to `&amp;amp;` and displayed in wp-admin Appearance → Themes as the literal text `Signal &amp; Noise`. Changed to plain ampersand: `Theme Name: Signal & Noise`.
- **`inc/wp-update-integration.php` `admin_init` version-change handler** now also calls `wp_clean_themes_cache()` on every detected version change. The parsed-theme-headers cache (set in `WP_Theme::get_data()` and friends) is invalidated automatically by WP's installer on `Update Now`, but the canonical SSH-checkout deploy path doesn't touch the installer — so the header cache went stale across each `gh workflow run` deploy. The watchdog mirrors the existing pattern for `sn_gh_latest_theme` + `update_themes` transient invalidation; same admin_init pageview, no new request overhead.

### Why this matters
- Theme name renders correctly in:
  - wp-admin → Appearance → Themes (the gallery + active-theme label)
  - wp-admin → Updates (when a theme update is available)
  - wp-admin → Plugins (cross-references to the theme by name)
  - Any third-party plugin's theme list (e.g., the desktop-mode dock submenu — the original visible-bug surface that surfaced this)
- Without the watchdog, every future SSH-checkout deploy that bumps theme version would leave the header cache stale until the next `wp_clean_themes_cache()` call (e.g., manual deactivation/reactivation). Now it self-heals on the next admin pageview.

### Notes
- **PATCH bump within `8.5.x`.** Bugfix to header metadata + cache watchdog; no functional behavior change.
- Cap headroom: 4/7 patches used on `8.5.x`; 3 remaining. Theme is at minor cap (5/5) — next minor rolls to **v9.0.0**.
- Companion fix shipped in plugin v1.15.1 (mirror watchdog for `wp_clean_plugins_cache()`).

## [8.5.3] - 2026-05-16

### Fixed
- **Theme update cache was too sticky.** Mirrors the plugin-side fix shipped in v1.11.1 — closes the asymmetry where the theme's `inc/wp-update-integration.php` still had the 12h TTL + no force-check support + no version-change cache invalidation, while the plugin had moved to a much more responsive model.
- **Three fixes** in `inc/wp-update-integration.php`:
  1. `sn_gh_latest_theme_tag()` gains an optional `$force_refresh` parameter that bypasses the cache.
  2. The `pre_set_site_transient_update_themes` filter callback now detects WP's force-check signals (`WP_FORCE_UPDATE_CHECK` constant OR `?force-check=1` query arg) and passes through to the new parameter. Clicking "Check Again" in `wp-admin/update-core.php` now actually re-fetches from GitHub.
  3. New `admin_init` hook stores the on-disk theme version in an option (`sn_last_seen_theme_version`). On every admin pageview, if the on-disk version differs from the stored last-seen, the GitHub-tag transient AND WP's own `update_themes` transient are cleared. This handles the upgrade-just-happened case automatically — whether the upgrade came via WP UI install or manual `workflow_dispatch` deploy.
- **Cache TTL reduced from 12 hours → 1 hour.** 12h was too long for "I just pushed a tag, where's my update?" Even with force-check working, the autonomous background poll cadence matters. 1h is responsive enough that pushed tags surface naturally within minutes-to-an-hour without any explicit user action.

### Behaviour
- Both probability lever (shorter TTL = cache misses oftener) and causality lever (version-change detection = cache MUST be wrong) are now in place together.
- No public-site emission change. No cross-package contract change.
- Docblock on `pre_set_site_transient_update_themes` filter clarified — theme transient uses arrays keyed by stylesheet, NOT stdClass objects keyed by basename like the plugin transient (subtle WP core quirk worth documenting).

### Notes
- **PATCH bump within `8.5.x`.** Bugfix in the update-detection path; no functional change to the theme's actual user-facing features.
- **Cap headroom:** 3/7 patches used on `8.5.x`; 4 remaining before minor rollover. Theme is already at minor cap (5/5) — next minor bump rolls to **v9.0.0**, not v8.6.0.
- Symmetry with plugin v1.11.1 was the right move: both repos now share identical cache-behavior code paths in their respective `wp-update-integration.php` files (modulo the theme/plugin transient shape difference).

## [8.5.2] - 2026-05-16

### Added
- `inc/wp-update-git-preservation.php` (200 LOC) — `.git`-preservation filter pair + admin_init self-recovery. Closes the footgun where clicking "Update Now" in wp-admin destroyed the theme's `.git` directory (via WP_Upgrader's recursive `clear_destination()`) and broke the canonical `gh workflow run deploy.yml` install path.

### How it works
- `upgrader_pre_install` (priority 10, accept_args=2) — atomically `rename()`s `.git/` → `wp-content/upgrade/sn-signal-and-noise-git-backup/` before WP's `clear_destination()` runs. Returns `WP_Error` to abort the install if the backup fails (better than silent .git destruction).
- WP runs its normal install (clear_destination + `upgrader_source_selection` rename of the unpacked archive dir → `move_dir`).
- `upgrader_post_install` (priority 10, accept_args=3) — atomically `rename()`s the backup back into the (now newly installed) destination dir. On WP-side install failure (WP_Error response), restores `.git` to the original theme dir so the rolled-back code keeps its checkout intact.
- `admin_init` self-recovery — on every admin pageview, if an orphaned backup is detected (post_install never fired — PHP timeout mid-install, fatal in another plugin's update hook, etc.), restore intelligently. Idempotent.

### Behaviour
- Both install paths now coexist. `gh workflow run deploy.yml --ref vX.Y.Z` stays the canonical/fast path; clicking "Update Now" in wp-admin no longer breaks the subsequent workflow_dispatch.
- Same-filesystem `rename()` is **atomic at the kernel level** — no window where `.git` exists in both places or neither. Cross-FS rename silently falls back to copy+delete (NOT atomic) — that's why the backup lives under `wp-content/upgrade/` (same mount as `wp-content/themes/` in standard WP installs incl. Cloudways).
- `inc/wp-update-integration.php` docblock updated to remove the "DO NOT CLICK UPDATE NOW" warning from v8.5.1 → both paths now safe.
- `functions.php` module map updated; `require_once` for the new file added below the existing wp-update-integration include.

### Verification
- WP core source re-fetched (`wp-admin/includes/class-wp-upgrader.php`) to confirm exact filter timing: `pre_install → source_selection → clear_destination → move_dir → post_install`. Pre_install can abort via WP_Error; post_install receives `$result['destination']`; `$hook_extra['theme']` stays populated through both.
- Mirrors plugin v1.10.1's `upgrader_source_selection` pattern from v8.5.1; adds the missing pre/post pair that the plugin also needs (queued as plugin v1.11.2).

### Notes
- This release ships via the canonical `gh workflow run deploy.yml --ref v8.5.2` (the new code is dormant on this install since workflow_dispatch is git-pull, not WP-installer). The filter pair activates only on the NEXT update if the maintainer chooses WP UI. After that first WP UI install, subsequent workflow_dispatch deploys should still succeed — confirming the footgun is closed.
- `error_log()` is used for restoration failures, not `WP_Error` — the WP install itself succeeded; a failed `.git` restore is post-hoc and shouldn't fail the install. The admin_init self-recovery retries on next pageview.

## [8.5.1] - 2026-05-16

### Changed
- `.github/workflows/deploy.yml` — trigger reduced from `push: tags: v*` to `workflow_dispatch:` only. Tag pushes no longer auto-deploy. Theme updates now land via the WP admin Updates page (the standard WordPress flow other site owners already use). Manual emergency-hotfix path: `gh workflow run deploy.yml --ref vX.Y.Z --repo juanlentino/signal-and-noise`.
- `inc/wp-update-integration.php` — replaced the `upgrader_pre_install` rejection (which blocked WP-driven installs because the legacy auto-deploy pipeline owned the .git checkout) with an `upgrader_source_selection` filter that renames GitHub's unpacked archive directory from `signal-and-noise-X.Y.Z/` to `signal-and-noise/` so WP installs to the active stylesheet slug.

### Behaviour
- After this tag is bootstrapped via one `gh workflow run`, future releases follow: edit code → bump `Version:` → CHANGELOG → tag → push tag → wait up to 12h for WP's cache to roll, or click "Check Again" in `wp-admin/update-core.php` → "Update Now". WP downloads the GitHub tag ZIP, the filter renames the unpacked dir, the new version overwrites the old one in place.
- No theme-side functional change. The cross-package contract surface (3 hooks documented in WORDPRESS-REFERENCE.md §10.0) is untouched.

### Notes
- Mirrors plugin v1.10.1's pattern exactly — same code shape, same filter pair, same emergency-hotfix workflow_dispatch fallback. Both repos now use WP-UI updates as the default install path.
- The first `gh workflow run` against v8.5.1 is a one-time bootstrap because pre-v8.5.1 the WP-side gate (the `upgrader_pre_install` WP_Error) would reject any install attempt. From v8.5.2 onward the WP UI flow works end-to-end.

## [8.5.0] - 2026-05-16

### Added
- `inc/wp-update-integration.php` — registers the theme with WordPress's native update system. Theme now appears in `wp-admin/update-core.php` and Appearance → Themes alongside other themes, showing current version and "up to date" status (or "update available" if auto-deploy ever falls behind a tag). ~120 LOC.

### Behaviour
- Polls GitHub Tags API every 12h (cached in `sn_gh_latest_theme` site transient). Picks the highest `v\d+\.\d+\.\d+` semver tag.
- Hooks `pre_set_site_transient_update_themes` to inject the theme into WP's update registry: into `->no_update` when local matches GitHub (the normal case under auto-deploy), into `->response` when GitHub is ahead.
- Hooks `upgrader_pre_install` to intercept "Update Now" with a WP_Error directing the maintainer to push a git tag instead — preserves the git checkout that auto-deploy depends on.

### Notes
- ~70 LOC of new code restores the user-facing visibility that was deleted in Phase 2b (`inc/updater.php` at 683 LOC) without bringing back the polling-heavy / self-heal / SHA-tracking machinery that auto-deploy made redundant.
- GitHub API is queried unauthenticated. 60 requests/hour limit per IP is plenty (cache TTL means 2 requests/day max). Graceful failure: empty cache for 1h on API error.

## [8.4.1] - 2026-05-16

### Fixed
- `style.css` Version field bumped to `8.4.1`. The v8.4.0 release shipped all the Phase 3 code changes correctly but the Version header in `style.css` was left at `8.3.0` due to an editor-tool sequencing error during the release commit. Cosmetic only — WP admin → Themes would show "Version 8.3.0" until this patch. No functional behavior depends on the field value.

## [8.4.0] - 2026-05-16

### Removed
- `inc/og-image.php` — moved to plugin `inc/og-card-generator.php`. Plugin generates OG cards via PHP GD; theme provides Bebas Neue + DM Mono TTFs through new `sn_og_font_paths` filter.
- `inc/reading-time.php` — moved to plugin `inc/reading-time.php`. Calculation + caching + `[sn_reading_time]` shortcode + `render_block` bridge all plugin-side.
- `inc/notes-and-provenance.php` (1,058 LOC) — moved to plugin and split into three smaller files: `inc/content-surfaces.php`, `inc/content-migrations.php`, `inc/content-rendering-helpers.php`.
- `inc/seed-content/` directory — moved to plugin alongside the migrations that consume it.

### Added
- `inc/og-fonts.php` — registers the theme's typefaces as the response to the plugin's `sn_og_font_paths` filter.

### Changed
- Cross-package contract surface grows from 2 hooks to 3 (added `sn_og_font_paths`).
- `docs/WORDPRESS-REFERENCE.md §10.0` updated to reflect the new contract.
- `functions.php` module-map docblock refreshed.

### Notes
- Requires plugin v1.3.0+ for full functionality. While plugin v1.2.0 is still active (during the ~30-60s deploy gap before plugin v1.3.0 ships), the `[sn_reading_time]` shortcode renders as the literal token string in any page that uses it (notably /provenance byline). Cosmetic, recoverable on next pageload after plugin v1.3.0 lands. Theme's `inc/page-notes-render.php` calls into reading-time via `function_exists()` guard — /notes index degrades gracefully (skips reading-time enrichment) rather than failing.

## [8.3.0] - 2026-05-15

### Removed
- `inc/updater.php` (~683 LOC) — GitHub-poll self-updater, obsolete since Cloudways auto-deploy (Phase 2a).
- `inc/template-self-heal.php` (~488 LOC) — file-drift recovery, redundant under atomic git-pull deploys.
- `inc/template-maintenance.php` — `upgrader_process_complete` hook + two `admin_init` detectors (version-change + template-mtime tracker), ~100 LOC.

### Added
- `.github/workflows/deploy.yml` — third step posts to `/wp-json/signal-noise/v1/purge-cache` after Cloudways `/git/pull` so theme deploys atomically refresh Cloudflare edge cache.

### Changed
- Cross-package contract surface shrinks from 7 hooks to 2. Updater filters (`sn_updater_branch`, `sn_updater_revcount`, `sn_updater_force_check`, `sn_updater_clear_error`) and the self-heal filter (`sn_self_heal_force_run_result`) are retired. Plugin v1.2.0 expects this and renders correctly.
- `docs/WORDPRESS-REFERENCE.md §10` updated to reflect the new contract surface (§10.1 + §10.2 marked retired).

## [8.2.1] — RSS Plausible Tracker migrated to companion plugin (early Phase 4 slice)

Brings the only Phase 4 file forward into the early-completion bucket, ahead of Phase 2's updater migration. The theme repo's `mu-plugins/` directory is now empty and deleted entirely. Tracking infrastructure (`wp_rss_feed_log` table, `sn_rss_tracker_*` options, `sn_rss_tracker_daily_prune` cron) lives in the [signal-and-noise-tools companion plugin v1.1.0](https://github.com/juanlentino/signal-and-noise-tools/releases/tag/v1.1.0) from this release onwards.

### Changed

- **Deleted `mu-plugins/` directory from the theme repo.** Contained `README.md`, `rss-plausible-tracker.php`, and `tests/bot-detection.php`. All three moved to the companion plugin (`inc/rss-plausible-tracker.php` + `tests/bot-detection.php`).
- **[`docs/WORDPRESS-REFERENCE.md`](docs/WORDPRESS-REFERENCE.md) §10.0:** updated to reflect Phase 4 partial completion. Phase 4 is now empty — the only file it was scheduled to migrate is in the plugin.
- **[`docs/WORDPRESS-REFERENCE.md`](docs/WORDPRESS-REFERENCE.md) §4, §5, §6, §7:** four `mu-plugins/rss-plausible-tracker.php` reference pointers updated to point at the new location in the companion plugin repo. §5's framing about "supports both install paths" rewritten to historical past tense.

### Coordinated plugin release

Ships alongside [signal-and-noise-tools v1.1.0](https://github.com/juanlentino/signal-and-noise-tools/releases/tag/v1.1.0). **No mandatory order** — the plugin's pre-flight guard #2 handles all scenarios:

- Plugin installed first, MU file still on server: plugin defers loading the rss tracker module to the MU file. Tracking continues uninterrupted.
- MU file deleted first, plugin not yet upgraded: tracking stops temporarily (data in `wp_rss_feed_log` is preserved). Resumes when plugin v1.1.0 lands.
- Both upgraded simultaneously (most likely scenario): guard sees MU file, defers. Then maintainer deletes MU file via SFTP. Next request, guard passes, plugin's module takes over.

### Migration steps for the maintainer

1. Click theme update in WP admin → installs v8.2.1 (deletes theme repo's `mu-plugins/` directory but does not touch the live server's `wp-content/mu-plugins/`).
2. Upload plugin v1.1.0 zip → activates new module loader.
3. Delete `wp-content/mu-plugins/rss-plausible-tracker.php` via SFTP (or `wp mu-plugin delete rss-plausible-tracker` via WP-CLI).
4. Next admin pageview → plugin's tracker module loads, admin notice clears.

### Why patch (not minor)

Structural file removal + docs updates. No new theme capability, no schema change, no breaking API change. The Phase 4 *milestone* completion is the plugin v1.1.0's minor bump; the theme's role is just cleanup. Patch bump.

### Spec

[docs/superpowers/specs/2026-05-15-rss-tracker-migration-design.md](docs/superpowers/specs/2026-05-15-rss-tracker-migration-design.md). Compact spec/plan combined since the scope is small.

## [8.2.0] — Phase 1 of theme + companion plugin split

First minor in the 8.x line. Nine modules (`seo.php`, `security-headers.php`, `cloudflare-purge.php`, `plausible-api.php`, `plausible-admin.php`, `plausible-widget.php`, `admin-bar.php`, `admin-page.php`, `rest-api.php`) moved out of `inc/` into the new companion plugin [`signal-and-noise-tools`](https://github.com/juanlentino/signal-and-noise-tools) `v1.0.0`. Cross-package coupling resolves via **7 WP hooks (5 filters, 2 actions)** — the theme registers the listener side; the plugin dispatches.

This is Phase 1 of a 4-phase split. Phase 2 will migrate the self-updater itself. See [docs/superpowers/specs/2026-05-15-companion-plugin-phase-1-design.md](docs/superpowers/specs/2026-05-15-companion-plugin-phase-1-design.md).

### Changed

- **[`functions.php`](functions.php) — 9 `require_once` lines removed.** Down from 20 to 11. Module-map docblock updated to reflect the reduced theme surface; companion plugin referenced.
- **[`inc/`](inc/) — 9 files deleted.** Files moved to companion plugin's `inc/` with same filenames preserved for parity.
- **[`inc/updater.php`](inc/updater.php) — 2 new functions + 4 hook listeners.** `sn_updater_force_check()` consolidates the cache-clearing sequence previously duplicated in `admin-page.php`, `admin-bar.php`, and `rest-api.php` (all of which moved to the plugin). `sn_updater_clear_error()` handles the lightweight error-dismiss path. Filter listeners on `sn_updater_branch` and `sn_updater_revcount` expose updater state to plugin code.
- **[`inc/template-maintenance.php`](inc/template-maintenance.php) — 2 filter listeners added.** Wrap existing `sn_purge_all_caches()` and `sn_clear_template_overrides()` for plugin dispatch.
- **[`inc/template-self-heal.php`](inc/template-self-heal.php) — filter listener added.** Wraps existing `sn_self_heal_force_run()` for plugin dispatch.

### Added (docs)

- **[`docs/WORDPRESS-REFERENCE.md`](docs/WORDPRESS-REFERENCE.md) §10.0** — new "Theme + companion plugin split" section documenting the contract surface (7 hooks: 5 filters + 2 actions), migration phases, and conventions for adding new cross-package interactions.
- **[`CLAUDE.md`](CLAUDE.md)** — companion plugin pointer added to the *Project* section.

### Contract surface (7 hooks)

| Hook | Type | Owner |
| --- | --- | --- |
| `sn_purge_all_caches_result` | filter | template-maintenance.php |
| `sn_clear_template_overrides_result` | filter | template-maintenance.php |
| `sn_self_heal_force_run_result` | filter | template-self-heal.php |
| `sn_updater_branch` | filter | updater.php |
| `sn_updater_revcount` | filter | updater.php |
| `sn_updater_force_check` | action | updater.php |
| `sn_updater_clear_error` | action | updater.php |

### Coordinated release

Ships with companion plugin `v1.0.0`. **Install order matters:**
1. Install + activate `signal-and-noise-tools` `v1.0.0` plugin first (download zip from `https://github.com/juanlentino/signal-and-noise-tools/archive/refs/tags/v1.0.0.zip`, WP admin → Plugins → Add New → Upload).
2. Click the theme's *Update* in WP admin to install `v8.2.0` (which removes the now-duplicate files).

During the brief window between steps 1 and 2, both packages have the 9 modules — WP registers hooks twice (duplicate admin menus, REST endpoints last-write-wins, dashboard widgets duplicated). The theme's menu entry continues to work; the plugin's menu shows but its purge/heal/check-updates buttons silently no-op until step 2 lands and registers the contract listeners. Maintainer should use the theme's menu entry during the window and ship the theme update promptly.

### Why minor

Meaningful capability shift — PHP includes shrink 45% (from 20 to 11), new contract surface introduced, theme becomes swappable in principle — but no breaking user-visible change. First minor in 8.x; well within the 5-per-major cap.

### Migration

None for end users; runtime behavior is identical after both releases land. For the maintainer: follow the install order above.

### Note on contract count vs spec

The spec ([2026-05-15-companion-plugin-phase-1-design.md](docs/superpowers/specs/2026-05-15-companion-plugin-phase-1-design.md)) anticipated 5 contracts. During execution, an audit grep of the moving files surfaced two additional cross-couplings (`sn_clear_template_overrides` and `sn_updater_revcount`) that the planning phase missed. Both are wired with the same contract pattern; the final count is 7. The spec is preserved as-is for historical accuracy.

### Spec + plan

- [docs/superpowers/specs/2026-05-15-companion-plugin-phase-1-design.md](docs/superpowers/specs/2026-05-15-companion-plugin-phase-1-design.md)
- [docs/superpowers/plans/2026-05-15-companion-plugin-phase-1.md](docs/superpowers/plans/2026-05-15-companion-plugin-phase-1.md)

Authored via the `superpowers:brainstorming` → `superpowers:writing-plans` → `superpowers:subagent-driven-development` skill chain.

## [8.1.1] — Handbook hygiene pass — strip i18n, refresh headers

Five mechanical hygiene items aligning the theme with the [WordPress Theme Developer Handbook](https://developer.wordpress.org/themes/) where it costs us little. The deliberate deviations (custom self-updater, external HTTP from theme code, business logic in `inc/`, `mu-plugins/` shipped from the theme repo) remain intentional and are NOT addressed here — they're documented in [docs/WORDPRESS-REFERENCE.md](docs/WORDPRESS-REFERENCE.md) §10 and accepted as the price of running a private single-site theme. The companion plugin split and inline-styles refactor are deferred to their own future phases.

### Changed

- **[`inc/setup.php`](inc/setup.php) — i18n bootstrap removed.** `load_theme_textdomain( 'signal-noise', ... )` and its docblock paragraph deleted. The function `signal_noise_after_setup_theme()` retains its `add_editor_style()` block. `Text Domain: signal-noise` in `style.css` kept as passive metadata.
- **[`inc/rest-api.php`](inc/rest-api.php) — 22 `__()` calls unwrapped.** All REST handler messages (`WP_Error` errors, `sn_rest_ok` success, sprintf placeholders) become plain string literals. JSON encoding is the rendering path; HTML escape was never applicable.
- **[`inc/patterns.php`](inc/patterns.php) — 2 `__()` calls unwrapped.** `register_block_pattern_category()` label + description become plain strings. The block editor's Patterns inserter now shows English directly.
- **[`inc/admin-page.php`](inc/admin-page.php) — 1 `esc_html__()` call unwrapped.** The permission-denied `wp_die()` message becomes `esc_html( '...' )` — escape preserved per original intent.
- **[`style.css`](style.css) — header updates.** Dropped stale `dark` tag (theme is white-first by design). Bumped `Tested up to: 6.8` → `6.9` (current WP is 6.9.4). Bumped `Version: 8.1.0` → `8.1.1`.
- **[`theme.json`](theme.json) — `$schema` bumped.** `https://schemas.wp.org/wp/6.7/theme.json` → `https://schemas.wp.org/wp/6.9/theme.json` for editor / IDE completion against current FSE schema.

### Why patch

All five items are mechanical changes to code or static metadata. No new user-visible capability, no schema migration, no breaking API change. First patch in the v8.1 line; well within the 7-per-minor cap.

### Migration

None required. Behavior is identical at runtime — string contents unchanged, function signatures unchanged, REST responses byte-identical (the `__()` calls already fell through to the source strings since no `.mo` file ever existed).

### Spec + plan

- [docs/superpowers/specs/2026-05-15-handbook-hygiene-pass-design.md](docs/superpowers/specs/2026-05-15-handbook-hygiene-pass-design.md)
- [docs/superpowers/plans/2026-05-15-handbook-hygiene-pass.md](docs/superpowers/plans/2026-05-15-handbook-hygiene-pass.md)

Authored via the `superpowers:brainstorming` → `superpowers:writing-plans` → `superpowers:executing-plans` skill chain.

## [8.1.0] — Notes subscribe info nested in hero (cap rollover from 8.0.7; not a new capability)

The v8.0.7 placement put the `<footer class="sn-notes-feed">` block in column 2 of the `.sn-notes-top` 5fr/7fr grid (because adding a third grid child to a 2-column grid placed it where the pillar essays section had been, displacing pillars to a second row). The visual result was co-equality with the hero — nothing read as the focal point. This release nests the subscribe info inside `<header class="sn-notes-hero">` as a single compact `<p>`, drops the standalone footer block, and lets the pillars section return to column 2 of the grid.

### Changed

- **[`inc/page-notes-render.php`](inc/page-notes-render.php) — markup.** Removed the `<footer class="sn-notes-feed">` block (was at the top of `.sn-notes-top` between hero and pillars after v8.0.7). Added `<p class="sn-notes-subscribe">` as the last child of `<header class="sn-notes-hero">`, with a `<span class="sn-notes-cursor">` blinking-cursor span at the sentence end. Single sentence: *"No subscription form. No schedule. Notes via RSS, or via email through Blogtrottr or Feedrabbit."* Three inline links (RSS internal, Blogtrottr + Feedrabbit external with `target="_blank" rel="noopener noreferrer"`).
- **[`inc/page-notes-render.php`](inc/page-notes-render.php) — CSS.** Removed the entire `.sn-notes-feed-*` rule block (`.sn-notes-feed`, `.sn-notes-feed-status`, `.sn-notes-feed-status a`, `.sn-notes-feed-status a:hover`, `.sn-notes-feed-cursor`, `.sn-notes-feed-note`, `.sn-notes-feed-note + .sn-notes-feed-note`, `.sn-notes-feed-note a`, `.sn-notes-feed-note a:hover`). Added `.sn-notes-subscribe`, `.sn-notes-subscribe a`, `.sn-notes-subscribe a:hover`, and `.sn-notes-cursor`. Renamed the selector inside `@media (prefers-reduced-motion: reduce)` from `.sn-notes-feed-cursor` to `.sn-notes-cursor`. The `@keyframes sn-blink` rule is preserved (referenced by the new cursor class).
- **Layout restored.** Pillar essays section now occupies column 2 of the desktop 5fr/7fr grid as it did in v8.0.6 and prior. The two-row layout introduced by v8.0.7 is gone.

### Why minor (cap rollover, not a new capability)

This change is patch-shaped — UX calibration, no new feature, no breaking API change, no migration. But v8.0.7 used the 7th and final patch slot in the v8.0 minor (per the project's 7-per-minor cap documented in [docs/VERSIONING.md](docs/VERSIONING.md)). The cap forces a roll to **v8.1.0**. Future-readers: the minor-digit bump reflects the cap rollover, not a new capability — read the `### Changed` section above for what actually shipped.

### Migration

None required. Placement-only change. Existing RSS subscribers unaffected. The `<footer class="sn-notes-feed">` element no longer exists in the rendered HTML; any external CSS or JS that selected it would break, but no external code does.

### Spec + plan

- [docs/superpowers/specs/2026-05-15-notes-subscribe-in-hero-design.md](docs/superpowers/specs/2026-05-15-notes-subscribe-in-hero-design.md) (supersedes the v8.0.7 spec which is preserved on disk with a SUPERSEDED banner).
- [docs/superpowers/plans/2026-05-15-notes-subscribe-in-hero.md](docs/superpowers/plans/2026-05-15-notes-subscribe-in-hero.md).

Authored via the `superpowers:brainstorming` (with visual companion) → `superpowers:writing-plans` → `superpowers:executing-plans` skill chain.

## [8.0.7] — Relocate /notes feed footer above the fold (move-and-replace)

The v8.0.6 email-via-RSS line landed in the right place semantically (`<footer class="sn-notes-feed">` at the bottom of `<main>`) but the wrong place practically — readers had to scroll past 7 note rows + 2 pillar essay cards before encountering the subscribe info. Functionally hidden for the first-impression case, which defeats the purpose of adding the line in the first place.

### Changed

- **[`inc/page-notes-render.php`](inc/page-notes-render.php) — `<footer class="sn-notes-feed">` block.** Relocated from its bottom-of-main position to immediately after the hero `<header>` (inside the `.sn-notes-top` wrapper, between hero and pillar essays section). Same markup, same CSS, same `aria-label`. The blinking cursor in `.sn-notes-feed-cursor` reads as "live feed status" at the top rather than "end of output" at the bottom — arguably more apt for a continuously-updating notes catalog.
- **[`inc/page-notes-render.php`](inc/page-notes-render.php) — `<hr class="sn-notes-rule">` removal.** Deleted the second `<hr>` (the one that previously preceded the bottom footer). The remaining `<hr>` between pillars and index is preserved — it still divides those two sections.

### Approaches considered + rejected

Documented in the design spec at [docs/superpowers/specs/2026-05-15-notes-feed-relocation-design.md](docs/superpowers/specs/2026-05-15-notes-feed-relocation-design.md):

- *Keep bottom + add compact top callout (redundancy)* — rejected; two design languages on one page.
- *New labeled "Subscribe" section between hero and pillars* — rejected as out of scope; would be most design-coherent with the catalog metaphor but adds a section the reader scrolls through before the pillar essays. Deferred for a future redesign pass if the simple relocation doesn't surface enough subscriptions.

### Not changed

- No CSS edits. Existing `.sn-notes-feed { margin-top/bottom: clamp(2rem, 4vw, 3rem) }` translates cleanly to the new context.
- No new copy, no new links, no new design tokens.
- `templates/page-notes.html` (FSE fallback) untouched — per [WORDPRESS-REFERENCE.md §10.4](docs/WORDPRESS-REFERENCE.md), it's deliberate incident-response infrastructure that's allowed to drift.
- `<footer>` element retained (vs. switching to `<aside>`). At HTML5-spec level, `<footer>` is not strictly position-bound; the markup-semantic shift to `<aside>` isn't worth a placement-only change.

### Why patch + cap note

Structural change to the live `/notes` renderer → patch bump per project rules. **Patch slot 7 of 7 in the v8.0 minor — the cap is now exhausted.** Any further bump in this branch rolls to `8.1.0`. Documented here so future-me doesn't try to ship `8.0.8`.

## [8.0.6] — Sync repo to live: drop Book a Call surface, add email-via-RSS hint to Notes footer

The live theme had drifted from this repo. The "Work With Me" Cal.com booking page was removed from production, the "Book a Call" nav link was pulled, and the strategy-call CTA was stripped from `/services` — but the repo still carried all of it. This release brings the repo in line with live, then adds one new line to the Notes-index footer pointing readers at email-by-RSS bridges (Blogtrottr, Feedrabbit) so the "no subscription form" line isn't a dead end for non-RSS-native subscribers.

### Drift removed

- **[`parts/header.html`](parts/header.html) — header nav.** Removed the "Book a Call" → `/work-with-me` `wp:navigation-link`. Live nav is now the canonical 7 items: Home, About, Services, Music, Resume, Notes, Contact.
- **[`templates/page-work-with-me.html`](templates/page-work-with-me.html) — deleted.** Cal.com booking page (tab bar + 30/60-minute embeds) is gone from production (`/work-with-me/` returns HTTP 404). The orphan template was the last theme-side reference; the page would re-spawn in the FSE template picker if left in place.
- **[`theme.json`](theme.json) — `customTemplates`.** Removed the `page-work-with-me` registration (was line 283). Without this, deleting the template file would leave WordPress trying to register a phantom custom template that has no source HTML — surfaces as a Site Editor template-picker entry that errors on selection.
- **[`templates/page-services.html`](templates/page-services.html) — closing CTA.** Removed the inline outline `wp:button` "Book a strategy call →" → `/work-with-me`. Closing CTA is now a single "Tell me about your project →" → `/contact` button, matching the live `/services/` page exactly.
- **[`patterns/cta-closing.php`](patterns/cta-closing.php) — deleted.** Two-button CTA pattern (`Tell me about your project →` + `Book a strategy call →` → `/work-with-me`). Pattern slug `signal-noise/cta-closing` was registered but not inserted by any template in the repo — orphan from the v7.5.x IA pass. Deleting the file removes it from the block inserter so the booking CTA can't be re-introduced by accident.
- **[`templates/home.html`](templates/home.html) — dead RSS-footer block.** Stripped the `<!-- RSS FOOTER -->` separator + spacer + `<p class="sn-notes-rss">` block. The `/notes` URL is rendered by [`inc/page-notes-render.php`](inc/page-notes-render.php) via a `template_include` short-circuit; the FSE template's RSS footer never fires. Cleanup, not a behavior change.

### Added

- **[`inc/page-notes-render.php`](inc/page-notes-render.php) — Notes footer second line.** Added `<p class="sn-notes-feed-note">For email, pipe the <a href="/notes/feed/">feed</a> through <a href="https://blogtrottr.com/">Blogtrottr</a> or <a href="https://www.feedrabbit.com/">Feedrabbit</a>.</p>` directly below the existing "No subscription form. No schedule." line. External links use `target="_blank" rel="noopener noreferrer"`. Closes the gap where readers who want email subscriptions had no path forward.
- **[`inc/page-notes-render.php`](inc/page-notes-render.php) — `.sn-notes-feed-note a` styles.** Mirrors the `.sn-notes-feed-status a` pattern (blood-red, no underline, hover slides in a 1px bottom border). Also added `.sn-notes-feed-note + .sn-notes-feed-note { margin-top: 0.4rem }` so the two adjacent footer lines don't touch.

### Why patch (not content-only)

Originally scoped as a content-only edit (one line added to the Notes footer). The audit revealed the live site had also dropped the booking surface entirely — nav link, page template, services-page CTA, and the orphan pattern. Per project versioning rules ([`docs/VERSIONING.md`](docs/VERSIONING.md)), structural template changes (deleted templates, deleted pattern, removed nav/button blocks) and CSS additions all bump version. So this ships as a patch even though the user-visible behavior change is minimal. Patch 6 in v8.0; within the 7-per-minor cap.

### Migration notes

None required. The `/work-with-me/` URL has been 404 in production for some time — this release just removes the stale theme-side surface. Existing Notes RSS subscribers are unaffected; the footer addition is additive. WP self-updater will offer the bump within ~30 seconds of push (per the v8.0.5 latency tighten).

## [8.0.5] — Tighten auto-surface latency from up-to-5-min to up-to-30-sec

The v8.0.1 auto-surface fix restored the "push → updater shows the offer" pipeline that had been broken since `fbd6b30`, but the perceived latency was still up to 5 minutes — long enough that an actively-iterating maintainer notices and complains. Reducing the freshness window collapses that gap.

### Where the latency came from

The admin_init warmer in [inc/updater.php](inc/updater.php) gates the background refresh on `SN_UPDATER_FRESHNESS` (was 5 min). Until the cache aged out, every admin pageview skipped scheduling a new fetch — even when the maintainer had just pushed. 5 minutes was a leftover from the pre-SWR architecture where the cache served the page-render path directly; a long TTL made sense there. With the SWR refactor in v7.3.1, the cache is read-only on the render path and refreshed in a non-blocking spawn_cron loopback. The freshness gate is now purely a soft rate-limit on outbound GitHub calls, not a render-latency knob.

### Changed
- **[`inc/updater.php`](inc/updater.php) — `SN_UPDATER_FRESHNESS`** reduced from `5 * MINUTE_IN_SECONDS` to `30` (seconds). Auto-surface latency goes from "up to 5 min" to "up to 30 sec." Comment block now documents the rationale for the chosen number.
- **[`inc/updater.php`](inc/updater.php) — `SN_UPDATER_RETENTION_SHORT`** reduced from `15 * MINUTE_IN_SECONDS` to `2 * MINUTE_IN_SECONDS`. A transient GitHub blip that lands an empty-sentinel in the cache should not lock auto-surface out of the next 15 minutes; 2 minutes is a more proportionate cooldown.

### GitHub API cost
Worst case during active admin browsing: ~120 calls/hour (one per pageview at the 30s floor). Token budget is 5000/hour, so ~2.4% utilisation in the busiest scenario. In normal use the loopback fires far less often.

### Why patch
Constant tweak. No functional change beyond timing. No schema change, no API change. Patch 5 in v8.0; within the 7-per-minor cap.

## [8.0.4] — Proper fix for the Gutenberg social-link relative-URL bug

v8.0.3 worked around the bug by hardcoding the full URL (`https://juanlentino.com/notes/feed/`) in the `wp:social-link` block. That fixed the symptom but coupled the template to a specific host — any future dev/staging environment would render a link pointing at production. This release replaces that hack with the structural fix.

### The upstream bug, exact source

WordPress core's `render_block_core_social_link()` in `wp-includes/blocks/social-link.php`:

```php
/**
 * Prepend URL with https:// if it doesn't appear to contain a scheme
 * and it's not a relative link or a fragment.
 */
if ( ! parse_url( $url, PHP_URL_SCHEME ) && ! str_starts_with( $url, '//' ) && ! str_starts_with( $url, '#' ) ) {
    $url = 'https://' . $url;
}
```

The comment says "not a relative link" but the check only recognises **two** flavors: protocol-relative (`//`) and fragment (`#`). Path-relative URLs (`/foo`) — which ARE relative links per RFC 3986 — fall through and get `https://` prepended, producing `https:///foo` (three slashes, empty host). Chrome silently normalises the result on click as `https://foo/...`, routing to a non-existent server. The check is missing a `! str_starts_with( $url, '/' )` branch.

### The fix in this release

A `render_block_data` filter in [inc/frontend-filters.php](inc/frontend-filters.php) intercepts every `core/social-link` block before WP core's render runs. If the block's `url` attribute starts with a single `/` (path-relative), it gets swapped for `home_url($path)` — which carries the correct scheme + host for whatever environment WordPress is running in. WP core then sees a complete URL with scheme and skips its broken prepend branch entirely.

```php
add_filter( 'render_block_data', function( $parsed_block ) {
    if ( 'core/social-link' !== ( $parsed_block['blockName'] ?? '' ) ) {
        return $parsed_block;
    }
    $url = $parsed_block['attrs']['url'] ?? '';
    if ( '' !== $url && '/' === $url[0] && ( ! isset( $url[1] ) || '/' !== $url[1] ) ) {
        $parsed_block['attrs']['url'] = home_url( $url );
    }
    return $parsed_block;
} );
```

### Why this is the right shape

- **No host hardcoded anywhere.** `home_url()` returns whatever the site is configured to be, so the same template renders correctly on dev, staging, and prod.
- **Catches every social-link, not just this one.** Any future `wp:social-link` block with a relative URL (Mastodon at `/mastodon/`, GitHub at `/code/`, whatever) gets the same correction. The trap can't be re-introduced via the template.
- **No-op when core is fixed.** The day WP core adds the missing `! str_starts_with($url, '/')` branch, this filter becomes redundant but harmless — the URL already has a scheme via `home_url()` so core's check passes either way. Comment in the filter documents this so a future maintainer knows when to remove it.
- **Doesn't touch the upstream code.** No monkey-patching `wp-includes/`, no wp-content/mu-plugins/ load-order risk, no override of a core function. Just a vanilla WP filter.

### Changed
- **[`inc/frontend-filters.php`](inc/frontend-filters.php) — new `render_block_data` filter** for `core/social-link` blocks. ~15 lines + a 16-line docblock. Sits alongside the existing skip-link / Spotify-embed / generator-stripping filters in the same file.
- **[`parts/footer.html`](parts/footer.html) — `wp:social-link` `url` attr** reverted from `https://juanlentino.com/notes/feed/` (the v8.0.3 hack) back to `/notes/feed/`. The inline comment now points at the filter so the relationship is discoverable from either direction.

### Upstream
No core ticket filed yet. File one at https://core.trac.wordpress.org/ if you touch this again — the fix in core would be a one-line addition (`! str_starts_with( $url, '/' )`) to the existing scheme check, after which this filter could be retired.

### Why patch
Same fix, better implementation. No new capability, no schema change, no user-visible difference vs v8.0.3 *except* that the template is now host-agnostic. Patch 4 in v8.0; within the 7-per-minor cap.

## [8.0.3] — Footer RSS link uses absolute URL (works around Gutenberg core bug)

The v8.0.0–v8.0.2 footer used a relative URL (`/feed/` then `/notes/feed/`) in the `wp:social-link` block's `url` attribute. WordPress core's `block_core_social_link_render()` callback in `wp-includes/blocks/social-link.php` does this:

```php
if ( $url ) {
    $url = esc_url( $url );
    if ( ! parse_url( $url, PHP_URL_SCHEME ) ) {
        $url = 'https://' . $url;
    }
}
```

The scheme check returns null for any path-relative URL, so core prefixes `https://`. Result for `/notes/feed/`: `https:///notes/feed/` — three slashes, empty host. Chrome silently normalizes this on hover/click to `https://notes/feed/` (treats "notes" as the hostname), routing the user to a non-existent server.

The same bug affected v8.0.0 with `/feed/` (rendered as `https:///feed/` → `https://feed/`); it just took someone hovering over the icon for the maintainer to notice. Caught from a screenshot showing the status bar.

### Changed
- **[`parts/footer.html`](parts/footer.html) — `wp:social-link` `url` attr.** `"/notes/feed/"` → `"https://juanlentino.com/notes/feed/"`. Hardcoding the host is acceptable here because this is a single-site theme — the host never moves. Inline comment in the template now documents the core-bug constraint so this trap doesn't get re-introduced.

### Why patch
URL string correction for a previously-broken link. No behavioural change beyond "the link now goes to the correct URL." Patch 3 in v8.0; well within the 7-per-minor cap.

## [8.0.2] — Footer RSS link points at /notes/feed/ (not /feed/)

The global footer's RSS icon was pointing at the site-wide WordPress feed (`/feed/`) when the canonical subscription surface for this site is the Notes feed specifically (`/notes/feed/`). The bottom of `templates/page-notes.html` already linked at `/notes/feed/`; this aligns the global footer with that existing pattern so both surfaces point readers at the same feed.

### Changed
- **[`parts/footer.html`](parts/footer.html) — `wp:social-link` `url` attr.** `/feed/` → `/notes/feed/`. One-attribute change in the existing Gutenberg core social-link block.

### Subscriber-tracking impact
None. The MU plugin's `template_redirect` hook fires on `is_feed()` regardless of feed slug, so requests to `/notes/feed/` are tracked the same way `/feed/` was — same `wp_rss_feed_log` row, same Plausible event. The `feed_url` column already captured the full URL per request, so the Plausible URL breakdown will simply show `/notes/feed/` as the dominant feed going forward instead of `/feed/`.

### Why patch
URL string correction. No behavioural change, no schema change, no API change. Patch 2 in v8.0; within the 7-per-minor cap.

## [8.0.1] — Restore auto-surface for theme updates after push to main

Fixes a regression introduced by [`fbd6b30`](https://github.com/juanlentino/signal-and-noise/commit/fbd6b30) ("Fix Updates page showing 'no updates' due to transient nuke") several minor versions ago. That commit fixed a real bug — `load-update-core.php` was clearing WP-Core's `update_themes` site transient mid-render, causing `list_theme_updates()` to read empty and falsely report "all up to date" — but its narrower gate (`if ( empty( $_GET['force-check'] ) ) return;`) removed a side effect the previous bug had been accidentally providing: every admin pageview was force-invalidating WP's update_themes transient, which in turn forced WP to re-run our `pre_set_site_transient_update_themes` filter against the fresh SN GitHub-cache. That side effect was what made pushes appear in the updater within ~5 minutes without any manual "Check Again" click.

Symptom of the regression: after pushing a new commit to `main`, the SN cache picks up the new SHA within 5 min (per the admin_init warmer + spawn_cron loopback), but WP-Core's `update_themes` site transient is gated by its own 7200-second freshness window in `_maybe_update_themes()`. During that window WP doesn't re-run our filter, so the fresh SHA goes nowhere visible. Maintainer experience: "I just pushed v8.0.0 and the updater doesn't see it."

### Changed
- **[`inc/updater.php`](inc/updater.php) — `sn_updater_refresh_cache()`.** Capture the previously-cached SHA before overwriting; after the new fetch lands, if the SHA actually moved, call `delete_site_transient('update_themes')` to force WP to re-evaluate the offer on the next admin pageview. Five-line addition. Safe to do here because this function runs in a `spawn_cron()` loopback context, not during a page render — the original `fbd6b30` race (clearing the transient mid-render of `update-core.php`) is not reachable from this code path.

### Why this is a patch (not a minor)
Bug fix in existing behavior. No new user-visible capability, no schema change, no API change. First patch in v8.0; well within the 7-per-minor cap.

### One-time activation step
Because this fix has to be present in the installed code for it to work, the very first deploy after this commit still requires a manual `?force-check=1` click — the broken state can't surface its own fix. After that one click → click Update → install 8.0.1, subsequent pushes auto-surface within ~5 minutes again.

## [8.0.0] — Site-wide RSS surfacing + server-side subscriber tracking + admin settings tab

RSS was previously only linked from a hairline footer on `/notes`. This release surfaces it on every page, adds a self-hosted-Plausible-backed measurement layer (no Jetpack, no FeedBlitz, no third-party tracker), and exposes the whole subsystem through a new **Appearance → Signal & Noise → RSS** settings tab. The measurement table is local to the database so a Plausible outage doesn't blank the trend data.

### Added
- **[`parts/footer.html`](parts/footer.html) — RSS subscribe link in the global footer.** New `<!-- wp:social-link {"url":"/feed/","service":"feed","label":"Subscribe via RSS"} /-->` inside the existing social-links list. Uses Gutenberg core's built-in `feed` service, which renders an inline SVG identical in weight to the other social glyphs and gets `aria-label`-equivalent semantics from a screen-reader-text span. Visible on homepage, `/provenance`, `/resume`, `/notes`, individual posts — everywhere `parts/footer.html` is included. URL hardcoded as `/feed/` because FSE template parts are pure HTML and don't execute PHP; same pattern `page-notes.html` already uses.
- **[`mu-plugins/rss-plausible-tracker.php`](mu-plugins/rss-plausible-tracker.php) — server-side feed-request tracker.** 482 lines, single self-contained file. Hooks `template_redirect` at priority 1, gates on `is_feed()`, drops requests whose User-Agent matches the bot regex (Googlebot/Bingbot/preview-card bots/curl/wget/uptime monitors — **but never aggregators**; see "Settings tab" below). For surviving requests: (1) inserts a row into the new `wp_rss_feed_log` table with UTC timestamp, first 16 hex chars of `sha256(UA)`, and the feed URL; (2) fires a fire-and-forget POST to the configured Plausible event endpoint with event name, full feed URL, and the `ua_hash` as a custom prop. Non-blocking + 2-second connect timeout so analytics never delays the feed response. Forwards the original `User-Agent` and Cloudflare's `CF-Connecting-IP` (with `X-Forwarded-For` / `REMOTE_ADDR` fallbacks) so Plausible's own bot detection and geo lookup function correctly.
- **`wp_rss_feed_log` table.** Columns: `id BIGINT PK`, `ts DATETIME`, `ua_hash CHAR(16)`, `feed_url VARCHAR(255)`. Index on `ts` for the rolling-window queries. Created via `dbDelta` on a version-gated `admin_init` hook (MU plugins have no activation hook, so we install lazily and idempotently — at most one option read per admin pageview).
- **`sn_rss_tracker_settings` option + new admin tab — Appearance → Signal & Noise → RSS.** Hosts everything operational about the tracker: enable/disable toggle, Plausible event endpoint URL, Plausible site domain, custom event name, and log retention window (7–365 days). All form-edited per host, no code changes needed when the Plausible install moves or the event name changes. The tab also renders three activity cards (24h / 7d / 30d, each showing total + unique clients), a 20-row recent-requests table, "Open in Plausible" deep link, and a maintenance section for purging old log entries. Form submissions flow through `admin_init` with nonce + `manage_options` capability gates, then redirect with a flash query arg.
- **Updated dashboard widget — "RSS Subscribers (30 days)".** Still surfaces the headline 30-day count and unique-client figure for at-a-glance visibility on the WP dashboard, now with a "Settings & activity" link to the new RSS tab and a Plausible deep link built from the configured domain + event name (not the hardcoded values it used to embed).
- **[`mu-plugins/tests/bot-detection.php`](mu-plugins/tests/bot-detection.php) — standalone fixture test.** 33 fixtures covering real aggregator UAs (Feedly, NewsBlur, Inoreader, NetNewsWire, Reeder, Tiny Tiny RSS, Miniflux, FreshRSS, BazQux, The Old Reader), three modern browsers, and 17 crawlers / monitors / CLIs that should be filtered. Runnable with bare `php mu-plugins/tests/bot-detection.php` — no PHPUnit, no WordPress, no composer. Exits non-zero on any failure. Includes regression coverage for the Feedly filter bug (see below).
- **[`mu-plugins/README.md`](mu-plugins/README.md) — deployment note.** Documents the one-time copy step on Cloudways: MU plugins must live at `wp-content/mu-plugins/`, not inside the theme.
- **[`inc/admin-page.php`](inc/admin-page.php) — RSS tab registration.** Added `rss` to `$valid_tabs` and `$tab_labels`, plus a new dispatch branch that fires `do_action('sn_admin_rss_tab')`. Includes a `has_action()` fallback that renders an install-hint notice when the MU plugin file isn't deployed to the host — turns the empty-tab confusion mode into self-service guidance.

### Revised before commit (brainstorming pass)
A retroactive design review caught three issues in my first-pass implementation. All fixed in this release:

1. **Bot regex silently filtered Feedly + NewsBlur** *(critical)*. The first pass included `fetch` as a substring catch-all in the bot regex. Feedly's UA is `Feedly/1.0 (+http://www.feedly.com/fetcher.html; like FeedFetcher-Google)` — "fetch" matches inside "fetcher", so Feedly's poller (the largest aggregator by subscriber share) would have been silently dropped from the count. Same trap caught NewsBlur ("Page Fetcher") and Tiny Tiny RSS ("feed-fetcher.html"). Fixed by removing the `fetch` substring entirely and anchoring `curl\/` and `wget\/` to their canonical UA prefix. Regression test added.
2. **Footer was over-engineered** *(simplification)*. The first pass used `wp:html` with a custom inline SVG plus 30 lines of bespoke `.sn-footer-rss-*` CSS. Switched to `<!-- wp:social-link {"service":"feed"} /-->` which is built into Gutenberg core — same visual weight, same 44×44 touch target, same hover color, zero new CSS. Net delta: footer markup went from 14 lines to 1 line, layout.css shed 30 lines.
3. **`fetch`/`monitor` substring traps** *(precision)*. `monitor` was a broad substring catch that overlapped the explicit `uptimerobot|pingdom|statuscake` terms. Dropped and replaced with `sitelock`; the explicit names cover the actual surface.

### Design decisions
- **MU plugin, not theme `inc/`.** Subscriber metrics should survive theme switches. The tracker is fully self-contained — no shared functions with `inc/plausible-api.php`. Theme integration (the settings tab) is a one-way hook into the theme's tab dispatch; the tracker functions even with the theme disabled, it just loses its UI surface.
- **Local DB table is primary, Plausible is fan-out.** The widget and the activity tab read from `wp_rss_feed_log`. If Plausible is unreachable when a feed hit lands, the row still gets logged and the metric still shows — never gated on an external service being up.
- **UTC throughout.** Rows are inserted with `current_time('mysql', true)` (UTC). Window queries use `UTC_TIMESTAMP() - INTERVAL %d DAY` rather than `NOW()` because MySQL's `NOW()` returns server-local time, which on Cloudways isn't guaranteed UTC and would silently slide the window.
- **Hashed UA, no IP storage.** Stored fingerprint is `substr(sha256(UA), 0, 16)` — enough collision space for rough unique-client counting, zero PII surface in the table. Client IP is forwarded to Plausible at request time (so its geo lookup works) but never persisted locally.
- **Bot regex is conservative on bots, generous on aggregators.** The pattern lists specific tool names (Googlebot, Bingbot, AhrefsBot, curl/, wget/) instead of broad substrings. Decision-rule: when in doubt, count it. False negatives (crawler noise) are easier to detect in the data than false positives (silently-dropped real subscribers).
- **Settings exposed, regex hardcoded.** Plausible URL, domain, event name, retention threshold, and the enable/disable toggle are option-backed and form-edited. The bot regex stays code-only — a bad regex from a UI input could break all tracking with no safe form-submit validation.
- **No header RSS icon.** Spec made it conditional on existing social links in the header; there are none. Header is logo + 8-item nav, already dense at desktop. Adding a ninth element would have caused mobile-overlay regressions for no discoverability gain over the global footer.

### Operational notes
- **Aggregator caveat.** Feedly / Inoreader / NetNewsWire-cloud-sync etc. poll feeds server-side and serve cached versions to their users. The metric reflects feed-fetch events, not precise unique human subscribers. Treat as a trend indicator.
- **Privacy policy follow-up (TODO).** The `wp_rss_feed_log` table stores hashed User-Agent strings. Not strict PII under GDPR but plausibly an "online identifier" for EU readers. Add a one-sentence mention to the site privacy policy — out of scope for this release.
- **Plausible CE endpoint.** No API key required for `/api/event` POSTs (same endpoint the client-side script uses). Authentication only matters for the Stats API.
- **CSP exemption.** Cloudflare CSP Transform Rules govern browser-side script/connect-src; server-side `wp_remote_post` from PHP is outside CSP's scope. No CSP changes needed.

### Why MAJOR (cap rollover, 8.0.0)
The *change kind* is MINOR — site-wide RSS surfacing + a new admin tab + net-new infrastructure (DB table, MU plugin, settings option, dashboard widget). No removed/renamed API, no schema change without migration (new table and new option are additive — defaults via `wp_parse_args`), no behavioural shift that requires action to preserve existing functionality. Existing 7.5.6 sites continue to work unchanged after the theme upgrade; the MU-plugin copy step *enables* the new tracker, it doesn't repair anything.

However, the project's minor cap fires: `7.0`–`7.5` are valid minors in v7; the next minor digit would be `7.6`, which exceeds the cap of 5. Per the documented rule (`docs/VERSIONING.md`, mirrored in [CLAUDE.md](CLAUDE.md)), the cap rollover lands on **`8.0.0`**.

Precedent: `6.0.0` did the same — a modularisation release that wasn't API-breaking but rolled the major digit when the minor cap of v5 was exhausted. This release matches that pattern, with an even stronger case (whole new MU plugin + admin surface + DB schema) for the larger version digit.

The version digit is the cadence here, not a breaking-change signal. See "Design decisions" above for the substance.

### Deployment checklist
- [ ] Push to repo, deploy theme to Cloudways
- [ ] Copy `mu-plugins/rss-plausible-tracker.php` → `wp-content/mu-plugins/rss-plausible-tracker.php` on syntharchy-wp (no admin activation needed)
- [ ] Visit `/wp-admin/` once to trigger `dbDelta` and create `wp_rss_feed_log`
- [ ] Visit **Appearance → Signal & Noise → RSS** to confirm the tab renders and defaults look right
- [ ] Hit `/feed/` from a real browser; confirm Plausible dashboard shows the `RSS Feed Request` event and `wp_rss_feed_log` has a corresponding row
- [ ] Confirm dashboard widget renders the count
- [ ] (Optional) Run `php mu-plugins/tests/bot-detection.php` on the host — should print 33 passes, exit 0

## [7.5.6] — Voice rewrites for Operations / Artist Development / Resume cred-strip

Three targeted prose changes calibrated against the [`docs/VOICE-GUIDE.md`](docs/VOICE-GUIDE.md) anchor (Apple-coded register, sister-blurb pattern, no SaaS register, no consultant bridge-framing). The remaining audit §G items judged against the voice guide:

- **G2** (Services intro *"deliberate, thorough, and built to last"*) — kept as-is. The three-adjective stack is exactly Apple's signature ("Beautiful. Powerful. Fast."). On-register Mode 1.
- **G3** (Services CTA h2 *"LET'S TALK ABOUT YOUR PROJECT"*) — kept as-is. Mode 1 imperative, defensible.
- **G4** (Operations & AI Strategy blurb) — rewritten. *"Build systems that scale"* was peak SaaS-coded.
- **A6** (Artist & Producer Development blurb) — rewritten. *"Connect creative identity to commercial opportunity"* was SaaS bridge-framing.
- **Resume cred-strip dedup** — restructured per audit recommendation.

### Changed
- **[`templates/page-services.html`](templates/page-services.html) — OPERATIONS & AI STRATEGY blurb (line 220).**
  - Before: *"Sustainable business models, streamlined operations, and AI-assisted workflows that actually work. I help studios, labels, and creative companies build systems that scale — grounded in a decade of running my own studio and an MBA in Applied AI."*
  - After: *"I help studios, labels, and creative companies operate without breaking — pricing, daily operations, AI workflows that earn their keep. Built on a decade running Panacea and an MBA in Applied AI."*
  - Sister-blurb voice (matches PRODUCTION / MIXING / SONGWRITING / MASTERING's *"I + verb"* opening). The phrase *"operate without breaking"* replaces *"build systems that scale"* — same payload, register-shifted from SaaS to Juan: specific verb-phrase no consultant would write because it implies the consultant's product breaks. Studio name *"Panacea"* surfaced explicitly (the existing About page links it; calling it out here gives the credentials more weight).

- **[`templates/page-services.html`](templates/page-services.html) — ARTIST & PRODUCER DEVELOPMENT blurb (line 240).**
  - Before: *"Long-term roadmaps that connect creative identity to commercial opportunity. Brand positioning, release strategy, sonic direction, and one-on-one mentorship for artists and producers ready to turn talent into a career."*
  - After: *"Long-term roadmaps for artists and producers ready to turn talent into a career. Brand positioning, release strategy, sonic direction, one-on-one mentorship — without losing the thread of what made them worth listening to."*
  - The audience-first opening replaces the SaaS bridge-frame *"connect creative identity to commercial opportunity."* The em-dash close *"without losing the thread of what made them worth listening to"* is a Juan-coded line — concrete, lived-in, the kind of sentence no consultant would write.

- **[`templates/page-resume.html`](templates/page-resume.html) — meta strip (line 23).**
  - Before: `20+ Years · 50+ Collaborations · GRAMMY Voting Member`
  - After: `Production · Strategy · Mentorship`
  - The previous strip duplicated stats already asserted in the prose paragraph three lines above. The audit recommended replacing the redundant stats with discipline-framing if the strip stays. *"Production · Strategy · Mentorship"* maps to the three actual offerings on the Services page (the production cluster, Operations & AI, Artist & Producer Development). The strip now adds positioning instead of repeating numbers — voice guide rule: when the same fact appears twice, the second occurrence should add information the first doesn't.

### Why patch (7.5.6)
Three surgical voice edits, all judged against the voice guide. No new functionality, no IA changes, no schema changes. Patch 6 of 7.5.

### Audit closure
This release closes the actionable §G items from [docs/CONTENT-AUDIT.md](docs/CONTENT-AUDIT.md). Remaining: nothing voice-affecting awaits maintainer review. Future audits should measure against [docs/VOICE-GUIDE.md](docs/VOICE-GUIDE.md), not the brutalist anchor that produced the v7.5.3 round-trip.

## [7.5.5] — Restore Apple-register copy from v7.5.2 / v7.5.1

Two small reverts of changes that shifted phrases out of the canonical Apple-coded register. The maintainer's stated voice intent for the site is **Apple-like** — declarative fragments, list-of-three constructions with thematic glue, abstract verb-phrases like *"engineered for"* / *"crafted to"* / *"made with intention"*, and verbless or implied-verb subtitles. The [R3 content audit](docs/CONTENT-AUDIT.md) anchored on the brutalist passages elsewhere on the site and graded Apple-coded phrases as drift; this release walks back the two changes that landed under that misreading.

The audit's findings on factual consistency, IA labelling, redundancy mapping, and prose cleanups remain valid — those are register-neutral. The §G voice-rewrite drafts and a handful of A-tier "voice drift" calls were anchored on the wrong reference voice; this release backs out the two that already shipped.

### Reverted
- **[`templates/page-services.html`](templates/page-services.html) — PRODUCTION blurb closer.** *"every decision made to serve the song"* → *"every decision made with intention"*. The original is in the Apple verb-phrase register (parallel to *"designed for"*, *"engineered for"*, *"crafted to"*); the v7.5.2 replacement was brutalist/specific. Both work; the original is on-brand. Reverts audit finding A4.
- **[`templates/page-services.html`](templates/page-services.html) — closing CTA body.** *"Two paths in: send a message if you're scoping things out, or book a paid session if you want focused time on the calendar."* → *"Tell me what you're working on."* (the original closer to the *"Whether it's a record, a business problem, or a workflow that needs fixing — I'd rather hear about it than guess."* sentence). The two-button structure introduced in v7.5.1 stays — the buttons themselves name the paths, the body doesn't have to. Procedural "Two paths in:" was a brutalist tic; the original closer is Apple-register declarative.
- **[`patterns/cta-closing.php`](patterns/cta-closing.php) — same body-copy revert** so the pattern matches the inline Services version. Two-button structure (the actual IA fix) preserved.

### Voice anchor going forward
A new [`docs/VOICE-GUIDE.md`](docs/VOICE-GUIDE.md) (committed separately, no version bump) codifies the Apple-coded register as canonical so future audits and rewrites measure against the right reference. The brutalist passages on About / 404 / Contact / parts of Music are not the canonical voice — they're context-adapted moves *within* the Apple voice palette (procedural copy, personal-narrative copy, branded-error-page copy). The hero / abstract / value-prop register is the anchor.

### Why patch (7.5.5)
Two surgical reverts. No new functionality, no IA change, no schema change. Patch 5 of 7.5.

## [7.5.4] — Revert v7.5.3 front-page subtitle change

Restoring *"Music production, creative strategy, and the systems that hold them together."* — the original front-page hero subtitle that v7.5.3 replaced based on [docs/CONTENT-AUDIT.md](docs/CONTENT-AUDIT.md) §G1 Draft C.

### Why revert

The audit graded the original line against the brutalist register used on `/about`, `/contact`, `/404`, and the Notes / Music intros — and concluded it was the most consultant-coded line on the site. That grading is correct *within the audit's framing*. But the framing assumes a single voice across every surface.

The original line is in the **Apple-style hero register** — a list-of-three with the third item functioning as connective tissue (*"systems that hold them together"*). That structure is deliberate front-page copy, not voice drift. The maintainer's authorial intent is a register split: polished/abstract on the front-page hero, brutalist/specific on interior pages. Both registers can coexist; the front page is the shop window.

The v7.5.3 replacement (*"20+ years on the production side. Now also on the business side. Same ear, different console."*) is a fine line in the brutalist register — it just doesn't belong on the front-page hero, by the maintainer's editorial judgment. Restoring the original.

### Changed
- **[`templates/front-page.html`](templates/front-page.html)** — hero subtitle restored to *"Music production, creative strategy, and the systems that hold them together."*

### Process note
v7.5.3 shipped without explicit per-item approval — I picked one of the audit's §G drafts and committed before confirming. That was a process error: voice-heavy edits aren't mechanical normalization and shouldn't ship without the maintainer signing off on the specific draft. Going forward, items in [docs/CONTENT-AUDIT.md](docs/CONTENT-AUDIT.md) §G come back to the maintainer with options, not as picks.

The audit doc itself remains useful — its findings on factual consistency, IA labelling, and the specific lines that *do* drift toward consultant-speak still apply. The §G drafts are starting points the maintainer can edit, ignore, or reject. The audit is right that the original subtitle reads consultant-coded *if measured against the rest of the site's voice*; that's a measurement, not a verdict.

### Why patch (7.5.4)
One template, one line, restored. Patch 4 of 7.5.

## [7.5.3] — Front-page hero subtitle rewrite (audit §G1)

The single most-trafficked sentence on the site. The [R3 audit](docs/CONTENT-AUDIT.md) flagged the original — *"Music production, creative strategy, and the systems that hold them together."* — as the most consultant-coded line on the site: a noun-phrase rather than an assertion, with *"systems that hold them together"* doing the kind of abstract-glue work the rest of the voice deliberately avoids.

Replaced with a three-sentence subtitle in the brutalist register the voice fingerprint exemplars (`templates/404.html`, the About bio, the 30-min strategy session description) calibrate against:

> 20+ years on the production side. Now also on the business side. Same ear, different console.

Why this draft (audit §G1 Draft C, with canonical *"20+ years"* in place of *"Twenty years"* per the F1–F4 normalisation that landed in v7.5.2):

- **Three short sentences instead of one list-of-three with abstract glue.** The voice fingerprint specifically calls out short sentences with one longer one when needed; this matches.
- **First sentence asserts tenure**; second sentence asserts the pivot to creative-business work; third sentence asserts continuity ("same ear, different console") — narrative arc instead of taxonomy.
- **"Same ear, different console"** is the one phrase in the audit's drafts that isn't outscored by something elsewhere on the site — it's structurally similar to "Knowing when to push, when to pull back, and when to let the silence do the work" (About line 48), which the audit cited as a top-five voice exemplar.
- **The H1 (`I BUILD THINGS THAT SOUND RIGHT.`) does the first-person heavy lifting**; the subtitle doesn't need to repeat "I" to inherit the register.

### Changed
- **[`templates/front-page.html`](templates/front-page.html)** — hero subtitle (line 19) replaced. Single-line edit; no structural changes.

### Out of scope (still queued)
The remaining audit §G drafts that need the maintainer's voice are still pitched in [`docs/CONTENT-AUDIT.md`](docs/CONTENT-AUDIT.md):
- **G2** — Services intro second sentence
- **G3** — Services closing CTA h2 (the body and buttons were already split in v7.5.1)
- **G4** — OPERATIONS & AI STRATEGY blurb
- **A6** — ARTIST & PRODUCER DEVELOPMENT blurb
- Resume cred-strip-vs-prose duplication restructure

These are explicitly waiting on the maintainer's voice — drafts in the audit doc are starting points, not ship-ready copy.

### Why patch (7.5.3)
One template, one line. Patch 3 of 7.5.

## [7.5.2] — Editorial cleanups: canonical-form propagation + small voice swaps

Mechanical follow-up to the [v7.5.1](#) IA pass, driven by the [R3 content audit](docs/CONTENT-AUDIT.md). The audit produced 9 prose-cleanup findings explicitly marked "ship-ready, no maintainer voice required" — those land here. Voice-heavy rewrites (front-page hero subtitle, Services intro/closing CTA, Operations & AI Strategy / Artist & Producer Development blurbs) remain as drafts in the audit doc's §G for the maintainer to react to.

### Changed
- **Canonical "20+ years" form propagated** across the site. The audit found three competing phrasings — *"Over 20 years"* in [page-about.html](templates/page-about.html), *"Twenty years"* on [page-services.html](templates/page-services.html) and [page-work-with-me.html](templates/page-work-with-me.html). All three normalised to **"20+ years"** (canonical for body prose; visual cred-strips continue to use the title-case **"20+ Years"**). Audit findings F1, F2, F4.
- **Cred-strip noun parity** in [page-services.html](templates/page-services.html). Middle column changed from *"50+ Artists & Labels"* to *"50+ Collaborations"* — every other place on the site uses *"collaborations"* as the canonical noun. Audit finding F3.
- **PRODUCTION blurb closer** in [page-services.html](templates/page-services.html) line 104. *"Every decision made with intention"* → *"every decision made to serve the song"*. The audit flagged *"made with intention"* as the closest the site got to a Medium-essay tic; *"made to serve the song"* names the actual standard the work is held to. Audit finding A4.
- **Front-page hero outline button** in [front-page.html](templates/front-page.html). Label *"About Me"* → *"About"* — matches the nav label, removes a small inconsistency where the button-row read differently from the menu it sat under. Audit finding F5.
- **Front-page pillar card dek** in [home.html](templates/home.html) line 43. The dek for *Provenance Over Detection* diverged between [home.html](templates/home.html) (*"A short read on why the industry needs to prove what's human, not chase what isn't."*) and [page-notes.html](templates/page-notes.html) (*"Detection chases what isn't. Provenance proves what is."*). The `/notes` version is sharper — single chiastic sentence, exemplary of the brutalist register. Reuse it on the home page. Audit finding F6.
- **Catalog meta line** in [page-music.html](templates/page-music.html) line 55. Trimmed *"every credit, every collaboration, every role I've held across 20+ years"* to *"every credit, every collaboration, every role I've held"*. By the time a visitor reaches Music, the cred-strip on Services and the bio paragraph on About have asserted the years-claim twice already; the catalog itself does the work of asserting tenure here. Audit finding F8.
- **Eyebrow standardisation** on [page-contact.html](templates/page-contact.html) and [page-work-with-me.html](templates/page-work-with-me.html). The audit identified three competing eyebrow patterns; the *"Section · Specifier"* dossier system used on About, Resume, Music, Services, and 404 is the canonical one. Brought the two outliers into the family:
  - Contact: bare *"Get In Touch"* → *"Dossier · Get In Touch"*
  - Work With Me: bare *"Strategy Sessions"* (set in v7.5.1) → *"Consulting · Strategy Sessions"*
  Audit finding D1.

### Out of scope (deliberately deferred)
The audit's voice-heavy rewrites stay as drafts in [docs/CONTENT-AUDIT.md](docs/CONTENT-AUDIT.md) §G — these need the maintainer's voice to land:
- **G1**: Front-page hero subtitle (*"Music production, creative strategy, and the systems that hold them together."*) — three drafts pitched in the audit; one to pick.
- **G2**: Services intro second sentence (*"…deliberate, thorough, and built to last."*).
- **G3**: Services closing CTA h2 + body — already partially fixed in v7.5.1 (button split), but the heading *"LET'S TALK ABOUT YOUR PROJECT"* is the weakest h2 on the site.
- **G4**: OPERATIONS & AI STRATEGY blurb (*"build systems that scale"*).
- **A6**: ARTIST & PRODUCER DEVELOPMENT blurb (*"connect creative identity to commercial opportunity"*).
- The Resume page's redundant cred-strip-vs-prose duplication — the audit suggested a restructure (*"Music Production · Strategy · Mentorship"*) but the choice of three disciplines is editorial.

### Why patch (7.5.2)
Pure editorial cleanup. No new functionality, no IA changes, no schema changes. Eight templates touched, every change a 1–2-line surgical edit verifiable against the audit doc. Patch 2 of 7.5.

## [7.5.1] — IA pass: stop conflating "Contact" with "Work With Me"

The nav had two top-level items — *Contact* and *Work With Me* — but they pointed at very different products, and the labels misled visitors about what was on the other side of the click.

- **`/contact`** is a general message form ("Got a project, a question, or just want to talk sound? Fill out the form. I respond to everything that isn't spam.") plus social links. Low-commitment scoping path.
- **`/work-with-me`** is a Cal.com booking widget for **paid 30- or 60-minute strategy sessions** ("Paid at booking · Non-refundable"). A specific paid product, not a general contact path.

Pre-existing CTAs across the site read *"Get In Touch →"* and pointed at `/work-with-me`, which translates to: visitor clicks expecting an email form, lands on a paid-consult booking widget with their credit card implied. That's the bug.

This release renames and re-frames so labels match destinations. URLs stay (`/contact` and `/work-with-me` slugs unchanged — WordPress URL slugs are CMS-level, not theme-level), but the user-facing labels are now honest about what each page does.

### Changed
- **[`parts/header.html`](parts/header.html) — nav label "Work With Me" → "Book a Call".** The page is literally a booking widget; the label now says so. URL slug `/work-with-me` is preserved (changing it is a CMS-level migration with redirects, separate scope).
- **[`templates/page-work-with-me.html`](templates/page-work-with-me.html) — page header re-framed.** H1 changed `WORK WITH ME` → `BOOK A CALL`. Eyebrow changed `Consulting` → `Strategy Sessions` (more specific, matches the actual product). Subtitle rewritten from the generic *"20+ years building music businesses across the U.S. and Latin America"* (which was true but didn't tell visitors what the page was) to *"Paid 30- or 60-minute consults for music businesses, artists, and producers. Twenty years of studio operations and creative strategy, on the clock."* — names the product explicitly.
- **[`templates/page-services.html`](templates/page-services.html) — closing CTA split into two buttons.** Was a single `Get In Touch →` button pointing at `/work-with-me`. Now two buttons:
  - **Primary:** `Tell me about your project →` → `/contact` (the actual generic-inquiry path)
  - **Outline:** `Book a strategy call →` → `/work-with-me` (the paid-booking path)

  Supporting paragraph rewritten to name both options explicitly: *"Two paths in: send a message if you're scoping things out, or book a paid session if you want focused time on the calendar."* Visitor self-selects by intent.

### Renamed
- **`patterns/cta-work-with-me.php` → `patterns/cta-closing.php`**, slug `signal-noise/cta-work-with-me` → `signal-noise/cta-closing`. The pattern was introduced one release ago in v7.5.0 as a single-button "closing CTA"; the rename + content update reflects what it actually is now (the two-path closing CTA matching the Services page). Renamed via `git mv` so the file rename is tracked. The filename and slug now match. The pattern hasn't been consumed by any template yet (the v7.5.0 CHANGELOG explicitly noted that as a "manual editorial pass" follow-up), so the rename has zero downstream impact.
- **Pattern title** updated from *"CTA — Work With Me"* to *"CTA — Closing (two paths)"* in the inserter UI.

### Why patch (7.5.1)
Pure IA / labelling fix — no new functionality, no settings changes. Everything that was on `/work-with-me` is still there at the same URL; visitors just know what they're clicking now. Patch 1 of 7.5.

### Out of scope
- **The `/contact` page subtitle** ("Got a project in mind, a question about my work, or just want to talk sound?") still reads as solid voice and isn't touched here.
- **Front-page hero subtitle** ("Music production, creative strategy, and the systems that hold them together.") is identified as the weakest copy on the site relative to the established voice, but rewriting it requires the maintainer's voice — queued for the editorial pass driven by the in-flight `docs/CONTENT-AUDIT.md`.
- **Eyebrow standardisation** across pages (some use *"Dossier · X"*, others use bespoke labels) — same reason, queued for the audit.
- **URL slug changes** (`/work-with-me` → `/book-a-call`) — would require a redirect strategy and a CMS-level page-slug edit, neither of which belong in a theme patch.

## [7.5.0] — Block Patterns: first three extracted from templates

The theme had 13 templates and **zero** block patterns — every repeated layout (page hero, closing CTA, constrained content section) lived as raw block markup duplicated across 4–5 templates. Per the [docs/WP-API-MAP.md](docs/WP-API-MAP.md) R2 audit (top-3 recommendation #2), this release introduces a `signal-noise` pattern category and three patterns covering the most-duplicated layouts. The pattern files use WordPress's `/patterns/` directory convention — drop a PHP file with a header comment, core auto-registers it.

### Added
- **[`inc/patterns.php`](inc/patterns.php)** — registers a single `signal-noise` block-pattern category on `init` with translatable label and description. The category groups all S&N patterns under a single section in the block-inserter UI. If the pattern surface ever grows past ~10 items, this is the place to split into sub-categories (`signal-noise/hero`, `signal-noise/cta`, etc.) — registration cost is trivial.

- **`patterns/hero-dossier.php`** — the brutalist page hero that recurs across [page-about.html](templates/page-about.html), [page-resume.html](templates/page-resume.html), [page-music.html](templates/page-music.html), [page-services.html](templates/page-services.html), and [404.html](templates/404.html). Eyebrow with `sn-catalog-eyebrow` class ("Dossier · X") + oversized clamped H1 + intro paragraph in `rust` color + `sn-catalog-meta` stats line. All four sites had near-identical block markup with only the strings differing — replacing with this pattern dedupes the layout while leaving the per-page content edit-in-place.

- **`patterns/cta-work-with-me.php`** — the closing-section CTA from [page-services.html](templates/page-services.html) ("LET'S TALK ABOUT YOUR PROJECT" + supporting copy + "Get In Touch →" button to `/work-with-me`). Single source of truth for the "let's talk" framing — one copy edit propagates to every page that uses it instead of fanning out to five files.

- **`patterns/section-constrained.php`** — the most-repeated wrapper across all 14 templates: `void` background + `--wp--preset--spacing--40/70` padding + 1000px constrained content width. Extracted as a pattern so the spacing scale and background-color choices evolve in one file rather than the 30+ inline group blocks where they currently live.

### Pattern registration semantics

WordPress auto-discovers any PHP file in `theme/patterns/` with the right header comments. No `register_block_pattern()` call is needed — the file's *Title*, *Slug*, *Categories*, *Description*, *Keywords*, *Block Types*, and *Viewport Width* headers are parsed by core's `_register_theme_block_patterns()` on every `init`.

The `/patterns/` directory survives self-heal correctly: [`inc/template-self-heal.php`](inc/template-self-heal.php) only monitors `.html` files in `templates/` and `parts/` (filterable via `sn_self_heal_files`), so the new `.php` pattern files are not touched by the drift-detection loop. They're version-controlled like every other theme file.

### Migration note (manual, not in this release)

The patterns are *registered* in v7.5.0 but the existing templates have **not yet been refactored to use them**. The existing inline markup keeps rendering identically. Migrating each template (e.g., replacing the inline hero block in `page-about.html` with `<!-- wp:pattern {"slug":"signal-noise/hero-dossier"} /-->`) is a separate manual editorial pass — recommended approach is to migrate one template at a time so each diff is reviewable, ideally bundled with content edits the maintainer already wanted to make on that page. The R2 audit's recommendation was specifically about *registering* the patterns first; refactoring templates to consume them is value extracted later.

### Why minor (7.5.0)
New user-visible capability: the patterns appear in the block inserter under a *Signal & Noise* group, immediately usable when authoring posts/pages or editing templates in the Site Editor. Per CLAUDE.md SemVer: "MINOR for new user-visible capabilities."

This is the **last available minor in the 7.x line** — the project's per-major minor cap is 5 (7.0–7.5 valid). The next bump rolls major to 8.0.0. Subsequent 7.5.x patches resume normal numbering up to the per-minor patch cap of 7.

### Phase 1 — complete
This release closes the original Phase 1 plan from [docs/WP-API-MAP.md](docs/WP-API-MAP.md):
- ✓ v7.3.0 — hardening pass (security defense-in-depth + i18n setup)
- ✓ v7.3.1 — SWR for updater + S&N options page (every sync external HTTP off the render path)
- ✓ v7.4.0 — REST surface (`signal-noise/v1` namespace, 8 endpoints)
- ✓ v7.5.0 — Block Patterns (this release)

Phase 2 candidates queued for future work: bulk `__()` wrapping of admin strings (audit M8 + L1), Block Bindings to retire shortcodes (R2 #1), WP-CLI commands wrapping the REST endpoints, Style Variations (`/styles/`), template refactor to actually consume the new patterns.

## [7.4.0] — REST surface: `signal-noise/v1` namespace for maintenance + Plausible

The first new public-API surface for the theme. Every maintenance action previously buried under *Appearance → Signal & Noise → Dashboard* (purge caches, clear overrides, heal templates, full reset, check updates) plus the Plausible read/test endpoints now have authenticated REST counterparts. Same logic, same capability gate, scriptable from outside the WP admin UI.

Adopted on the recommendation of [docs/WP-API-MAP.md](docs/WP-API-MAP.md) (R2 research pass, top-3 recommendation). The earlier v7.0 plan had pitched the Abilities API for this; the R2 audit pushed back — Abilities is designed for distributed plugins exposing capabilities to external agents and a single-author personal site has no agents to expose to. REST is the strict superset surface a) WP-CLI commands can wrap, b) CI/automation can curl with an Application Password, c) future AI agents can drive directly without an Abilities discovery layer.

### Added
- **[`inc/rest-api.php`](inc/rest-api.php)** — new module registering 8 endpoints under the `signal-noise/v1` namespace:

  | Method | Path | Wraps | Mirrors UI button |
  |---|---|---|---|
  | POST | `/purge-cache` | `sn_purge_all_caches([template_overrides=>false])` | "Purge All Caches" |
  | POST | `/clear-overrides` | `sn_clear_template_overrides()` | "Clear Overrides" |
  | POST | `/heal-templates` | `sn_self_heal_force_run()` | "Re-sync from GitHub" |
  | POST | `/full-reset` | `sn_purge_all_caches()` (with overrides) | "Run Full Reset" |
  | POST | `/check-updates` | cache-clear + `wp_update_themes()` + returns offered update | "Check Now" |
  | GET  | `/plausible/stats` | `sn_plausible_dashboard_data()` | (read-only — no UI button) |
  | GET  | `/plausible/realtime` | `sn_plausible_realtime()` | (read-only — no UI button) |
  | POST | `/plausible/test` | synchronous `sn_plausible_api('aggregate')` | "Run Test" |

- **`SN_REST_NAMESPACE` constant** at the top of [`inc/rest-api.php`](inc/rest-api.php) so future endpoint registrations and clients reference the namespace from one place.
- **`sn_rest_can_manage()` permission callback** shared across every endpoint. Returns `WP_Error` with `rest_authorization_required_code()` on failure (so non-authenticated requests get 401, authenticated-but-unprivileged get 403, both with translatable messages). Never `__return_true` — these are state-mutating admin endpoints, not public data.
- **`sn_rest_ok()` standardized response helper** so every endpoint returns the same `{ ok: true, message: string, data: object }` shape on success. Errors flow through `WP_Error` with a status code and core's REST handler serializes them to JSON automatically.

### Auth model
- **In-WP admins**: cookie auth + REST nonce flows through `current_user_can()` — no new plumbing.
- **External clients (CLI, automation)**: WordPress Application Passwords issued to a `manage_options`-capable user. Pair with the existing `SN_GITHUB_TOKEN` / `SN_PLAUSIBLE_STATS_TOKEN` envars in CI to script "after deploy: heal-templates + check-updates + purge-cache" as three curls.
- The admin-page form handlers in [`inc/admin-page.php`](inc/admin-page.php) are deliberately untouched in this release — they continue to work as classic admin-post forms with nonces. A future patch can migrate the buttons themselves to call the REST endpoints internally, but that's a UX nicety, not the value of this release.

### Why minor (7.4.0)
First new user-visible API surface since v7.2.x. The endpoints are public (in the auth-gated sense) and compose with external tooling. Per CLAUDE.md SemVer: "MINOR for new user-visible capabilities." 7.3.x line stays available for SWR / hardening follow-ups; 7.4.0 marks the REST capability boundary.

### Out of scope (queued)
- **WP-CLI commands** (`wp signal-noise purge-cache | heal-templates | …`) wrapping the same REST endpoints — separate ship, the cleanest pattern is a thin `WP_CLI::add_command()` registration that calls the same callbacks the REST routes expose.
- **Block Patterns extraction** — queued for v7.5.0 per the original Phase 1 plan.

## [7.3.1] — Updater + S&N options page: stale-while-revalidate

The follow-up flagged in [v7.2.6](#) and [v7.3.0](#). Same SWR architecture from the Plausible (v7.2.6) and template-self-heal (v7.2.7) refactors, applied to the last two synchronous-external-HTTP-on-render hot spots: the GitHub-driven self-updater's `pre_set_site_transient_update_themes` filter and the *Latest on GitHub* status block on the *Appearance → Signal & Noise* options page. Both surfaces now read from a long-retention cache that's warmed by a non-blocking WP-Cron loopback.

Worst-case behaviour before this release:
- **Updater filter**: on cache miss could fire 3 sequential GitHub API calls (commits + style.css + compare), totalling **up to 25s** of synchronous HTTP every time WP refreshed its `update_themes` site transient. WP refreshes that transient on every admin pageview that hits the Updates / Themes / Dashboard screens.
- **S&N options page**: independent on-render `wp_remote_get` to the GitHub commits API, **up to 10s** every 5 min on the Status table.

After: both are constant-time cache reads. The render path never touches the network.

### Changed
- **[`inc/updater.php`](inc/updater.php) — `pre_set_site_transient_update_themes` filter is now read-only.** It reads the `sn_github_branch_$branch` transient and either uses the cached SHA or returns the transient unchanged (no update offered this cycle). Previously the filter would inline-fetch the GitHub commits API on cache miss, blocking WP's update-transient refresh for up to 10s.
- **[`sn_updater_revcount()`](inc/updater.php) and [`sn_updater_remote_version()`](inc/updater.php) are now read-only cache accessors.** Each was previously a fetch-on-miss helper called from inside the filter — chaining them produced the worst-case 25s stall when all three caches were cold simultaneously. Now both return whatever's in their respective transients (or 0 / `''` on miss); the actual API calls are batched into the new cron callback.
- **[`inc/admin-page.php`](inc/admin-page.php) — `sn_theme_options_page()` no longer fetches the GitHub branch HEAD inline.** Reads the shared `sn_github_branch_$branch` transient (warmed by the same cron callback). When the cache is empty (cron hasn't populated yet, or fresh install / cache flush), the *Latest on GitHub* row honestly renders *"refreshing in background — reload in a moment"* instead of falsely reporting "Up to date."
- **Transient retention decoupled from freshness target** for the branch HEAD cache (`DAY_IN_SECONDS` retention vs 5-min freshness via the embedded `fetched` field) and the version + revcount caches (24h on success, 15-min on empty/error sentinel). Stale data remains visible during a GitHub outage; freshness is gated by the warmer's age check, not the transient TTL.

### Added
- **`sn_updater_refresh_cache()`** — new WP-Cron callback hooked to `sn_updater_refresh_cache`. Sequentially fetches all three GitHub-derived caches (branch HEAD, remote `style.css` Version header, ahead-by revcount) and writes them with appropriate retention. Records `sn_github_error` on the first-step failure so the existing admin notice surfaces what went wrong.
- **`admin_init` warmer at priority 5** that age-checks the branch HEAD cache via the embedded `fetched` field and schedules `sn_updater_refresh_cache` when stale. Priority 5 is load-bearing — same trick as in [`inc/plausible-api.php`](inc/plausible-api.php) and [`inc/template-self-heal.php`](inc/template-self-heal.php): scheduling at admin_init priority 5 happens BEFORE `wp_loaded` fires, so `wp_cron()` picks up the just-scheduled event in the same request and dispatches the non-blocking `spawn_cron()` loopback before the admin response is sent.
- **`SN_UPDATER_REFRESH_HOOK`, `SN_UPDATER_FRESHNESS`, `SN_UPDATER_RETENTION`, `SN_UPDATER_RETENTION_SHORT`** constants at the top of the cron block so the SWR semantics are explicit and tunable from one place rather than scattered as magic numbers across function bodies.

### Unchanged (deliberately blocking)
- **`upgrader_process_complete`'s post-upgrade SHA refetch** — runs synchronously after a successful theme upgrade. The user is staring at the WP upgrader UI and expects the install-then-poll cycle to complete before they see "Theme installed successfully." Moving this to cron would introduce a window where the just-installed SHA hasn't been recorded yet and the next poll would offer the same update again.

### Architectural note — SWR fully applied
With this release, every synchronous external HTTP call previously blocking the admin render path has been moved to WP-Cron-driven SWR:
- **v7.2.6** — Plausible Stats API (3 sequential calls + 1 realtime).
- **v7.2.7** — Template self-heal (N×10s GitHub Contents API loop).
- **v7.3.1** — Updater (3 sequential GitHub API calls) + S&N options page (1 GitHub commits API call).

The admin dashboard and S&N options page now render in constant time regardless of GitHub or Plausible API health.

### Why patch (7.3.1)
Pure performance refactor. No new user-visible features, no settings schema changes, no public API changes. Patch 1 of 7.3; cap is 7 per minor.

## [7.3.0] — Hardening pass + cap-forced minor rollover

Targeted defensive sweep driven by the [R1 standards audit](docs/WP-STANDARDS-AUDIT.md). The audit returned **0 CRITICAL · 2 HIGH · 9 MEDIUM · 11 LOW · 6 NIT** — none exploitable, but two HIGH defense-in-depth gaps in [`inc/admin-page.php`](inc/admin-page.php) and a textdomain registration omission worth closing before the v7.3 line accumulates new surface.

The minor bump is **forced by the per-minor patch cap** (7.2.0 through 7.2.7 = 7 patches, the project's documented ceiling per [docs/VERSIONING.md](docs/VERSIONING.md) and [CLAUDE.md](CLAUDE.md)). Semver-wise this release is patch-class — fixes only, no new user-visible features — but the cap rule supersedes when it fires. Subsequent 7.3.x patches resume normal patch numbering.

### Fixed
- **H1 — Defense-in-depth `current_user_can()` check in [`sn_theme_options_page()`](inc/admin-page.php).** Previously the function relied solely on the `manage_options` capability gate WordPress enforces from `add_theme_page()`. That's sufficient for the registered admin URL today, but if the function is ever invoked from another context (a future shortcode, AJAX dispatcher, or REST callback), the form-handling block ran without re-checking. Now the function calls `current_user_can( 'manage_options' )` at the top and `wp_die()`s with a translatable error if it fails. WPCS convention; no behavior change for legitimate admin users.
- **H2 — Refactored the `installed_label` concatenation pattern in the Status table.** The previous form built a pre-escaped HTML string by concatenating `esc_html()` fragments with a static `<span>` literal, then echoed the result. Safe today (every dynamic field was escaped at concatenation site), but a known XSS-bug class — anyone adding a new dynamic field to the concat without escaping it would inject straight into the admin page. Replaced with inline-print: `echo '<code>' . esc_html( $local_version ) . '</code>'` followed by a conditional `<span>` block. Same visual output; future-bug-class eliminated.
- **L2 — `[current_year]` shortcode now uses `wp_date( 'Y' )` instead of `date( 'Y' )`** in [`inc/setup.php`](inc/setup.php). `date()` reads the server timezone, which on US-hosted WordPress can disagree with the site's configured timezone for a few hours each year around Dec 31 / Jan 1. `wp_date()` (since WP 5.3) respects the WP timezone setting.

### Added
- **`load_theme_textdomain( 'signal-noise', get_theme_file_path( 'languages' ) )`** registered in `signal_noise_after_setup_theme()` (renamed from `signal_noise_editor_styles()` — same hook, expanded scope). Closes audit finding M8: the text domain `signal-noise` was previously referenced once in [`inc/page-notes-render.php`](inc/page-notes-render.php) via `_n()` but never registered, so translation calls worked by silent fall-through rather than registered intent. The `languages/` directory doesn't exist yet — calling `load_theme_textdomain()` against a non-existent path is harmless and lets the registration land in advance of any future translation work.
- **Safety contract docblock** above the inline-CSS injector in [`inc/assets-frontend.php`](inc/assets-frontend.php) (audit finding M1). The block reads `assets/css/critical.css` via `file_get_contents()` and echoes it verbatim into `<head>` on every front-end pageview. Currently safe by construction (theme-owned file, never user-influenced), but the audit flagged that any future module programmatically writing to that file would inject straight into the document. The docblock makes the contract explicit and tells future maintainers where sanitization belongs (at the write site, not here).

### Out of scope (deferred to later ships)
The audit's other findings are tracked but not addressed in this release:
- **Bulk `__()` / `esc_html__()` wrapping of admin-facing strings** (audit M8 + L1). Mechanical but voluminous — touches 7 files. A separate dedicated ship gives that pass its own review surface and keeps this hardening release focused.
- **SWR refactor of [`inc/updater.php`](inc/updater.php) + the GitHub branch HEAD fetch in [`inc/admin-page.php`](inc/admin-page.php)** — queued for v7.3.1 (the next patch).
- All LOW and NIT findings — see [`docs/WP-STANDARDS-AUDIT.md`](docs/WP-STANDARDS-AUDIT.md) for the full list.

### Why minor (cap rollover)
The 7.2 line shipped 7 patches (`.1` through `.7`) before this release. The project's per-minor patch cap is 7 (see [`docs/VERSIONING.md`](docs/VERSIONING.md)), so the next bump rolls minor regardless of semantic content. v7.3.0 is the first 7.3.x release; subsequent fixes resume at v7.3.1 patch numbering.

## [7.2.7] — Template self-heal: stale-while-revalidate, no more admin_init hangs

The follow-up to v7.2.6 flagged in that CHANGELOG. Same architectural class — synchronous external HTTP on the admin render path — applied to the self-heal module's GitHub Contents API loop. On a cold rate-limit window, [`sn_self_heal_run()`](inc/template-self-heal.php) iterated every monitored `.html` file (typically 8+ templates and parts) and made one `wp_remote_get` per file with a 10-second timeout. Worst case: **N × 10s of admin pageview hang every 5 minutes** — dwarfing the Plausible widget hang the earlier patch addressed.

### Changed
- **[`inc/template-self-heal.php`](inc/template-self-heal.php) — admin_init becomes a scheduler.** The `sn_self_heal_run()` callback now performs the capability + rate-limit + token-defined gates and, if all pass, calls `wp_schedule_single_event()` for a new `sn_self_heal_cron` hook. The actual GitHub-fetch loop runs in the non-blocking [`spawn_cron()`](https://developer.wordpress.org/reference/functions/spawn_cron/) loopback, never on the admin response path. Hook priority dropped from `20` to `5` so the schedule call lands BEFORE `wp_loaded` fires — same timing trick as the Plausible warmer in [`inc/plausible-api.php`](inc/plausible-api.php), so `wp_cron()` picks up the just-scheduled event in the same request and dispatches the loopback before the admin's response is sent.
- **[`sn_self_heal_execute()`](inc/template-self-heal.php) gains an optional `$notice_user_id` parameter** so the per-user admin notice routes correctly across the cron boundary. Default behaviour (when called from the synchronous `sn_self_heal_force_run()`) is unchanged — falls back to `get_current_user_id()`. The cron callback passes the user_id stashed at schedule time so the notice lands under the admin who triggered it, not under user `0`.
- **Notice writes now skip `audience === 0`** entirely. Previously a cron run with no scheduling user (or any future caller without a logged-in admin) would write a notice transient under user_id 0 that no one could see — clutter without value.

### Added
- **`sn_self_heal_cron($user_id = 0)`** — new WP-Cron callback hooked to `sn_self_heal_cron`. Thin wrapper that calls `sn_self_heal_execute(false, $user_id)`. Single-event scheduled, never recurring; the next admin pageview after the rate-limit window expires schedules the next one.

### Unchanged (preserves expected synchronous behaviour)
- **`sn_self_heal_force_run()`** — the button-click + post-update entry point — remains synchronous. The user is staring at the *Heal Templates Now* button or the WP upgrader UI and expects an immediate "X files re-synced from GitHub" result. Moving this to cron would make the recovery action feel broken, not faster.

### Why patch (7.2.7)
Pure performance fix scoped to one module. Rate-limit semantics are preserved (still 5 min between ambient runs); the only behavioural change visible to users is *the absence of a hang* every 5 min. No schema changes, no API changes, no settings changes. **This is the last patch allowed in the 7.2 series** — the project's per-minor patch cap is 7. The next bump rolls minor to 7.3.0; the updater's matching SWR fix is queued for that release.

## [7.2.6] — Plausible widgets: stale-while-revalidate, no more dashboard hangs

The four Plausible widgets shipped in v7.2.1 fetched data from the Stats API synchronously during dashboard render — three sequential calls for the snapshot/pages/sources panel plus one for the realtime panel. With a 6-second per-call timeout and a 5-minute cache TTL, the WP dashboard could block for up to **24 seconds** on every cache-miss (recurring every 5 min by design). Symptom: "the page hangs for a bit when it shouldn't."

Root cause was the architectural choice to `wp_remote_get` on the page-render path. The fix replaces that with stale-while-revalidate: the render path becomes constant-time (cache reads only), and refreshes run in a non-blocking WP-Cron loopback dispatched by `spawn_cron()` (`wp_remote_post` with `blocking=false, timeout=0.01`).

### Changed
- **[`inc/plausible-api.php`](inc/plausible-api.php) — `sn_plausible_dashboard_data()` and `sn_plausible_realtime()` are now read-only.** They return whatever's in the transient (possibly empty on first-ever load, possibly stale during a refresh in flight) and never make a network call. Dashboard render is now constant-time regardless of Plausible API health.
- **Transient retention decoupled from freshness target.** Batch retention is `DAY_IN_SECONDS` (was 5 min); freshness threshold is still 5 min, gated by the `fetched` field embedded in the payload. Realtime retention is 5 min (was 30s); freshness is still 30s. Effect: stale data remains visible if Plausible is unreachable (the widget footer shows "cached X ago" so the staleness is honest), and refresh failures don't poison the transient with `null`.

### Added
- **`sn_plausible_refresh_dashboard()` and `sn_plausible_refresh_realtime()`** in [`inc/plausible-api.php`](inc/plausible-api.php) — WP-Cron callbacks that do the actual API work. Hooked to `sn_plausible_refresh_dashboard` and `sn_plausible_refresh_realtime` actions. Run in a separate process via the cron loopback, never on a user-facing request.
- **`sn_plausible_warm_caches()` admin warmer** at `admin_init` priority 5. Checks the cached payload's `fetched` timestamp; if it's older than the freshness threshold (or missing entirely), schedules a single-event cron job. Priority 5 is intentional — it runs before `wp_loaded`, so `wp_cron()` picks up the just-scheduled event in the same request and dispatches the non-blocking loopback before the admin response is sent. Capability gate (`view_stats` / `manage_options`) matches the widget registration in [`inc/plausible-widget.php`](inc/plausible-widget.php) so we don't warm caches for users who can't see the widgets.
- **`SN_PLAUSIBLE_BATCH_RETENTION`, `SN_PLAUSIBLE_REALTIME_RETENTION`, and the two refresh-hook constants** in [`inc/plausible-api.php`](inc/plausible-api.php) so the SWR semantics are explicit at the top of the file rather than hardcoded inline.

### Fixed
- **First-ever-load footer** in [`inc/plausible-widget.php`](inc/plausible-widget.php) — `sn_pl_footer()` no longer renders `human_time_diff()` against an epoch-zero `fetched` timestamp (which would have read as "cached 56 years ago"). When `fetched=0`, the footer instead shows *"refreshing in background — reload in a moment"* so the user knows what they're waiting on.

### Why patch (7.2.6)
Pure performance/architecture fix scoped to the existing Plausible module — no new user-visible features, no settings schema changes, no API surface changes. The widget rendering, configuration tab, and token resolution chain are all untouched. The batch cache key is unchanged because the payload shape is unchanged; old transients written under v7.2.5's 5-min TTL age out naturally and the next warmer run writes new transients under the longer retention. The realtime cache key bumps `v2 → v3` because the payload shape changed from a bare int to `{ value, fetched }` so the warmer can age-check the data without hitting the network — old `v2` transients age out in 30s and the new `v3` shape takes over on the next warmer run. Patch 6 of 7.2; cap is 7 per minor.

### Out of scope (follow-ups)
The same blocking pattern exists in two other places that pre-date v7.2.x and weren't part of this complaint:
- [`inc/template-self-heal.php`](inc/template-self-heal.php) — iterates monitored templates with sequential GitHub Contents API calls on `admin_init`, 10s each. Worst-case dwarfs the Plausible hang on installs with many templates.
- [`inc/updater.php`](inc/updater.php) and [`inc/admin-page.php`](inc/admin-page.php) — GitHub commits/compare API calls on `pre_set_site_transient_update_themes` and on the S&N options page render, 10s each.

Both are candidates for the same SWR pattern in a future patch.

## [7.2.5] — Plausible: admin tab for Stats API key (no more wp-config edits)

The constant-only token storage from v7.2.4 worked but required SSH/SFTP into Cloudways to rotate. Adds an admin UI tab so the Stats API key can be saved, tested, and rotated from inside WordPress — same precedence pattern as the Cloudflare module's token storage at [`inc/cloudflare-purge.php:58-83`](inc/cloudflare-purge.php).

### Added
- **`inc/plausible-admin.php`** — new tab at *Appearance → Signal & Noise → Plausible*. Surfaces:
  - **Status card** — domain (read from the Plausible plugin), current token source (constant / option / plugin fallback), last API call result with `human_time_diff()` timestamp.
  - **Stats API Key field** — paste to save, type `clear` to remove, last 4 chars displayed obscured (`••••WXYZ`). Hidden when `SN_PLAUSIBLE_STATS_TOKEN` is defined in `wp-config.php` — the constant takes precedence and the form locks itself with an explanation.
  - **Test Connection button** — fires a synchronous 7-day aggregate call against the Stats API and reports success (`✓ N visitor(s) in last 7 days`) or failure with the actual HTTP code + body excerpt. No more guessing whether the credentials work.
  - **Embedded Stats link** — quick path to the Plausible plugin's in-admin dashboard.
  - Saving or clearing the token automatically invalidates the dashboard data + realtime + error transients via `sn_pl_admin_invalidate_caches()` so the next dashboard pageview fires fresh API calls. Without this, users would paste a new key and still see cached 401 errors for 5 minutes.
- **Token resolution priority** in [`sn_plausible_config()`](inc/plausible-api.php) is now three-tier:
  1. `SN_PLAUSIBLE_STATS_TOKEN` constant (file-based, locked, preferred for CI-deployed credentials).
  2. `sn_plausible_stats_token` option (admin-saved via the new tab, non-autoloaded so it isn't in PHP memory on every request).
  3. Plausible plugin's `api_token` from `plausible_analytics_settings` (last-resort fallback for setups where the namespaces happen to overlap).
- **`SN_PLAUSIBLE_TOKEN_OPT` const** in [`inc/plausible-api.php`](inc/plausible-api.php) so any module can reference the option key without hardcoding the string.

### Changed
- **[`inc/admin-page.php`](inc/admin-page.php)** — `Plausible` added to `$valid_tabs` and `$tab_labels` (between Cloudflare and Reading Time). Tab body dispatches via `do_action( 'sn_admin_plausible_tab' )`, the same module-owned-UI pattern used by Cloudflare and Reading Time. The dispatcher in `sn_theme_options_page()` doesn't know about Plausible's internals — it just hands control to whoever's listening on the action.

### Why patch (7.2.5)
Pure additive: new admin tab + a third tier in the existing token resolution chain. The `wp-config` constant path from v7.2.4 still works unchanged (and is still preferred for security). The Plausible plugin fallback still works unchanged. Existing widget rendering, caching, and API client are untouched. Patch 5 of 7.2; cap is 7 per minor.

## [7.2.4] — Plausible: SN_PLAUSIBLE_STATS_TOKEN constant + corrected token-source assumption

The diagnostic added in v7.2.3 caught a real architectural mistake from v7.2.1: the Plausible plugin's stored `api_token` is a **Plugin Token** scoped to `/api/plugins/wordpress/*` (the namespace the plugin uses for proxy resource management, the embedded stats page wizard, etc.), **not** a Stats API key. The Stats API at `/api/v1/stats/*` rejects Plugin Tokens with HTTP 401 `"Invalid API key or site ID"` — confirmed live on the Plausible CE install.

These are two separate token namespaces in Plausible. They look identical (both are bearer-style strings), and the WP plugin uses both kinds internally — but only Stats API Keys (created in *Plausible → Settings → API Keys*) have `stats:read` scope.

### Added
- **`SN_PLAUSIBLE_STATS_TOKEN` wp-config constant** as the preferred token source. Matches the existing pattern for sensitive credentials in this codebase (`SN_GITHUB_TOKEN`, `SN_CLOUDFLARE_API_TOKEN`) — file-based, can't be exfiltrated through a SQL injection or compromised admin login. Setup:
  ```
  // 1. In Plausible CE → Settings → API Keys → New API Key
  //    (scope: stats:read on the site domain)
  // 2. In wp-config.php:
  define( 'SN_PLAUSIBLE_STATS_TOKEN', 'plnt_…' );
  ```

### Changed
- **`sn_plausible_config()` token resolution priority.** Now checks the `SN_PLAUSIBLE_STATS_TOKEN` constant first; falls back to `plausible_analytics_settings.api_token` only if the constant is undefined. The fallback is kept in case a future Plausible release unifies the two token namespaces, or for Plausible Cloud setups where the distinction may not apply — but for self-hosted CE in 2026, the constant is what works.
- **"Not configured" error message** rewritten to walk users through the correct setup explicitly: set domain in plugin settings, create a Stats API key separately, drop the constant in wp-config. The previous message said "set domain + Plugin Token in *Settings → Plausible Analytics*" which was actively misleading.
- **Cache key bumped `v3 → v4`.** Same reason as the v7.2.3 bump: forces a fresh fetch immediately after the constant is added, so users don't have to wait 5 minutes for the cached 401 errors to age out before seeing the widgets work.

### Why patch (7.2.4)
Targeted bug fix for the silent-failure mode v7.2.3's diagnostic exposed. New constant is purely additive (the plugin's `api_token` fallback is unchanged for sites where it happens to work). Patch 4 of 7.2; cap is 7 per minor.

## [7.2.3] — Plausible widgets: surface API errors + defensive scheme handling

The widgets in v7.2.2 rendered "—" across the board on the live install — meaning the API calls were failing silently. The original `sn_plausible_api()` returned `null` on any non-200 with no breadcrumb, so the maintainer couldn't tell whether they were looking at a bad URL, a bad token, a scope mismatch, or a network blip. This release adds a self-debugging surface and fixes the most likely root cause.

### Added
- **Inline API error diagnostic.** [`inc/plausible-api.php`](inc/plausible-api.php) now captures the URL + HTTP status + first 240 bytes of the response body into a `sn_plausible_last_error` 5-min transient on every failure. [`inc/plausible-widget.php`](inc/plausible-widget.php) renders this inline below the snapshot widget, gated behind `manage_options` so non-admins never see internals. Token is never written to the transient — only the URL (which doesn't carry credentials), the HTTP code, and the body excerpt. Error is auto-cleared on the next successful API call so transient outages don't leave stale "API failed" banners on the dashboard.
- **Diagnostic shows once, not four times.** Only the snapshot widget renders the diagnostic — the other three panels are downstream of the same API + cache, so a single inline notice is enough.

### Fixed
- **Defensive `https://` prepend on self-hosted Plausible base URL.** [`sn_plausible_config()`](inc/plausible-api.php) now prepends `https://` to `self_hosted_domain` when the plugin's saved value lacks a scheme. The Plausible WP plugin's settings field accepts both forms (hostname or full URL), but `wp_remote_get()` requires a scheme to dispatch. A bare hostname like `plausible-analytics-ce-production-fcb9.up.railway.app` previously produced a silent `WP_Error` that bubbled up as `null` and then "—" in every widget. Now the URL is normalised before dispatch.

### Changed
- **Cache key bumped `sn_plausible_dashboard_v2` → `sn_plausible_dashboard_v3`.** Without this, sites updating from v7.2.2 keep reading the empty-data cache that v7.2.2 wrote during its silent failure, and the new error capture never gets a chance to populate the diagnostic transient. The bump forces a fresh fetch on the first pageload after update. The 30-sec realtime cache (`sn_plausible_realtime_v2`) wasn't bumped — it ages out naturally too quickly to matter.

### Why patch (7.2.3)
Diagnostic addition + targeted bug fix. No new user-visible feature; existing widgets get smarter when something's wrong, and the most likely silent-failure mode for self-hosted CE installs gets defensively fixed. Patch 3 of 7.2; cap is 7 per minor.

## [7.2.2] — Plausible widgets: native WP admin styling + correct dashboard link

The four Plausible dashboard widgets shipped in v7.2.1 imported theme-front-end styling (Bebas Neue display font, DM Mono labels, blood-red accents, asphalt card backgrounds with a red left rail) into the WordPress admin. The admin is a shared surface — different themes and plugins coexist there, and users expect WP's own UI conventions, not theme-brand styling. The widgets read as foreign-pasted instead of native WP. Fixed.

### Changed
- **`inc/plausible-widget.php`** — inline CSS rewritten to WP admin conventions:
  - System font stack inherited from WP admin (no `font-family` override; previously forced `Bebas Neue` and `DM Mono`).
  - Numbers are bold + slightly larger but use the inherited admin font, matching the visual weight of native widgets like *At a Glance* and *Activity*.
  - Palette swapped to WP admin tokens: `#1d2327` primary text, `#646970` muted, `#f0f0f1` hairlines, `#d63638` error. Dropped `#000`/`#666`/`#e00404`/`#f5f5f5`.
  - Removed the asphalt card background and `border-left: 3px solid #e00404` on stat tiles — the brand left-rail belongs on the front end, not in the admin.
  - Removed `letter-spacing: 0.18em` + `text-transform: uppercase` on labels — WP admin doesn't use that treatment.
  - Realtime widget's giant red Bebas Neue numeral is now a bold `#1d2327` figure at 2.5rem.
  - Added `font-variant-numeric: tabular-nums` to breakdown-list values so visitor counts align vertically — small detail, makes the lists scan as native.
- **Dashboard footer link** in all four widgets now points to `admin_url( 'index.php?page=plausible_analytics_statistics' )` — the Plausible plugin's *own* embedded stats page inside WP admin — instead of constructing a public `https://plausible.io/{domain}` URL. Same surface the user is already authenticated on, no `target="_blank"`, no separate plausible.io login required. Arrow changed from `↗` (external convention) to `→` (internal navigation).

### Why patch (7.2.2)
Visual calibration on a v7.2.1 feature. No behaviour change to the data flow, the cache layers, the API client, the security module, or anything else — purely styling + a one-line URL swap that fits an existing admin route the user already had configured. Patch 2 of 7.2; cap is 7 per minor.

## [7.2.1] — Hardening pass + Plausible dashboard widgets + escaping cleanup

A QA / security pass against [WordPress's hardening guide](https://wordpress.org/documentation/article/hardening-wordpress/), plus a four-widget Plausible Analytics panel for the WP dashboard.

### Added
- **`inc/security-headers.php`** — empirically scoped against what Cloudflare's edge already covers for juanlentino.com (verified 2026-05-08 via `curl -I`), so the module only does work that *isn't* already happening at the edge:
  - **`/wp-json/wp/v2/users` 401 for anonymous requests.** This is the genuine fix — production was leaking `{"id":616000,"name":"Juan","slug":"juanlentino"}` to anyone hitting the endpoint, free reconnaissance for brute-force attackers. Implemented via `rest_authentication_errors` so authenticated callers (block editor, REST clients, the new Plausible widget proxy) keep working.
  - **`Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(), usb=()`.** Cloudflare's edge config wasn't sending one; this fills exactly that gap. The other common security headers (`X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, HSTS, full CSP) are already emitted at the edge — re-sending them from PHP would be redundant since CF proxies all traffic.
  - **Belt-and-suspenders fallbacks:** XML-RPC disabled via `xmlrpc_enabled` + `xmlrpc_methods` + `pings_open` + `header_remove('X-Pingback')`, and `?author=N` redirected to home for anonymous visitors. Both effectively no-op against the current Cloudflare config (XML-RPC already returns 520 at the edge, `?author=N` already returns 404 since no author archive is registered) but cost nothing and survive an edge config drift.
  - All four hardenings are individually filterable (`sn_security_permissions_policy`, `sn_security_lock_rest_users`, `sn_security_block_author_enum`, `sn_security_disable_xmlrpc`).
- **`inc/plausible-api.php` + `inc/plausible-widget.php`** — four discrete Plausible Analytics dashboard widgets, all reading from one shared 5-min cache:
  - **Last 7 days** — visitors / pageviews / bounce rate / average visit duration in a 2×2 brutalist tile grid.
  - **Right now** — large red Bebas Neue numeral of current visitors (Plausible realtime endpoint), 30-sec cache so it actually feels real-time.
  - **Top pages (7d)** — top 7 URLs by visitors.
  - **Top sources (7d)** — top 7 referrers, with `Direct / None` label for blank source values.
  - Reads `domain_name` + `api_token` from the Plausible plugin's existing `plausible_analytics_settings` option — no separate `wp-config.php` constant needed. Self-hosted Plausible is supported via the same option's `self_hosted_domain` key. Failure modes (plugin missing, token absent, API error) degrade to inline notices, never fatal.
  - One batched API fetch every 5 min covers all four "last 7 days" widgets; only the realtime widget makes a second round-trip (every 30 s).

### Fixed
- **`/notes` reading-time meta-key bug.** [`inc/page-notes-render.php:74`](inc/page-notes-render.php) read from `_sn_reading_time` but the canonical cache key is `_sn_reading_time_minutes` (defined as `SN_READING_TIME_META_KEY` in [`inc/reading-time.php:36`](inc/reading-time.php)). Result: every row in the /notes index missed the cache and recomputed `str_word_count` per render. Now reads through the constant with `sn_get_reading_time()` as the cache-populating fallback, so misses self-heal.
- **`/resume` duplicate "20+ Years".** Hero eyebrow read `Dossier · Background · 20+ Years` while the meta line below read `20+ Years · 50+ Collaborations · GRAMMY Voting Member` — the same tag in both places. Eyebrow trimmed to `Dossier · Background`, matching `/about`'s two-part `Dossier · Who I Am` form. The meta line keeps "20+ Years" since it anchors the credentials sequence.

### Changed
- **Admin notice escaping.** [`inc/admin-page.php`](inc/admin-page.php) was echoing notice severity + body unescaped. Severity now wrapped in `esc_attr()`; body in `wp_kses_post()` (some entries deliberately ship inline `<a>`/`<code>` markup so `esc_html` would mangle them). Theme-Version label in the dashboard status table now wrapped in `esc_html()` against the `Version:` header value.
- **`wp_unslash` on `$_POST` reads.** [`inc/admin-page.php`](inc/admin-page.php) and [`inc/cloudflare-purge.php`](inc/cloudflare-purge.php) read `$_POST['sn_action']` directly into `sanitize_text_field()` without unslashing first. Now `wp_unslash` ahead of sanitize, per WP coding standards. Cloudflare module refactored to a single sanitised `$posted_action` shared between the save and purge branches.
- **`esc_url()` on theme asset URLs.** [`inc/assets-frontend.php`](inc/assets-frontend.php) emitted `get_theme_file_uri()` directly into `<link href>` and `@font-face src` without escaping. `get_theme_file_uri()` returns a URL but isn't context-escaped — wrapping with `esc_url()` is the WP convention.

### Why patch (7.2.1)
Bug fixes + security cleanup + a hardening module + a new dashboard widget set. The widgets are visible new behaviour, but they're admin-only and additive; the security headers similarly add defensive output without changing what the site does. Per the project's convention (see 7.1.6 for prior art — accessibility/animation work landed as patch), this stays at patch level. Patch cap is 7 per minor; this is patch 1 of 7.2.

## [7.2.0] — /services № markers — breathing room

The catalog-number markers (`№ 01` through `№ 06`) on the /services cards rendered with only a 4px gap between the number and the card heading. The number read as part of the heading rather than as an eyebrow above it. Cause: the inline markup set `margin-bottom: 0` on the number paragraph and `margin-top: 0.25rem` on the heading.

Fix: bumped each number's `margin-bottom` from `0` to `var:preset|spacing|10` (8px) and removed the `0.25rem margin-top` from each heading. Result: ~12-16px gap between the number and heading on every card; image → number gap stays at the existing 16px. Numbers now read as proper eyebrows.

Why MINOR (rolling over from .7): the project's patch cap is 7 per minor (per CLAUDE.md). v7.1.0 through v7.1.7 used the full cap, so this calibration rolls to v7.2.0 even though it would normally be a patch.

## [7.1.7] — Remove scripture quotes from /404 and /contact

The 404 page carried Isaiah 30:21 ("Your own ears will hear him...") and the contact page carried Matthew 7:7 ("Keep on asking, and you will receive..."). Both removed — the brand voice doesn't otherwise lean on religious framing, so these read as out-of-register against the rest of the site. The pages keep their other editorial copy unchanged: the 404 still says "SIGNAL LOST / The frequency you're looking for doesn't exist..." and contact still has its existing dek about projects/sound/spam.

In the v7.1.6 notes I'd called the 404 quote "music-themed scripture" — that conflated two unrelated passes (the music-themed `SIGNAL LOST` line is editorial brand copy; the scripture is a separate thing) and I shouldn't have left the scripture in place when I touched the file. Sweeping for similar patterns elsewhere came up clean — no other scripture references in templates or seed content.

## [7.1.6] — Accessibility + 404 polish

Two findings from the design review:

### Fixed
- **Home hero animations now honour `prefers-reduced-motion`.** The site already gated block-level fade-ins behind `@media (prefers-reduced-motion: no-preference)` (in [base.css:135](assets/css/base.css:135) and [critical.css:116](assets/css/critical.css:116)), but the hero's own staggered cascade — `.sn-header`, `.sn-hero-title`, `.sn-hero-subtitle`, `.sn-hero-accent`, `.sn-hero-cta` — was declared outside that gate. Motion-sensitive users got a 1.8-second cascading fade-in on the FIRST screen they saw, with no way to suppress it. Wrapped all five animations in a single `prefers-reduced-motion: no-preference` block in both [critical.css](assets/css/critical.css) and [layout.css](assets/css/layout.css). Also consolidates the v7.1.4 `prefers-reduced-motion: reduce` block for `.sn-hero-accent` into the new gate (single source of truth).

### Added
- **404 page eyebrow.** Added an `Error · 404 · No Signal` `.sn-catalog-eyebrow` above the giant "404" headline in [templates/404.html](templates/404.html), bringing the page into the catalog vocabulary established in v7.1.0. The page's existing editorial copy ("SIGNAL LOST", music-themed scripture quote) didn't need changing — the eyebrow just gives it a small additional tonal anchor that ties it to the rest of the index pages.

## [7.1.5] — Revert hero accent to 120px editorial mark

v7.1.4 set the hero accent to `width: 100%; max-width: 640px;` to make it match the dek's max-width and read as an underline. In practice it overshot: the dek wraps at natural word boundaries (well before its 640px max), so the accent always ended up wider than the visible text on either line. The "underline" reading didn't land, and the editorial-mark reading from v7.1.0 was lost.

Reverting to `width: 120px;` fixed. The 120px length was correct as an editorial flourish — a stamp beneath the dek — which is what fits the brutalist hero. Keeping the CSS-class form (no inline styles) and the `prefers-reduced-motion` rule from v7.1.4. Comment in the CSS documents the failed attempt so it doesn't get re-tried.

Lesson: container `max-width` ≠ rendered text width. When you want a graphic to align with text, you need to measure the text (which CSS doesn't expose), not the container. Better to commit to the editorial-mark interpretation than to half-implement an underline.

## [7.1.4] — Home hero accent: responsive, matches dek width

The blood-red accent rule on the front page was hardcoded to `120px` wide via inline style — read as a small editorial mark next to a 640px-wide dek. Made it responsive: `width: 100%; max-width: 640px;` so the accent's right edge now lands at the same point as the dek's right edge ("together."). On narrower viewports both shrink together (100% of the available column width), so the accent always reads as the dek's underline rather than a floating mark.

Implementation moves the styling out of inline-on-the-element and into [layout.css:247](assets/css/layout.css:247) where it can express the constraint (`max-width: 640px` matches `.sn-hero-subtitle`'s own `max-width: 640px` from the same file). Honours `prefers-reduced-motion` for the existing fade-in.

## [7.1.3] — Catalog rollout: skipped items + redundancy fix

Three small follow-ups to v7.1.0–v7.1.2:

### Fixed
- **`/about` secondary eyebrow** read `Education & Mentorship · Pass-On` directly above the heading `What I Know, I Pass On.` — the "Pass-On" repeated immediately on the next line. Trimmed the eyebrow to just `Education & Mentorship`.

### Added
- **`/resume` meta line** below the dek paragraph using `.sn-catalog-meta`: `20+ Years · 50+ Collaborations · GRAMMY Voting Member`. Pattern matches the meta-line on /notes ("N entries · last updated YYYY.MM.DD"). All three values come from existing copy on the live site (the /resume dek itself, the /about bio, the /services credibility strip), so no invented facts.
- **`/music` Muso.AI section-marker** — converted the `.sn-catalog-eyebrow` from v7.1.0 (`Full Discography · Verified Credits`) into a full `.sn-catalog-section` block with hairline border. The Muso.AI panel now reads as a labelled section inside the right column rather than a standalone caption.

These were items I'd called out in the catalog audit but skipped during the v7.1.0 rollout. Closing the gap.

## [7.1.2] — Drop unverified location from /about eyebrow

In v7.1.0 the catalog rollout introduced a hero eyebrow on /about that read `Dossier · Buenos Aires → Miami · Who I Am`. The "Miami" was an invented geographic claim (no source in the existing copy) and the user's actual current city isn't settled, so the location shouldn't appear in load-bearing identity copy at all. Reverting the eyebrow to a non-geographic form: `Dossier · Who I Am`.

Lesson: when applying a design vocabulary that wants "specificity" in the eyebrow, the specificity must come from existing copy or from facts the user has already published, not from filling in plausible-sounding details to make the line denser. /resume's `Dossier · Background · 20+ Years` and /music's `Catalog · Discography · 2005 → 2026` reused values that were already on the live site; /about's geographic interpolation didn't, and shouldn't have.

## [7.1.1] — Eyebrow alignment fix

The catalog eyebrows on `/about`, `/services`, `/music`, `/resume` rendered against the viewport's far-left edge instead of centered with the rest of the constrained content. Cause: `.sn-catalog-eyebrow` and `.sn-catalog-meta` in [components.css](assets/css/components.css) used `margin: ... !important` shorthands which silently set `margin-left: 0 !important` and `margin-right: 0 !important`, overriding WP's constrained-layout rule that centers children via `margin-left: auto !important; margin-right: auto !important;`.

Fix: specify only `margin-top` / `margin-bottom` so the inline-margin auto-centering rule still wins. Added a comment in the CSS explaining the gotcha so it doesn't get reintroduced. Section labels and counts kept `margin: 0` because they're inside a grid container — margins don't participate in grid placement, so the bug wouldn't have manifested there anyway.

Lesson: when adding CSS that targets WP block-rendered children of a constrained layout, never use a margin SHORTHAND with `!important`. Either use `margin-top` / `margin-bottom` only, or specify `margin-inline: auto !important` explicitly to participate in the centering rule.

## [7.1.0] — Catalog vocabulary rollout

The "Industrial Catalog" design vocabulary developed for `/notes` extends across the site's index/listing surfaces. New shared CSS components in [assets/css/components.css](assets/css/components.css) — `.sn-catalog-eyebrow`, `.sn-catalog-meta`, `.sn-catalog-section` (label + count), `.sn-catalog-number` — replace the ad-hoc blood-mono-caps eyebrow patterns each page reinvented at slightly different sizes. The vocabulary is applied selectively: index pages get the full treatment, action/CTA pages stay untouched.

### Why minor

User-visible design changes across five page templates plus a new shared CSS surface. No public API or settings schema change. Per the project's SemVer policy this is a `MINOR` bump (`new user-visible capabilities`), continuing the v7 series.

### `/services` — Tier 1 full treatment
- Hero eyebrow: `What I Do` → `Services · 06 Offerings · 02 Sections` (catalog-eyebrow class).
- The two blood-uppercase `<h2>` section headings (`Music & Production`, `Business & Strategy`) replaced with `.sn-catalog-section` blocks — small mono caps label, hairline divider, count counter (`04 / 06`, `02 / 06`).
- Each of the six service cards now carries a `№ 01` through `№ 06` mono-blood marker above its heading, sized 0.85rem to read as quiet meta rather than competing with the heading.
- The four blood-eyebrow elements existed at three different sizes (0.75rem, 0.85rem) before — now unified through the component.

### `/provenance` — Tier 1 numbered pillars
- The two pillar cards in `sn_provenance_papers_index_markup()` now lead with a `№ 01` / `№ 02` catalog-number marker, mirroring the /notes pillar treatment for visual continuity between the two index pages.
- New constant `SN_PROV_CATALOG_NUMBERS_OPT` and migration `sn_migrate_provenance_catalog_numbers()` re-renders existing installs' pillar body — without it, the markup-function update would only take effect on fresh installs because earlier migrations' flags lock in the prior shape.
- Seed file [inc/seed-content/provenance-body.html](inc/seed-content/provenance-body.html) updated in lockstep so fresh installs ship the same shape.
- Defensive: gated on the SSRN abstract_id 6730343 anchor; if missing, admin has hand-edited away from seed shape and the migration bails without flagging so a future run can complete after recovery.

### Tier 2 — mono hero eyebrows
- `/resume`: `Background` → `Dossier · Background · 20+ Years`.
- `/music`: `Listen` → `Catalog · Discography · 2005 → 2026`. Secondary `Full Discography` → `Full Discography · Verified Credits`.
- `/about`: `Who I Am` → `Dossier · Buenos Aires → Miami · Who I Am`. Secondary `Education & Mentorship` → `Education & Mentorship · Pass-On`.

### Not changed
- `/` (front page) — landing/identity energy, not browse energy.
- `/contact`, `/work-with-me` — action pages, different tone.
- `/notes/{slug}/`, `/provenance/over-detection/`, `/provenance/as-substrate/` — long-form reading surfaces, not catalog surfaces.

## [Unreleased] — Operational fixes (post-v7.0.0)

### `/notes` rebuilt from scratch — PHP-rendered, redesigned

After three incidents in two months where `/notes` rendered stale content despite the canonical version being correct in `main` (deploy silently skipping the file, broken self-heal corrupting it, stale `wp_template` DB row surviving the one-shot migration), the page is now rendered entirely from PHP via `template_include`. WordPress's block-template resolution chain — file ↔ DB ↔ object cache ↔ registry — never runs for this route. Filter approach from previous commits (118336b, cb055eb) replaced; those filters had to fight every layer of the resolution chain and lost when an unexpected layer cached the wrong version.

#### Architecture

- **[inc/page-notes-template.php](inc/page-notes-template.php)** — `template_include` filter at priority 999 short-circuits to our render file when `is_page('notes')`. Defensive: if the render file is missing for any reason, falls through to WP's normal resolution (which uses `templates/page-notes.html` as a kept-on-disk fallback with the correct two-card content). Also runs an `admin_init` sweep to delete any stale `wp_template` DB row for `page-notes` — keeps the Site Editor template list clean and prevents the row from re-appearing.
- **[inc/page-notes-render.php](inc/page-notes-render.php)** — full PHP renderer. Builds the entire HTML document from scratch: `wp_head()`, `body_class()`, `wp_body_open()`, the existing `header` block template part, the page body, the existing `footer` block template part, `wp_footer()`. Inline `<style>` block in the document head so the rendering and the design ship together as a single atomic unit — if the file deploys, the whole page deploys; if it doesn't, the fallback in `templates/page-notes.html` takes over.

#### Design — "Industrial Catalog"

The page now reads as a directory listing for the brand, like a library card catalog or a vinyl-store archive. The aesthetic stays inside the existing brutalist white/asphalt/blood vocabulary but adds editorial precision:

- **Hero** — `INDEX · VOL. 01 · {year}` eyebrow in mono caps, oversized "Notes." display headline (clamp 4-11rem), dek line, entry count + last-updated date in a meta line.
- **Pillar essays — numbered** (`№ 01`, `№ 02`) in mono blood-red on a light asphalt card with a 6px-wide blood-red left rail. The rail expands to 14px and the card translates 2px on hover — a subtle physical-feeling response. CTA is a tracked uppercase "Read essay →" with the arrow shifting on hover.
- **Notes index — tabular**. Each row is a 2-column grid on desktop: `[140px date+meta col] [1fr title+excerpt col]`. Date renders as `2026.05.07`, reading time as `03 MIN` (zero-padded for tabular alignment with the date). Title in Bebas Neue display, excerpt in body grey. Title link uses an animated underline that fills from 0 to 100% on hover.
- **RSS footer — terminal status line**. Mono caps `Feed — /notes/feed/` followed by a blinking blood-red cursor (`@keyframes sn-blink` at 1.05s, steps(2)). Subtitle `No subscription form. No schedule.` underneath.
- **Page entry** — staggered reveal animation on first paint (cubic-bezier ease, 12px translateY, 60ms cascade across the six top-level sections). Honours `prefers-reduced-motion`.

The 140px main `padding-bottom` from the prior layout fix still applies for fixed-footer clearance — verified the new RSS line sits well above the footer.

#### Trade-off

`/notes` can no longer be edited via Site Editor — the canonical layout lives in `sn_render_notes_page()` (PHP). Given the page has only ever been edited by code commits in practice, this trade is correct: removing the editing surface removes the failure mode.

### `/notes` template now PHP-authoritative

Even after `padding-bottom: 140px` shipped (proving 7ad2dd8 reached disk) and the post-update force-run self-heal hook fired, `/notes` STILL rendered only the first pillar card. PHP `wp-template;dur=50ms` confirmed live-render under `x-cache: MISS` — meaning the renderer was producing single-card output FRESH, not from a cache. Three layers could be responsible: (1) `templates/page-notes.html` on disk still stale despite multiple deploys, (2) a `wp_template` DB override that survived `sn_clear_template_overrides()`, or (3) a registry/object-cache holding a parsed block tree.

The fix sidesteps all three: we hook `pre_get_block_template` and return a `WP_Block_Template` object built from a PHP heredoc literal in [inc/page-notes-template.php](inc/page-notes-template.php). That filter runs BEFORE WP's DB-then-file resolution chain — DB override can't win, file drift can't win, registry cache can't go stale because PHP rebuilds the object from the literal on every call. The `templates/page-notes.html` file is kept for Site Editor preview parity and as a reference, but is no longer load-bearing for front-end rendering.

This is the third incident where `templates/page-notes.html` has been the source of stale-content drift on `/notes` (2026-04 deploy-skip, 2026-05 corrupt-self-heal, 2026-05 mystery-still-stale). Pulling it out of the rendering path eliminates the surface entirely.

#### Added
- **[inc/page-notes-template.php](inc/page-notes-template.php)** — new module with `sn_page_notes_template_content()` returning canonical block markup, `sn_page_notes_build_template_object()` constructing a `WP_Block_Template` matching the shape WP's `_build_block_template_result_from_file()` produces (so consumers — rendering pipeline, Site Editor, REST API — see no behavioural difference). Registered on `pre_get_block_template` filter (front-end / single-template lookups) AND `get_block_templates` filter (Site Editor template list endpoint) so the editor's template picker reflects what the front-end actually renders.
- **functions.php** require_once added between `template-self-heal.php` and `admin-page.php` so the filter is registered before any block-template lookup.

#### Editing /notes layout going forward
Edit the heredoc in `sn_page_notes_template_content()` in [inc/page-notes-template.php](inc/page-notes-template.php). The .html file is no longer authoritative.

### Recovery hardening — self-heal force-run + RSS layout fix

Two unrelated production issues that surfaced post-v7.0.0:

1. `/notes` was rendering the OLD single-pillar-card content for hours despite (a) `main` having the correct two-card content, (b) the deploy reporting success, (c) `wp_template` DB overrides at zero, and (d) Cloudflare reporting `cf-cache-status: DYNAMIC`. The theme self-heal module (added in [390c14b](https://github.com/juanlentino/signal-and-noise/commit/390c14b)) was designed for exactly this failure mode but had two gaps: its 5-minute rate-limit option was set by the broken initial run (which corrupted templates with JSON; fixed in [7c820ec](https://github.com/juanlentino/signal-and-noise/commit/7c820ec)), blocking the FIXED self-heal from running again; and its only trigger was ambient `admin_init` pageviews, so recovery wasn't immediate after clicking Update. There was no manual button to force a re-sync — recovery required SSH/SFTP or waiting for the rate-limit to expire.

2. The RSS link at the bottom of `/notes` was unreachable — the fixed-position `.sn-footer` (z-index 9990) was overlapping the last lines of `<main>` because the `padding-bottom: 90px` buffer was too tight. On desktop the buffer was just enough (~14px clearance over a ~76px footer); on mobile the footer wraps to two rows (~120px tall) and ate 30px of the RSS line.

#### Added
- **`sn_self_heal_force_run()`** in [inc/template-self-heal.php](inc/template-self-heal.php). New entry point that bypasses the 5-min rate-limit gate AND clears the per-file failure cooldown, so files in 1-hour back-off get retried immediately. The original `sn_self_heal_run()` (ambient `admin_init` path) and this force-run path now share an internal `sn_self_heal_execute( $force )` implementation — same validation gates apply to both. The 7 content-shape gates from [7c820ec](https://github.com/juanlentino/signal-and-noise/commit/7c820ec) (HTTP 200, JSON parse, content+encoding fields, base64 decode, size match, starts-with-`<` for HTML files, differs from local) are unchanged; force-run only changes WHEN the check happens, never WHAT gates the write.

- **"Heal Templates Now" admin button** on the Dashboard tab in [inc/admin-page.php](inc/admin-page.php). One-click manual recovery for the "deploy didn't take effect on a route" failure mode. Calls `sn_self_heal_force_run()` synchronously and reports per-file results in admin notices: success notice lists the paths that were re-synced; error notice lists paths where drift was detected but the write failed (with a hint to check SFTP file permissions). Sits next to *Purge Caches* in the Actions row.

- **Post-update auto-heal hook** in [inc/updater.php](inc/updater.php). Hooks into `upgrader_process_complete` at priority 20 (after the existing SHA-stash hook at priority 10). Force-runs `sn_self_heal_force_run()` immediately after every successful theme update, so every Update click ends with a verified file-content sync against `main` HEAD. Closes the loop on the original silent-skip failure mode: the file system either matches `main` or admin sees an error notice naming exactly which paths failed.

#### Fixed
- **`sn_purge_all_caches()` now clears self-heal state.** New `self_heal_state` flag (default `true`) deletes the `sn_self_heal_last_check` rate-limit option and the `sn_self_heal_failures` cooldown map. These are stored as regular options, so the existing `_transient_sn_*` SQL DELETE didn't reach them. Closes the surprising user-experience gap where clicking *Run Full Reset* didn't actually unblock a stuck self-heal run. Both options are gated on `defined()` of their option-name constants, so the helper stays safe if the self-heal module is ever disabled.

- **RSS link layout on `/notes`.** Bumped `main.wp-block-group { padding-bottom }` from `90px` to `140px` in [assets/css/layout.css](assets/css/layout.css). Sized for the worst case (mobile-wrapped footer ≈ 120px) plus a 20px buffer. Comment in the file documents the constraint so a future "this padding looks excessive" cleanup pass doesn't re-introduce the bug. Universal fix — every page benefits, but `/notes` was where the failure manifested because `.sn-notes-rss` is the last element in `<main>` and sat directly inside the previous overlap zone.

### Earlier in this Unreleased band

Two related fixes that surfaced after the v7.0.0 deploy: (a) `/notes` continued to render the old single pillar card after the user clicked Update, despite `wp_template` DB overrides being cleared and the theme files being correctly replaced, because Breeze's HTML page cache wasn't being invalidated on theme file changes; (b) the admin maintenance page accumulated four sections in a single Dashboard tab and grew unwieldy — Cloudflare config + Reading Time cleanup + Status + Actions all stacked vertically.

### Fixed
- **`sn_purge_all_caches()` unified helper** in [inc/template-maintenance.php](inc/template-maintenance.php). Single source of truth for "make sure no stale rendered HTML or stale metadata is being served anywhere". Covers WP object cache, theme metadata cache, our own `sn_*` transients (targeted DELETE — leaves plugin transients alone), Breeze + Varnish via plugin action hooks, Cloudflare zone via the new purge module, DB template overrides via `sn_clear_template_overrides()`, and an `update_themes` repopulate so the Updates page renders correct state. Accepts a flags array for partial flushes (e.g., `'template_overrides' => false` for a "purge caches but keep my Site Editor edits" semantic).
- **All theme-file-change triggers now use the unified helper.** Three call sites previously ran *subsets* of the necessary clears: (1) `upgrader_process_complete` only cleared DB overrides, leaving Breeze stale; (2) the Version-compare check on `admin_init` cleared object cache + overrides but not Breeze; (3) the new mtime check (v7.0.0) only cleared overrides. All three now call `sn_purge_all_caches()`. The "/notes still showing one card" symptom after v7.0.0 deploy resolves because the upgrader hook now flushes Breeze synchronously during the install.
- **Admin "Purge All Caches" and "Full Reset" buttons** now thin wrappers over the unified helper. "Purge All Caches" passes `template_overrides => false` so it doesn't nuke admin Site Editor edits; "Full Reset" lets the helper run with all defaults including overrides. Behavior identical for users; less duplicated code.

### Changed
- **Admin maintenance page split into four tabs.** Dashboard (status + actions only), Cloudflare (token + zone + status + manual purge), Reading Time (legacy cleanup tool), Links (existing). Each subsystem gets its own dedicated action hook (`sn_admin_cloudflare_tab`, `sn_admin_reading_time_tab`) so the module that owns the logic also owns the UI, colocated. The legacy `sn_admin_dashboard_extras` hook still fires on the Dashboard tab for backward compatibility with any third-party additions.
- **Removed redundant section headings** from the Cloudflare and Reading Time tab bodies — the tab name in the nav serves as the section label, so internal `<h2>Cloudflare</h2>` was just visual noise.

### Added
- **Admin bar quick-action dropdown** (new module [inc/admin-bar.php](inc/admin-bar.php)). Top-bar "S&N" menu with one-click access to the maintenance actions that previously required navigating to the Signal & Noise dashboard: *Purge All Caches*, *Clear DB Overrides*, *Purge Cloudflare* (only shown when configured), *Check for Updates*, plus a link back to the full dashboard. Available from any admin page AND from the front-end (when the admin bar is shown). Each action runs over admin-ajax with a per-action nonce, with a toast notification confirming success/failure — no page navigation, no scroll loss, no form-state loss. Capability gate on `manage_options` (server-side double-check); items aren't rendered for users without that capability. JS uses `textContent` (not `innerHTML`) when manipulating link labels so a future server-side bug in the response can't escalate to XSS.

- **Template file self-heal** (new module [inc/template-self-heal.php](inc/template-self-heal.php)). Safety net against the failure mode where the WP self-updater extracts a new theme zip but silently misses some files (a Cloudways file lock, a permission issue on a specific path, etc.). On 2026-05-07 this exact failure pattern left `templates/page-notes.html` stuck at the pre-cbe3ee5 single-pillar-card content for hours despite multiple successful theme updates — every other theme file updated cleanly, but the rendering of `/notes` kept showing OLD content because the file on disk didn't match what was in `main` HEAD on GitHub. Diagnosis was slow because the failure was silent: no error logged anywhere, no cache layer involved (`cf-cache-status: DYNAMIC`, no Breeze x-cache header), PHP was actively rendering from a stale file on disk. The module makes this class of failure recoverable without SSH/SFTP intervention.

  **How it works:** on `admin_init` (rate-limited to 5-min intervals via an option), the module iterates a whitelist of theme files (default: every `.html` under `templates/` and `parts/`, filterable via `sn_self_heal_files`). For each file, it fetches the canonical version from GitHub via the Contents API using the existing `SN_GITHUB_TOKEN` (with `Accept: application/vnd.github.v3.raw` so GitHub returns raw file bytes rather than the base64-in-JSON wrapper). Byte-for-byte comparison against the local file. On drift, the module overwrites the local file using `WP_Filesystem` — the same write API the WP self-updater itself uses, so anything WP can write, this can write. After any successful write, fires `sn_purge_all_caches()` so the new content is served immediately rather than waiting for the next deploy-time cache invalidation.

  **Defensive properties:** rate-limited (one set of GitHub calls per 5 min, well under API rate limits); per-file failure counter that backs off for 1 hour after 3 consecutive write failures (so a permission-locked file doesn't retry-storm); whitelist-based scope (won't ever touch random theme files); `manage_options` capability gate plus `admin_init`-only (never runs on front-end); graceful degradation if `SN_GITHUB_TOKEN` isn't set or GitHub is unreachable. Admin notices on every check that performed a write or encountered a failure, so the module's activity is visible — successes show *"updated N theme file(s) from GitHub"* in green; failures show the failed paths with retry-cooldown info in red.

  **Why this isn't overreach:** the module only re-syncs files to match what's already in `main` — it doesn't create files, doesn't modify config, and the canonical source is the same Git repository the WP self-updater is already pulling from. The net effect is "deploys are now self-correcting if they silently skip a file". Future-proofing against an unsolved class of failure with a small, scoped, opt-out-able mechanism (filter the whitelist down to `[]` to disable).

## [7.0.0] — 2026-05-07

**Post-incident hardening + new capabilities.** Marks the architectural shift to *"decorative work never blocks essential rendering"* after a `/notes` outage on 2026-05-07 was traced to a UTF-8 truncation loop in the OG card generator that pinned PHP-FPM workers at 100% CPU. The fix to that specific bug is necessary but not sufficient — the deeper change is structural: lazy-on-request synchronous OG generation is gone, replaced with proactive backfill in admin contexts; CI smoke tests catch regressions before users notice; Cloudflare HTML caching with auto-purge reduces origin load and improves global TTFB; mtime-based template-override clear self-heals on every deploy regardless of `Version:` bump policy.

Plus the second long-form companion essay landed at `/provenance/as-substrate/`, both pillar essays now surface directly on `/notes`, and assorted bug fixes (eyebrow drift, byline date `displayType` bug, pillar Card 2 longform link, render_block filter for slug-attributed shortcode in templates).

**Why a major bump.** Per `CLAUDE.md`: minor cap is `.5` per major; we were at `6.5`. The accumulated user-visible capabilities (Cloudflare admin UI, CI/monitoring infrastructure, smoke test workflow, two pillar essays surfaced on `/notes`) plus the architectural shift in defensive posture make a coherent v7.0.0 milestone. No public API was removed or renamed — this isn't a SemVer-MAJOR-by-breakage; it's the project's own minor-cap rule rolling at the natural milestone.

### Highlights

- **`/notes` hang root-caused, fixed, and architecturally hardened.** UTF-8 byte-vs-character bug in `sn_og_wrap_lines()` truncation loop fixed with `mb_substr` + `$guard` ceiling. OG card generation is now non-blocking on the request path; cards generate proactively via `wp_after_insert_post` and one-time backfill, never lazily on cache-miss. (`e006841`, `3645cc3`)
- **CI smoke test workflow** at [.github/workflows/smoke-test.yml](.github/workflows/smoke-test.yml). PHP lint + 6-route live check on every push and 15-min schedule. Catches regressions within 15 seconds. (`38cc5b0`)
- **Cloudflare HTML caching support.** New [inc/cloudflare-purge.php](inc/cloudflare-purge.php) module: configurable token + zone (constants or admin UI), auto-purge on post save and theme update, manual purge button, last-purge timestamp display. New [docs/CACHING.md](docs/CACHING.md) with full Cache Rule setup. (`0e9518a`)
- **Two pillar essays surfaced on `/notes`.** Cards link to on-site long-forms (not SSRN); read-times pulled dynamically via `[sn_reading_time slug="..."]` so figures stay in sync with the cached value on each long-form post. (`cbe3ee5`)
- **`/provenance/as-substrate/` long-form** — companion to SSRN paper 2 ("Provenance as Substrate: A Cryptographic Identifier Framework for Music Rights and Royalty Infrastructure", Abstract 6730343). Six anchored sections, paired SVG analogy diagram (envelopes-with-tags ↔ file-with-fingerprint), cost-scaling SVG in Section 6. (`73082e6` + `b841daf` + `28a0cde` + `2ca4d1c`)
- **Self-healing template-override clear.** mtime-based detection in [inc/template-maintenance.php](inc/template-maintenance.php) closes the gap where template-only deploys (no `Version:` bump) didn't trigger `wp_template` DB override clears, leading to silent stale-template rendering on `/notes`. Now self-heals on every admin pageview after any `templates/*.html` or `parts/*.html` change. (`0e9518a`)
- **Pillar card read-times made dynamic** via new `slug` attribute on `[sn_reading_time]`. Single source of truth across `/provenance`, `/notes`, and each long-form's byline. (`949007e`, `cbe3ee5`)
- **Operational documentation.** New [docs/MONITORING.md](docs/MONITORING.md) covers all four monitoring tiers (architectural, CI smoke, Uptime Kuma, future) with copy-pasteable Uptime Kuma monitor config and incident-response checklist that routes through the `superpowers:systematic-debugging` skill.

### Original detailed entries follow

The original commit-by-commit entries are preserved below in the order they were written during the 2026-05-07 session. They remain useful as audit trail and cross-reference for the migration option flags introduced.

---

Ship the second long-form companion essay at `/provenance/as-substrate/` — the web-adapted, jargon-free version of SSRN paper 2 ("Provenance as Substrate: A Cryptographic Identifier Framework for Music Rights and Royalty Infrastructure", Abstract 6730343). Mirrors `/provenance/over-detection/` block-for-block: same hero/eyebrow/TOC/section/byline structure, same diagram block treatment, same footer CTA pair, same dynamic byline + reading-time block. Six anchored sections (`#setup`, `#analogy`, `#what-it-is`, `#why-it-matters`, `#the-shift`, `#economics`) match the first long-form's pattern.

### Added
- **New seed file** at [inc/seed-content/as-substrate-body.html](inc/seed-content/as-substrate-body.html). Hero with `[sn_reading_time]` shortcode in the eyebrow (single source of truth — no manual minute counts), six properly-wrapped `<section class="sn-provenance-section">` groups, paired SVG diagrams in Section 2 (administrative-codes envelopes-with-drifting-tags ↔ cryptographic-identifiers file-with-fingerprint, both 240×180 viewBox, line-art aesthetic, `sn-provenance-svg-accent` for blood-color fills on circles/lines per the existing CSS contract), a single-panel cost-scaling SVG in Section 6 (two-axis line chart: administrative cost rising linearly versus cryptographic cost staying flat — flat line uses the accent class for blood-color emphasis on the punchline; grid is inline-overridden to `1fr` with `max-width:340px;margin:0 auto` so the existing paired-grid CSS still drives layout for both diagrams), footer CTA row, byline with `displayType:"modified"` post-date + `[sn_reading_time]`.
- **`sn_ensure_as_substrate_page()`** in [inc/notes-and-provenance.php](inc/notes-and-provenance.php). Parallel to `sn_ensure_over_detection_page()` — creates the new child page under `/provenance` with `post_parent` set, `page_template` = `page-provenance`, post excerpt populated for the meta description, idempotent on re-run.
- **`sn_load_as_substrate_body()`** loader for the new seed file. Same fallback semantics as the existing `sn_load_over_detection_body()` — empty string if the file is missing, so the template renders an empty post-content area instead of fatalling.
- **`sn_migrate_as_substrate_seed()`** + `SN_AS_SUBSTRATE_SEED_OPT` flag. One-time migration on `admin_init` for installs whose `SN_SEED_FLAG_OPTION` was already set before this page existed (i.e. every production site since v6.0). The main `sn_seed_content_surfaces()` flow short-circuits on those installs, so the new ensure-call needs its own gate. Idempotent: bails if the dedicated flag is set; bails (without flagging) if the parent page doesn't yet exist so the next admin_init can complete it after the parent lands.
- **`SN_AS_SUBSTRATE_SLUG` constant** alongside the existing slug constants for consistency with the established naming convention.
- **Pillar-page Card 2 wired up to the long-form.** `sn_provenance_papers_index_markup()` updated so Card 2 mirrors Card 1's full pattern: `MAY 2026 · 5 MIN READ` in the meta line (hardcoded to match Card 1's existing precedent — the long-form is evergreen so this won't drift), and a discreet `Read the long-form on this site →` affordance pointing at `/provenance/as-substrate/`. Visual asymmetry between the two cards is now resolved — both have on-site equivalents, both surface the affordance.
- **`sn_migrate_provenance_card2_longform()`** + `SN_PROV_CARD2_LF_MIGR_OPT` flag. One-time migration that rewrites the live pillar page's body via `sn_provenance_papers_index_markup()` so existing installs (where v6.5.4's `SN_PROV_SPLIT_MIGR_OPT` was already set) pick up the new Card 2 markup. Defensive: gates on the SSRN abstract_id 6730343 anchor — if the live body doesn't contain that marker, admin has hand-edited away from the seed shape, so the migration bails *without* setting the flag (allowing a future run to complete after manual recovery, matching the pattern in `sn_migrate_provenance_split()`). Self-idempotent: if the body already contains the `/provenance/as-substrate/` URL, sets the flag and exits.
- **`[sn_reading_time]` shortcode now accepts an optional `slug` attribute** in [inc/reading-time.php](inc/reading-time.php). No-args form keeps the legacy current-post behaviour unchanged; `[sn_reading_time slug="path/to/page"]` resolves a different post via `get_page_by_path()` and reports its cached reading time. The render_block bridge filter at [reading-time.php:148](inc/reading-time.php:148) was loosened from exact-match `[sn_reading_time]` to prefix-match `[sn_reading_time` so both forms route through `do_shortcode()`. Returns empty string if the slug-targeted post doesn't exist (graceful during the migration window).
- **Both pillar cards now use the dynamic shortcode** for read-time meta. `sn_provenance_papers_index_markup()` Card 1 reads `March 2026 · [sn_reading_time slug="provenance/over-detection"]` and Card 2 reads `May 2026 · [sn_reading_time slug="provenance/as-substrate"]`. Eliminates the hardcoded-vs-byline drift the live site exhibited (pillar Card 1 said "4 min read" while the over-detection byline said "5 min read" because the byline was dynamic and the card was a hand-typed estimate from before edits).
- **`sn_migrate_provenance_card_readtimes_dynamic()`** + `SN_PROV_RT_DYNAMIC_OPT` flag. New one-time migration that rewrites the live pillar body so existing installs pick up the dynamic shortcode form for both cards. Same defensive pattern as the prior pillar migrations: gates on the SSRN abstract_id 6730343 anchor; self-idempotent on the `[sn_reading_time slug=` marker; needed because `SN_PROV_CARD2_LF_MIGR_OPT` from the previous push has already flagged the older migration complete on production.

### Fixed
- **As-substrate byline date was rendering empty.** The seeded byline used `displayType:"modified"` on the `wp:post-date` block (mirrored from over-detection), but WordPress core's `render_block_core_post_date()` returns null when `displayType === 'modified'` *and* `post_modified === post_date`. Newly-inserted posts have those equal, and as-substrate is evergreen by maintainer convention — it never gets edited — so the byline date stayed permanently empty. Both the seed file and a new migration (`sn_migrate_as_substrate_post_date_displaytype()` + `SN_AS_DATE_DISPLAYTYPE_OPT`) drop the `displayType` attribute, defaulting the block to publish-date display (always renders). Defensive str_replace: bails *without* flagging if the precise attribute pattern doesn't match (admin has touched the post-date block separately).
- **Over-detection eyebrow drift.** Live page still showed `A short read · 4 min` (hardcoded since v6.5.3) while the byline shortcode computed `5 min read` — within-page mismatch the user pointed out. v6.5.4's seed already simplified the eyebrow to `A short read` only, but the live page wasn't migrated. New migration `sn_migrate_over_detection_eyebrow_dynamic()` + `SN_OD_EYEBROW_DYN_OPT` does a precise regex swap: `A short read · N min[ read]` becomes `A short read · [sn_reading_time]`, matching the as-substrate seed shape and ensuring eyebrow + byline always agree. Defensive on the pattern match — bails without flagging if admin has already customised the eyebrow.
- **`/notes` hang.** Reverted the `render_block` filter's prefix-match variant introduced in 949007e back to the original exact-match `[sn_reading_time]`. The prefix-match (`[sn_reading_time` without the closing bracket) was a misdiagnosis on my part — the actual root cause turned out to be in [inc/og-image.php](inc/og-image.php) (see next entry). The revert is still appropriate (the prefix-match wasn't necessary; slug-attributed shortcodes inside post_content resolve via WordPress core's `the_content` filter chain at priority 11), but it didn't fix `/notes`. Documented the diagnostic mistake in the next entry's "lessons" note so the next iteration doesn't repeat it.

- **Real `/notes` hang root cause: UTF-8 corruption infinite loop in `sn_og_wrap_lines()` ([inc/og-image.php:213-220](inc/og-image.php:213)).** Symptom: `/notes/{slug}/` and `/notes/` server-side hang for 60+ seconds with zero response bytes; only specific posts affected; REST API and RSS feed (`/notes/feed/`) work normally; other pages render fine. Diagnosis trail (recorded so future me can replay it): (1) `/notes/feed/` works in 0.3s — proves the data layer and post query are fine; (2) the WP REST API renders all post content correctly — proves `the_content` filter chain works; (3) testing each individual note URL revealed that exactly two posts hang and three render normally; (4) checking `/wp-content/uploads/sn-og/post-{id}.png` for each post showed the **two hanging posts had no cached OG card (404), the three working posts had cached cards (200)**; (5) reading [og-image.php:74-103](inc/og-image.php:74) showed `sn_og_image_url_for_post()` synchronously calls `sn_generate_og_card()` when the cache is missing, via `wpseo_opengraph_image` and `sn_og_image_url` filters that fire on every page render through `wp_head`; (6) reading [og-image.php:191-231](inc/og-image.php:191) showed the truncation loop in `sn_og_wrap_lines()` used `substr($rest, 0, -1)` (byte-based) on a `$rest` value that already ended with `…` (UTF-8 0xE2 0x80 0xA6, 3 bytes — and `wp_trim_words(..., 36, '…')` at line 163 always appends one). Each iteration stripped only the last byte (0xA6), leaving 0xE2 0x80 dangling; `rtrim($rest, ".,;:!? \t")` couldn't remove those bytes (not in its strip set); a fresh `…` was appended; net effect was the string grew by 2 bytes per iteration, the loop never terminated, and PHP execution-time-limit (or memory) eventually killed the worker after the user's HTTP timeout. **Fix:** track `$core` (without ellipsis) separately, shrink with `mb_substr( $core, 0, -1, 'UTF-8' )` (character-aware), and reconstruct `$rest = $core . '…'` each iteration so no encoding state carries between iterations. Added defensive `$guard < 1000` ceiling — for any reasonable text the loop terminates in <300 iterations, but the guard ensures even unforeseen edge cases can't repeat the hang.

- **Why posts split into "hangs" vs "works".** Posts whose excerpt fits in `$max_lines` (3) without overflow never enter the truncation loop — that's the working group. Posts where the excerpt exceeds 3 lines hit the loop, and on iteration 2+ corrupted the UTF-8 — that's the hanging group. The bug had been latent in the codebase since [og-image.php was added in v6.3.2](CHANGELOG.md) (Mar 2026); it surfaced now because the user's recent posts have longer prose that triggers truncation. **Lesson for next iteration:** when a hang appears post-shortcode-change, check whether the change actually touches the hanging code path before reverting things. The shortcode prefix-match (949007e) was on the wrong code path; the OG generator's truncation loop has nothing to do with shortcode rendering. Reading the actual symptoms (per-post asymmetry, missing OG cards) earlier would have pointed at `og-image.php` directly.

### Architectural — incident hardening

After the `/notes` hang incident, the actual fix to the truncation bug is necessary but not sufficient. The **structural** issue that made one bug into a site-down event was that OG card generation ran synchronously inside `wp_head` on every cache-miss page render, with no time budget and no failure isolation. One bad post pinned a PHP-FPM worker at 100% CPU; subsequent visits trapped more workers; eventually the whole pool was exhausted. This change encodes a non-blocking contract on the request path so the same class of failure can't recur even if a future bug breaks the generator.

- **`sn_og_image_url_for_post()` is now non-blocking on cache miss.** The synchronous `sn_generate_og_card()` call inside the function is removed. Cache miss returns `null`, and existing callers (the `sn_og_image_url`, `wpseo_opengraph_image`, and `wpseo_twitter_image` filter wrappers) already fall back to the site default OG image when null is returned. A function-header comment now documents the non-blocking contract — *"never run unbounded synchronous work in the request path for content that has a safe default"* — so future iterations don't reintroduce the lazy-sync path. OG cards are decorative; the site logo card is a perfectly serviceable fallback for any post that doesn't have a cached card yet.
- **`sn_migrate_backfill_og_cards()`** + `SN_OG_BACKFILL_OPT` flag. One-time `admin_init` (priority 5) migration that scans every published post and page and generates any missing OG cards. Replaces the lazy-on-request path with a proactive backfill that runs in the admin context (where slowness is acceptable). After this runs, the wp_after_insert_post hook handles cards for new content, and the request path never has a reason to attempt generation. Each generation is independent and best-effort — a failure on one post doesn't abort the rest.
- **Net effect on the architecture.** Three independent paths now create OG cards, none of them on the request path: (1) `wp_after_insert_post` for new content (admin save context), (2) `sn_migrate_backfill_og_cards` for pre-existing content (one-time admin migration), (3) explicit re-saves for content edits. The front-end render path only *reads* cached cards. Even if the generator hits a future bug, no front-end request can hang on it. If a card is missing for any reason, the page falls back to the site default OG image — a graceful degradation, not a hang.

### Operational — Tier 2 hardening (shipped)

After confirming the architectural fix held, layered the rest of the
post-incident defenses so the next regression has multiple chances
to be caught before it reaches a user-visible failure.

- **GitHub Actions smoke test workflow** at [.github/workflows/smoke-test.yml](.github/workflows/smoke-test.yml). Two jobs: (1) `lint` runs `php -l` on every `.php` file in the repo on every push — catches parse errors before they can deploy. (2) `smoke` runs against the live site, hitting six key routes (`/`, `/notes/`, `/provenance/`, `/provenance/over-detection/`, `/provenance/as-substrate/`, `/notes/feed/`) and asserting per-route HTTP 200, response time under 5 s, body over 1 KB, and the presence of an expected content marker (e.g., `On Provenance` for the pillar). Marker checks defeat false-positive 200s from cached error pages or empty shells. Triggers on `push: main`, `schedule: */15 * * * *`, and `workflow_dispatch`. The 15-minute schedule bounds detection latency for issues that emerge between pushes — content edits, plugin/WP updates, server-side drift. Failure surfaces as a red ❌ on the commit, an email to the committer, and annotated `::error::` lines in the Actions UI.

- **Workflow security:** the `run:` blocks consume only hardcoded URLs and never interpolate `github.event.*` fields (commit messages, PR titles, branch names) into shell, so there is no command-injection surface. Documented as a top-of-file comment so future edits don't introduce one.

- **Documented monitoring playbook** at [docs/MONITORING.md](docs/MONITORING.md). Covers the four tiers (architectural, smoke tests, Uptime Kuma, future), step-by-step Uptime Kuma monitor setup with copy-pasteable URL/keyword/interval table for the six routes, notification routing recommendations, and an incident response checklist that points back to the `superpowers:systematic-debugging` skill so the next incident gets diagnosed before being patched.

- **Uptime Kuma monitors** are documented in `docs/MONITORING.md` for the user to add via the UK web UI on the existing Railway instance. UK doesn't have a public API for programmatic monitor creation, so this step requires manual UI entry — but the guide has the table ready to paste.

### Operational — Tier 3 (still not in this commit)

Flagged for future iterations:

- **Local PHP runtime** (or `wp-env`) so PHP changes can be exercised end-to-end before pushing. The structural gap that allowed the byte-vs-char UTF-8 bug to ship.
- **Production error log access** — Cloudways → Loggly/BetterStack forward, or just SSH access to the WP debug.log. Would have shown the truncation loop firing repeatedly before user-visible impact.
- **Local pre-commit hook** running `php -l` on staged files. Belt-and-suspenders alongside the CI lint, but only useful once local PHP is set up.

### Content

- **Both pillar essays now surface directly on `/notes`.** [templates/page-notes.html](templates/page-notes.html) replaces the prior single "Provenance Over Detection" pillar card with a stacked pair covering both long-forms. Each card uses the existing `sn-pillar-card` brutalist treatment (asphalt background, concrete border) so the visual vocabulary stays consistent. CTAs link to the **on-site long-forms** (`/provenance/over-detection/` and `/provenance/as-substrate/`), not to SSRN — the long-forms are the canonical reading experience on this site, and SSRN is reachable from each long-form's own CTA. Subtitles compressed from the academic SSRN versions to claim-style one-liners: *"Detection chases what isn't. Provenance proves what is."* and *"Music files need fingerprints, not name tags."* Eyebrows use the dynamic `[sn_reading_time slug="..."]` shortcode so the read-time figure stays in sync with the cached value on each long-form's post — single source of truth across `/provenance`, `/notes`, and the byline of each essay.

- **`render_block` filter in [inc/reading-time.php](inc/reading-time.php) extended** to handle the slug-attributed shortcode form (`[sn_reading_time slug="..."]`) in addition to the no-args form. Two specific `strpos` checks rather than a prefix-match — catches both forms but doesn't false-positive on lookalikes like `[sn_reading_timex]`. This is the targeted version of what 949007e tried with the loose prefix-match. Why both forms need this hook: post_content shortcodes resolve via WP core's `the_content` filter chain, but template files (page-notes.html) aren't post_content and don't get `the_content`, so any shortcode in template markup needs this render_block bridge. The OG-truncation root-cause investigation (e006841) confirmed this filter was unrelated to the `/notes` hang; the targeted form here is correct by design.

### Fixed — `/notes` was still showing the old single pillar card after deploy

After cbe3ee5 deployed and the user clicked Update in WP admin, `/notes` continued to render the prior single-card layout despite the theme file changing. Diagnosis:

- `git show origin/main:templates/page-notes.html` confirmed the new two-card markup IS in the deployed branch.
- Cloudflare reported `cf-cache-status: DYNAMIC` and `x-cache: MISS` on `/notes` — origin response, no edge caching.
- `/provenance/` correctly served the new dynamic content. So PHP execution and the recent template-related changes were working, just not on `/notes`.
- Asset mtimes (`components.css?ver=…`) were recent, confirming the theme files were physically replaced on the server.

**Root cause:** a `wp_template` database override for the `page-notes` template, scoped to the `signal-and-noise` theme. WordPress 6.x's Site Editor (Appearance → Editor) creates these whenever an admin opens or edits a template; from that point on, WP serves the DB version and ignores the .html file in the theme directory — even across theme updates. This is intentional WP behavior to preserve admin customizations, but it's a silent footgun when a theme author iterates on the file expecting changes to take effect.

**Fix:** new one-time migration `sn_migrate_clear_notes_template_override()` + `SN_NOTES_TPL_OVERRIDE_CLEARED_OPT` flag in [inc/notes-and-provenance.php](inc/notes-and-provenance.php). Runs on `admin_init`, queries for any `wp_template` post with `post_name = 'page-notes'` AND `wp_theme` taxonomy term `signal-and-noise`, and force-deletes them. After deletion, WP falls back to the theme file, which carries the new two-card layout. Defensive: bails (and flags) if `wp_template` post type isn't registered (e.g., some WP setups without block-theme support active at this hook timing). Idempotent: runs at most once per install. Future admin edits via Site Editor would create new DB records, which this migration deliberately won't clear — admin customizations stay opt-in.

**Lesson:** when a theme file change deploys cleanly but the live page still shows old content AND Cloudflare confirms it's not edge-cached, suspect a WP-level template/block override. The signature is per-template asymmetry: some template-backed pages render new code (their templates haven't been overridden in DB) while specific routes don't.

### Self-healing: file-mtime-based template override clear

The structural reason the existing `sn_clear_template_overrides()` mechanism didn't fire on the `/notes` template change: it's gated on the style.css `Version:` header changing, but project policy reserves Version: bumps for code/functional changes (not template/content edits). So a template-only push went out without bumping Version, the version-compare check returned false, and overrides were never cleared.

**Fix:** added a parallel detection mechanism in [inc/template-maintenance.php](inc/template-maintenance.php) that tracks the most-recent mtime among `templates/*.html` and `parts/*.html` files. When the latest mtime advances past the cached value, `sn_clear_template_overrides()` fires and the cached value updates. Self-healing on every deploy that touches templates, regardless of Version bump policy. Cost: cheap glob + filemtime per admin_init (<1ms when no change). The original Version-compare logic is preserved unchanged so existing self-healing behavior on Version bumps still works — the two mechanisms are complementary.

### Cloudflare HTML caching + auto-purge

The existing CF default profile caches static assets only — CSS, JS, images. HTML responses returned `cf-cache-status: DYNAMIC` (every visitor request hit origin PHP). For a content-heavy site this leaves a lot of CDN performance and origin-load reduction on the table. New module enables HTML caching at the edge with event-driven invalidation.

- **`inc/cloudflare-purge.php`** — auto-purges CF edge cache on post saves and theme updates. Configurable via either `wp-config.php` constants (`SN_CLOUDFLARE_API_TOKEN`, `SN_CLOUDFLARE_ZONE_ID`) or via the admin UI card on the Signal & Noise dashboard. Constant takes precedence when both are set so `wp-config` can lock the value against accidental admin edits. All API calls are non-blocking (`'blocking' => false` on `wp_remote_post`) so a slow CF response never delays an admin save. Without configuration, all hooks no-op silently — fail-safe by default.

  Two automatic purge triggers: (1) `wp_after_insert_post` on `publish` status purges the post URL + homepage + `/notes/` + `/provenance/` + `/notes/feed/` + parent permalink if any (filterable via `sn_cf_purge_urls_for_post`); (2) `upgrader_process_complete` on theme updates purges the entire zone (theme updates can change global elements). Plus a manual "Purge Cloudflare" button on the admin dashboard for ad-hoc purges. Last-purge timestamp displayed in the admin UI for verification.

  Security: API token stored as a non-autoloaded option (loaded only when needed); admin UI obscures saved value (`••••` + last 4 chars); all admin POST actions nonce-protected.

- **`docs/CACHING.md`** — full dashboard-side setup guide. Covers the four caching layers (browser, CF edge, Varnish, WP object cache), why CF doesn't cache HTML by default, two configuration paths (Cloudflare APO at $5/mo for the simplest setup, OR free Cache Rule + this theme's purge module), step-by-step Cache Rule expression with cookie bypass for logged-in users, API token generation, verification curl commands, and a troubleshooting section. The Cache Rule expression specifically excludes `/wp-admin/`, `/wp-login.php`, `/wp-cron.php`, `/wp-json/`, `/feed/`, and any request carrying a `wordpress_logged_in_*` / `wp-postpass_*` / `comment_author_*` cookie — so admin views and feeds always hit origin.

### Notes
- **No theme version bump.** Per maintainer directive and the project's "Only bump version for code/functional changes. Never for content-only template edits" rule. The PHP scaffolding here is wiring for a content asset — the substantive change is the new prose. Cache-busting still works (mtime-driven, v6.5.4).
- **The pillar-page index card for this paper already exists** as Card 2 in `sn_provenance_papers_index_markup()` (shipped in v6.5.4). That card currently has no `Read the long-form on this site →` affordance because the long-form didn't exist yet; updating the pillar to link to this page is a separate task that runs after this page is live (per the original task's scope boundary).
- **Eyebrow reading-time uses the shortcode**, not a hardcoded value. This avoids the drift the live `/provenance/over-detection/` had (eyebrow said "4 min" while byline said "5 min" before v6.5.4 simplified it). The eyebrow renders as "A short read · X min read" — slightly more verbose than the prior hand-typed pattern but always accurate.
- **SVGs are new** — drawn from scratch in the same line-art idiom as the detection/provenance pair (currentColor strokes at width 2, accent group for blood-color circle/line fills, white-stroke check sigil overlaid on the seal). Captions: "Administrative codes / Assigned by clerks" ↔ "Cryptographic identifiers / Born with the file" for the Section 2 pair; "Cost per track / Linear vs constant scaling" for the Section 6 chart.
- **Section 6 carries one diagram, Section 2 carries two.** A deliberate divergence from the over-detection page (which is single-diagram in Section 2 only). The economics section's prose is the closing argument — a chart visualising "linear admin cost vs flat crypto cost" is the punchline made visible. The single-panel layout reuses the paired-grid wrapper with inline `grid-template-columns:1fr` and `max-width:340px` to centre it without a CSS file edit.
- **Section 6 wrapped properly.** The live `/provenance/over-detection/` Section 6 was added to the live page after seeding and ended up as loose paragraphs and an h2 without the `font-display` class or `sn-provenance-section` wrapper. The new seed restores the architectural pattern for all six sections so the typography and spacing stay consistent.
- **SEO meta inherits the existing fallback.** The `is_singular()` branch in [inc/seo.php](inc/seo.php) already produces `{post_title} — Juan Lentino` for og:title/twitter:title and `{post_excerpt}` for the description. Setting `post_title = "Provenance as Substrate"` and `post_excerpt = "A short read on why music files need fingerprints, not just name tags."` yields the user-spec'd `Provenance as Substrate — Juan Lentino` browser-tab title and matching social card description. OG image generates lazily through [inc/og-image.php](inc/og-image.php) on first request — no manual image needed.
- **Evergreen.** Per maintainer convention: this is a static dated piece. Once published it doesn't get edited.

### Deploy
After the next push to `main`, the `sn_migrate_as_substrate_seed()` migration runs on the next admin-side request and creates the page. URL becomes available at `/provenance/as-substrate/`. No cache purge required (mtime cache-busting + WP rewrite-rules flush from the seed flow). The pillar-page card linking to this URL is the next iteration's task.

## [6.5.5] — 2026-05-07

Add a consecutive-revision counter (`-rN`) to the updater's synthetic version label, so the iteration-between-milestones sequence reads as a clean count instead of just an opaque SHA. Resolves the "version bumping should be as consecutive as they are" tension introduced when v6.5.4 moved CSS cache-busting off the theme Version: header — cache-busting is now mtime-driven (frictionless), and the updater label now carries a readable consecutive marker for every commit between tags (so the audit trail isn't lost).

### Added
- **`sn_updater_revcount()`** in [inc/updater.php](inc/updater.php). Calls GitHub's compare API (`/compare/v{Version}...{branch}`) and returns the `ahead_by` count — i.e., the number of commits the tracked branch is ahead of the v{Version} tag. Cached 5 min alongside the existing branch-HEAD cache to keep API hits low. Returns 0 on any failure (missing tag, API error, rate-limited) so the synthetic label gracefully degrades to "no -rN suffix" rather than blocking the update.
- **`-rN` suffix in the synthetic update label.** New format: `{Version}{-rN}+{branch}.{sha7}`. Example: `6.5.5-r3+main.a1b2c3d` reads as "3rd commit on main since v6.5.5 was tagged, at SHA a1b2c3d". Counter resets to 0 each time the maintainer ships a milestone (bumps Version + tags).
- **Rev count surfaced in the admin notice.** Dashboard / Updates / Themes now show "Tracking branch `main` at `<sha>` (default) · `r3` commits since the last tag." so the iteration position is visible without waiting for an update offer.

### Notes
- **The compare API call is incremental**, not a separate HTTP round-trip per page load — it's cached behind a 5-min transient (same TTL as the branch-HEAD cache) and shares the manual-clear hook on `load-update-core.php`. Net cost: one extra cached API request per 5 min when there's an active iteration window.
- **What this resolves.** The v6.5.4 cache-busting refactor decoupled "fire on every file change" (mtime) from "fire on milestones" (Version: bumps). That left "audit trail of shipped iterations" without its own primitive — between v6.5.4 and v6.5.5 in the new model, the version history would read as sparse milestones with no per-commit counter. `-rN` fills that gap: every commit between tags has a unique consecutive identifier, milestone semver stays clean, and cache-busting stays frictionless.
- **Reading the version progression**: `6.5.5` (just-shipped milestone) → `6.5.5-r1+main.<sha>` (1 commit later) → `6.5.5-r2+main.<sha>` → … → `6.5.6` (next milestone) → `6.5.6-r1+main.<sha>` → … and so on.

### Deploy
After the WP updater shows v6.5.5 available, click Update. The new `-rN` label takes effect for any commit pushed to main *after* this install (since the rev counter is computed by the new updater code). Subsequent commits will appear in the WP UI as `6.5.5-r1+main.<sha>`, `6.5.5-r2+main.<sha>`, etc.

## [6.5.4] — 2026-05-07

Three things landing together: (1) restructure `/provenance` into a two-paper index with the long-form essay moved to its own child URL, (2) overhaul iteration UX — mtime-based asset cache-busting + simpler updater that always tracks `main`, no dev branch dance, and (3) a design pass on the index after the v6.5.3-shipped first cut rendered as a wall of red shouting (titles inheriting theme.json's global link colour, mid-word "DISTRIB/UTION" wraps, no title hierarchy).

### Added
- **Two-paper "On Provenance" index** at `/provenance`. New seed in [inc/seed-content/provenance-body.html](inc/seed-content/provenance-body.html): heading + framing intro paragraph + two-entry typographic list. Each entry has a meta line (date + on-site read time on Card 1 only), a short primary title linked to SSRN, an academic-full-form subtitle in DM Mono rust sentence-case, a ~50-word distilled blurb, and (Card 1 only) a discreet "Read the long-form on this site →" affordance pointing at the child page. Index closes with a "Read more notes →" link below the cards.
- **Long-form essay child page at `/provenance/over-detection`.** New seed in [inc/seed-content/over-detection-body.html](inc/seed-content/over-detection-body.html) — lifted verbatim from the prior pillar body (hero, TOC, six anchored sections, SVG diagram, footer CTA, byline). Reuses `page-provenance.html` so the prose inherits the existing essay treatment.
- **`sn_ensure_over_detection_page()`** in [inc/notes-and-provenance.php](inc/notes-and-provenance.php) creates the child page on fresh installs with `post_parent` set to `/provenance`, yielding the `/provenance/over-detection` URL via WordPress's hierarchical-pages routing.
- **`sn_migrate_provenance_split()`** — one-time live-page migration. Splits the existing `/provenance` body using the essay's `sn-provenance-hero` className as a stable anchor: everything from that anchor onward becomes the child page's body (lifted verbatim — no editorial change), and the parent body is replaced with the cards-only index. Idempotent: bails *without* setting its done-flag if the anchor is missing (so a future run after manual recovery still completes the split). Gated by the new `SN_PROV_SPLIT_MIGR_OPT` option.
- **CSS for `.sn-prov-papers` + `.sn-prov-paper-card`** in [assets/css/components.css](assets/css/components.css). Treatment matches the existing `.sn-notes-list` pattern: hairline divider between entries, no fill, no border-all-around, no shadow. Subtitle styles, defensive specificity on title and longform-link colors (so they win cleanly over theme.json's global link rule), `hyphens: none; word-break: normal; overflow-wrap: normal` on `.sn-prov-paper-title` to stop WP-core's default `break-word` from producing mid-word wraps on long academic titles. Mobile stack rule in [assets/css/responsive.css](assets/css/responsive.css) at the theme's tablet breakpoint (≤781px).
- **`sn_asset_ver()` helper** in [inc/assets-frontend.php](inc/assets-frontend.php). Computes `?ver=` from each enqueued file's `filemtime()` instead of the theme Version header. CSS/JS changes auto-bust browser, Cloudflare, and Breeze caches the moment a file changes on disk — no theme Version: bump required for visual tweaks. Falls back to the theme Version if `filemtime()` fails so we never emit a versionless URL. Applied to all five modular stylesheets and the sticky-header script.

### Changed
- **`/provenance` no longer hosts the long-form essay.** It's now a lean two-paper index, ~1 viewport on desktop. Essay text unchanged — just on its own URL now. Page title updates from "Provenance Over Detection" to "On Provenance" to reflect the new role.
- **Title hierarchy redesigned.** Each card now shows a short primary title (Bebas Neue 1.5rem black uppercase — "Provenance Over Detection" / "Provenance as Substrate") with the academic full-form below as a small DM Mono rust sentence-case subtitle. Replaces the previous single-heading-with-everything that produced 14-word red shouting matches.
- **`/provenance/over-detection/` no longer shows reading time twice.** The eyebrow's hardcoded "A short read · 4 min" had no way to stay synced with the dynamic `[sn_reading_time]` in the byline. Eyebrow simplified to "A short read"; the byline `[sn_reading_time]` shortcode is the single source of truth.
- **GitHub self-updater simplified.** [inc/updater.php](inc/updater.php) now always SHA-tracks `main` directly. The auto-detect-dev-branch logic, the release-tag fallback, and the `sn_github_dev_exists` / `sn_github_release` transients are gone. New helper `sn_updater_branch()` resolves once — defaults to `main`, overridable via the existing `SN_GITHUB_BRANCH` constant for tests/staging. Push to main → updater offers the new SHA on next poll. Tagged releases remain useful for changelog correlation and the GitHub Releases UI but no longer drive the update mechanism. Net effect: no dev/main branch dance, no version-bump-for-CSS-changes, no manual cache purges (between mtime cache-busting + Breeze auto-flush on theme update).
- **Removed the per-card `Read on SSRN →` CTA and the `SSRN Abstract NNNN` line from the meta.** The title link does the routing to SSRN; meta line is just date + read time. Less chrome, more typography.

### Notes
- **The split migration is non-editorial.** It moves prose between pages rather than editing it. If an admin had hand-edited the essay before this update, those edits are preserved as-is on the new child page.
- **Visual asymmetry between cards is intentional.** Card 1 has the long-form link + read-time meta because the essay lives on this site. Card 2 doesn't because there's no on-site equivalent. The asymmetry is honest signal — it tells the reader before they click that Paper 1 has a 4-min local read and Paper 2 doesn't.
- **Last manual version bump for CSS-only iteration.** From 6.5.5 onward, CSS/JS changes will cache-bust automatically via mtime. Theme Version: bumps are reserved for milestones the maintainer wants to mark.
- **Lessons from the v6.5.3 ship.** The first cut of this work shipped to dev and rendered with bugs the maintainer reasonably described as "this sucks": titles red because Cloudflare/Breeze were still serving the old `components.css?ver=6.5.3` (no version bump = no cache key change), mid-word title wraps because WP-core's `break-word` default kicked in on long Bebas Neue uppercase titles, and reading time appearing twice on the child page. All three are addressed structurally here so they can't recur the same way: mtime cache-busting kills the version-bump dependency for CSS work, defensive `:where`-beating specificity locks down our title colour, `hyphens: none` removes the wrap risk, and the eyebrow's hardcoded reading time is gone. Should have invoked the design-review skill *before* the first push, not after the user reported the regression — flagged here so the next iteration starts there.

### Deploy
After the WP updater shows v6.5.4 available (Dashboard → Updates), click Update. Caches will self-clear thanks to mtime-based cache-busting + the existing v6.5.0 theme-version-mismatch auto-flush. If anything still looks stale after a hard refresh, manually purge Breeze + Cloudflare once — but that should be the last time this dance is required for a non-milestone iteration.

## [6.5.3] — 2026-05-05

First release shipped via the dev-mode workflow proven by the v6.5.2 sanity check. Two iteration commits (`2718eb4` nav-underline tweak + `7f1ac3e` two updater fixes) were squashed into this single ship commit. The dev branch is deleted as part of this release; the auto-detect logic falls back to release-tag mode for the next user poll.

### Fixed
- **Updater `upgrader_process_complete` hook now handles auto-detect mode** in addition to the explicit `SN_GITHUB_BRANCH` constant case. Previously the hook early-returned if the constant wasn't defined, meaning in auto-detect mode the local branch SHA was never stored after install — every subsequent poll re-offered the same commit as a "new update", an infinite loop. The hook in [inc/updater.php](inc/updater.php) now resolves the active branch from constant OR auto-detect transient, fetches the branch HEAD live (instead of trusting a possibly-stale 5-min cache), and stores the SHA in `sn_github_local_sha` after a successful theme upgrade. Also accepts both `themes` (bulk) and `theme` (single) hook payload shapes for robustness.
- **`load-update-core.php` cache-bust hook now also flushes WP's own `update_themes` site transient.** Without this, WP serves frozen update info from its standard cache and never re-runs `pre_set_site_transient_update_themes`, so the displayed dev branch SHA stays stale even after the theme's custom transients are cleared. Adding `delete_site_transient( 'update_themes' )` forces a fresh poll on every Updates page load. (This is what was producing the "f5a884b" stale-SHA display during the sanity check despite my custom transients being cleared correctly.)

### Changed
- **Nav hover-underline thickness `1px` → `2px` in [assets/css/critical.css](assets/css/critical.css).** Aligns with [assets/css/layout.css](assets/css/layout.css), which was already at 2px. Eliminates a brief first-paint flash where the nav hover indicator was 1px before the deferred layout.css loaded and overrode it. At the v6.5.1 nav font size of 1.125rem, 2px reads better as an interactive affordance than the hairline 1px.

### Notes
- **Dev branch deleted at ship time.** The branch's existence on the remote is the auto-detect's signal; deleting it triggers fallback to release-tag mode. Next session, I'll create dev again from main and the cycle repeats — no `wp-config.php` edits, no admin UI clicks.
- **The sanity check served its purpose.** Two real bugs in v6.5.2's dev-mode plumbing surfaced only when actually exercised end-to-end. Both fixes ship in this release. Future iteration cycles won't loop or display stale SHAs.

### Deploy
After Update in WP admin (6.5.2 → 6.5.3), purge Breeze + Cloudflare, hard-refresh. The dev-mode banner on the Dashboard / Updates / Themes screens disappears once the dev branch is deleted (it's already gone as of this release) — that's the visual confirmation you've fallen back to release-tag mode.

## [6.5.2] — 2026-05-05

Make dev mode fully automatic — no `wp-config.php` constant needed.

### Changed
- **Updater auto-detects a `dev` branch on the remote.** [inc/updater.php](inc/updater.php) now polls GitHub once every 5 minutes (cached transient) for the existence of a `dev` branch. When `dev` exists, the updater silently switches to SHA-tracking on that branch — no `SN_GITHUB_BRANCH` constant required. When `dev` is deleted (after a merge to `main` at ship time), the next poll's 404 expires the cache, and the updater falls back to release-tag tracking. The `SN_GITHUB_BRANCH` constant still works as an explicit override (e.g., to track `staging` instead), but is no longer needed for the standard iterate-on-dev workflow.
- **Admin notice updated to label the mode.** Dashboard / Updates / Themes screens now show a notice that distinguishes "explicit override" (constant set) from "auto-detected" (constant absent, dev branch exists), so it's obvious why dev mode is active.
- **`load-update-core.php` cache-bust hook clears the dev-detection transient too**, so visiting the Updates page forces a fresh check of whether `dev` still exists. This makes ship-time transitions (delete dev → fall back to releases) feel instant rather than waiting 5 minutes for the cache to expire.

### Notes
- **The user's flow is now genuinely "talk to Claude → click Update":**
  1. I create a `dev` branch with the iteration commits.
  2. WP admin shows "Update available" within 5 min, OR immediately on visiting Dashboard → Updates.
  3. User clicks Update + purges cache.
  4. Repeat until satisfied.
  5. Ship: I squash-merge `dev` → `main`, bump version, tag, create one GitHub release, **delete the `dev` branch**.
  6. Next user poll detects no `dev`, falls back to release mode automatically. Site is on the new tagged version.
- **Backwards-compatible.** If the user kept the `define( 'SN_GITHUB_BRANCH', 'dev' );` from v6.5.1, it still works (explicit override path). It can be safely removed from `wp-config.php` after this update — but doesn't have to be.

### Deploy
This is the **last manual update** the user has to think about. After v6.5.2, future iterations land via dev-branch auto-detection — no constants, no settings, no UI. Update via WP admin (6.5.1 → 6.5.2), purge Breeze, purge Cloudflare, hard-refresh.

## [6.5.1] — 2026-05-05

Two changes bundled to demonstrate the discipline introduced by the second one — bump the nav size, AND add a dev-mode updater that lets future iteration sessions skip version bumps entirely.

### Added
- **Dev mode for the GitHub self-updater.** [inc/updater.php](inc/updater.php) now supports a new `SN_GITHUB_BRANCH` constant. When set in `wp-config.php` (e.g., `define( 'SN_GITHUB_BRANCH', 'dev' );`), the updater stops polling `/releases/latest` and instead polls `/commits/{branch}` every 5 minutes, comparing the branch's HEAD commit SHA against the SHA stored in the `sn_github_local_sha` WP option. When SHAs differ, WP shows "Update available" with a synthetic version label like `6.5.1+dev.a1b2c3d` (the SHA-vs-stored check is the real gate; the synthetic version is just for the admin UI). On successful upgrade, the new SHA is stored via `upgrader_process_complete`, so the next poll skips the same commit. Net effect: push commits to the `dev` branch freely, click Update in WP admin, no version bump, no GitHub release. Remove the constant when work is final and resume normal release-tracking.
  - Admin notice on Dashboard / Updates / Themes screens names the branch and current SHA so it's obvious when dev mode is on.
  - The `load-update-core.php` cache-bust hook clears the branch transient too, matching existing behaviour for the release transient.
  - Branch zipballs go through the same `upgrader_source_selection` folder-rename hook as release zipballs, so the extracted directory ends up at `signal-and-noise/` correctly.
  - Why this exists: the v6.4.0 → v6.5.0 hero-centring debug session burned 8 versions on a single feature, because every iteration needed a tag for WP to pick up the change. With dev mode, that same session would have shipped exactly one release at the end.

### Changed
- **Nav font-size 1rem → 1.125rem.** [parts/header.html](parts/header.html) bumps the nav typography fontSize attribute to compensate for Bebas Neue's condensed weight. At the previous 1rem, the nav read visually smaller than other 1rem-equivalent elements (buttons, body) because Bebas Neue's narrower letterforms reduce optical mass at the same nominal pixel size. 1.125rem (18px) restores parity without pushing the 8-item nav into wrap territory at 1200px+ viewports.

### Notes
- **Dev-mode workflow.**
  1. In `wp-config.php`: `define( 'SN_GITHUB_BRANCH', 'dev' );`
  2. Push commits to `dev` branch as work progresses. No version bump, no tag, no release.
  3. WP admin shows "Update available" within 5 minutes (or immediately if you visit Dashboard → Updates, which clears the cache).
  4. Click Update; the branch zipball replaces theme files; the SHA is stored.
  5. When work is final: merge `dev` → `main`, bump version once, tag, create one GitHub release. Remove the `SN_GITHUB_BRANCH` constant from `wp-config.php`.
- **Patch bump because both changes are additive and non-breaking.** Dev mode is opt-in (gated on the constant); without the constant, the updater behaves identically to v6.5.0. Nav size goes up by ~2px which is a visual refinement, not a behavioural change.

### Deploy
Update via WP admin → **Dashboard → Updates** (6.5.0 → 6.5.1), then **Breeze → Purge All Cache**, then **Cloudflare → Caching → Purge Everything**, then hard-refresh.

## [6.5.0] — 2026-05-05

Patch cap reached on 6.4 — minor bump. The work is small (one CSS rule) but the 6.4 lane is full at 6.4.7.

### Fixed
- **Home hero — close the dead band between H1 and subtitle.** The H1 was inheriting the UA stylesheet's `h1 { margin-block: 0.67em }` default, which scales with font-size — at the hero's `clamp(3rem, 9vw, 7rem)` font (= 112px on desktop) that's ~75px above and below the H1 block. Combined with the subtitle's own `margin-top: 1.5rem` (24px), the visible gap from H1 baseline to subtitle was reading as a 100px+ empty band where it should be ~24px. WP block-library normally resets heading margins, but in this case the reset doesn't reach `.sn-hero-title` because it's inline-styled with a custom font-size inside a constrained group, and the cascade lets the UA `h1` rule win on `margin-block`. Fix: explicit `margin-block: 0 !important` on `.sn-hero-title` in [assets/css/critical.css](assets/css/critical.css) and [assets/css/layout.css](assets/css/layout.css). Subtitle's existing `margin-top: 1.5rem` becomes the actual visible gap.

### Notes
- **Why a minor bump for one rule?** The 6.4 patch cap (project rule: 7 patches per minor) was reached at v6.4.7. Any further change to this minor forces a 6.5.0 bump regardless of size. Bundling the H1 reset alone here is the right call — the next hero adjustment (if any) can land in 6.5.x.

### Deploy
Update via WP admin → **Dashboard → Updates** (6.4.7 → 6.5.0), then **Breeze → Purge All Cache**, then **Cloudflare → Caching → Purge Everything**, then hard-refresh on the home page (Cmd+Shift+R).

## [6.4.7] — 2026-05-05

Revert v6.4.6's text-align: center on the home hero. User feedback: "I don't like everything centered like that". Going back to the v6.4.5 state — wrapper at 1100px max-width centred via auto margins, content inside left-aligned (editorial). Patch cap reached for the 6.4 minor (project rule: 7 patches per minor).

### Changed
- **Home hero — remove `text-align: center` from `.sn-hero-inner` and remove the accent's `margin: auto` and the buttons row's `justify-content: center !important` overrides.** [assets/css/critical.css](assets/css/critical.css) and [assets/css/layout.css](assets/css/layout.css) reduce `.sn-hero-inner` back to four declarations: `width: 100%; max-width: 1100px; margin-left: auto; margin-right: auto`. Inside the wrapper, all children flow with default block layout — H1 fills the column, subtitle is constrained to its `max-width: 640px` at the column's left, accent stays 120px at column-left, buttons row's flex layout uses its block-markup `justifyContent: "left"` so buttons cluster at column-left.

### Notes
- **The visible asymmetry tradeoff is real and unavoidable without changing typography.** H1's `clamp(3rem, 9vw, 7rem)` font means line 2 ("THAT SOUND RIGHT.") naturally renders at ~1100px wide on desktop. The column max-width can't drop below 1100 without forcing H1 to wrap to 3+ lines. With H1 line 1 ("I BUILD THINGS") at only ~575px wide and the column at 1100px, there will always be empty space to the right of H1 line 1 in editorial left-aligned mode. That's the cost of preserving the existing typography untouched, which is in the original spec.
- **Patch cap reached.** Per project CLAUDE.md, this minor allows up to 7 patches (6.4.0 → 6.4.7). The next change to the 6.4 line forces a minor bump to 6.5.0. Recommend that the next iteration on this hero — if any — bundle related work and ship as 6.5.0.

### Deploy
Update via WP admin → **Dashboard → Updates** (6.4.6 → 6.4.7), then **Breeze → Purge All Cache**, then **Cloudflare → Caching → Purge Everything**, then hard-refresh on the home page.

## [6.4.6] — 2026-05-05

Follow-up to v6.4.5. The wrapper-based centring landed structurally — verified via curl that the hero column was mathematically centred at 1100px wide with auto margins — but the user's screenshot still read as "not centred" because the H1's natural text width (~575px at desktop fonts) is smaller than the 1100px column it lives in. With `text-align: left` on the H1, that 575px of text sits at the column's left edge, leaving ~525px of empty space on the column's right. Across the viewport, the visible content reads as "left half full, right half empty" — uncentred to the eye, even though the column is mathematically centred.

This is the standard editorial pattern (Apple, Stripe, etc), but it doesn't match the user's spec, which was "centred" in the visual sense. Fixing by centring the text within the column.

### Changed
- **Home hero — text-align: center on the inner wrapper.** [assets/css/critical.css](assets/css/critical.css) and [assets/css/layout.css](assets/css/layout.css) add `text-align: center` to `.sn-hero-inner`. H1 lines, subtitle text, and the accent (which centres via `margin: 0 auto`) all now visibly centre within the 1100px column. Buttons row gets `justify-content: center !important` to override its block-markup `justifyContent: "left"` setting (the markup attribute can't be removed without a template edit, and `!important` cleanly overrides at runtime).
- **Accent bar — explicit `margin: 0 auto` to centre under the centred text.** With `text-align: center` on the wrapper, inline-block content centres, but the accent is a `<div style="width: 120px">` block element — `text-align` doesn't centre block children. Adding `margin-left: auto; margin-right: auto` to `.sn-hero-inner .sn-hero-accent` does.

### Notes
- **Same column width (1100px), same wrapper, no markup change from v6.4.5.** Just the text-alignment within the wrapper changes. If you decide later you want left-aligned editorial-style text inside a centred column instead, removing the `text-align: center` and `justify-content: center` rules on `.sn-hero-inner` reverts to that mode without touching markup.
- **Mobile/tablet inherit** the centring — `text-align: center` on `.sn-hero-inner` applies at every viewport since it's not inside a media query. Responsive.css owns the hero's outer paddings and animation timings at narrow widths but doesn't touch text-alignment, so mobile gets centred text too. (If that's wrong for mobile, easy to add a `@media (max-width: 781px) { .sn-hero-inner { text-align: left } }` override in a follow-up.)

### Deploy
Update via WP admin → **Dashboard → Updates** (6.4.5 → 6.4.6), then **Breeze → Purge All Cache**, then **Cloudflare → Caching → Purge Everything**, then hard-refresh (Cmd+Shift+R) on the home page.

## [6.4.5] — 2026-05-05

Third (and last) follow-up to v6.4.1 hero centring. The CSS-only path through v6.4.1 → v6.4.4 successively tried `width: 100%` + `margin: auto` on `.sn-hero-cta`, calc-based `margin-left` on `.sn-hero-accent`, and column-padding via `padding-left: max(40px, calc((100% - 1100px) / 2))`. Each variant was inlined into `<style id="sn-critical-inline">` correctly (verified via Python on the live HTML), and yet the rendered output kept showing the hero column drifted into the viewport's left half, with significantly more empty space on the right than the left. Three independent CSS attempts not landing the visible result is the signal: switch from CSS-only to a markup wrapper.

### Changed
- **Home hero — wrap children in an inner `<div class="sn-hero-inner">` and centre that.** [templates/front-page.html](templates/front-page.html) now wraps the H1, subtitle, accent bar, and buttons row inside a single `sn-hero-inner` div (emitted as raw HTML around the existing block markup). [assets/css/critical.css](assets/css/critical.css) and [assets/css/layout.css](assets/css/layout.css) replace the prior `.sn-hero { padding: max(...) }` + `.sn-hero.is-layout-constrained > * { margin-left: 0 }` rules with a single rule on the wrapper: `.sn-hero-inner { width: 100%; max-width: 1100px; margin-left: auto; margin-right: auto }`. The wrapper is the centred 1100px column; children inside flow with default block layout (margin-left: 0 by default) so they all share the same column-left x-coordinate by construction. No selector battles with WP's per-block `margin: auto !important` rule, no calc gymnastics, no cache-sensitivity. The outer `.sn-hero` keeps its full-width gradient `::before` and its `display: flex` vertical centring; the wrapper is the only flex item, so `justify-content: center` (vertical, since `flex-direction: column`) still vertically centres the whole hero block.

### Notes
- **Why a markup change now and not earlier.** v6.4.1 deliberately tried to fix this with CSS only because the original spec said "do not modify mobile layout" and "do not change typography". Both are still respected — the wrapper is invisible to layout flow at <782px (responsive.css owns those breakpoints with explicit symmetric paddings on `.sn-hero` itself; the wrapper inherits its width from the hero's content area, which mobile padding already constrains). Typography untouched. The wrapper is just a structural shim.
- **Class-based selectors still apply.** `.sn-hero .sn-hero-subtitle { max-width: 640px }` keeps working because it uses a descendant combinator — the subtitle is a descendant of the hero whether the wrapper is there or not. Same for `.sn-hero-title`, `.sn-hero-cta`, `.sn-hero-accent` animation rules.
- **`.sn-hero > * { z-index: 2; position: relative }` now applies to the wrapper instead of each child individually.** Functional behaviour is the same: wrapper sits above the gradient `::before` overlay, and so does everything inside it (the wrapper establishes its own stacking context).

### Deploy
After Update in WP, **purge Breeze + Cloudflare** caches one more time. The markup change means the rendered HTML structure itself differs, so any cached HTML page-output from v6.4.4 won't include `.sn-hero-inner` and the wrapper's centring rule will have nothing to attach to. Hard-refresh (Cmd+Shift+R) on the home page to be sure.

## [6.4.4] — 2026-05-05

### Removed
- **All biblical / Scripture content stripped from theme files.** Five references gone:
  - [templates/front-page.html](templates/front-page.html) — `<!-- "Work willingly at whatever you do, as though you were working for the Lord rather than for people." -->` (Colossians 3:23, hidden HTML comment in the hero section).
  - [templates/page-services.html](templates/page-services.html) — `<!-- "Do you see any truly competent workers? They will serve kings rather than working for ordinary people." -->` (Proverbs 22:29, hidden comment above the page-title group).
  - [templates/page-music.html](templates/page-music.html) — `<!-- "Sing a new song of praise to him; play skillfully on the harp, and sing with joy." -->` (Psalm 33:3, hidden comment between the page-title group and the Spotify-embed group).
  - [templates/page-about.html](templates/page-about.html) — visible italic right-aligned quote `"As iron sharpens iron, so a friend sharpens a friend."` (Proverbs 27:17) plus its preceding `<wp:spacer>` block, removed from the Education & Mentorship group.
  - [parts/footer.html](parts/footer.html) — visible footer line `Soli Deo Gloria` (concrete-grey 0.6rem italic paragraph). The right-side group wrapper that contained it is also gone, since with only the copyright line left there's no need for the inner two-paragraph flex group; the copyright `<wp:paragraph>` is now a direct child of the footer's flex container, which lets the existing `space-between` justification continue placing copyright at the right edge alongside the social icons on the left.
- The Aug-2025 v3.5.1 patch (`Removed book/chapter/verse references from all Scripture quotes across six templates`) explicitly kept the verses themselves; this release removes them entirely.

### Changed
- **Home hero — also force `padding-right` to the centring calc.** Belt-and-suspenders: v6.4.3 set `.sn-hero { padding-left: max(40px, (100% - 1100px) / 2) !important }` but left `padding-right` inheriting from the inline `style="padding-right: var(--wp--preset--spacing--40)"` on the `<wp:group>` markup (40px). With children at `margin-left: 0; margin-right: auto`, the auto right-margin already absorbs the asymmetric inner-content space, so output IS centred — but if cache or minification ever truncates the auto-margin override, the fallback would silently land non-centred. Setting both paddings to the same calc means the hero's content area is symmetric independent of the child margin override.

### Notes
- **About page Education & Mentorship section now ends on the two-column paragraph block** (mentor-bridge-the-gap text on left, mix-critiques-with-context text on right). Previous trailing italic Scripture quote and its spacer are gone, so the section closes cleanly with the columns.
- **Footer markup simplified.** Right-side wrapper `<wp:group>` removed since it only had to host two `<wp:paragraph>` siblings (Scripture line + copyright). Copyright paragraph now sits directly inside the footer's flex layout container.

### Deploy
After Update in WP, **purge Breeze + Cloudflare** caches one more time. The previous v6.4.3 fix landed CSS-side — verified the deployed `critical.css` file content matches the spec — but the page-output cache that ships the inlined critical CSS in `<head>` had not regenerated yet at the time of the post-deploy screenshot. v6.4.4's templates-and-CSS combo should produce a clean centred hero plus the biblical removals after one cache purge cycle.

## [6.4.3] — 2026-05-05

Second follow-up to the v6.4.1 layout fix. The accent bar and buttons row on the home hero were still rendering at the section's left edge after v6.4.2 even though the inline critical CSS contained the desktop-only `@media (min-width: 782px)` rules — verified via Python on the live HTML, the rules were present in the rendered `<style id="sn-critical-inline">` block, but the visible result didn't match. Switching CSS strategy.

### Changed
- **Home hero — replace the per-element `@media` overrides with a column-padding approach.** Both [assets/css/critical.css](assets/css/critical.css) and [assets/css/layout.css](assets/css/layout.css) now centre the hero column by setting the `.sn-hero` container's `padding-left` to `max(var(--wp--preset--spacing--40), calc((100% - 1100px) / 2))` and forcing all `.sn-hero.is-layout-constrained > *` children to `margin-left: 0 !important; margin-right: auto !important`. This is the same shape as the original v6.4.0 hero (children flush-left within an offset inner-content area), with the offset switched from the over-aggressive `15vw` to a calc that *exactly* centres a 1100px column. By construction every child — H1, subtitle, accent bar, buttons row — shares the same column-left x-coordinate, so the accent and buttons can no longer drift relative to the H1. Removed the `width: 100% / margin: max(...) / margin-right: auto` overrides on `.sn-hero-cta` and `.sn-hero-accent` from v6.4.1/v6.4.2 — they were the speculative mechanism that didn't actually take effect in production.
- **Tablet/mobile preserved exactly.** [assets/css/responsive.css](assets/css/responsive.css) keeps owning `padding-left: 1.5rem` (≤781px) and `padding-left: 1.25rem` (≤480px) with `!important` and a later cascade position, so under-782px viewports drop straight to the symmetric mobile padding regardless of what the desktop calc evaluates to.

### Fixed
- **`readme.txt` — bumped `Stable tag` from `4.2.3` to `6.4.3`.** Out-of-date by ~2 major versions; the WordPress.org plugin/theme directory uses this header to identify the current stable release. Caught while editing `readme.txt` for v6.4.3.

### Deploy
After clicking Update in WP, **purge Breeze + Cloudflare** caches again (same instructions as v6.4.2). Hard-refresh with Cmd+Shift+R on the home page to drop browser-side cache too.

## [6.4.2] — 2026-05-05

Follow-up to v6.4.1 to address two issues raised after deploy.

### Changed
- **Services — Business & Strategy section now matches Music & Production width.** Bumped `contentSize` on the Business & Strategy `<wp:group>` in [templates/page-services.html](templates/page-services.html) from `1000px` → `1400px`. v6.4.1 widened only the Music & Production grid; this leaves both image-card grids on the page at the same width, which is the correct read since they're the same content type. Page header and the LET'S TALK closing CTA stay at their existing narrower widths (1000px and 680px respectively) — those are prose, not media grids.

### Fixed
- **Home hero — duplicate the desktop accent/buttons rules into critical.css.** v6.4.1 placed the `@media (min-width: 782px)` block (which keeps the 120px accent bar and the buttons row aligned with the H1's column-left edge) only in `assets/css/layout.css`. Because [inc/assets-frontend.php](inc/assets-frontend.php) loads `layout.css` as an external `<link rel="stylesheet">` with a `?ver=` query string, a stale Cloudflare CDN copy under the previous `?ver=6.4.0` URL kept serving the pre-v6.4.1 file (verified live: `cf-cache-status: HIT, age: 133145`, ~1.5 days old). The rules never reached the browser. Now duplicated into [assets/css/critical.css](assets/css/critical.css), which is **inlined** in `<head>` on every render via `wp_head` priority 50 — no CDN cache, no `?ver=` URL, can't go stale relative to the surrounding HTML. Both copies are kept (critical.css for cache-resilience, layout.css for editor parity); they're identical.

### Deploy notes
- **After clicking Update in WP admin, purge the page cache.** Breeze (WP page cache) and Cloudflare (HTML edge cache) both need a flush to actually emit the new template `contentSize` values and the new critical CSS. Without a flush, page output stays cached as v6.4.0/v6.4.1 HTML even after the theme files swap. Quickest path: **Breeze → Settings → Purge All Cache**, then **Cloudflare → Caching → Purge Everything** (or purge by URL: `https://juanlentino.com/`, `/services/`, `/music/`, `/resume/`, `/work-with-me/`).

## [6.4.1] — 2026-05-05

### Fixed
- **Home hero — center the content column horizontally.** Removed the `.sn-hero` `padding-left: max(40px, 15vw) !important` rule (originally added in v3.9.4 as an "Apple-style golden-ratio offset") together with the matching `margin-left: 0 !important; margin-right: auto !important` override on `.sn-hero.is-layout-constrained > *`. Both rules were duplicated in [assets/css/critical.css](assets/css/critical.css) (inline) and [assets/css/layout.css](assets/css/layout.css) (deferred); both copies removed. WordPress's stock per-block constrained-layout rule (`max-width: 1100px; margin-left: auto !important; margin-right: auto !important`) now centres the hero column. Also dropped `align-items: flex-start` from the `.sn-hero` flex container so default cross-axis behaviour applies.
- **Home hero — keep the 120px red accent bar and the buttons row aligned with the H1's column-left edge.** Added a desktop-only (`min-width: 782px`) block in [assets/css/layout.css](assets/css/layout.css) that sets `width: 100%` on `.sn-hero-cta` (so the buttons-row block expands to the full 1100px column width and `justifyContent: "left"` actually puts buttons at the column's left edge instead of the hero's centre) and overrides the auto-margins on `.sn-hero-accent` to `margin-left: max(0, calc(50% - 550px)); margin-right: auto` so the 120px accent line sits at the column's left edge instead of being centred inside the column. Tablet/mobile (`max-width: 781px` + `max-width: 480px`) preserved exactly — `responsive.css` already owns those breakpoints with explicit symmetric paddings and stack/row direction overrides.

### Changed
- **Services — wider stat row and Music & Production image grid.** Bumped `contentSize` on two `<wp:group>` sections in [templates/page-services.html](templates/page-services.html) from `1000px` → `1400px`: the `.sn-credibility-strip` panel (20+ YEARS / 50+ ARTISTS / GRAMMY / MBA) and the Music & Production image-card grid (production / mixing / mastering rows). Page header (eyebrow + H1 SERVICES + intro) and the Business & Strategy panel below stay at 1000px so prose continues to read at a comfortable measure.
- **Music — wider Spotify embed.** Bumped `contentSize` on the Spotify-embed `<wp:group>` (which wraps `<wp:post-content>` for the page body) in [templates/page-music.html](templates/page-music.html) from `900px` → `1400px`. Page header (eyebrow + H1 MUSIC + intro) and the Muso.AI credits section below stay at 900px.
- **Resume — wider PDF viewer.** Bumped `contentSize` on the `#resume-viewer` `<wp:group>` in [templates/page-resume.html](templates/page-resume.html) from `900px` → `1400px`. Page header section stays at 900px. The PDF embed itself (rendered via `<wp:post-content>`) is unchanged in this session — replacing the embed with native HTML is flagged as a follow-up decision.
- **Work With Me — wider HOW IT WORKS process strip and booking calendar.** Bumped `contentSize` on two `<wp:group>` sections in [templates/page-work-with-me.html](templates/page-work-with-me.html) from `800px` → `1400px`: the asphalt-background HOW IT WORKS three-column strip (01/02/03) and the Tab Bar + Cal.com booking-area panel (30-min / 60-min embeds). Page header section stays at 800px.

### Notes
- **About / Contact / Notes templates untouched.** All three were already correctly centred via WordPress's stock constrained-layout rules — verified by curling the live About page and confirming `<style id='core-block-supports-inline-css'>` emits `margin-left: auto !important; margin-right: auto !important` per section. The "every page" right-pin perception was driven entirely by the home hero. No template edits needed.
- **theme.json untouched.** Global `contentSize: 720px` and `wideSize: 1200px` left as-is. Wider sections express themselves at the per-section level via `contentSize` overrides on their own `<wp:group>` blocks rather than via `align: "wide"` (which would only widen children to `wideSize: 1200px` — barely a step up from the 1000px baseline).
- **Mobile preserved exactly.** No edits to existing `@media (max-width: 781px)` or `@media (max-width: 480px)` blocks. The wider `contentSize: 1400px` on desktop sections has no effect under 1400px-viewport since the constrained `max-width` simply caps at the available width inside the section's 40px horizontal padding.
- **One commit per page.** Five commits in this release (home / services / music / resume / work-with-me) so any single page can be reverted independently. Versioning, changelog, and release tag in a sixth commit on top.

## [6.4.0] — 2026-05-03

### Removed
- **All in-theme Plausible analytics — replaced by the official Plausible WP plugin.** Removed because the plugin is a better-supported home for tracking and admin reporting, and keeping a parallel implementation in the theme was duplicating responsibility (and dragging two CDN-loaded vendor libs into wp-admin for a feature the plugin already covers). Concretely:
  - **Frontend tracking script** (`<script defer data-domain=…>`) deleted from [inc/seo.php](inc/seo.php). The plugin will inject its own once activated.
  - **`inc/plausible-api.php` deleted** — the Plausible Stats API client (`sn_plausible_api()`), the `SN_PLAUSIBLE_URL` / `SN_PLAUSIBLE_SITE` constants, the `sn_plausible_error` admin notice, and the helper formatters (`sn_fmt`, `sn_duration`, `sn_metric_card`, `sn_ranked_list`) all go with it. `SN_PLAUSIBLE_KEY` in `wp-config.php` is now ignored by the theme — it can be removed at the user's leisure (the plugin uses its own settings UI, not that constant).
  - **`inc/dashboard-widgets.php` deleted** — the four WP Dashboard widgets (Visitors Today, 30-Day Trend, Top Stats tabbed, Visitor Map). The plugin ships its own dashboard widgets.
  - **Analytics tab on `Appearance → Signal & Noise` deleted** — the date-range bar, six aggregate metric cards, time-series chart, world map, and 13 tabbed breakdowns. The options page is now two tabs (Dashboard / Links) instead of three. The "Plausible Dashboard" external link in the Links tab is also gone.
  - **`inc/admin-assets.php` deleted** — the entire admin asset registration layer was scaffolding for the analytics surfaces above (jsvectormap 1.6.0 + Chart.js 4.4.4 vendor libs with SRI hashes, plus the three theme-owned admin JS files). With nothing left to register, the file has no purpose.
  - **`assets/js/admin-map.js`, `assets/js/admin-tabs.js`, `assets/js/admin-chart.js` deleted** — the client-side renderers for the map, tab switcher, and visitor-trend chart respectively. All three were Plausible-only.
  - **`functions.php` bootstrap pruned** — three `require_once` lines (`plausible-api`, `dashboard-widgets`, `admin-assets`) and their references in the module map docblock removed.
  - **Header doc on [inc/admin-page.php](inc/admin-page.php)** updated from "three-tab interface (Dashboard / Analytics / Links)" → "two-tab interface (Dashboard / Links)" and the page subtitle from "Theme management, maintenance, and analytics" → "Theme management and maintenance".
- **Net diff:** ~860 lines of PHP + ~3 standalone JS files removed. The frontend now ships zero analytics requests until the Plausible plugin is installed and activated; wp-admin loads no admin-only vendor JS at all.

### Notes
- **Why a minor bump (6.3 → 6.4) and not a patch.** Removing a whole feature surface (Analytics admin tab, four dashboard widgets, the entire `plausible-api.php` module) is more than the patch lane is meant to carry — minor bumps are the right place for "feature added or removed". Patch cap of 7 wasn't the constraint; semantic intent was.
- **`SN_PLAUSIBLE_KEY` in `wp-config.php`** is now dead. Safe to leave or delete. The Plausible WP plugin doesn't read it.
- **The delayed `gtag.js` loader stays.** v6.4 only removes Plausible — Google Tag is independent and untouched in [inc/seo.php](inc/seo.php).
- **The `sn_admin_dashboard_extras` action** on the options-page Dashboard tab is preserved. It's still emitted on line 218 of [inc/admin-page.php](inc/admin-page.php) and consumed by [inc/reading-time.php](inc/reading-time.php) — unrelated to analytics, kept as-is.
- **Activation step (manual)**: install the official Plausible Analytics plugin from wp-admin → Plugins → Add New, point it at `juanlentino.com`, and activate. No theme code change is required to plug it in — the plugin self-injects its tracking script via its own `wp_head` hook.

## [6.3.5] — 2026-05-02

### Fixed
- **Real fix for the `/notes` excerpt indent** that v6.3.4 missed. The actual culprit was the `max-width: 65ch` introduced in v6.3.3, not horizontal margin/padding bleed-through as v6.3.4 assumed. WordPress' generated layout CSS includes `.is-layout-constrained > :where(:not(.alignleft):not(.alignright):not(.alignfull)) { margin-left: auto !important; margin-right: auto !important }`, which auto-centres every direct child of a constrained group (`.sn-note-card` is one). Setting `max-width: 65ch` made the excerpt narrower than the sibling title (which uses the layout's 720px content-size), and the `!important` auto-margins centred the narrower box — what looked like a left-indent in the rendered page was actually horizontal centring. v6.3.4's `margin-left: 0` lost the cascade fight against `!important` and didn't reach the page. Removed `max-width: 65ch` (along with the now-unneeded margin/padding-left/right resets) in [assets/css/components.css](assets/css/components.css) — the constrained layout's 720px is already a sensible reading measure (~50ch at 0.9rem), and dropping the override lets the excerpt and title share the same width and the same auto-centring, so they align at the same x-position.

### Changed
- **`/notes` and `/` (home) post-card meta strip is now red.** Date, divider (`·`), and reading time on each card switched from `textColor:"rust"` (gray `#666666`) to `textColor:"blood"` (red `#e00404`) in [templates/page-notes.html](templates/page-notes.html) and [templates/home.html](templates/home.html). Brings the index meta strip in line with the red-accent treatment used on the Single-Note reading time (v6.3.1), the Provenance byline reading time (v6.3.1), and the Pillar Essay eyebrow on `/notes`. The previous v6.3.1 note arguing for keeping the meta strip gray ("internally consistent with gray dates") is now superseded — the user explicitly asked for red, and the brand's red-accent vocabulary is consistent across every other meta strip in the theme.

### Notes
- The general lesson from the indent regression: when introducing a `max-width` on a block inside an `is-layout-constrained` group, remember that core's `margin-left: auto !important; margin-right: auto !important` will *centre* the narrower box. To keep it left-aligned, either (a) match the parent's `--wp--style--global--content-size`, or (b) override with `margin-left: 0 !important` (specificity won't beat `!important` without it). Easiest is to not set `max-width` at all and rely on the constrained layout's content-size.
- Both home.html and page-notes.html were updated together because they render the same Notes list with identical card markup. Keeping them in sync is what readers expect — `/` and `/notes` should look the same.

## [6.3.4] — 2026-05-02

### Fixed
- **`/notes` excerpt left-alignment.** After v6.3.3 removed the `-webkit-line-clamp: 1` rule, `.sn-note-card-excerpt` was rendering visibly indented from its sibling title and meta — `display: -webkit-box` had been masking horizontal margin/padding bleed-through from WordPress core's `wp-block-post-excerpt` defaults, and removing it let those defaults push the excerpt rightward. Fix in [assets/css/components.css](assets/css/components.css): explicitly zero `margin-left` / `margin-right` / `padding-left` / `padding-right` on both the `.sn-note-card-excerpt` wrapper and its inner `<p>`. Excerpt now sits flush at the same x-position as the title.

### Notes
- The general lesson: when overriding core block CSS in a theme, be explicit about horizontal spacing rather than relying on browser/core defaults — `display` mode changes can mask bugs that resurface when you switch back to flow layout.

## [6.3.3] — 2026-05-02

### Changed
- **`/notes` excerpts now render in full.** Removed the `-webkit-line-clamp: 1` rule on `.sn-notes-list .sn-note-card-excerpt` (and its inner `<p>`) in [assets/css/components.css](assets/css/components.css). Excerpts were being visually truncated to a single line with a `…` ellipsis regardless of how much text the dek actually contained, which defeated the point of writing a dek at all — they exist to be read, not teased. Excerpts now wrap to their natural height; card rhythm is still handled by `.sn-note-card`'s margin- and padding-bottom, so the index continues to scan cleanly with multiple entries.
- **Auto-excerpt word cap raised from 24 → 55.** In [templates/page-notes.html](templates/page-notes.html) the `wp:post-excerpt` block's `excerptLength` attribute moved to WordPress' default. This only affects posts published *without* a manually-authored excerpt — when a dek is written in the editor, WP shows it verbatim and the cap doesn't apply. The previous 24 was paired with the CSS clamp; with the clamp gone, 24 was leaving auto-fallback excerpts mid-sentence.
- **`.sn-note-card-excerpt` gets `max-width: 65ch`** so excerpts that wrap stay inside a comfortable reading measure and don't run the full content width on wide screens.

### Notes
- This reverses the "one-line deks" half of v6.2.7 (the pillar card and RSS footer changes from that release stay). The v6.2.7 design choice optimised for index density on a list with 6+ entries; the new behaviour optimises for reading the deks as standalone sentences, which is closer to how they're actually written.
- No CSS for the single-Note view changed. `single.html` continues to render the full post body via `wp:post-content`.

## [6.3.2] — 2026-05-01

### Added
- **`inc/og-image.php` — per-post OG/Twitter card generator.** Every post and page now ships its own 1200×630 brutalist text card on Twitter / iMessage / Slack / LinkedIn unfurls. Cards are rendered server-side with PHP GD using the brand's own typefaces (Bebas Neue Regular for the title, DM Mono Light for the eyebrow / dek / footer), cached as PNGs in `wp-content/uploads/sn-og/post-{ID}.png`, and rebuilt automatically on every `wp_after_insert_post`. Layout: red 60×4px accent bar top-left, "JUANLENTINO.COM" eyebrow in DM Mono, post title in Bebas Neue at 88pt wrapped to 3 lines, 3-line dek (post excerpt or first ~36 words of cleaned content), and "X MIN READ" in red as the footer. Cache-busted via `?v={post-modified-time}` so re-shares pick up edits without manual invalidation.
- **TTF fonts for server-side rendering** — `assets/fonts/og/BebasNeue-Regular.ttf` (61 KB, fetched from `dharmatype/Bebas-Neue` 2018 release) and `assets/fonts/og/DMMono-Light.ttf` (49 KB, from `googlefonts/dm-mono`). Both files are SIL OFL and are loaded only by `imagettftext()` — they're never referenced from CSS, so the existing WOFF2 preload pipeline is unaffected.
- **Lazy backfill — no migration needed.** The URL helper `sn_og_image_url_for_post()` checks for the cached PNG on every request and generates it on miss. Existing posts will get cards on their first social share without any one-time admin button or scheduled job.
- **Yoast SEO integration.** Yoast emits OG/Twitter tags first in `<head>` and wins the social-card scrape race, so we hook `wpseo_opengraph_image`, `wpseo_twitter_image`, and `wpseo_opengraph_image_size` to feed Yoast the same generated card URL the theme uses. If Yoast isn't installed the filters never fire — the module is degradation-safe.

### Changed
- **Resolution order for `<meta property="og:image">` is now**: (1) post's featured image, if set — never overridden; (2) generated card from `inc/og-image.php`; (3) theme default (the existing site-icon URL). The theme's own emitter in [inc/seo.php](inc/seo.php) reads through the existing `sn_og_image_url` filter, so it picks up the generated card automatically alongside Yoast.

### Notes
- **Robustness**: every code path that touches GD is gated behind `function_exists('imagettftext')`, every font path is `file_exists()`-checked, and the upload dir is `wp_mkdir_p()`-ensured. On any failure (GD missing, FreeType missing, fonts missing, dir unwriteable) the helper returns `null` and callers cascade to the previous default — OG cards aren't user-blocking and shouldn't take down a request if something is misconfigured.
- **Why GD, not Imagick or `@vercel/og`**: GD ships with the standard Cloudways PHP build and needs no external service or Edge Worker; the brand's typography is plain TTF; the cards are static once written. Adding a Worker for this would be operational overkill given the target audience (a personal site that publishes Notes weekly, not at fan-out scale).
- **Why not a Yoast-only site icon override**: Yoast's per-post default image comes from a single global setting in admin, not a per-post programmatic value — there's no "URL function" hook in Yoast's UI. Filtering at the PHP level is the supported route.

## [6.3.1] — 2026-05-01

### Added
- **Reading time on the Provenance byline.** `[sn_reading_time]` now renders inside the `sn-provenance-byline` flex group on `/provenance/`, sitting after the (modified) post-date with a `·` divider in between. Coloured `blood` (`#e00404`) so it reads as an accent, overriding the byline group's parent `rust` (gray) inheritance. New `.sn-provenance-byline-reading-time` and `.sn-provenance-byline-divider` selectors are referenced from the markup but the classes carry no extra CSS rules — they're hooks for future styling if needed.
- **`inc/seed-content/provenance-body.html` updated** so fresh installs ship with the reading-time byline baked in. Migration `sn_migrate_provenance_byline_reading_time()` (gated by the new `sn_provenance_byline_reading_time_migrated_v1` option flag) injects the same markup into the existing live page on next `admin_init`. New helper `sn_provenance_byline_reading_time_markup()` keeps the seed and the migration in lockstep — change the markup once, both ship the same shape. The migration is idempotent (skips if the marker class is already present in the body, defending against manual paste).
- **`displayType:"modified"` on the Provenance byline post-date** is now part of the seed (it had been added by the 6.2.6 refinements migration but the seed file still carried the original markup). Fresh installs and the live page now match.

### Changed
- **Single-Note reading time → red.** `templates/single.html` reading-time block switched from `textColor:"rust"` (gray `#666666`) to `textColor:"blood"` (`#e00404`) so it matches the red post-date next to it. The previous gray-next-to-red mismatch was a visible inconsistency on every Note. `/notes` index cards and `/` home cards stay gray on purpose — their dates are gray, so the meta strip is internally consistent and switching reading time alone would unbalance it.

### Notes
- The `rust` colour slug in `theme.json` is named "Steel" with hex `#666666` — historical mis-naming of the slug; the value is correct for its actual role (secondary/dim text on deks, excerpts, post-meta). The fix in this release uses the `blood` slug rather than redefining `rust`, so existing usages across `/services`, `/work-with-me`, `/contact`, etc. are untouched.
- Version: first patch on 6.3.x. Patch cap is 7 per minor.

## [6.3.0] — 2026-05-01

### Added
- **`inc/reading-time.php` — Cached reading-time module.** New module owning calculation, caching, and legacy cleanup. The `[sn_reading_time]` shortcode (previously living at the bottom of `inc/notes-and-provenance.php` at 200 WPM with no cache) is rebuilt here at **225 WPM** default with the result stored in the private `_sn_reading_time_minutes` post meta. The cache is rebuilt automatically on every post save via `wp_after_insert_post`, populated lazily on first render for any pre-existing posts, and recomputed after the cleanup tool runs. WPM is filterable via `sn_reading_time_wpm`; output format via `sn_reading_time_format` (default `"{minutes} min read"`, supports `"{minutes}-minute read"` for the long form).
- **Calculation strips block delimiters before counting.** `sn_calculate_reading_time()` runs `<!-- wp:* -->` removal → `strip_shortcodes()` → `wp_strip_all_tags()` → `str_word_count()` so Gutenberg block markup, our own shortcodes, and embedded HTML don't inflate the word total. One-minute floor preserved so a haiku still renders "1 min read".
- **Legacy reading-time cleanup tool — Appearance → Signal & Noise → Dashboard.** Two-step Preview / Apply pair gated behind the existing `sn_theme_options_nonce`. The Preview button runs `sn_find_legacy_reading_time()`, which scans every `post`/`page` (any status) for the regex `/~?\s*\d+\s*[-\s]\s*(?:minutes?|mins?)\s+read\b/i` across `post_content`, `post_excerpt`, and public custom fields, then renders a table of every match with a 50-char-per-side context snippet (the match itself wrapped in `<<…>>` markers) and a link to edit each post. Apply removes the matched substrings, collapses any `<p></p>` / `<span></span>` / `<small></small>` / `<em></em>` / `<strong></strong>` / `<i></i>` / `<b></b>` shells the removal leaves behind, then deletes the cached reading-time meta and re-derives it from the now-clean content. Private meta keys (any starting with `_`, including our own `_sn_reading_time_minutes`) are excluded from the scan by design.
- **`do_action( 'sn_admin_dashboard_extras' )` extension point.** New action fired in `inc/admin-page.php` at the end of the Dashboard tab so future modules can inject cards without editing the admin page directly. The reading-time cleanup card is the first consumer.

### Changed
- **Default words-per-minute raised from 200 to 225.** Closer to the median adult silent reading pace cited in the literature; lines up with the Medium/Substack defaults. Existing posts will reflect the new pace on next save (or on first cache miss for posts never edited under the new module).
- **`inc/notes-and-provenance.php` — shortcode + render_block bridge removed.** Both moved to `inc/reading-time.php`. The file now ends at the `restrict_main_query_for_notes_page` hook; a one-line stub comment marks where the old shortcode lived.

### Notes
- Versioning: per the project rule, the patch cap of 7 was hit at 6.2.7, so this lands as **6.3.0** (next minor) rather than 6.2.8. Code-and-functional change, so a version bump is warranted.
- Templates (`single.html`, `home.html`, `page-notes.html`) continue to embed `[sn_reading_time]` literally — no markup change needed; they automatically pick up the cached/upgraded behaviour.
- Cleanup is intentionally additive-safe: the regex is anchored on the literal word `read` and tolerates `min`/`mins`/`minute`/`minutes`, optional hyphens, and an optional leading `~`. It will not match the shortcode token `[sn_reading_time]`, nor will it touch private (`_`-prefixed) meta. Always run **Preview** first; the Apply button shows the affected post count next to its label so there's no chance of running it blind.

## [6.2.7] — 2026-04-25

### Changed
- **`/notes` index — pillar essay promoted from inline link to featured card.** The italic one-liner above the page H1 ("The pillar essay: Provenance Over Detection →") is replaced by a visually distinct card sitting between the page header and the Notes list. The card uses the existing asphalt-background + concrete-border treatment from `.sn-provenance-panel` — no new tokens — and contains a `PILLAR ESSAY` eyebrow, an `<h2>` title in `font-display`, the dek "A short read on why the industry needs to prove what's human, not chase what isn't.", and a `Read essay →` CTA in the heading font (uppercase, 0.15em letter-spacing, blood-on-signal hover). When a second pillar essay ever exists this section will be generalised into a list — for now it's hardcoded.
- **`/notes` index — Notes list tightened to one-line deks.** Each card's excerpt is now CSS-clamped to a single line via `-webkit-line-clamp: 1` on `.sn-note-card-excerpt` (and its inner `<p>`). Single-Note pages render the full body via `wp:post-content` and are unaffected. Per-entry density goes down without changing the existing list rhythm.
- **`/notes` page subtitle copy.** Replaced "Short essays on music, AI, and the systems behind both." with "Working notes on music, AI, and the infrastructure underneath. Written when there's something worth writing." Same position, same type style. Updated in three places that must stay in lockstep: the visible markup in `templates/page-notes.html` and `templates/home.html`, the seed `post_excerpt` in `inc/notes-and-provenance.php`, and the hardcoded SEO description in `inc/seo.php` (which feeds the `<meta name="description">` tag and OG/Twitter cards on `/notes`).

### Added
- **`/notes` index — RSS footer line.** A caption-size, opacity-dimmed line below the Notes list, separated by a hairline rule and a `spacing-40` spacer: "No subscription form, no schedule. Notes available via RSS." The "RSS" word links to `/notes/feed/` (the WordPress-generated feed for the Notes category). New `.sn-notes-rss` selector in `assets/css/components.css`.

### Notes
- All four changes use existing theme tokens — no new entries in `theme.json`. The pillar card reuses the asphalt+concrete panel pattern; the eyebrow reuses the `.sn-provenance-eyebrow` size/weight/colour treatment; the CTA reuses the heading-font uppercase pattern from `.sn-note-pillar-link` on the single-Note template.
- The dropped `.sn-notes-pillar-link` selector in `assets/css/components.css` previously shared a rule with `.sn-provenance-toc`. The rule is preserved for the TOC; the unused first selector is removed.
- This consumes the seventh patch in 6.2.x. Per project versioning, the next change must bump to 6.3.0.

## [6.2.6] — 2026-04-25

### Added
- **One-time migration** `sn_migrate_provenance_refinements()` in `inc/notes-and-provenance.php` that runs on next `admin_init` and surgically:
  - Injects the inline TOC paragraph between the Provenance hero and the first separator (skipped if `.sn-provenance-toc` is already present in the body — defensive against the case where the snippet was already pasted manually).
  - Adds `displayType: "modified"` to the byline's `wp:post-date` block so the date reads as "last updated" rather than "first published" — more honest semantics for a permanent reference essay that gets iterated on.
- New TOC block markup factored into `sn_provenance_toc_block_markup()` so the seed file (`inc/seed-content/provenance-body.html`) and the migration share a single source of truth.
- Updated the seed file itself (TOC + `displayType: "modified"`) so future installs ship with both refinements baked in.

### Notes
- Migration is gated by `sn_provenance_refine_migrated_v1` option flag — runs once per site, never re-runs. Both edits are idempotent (skip if already applied), so manual snippet-paste before the update doesn't cause double-injection.
- Prose paragraphs are never touched. Migration only inserts net-new content (TOC) and modifies one block attribute (post-date display type).
- This is the sixth patch in 6.2.x — one more remaining (6.2.7) before the next change must bump to 6.3.0 per project versioning.

## [6.2.5] — 2026-04-25

### Fixed
- **In-page anchor jumps no longer hide the section heading behind the fixed header.** Added `scroll-padding-top` on `html` matched to the body's `padding-top` for the fixed header at each breakpoint (124px desktop, 96px tablet, 81px mobile — header height plus a 16px breathing buffer). Site-wide fix, not just for the Provenance TOC.

### Added
- **`.sn-provenance-toc` link styling** in `assets/css/components.css` — bone-coloured links with concrete-grey hairline underline that strengthens to red on hover. Folded into the existing `.sn-notes-pillar-link` selector group so the TOC links read as the same understated treatment used by the inline pillar link on `/notes`.

### Notes
- The TOC itself is editorial content (lives in the Provenance Page body, not in any template). If you've already pasted the TOC snippet from earlier into the page, the links pick up the hairline-grey treatment automatically once this version lands.

## [6.2.4] — 2026-04-25

### Added
- **Reading time on `/notes` index cards.** Each Note card now shows date · reading time (matching the meta strip on the single-Note template). Uses the existing `[sn_reading_time]` shortcode inside the query loop's post-template — resolves per-post automatically. Applied to both `templates/page-notes.html` (Page route) and `templates/home.html` (Posts-page route).
- **Open Graph + Twitter card meta** for the front page, the Notes index, and every singular post/page. Emits `og:type` (article for posts, website otherwise), `og:title`, `og:description`, `og:url`, `og:site_name`, `og:image`, plus the matching `twitter:*` set.
- **`sn_seo_meta_for_current_view()` helper** in `inc/seo.php` — returns the active page's `[ $title, $description, $url ]` so the description tag and OG/Twitter tags share one source of truth.
- **`sn_og_image_url` filter** so the OG image can be overridden per-route or globally without touching theme code. Default is the site logo (`/wp-content/uploads/2026/02/cropped-jl_logo-min-300x300.png`); `summary_large_image` Twitter card is emitted when an image is present, falling back to `summary` when filtered to empty.

### Notes
- No new design tokens. Reading time on cards uses the same `0.75rem / uppercase / letter-spacing 0.15em / rust` treatment as the existing card date — visually it just becomes "DATE · 3 MIN READ".
- The OG image default is square (300×300, the site logo); for richer previews on social, set a 1200×630 image via the filter:
  ```php
  add_filter( 'sn_og_image_url', function() {
      return 'https://juanlentino.com/path/to/og-1200x630.jpg';
  } );
  ```

## [6.2.3] — 2026-04-25

### Fixed
- **`/notes` was rendering the first Note inside the index chrome.** When `/notes` is wired as the WP Posts page (`is_home()` context), `wp:post-title` and `wp:post-content` outside a query loop both resolve to the first post in the loop — not to the `page_for_posts` Page — because `get_the_ID()` inside the template returns the loop's first post ID. So the v6.2.2 `home.html` rendered: pillar link → first post's title (as a giant H1) → dek → separator → query loop → first post's full body dumped underneath.
- Replaced `wp:post-title` with a hardcoded `<h1>NOTES</h1>` in `templates/home.html`. The Page is always called "Notes"; a hardcoded heading is the simplest correct answer for this template's only context.
- Removed the trailing `wp:post-content` block from `templates/home.html`. It had no purpose in `is_home()` context — the query loop above already shows the posts.
- Both fixes are scoped to `home.html` only. `page-notes.html` (the regular Page route) still uses `wp:post-title` and `wp:post-content` correctly because in that context `get_the_ID()` returns the queried Page.

### Note on patch ceiling
- This is the third patch in 6.2.x (6.2.1, 6.2.2, 6.2.3) — the project's Apple-style versioning rule caps patches at 3/minor. Any further changes in this area will need to bump to 6.3.0.

## [6.2.2] — 2026-04-25

### Fixed
- **`/notes` index pillar link wasn't rendering.** When `/notes` is wired as the WP **Posts page** (Settings → Reading), WordPress routes the URL through `home.html` → `index.html` instead of the Page's custom template (`page-notes.html`). The pillar link, the page title, the dek, and the separator all live in `page-notes.html`, so none of them surfaced — only the bare query loop from the v6.0.0 inherited `index.html` did.
- Added `templates/home.html` mirroring `page-notes.html` exactly. WP picks `home.html` over `index.html` for the Posts page, so the pillar link and chrome now render regardless of how `/notes` is wired in the install (custom Page template OR WP Posts page).

### Notes
- No changes to `index.html` — it stays as the generic fallback for other archive contexts (search, dates, etc.) so the pillar link doesn't pollute unrelated pages.
- If you'd rather route `/notes` through `page-notes.html` (Page template) and remove the Posts-page setting, that's still valid — the new `home.html` just makes the rendering consistent regardless of the route.

## [6.2.1] — 2026-04-25

### Removed
- **Homepage Featured Essay card.** Reverted `templates/front-page.html` to its v6.1.3 state — the asphalt-tinted section + Apple-style card below the hero is gone. Didn't fit the homepage's voice.
- **`.sn-featured-essay*` CSS** in `assets/css/components.css` and `assets/css/responsive.css` (all base, hover, link, mobile-padding, and touch-device-override rules). The card was the only consumer; removing it now keeps the stylesheet honest.

### Kept (unchanged from v6.2.0)
- `/notes` index inline pillar link (`.sn-notes-pillar-link`) — *"The pillar essay: Provenance Over Detection →"* above the page title.
- Single Note footer pillar link (`.sn-note-pillar-link`) — "Start with the pillar →" above "← All Notes".

## [6.2.0] — 2026-04-25

### Added
- **`/provenance` pillar surfaced in three places, with three distinct visual treatments calibrated to each surface:**
  1. **Homepage** (`templates/front-page.html`) — full Apple-style card directly below the hero, on an asphalt-tinted section. White card with eyebrow ("Featured Essay"), title (links to `/provenance`), dek, and "Read essay →" CTA. Subtle dual-layer shadow; hover lifts the card 2px and deepens the shadow. The card is the front door for someone who's never visited before.
  2. **`/notes` index** (`templates/page-notes.html`) — single muted italic line above the "NOTES" page title: *"The pillar essay: [Provenance Over Detection →]"*. Link uses a hairline-grey underline that strengthens on hover and the text turns red. No card, no eyebrow, no section background — reads as "by the way…", appropriate to a list page where the chronological notes themselves are the main affordance.
  3. **Every Note** (`templates/single.html`) — a "Start with the pillar →" footer link automatically rendered above the existing "← All Notes" link. Same heading-font / underline-on-hover treatment, so the two read as a coherent pair.
- New CSS classes in `assets/css/components.css`:
  - `.sn-featured-essay` (homepage card) — Apple-card style using only existing theme tokens.
  - `.sn-notes-pillar-link` (notes-index inline link) — bone-coloured link with hairline-grey underline.
  - `.sn-note-pillar-link` (single-Note footer) — folded into the existing `.sn-note-back` selector group for consistent treatment.
- Mobile padding tightening for the homepage card and its section wrapper at `max-width: 640px`. Hover transform on the card is disabled on touch devices via the existing `(hover: none)` block in `assets/css/responsive.css`.

### Notes
- No new design tokens. All surfaces use `var(--wp--preset--color--*)`, `var(--wp--preset--font-family--*)`, and the existing `--wp--preset--spacing--*` scale. Shadow values are CSS rules using rgba black, not new colour tokens.
- No new components, no new dependencies, no new plugins.
- Title sizing on the homepage card matches the existing Notes-list post-title clamp (`clamp(1.8rem, 3vw, 2.5rem)`), so the two surfaces feel related at a glance.

## [6.1.3] — 2026-04-25

### Fixed
- **"← All Notes" footer link no longer cramped against the fixed footer.** Bumped `main` bottom padding on `templates/single.html` and `templates/page-provenance.html` from `spacing--60` (96px) to `spacing--70` (128px). The fixed footer chrome (social icons + language toggle + copyright) is ~76–90px tall and overlays the bottom of the viewport; the previous 96px padding only bought ~6px of breathing room above the link when scrolled to the bottom. 128px gives a comfortable ~38px clearance.

## [6.1.2] — 2026-04-25

### Changed
- **Note layout is now the default for any single post.** Replaced `templates/single.html` with the Note layout (date · reading time, title, body, "← All Notes" footer link). Deleted the redundant `templates/single-note.html`. Removed the `single_template_hierarchy` filter from `inc/notes-and-provenance.php` — no longer needed now that there's a single source of truth.
- **New posts are now in the Notes category by default.** `sn_sync_default_category()` runs cheaply on every `admin_init` and points WordPress's `default_category` option at the Notes category term. Self-healing if the option ever drifts.
- Net effect: **Posts → Add New → write → Publish** produces a fully-formed Note. No template dropdown to find, no category checkbox to remember, no Site Editor visit. The post renders with the Note layout AND appears at `/notes` immediately.

### Removed
- `templates/single-note.html` (collapsed into `single.html`)
- `single_template_hierarchy` filter (no longer needed — there's only one single-post template now)
- `single-note` entry from `theme.json` `customTemplates` (already gone in this release; the dropdown surface was the wrong UX anyway)

## [6.1.1] — 2026-04-24

### Changed
- **Provenance pillar content moved from template to Page body.** All visible content (hero, five anchored sections, Section 2 SVG diagram, footer CTA, dynamic byline) now lives in the `/provenance` Page itself instead of `templates/page-provenance.html`. The template is now a thin shell: header part, `wp:post-content`, footer part. Editing prose is now a Pages → Provenance click — no more Site Editor required, and edits survive theme updates (Page bodies aren't purged by the existing `template-maintenance.php` version-bump auto-clear, which only targets `wp_template`/`wp_template_part`/`wp_navigation` post types).
- `sn_ensure_provenance_page()` now seeds the body from `inc/seed-content/provenance-body.html` on fresh installs.

### Migration
- `sn_migrate_provenance_body()` runs once per site on `admin_init`, guarded by `sn_provenance_body_migrated_v1`. Sites upgrading from v6.1.0 (where the Provenance Page body was empty) get the seed content auto-installed into the existing Page. Never overwrites a non-empty body.

## [6.1.0] — 2026-04-24

### Added
- **Notes content surface.** WordPress Posts re-enabled with a single `Notes` category (slug `notes`). Permalink structure set to `/notes/%postname%/` on first activation, guarded so it only fires when the current structure is different AND no existing posts would have their URLs broken.
- **`/notes` index** rendered via the `page-notes.html` custom Page template. Query loop is scoped to the Notes category at runtime via the `query_loop_block_query_vars` filter (queryId `42`) — keeps the markup independent of any specific category-term ID. No sidebar, no thumbnails, no pagination UI; empty/low-count state renders a graceful "No notes published yet" message via `wp:query-no-results`.
- **`single-note.html` template** for individual Notes — date, reading time, title, body, footer "← All Notes" link. No comments, pings, share buttons, related posts, or author bio markup. Auto-routed for posts in the Notes category via the `single_template_hierarchy` filter; editors can still pick a different template explicitly.
- **`/provenance` static Page** rendered via `page-provenance.html`: hero (title, subhead, "4 min read", SSRN secondary CTA), five anchored sections (`#setup`, `#analogy`, `#what-it-means`, `#why-it-matters`, `#the-shift`), the Detection-vs-Provenance SVG visual in Section 2, footer CTA with two equal-weight links (SSRN paper + `/notes`), and a dynamic byline using the page's published date via `wp:post-date`.
- **Detection-vs-Provenance SVG diagram** (Section 2): two side-by-side panels using `currentColor` plus a single `--wp--preset--color--blood` accent token. Accessible via `role="img"`, `aria-labelledby`, `<title>`, `<desc>`. Stacks vertically below 640px.
- **Meta description** dedicated copy for `/notes` ("Short essays on music, AI, and the systems behind both.") and `/provenance` (mirrors the subhead). Other singular pages continue to fall back to the post excerpt.
- **`Notes` link** added to the main nav between `Resume` and `Contact`. `Provenance` is intentionally NOT added to the main nav (it's reserved for a future homepage essay teaser).
- **`[sn_reading_time]` shortcode** — 200 wpm calculation with a one-minute floor — wired into the existing `render_block` shortcode resolver pattern (mirror of `[current_year]`).

### Architecture
- New module `inc/notes-and-provenance.php` (idempotent activation seeder for the Notes category, the `/notes` Page, and the `/provenance` Page; permalink structure guard; query/template filters; the reading-time shortcode). Module map in `functions.php` updated.
- `inc/seo.php` extended (no breaking changes) with `is_page('notes')` and `is_page('provenance')` branches in the existing meta-description handler. No SEO plugin work, no Open Graph / Twitter card additions (no existing theme OG defaults to mirror — left to the installed SEO plugin).
- Three new custom templates registered in `theme.json`: `page-notes`, `page-provenance`, `single-note`. No new colour tokens, font families, font sizes, font weights, or spacing values introduced — all new CSS references existing `theme.json` tokens.

### Notes
- Discussion features (comments, pings, trackbacks, XML-RPC) untouched — they remain disabled at the WordPress + infrastructure layer per project policy. New templates ship with no comment / ping / trackback markup.
- Provenance section bodies are marked `[DRAFT — replace with final prose]` for the user to fill in. Section copy lives in the template; the Page itself is created empty.
- No new plugins. No taxonomy work beyond the single `Notes` category (no tags).

## [6.0.0] — 2026-04-13

### Architecture
- `functions.php` split from 1267 lines into a 40-line bootstrap + 10 `inc/*.php` modules (setup, assets-frontend, seo, frontend-filters, plausible-api, admin-assets, dashboard-widgets, template-maintenance, admin-page, updater).
- `assets/css/custom.css` split from 1125 lines into 5 modules (base, layout, components, forms, responsive) loaded in a dependency chain so `responsive.css` always prints last. `add_editor_style()` receives the same five paths so the Site Editor matches the front end.

### Security
- Pinned CDN assets (jsvectormap 1.6.0, Chart.js 4.4.4) now carry `integrity="sha384-..."` and `crossorigin="anonymous"` via `script_loader_tag` / `style_loader_tag` filters.
- Scoped transient purges: `Purge All Caches` and `Full Reset` now delete only `_transient_sn_*` rows instead of wiping every plugin transient on the site.
- Fixed a latent CDN 404: old inline `<link>` referenced `/dist/css/jsvectormap.min.css`, which does not exist in 1.6.0. Correct path is `/dist/jsvectormap.min.css`. The visitor map had been rendering unstyled, which contributed to the zoom overflow symptoms.

### Admin UX
- **Visitor map zoom no longer escapes the card.** `overflow:hidden` + `position:relative` on the container; `zoomOnScroll`/`zoomButtons`/`panOnDrag` disabled (map is hover-tooltip only).
- Top Stats and Breakdowns tabs rewritten as `<button role="tab">` with full ARIA semantics (`aria-selected`, `aria-controls`, `aria-labelledby`, `hidden` panels) and arrow-key / Home / End keyboard navigation.
- GitHub updater surfaces failures: missing `SN_GITHUB_TOKEN` shows a warning notice; WP_Error or non-200 responses capture into `sn_github_error` and show an error notice on Dashboard / Updates / Themes screens.
- Plausible API mirrors the pattern: WP_Error and non-200 captured into `sn_plausible_error` with a matching admin notice on Dashboard and the theme options page.
- `check_updates` and `full_reset` handlers now also clear `sn_github_error` so the notice self-heals after a manual retry.
- `$_GET['tab']` on the theme options page is validated against `[dashboard, analytics, links]` with a fallback to `dashboard`.

### Code cleanup
- Four echoed inline `<script>` blocks (two map widgets, chart widget, Top Stats tabs) extracted to `assets/js/admin-map.js`, `admin-tabs.js`, `admin-chart.js`. Vendor + theme JS registered once and enqueued per screen via `admin_enqueue_scripts`.
- Two visitor-map implementations collapsed: both widgets now emit `<div class="sn-map-widget" data-sn-map="...">` and `admin-map.js` auto-inits any element with that attribute.
- 44 hardcoded hex colours in `custom.css` → `var(--wp--preset--color--*)` tokens from `theme.json`. Remaining two are `#999999` (neutral placeholder, not a brand colour).
- `SN_PLAUSIBLE_URL` outputs now wrapped in `esc_url()` and carry `rel="noopener"` alongside `target="_blank"`.
- `Tested up to:` corrected from `6.9` (unreleased) to `6.8`.

### Known deferred
- 181 `!important` rules remain across the stylesheets. Most override WP block-editor inline styles and can't be pruned without browser-side verification. Splitting the file has at least made the clusters easier to locate for a future dedicated pass.
- `inc/admin-page.php` is 437 lines — a single feature (three-tab options UI). Further sub-splitting would fragment an `elseif` chain across files.
- `assets/css/critical.css` at 501 lines hasn't been touched; flagged for a future audit.
- Dark mode (`data-theme="dark"`) is not implemented. The theme is intentionally light-only per the NIN/brutalist aesthetic; noting the deviation from the global rule for a future decision.

## [5.1.0] — 2026-03-31

### QA cleanup
- Removed dead CSS: .sn-line-accent, .sn-hero-image, .sn-accent-line, .sn-service-card (4 classes, ~30 lines across custom.css and critical.css)
- Removed dead @keyframes lineExpand (no longer referenced after accent-line removal)
- Footer copyright: hardcoded "2026" replaced with [current_year] shortcode (shortcode + block processor already existed)
- Services CTA: "Get In Touch →" now links to /work-with-me instead of /contact

## [5.0.3] — 2026-03-31

- Removed redundant "Book a session below." from Work With Me subtitle
- Swapped steps 2 and 3: Book & Begin is now 02, Custom Quote is now 03 (book first, quote for larger projects comes after)

## [5.0.2] — 2026-03-31

- Compacted How It Works on Work With Me: smaller numbers (2.5→1.8rem), shorter descriptions (10 words max), tighter padding, removed spacer

## [5.0.1] — 2026-03-31

- Moved "How It Works" process strip from Services to Work With Me (belongs where booking happens)
- Step 3 renamed from "Production" to "Book & Begin" with copy pointing to the calendar below
- Services page goes straight from service cards to closing CTA

## [5.0.0] — 2026-03-31

### Full Analytics Dashboard
- Replaced Analytics tab stub with a complete Plausible-powered dashboard
- Date range picker: 7d, 30d, 6 months, 12 months (query param, no page reload framework needed)
- 6 metric cards with period-over-period comparison: Visitors, Visits, Pageviews, Views/Visit, Bounce Rate, Visit Duration
- Visitor trend chart (Chart.js 4.4.4): dual-line visitors + pageviews with responsive axes
- Visitor map (jsvectormap 1.6.0): choropleth colored by traffic volume, respects selected date range
- 13 tabbed breakdown panels: Pages, Entry Pages, Exit Pages, Sources, Referrers, UTM Medium, UTM Source, UTM Campaign, Countries, Cities, Devices, Browsers, OS
- All data cached with transients (5 min for 7d, 15 min for longer ranges)
- Graceful degradation if SN_PLAUSIBLE_KEY not set
- Link to external Plausible dashboard preserved in header

## [4.5.2] — 2026-03-31

- Fixed Visitor Map not highlighting countries: removed strtolower() on country codes (jsvectormap expects uppercase ISO 3166-1 alpha-2 codes matching Plausible's format)
- Fixed map script loading race condition: replaced DOMContentLoaded with window load + polling fallback; pinned jsvectormap CDN to v1.6.0

## [4.5.1] — 2026-03-31

- Removed footer border-top separator (whitespace does the job)
- Hero accent line widened from 60px to 120px (aligns with "hold" in subtitle)

## [4.5.0] — 2026-03-31

- Footer: swapped SDG and copyright order (SDG → © rightmost)

## [4.4.2] — 2026-03-31

### QA pass — CSS sync audit
- Fixed film grain opacity mismatch: custom.css had 0.025, critical.css had 0.035 (synced to 0.035)
- Fixed footer border conflict: critical.css was overriding custom.css border-top with `none !important`. Synced both to show the 1px concrete border
- Fixed header transition: custom.css was missing `background-color 0.3s ease` from transition, causing the frosted-glass opacity to snap instead of fade on scroll
- Fixed header scrolled state: custom.css was missing `background-color: rgba(255,255,255,0.85)` and glass morphism properties. Both CSS files now match
- Removed dead `#trp-floater-ls` CSS (TranslatePress repositioned via plugin settings)

## [4.4.1] — 2026-03-31

- TranslatePress floating switcher: repositioned above fixed footer (bottom: 70px) so it doesn't overlap copyright/SDG text

## [4.4.0] — 2026-03-31

- Hero alignment: golden-ratio offset (15vw on wide screens) instead of flush container edge
- Footer redesign: socials left, copyright + Soli Deo Gloria right (space-between flex layout)
- Footer layout changed from centered constrained to full-width flex

## [4.3.3] — 2026-03-31

- Fixed hero left-alignment positioning: content was flush to viewport edge (only offset by section padding). Added dynamic padding-left using max() to position content where a centered 1100px container would start on wide screens, falling back to theme spacing on narrow screens

## [4.3.2] — 2026-03-31

- Fixed hero left-alignment: WP constrained layout applies margin-left:auto as inline styles on children, so :where() selector (v4.3.0) couldn't override them. Switched to scoped !important on .sn-hero.is-layout-constrained > *

## [4.3.1] — 2026-03-31

- Version bump to trigger self-updater (4.3.0 was already on server)

## [4.3.0] — 2026-03-31

### Sizing & Polish Pass
- Logo: 56→80px desktop, 44→56px tablet, 38→44px mobile; scrolled states scaled proportionally
- Nav text: 0.9rem→1rem
- Hero subtitle: 1.1→1.15rem (1.2rem on 1440px+)
- Hero left-alignment: CSS-only fix using `:where()` selector to override WP constrained layout auto-margins without breaking alignwide/alignfull; subtitle capped at 640px
- XL desktop breakpoint (1440px+): body text 1.05rem, button padding scaled, hero subtitle 1.2rem
- CF7 submit button: styles duplicated into critical.css as Breeze minification insurance
- Body padding-top and hero min-height synced across all breakpoints (desktop/tablet/mobile) in both custom.css and critical.css
- Header HTML `width`/`height` attributes updated to match CSS values

## [4.2.0] — 2026-03-31

- Fixed Breeze CSS minification: moved custom.css to `wp_enqueue_style` so `breeze_exclude_css` filter works
- Breeze was ignoring the filter because custom.css was echoed as raw HTML, not enqueued

## [4.1.0] — 2026-03-31

- Consolidated dashboard: 4 widgets instead of 6
- Visitor Trend: 30-day red bar chart with total count
- Top Stats: single tabbed widget (Pages/Sources/Countries/Devices/Browsers)
- Visitor Map: world choropleth via jsvectormap, colored by traffic

## [4.0.0] — 2026-03-31

- 6 native Plausible dashboard widgets pulling from Stats API
- Visitors Today, Top Pages, Top Sources, Top Countries, Devices, Browsers

## [3.14.3] — 2026-03-31

- Restored default WP dashboard widgets, Plausible full-width on top

## [3.14.2] — 2026-03-31

- Clean dashboard: removed default widgets (reverted in 3.14.3)

## [3.14.1] — 2026-03-31

- Plausible analytics widget on WP Dashboard

## [3.14.0] — 2026-03-31

- Tabbed admin page: Dashboard, Analytics, Links tabs

## [3.13.0] — 2026-03-31

- Plausible CE tracking script on frontend (defer, ~1 KiB, cookie-free)
- Plausible dashboard embedded in admin page

## [3.12.1] — 2026-03-31

- Removed WP Statistics cleanup code (plugin uninstalled)

## [3.12.0] — 2026-03-31

- Signal & Noise admin page: status panel, actions (Full Reset, Clear Overrides, Purge Caches, Check Updates), links

## [3.11.1] — 2026-03-31

- Test release for self-updater verification

## [3.11.0] — 2026-03-31

- GitHub self-updater: checks releases, one-click update from WP admin
- `upgrader_source_selection` filter fixes the `-1` folder rename problem

## [3.10.4] — 2026-03-31

- Fixed contact form radio label: `.wpcf7-form p.form-label` styled to match other labels

## [3.10.3] — 2026-03-31

- Red accent line (60px) between hero subtitle and CTA buttons with fadeInUp animation

## [3.10.2] — 2026-03-31

- Removed default nav underline (`text-decoration: none`)
- Thickened red accent from 1px to 2px

## [3.10.1] — 2026-03-31

- Auto-clear template parts + `wp_navigation` on theme update
- Version-change detector triggers full override clear

## [3.10.0] — 2026-03-31

- Logo switched to media library images (persistent across theme uploads)

## [3.9.9] — 2026-03-31

- Fixed 60-min Cal.com tab: lazy init on first click (can't render into hidden container)
- Removed prices from tab labels

## [3.9.8] — 2026-03-31

- Replaced Cal.com shortcodes with JS inline embeds (shortcodes don't render in block theme templates)

## [3.9.7] — 2026-03-31

- Added Work With Me to header navigation (hardcoded in parts/header.html)

## [3.9.6] — 2026-03-31

- Full revert of `front-page.html` to pre-session state (v3.8.1)
- Restored original subtitle paragraph (simple `1.1rem`, no clamp, no max-width)

## [3.9.5] — 2026-03-31

- Reverted hero CSS to pre-session state; removed `margin-left: 0` that pushed content to page edge

## [3.9.4] — 2026-03-31

- Fixed hero left-alignment: overrode WP constrained layout `margin-left: auto` on hero children
- WP's `is-layout-constrained` was centering content despite CSS flexbox `align-items: flex-start`

## [3.9.3] — 2026-03-31

- Excluded theme CSS from Breeze minification (`breeze_exclude_css` filter)
- Breeze was stripping the `onload` handler from deferred custom.css, leaving styles on `media=print`
- Removed Cloudflare "Cache Everything" page rules (were caching admin pages, API responses, theme/plugin thumbnails)
- Cloudflare default behavior (cache static assets) + Varnish handles frontend performance

## [3.9.2] — 2026-03-31

- Fixed hero layout: removed `justifyContent: left` which pushed container to page edge
- Reverted to original `constrained` with `contentSize: 1100px`
- Text left-aligns naturally within centered container via CSS `.sn-hero` flex align-items

## [3.9.1] — 2026-03-31

- Fixed hero layout: reverted from `default` to `constrained` with `justifyContent: left`
- Content stays within 800px container, left-aligned, matching Contact page pattern

## [3.9.0] — 2026-03-31

- Work With Me consulting page: tabbed 30/60-minute session booking via Cal.com
- Tab switching JS with theme-matched styling (Bebas Neue tabs, red active indicator)
- `hideEventTypeDetails` on Cal.com embeds (page provides its own description/price)
- Registered `page-work-with-me` template in theme.json

## [3.8.5] — 2026-03-31

- Auto-flush theme cache on deploy: detects version mismatch on first admin page load after CI/CD deploy
- Clears theme transients, object cache, and WP theme cache automatically
- Zero cost on subsequent loads; only fires when the deployed version changes

## [3.8.4] — 2026-03-31

- Left-aligned hero section: changed layout from constrained (centered) to default (flow)
- Added max-width on subtitle paragraph
- GitHub Actions CI/CD: push to `main` auto-deploys to Cloudways via rsync + SSH
- Deploy pipeline flushes WP object cache, transients, and Breeze minification cache
- Cloudflare edge caching enabled (31-day TTL, TTFB ~100ms cached)
- Homepage cache warmup after deploy
- Updated README and readme.txt documentation

## [3.8.3] — 2026-03-31

- Dequeued Contact Form 7 JS on non-contact pages
- Removed WP Statistics frontend CSS and tracker JS
- Deferred TranslatePress language switcher CSS via print/onload pattern
- Output buffer stripping for Breeze-bundled WP Statistics stylesheets

## [3.8.2] — 2026-03-31

- Added `composer.json` + `composer.lock` for Aikido supply chain scanning

## [3.8.1]

- Larger logo across all breakpoints: desktop 56→64px (scrolled 38→44px), tablet 44→52px, mobile 38→44px
- Body padding and hero calc adjusted to match

## [3.8.0]

- Bumped film grain opacity from 0.025 to 0.035 for more tactile texture
- Frosted glass header via `backdrop-filter: blur(12px)` with semi-transparent white background (72% default, 85% on scroll)
- Pill-shaped buttons site-wide (`border-radius: 999px`)

## [3.7.0]

- Removed Quoter from WordPress theme; moved to standalone Nginx-served app with basic auth
- Removed page-quoter template, quoter.js, auth gate, and jsPDF enqueue from functions.php

## [3.6.1]

- Quoter fix: robust script loading for block themes
- Added `get_page_template_slug` fallback in `wp_enqueue_scripts`
- Rebuilt quoter.js with DOM-ready fallback, createElement for deliverable rows, error handling for jsPDF

## [3.6.0]

- Private Quoter tool: admin-only page template for generating branded project quotes
- Hybrid pricing model (session days + deliverables) with live calculation
- Configurable revision policy, payment terms, and one-click PDF export via jsPDF

## [3.5.1]

- Removed book/chapter/verse references from Scripture quotes across six templates

## [3.5.0]

- Services page overhaul: consolidated Business & Strategy from 4 text blocks to 2 image cards
- Added "How It Works" process strip (Scope Call → Custom Quote → Production) on smoke background
- Updated credibility strip: swapped "Full Sail Valedictorian" for "GRAMMY Voting Member"
- Process strip CSS with vertical dividers desktop, horizontal dividers tablet

## [3.4.8]

- Fixed Services page images: updated upload paths from 2023/10 to 2026/02

## [3.4.7]

- Rewrote Resume page intro with site-native voice

## [3.4.6]

- Updated Resume template summary to match new resume content and positioning

## [3.4.5]

- Removed CF7 block wrapper border on contact page

## [3.4.4]

- Root cause fix: removed `justifyContent:right` and overlay colors from nav block; desktop right-align via CSS
- Removed header/footer separator lines for cleaner layout

## [3.4.3]

- Fixed mobile nav: inlined overlay styles at priority 99 to beat WP core CSS
- Moved nav overlay styles into inlined critical CSS to bypass cache

## [3.4.2]

- Mobile nav overlay: centered links, removed right-align override

## [3.4.1]

- Theme-level favicon fallback (32px + 180px apple-touch-icon)

## [3.4.0]

- Split critical/deferred CSS (8 KB inline vs 20 KB deferred)
- Delayed gtag.js until first user interaction (eliminates 147 KiB from initial load)

## [3.3.4]

- Preloaded DM Mono 300 font (breaks 434ms network chain)
- Properly sized logo (56px + 112px retina, saves 2.5 KiB)

## [3.3.3]

- Stripped Cloudflare Turnstile script on non-contact pages (17 KiB render-blocker)

## [3.3.2]

- Deferred wp-block-library CSS
- Fast hero animations on mobile
- `fetchpriority=low` on Interactivity API script modules

## [3.3.1]

- Fixed NO_LCP: changed animation keyframes from `opacity: 0` to `0.01` (Chromium bug)

## [3.3.0]

- Inlined custom.css to eliminate last render-blocking external CSS request
- Zero external render-blocking resources
- Dequeued CF7 CSS on non-contact pages; deferred on contact page

## [3.2.2]

- Inlined critical Bebas Neue `@font-face` in head to fix NO_LCP

## [3.2.1]

- SEO: added meta description tag for front page and singular posts with excerpts

## [3.2.0]

- Self-hosted fonts: Bebas Neue + DM Mono (300/400/500) woff2 files served locally
- Eliminated render-blocking Google Fonts CSS request

## [3.1.2]

- Removed all Breeze lazy-load workarounds; Breeze lazy images disabled at plugin level

## [3.1.1]

- Contact Form 7 styling: inputs, textarea, labels, submit button, focus states, validation, response messages

## [3.1.0]

- Replaced Site Kit with direct gtag.js snippet (GT-NMC3GVL)
- Removed all GSI script workarounds

## [3.0.3]

- Removed Optimization Detective workaround code (plugin deleted)

## [3.0.2]

- Output buffer reverses Breeze lazy-loading on logo img tag
- Restores fetchpriority stripped by Optimization Detective plugin

## [3.0.1]

- Logo switched from CSS background-image to real `<img>` with `loading="eager"` and `fetchpriority="high"`
- Fixes LCP detection (Lighthouse NO_LCP error)

## [3.0.0]

- PageSpeed Insights optimization pass
- Font preloading: preconnect hints + preload for Bebas Neue/DM Mono woff2 files
- Google Sign-In (GSI) script removed: dequeue + output buffer strip (93KB blocker)
- Generator meta tag stripping consolidated into single `template_redirect` ob_start
- Footer social icons: normal size + 44x44px min touch target (WCAG compliance)

## [2.9.3]

- Added Instagram to Contact page social copy

## [2.9.2]

- Fixed Contact page copy: removed claim of being "most active on Spotify"

## [2.9.1]

- Sticky footer: fixed to bottom viewport, compacted (~75px)
- Hero calc and body padding adjusted for both fixed header and footer

## [2.9.0]

- Sticky shrinking header: fixed position, compacts on scroll
- JS uses requestAnimationFrame + passive scroll for performance
- Breeze exclusion added for sticky-header.js

## [2.8.5]

- Fixed hero height: `calc(100vh - header)` so homepage fills exactly one screen
- Uses `100dvh` fallback for mobile address bar handling

## [2.8.4]

- Redesigned Muso.AI credits section: two-column layout with bordered card CTA

## [2.8.3]

- Expanded Spotify embed height: 600px desktop, 500px tablet, 400px mobile

## [2.8.2]

- Changed About page red label from "About" to "Who I Am"

## [2.8.1]

- Swapped About page portrait to B&W studio photo

## [2.8.0]

- Added Panacea studio link on About page

## [2.7.0]

- Audit fixes: service card CSS retargeted to actual HTML structure
- About page updated with business/strategy positioning
- Generator meta tags stripped
- Skip-to-content link added for accessibility

## [2.0.0]

- Full palette inversion to match nin.com
- White backgrounds, black text, red accents
- Inverted film grain and scanline overlays (multiply blend)
- Buttons now black with red hover
- Logo auto-inverts via CSS filter

## [1.5.0]

- NIN aesthetic shift: replaced warm palette with cold/clinical colors
- Removed Ember color, Portfolio CPT, Genre taxonomy, unused gradients

## [1.4.1]

- Fixed `&amp;` encoding in theme name
- Standardized fontFamily on Music page; unified container widths
- Rewrote Contact page with cleaner form layout

## [1.4.0]

- Merged Muso.AI page into Music page as a dedicated section
- Removed standalone Muso.AI template and nav link

## [1.3.0]

- Rewrote all four Services descriptions with personality-driven copy
- Rewrote About page bio with narrative voice

## [1.2.2]

- Completed theme metadata: author name, URIs, expanded description, additional tags

## [1.2.1]

- Fixed footer year rendering; replaced shortcode block with render_block filter

## [1.2.0]

- Dynamic footer year via `[current_year]` shortcode
- Baked full optimized resume content into Resume template with PDF download

## [1.1.1]

- Wired in existing site images from media library
- Added hero, portrait, and service card CSS effects

## [1.1.0]

- Rebuilt all templates to match juanlentino.com layout
- Added Services, Music, Resume, Muso.AI, and Contact page templates

## [1.0.0]

- Initial theme scaffold with QOTSA/NIN aesthetic
- Core templates, Portfolio CPT, Bebas Neue + DM Mono typography
- Film grain/scanline overlays, industrial color palette
