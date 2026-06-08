# Handoff — Settings-Hygiene Batch A COMPLETE (both halves shipped)

**Date:** 2026-06-08
**Status:** Batch A fully shipped. Theme **v9.12.0** (prior session) + plugin **v4.12.0** (this session) both on `main` + tagged + pushed. **Not yet installed to production** — see UAT below.

## What shipped this session (PLUGIN — v4.12.0, on `main` + tag `v4.12.0`)

The plugin half of the settings-hygiene batch: 7 front-end "render knobs" the theme previously hardcoded are now plugin-owned, edited on a new **Tools → Front-End** sub-tab, and supplied to the theme through a standalone-safe `add_filter` contract.

Commits (off `origin/main` = v4.11.0 `3543a45`, now `142cbe9`):
- P1 `63d78aa` (prior session) — `theme` subtree defaults in `sn_settings_defaults()`.
- P2 `7f773b8` — `sn_handle_save_theme()` + `sn_theme_ai_models()` allowlist (clamped ints + model validation).
- P3 — dispatch-map entry + **positional** flash messages (`array('severity','message')`).
- P4 — `inc/admin-forms/front-end.php` form + Tools→Front-End sub-tab + page dispatch + bootstrap require (+ render-smoke test).
- P5 — `inc/theme-filters.php` — 7 `sn_tf_*` callbacks → `sn_related_count` / `sn_palette_recent_count` / `sn_palette_enabled` / `sn_json_feed_items` / `sn_updated_date_threshold_days` / `sn_reading_time_wpm` / `snt_ai_model_preference`.
- **fix** — `sn_settings_save()` now preserves the `theme` subtree (data-loss bug, see below).
- test — dispatch-map count 26→27.
- release `142cbe9` — Version bump + CHANGELOG.

### Verification (all green)
- `tests/settings-theme.php` **33/0** (defaults, clamps, allowlist, render-smoke, filter callbacks) — falsification-verified.
- `tests/settings-save-preserves-subtrees.php` **12/0** (now covers the theme subtree).
- Full plugin suite: **0 FAIL/fatal** across 55 files.
- phpcs **0 errors** — falsification-verified (injected `$_GET` echo → 4 errors reported in a new file → reverted clean; proves new files are in scope, not a `.claude/`/batch false-green).
- **4-lens Ultracode adversarial-verify workflow on the full diff → 0 real issues across all 4 lenses** (cross-package defaults, clamp/allowlist, wiring/standalone, data-integrity/security). No verifier crashed. All 7 cross-package defaults reconciled against real source (incl. `$threshold_days=14` param default and the plugin-owned `SN_READING_TIME_DEFAULT_WPM=225` / `ai-bootstrap.php:208` `claude-sonnet-4-6`).

### The data-loss bug the pre-tag verify caught (memory-worthy)
`sn_settings_save()` (Identity-tab handler) does a **whole-option replace** of `sn_settings`. It re-includes the `audit`/`monitoring`/`perf` subtrees but the plan MISSED adding the new `theme` subtree — so saving the Identity tab after configuring any Front-End knob silently reverted them all to defaults (confirmed repro: `related_count 9 → 3`). Fixed (`inc/settings.php` + regression test). **This is the 4th time this exact trap has bitten** (audit v4.5.2, monitoring v4.9.0, perf v4.10.0, theme v4.12.0). Rule: **every new `sn_settings` subtree written via `sn_setting_update()` outside the Identity form MUST be added to `sn_settings_save()`'s preservation list.**

## Theme defaults (already shipped v9.12.0) the plugin matches — DO NOT drift
related 3 · palette_recent 8 · palette_enabled true · json_feed 20 · updated_threshold 14 · reading_wpm 225 · ai_model `claude-sonnet-4-6`. Theme call sites: `related-notes.php:146`, `command-palette.php:30/66`, `feed-json.php:33`, `post-updated-date.php:38/50` (`$threshold_days=14` param). `reading_wpm` + `ai_model` are plugin-owned (`reading-time.php` const, `ai-bootstrap.php:208`).

## UAT (user action — neither repo auto-deploys on tag push)
1. wp-admin → Dashboard → Updates → update **Signal & Noise Tools** to v4.12.0 (and the theme to v9.12.0 if not already).
2. Smoke: Tools → Front-End renders 7 fields + saves (success flash); change related-count → front-end reflects it; toggle palette off → ⌘K trigger disappears + no palette JS loads; **save the Identity (Site) tab and confirm Front-End values persist** (the data-loss regression).
   - Note: `feedback_desktop_mode_blocks_browser_automation` — UI smoke is manual.

## Next: the agreed sequence is **C → B** (Batch A done)
- **C (cheap wins, next):** (1) **Build Speculation Rules** — roadmap CLAIMS "shipped v4.10.0" but grep shows ZERO impl in either repo (drift). ~one filter, prerender `/notes`→note. (2) **Copy the Plausible content-intelligence seed doc to plugin main** — exists only in plugin worktrees (`.claude/worktrees/v4.*/docs/superpowers/specs/2026-06-06-plausible-content-intelligence-seed.md`). (3) Correct the Speculation-Rules drift status line in `docs/superpowers/specs/2026-06-06-upgrade-opportunities-roadmap.md`.
- **B (flagship specs, after C):** brainstorm + spec the two majors — **theme v10.0.0 = Music identity** (`MusicGroup`/`MusicRecording` JSON-LD + discography); **plugin v5.0.0 = Plausible content-intelligence** (v1→v2 API refactor + "Read next/Popular" surface + traffic-grounded stale-post triage).

## Workflow reliability lessons (reconfirmed this session)
The 4-lens verify ran clean with: text-mode (no schema), `agentType:'general-purpose'`, `parallel()` barrier (4 lenses, low concurrency), `.catch()` that flags crashed verifiers as UNVERIFIED (not as a pass — per `feedback_workflow_verify_crash_not_refute`). Script auto-persisted under the session dir; inline `script` worked fine.
