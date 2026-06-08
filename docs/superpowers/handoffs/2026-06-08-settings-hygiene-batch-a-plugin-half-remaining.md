# Handoff — Settings-Hygiene Batch A (plugin half remaining)

**Date:** 2026-06-08
**Context for resume:** Parked at 74% context. Theme half SHIPPED; plugin half is partway (P1 of 6 done). This doc is self-contained — read it + the plan to resume.

## Canonical docs
- **Spec:** `docs/superpowers/specs/2026-06-08-settings-hygiene-batch-a-design.md`
- **Plan (task-by-task, exact code):** `docs/superpowers/plans/2026-06-08-settings-hygiene-batch-a.md`
- **v5/v10 research output** that spawned all this is summarized in the chat that produced these docs (music-identity flagship for v10, Plausible content-intelligence for v5, settings hygiene = this batch).

## What shipped this session (THEME — all on `main` + tagged)
- **v9.11.2** — the real single-notes fatal fix: `get_the_queried_object_id()` → `get_queried_object_id()` at `inc/related-notes.php:136` + `inc/post-share.php:45` (v9.11.1 had misdiagnosed it as Block Bindings). Deployed + verified live.
- **v9.11.3** — moved the command-palette `SEARCH ⌘K` trigger from a `position:fixed` overlay into the footer utility bar (`parts/footer.html` `wp:html` block); it was colliding with the colophon. Deployed + verified.
- **v9.12.0** — **batch A theme half** (THIS work): 4 new front-end filters + a related-notes configurability change + the palette kill-switch. Commit `f670873`, tag `v9.12.0`, pushed. **Adversarially verified (2 agents, both "ship"); no false-greens.** Suite 26/697/0, phpcs clean. NOT yet deployed to production (install via wp-admin Updates when ready).

### Theme filters added (v9.12.0) — the plugin must drive these
| Filter | Default | Theme call site |
|---|---|---|
| `sn_related_count` | 3 | `inc/related-notes.php` shortcode (was hardcoded literal 3 — NOT a bug, just non-configurable; reworded after review) |
| `sn_palette_recent_count` | 8 | `inc/command-palette.php` recent WP_Query |
| `sn_palette_enabled` | true | `sn_cmdk_enqueue()` early-return + `body_class` `sn-cmdk-off` + `critical.css` hide rule |
| `sn_json_feed_items` | 20 | `inc/feed-json.php` `sn_feed_json_query_args()` (pure helper extracted) |
| `sn_updated_date_threshold_days` | 14 | EXISTING (theme `post-updated-date.php`) — plugin just supplies value |
| `sn_reading_time_wpm` | 225 | EXISTING (plugin `reading-time.php`, const `SN_READING_TIME_DEFAULT_WPM`) |

## PLUGIN half — STATE + how to resume

**Worktree:** `/Users/juanlentino/Projects/signal-and-noise-tools/.claude/worktrees/settings-batch-a`
**Branch:** `claude/settings-batch-a` (off `origin/main` = **v4.11.0**, `3543a45`). 1 commit ahead, **unpushed**.
**ALL plugin edits happen in this worktree.** (The non-worktree checkout `/Users/juanlentino/Projects/signal-and-noise-tools` is stale at v4.7.0 — ignore it.)

- ✅ **P1 DONE** (commit `63d78aa`): added `theme` subtree to `sn_settings_defaults()` in `inc/settings.php` (after the `og` block) + created `tests/settings-theme.php` (P1 default assertions only, 8/0 green). Harness = in-memory `$GLOBALS['__options']` + `get_option`/`update_option` stubs + `require inc/settings.php`; defines `SN_THEME_FILTERS_TEST` + `sanitize_text_field`/`wp_unslash` stubs already.

### Remaining: P2 → P6 (TDD, append to `tests/settings-theme.php`)

