# Handoff — 2026-05-27 — Paired cycle: spec + all 4 plans LOCKED

**Why this exists:** the prior handoff ([`2026-05-27-paired-major-cycle-complete.md`](2026-05-27-paired-major-cycle-complete.md)) captured "plugin v4.5.1 + theme v9.5.0 both shipped, paired-major cycle COMPLETE." This continuation session ran the v5/v10 brainstorm the user requested, locked Approach B (prep-minor → paired major), and wrote ALL FOUR implementation plans (v4.6.0 + v9.6.0 + v5.0.0 + v10.0.0) before pausing execution. The strategic + tactical scaffolding for the next paired-major cycle is now fully on disk + in git.

---

## TL;DR

- **Brainstorm cycle convened + completed:** user explicitly requested v5.0.0 + v10.0.0 brainstorm done together in parallel; spec committed in theme repo as `docs/superpowers/specs/2026-05-27-v5-and-v10-paired-cycle-design.md` (commit `23a15e9`).
- **Approach B locked:** prep minor → patch cycle → paired major. Three real SemVer drivers: X6 (WP 7.0 baseline raise), X1+P2 (JS-client → Ability flip + REST removal), P6+X4 (Abilities API formalization + non-Ability deprecation). User picked maximum scope across all three drivers.
- **ALL 4 implementation plans written + committed + pushed.** v4.6.0 + v5.0.0 in plugin repo; v9.6.0 + v10.0.0 in theme repo. v4.6.0 + v9.6.0 are fully concrete (executable as-written); v5.0.0 + v10.0.0 carry `**TENTATIVE — refine at BC**` headers because their Tasks 2/3/6/etc. depend on the live state AFTER prep minors land.
- **No execution started yet.** User chose to lock all plans first, then execute later. Next session picks up at v4.6.0 BC (in plugin repo) and proceeds through subagent-driven execution.
- **Total estimated arc:** ~1,725 LOC across 4 ships + post-ship cycles. ~20–28 sessions per the spec §9.6 commitment estimate.

---

## Section 1: What shipped this session

### Theme repo (`signal-and-noise`)

3 commits on `main`:

```
2b827fd docs(plan): theme v10.0.0 major implementation plan (TENTATIVE)
d6725a8 docs(plan): theme v9.6.0 prep-minor implementation plan — 1 new ability + WP 7.0 notice
23a15e9 docs(spec): v5.0.0 + v10.0.0 paired-cycle design — Approach B prep-minor → major
```

### Plugin repo (`signal-and-noise-tools`)

2 commits on `main`:

```
7d44621 docs(plan): plugin v5.0.0 major implementation plan (TENTATIVE)
cf82bc0 docs(plan): v4.6.0 prep-minor implementation plan — 6 abilities + WP 7.0 notice + REST deprecation
```

### Released versions

No actual code shipped this session. Theme stays at **v9.5.0** (last release: `88e4a1a`, tag `v9.5.0`). Plugin stays at **v4.5.1** (last release: `ced40b5`, tag `v4.5.1`). Both repos remain at the state captured in the prior handoff.

---

## Section 2: Plans inventory + status

| Plan | LOC est | Status | Commit | Repo |
|---|---|---|---|---|
| v4.6.0 — Plugin prep minor | ~540 | **Concrete (ready to execute)** | `cf82bc0` | signal-and-noise-tools |
| v9.6.0 — Theme prep minor | ~220 | **Concrete (ready to execute)** | `d6725a8` | signal-and-noise |
| v5.0.0 — Plugin major | ~780 | TENTATIVE | `7d44621` | signal-and-noise-tools |
| v10.0.0 — Theme major | ~185 (or ~385 with v3→v4) | TENTATIVE | `2b827fd` | signal-and-noise |

**Sum:** ~1,725 LOC. **Concrete portion (prep minors only):** ~760 LOC across the 2 prep-minor ships.

### Audit-driven scope revisions vs spec estimates

Both prep-minor plans audited the actual codebase and revised down from the spec's estimates:

