# Handoff — 2026-05-27 — Paired-major cycle COMPLETE (Plugin v4.5.1 + Theme v9.5.0)

**Why this exists:** the prior handoff ([2026-05-26-v4.5.0-shipped.md](2026-05-26-v4.5.0-shipped.md)) captured "plugin v4.5.0 shipped, theme v9.5.0 plan ready." This continuation session shipped two more cycles: a post-ship audit on v4.5.0 that surfaced a Critical functional gap and a v4.5.1 patch, followed by full execution + ship of the theme v9.5.0 plan. The paired-major brainstorm cycle that opened on 2026-05-26 with [the paired-design spec](../specs/2026-05-26-v4.5.0-and-v9.5.0-paired-design.md) is now fully DELIVERED on both sides.

---

## TL;DR

- **Plugin v4.5.1 SHIPPED** (commit `ced40b5`, tag `v4.5.1` on signal-and-noise-tools/main). Post-ship audit caught a CRITICAL dead-Suggest-button bug + 3 other findings; all patched before user UAT exposure.
- **Theme v9.5.0 SHIPPED** (commit `88e4a1a`, tag `v9.5.0` on signal-and-noise/main). 5-task plan executed: cross-package listener tests (25 assertions) + WCAG contrast verification (20 assertions) + v10.0.0 scope audit doc (244 lines) + sidenote test fix bundled at ship.
- **Both audits agree: v10.0.0 has no current driver.** Plugin v5.0.0-scope.md (separate) + Theme v10.0.0-scope.md (this cycle) both classify all public surface as KEEP. Cap-drop intact.
- **Test totals across both repos:** 940 plugin + 355 theme = **1,295 assertions, 0 failures** across 32 suites.
- **User actions owed:** install Plugin v4.5.1 + Theme v9.5.0 via wp-admin → Dashboard → Updates. Real-world UAT pending.

---

## Section 1: What shipped this session

### Plugin (`signal-and-noise-tools`)

