# Changelog

All notable changes to Signal & Noise are documented here.

## [Unreleased] — Content addition (2026-05-07)

Ship the second long-form companion essay at `/provenance/as-substrate/` — the web-adapted, jargon-free version of SSRN paper 2 ("Provenance as Substrate: A Cryptographic Identifier Framework for Music Rights and Royalty Infrastructure", Abstract 6730343). Mirrors `/provenance/over-detection/` block-for-block: same hero/eyebrow/TOC/section/byline structure, same diagram block treatment, same footer CTA pair, same dynamic byline + reading-time block. Six anchored sections (`#setup`, `#analogy`, `#what-it-is`, `#why-it-matters`, `#the-shift`, `#economics`) match the first long-form's pattern.

### Added
- **New seed file** at [inc/seed-content/as-substrate-body.html](inc/seed-content/as-substrate-body.html). Hero with `[sn_reading_time]` shortcode in the eyebrow (single source of truth — no manual minute counts), six properly-wrapped `<section class="sn-provenance-section">` groups, paired SVG diagrams in Section 2 (administrative-codes envelopes-with-drifting-tags ↔ cryptographic-identifiers file-with-fingerprint, both 240×180 viewBox, line-art aesthetic, `sn-provenance-svg-accent` for blood-color fills on circles/lines per the existing CSS contract), a single-panel cost-scaling SVG in Section 6 (two-axis line chart: administrative cost rising linearly versus cryptographic cost staying flat — flat line uses the accent class for blood-color emphasis on the punchline; grid is inline-overridden to `1fr` with `max-width:340px;margin:0 auto` so the existing paired-grid CSS still drives layout for both diagrams), footer CTA row, byline with `displayType:"modified"` post-date + `[sn_reading_time]`.
- **`sn_ensure_as_substrate_page()`** in [inc/notes-and-provenance.php](inc/notes-and-provenance.php). Parallel to `sn_ensure_over_detection_page()` — creates the new child page under `/provenance` with `post_parent` set, `page_template` = `page-provenance`, post excerpt populated for the meta description, idempotent on re-run.
- **`sn_load_as_substrate_body()`** loader for the new seed file. Same fallback semantics as the existing `sn_load_over_detection_body()` — empty string if the file is missing, so the template renders an empty post-content area instead of fatalling.
- **`sn_migrate_as_substrate_seed()`** + `SN_AS_SUBSTRATE_SEED_OPT` flag. One-time migration on `admin_init` for installs whose `SN_SEED_FLAG_OPTION` was already set before this page existed (i.e. every production site since v6.0). The main `sn_seed_content_surfaces()` flow short-circuits on those installs, so the new ensure-call needs its own gate. Idempotent: bails if the dedicated flag is set; bails (without flagging) if the parent page doesn't yet exist so the next admin_init can complete it after the parent lands.
- **`SN_AS_SUBSTRATE_SLUG` constant** alongside the existing slug constants for consistency with the established naming convention.

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