- **v4.6.0:** spec estimated ~800 LOC; audit found 30 abilities already registered + JS clients already using Abilities since v2.5.0. Revised to ~540 LOC.
- **v9.6.0:** spec estimated ~280 LOC; audit found theme already has 12 abilities + 0 REMOVE candidates in v10.0.0-scope.md. Revised to ~220 LOC. The `@deprecated` annotations workstream is empty in practice.

This delta is normal — plans audit reality; specs sketch direction. The strategic shape (prep minor → patch cycle → paired major) is unchanged.

---

## Section 3: Sequencing chain (HARD GATES)

```
v4.5.1 (current plugin, shipped)
   │
   ▼
[Session N+1]  v4.6.0 BC → plan → execute → ship → post-ship audit → v4.6.x patches → gate
   │
   ▼
[Session N+2]  v9.6.0 BC → plan → execute → ship → post-ship audit → v9.6.x patches → gate
   │
   ▼
[Session N+3]  Both prep minors stable + UATed
                  ↓
                  v5.0.0 BC (refresh tentative plan against live state)
                  ↓
                  v5.0.0 execute → ship → post-ship audit → v5.0.x patches → gate
   │
   ▼
[Session N+4]  v10.0.0 BC (refresh tentative plan against live state)
                  ↓
                  v10.0.0 execute → ship paired with v5.0.x stable (same session if possible)
                  ↓
                  v10.0.x post-ship cycle → gate
   │
   ▼
PAIRED-MAJOR CYCLE COMPLETE → spec archives → roadmap for v11/v6 emerges from live state
```

**Critical sync points** (per spec §7):
1. Post-v4.6.0 ship: theme smoke-tests the new ~20–30 plugin abilities (actually: 6 new — per the audit).
2. Post-v9.6.0 ship: plugin verifies no cross-package contract drift.
3. v5.0.0 BC consumes v9.6.x state; v10.0.0 BC consumes v5.0.0 (or v5.0.x) state.
4. v5.0.0 + v10.0.0 ship paired (same session if possible) so WP 7.0 floor lands in lockstep.

---

## Section 4: Next-session pickup recipe

```bash
# 1. Navigate to theme worktree (this branch)
cd /Users/juanlentino/Projects/signal-and-noise/.claude/worktrees/nice-goldstine-063551

# 2. Read THIS handoff (you are here)
cat docs/superpowers/handoffs/2026-05-27-paired-cycle-all-plans-locked.md

# 3. Read the spec (strategic frame)
cat docs/superpowers/specs/2026-05-27-v5-and-v10-paired-cycle-design.md

# 4. Read the v4.6.0 plan (FIRST execution target)
cd /Users/juanlentino/Projects/signal-and-noise-tools
cat docs/superpowers/plans/2026-05-27-v4.6.0.md

# 5. Verify both repos are at the published state
git log --oneline -3 -- docs/superpowers/plans/
# Expect: 7d44621 + cf82bc0 at top of plugin-side plans/

cd /Users/juanlentino/Projects/signal-and-noise/.claude/worktrees/nice-goldstine-063551
git log --oneline -3 -- docs/superpowers/plans/ docs/superpowers/specs/
# Expect: 2b827fd + d6725a8 + 23a15e9 across theme-side specs/ and plans/

# 6. Confirm both test suites are still green
cd /Users/juanlentino/Projects/signal-and-noise-tools
for f in tests/*.php; do echo "=== $f ==="; php "$f" 2>&1 | tail -1; done | grep -E "(passed|failed)"
# Expect: 940 passed, 0 failed across 25 suites

cd /Users/juanlentino/Projects/signal-and-noise/.claude/worktrees/nice-goldstine-063551
for f in tests/*.php; do echo "=== $f ==="; php "$f" 2>&1 | tail -1; done | grep -E "(passed|failed)"
# Expect: 355 passed, 0 failed across 7 suites
```

### Decision points for the next session

**Default path (most likely):** start v4.6.0 execution.

```bash
# Invoke the writing-plans skill's recommended sub-skill
# (Use the Skill tool to invoke superpowers:subagent-driven-development
# pointed at signal-and-noise-tools/docs/superpowers/plans/2026-05-27-v4.6.0.md)
```