| Version | Commit | What it does |
|---|---|---|
| **v4.5.1** | `ced40b5` | Post-ship audit fixes: **CRITICAL** dead Suggest button (`assets/health-suggest-actions.js` wasn't enqueued on Tools tab — entire Suggest+Apply loop was non-functional in production); **IMPORTANT** `buildSuggestButton()` missing block_migrations branch (Discard → Suggest retry path broke); MINOR Test 4 dismiss-filter fixture nested incorrectly (test passed for wrong reason); MINOR file docblock corrected ("back-compat surface" → primary JS surface). |

Audit cycle: cross-cutting holistic review over the 15-commit v4.5.0 implementation, looking for issues only visible at the whole-implementation level. Per-task two-stage reviews had already caught unit-level issues; this audit found integration issues.

**Plugin state:** v4.5.1, 940 assertions / 25 suites — all green.

### Theme (`signal-and-noise`)

| Version | Commit | What it does |
|---|---|---|
| **v9.5.0** | `88e4a1a` | Cross-package listener tests (WS1, theme-side seal for 4 plugin→theme filter contracts, 25 assertions); build-time WCAG contrast verification (WS3, ACCESSIBILITY.md baseline + ±0.20 drift tolerance, 20 assertions); v10.0.0 scope audit doc (WS2, 244 lines, 79 KEEP / 0 RENAME / 0 REMOVE / 1 SCHEMA-CHANGE-conditional); fix: sidenote pattern was untested (discovered during the v10.0.0 audit, bundled into ship); fix: readme.txt Stable tag drift (9.4.3 → 9.5.0). |

**Theme state:** v9.5.0, 355 assertions / 7 suites — all green.

### Theme repo: 7 commits since the previous handoff

```
88e4a1a v9.5.0: cross-package listener tests + WCAG contrast baseline + v10 scope audit
8788205 docs(spec): theme v10.0.0 scope audit — public surface inventory + dispositions (WS2 of v9.5.0)
0431bc9 feat(tests): build-time WCAG 2.1 contrast verification (WS3 of v9.5.0)
56d301c feat(tests): cross-package listener tests — theme-side of all 4 contracts (WS1 of v9.5.0)
cbae418 docs(handoff): session close — plugin v4.5.0 shipped, theme v9.5.0 plan ready
af8d5a0 docs(plan): theme v9.5.0 implementation plan — 5 tasks
7475d2c docs(spec): drop WS2 from v4.5.0 paired design — JS-client flip already shipped in v2.5.0
```

All 5 theme tasks + the ship commit landed cleanly. No cleanup commits needed on the theme side (the plan was tighter and the implementer subagents had cleaner reference patterns from the plugin cycle).

---

## Section 2: The post-ship audit findings (cross-cutting v4.5.0 review)

The audit was the formal "v4.5.x gate" check that the spec called for. It surfaced 4 findings; all were addressed in v4.5.1:

| Severity | Finding | Location | Fix |
|---|---|---|---|
| **CRITICAL** | `health-suggest-actions.js` only enqueued on `?tab=health` AND `snt_ai_is_available()`. Block Migrations lives at `?tab=tools` with no AI dependency → JS never loaded → every Suggest click silently no-op. | `inc/admin-page.php` enqueue gate | Added parallel enqueue block for `?tab=tools` with no AI gate. |
| **IMPORTANT** | `buildSuggestButton()` had branches for `missing_alt`, `drift_time_phrases`, `orphaned_media`, `pattern_adoption_*` — but the v4.5.0 cycle added the new `block_migrations_heading_skip` check type to the click-handler chain WITHOUT adding the symmetric branch here. After Discard → click Suggest, the rebuilt button lacked `data-post-id` / `data-fingerprint` / `data-migration-type` and validation failed. | `assets/health-suggest-actions.js` `buildSuggestButton()` | Added the missing branch. Same shape as the v4.4.3 Bug-B2 fix for pattern-adoption — carried forward correctly this time. |
| **MINOR** | Test 4 in `tests/block-migrations-detect.php` had a double-nested dismiss fixture (`array(array(...))` instead of `array(...)`). Test passed (0 candidates expected) for the WRONG reason — the dismiss filter was never actually exercised because the stored value didn't match what `in_array()` looked for. | `tests/block-migrations-detect.php:155` | Unwrapped to a single-level array. Test now passes for the right reason. |
| **MINOR** | `inc/block-migrations-admin.php` file docblock described the dismiss REST endpoint as "back-compat surface for JS clients." Carried over from pattern-adoption's docblock where it was actually a back-compat alias. For block-migrations it's the PRIMARY JS surface. | `inc/block-migrations-admin.php` docblock | Rephrased — REST endpoint is primary, ability wrapper is secondary for AI agents. |

**Audit value-add:** the Critical finding would have ruined first-impression UAT — every user clicking Suggest in the new Tools tab would have gotten silence. The audit caught it before that exposure. This is the highest-value finding of the entire v4.5.0 cycle, and it surfaced only because of the holistic post-ship review (not visible to per-task reviewers because the JS enqueue logic lives in `admin-page.php`, not in any of the block-migrations files).

---

## Section 3: The v10.0.0 scope audit findings (cross-package signal)

The theme's `docs/superpowers/specs/2026-05-27-v10.0.0-scope.md` (244 lines) audited the theme's public surface across 7 dimensions:

| Surface | Count | Disposition |
|---|---|---|
| `sn_*` functions in `inc/` | 36 | all KEEP |
| Dispatched hooks (apply_filters / do_action FROM theme) | 4 | all KEEP |
| Cross-package contract listeners | 4 | all KEEP (locked by `tests/cross-package-listeners.php` as of v9.5.0) |
| theme.json schema | v3 (WP 7.0) | 5 KEEP + 1 SCHEMA-CHANGE-conditional (v3→v4 if WP ships v4 schema) |
| Templates + parts | 13 + 4 | all KEEP |
| Block patterns | 6 | all KEEP |
| Theme abilities | 12 | all KEEP |

**Aggregate:** 79 KEEP / 0 RENAME / 0 REMOVE / 1 SCHEMA-CHANGE-conditional.

**Conclusion:** v10.0.0 has no current driver. The only schema-change candidate is contingent on WP shipping theme.json v4 (unknown timeline). All public surface is stable.

**Surprising finding during the audit:** `patterns/sidenote.php` exists with a valid `signal-noise/sidenote` slug but was not registered in `tests/patterns-registry.php`. The pattern works in production (WP auto-registers it from the patterns/ directory), but had no test enforcement. Coverage gap caught + fixed in the same v9.5.0 ship commit (+7 assertions added to patterns-registry test).

**Pairing with plugin v5.0.0-scope.md:** both audits now exist (plugin: `2026-05-26-v5.0.0-scope.md`, theme: `2026-05-27-v10.0.0-scope.md`). They agree: no current driver for the paired major. Cap-drop framing intact.

---

## Section 4: User actions owed

Carried over:

- ⏳ **Install Plugin v4.5.1** (supersedes v4.5.0 / v4.4.5 / earlier) via wp-admin → Dashboard → Updates → "Update plugin" for Signal & Noise Tools.
- ⏳ **Install Theme v9.5.0** (supersedes v9.4.6 / v9.4.5 / earlier) via wp-admin → Dashboard → Updates → "Update theme" for Signal & Noise. **No cache purge needed** — v9.5.0 ships only tests + docs, no CSS/JS/template changes.
- ⏳ **UAT the Block Migrations Tools sub-tab** (now actually functional after v4.5.1):
  1. wp-admin → Signal & Noise → Tools → Block Migrations.
  2. Click "Scan for migrations." Should produce a list (the PA-01 candidates that the v4.4.x bespoke SQL missed).
  3. Click [Suggest] on a row. Modal should open with the before/after preview. **If this opens — the v4.5.1 enqueue fix is working in production.** If it silently does nothing → revert to v4.5.0 isn't the answer; the bug shipped in v4.5.0 and was fixed in v4.5.1, so verify v4.5.1 actually installed.
  4. Click [Apply] in the modal. Post should update; verify the live URL renders `<h2>` after a Cloudflare + Breeze purge.
  5. Optional: click [Dismiss] on a different candidate row. Row disappears; re-scan shouldn't bring it back.
  6. Optional: click [Suggest], then [Discard], then [Suggest] again — the data-attrs should persist (this is the IMPORTANT fix in v4.5.1).
- ⏳ **Verify both repos are in sync on GitHub.** Both `signal-and-noise` and `signal-and-noise-tools` `main` branches should have the new tags visible.
- ⏳ Aikido Security check for prior exploitation of `tests/contracts-smoke.php` during the v4.4.0 → v4.4.2 exposure window (carried from earlier handoff — not blocking v4.5.1 or v9.5.0).

---

## Section 5: Strategic state

| Repo | Current | Next concrete step |
|---|---|---|
| Plugin (`signal-and-noise-tools`) | **v4.5.1** (gated) | After user UAT confirms in production: stable. If bugs surface → v4.5.2 patch cycle. Otherwise: nothing required until next minor (v4.6.0 — TBD scope). |
| Theme (`signal-and-noise`) | **v9.5.0** (just shipped) | Post-ship UAT pending. v9.5.x cycle on any bugfixes. Then stable until next minor. |

**Caps remain dropped** (since 2026-05-26). v5.0.0 / v10.0.0 stay optional — convene only on actual breaking changes per SemVer. The two scope audits (plugin's v5.0.0-scope.md + theme's v10.0.0-scope.md) both confirm "no current driver" as of 2026-05-27.

---

## Section 6: Next-session pickup recipe

```bash
# 1. Navigate to theme worktree
cd /Users/juanlentino/Projects/signal-and-noise/.claude/worktrees/nice-goldstine-063551

# 2. Read THIS handoff (you are here)
cat docs/superpowers/handoffs/2026-05-27-paired-major-cycle-complete.md

# 3. Verify both repos are at the documented shipped state
cd /Users/juanlentino/Projects/signal-and-noise-tools
git log --oneline -3
# Expect top: ced40b5 v4.5.1: post-ship audit fixes ...
grep "^ \* Version:" signal-and-noise-tools.php
# Expect: 4.5.1
git tag --list | tail -3
# Expect: ..., v4.5.0, v4.5.1

cd /Users/juanlentino/Projects/signal-and-noise/.claude/worktrees/nice-goldstine-063551
git log --oneline -3
# Expect top: 88e4a1a v9.5.0: cross-package listener tests + WCAG contrast baseline + v10 scope audit
grep "^Version:" style.css
# Expect: 9.5.0
git tag --list | tail -3
# Expect: ..., v9.4.6, v9.5.0

# 4. Confirm tests stay green on both sides
cd /Users/juanlentino/Projects/signal-and-noise-tools
for f in tests/*.php; do echo "=== $f ==="; php "$f" 2>&1 | tail -1; done | grep -E "(passed|failed)"
# Expect aggregate: 940 passed, 0 failed across 25 suites (contracts-smoke.php = WP-CLI guard, not failure)

cd /Users/juanlentino/Projects/signal-and-noise/.claude/worktrees/nice-goldstine-063551
for f in tests/*.php; do echo "=== $f ==="; php "$f" 2>&1 | tail -1; done | grep -E "(passed|failed)"
# Expect aggregate: 355 passed, 0 failed across 7 suites
```

### Decision points for the next session

**If user has installed both + UATed successfully + no bugs:**
→ Both cycles are STABLE. The session can move to net-new work. Candidates per the prior handoffs:
- v4.6.0 / v9.6.0 minors (no scope defined yet — brainstorm time)
- Outstanding items from `feedback_plugin_absorption_strategic_direction.md` memory (post-WP-7.0 audit candidates not yet absorbed)
- The Mimestream-style release notes idea from `project_mimestream_style_release_notes.md` memory (forward-looking, post-v10.0.0)
- Theme/plugin's [outstanding user actions](#section-4-user-actions-owed) if any are still pending

**If user has installed + found bugs:**
→ Bugfix patch cycle on the affected side. v4.5.2 or v9.5.1. Same workflow: small fix + bump + tag + push.

**If user hasn't installed yet:**
→ Don't push more code. The current state is fully self-consistent — both repos are gated and shipped. Wait for UAT signal.

---

## Section 7: Lessons captured this session

These extend the prior handoff's lessons. Worth carrying forward:

1. **Post-ship audits catch what per-task reviews miss.** The dead-Suggest-button bug in v4.5.0 had clean per-task code reviews on every commit. It only surfaced when the holistic audit asked "is the JS that the admin renderer references actually enqueued?" — a question that crosses the admin module, the JS file, and the enqueue logic in admin-page.php. None of the three per-task reviews owned the cross-file question. The audit did. Lesson: **schedule the audit as a discrete step, not as "we'll get to it."** Per-task reviews and post-ship audits look at different things.

2. **Scope audits surface real bugs as a side effect.** The v10.0.0 scope audit was supposed to be a doc-only inventory ("classify every public surface item"). It found that `signal-noise/sidenote` was a real pattern with no test coverage. Fix landed in the same v9.5.0 ship commit. The audit cost ~1 subagent dispatch and produced both the strategic deliverable (the doc) AND a tactical bug fix.

3. **Bookend reviews are load-bearing in different ways.** The full v4.5.0 + v4.5.1 + v9.5.0 cycle produced findings at three review points:
   - **Pre-spec source verification** (dropped WS2 entirely — prevented phantom work)
   - **Per-task two-stage reviews** (caught 6 cleanup-worthy findings across 10 plugin tasks)
   - **Post-ship audit** (caught 1 Critical + 1 Important + 2 Minor)
   - **Scope audit during ship** (caught the sidenote test coverage gap)
   Four different review postures, four different failure modes caught. Each had unique value. None was redundant.

4. **Plan estimates for assertion counts are systematically low.** Plugin plan estimated 931 aggregate; shipped 940 (+9 from cleanups). Theme plan estimated 344 aggregate; shipped 355 (+11 from cross-package-listeners actually having 25 not 21, contrast having exactly 20, sidenote fix adding 7). The drift is normal — plans count assertions before edge cases are fleshed out during implementation. Don't treat plan numbers as gospel; verify against actual `php tests/*.php` output before tag.

5. **`grep -c "<FILL"` returning 0 with exit code 1 is correct behavior.** Easy to mistake the exit-1 for failure when chaining with `&&`. Use `;` (sequential) or check exit code explicitly when the "no matches" outcome IS the success condition.

6. **The paired-design pattern works at the discipline level, not just the file level.** v4.5.0 + v9.5.0 were drafted as one spec, planned as two plans, executed in spec-prescribed sequence (plugin first, gate, theme second), reviewed at three audit points each. The paired framing held through the whole cycle. The cap-drop framing (v5.0.0 / v10.0.0 optional, gated on actual breaking changes, NOT on counter-rollover math) was tested and held. Both scope audits agree: no current major driver. The pattern is **stable enough to reuse for v4.6.0 + v9.6.0 if/when scope emerges**.

7. **v4.5.1 patch shipped same-day, no install gap.** Old discipline would have been to ship v4.5.0, wait for user UAT to surface the dead-button bug, then patch. New discipline (audit-then-patch before UAT) means v4.5.0 was effectively never the "in production" version — v4.5.1 supersedes it before install. This is a faster, safer pattern for releases when the audit catches Critical issues. **Worth applying to future cycles by default: always audit before declaring gate.**

---

## Section 8: Task list state at session close

All 21 tracked tasks complete:

| # | Status | Subject |
|---|---|---|
| 1 | ✅ | Spec user review — v4.5.0 + v9.5.0 paired design |
| 2 | ✅ | Transition to superpowers:writing-plans — one plan per repo |
| 3 | ✅ | Execute Plugin v4.5.0 via subagent-driven development |
| 4 | ✅ | Revise paired spec — drop WS2 (already shipped in v2.5.0) |
| 5–14 | ✅ | Plugin Tasks 1–10 (full v4.5.0 plan execution) |
| 15 | ✅ | Plugin v4.5.x post-ship audit — holistic cross-cycle review (→ v4.5.1 patch) |
| 16 | ✅ | Theme v9.5.0 BC — execute the 5-task plan |
| 17–21 | ✅ | Theme Tasks 1–5 (full v9.5.0 plan execution) |

---

## Section 9: Open initiatives carried forward

Not blocking, but worth surfacing for future sessions:

- **`project_mimestream_style_release_notes.md`** memory: forward-looking idea to add categorized/visual release notes alongside CHANGELOG.md. Not for v4.5.x or v9.5.x. Possibly v4.6.0+ or post-v10.0.0.
- **`feedback_plugin_absorption_strategic_direction.md`** memory: prior brainstorm identified additional plugin absorption candidates beyond the 15-phase roadmap (which closed). Some may warrant net-new minors.
- **PA-01 content sweep:** the heading-hierarchy-skip issue that originally surfaced in the v4.4.x cycle is now ADDRESSABLE via the v4.5.0 Block Migration tool. User UAT of that tool is the test of whether the audit-driven feature actually solves the original problem.
- **Theme.json v3 → v4 schema migration:** flagged in v10.0.0-scope.md as the only SCHEMA-CHANGE candidate. Conditional on WP shipping v4 schema (unknown timeline). When it arrives, would trigger the v10.0.0 cycle.

---

## One-line summary

**Session shipped Plugin v4.5.1 (post-ship audit patch fixing a CRITICAL dead-Suggest-button enqueue bug + Important Discard-retry break + 2 minor) and Theme v9.5.0 (cross-package listener tests + WCAG contrast baseline + v10.0.0 scope audit + bundled sidenote test coverage fix). Both audits confirm v10.0.0 has no current driver — cap-drop intact. Combined test count across both repos: 1,295 assertions across 32 suites, 0 failures. Paired-major brainstorm cycle from 2026-05-26 is now fully DELIVERED. Next session pickup: user installs both, UATs the Block Migrations sub-tab, and either declares stable or surfaces patch-worthy bugs.**
