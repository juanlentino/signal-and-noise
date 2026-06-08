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

## Next: Batch B (the agreed C→B sequence — but C is PHANTOM, see below)

### ⚠️ Batch C ("cheap wins") was investigated 2026-06-08 and is ALREADY DONE — do NOT re-chase it
All three items in the prior handoff's Batch C were false-premise drift in the *research*, not in the code/docs. Verified against ground truth:
- **(1) "Build Speculation Rules — zero impl (drift)" → FALSE.** Fully shipped **plugin v4.10.0**: `inc/speculation-rules.php` hooks both WP 7.0 core filters (`wp_speculation_rules_configuration` → null/off else prerender+moderate; `wp_speculation_rules_href_exclude_paths` (10,2) → appends the login slug + `/contact/*`), `sn_login_get_slug()` exists (`inc/login-hide.php:108` — no latent fatal), required at bootstrap (`signal-and-noise-tools.php:204`), dedicated `tests/speculation-rules.php`. Performance sub-tab toggle + `sn_handle_perf_save` + `perf.speculative_loading` default. Nothing to build.
- **(2) "Copy Plausible seed doc to plugin main" → FALSE.** Already tracked on plugin `origin/main` since `6b7d60e` (v4.11.0), present at v4.12.0.
- **(3) "Fix the drift status line in the roadmap" → FALSE.** The roadmap (`2026-06-06-upgrade-opportunities-roadmap.md:15,47`) AND the master sequence (`2026-06-05-master-execution-sequence.md:78`) BOTH already correctly record Speculation Rules as ✅ SHIPPED v4.10.0.
- **Root cause of the bad research:** it almost certainly grepped the **stale non-worktree plugin checkout** (`/Users/juanlentino/Projects/signal-and-noise-tools`, pinned at v4.7.0 — predates v4.10.0's `speculation-rules.php`), so "grep shows zero impl" was an artifact of the wrong tree. **Lesson:** for "does feature X exist" greps, ALWAYS check `origin/main` or a CURRENT worktree, never the stale v4.7.0 checkout. → see [[reference_stale_plugin_checkout_grep_trap]].

### **B (flagship specs — the real next work):** brainstorm + spec the two majors
- **theme v10.0.0 = Music identity** (`MusicGroup`/`MusicRecording` JSON-LD + discography model + release timeline).
- **plugin v5.0.0 = Plausible content-intelligence** (v1→v2 API refactor + "Read next/Popular" front-end surface + traffic-grounded stale-post triage). Seed: `docs/superpowers/specs/2026-06-06-plausible-content-intelligence-seed.md` (already on plugin main).
- Both majors are otherwise scoped cleanup-only (WP 7.0 floor-raise + REST removals). NOT cheap — each needs a brainstorm before spec/build.

## Workflow reliability lessons (reconfirmed this session)
The 4-lens verify ran clean with: text-mode (no schema), `agentType:'general-purpose'`, `parallel()` barrier (4 lenses, low concurrency), `.catch()` that flags crashed verifiers as UNVERIFIED (not as a pass — per `feedback_workflow_verify_crash_not_refute`). Script auto-persisted under the session dir; inline `script` worked fine.