**P2 — AI allowlist + save handler** (in `inc/admin-post-actions.php`):
- `sn_theme_ai_models()` returns the allowlist (single source). **VERIFIED model ids (alias form, NO date suffix, per claude-api skill):**
  ```php
  'claude-sonnet-4-6' => 'Claude Sonnet 4.6 (balanced — default)',
  'claude-opus-4-8'   => 'Claude Opus 4.8 (most capable)',
  'claude-haiku-4-5'  => 'Claude Haiku 4.5 (fastest, cheapest)',
  ```
- `sn_handle_save_theme($post)` — sparse writes via `sn_setting_update()` (NOT the whole-replace `sn_settings_save()`). Clamps: related `max(1,min(12,…))`, palette_recent `max(0,min(20,…))`, json_feed `max(1,min(50,…))`, updated_threshold `max(1,min(90,…))`, reading_wpm `max(100,min(400,…))`; palette_enabled `! empty()`; ai_model `in_array($v, array_keys(sn_theme_ai_models()), true) ? $v : (string) sn_setting('theme.ai_model', $allowed[0])`. Returns flash code `theme_saved` / `theme_unchanged`. **Mirror the audit-retention handler** (`max(7,min(365,…))` pattern) for style. Full code in the plan §P2.
- Test: requires `inc/admin-post-actions.php`; assert clamps (0→min, 99→max), off-list model → keeps default, palette_enabled present→true/absent→false.

**P3 — register + flash** (no test, mechanical):
- `inc/admin-post-handler.php` `sn_admin_post_handlers()` (function at `:29`): add `'save_theme' => 'sn_handle_save_theme',`.
- `inc/admin-flash-messages.php` `sn_admin_flash_messages()` (function at `:29`): **flash shape is POSITIONAL `array( 'severity', 'message' )`** (NOT `['type'=>,'text'=>]`). Add:
  ```php
  'theme_saved'     => array( 'success', 'Front-end settings saved.' ),
  'theme_unchanged' => array( 'info', 'No front-end settings changed.' ),
  ```

**P4 — form + sub-tab + dispatch** (manual smoke, no unit test):
- Create `inc/admin-forms/front-end.php` → `sn_admin_render_front_end_form()`. Mirror `inc/admin-forms/identity-and-seo.php`: `<form method="post">` + `wp_nonce_field('sn_theme_options_nonce')` + `<input type="hidden" name="sn_action" value="save_theme">` + `.sn-field`/`.sn-field-label`/`.sn-field-w-xs|md` fields + **savebar = `<div class="sn-savebar"><p class="sn-savebar-hint">…</p><button type="submit" class="button button-primary">Save front-end settings</button></div>` then `</form>`** (NOT `submit_button()`). Fields: 4 number inputs (related/palette/json/updated/reading — actually 5 numbers), 1 checkbox (palette_enabled), 1 select (ai_model via `sn_theme_ai_models()` + `selected()`). Escape: `esc_attr()` on values, `checked()`, `selected()`. Full markup in plan §P4 / spec.
- `inc/admin-tabs-data.php` Tools `sub_tabs` (cluster at `:105-107`: reading-time/links/block-migrations): add `'front-end' => array( 'label' => 'Front-End' ),`.
- `inc/admin-page.php` tools dispatch (`'tools' === $active_tab` at `:228`; default reading-time at `:244-245`): add `elseif ( 'front-end' === $active_sub ) { sn_admin_render_section( 'front-end', 'sn_admin_render_front_end_form' ); }`.
- Bootstrap `signal-and-noise-tools.php`: add `require_once SNT_PATH . 'inc/admin-forms/front-end.php';` near `:85-89` (other admin-forms).