**Conditional path:** if v4.5.1 plugin or v9.5.0 theme has surfaced bugs during UAT since 2026-05-27, ship those patches FIRST (v4.5.2 / v9.5.1) before starting v4.6.0. The v4.6.0 cycle should start against a stable v4.5.x baseline.

**Skip path:** if the user wants to defer execution longer, the plans persist on disk + in git. No state drift; just check `git log` to confirm nothing changed.

---

## Section 5: Open questions + risks carried forward

| # | Item | Severity | Notes |
|---|---|---|---|
| 1 | WP 7.0 adoption timing | Medium | WP 7.0 shipped 2026-05-20; this spec dated 2026-05-27. Adoption is 1 week old. Verify juanlentino.com is on 7.0 before v4.6.0 BC (spec §9.1). |
| 2 | Cloudways + WP 7.0 compatibility | Medium | Verify Cloudways stack supports 7.0 before v5.0.0 HARD-raises (spec §9.2). |
| 3 | WP theme.json v4 timeline | Low | Unknown; watch make.wordpress.org/core. Decision at v10.0.0 BC: fold into v10 or defer to v11 (spec §9.3). |
| 4 | Task 6 (v5.0.0 pre-7.0 compat drop) — exact list | Medium | Marked `[BC-REFINE]`. Audit at v5.0.0 BC against then-current codebase. |
| 5 | Task 7 (v5.0.0 _deprecated_function promotion) clarification | Medium | Tasks 2+3 REMOVE the deprecated handlers, so there's nothing left to runtime-warn. Spec §5 row may have intended other non-Ability surface (e.g., `sn_admin_pages()`). Resolve at BC. |
| 6 | Timeline commitment | Low | ~20–28 sessions across both repos. User aware; cap-drop allows extension at any cycle. |
| 7 | Aikido security check carried from prior handoff | Low | Not blocking. Recheck after v4.6.0 ships. |

---

## Section 6: Lessons captured this session

1. **Plans-first visibility is a legitimate user mode.** The user explicitly asked to write ALL plans before starting execution. The cost: v5.0.0 + v10.0.0 plans carry TENTATIVE markers because their tasks depend on live post-prep-minor state. The benefit: full arc visibility before any code lands. The discipline that preserves honesty in this mode: `**TENTATIVE — refine at BC**` headers + `[BC-REFINE]` per-task markers.

2. **Audit-driven scope revisions are normal during plan writing.** Both prep-minor plans revised LOC down from spec estimates (~800 → ~540, ~280 → ~220) after auditing the actual codebase. The spec sketched direction; the plan audited reality. Documenting the delta in a "Scope context" preamble at the top of each plan preserves the audit trail.

3. **Explore agent was non-functional this session.** Every Explore dispatch returned "Prompt is too long" regardless of prompt size. Fell back to direct Bash + Read commands, which worked fine. Worth investigating in a future session — but not blocking.

4. **CWD persistence between Bash calls means cd is sticky.** After `cd /Users/juanlentino/Projects/signal-and-noise-tools && ...`, subsequent commands run in plugin repo until explicitly cd-ed elsewhere. The git-push step caught this — the first `git push origin HEAD:main` went to the plugin repo (since CWD was still there) instead of the theme worktree. Fix was a second cd + push. Worth being explicit about CWD in multi-repo flows.

5. **Spec → plan → reality drift is normal and expected.** The spec's scope estimates are pre-audit. Plans should AUDIT and revise. Specs shouldn't be edited retroactively for LOC accuracy — the spec is the strategic frame; the plan is the tactical contract.

---

## Section 7: One-line summary

**Session shipped strategic + tactical scaffolding for the v5.0.0 + v10.0.0 paired-major cycle: a 12-section spec (Approach B locked: prep minor → patch cycle → paired major) and all 4 implementation plans (v4.6.0 + v9.6.0 concrete; v5.0.0 + v10.0.0 TENTATIVE). Both repos pushed; 5 commits total across both. No code executed this session. Next session picks up at v4.6.0 in plugin repo via subagent-driven execution. Estimated arc: ~1,725 LOC across 4 ships + post-ship cycles, ~20–28 sessions, cap-drop honored throughout.**
