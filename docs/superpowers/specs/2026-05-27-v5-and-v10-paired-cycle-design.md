# Paired-cycle design — v4.6.0 + v9.6.0 → v5.0.0 + v10.0.0

**Status:** APPROVED via brainstorm 2026-05-27. Spec locked; ready for implementation planning.

**Scope:** This spec covers FOUR shipping windows across both repos:
- Plugin **v4.6.0** prep-minor + **v4.6.x** patch cycle (optional **v4.7.0+** if additive work emerges)
- Theme **v9.6.0** prep-minor + **v9.6.x** patch cycle (optional **v9.7.0+** if additive work emerges)
- Plugin **v5.0.0** paired-major
- Theme **v10.0.0** paired-major

**Approach:** B (prep minor → patch cycle → paired major). Selected over Approach A (big-bang) after socratic comparison because B applies the audit-then-patch discipline at the major-cycle level — additive work ships as a low-risk minor first, breaking work ships as the major against a known-stable base.

**Cap policy:** caps remain dropped (per 2026-05-26 cap-drop). v5.0.0 / v10.0.0 are warranted ONLY because of the real SemVer drivers identified below — not because a counter rolled over.

---

## §0. Cross-references

**Audit inputs that fed this brainstorm:**
- Plugin scope audit: [`signal-and-noise-tools/docs/superpowers/specs/2026-05-26-v5.0.0-scope.md`](https://github.com/juanlentino/signal-and-noise-tools/blob/main/docs/superpowers/specs/2026-05-26-v5.0.0-scope.md) — 1 REMOVE (orphan option), 0 RENAMEs, 267 KEEP. No standalone driver for v5.0.0.
- Theme scope audit: [`2026-05-27-v10.0.0-scope.md`](2026-05-27-v10.0.0-scope.md) — 79 KEEP, 0 RENAME, 0 REMOVE, 1 conditional SCHEMA-CHANGE (theme.json v3→v4, blocked on WP). No standalone driver for v10.0.0.
- Prior roadmap: [`2026-05-26-roadmap-to-v5-and-v10-design.md`](2026-05-26-roadmap-to-v5-and-v10-design.md) — defines the paired-major structure this spec slots into.

**Memory inputs:**
- `project_parallel_major_brainstorm.md` — directive that v5.0.0 + v10.0.0 must be brainstormed as coordinated work.
- `feedback_plugin_absorption_strategic_direction.md` — AI-native direction, Abilities API formalization candidate.
- `project_mimestream_style_release_notes.md` — release-notes presentation layer (deferred from this cycle).
- `feedback_audit_before_uat_when_critical.md` — discipline validated by v4.5.0 → v4.5.1 cycle; extended to major-cycle level here.
- `feedback_versioning_patch_cap.md` — cap-drop rationale.

**Most recent handoff:** [`2026-05-27-paired-major-cycle-complete.md`](../handoffs/2026-05-27-paired-major-cycle-complete.md).

---

## §1. Posture + sequencing

**Goal:** ship v5.0.0 + v10.0.0 as a coordinated paired-major event driven by real SemVer breaks:

| Driver | What breaks | Affects |
|---|---|---|
| **X6** — WP 7.0 baseline raise | `Requires at least: 7.0` HARD-raise; pre-7.0 compat code deleted | Both |
| **P6 + X4** — Abilities API formalization | Non-Ability surface marked `_deprecated_function()`; eventual removal in v6/v11 | Both |
| **X1 + P2** — JS-client → Ability flip | 4 `@deprecated since 2.5.0` REST routes REMOVED; clients flipped to Ability-only | Plugin |
| **P1** — Orphan option cleanup | `sn_login_rewrites_flushed` deleted from options table | Plugin |

**Approach B sequencing diagram:**

```
Plugin: v4.5.1 ─► v4.6.0 ─► v4.6.x ─► (v4.7.0?) ─► v5.0.0 ─► v5.0.x
Theme:  v9.5.0 ─► v9.6.0 ─► v9.6.x ─► (v9.7.0?) ─► v10.0.0 ─► v10.0.x
                   ▲          ▲                      ▲
                   │          │                      │
                   prep       "fixes in the         paired
                   minor      middle" patch         major
                              cycle                 event
```

**Cross-package coordination:**
- Prep minors ship in **sequence** (plugin first, then theme — mirrors v4.5.0 → v9.5.0 pattern).
- Majors ship **paired** (same session if possible) so WP 7.0 baseline lands in lockstep on both sides.
- Each side's BC (Brainstorm-Checkpoint) consumes the OTHER side's actual published state at BC-time.

---

## §2. v4.6.0 prep-minor scope (plugin)

**Character:** additive only — NO breaking changes. Public API expands; nothing is removed or renamed.

**Manifest:** `Tested up to: 7.0` (bump). `Requires at least: 6.7` (UNCHANGED — raised in v5.0.0).

| Work | Detail | LOC est |
|---|---|---|
| **Abilities API formalization (P6)** | Audit all `sn_*` / `snt_*` functions representing meaningful actions; register each via `wp_register_ability( 'signal-and-noise-tools/<slug>', ... )`. Decision rule for "meaningful action" — see §9.4. Targets: admin actions (purge cache, run cron, test plausible, deploy), AI Suggest/Apply actions (alt / excerpt / og / drift / pattern-adoption / block-migrations), ops actions (deploy widget). ~20–30 new abilities. | ~400 |
| **Ability-only paths in `assets/ai-*.js` (X1 prep)** | For each of the 4 `@deprecated since 2.5.0` REST routes (`/ai/generate-meta-description`, `/ai/generate-excerpt`, `/ai/generate-og-card-title`, `/cmd/<action>`): add a "try Ability first, fallback to REST" path. Same UX. Both branches exercised by tests. | ~150 |
| **WP 7.0 admin notice (X6 prep)** | Dismissible admin notice on every plugin admin page when `WP < 7.0`. Copy: "Signal & Noise Tools v5.0.0 will require WordPress 7.0. You're on X.Y." Persisted via user-meta dismiss state. | ~50 |
| **`@deprecated` PHPdoc annotations** | Tag every non-Ability surface (functions, routes) with `@deprecated since 4.6.0 — use signal-and-noise-tools/<ability-slug> ability instead. Removal in v5.0.0+`. Compile-time signal only — no runtime warnings yet. | ~100 |
| **CHANGELOG + readme.txt** | Top of CHANGELOG.md explicitly announces v5.0.0 plan (REST removals + WP 7.0 floor + non-Ability surface deprecation). Mirrors v4.4.0's v5.0.0-readiness pattern. | ~20 |
| **Tests** | Assertions: Ability registrations exist for each meaningful action; JS clients try Ability path first; admin notice renders correctly with version comparison logic; `@deprecated` PHPdoc parses cleanly (no syntax errors). | ~80 |

**Total ≈ 800 LOC.**

---

## §3. v9.6.0 prep-minor scope (theme)

**Character:** additive only — NO breaking changes. Mirrors §2 shape with theme-specific surface.

**Manifest:** `Tested up to: 7.0` (bump). `Requires at least: 6.7` (UNCHANGED — raised in v10.0.0).

| Work | Detail | LOC est |
|---|---|---|
| **Abilities API formalization (P6 — theme side)** | Theme already has 12 abilities. Audit `inc/template-maintenance.php`, `inc/wp-update-integration.php`, etc. for missing meaningful actions. Likely candidates: `clear-template-overrides`, `purge-all-caches`, `flush-rewrites`, possibly migration-runners. ~5–8 new abilities. | ~150 |
| **WP 7.0 admin notice** | Same pattern as plugin. Render on FSE/admin pages when `WP < 7.0`. | ~40 |
| **`@deprecated` PHPdoc annotations** | Tag any non-Ability theme surface that has an Ability equivalent. Smaller surface than plugin (~5–10 functions). | ~30 |
| **CHANGELOG + readme.txt** | Announces v10.0.0 plan explicitly. | ~20 |
| **Tests** | Ability-registration tests for new abilities; admin notice render test. | ~40 |

**Total ≈ 280 LOC.**

---

## §4. Patch-cycle rules — "fixes in the middle"

**Window:** v4.6.0 ship → v5.0.0 ship (mirror for theme: v9.6.0 → v10.0.0).

### Allowed as v4.6.x patch (PATCH bump only)

- Bugfixes — must NOT change public API
- UI/UX patches — must NOT change public API
- Documentation updates
- Test updates / additions
- New patterns / templates (theme only — additive, non-breaking)

### Not allowed as patch

- New Abilities registrations (those need a minor — would be v4.7.0+)
- New REST routes (need a minor)
- New `@deprecated` annotations expanding scope (need a minor — extends the prep framing)
- Any public-API rename or signature change (would be MAJOR — triggers v5.0.0)

### Allowed as new minor (v4.7.0+)

- Net-additive work that can't wait for v5.0.0:
  - New AI client surface that needs a new Ability
  - Security fix that requires a new public function
  - Net-new feature that's strategically tied to the prep framing
- v4.7.0 **continues** the prep-minor framing: adds any newly-discovered Abilities, extends `@deprecated` annotations, etc. v5.0.0 BC remains the next major event.

### Cross-package patch coordination

- Plugin v4.6.x patch does NOT force theme v9.6.x patch unless the cross-package contract is affected (locked by `tests/cross-package-listeners.php` since v9.5.0).
- If a coordinated fix is needed: **paired patches** (v4.6.x + v9.6.x) in the same session.
- Contract drift surfacing during a v4.6.x cycle is the trigger for a paired patch — not a free pass to break the contract.

### Gate to v5.0.0 BC

v5.0.0 BC convenes when BOTH prep minors are gated stable + UATed:
- v4.6.x has no outstanding bugs known
- v9.6.x has no outstanding bugs known
- Both repos have a clean post-ship audit (per §7's coordination model)

---

## §5. v5.0.0 major scope (plugin)

**Character:** BREAKING. SemVer 2.0.0 compliance: `Requires at least:` HARD-raise, public REST routes removed, public functions marked with runtime warnings.

**Manifest:** `Requires at least: 7.0` (HARD-RAISE). `Tested up to: 7.0`.

| Work | Detail | LOC est |
|---|---|---|
| **HARD-raise `Requires at least: 7.0` (X6)** | Plugin manifest header change. WP enforces — install on WP < 7.0 refuses. | ~10 |
| **REMOVE 4 `@deprecated since 2.5.0` REST routes (P2)** | `register_rest_route` deletions for `/ai/generate-meta-description`, `/ai/generate-excerpt`, `/ai/generate-og-card-title`, `/cmd/<action>`. Corresponding `snt_*_handler` functions deleted. | ~150 |
| **Flip `assets/ai-*.js` to Ability-only (X1)** | Remove REST fallback paths from each JS client. By v5.0.0 the Ability paths have been live + validated through the entire v4.6.x cycle window. | ~80 |
| **REMOVE `sn_login_rewrites_flushed` orphan option (P1)** | One-line `delete_option()` in upgrade path. Orphaned since v4.2.1 per scope audit §4. | ~10 |
| **DROP pre-7.0 compat code (X6)** | Audit + delete: AI Client feature-detect gates (`is_callable` workarounds — see `feedback_method_exists_vs_is_callable.md`), any native-breadcrumbs JSON-LD fallback (7.0 ships native; absorption-direction memory confirms drop), WP 6.x-specific code paths. | ~200 |
| **PROMOTE `@deprecated` PHPdoc → `_deprecated_function()` runtime warnings** | For everything annotated `@deprecated since 4.6.0`. Runtime warnings visible to developers with `WP_DEBUG`. Removal scheduled for **v6.0.0**. | ~80 |
| **Tests** | Delete tests for removed REST routes; assert removed endpoints return 404; assert `_deprecated_function()` fires for the deprecated surface. | ~100 |
| **CHANGELOG + readme.txt** | "v5.0.0 — Breaking changes" entry naming each removal + the WP 7.0 floor + the deprecation runtime warnings. | ~30 |

**Total ≈ 660 LOC.** Mostly removals — typical major shape (subtract code; add minimal new code).

---

## §6. v10.0.0 major scope (theme)

**Character:** BREAKING. Smaller scope than plugin major — theme has less to remove because theme already ran tighter v9.4.x → v9.5.0 cycles.

**Manifest:** `Requires at least: 7.0` (HARD-RAISE). `Tested up to: 7.0`.

| Work | Detail | LOC est |
|---|---|---|
| **HARD-raise `Requires at least: 7.0` (X6)** | Theme manifest header change in `style.css`. | ~10 |
| **DROP pre-7.0 compat code** | Audit theme for WP < 7.0 conditionals. Likely minimal — theme is already mostly WP 7.0+. Anything in `inc/og-fonts.php`, `inc/wp-update-integration.php`, etc. that checks for 7.0 features can simplify. | ~80 |
| **PROMOTE `@deprecated` PHPdoc → `_deprecated_function()` runtime warnings** | For functions annotated in v9.6.0 prep. Smaller surface than plugin. | ~30 |
| **(Conditional) theme.json v3 → v4 migration (T1)** | ONLY if WP has shipped v4 schema by this point. If not, defer to v11.0.0. Adds ~100–200 LOC if applicable. | ~0 baseline (+100–200 conditional) |
| **Tests** | Delete obsolete tests; add deprecation-warning assertion tests. | ~40 |
| **CHANGELOG + readme.txt** | "v10.0.0 — Breaking changes" entry. | ~25 |

**Total ≈ 185 LOC** (or ~285 with theme.json v4 migration if WP ships v4 in time).

---

## §7. Cross-package coordination points

The two repos co-evolve at 4 sync points across the cycle:

| # | Sync point | What syncs | When in cycle |
|---|---|---|---|
| 1 | **Post-v4.6.0 ship** | Theme verifies plugin's new Ability surface (~20–30 new abilities) is consumable from theme code. Smoke test: assert each new ability is `is_callable( 'WP_Abilities_Registry::get_ability' )` from theme context. | Within v4.6.x cycle, BEFORE v9.6.0 BC |
| 2 | **Post-v9.6.0 ship** | Plugin verifies any new cross-package contract changes are listening correctly. Likely no-op (no new contracts expected) — but confirmation step. | Within v9.6.x cycle |
| 3 | **v5.0.0 + v10.0.0 BC** | Each BC consumes the OTHER side's actual published state at BC-time. Plugin v5.0.0 BC consumes theme v9.6.x state; theme v10.0.0 BC consumes plugin v5.0.0 (or v5.0.x) state. | Right before each major's plan phase |
| 4 | **v5.0.0 + v10.0.0 ship** | Paired ship — same session if possible. Both raise WP 7.0 in lockstep. Cross-package contract tests run against both sides post-ship. | The paired-major event |

---

## §8. Anti-scope — what this cycle does NOT cover

| Item | Disposition | Why |
|---|---|---|
| **T1** — theme.json v3 → v4 schema migration | Conditional in v10.0.0 ONLY if WP ships v4 schema by then; else defer to v11.0.0 | No driver yet; tied to external WP roadmap (unknown timeline) |
| **P3** — `sn_admin_pages()` legacy table removal | Defer to v5.1.0+ | Bookmarks not aged enough; per plugin scope audit §6 |
| **P4 / P5** — cosmetic namespace renames (`sn_admin_*_tab`, `sn_pl_*`, `sn_admin_render_*`) | Defer indefinitely | No functional benefit; 9-file refactor for zero user impact |
| **T2 / T3 / T4** — theme cosmetic refactors (`sn_theme_restore_git_backup` rename, pattern-slug evolution, function-namespace refactor) | Defer indefinitely | No driver |
| **X2** — Mimestream-style release notes infrastructure | Defer to its own minor cycle (likely v4.7.0 / v9.7.0 or post-v10.0.0) | Content-heavy initiative; doesn't belong in Abilities prep minor |
| **X3** — new cross-package contracts | Defer | No driver |
| **X5** — shared schema co-evolution (deploy history, version sentinels) | Defer | No driver; current schemas work |
| **P7** — Self-knowledge chat / RAG-lite | Defer to post-v5.0.0 | Net-new feature; depends on the v5.0.0 Abilities surface being live; separate cycle |

---

## §9. Open questions + risks

### §9.1 — WP 7.0 adoption timing
**Risk:** WP 7.0 shipped 2026-05-20; spec dated 2026-05-27. Adoption is 1 week old. Requiring 7.0 for v5/v10 is moderately aggressive in general WP-ecosystem terms.

**Mitigation:**
- v4.6.0 / v9.6.0 admin notice gives users a warning period (likely months between prep-minor ship and v5/v10 ship).
- CHANGELOG entries announce explicitly.
- juanlentino.com is the primary install — verify it's on 7.0 before v4.6.0 BC (likely yes; confirm).
- Cloudways stack supports WP 7.0 (verify before HARD-raise — per `feedback_cloudways_*` memories, Cloudways tracks WP releases promptly).

### §9.2 — Cloudways + WP 7.0 compatibility
**Risk:** the user's hosting stack must support WP 7.0 before v5.0.0 ships. Symptoms of incompatibility: WP refuses to install, PHP errors at boot, admin lockout.

**Mitigation:** verify on Cloudways control panel + Cloudways docs before v5.0.0 BC convenes. If Cloudways hasn't certified 7.0 by then, defer the HARD-raise to v5.0.x or hold v5.0.0 until certification.

### §9.3 — WP theme.json v4 timeline
**Risk:** WP may ship theme.json v4 schema during the v9.6.x → v10.0.0 window. Decision needed at v10.0.0 BC: fold v4 migration in, or defer.

**Mitigation:** watch `make.wordpress.org/core` for v4 announcements. Decision rule:
- If v4 ships during v9.6.0 → v10.0.0 BC: fold into v10.0.0 (T1 becomes scope).
- If v4 has not shipped by v10.0.0 BC: defer T1 to v11.0.0; v10.0.0 ships without theme.json migration.

### §9.4 — Abilities discovery rule for prep minor
**Decision rule for "meaningful action" worth registering as Ability:**

A function/REST handler is a candidate for Ability registration if **at least one** of these is true:
1. An external AI agent could plausibly want to invoke it (e.g., "purge the cache," "regenerate OG card").
2. An automation script (cron, CLI, REST client) would want to invoke it.
3. It mutates state and has a clear input/output contract (admin actions, content actions).

Skip:
- Pure renderers (template helpers, widget output).
- Internal helpers (parsing, formatting, validation).
- Functions already hooked to WP events (those are extension points, not invocable actions).

Audit happens at v4.6.0 / v9.6.0 BC. Decisions documented in the per-cycle BC spec, not here.

### §9.5 — JS-client migration test coverage
**Risk:** v4.6.0's "try Ability first, fallback to REST" path needs explicit tests for BOTH branches. Without them, v5.0.0's REST-route removal could silently break (Ability path was untested, only REST worked).

**Mitigation:** v4.6.0 plan must include test coverage for both code paths. Recommended: PHP-side test that hits the Ability with valid args + asserts the JS-callable contract; JS-side test (or integration test) that asserts the fallback fires when Ability call fails.

### §9.6 — Timeline commitment
**Risk:** Approach B requires 4 ship windows × ~5–7 sessions per cycle = ~20–28 sessions total across both repos. Real commitment.

**Mitigation:** cap-drop allows extension at any point. If the user wants faster cadence at any cycle, can fold work into a bigger ship (becomes Approach A for that cycle). Not forced; decisions remain at the user's discretion at each BC.

---

## §10. Decision log

This brainstorm passed through 4 explicit gates:

| Gate | Question | Answer | Rationale |
|---|---|---|---|
| 1 | What's the goal of this brainstorm? | "Explore landscape, decide at end" | Honest engagement with scope audits' "no current driver" findings |
| 2 | Which directions resonate for v5/v10? | X1 + X6 + P6+X4 (all three substantive) | User picked maximum scope — wants real majors, not cosmetic |
| 3 | Which approach (big-bang vs prep-minor)? | Approach B (prep-minor → paired major) | Discipline cost (time) preferred over risk cost (production criticals); audit-then-patch already validated at v4.5.0 → v4.5.1 |
| 4 | How does "fixes in the middle" land? | v4.6.x / v9.6.x patches + optional v4.7.0+ for additive | Standard SemVer post-ship cycles; cap-drop allows extension; v5.0.0 BC consumes live state |

---

## §11. Next steps

1. **User review of THIS spec** — gate before transitioning to planning. If approved, proceed to step 2.
2. **Transition to `superpowers:writing-plans`** to produce the v4.6.0 implementation plan in the **plugin** repo (`signal-and-noise-tools/docs/superpowers/plans/<date>-v4.6.0.md`). Per Approach B sequencing, plugin v4.6.0 ships first.
3. **Execute v4.6.0** via subagent-driven development.
4. **v4.6.0 post-ship audit** (cross-cutting, per the discipline validated in v4.5.0 → v4.5.1).
5. **v4.6.x patches** as bugs surface during UAT.
6. **v9.6.0 BC + plan + execute + audit** (theme prep-minor cycle).
7. **v9.6.x patches** as bugs surface during UAT.
8. **v5.0.0 BC** (consumes v9.6.x state).
9. **v5.0.0 plan + execute + audit + patches**.
10. **v10.0.0 BC** (consumes v5.0.x state).
11. **v10.0.0 plan + execute + audit + patches**.
12. **DONE** — paired-major cycle complete; this spec archives.

---

## §12. One-line summary

**v4.6.0 + v9.6.0 prep minors (additive Abilities + WP 7.0 warning + @deprecated annotations) → bugfix-only v4.6.x / v9.6.x patch cycles (with v4.7.0+ allowed for net-additive emergencies) → v5.0.0 + v10.0.0 paired major (HARD WP 7.0 raise + 4 REST route removals + JS-client flip + non-Ability deprecation runtime warnings + pre-7.0 compat code drop). ~20–28 sessions total across both repos. Approach B selected over big-bang Approach A because the v4.5.0 → v4.5.1 cycle already validated audit-then-patch at the ship level; this extends the discipline to the major-cycle level. Cap-drop honored — each cycle's scope dictates whether it's minor or major, never the counter.**