**P5 — filter callbacks** (`inc/theme-filters.php`, NEW):
- 7 named functions `sn_tf_*($d)` reading `sn_setting('theme.x', $d)` clamped, + guarded registration block (`if ( ! defined('SN_THEME_FILTERS_TEST') || ! SN_THEME_FILTERS_TEST )` → `add_filter`). Map: `sn_related_count`→sn_tf_related_count, `sn_palette_recent_count`, `sn_palette_enabled`, `sn_json_feed_items`, `sn_updated_date_threshold_days`→sn_tf_updated_threshold, `sn_reading_time_wpm`→sn_tf_reading_wpm, `snt_ai_model_preference`→sn_tf_ai_model. **`sn_theme_ai_models()` is front-end available** (admin-post-actions.php is required unconditionally in bootstrap `:84`), so `sn_tf_ai_model` can call it. Full code in plan §P5.
- Bootstrap: `require_once SNT_PATH . 'inc/theme-filters.php';` with the other `inc/*.php`.
- Test: assert each callback returns configured value (via `sn_setting_update` then read) + falls back to `$d` when unset (reset store + `sn_setting_reset_cache()` between). **Watch the sn_setting per-request static cache** — use `sn_setting_update()` to write (busts cache) or call `sn_setting_reset_cache()` after direct `$GLOBALS['__options']` mutation.

**P6 — release v4.12.0:**
- Full suite (`for f in tests/*.php; do php "$f"; done`, falsify one assertion first), `composer run lint` / phpcs clean.
- Bump `signal-and-noise-tools.php` `Version: 4.11.0 → 4.12.0`; CHANGELOG `### New` (Front-End settings tab + the 7 knobs).
- Commit, `git push origin HEAD:main`, tag `v4.12.0`, push tag. Then `git worktree remove .claude/worktrees/settings-batch-a` from the main plugin checkout.
- **Recommended before tag:** a lean Ultracode adversarial-verify workflow on the plugin diff (mirror the theme one at `/tmp/sn_theme_verify_wf.mjs`) — check clamps/allowlist behavior + that the cross-package defaults match the theme's (3/8/true/20/14/225/sonnet-4-6).

## After batch A: the agreed sequence is **A → C → B**
- **C (cheap wins, next):** (1) **Build Speculation Rules** — research found the roadmap CLAIMS "shipped v4.10.0" but grep shows ZERO impl in either repo (drift, like v9.11.1). ~one filter, prerender `/notes`→note. (2) **Copy the Plausible content-intelligence seed doc to plugin main** — it exists only in plugin worktrees (`.claude/worktrees/v4.*/docs/superpowers/specs/2026-06-06-plausible-content-intelligence-seed.md`), dangling cross-ref from the master sequence. (3) Correct the Speculation-Rules drift status line in `docs/superpowers/specs/2026-06-06-upgrade-opportunities-roadmap.md`.
- **B (flagship specs, after C):** brainstorm + spec the two majors — **theme v10.0.0 flagship = Music identity** (`MusicGroup`/`MusicRecording` JSON-LD + discography model + release timeline; the site is a music producer's but models zero music — biggest blind spot, in NO parked doc); **plugin v5.0.0 flagship = Plausible content-intelligence** (v1→v2 API refactor + "Read next/Popular" front-end surface + traffic-grounded stale-post triage). Both majors are otherwise scoped cleanup-only (WP 7.0 floor-raise + REST removals).

## Key decisions / gotchas captured this session (memory-worthy)
- The hand-rolled settings pattern is HANDBOOK-VALIDATED — do NOT migrate to `register_setting()`/Settings API (the dispatcher already does nonce+cap+sanitize; API value is REST-only, deliberately not exposed).
- New settings live in the single `sn_settings` `theme` subtree (Options-API handbook blesses one-array-many-options). Write via sparse `sn_setting_update()`, never the whole-replace `sn_settings_save()` (which would need another subtree-preserve hack).
- Standalone-safety contract: theme `apply_filters('sn_x', <default>)` ← plugin `add_filter` returns clamped `sn_setting()`; theme default MUST equal plugin default; theme works with plugin absent.
- Model ids: alias form only (`claude-opus-4-8`, NOT date-suffixed). Current pin in `ai-bootstrap.php:208` is `claude-sonnet-4-6` via `snt_ai_model_preference` filter.
- Workflow reliability lessons: text-mode (no schema) + `agentType:'general-purpose'` + sequential tracks / low concurrency + throw-on-ratelimit. Write script to `/tmp/*.mjs`, `node --check` (ignore the expected top-level-`return` notice), launch via `scriptPath`.
