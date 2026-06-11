# Handoff — paired modernization major SHIPPED (plugin v5.0.0 + theme v10.0.0)

**Date:** 2026-06-10
**Status:** The long-parked v5.0.0/v10.0.0 paired major is **DONE** — both merged + tagged, WP 7.0 floor landed in lockstep. Driven by real SemVer breaks (WP-floor raise + public REST-route removal), no flagship feature (deliberate — see below). **0 open PRs** in either repo. One scoped follow-up was deferred on purpose: the `/cmd` route removal.

## What shipped

### Decision (brainstorm 2026-06-10)
The user observed "we did a lot of work to go v5/v10 and never bumped." Reframed honestly: v5/v10 was fully *designed* (the approved [2026-05-27 paired-cycle spec](../specs/2026-05-27-v5-and-v10-paired-cycle-design.md)) but the major was *deferred ~9 times* in favor of minors — the breaking changes sat teed-up on `main`. Chosen direction: **pure modernization, no flagship** (user rejected a `/releases` page as too product-y for a personal site, and anything token-intensive). Spec: [2026-06-10-v5-v10-execution-reconciliation.md](../specs/2026-06-10-v5-v10-execution-reconciliation.md). Plan: [signal-and-noise-tools …/plans/2026-06-10-plugin-v5.0.0.md](https://github.com/juanlentino/signal-and-noise-tools/blob/main/docs/superpowers/plans/2026-06-10-plugin-v5.0.0.md).

### Plugin v5.0.0 ([#8](https://github.com/juanlentino/signal-and-noise-tools/pull/8), merged + tagged)
- **WP floor → 7.0** (header + self-updater mirror in `wp-update-integration.php`).
- **Removed 3 gen-1 `@deprecated since 2.5.0` REST routes**: `/ai/generate-excerpt`, `/ai/generate-meta-description`, `/ai/generate-og-card-title` (+ permission callbacks + handlers + dead `restPath` localizes). Live Abilities are the replacement; the in-product JS already calls them. **Shared `*_impl()` functions + the publish-time prepopulate engine preserved.**
- **Promoted 6 gen-2 `@deprecated since 4.6.0` routes to runtime `_deprecated_function()`** (plausible ×3, run-cron, pattern-adoption ×2) — **removal targets v6.0.0**.
- **Removed** orphan option `sn_login_rewrites_flushed` (sentinel-gated `admin_init` migration) + the WP<7.0 admin-notice.
- TDD throughout; new guards `manifest-floor`/`routes-removed`/`gen2-runtime-warnings`/`orphan-option-migration`; **67 suites green**; PHPCS falsification-verified.

### Theme v10.0.0 ([#8](https://github.com/juanlentino/signal-and-noise/pull/8), merged + tagged)
- **WP floor → 7.0** (style.css; first theme major since v9.0.0 — the floor raise IS the break).
- **Removed** the WP<7.0 admin-notice; **simplified** the `get-system-status` WP-version read (`wp_get_wp_version()` always present on 7.0).
- `theme.json` stays **v3** (WP hasn't shipped v4 → defers to v11.0.0). 30 suites green; PHPCS falsification-verified.

## ⏭️ Deferred (the one scoped follow-up) — `/cmd` route removal
The 4th gen-1 route (`/cmd/<action>` in `desktop-mode-integration.php`) was **NOT removed.** Clean removal requires migrating **4 desktop-mode widget JS clients** (`desktop-mode.js`, `desktop-mode-widget{,-actions,-rss}.js`) from `/cmd/*` to the Abilities run surface — only `command-palette.js` is migrated — and there's an output-shape divergence. That UI **can't be auto-verified** (desktop-mode blocks browser automation per memory), so flipping it blind inside a major was the wrong risk. It stays `@deprecated` + fires `_deprecated_function()`. **Next:** a focused, *live-verifiable* cycle — copy the `command-palette.js` Ability pattern to the 4 widgets, verify in desktop-mode with the user, then remove the route + `snt_desktop_cmd_handler` (keep the `snt_cmd_impl_*` functions). Plan §C has the details.

## v6.0.0 queue (already teed up)
The 6 gen-2 routes now fire runtime warnings with removal target **v6.0.0**. When v6.0.0 convenes: remove them (their Abilities are live) + the `/cmd` route (if its widget migration shipped by then).

## CI note
`claude-review.yml` `--max-turns` bumped **15 → 30 in BOTH repos** (committed direct-to-main). The reviewer hit `error_max_turns` (zero findings = tooling crash, not a finding) on large diffs; 30 lets it complete. Byte-match-on-main requirement honored both times.

## PENDING / next session
1. **Owner installs:** plugin **v5.0.0** + theme **v10.0.0** via wp-admin → Updates. ⚠️ Both now **require WP 7.0** — confirmed live before shipping (juanlentino.com on 7.0). Tag pushes don't auto-deploy.
2. **`/cmd` widget-migration cycle** (above) — the one real follow-up.
3. **v6.0.0** (eventually) — drop the 6 gen-2 routes (+ `/cmd` if migrated).
4. Worktrees clean (`/tmp/snt-v5`, `/tmp/snt-main` removed); both repos 0 open PRs at tagged HEADs.
