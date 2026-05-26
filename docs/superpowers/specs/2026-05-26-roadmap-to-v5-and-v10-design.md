# Roadmap — Path to v5.0.0 (plugin) + v10.0.0 (theme)

**Status:** ROADMAP LOCKED via brainstorm 2026-05-26. Living doc — phase completion markers update as work ships; structural shape doesn't change without re-brainstorm.

**Last updated:** 2026-05-26 (caps dropped — v5.0.0 + v10.0.0 no longer cap-forced; sequencing preserved as intent, not requirement).

**Repos:**
- Plugin: [`signal-and-noise-tools`](https://github.com/juanlentino/signal-and-noise-tools) — currently at v4.4.0
- Theme: [`signal-and-noise`](https://github.com/juanlentino/signal-and-noise) — currently at v9.4.0

**Coordination model:** Sequenced (Option C from brainstorm), now as an intent rather than a requirement. v5.0.0 ships first IF/WHEN it ships; v9.5.0 starts after v5.0.x post-ship cycle completes. Theme prep operates on plugin's published reality, not anticipated scope.

**Cap policy update (2026-05-26):** the 7-patches-per-minor + 5-minors-per-major caps were dropped after the v4.4.x audit revealed the caps were forcing fictional majors. v5.0.0 and v10.0.0 are no longer cap-forced — they happen when actual breaking changes (per SemVer) accumulate. Plugin can now ship v4.5.0, v4.6.0, etc. as long as no breaking changes are warranted. Theme similarly. The `v5.0.0-scope.md` audit doc's single REMOVE finding can ship as a `v4.4.4+` patch whenever convenient. See [docs/VERSIONING.md](../../VERSIONING.md) for full rationale.

---

## 1. The two lanes

```
                                                              ┌───── PLUGIN DONE
Plugin lane ─┬──────┬───────┬───────┬───────┐
             │      │       │       │       │
           v4.4.0  v4.4.x  v5.0.0  v5.0.0  v5.0.x
           (✓)    cycle    BC      ship    cycle ──── gate ┐
                                                            │
                                                            │
Theme lane ──┬──────┬─────────────────────────────────────  ▼  ─┬──────┬──────┬──────┬──────┬──────┐
             │      │                                            │      │      │      │      │      │
           v9.4.0  v9.4.x   ...... parallel-OK with plugin ......v9.5.0 v9.5.0 v9.5.x v10.0.0 v10.0.0 v10.0.x
           (✓)    cycle                                          BC     ship   cycle  BC      ship    cycle
                                                                                              │
                                                                                              └───── THEME DONE
```

**Glossary:**
- **BC** — Brainstorm-checkpoint (run via `superpowers:brainstorming` skill; outputs a per-phase spec doc)
- **cycle** — Post-ship cycle (QA → Bugfix → UI/UX → Gate)
- **gate** — Concrete attestation commit that closes a cycle and unblocks the next phase

**Critical cross-repo sync:** v9.5.0 cannot start until BOTH plugin v5.0.x cycle gate AND theme v9.4.x cycle gate have passed. Plugin and theme work on their cycles can run in true parallel (different sessions, different worktrees) — the synchronization only happens at v9.5.0 entry.

---

## 2. Phase template (universal repeating structure)

Every minor and major follows this exact sequence:

```
[Phase vX.Y.Z]
  1. (mandatory for new minors + majors) Brainstorm-checkpoint
     └─→ produces docs/superpowers/specs/<YYYY-MM-DD>-vX.Y.Z-design.md
         Even when content reduces to "ship the backbone only," the BC produces
         an explicit "no additional scope" spec — never implicit silence.
  
  2. Plan
     └─→ produces docs/superpowers/plans/<YYYY-MM-DD>-vX.Y.Z.md (via writing-plans skill)
  
  3. Execute
     └─→ shipped + annotated tag + pushed to origin/main
         (Per CLAUDE.md: tag pushes do NOT auto-deploy — user installs via wp-admin)
  
  4. Post-ship cycle:
     a. QA pass
        - Run documented smoke recipes against the live install
        - Walk the surfaces named in the phase's spec §5
        - For each finding: categorize as bug / UI/UX / observation
        - Output: a findings list (lives in the Gate commit body or a brief notes doc)
     
     b. Bugfix patches (0-N, max 7 per cycle)
        - One patch per bug or one bundling several related bugs
        - `fix(...): summary` commit prefix
        - Version bump per CLAUDE.md release workflow
     
     c. UI/UX patches (0-N, max 7 per cycle — shared cap with bugfix)
        - One patch per polish or bundling
        - `polish(...):` / `refine(...):` commit prefix
        - Version bump per CLAUDE.md release workflow
     
     d. Gate
        - Concrete commit: `docs(roadmap): vX.Y.x cycle complete — gate passed`
        - Body lists what was found in QA + which patches addressed each finding
        - Updates this roadmap doc (§3 below) to mark the phase ✓ complete
        - Pushed to origin/main → next phase unblocked
```

**Note on patch caps:** per project [VERSIONING.md](../../VERSIONING.md), patch cap is 7 per minor. The Bugfix + UI/UX patches share that cap. If a cycle threatens to exceed 7 patches, that's a signal to roll a minor instead (which forces re-brainstorm).

---

## 3. Per-phase content + completion tracking

### Plugin lane

| Phase | Status | Notes |
|---|---|---|
| **v4.4.0** | ✓ SHIPPED 2026-05-26 | Commit [`79ea06f`](https://github.com/juanlentino/signal-and-noise-tools/commit/79ea06f), tag `v4.4.0`. Cross-package contracts E2E + v5.0.0 readiness pass. 888 assertions / 21 suites. |
| **v4.4.x post-ship cycle** | ✓ COMPLETE (re-gated) 2026-05-26 | Initial gate (commit a9c71bc) passed prematurely on narrow QA. Deep audit ([findings doc](2026-05-26-v4.4.x-and-v9.4.x-cycle-audit-findings.md)) then surfaced 1 CRITICAL + 2 HIGH + 2 MEDIUM bugs + 9 UI/UX. All addressed across 3 patches: **v4.4.1** (docs tightening, cc881fd), **v4.4.2** (CRITICAL: CLI guards on 27 test files closing remote destructive-action exposure, 0dd786f), **v4.4.3** (Bug-B2 JS dispatcher + Bug-B1 login allowlist + Bug-E1 schema language + TSF gating + inline-style consolidation, 2ad81d0). Live install + URL 404s verified. Site Editor checked — no exploitation occurred during exposure window. Patch count: **3/7**. Re-gate commit: [this commit]. |
| **v5.0.0 brainstorm-checkpoint** | ⏳ READY TO RUN (no longer cap-forced) | Plugin v4.4.x gate passed 2026-05-26. Inputs: [v5.0.0-scope.md](https://github.com/juanlentino/signal-and-noise-tools/blob/main/docs/superpowers/specs/2026-05-26-v5.0.0-scope.md) (backbone — 1 REMOVE on `sn_login_rewrites_flushed` option, no longer forced as v5.0.0; can ship as v4.4.4+ patch), [mimestream-style release notes](~/.claude/projects/-Users-juanlentino-Projects-signal-and-noise/memory/project_mimestream_style_release_notes.md) memory entry (candidate feature). **Legitimate BC outcomes now include "no v5.0.0 yet — counter reset isn't required and the single REMOVE ships as a patch."** |
| **v5.0.0 ship** | 🔒 BLOCKED on v5.0.0 BC (and on actual breaking changes warranting a major) | Major version. Caps dropped 2026-05-26 — happens only when actual breaking changes accumulate, not when counter math forces it. May ship eventually as the accumulation of cluster transitions, refactors, schema changes. Until then, v4.5.0 / v4.6.0 / etc. are valid. |
| **v5.0.x post-ship cycle** | 🔒 BLOCKED on v5.0.0 ship | Same template. Gate completion unblocks v9.5.0 in theme lane. |

### Theme lane

| Phase | Status | Notes |
|---|---|---|
| **v9.4.0** | ✓ SHIPPED 2026-05-26 | Commit [`a9a6d23`](https://github.com/juanlentino/signal-and-noise/commit/a9a6d23), tag `v9.4.0`. Typography polish (justified + hyphenation + hanging punctuation). 303 assertions / 5 suites. |
| **v9.4.x post-ship cycle** | ✓ COMPLETE 2026-05-26 | Deep audit + browser smoke surfaced 1 HIGH (sidenote justification regression) + 1 doc-level + 1 UI/UX (Next-only nav) + 1 user-preference (drop cap too aggressive at 5rem). All addressed across 3 patches: **v9.4.1** (sidenote `:not(.sn-sidenote)` selector exclusion, 083b489), **v9.4.2** (test file CLI guards + Previous post nav link, 6217f0d), **v9.4.3** (drop cap 5rem→2.5rem + post-closing `__prev` CSS parity, 4c52587). Live install + visual verification confirmed all fixes working. Patch count: **3/7**. Gate commit: [this commit]. |
| **v9.5.0 brainstorm-checkpoint** | 🔒 BLOCKED on v5.0.x gate (v9.4.x gate ✓ passed 2026-05-26). NOTE: with caps dropped 2026-05-26, v5.0.0 may now happen later than originally planned (or never if no breaking changes accumulate). If the wait for v5.0.0 extends indefinitely, the v9.5.0 BC's sync constraint can be relaxed (theme proceeds with anticipated rather than published plugin reality) — that decision belongs to a future brainstorm. | Inputs: [parallel-major-brainstorm](~/.claude/projects/-Users-juanlentino-Projects-signal-and-noise/memory/project_parallel_major_brainstorm.md) memory directive, v5.0.0's actual published changes. **Backbone:** cross-package listener tests (theme side of 4 filters) + theme API audit producing `v10.0.0-scope.md`. **Features TBD** at checkpoint — strong candidates from prior brainstorms: cluster transitions (thrice-deferred), template variants (long-form essay vs short-note distinction), OpenType ligatures, mimestream-style release notes infrastructure (if not landed in v5.0.0). |
| **v9.5.0 ship** | 🔒 BLOCKED on v9.5.0 BC | Prep-for-major + emergent features. Likely 400-800 LOC depending on feature scope. |
| **v9.5.x post-ship cycle** | 🔒 BLOCKED on v9.5.0 ship | Same template. |
| **v10.0.0 brainstorm-checkpoint** | 🔒 BLOCKED on v9.5.x gate (and on actual breaking changes warranting a theme major) | Inputs: the v10.0.0-scope.md produced by v9.5.0 audit (if v9.5.0 happens). **Backbone:** whatever the audit identified as v10.0.0-disposition (RENAME/REMOVE/SCHEMA-CHANGE). With caps dropped, this BC may produce "no v10.0.0 yet" as a legitimate outcome — same logic as v5.0.0 BC. |
| **v10.0.0 ship** | 🔒 BLOCKED on v10.0.0 BC (and on actual breaking changes) | Major version. Caps dropped 2026-05-26 — happens only when actual breaking changes accumulate. v9.5.0 / v9.6.0 / etc. are valid until then. |
| **v10.0.x post-ship cycle** | 🔒 BLOCKED on v10.0.0 ship | Terminal cycle — completion = THEME DONE for this roadmap. |

**Status legend:**
- ✓ SHIPPED — phase completed
- ⏳ IN PROGRESS — phase actively running
- 🔒 BLOCKED — phase has a gating dependency unmet

**Update protocol:** when a phase ships or its cycle gate passes, this table updates in the same commit (or the immediately following commit). Status transitions are part of the Gate commit; ship transitions are part of the release commit.

---

## 4. Brainstorm-checkpoints inventory

| # | Checkpoint | Trigger | Backbone (locked direction) | Memory-entry inputs | Output |
|---|---|---|---|---|---|
| 1 | **v5.0.0 BC** | After v4.4.x gate passes | 1 option REMOVE + minor counter reset (already specified in [v5.0.0-scope.md](https://github.com/juanlentino/signal-and-noise-tools/blob/main/docs/superpowers/specs/2026-05-26-v5.0.0-scope.md)) | [mimestream-style release notes](~/.claude/projects/-Users-juanlentino-Projects-signal-and-noise/memory/project_mimestream_style_release_notes.md) — consider whether release-notes infrastructure lands here | `docs/superpowers/specs/<date>-v5.0.0-design.md` in plugin repo |
| 2 | **v9.5.0 BC** | After (v5.0.x gate AND v9.4.x gate) both pass | Cross-package listener tests + theme API audit → v10.0.0-scope.md | [parallel-major-brainstorm](~/.claude/projects/-Users-juanlentino-Projects-signal-and-noise/memory/project_parallel_major_brainstorm.md), [mimestream-style release notes](~/.claude/projects/-Users-juanlentino-Projects-signal-and-noise/memory/project_mimestream_style_release_notes.md), v5.0.0's actual published reality | `docs/superpowers/specs/<date>-v9.5.0-design.md` in theme repo |
| 3 | **v10.0.0 BC** | After v9.5.x gate passes | Whatever v9.5.0's audit identifies as v10.0.0-disposition | The v10.0.0-scope.md from v9.5.0's audit | `docs/superpowers/specs/<date>-v10.0.0-design.md` in theme repo |

**Each BC follows the standard brainstorming-skill flow:** explore context → ask clarifying questions → propose approaches → present design → write spec → user review → transition to writing-plans.

**Memory-entry inputs aren't directives — they're considerations.** The BC may decide to defer them. e.g., the v5.0.0 BC may decide that release-notes infrastructure is too big for v5.0.0 and defer to v5.0.x or post-v10.0.0. The decision happens at the checkpoint, not in advance.

---

## 5. Cross-references

**Specs supporting this roadmap:**
- Plugin v5.0.0 scope: [`docs/superpowers/specs/2026-05-26-v5.0.0-scope.md`](https://github.com/juanlentino/signal-and-noise-tools/blob/main/docs/superpowers/specs/2026-05-26-v5.0.0-scope.md) — backbone for v5.0.0 BC
- Plugin v4.4.0 design: [`docs/superpowers/specs/2026-05-26-v4.4.0-cross-package-contracts-and-v5-readiness-design.md`](https://github.com/juanlentino/signal-and-noise-tools/blob/main/docs/superpowers/specs/2026-05-26-v4.4.0-cross-package-contracts-and-v5-readiness-design.md) — locked the 4 cross-package contracts
- Theme v9.4.0 design: [`docs/superpowers/specs/2026-05-26-v9.4.0-typography-polish-design.md`](docs/superpowers/specs/2026-05-26-v9.4.0-typography-polish-design.md)

**Cross-package contract surface:**
- [`docs/WORDPRESS-REFERENCE.md` §10.0](WORDPRESS-REFERENCE.md) — canonical doc for the 4 theme↔plugin filter contracts. v9.5.0's listener-tests work depends on this.

**Memory entries (forward-looking direction):**
- [parallel-major-brainstorm](~/.claude/projects/-Users-juanlentino-Projects-signal-and-noise/memory/project_parallel_major_brainstorm.md) — why v10.0.0 BC pairs with v5.0.0 BC strategically (originally written as the directive; this roadmap supersedes by sequencing v5.0.0 first, but the coordination spirit is preserved via the v9.5.0 cross-lane sync point)
- [mimestream-style release notes](~/.claude/projects/-Users-juanlentino-Projects-signal-and-noise/memory/project_mimestream_style_release_notes.md) — forward direction for release-notes presentation; candidate for one of the BCs

**Handoff context:**
- Most recent: [`docs/superpowers/handoffs/2026-05-26-v4.4.0-and-v9.4.0-shipped.md`](handoffs/2026-05-26-v4.4.0-and-v9.4.0-shipped.md) — cold-start for next session

---

## 6. Anti-scope (what this roadmap explicitly does NOT lock)

- **Calendar dates** — no shipping deadlines. Discipline drives timing, not schedule.
- **Specific feature contents** for v5.0.0, v9.5.0, v10.0.0 beyond locked backbones — emerges at BCs.
- **Patch counts** in each cycle — reactive based on QA findings.
- **Coordination beyond v9.5.0** — v9.5.0 onward, theme lane runs sequentially without further cross-repo sync.
- **What happens after v10.0.0** — out of scope for this roadmap; a new roadmap will be written at v10.0.x gate.

---

## 7. Update protocol for this doc

The roadmap doc is LIVING. It updates at three triggers:

1. **Phase ship** — update §3 status from ⏳ → ✓ with commit/tag references in the same commit as the version-bump release commit.
2. **Cycle gate pass** — update §3 status of the cycle phase from ⏳ → ✓ in the Gate commit itself.
3. **Cross-references added** — when a new spec/plan/handoff is written that this roadmap should point to, update §5 in the same commit.

**Out-of-band updates** (e.g., reframing the lane structure, adding new BCs) require a re-brainstorm session and explicit user approval. The structural sections (§1, §2, §4, §6) shouldn't change without that gate.

---

## 8. One-line summary

**Two lanes, one sync point at v9.5.0. Plugin: v4.4.x cycle → v5.0.0 BC → v5.0.0 → v5.0.x cycle → PLUGIN DONE. Theme: v9.4.x cycle (parallel) → wait for plugin → v9.5.0 BC → v9.5.0 → v9.5.x cycle → v10.0.0 BC → v10.0.0 → v10.0.x cycle → THEME DONE. Every minor + major runs through a brainstorm-checkpoint that produces a per-phase spec doc + post-ship cycle (QA → Bugfix → UI/UX → Gate). v5.0.0 and v10.0.0 are both expected to be minimal-breakage majors unless emergent features at their respective BCs change that.**
