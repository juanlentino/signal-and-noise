# v5.0.0 + v10.0.0 — Execution reconciliation (paired modernization major)

**Status:** APPROVED to execute (brainstorm 2026-06-10). Reconciles the approved [2026-05-27 paired-cycle spec](2026-05-27-v5-and-v10-paired-cycle-design.md) §5/§6 to **live `main`** (plugin **v4.14.5**, theme **v9.15.6**) — the codebase moved ~14 plugin / ~9 theme minors past what that spec assumed, so the manifest below supersedes its §5/§6.

## Decisions locked this session

- **No flagship feature.** v5/v10 = *pure modernization*. The breaking changes alone justify the majors. (Rejected: a `/releases` page — product-y for a personal brand site; any token-intensive AI feature — no LLM-at-request-time.)
- **Skip the prep-minor → patch cycle** the parent spec sequenced (v4.6.0 → v4.6.x → …). It already (partly) shipped through the v4.6→v4.14 / v9.6→v9.15 minor stream: the deprecation runway is laid (plugin carries **two** clean `@deprecated` generations + `_deprecated_function` warnings + Ability replacements). Go straight to the paired major.
- **WP 7.0 confirmed live** (juanlentino.com on 7.0, Cloudways ready). The hard-raise is greenlit — it is the primary SemVer driver for *both* majors.
- **Cap policy** (per 2026-05-26 cap-drop): majors warranted by real breaks (WP floor raise + public-route removals), never counter math.

## Plugin v5.0.0 — manifest (verified on `origin/main`)

| Action | Surface |
|---|---|
| **HARD-RAISE** | `Requires at least: 6.4 → 7.0` (manifest header; WP enforces — install on < 7.0 refuses) |
| **REMOVE** — gen-1 `@deprecated since 2.5.0` (already firing `_deprecated_function`, Ability replacements live) | `/ai/generate-excerpt` ([inc/ai-excerpt.php](https://github.com/juanlentino/signal-and-noise-tools/blob/main/inc/ai-excerpt.php)), `/ai/generate-meta-description`, `/ai/generate-og-card-title`, the `/cmd/<action>` route ([inc/desktop-mode-integration.php](https://github.com/juanlentino/signal-and-noise-tools/blob/main/inc/desktop-mode-integration.php)). Delete the route registrations + the deprecated handler functions. |
| **FLIP** JS clients to Ability-only | Any `assets/*.js` call site that still hits a removed route → remove the REST fallback (Ability path validated through the whole v4.6→v4.14 window). |
| **PROMOTE → runtime warnings** — gen-2 `@deprecated since 4.6.0` (removal scheduled **v6.0.0**) | `pattern-adoption-dismiss` / `-scan`, `get-plausible-stats` / `-realtime` / `test-plausible-connection`, `run-cron-event` ([inc/rest-api.php](https://github.com/juanlentino/signal-and-noise-tools/blob/main/inc/rest-api.php), [inc/pattern-adoption-*.php](https://github.com/juanlentino/signal-and-noise-tools/tree/main/inc)). Add `_deprecated_function()` where not already firing. |
| **REMOVE** | orphan option `sn_login_rewrites_flushed` (one-line `delete_option()` in the upgrade path) |
| **DROP** | pre-7.0 compat — audit the ~7 version-gates + `is_callable` AI-client shims (see [[feedback_method_exists_vs_is_callable]]); delete branches only reachable on WP < 7.0 |
| **KEEP** | `admin-legacy-redirect` (`@deprecated`-framed but load-bearing — not a removal) |

## Theme v10.0.0 — manifest

| Action | Surface |
|---|---|
| **HARD-RAISE** | `Requires at least: 6.4 → 7.0` ([style.css](style.css)) — the breaking change that earns the major |
| **DROP** | removable pre-7.0 compat — audit the ~9 `version_compare`/`$wp_version` gates (likely minimal; theme is already WP-7.0-shaped) |
| **HOLD** | `theme.json` stays **v3** — WP has not shipped the v4 schema, so T1 defers to v11.0.0 |
| **OPTIONAL** | a light craft-consolidation pass (NOT a redesign) — kept minimal; drop if it adds risk |

## Sequencing

- **Plugin v5.0.0 first** (the substantive side), then **theme v10.0.0**; **paired ship** so the WP 7.0 floor lands on both in lockstep.
- Each side: **TDD** (removed route → 404 assertion; `_deprecated_function` fires for the gen-2 surface; compat-drop regression) → full suite + PHPCS falsification → **post-ship audit** (the project's audit-then-UAT discipline) → tag.
- **v6.0.0** later inherits the gen-2 removals (now on runtime warnings).

## Gates & risks

- **WP 7.0 readiness:** CONFIRMED (this session).
- **JS-client safety:** before deleting any gen-1 route, confirm its Ability path is exercised by a test and no live client still depends on the REST fallback (the gen-1 routes already carry `_deprecated_function` + replacements; verify call sites).
- **Cross-package contract:** locked by `tests/cross-package-listeners.php` since v9.5.0 — run it post-ship on both sides.

## Anti-scope

No flagship · no `/releases` page · no AI/token-runtime feature · theme "craft tightening" stays minimal (no redesign) · gen-2 deprecated surface is *promoted, not removed* (v6.0.0) · cosmetic renames (parent spec §8: P3/P4/P5, T2–T4) stay deferred.

## Next

1. **User reviews this spec.**
2. `superpowers:writing-plans` → **plugin v5.0.0** implementation plan first (per sequencing).
