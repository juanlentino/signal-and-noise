# Handoff — 2026-05-26 — Session close — Audits C + D deferred to next clean-slate

**Why this exists:** the session that shipped 10 releases across both repos + the full QA discipline framework (roadmap, brainstorm-checkpoints, post-ship cycle template) + a deep audit (Audits A/B/E) + cap removal. Next session's job: Audits C (project hygiene) and D (perf + a11y) on a clean context slate, then decide what to patch.

---

## TL;DR

- **10 releases shipped this session.** Plugin: v4.3.1 → v4.4.0 → v4.4.1 → **v4.4.2 (URGENT security)** → v4.4.3. Theme: v9.3.0 → v9.4.0 → v9.4.1 → v9.4.2 → v9.4.3.
- **Critical security exposure caught + closed.** The v4.4.x post-ship audit surfaced a remote unauthenticated destructive-action URL (`tests/contracts-smoke.php`) that the original "narrow QA" missed. Closed in v4.4.2.
- **Roadmap discipline framework now exists.** Per-phase brainstorm-checkpoints + post-ship cycle template (QA → Bugfix → UI/UX → Gate) + cross-package coordination + living roadmap doc.
- **Caps DROPPED 2026-05-26.** v5.0.0 + v10.0.0 no longer forced by counter math; happen when actual breaking changes accumulate. v4.4.x cycle + v9.4.x cycle both gated closed.
- **Audits C (project hygiene) + D (perf + a11y) deferred** at user direction for context budget reasons; next session picks them up first.

---

## Section 1: What shipped this session

### Plugin (`signal-and-noise-tools`)

| Version | Commit | Notes |
|---|---|---|
| v4.3.1 | `1decafa` | 6-item code-review polish sweep from v4.3.0 ship |
| v4.4.0 | `79ea06f` | Cross-package contracts E2E + v5.0.0 readiness pass (the LAST minor under the OLD cap rule) |
| v4.4.1 | `cc881fd` | Docs tightening (3 items from v4.4.x narrow QA) |
| **v4.4.2** | **`0dd786f`** | **URGENT: CLI guards on 27 test files closing remote destructive-action exposure** |
| v4.4.3 | `2ad81d0` | Bundled non-urgent audit fixes (JS dispatcher for v4.3.0, login allowlist, schema language, TSF gating, inline-style consolidation) |

### Theme (`signal-and-noise`)

| Version | Commit | Notes |
|---|---|---|
| v9.3.0 | (start-of-session ship) | Long-form post layout (drop caps + footnotes + sidenotes + frontmatter spec card) |
| v9.4.0 | `a9a6d23` | Typography polish (justified + hyphenation + hanging punctuation) |
| v9.4.1 | `083b489` | Sidenote `:not(.sn-sidenote)` regression fix |
| v9.4.2 | `6217f0d` | Test file CLI guards + Previous post nav link |
| v9.4.3 | `4c52587` | Drop cap toned down (5rem→2.5rem) + post-closing `__prev` CSS parity |

### Docs / strategic artifacts

| Doc | Commit | Purpose |
|---|---|---|
| Audit findings | `0b4c94b` | Unified findings from Audits A/B/E with prioritized patches |
| Roadmap | `1f24cfe` | Strategic path to v5.0.0 + v10.0.0 (now reframed post-cap-drop) |
| Plugin v4.4.x gate | `a9c71bc` | First Gate commit (premature, before audit) |
| Final re-gate | `fdd0cb7` | Both v4.4.x + v9.4.x cycles closed after audit + patches |
| Cap removal | `aa1c9b9` | Dropped 7/minor + 5/major caps; matches global "no caps" rule |
| This handoff | (next commit) | Session-close + next-session pickup |

---

## Section 2: Next session — Audits C + D

Both audits were dispatched-and-deferred this session at the user's direction (context budget). Each is now waiting to run in a clean-slate context.

### Audit C — Project hygiene (~1 deep-audit subagent dispatch)

**Scope** (verbatim from audit findings doc §2):

- CLAUDE.md accuracy vs actual project state — is anything stale?
- Stale handoffs / memory entries contradicting each other (there are ~30 memory entries now — worth a dedup pass)
- TODO/FIXME/XXX markers across both repos
- Orphaned files (backup files, swap files, leftover `/tmp/` references)
- Dead code paths (functions defined but never called)
- WordPress core + PHP version compat (we ship "Requires PHP: 8.0" — still accurate?)
- Open GitHub issues + PRs (gh issue list, gh pr list)

**Specific candidate findings to verify**:

- Memory entry `feedback_no_dashboard_widgets` contradicts existing Plausible widgets + admin bar (already flagged in Audit E as OBS-01)
- `abilities-registration.php` docblock says "28 abilities + 5 categories" but actual is 30 (already flagged in Audit B as OBS-3)
- Memory entry hygiene generally — 32 entries listed in MEMORY.md as of this writing

**Approach:**

Use a single opus subagent. Same shape as Audits A/B/E from this session — 10ish verification axes, categorized findings (bug / UI-UX / observation / pass), structured report.

### Audit D — Performance + accessibility (~1 deep-audit subagent dispatch)

**Scope** (verbatim from audit findings doc §2):

- Page Speed Insights / Core Web Vitals (LCP, FID, CLS) for juanlentino.com homepage + sample /notes post
- JS bundle size analysis
- CSS bundle size (`assets/css/critical.css` is now 902 LOC after v9.4.x work)
- Image optimization (lazy load, srcset)
- Render-blocking resources
- WCAG 2.1 AA scan of the live homepage + sample /notes post
- Color contrast (brutalist blood-red `#e00404` vs bone-white — high-contrast concerning)
- Keyboard navigation
- Screen reader compat
- ARIA usage
- Heading hierarchy
- Form labels

**Tooling caveat:** the sandbox blocked most live HTTP probes during this session's Audits A/B/E. Audit D may face the same constraint — fall back to source-side inspection for what can't be live-probed, document the gaps for browser-side verification.

**Likely findings (educated guesses pre-audit):**

- Color contrast may flag blood-red `#e00404` text on bone backgrounds (or vice versa) as failing WCAG AA's 4.5:1 minimum
- LCP likely fine (server-rendered, no JS frameworks); CLS likely fine (no late-loading content)
- Bundle sizes are small by modern standards (critical.css ~70KB unminified)
- The `inc/page-notes-render.php` inline CSS block (~500 LOC) might be flagged
- Heading hierarchy on /notes posts: post-frontmatter spec card → post-title h1 → section h2s → likely clean

### Synthesis

After both run, synthesize findings into a unified report. Same shape as the v4.4.x + v9.4.x cycle audit doc (`docs/superpowers/specs/2026-05-26-v4.4.x-and-v9.4.x-cycle-audit-findings.md`). Decide:

- Which findings warrant patches now (v4.4.4 / v9.4.4 candidates)?
- Which are observations to file in a backlog?
- Which need updates to project docs (e.g., color contrast → update theme.json or critical.css)?

---

## Section 3: Cap drop implications for next session

Caps were dropped 2026-05-26 ([commit `aa1c9b9`](https://github.com/juanlentino/signal-and-noise/commit/aa1c9b9)). Next session needs to internalize:

- **v5.0.0 and v10.0.0 are no longer forced.** They happen when actual breaking changes per SemVer warrant them.
- **`v5.0.0-scope.md`'s single REMOVE finding (orphaned `sn_login_rewrites_flushed` option) can ship as a v4.4.4 patch whenever convenient** — it's no longer a v5.0.0 backbone.
- **Plugin can keep shipping v4.5.0 / v4.6.0 / etc. indefinitely**; same for theme v9.5.0 / v9.6.0 / etc.
- **The roadmap doc has been updated** to reflect this (§1 coordination model + §3 status table) — but it preserves the SEQUENCING INTENT (v5.0.0 first if/when it happens, then v9.5.0 absorbing its reality).
- **Memory entry `feedback_versioning_patch_cap.md` was rewritten** to capture the new rule + historical context. Don't propose cap rollovers when reasoning about version bumps.

---

## Section 4: Strategic state — both repos

| Repo | Current version | Patch count this minor | Cap (since 2026-05-26) | Next strategic step |
|---|---|---|---|---|
| Plugin | v4.4.3 | 3 patches in v4.4.x | None | v5.0.0 BC unblocked but no longer urgent; can ship more v4.4.x patches OR roll v4.5.0 OR wait |
| Theme | v9.4.3 | 3 patches in v9.4.x | None | v9.5.0 BC currently gated on v5.0.x but caps drop relaxes this; can ship v9.4.x patches OR roll v9.5.0 |

---

## Section 5: User actions owed (carried over from prior handoffs)

These are install-time verifications the user can do whenever convenient. Not blocking for next session's audit work.

- ✅ Plugin v4.4.2 installed + verified URL closure (DONE)
- ✅ Theme v9.4.x installed + visual verification (DONE)
- ⏳ Plugin v4.4.3 install + verify Bug-B2 fix in wp-admin → Health → Opportunities (pattern-adoption Suggest button opens modal)
- ⏳ Plugin v4.4.3 install + check Aikido Security alerts for any prior exploitation of `tests/contracts-smoke.php` URL during the v4.4.0 → v4.4.2 exposure window

---

## Section 6: Cold-start resume recipe

```bash
# 1. Navigate to theme worktree (session-continuity location)
cd /Users/juanlentino/projects/signal-and-noise/.claude/worktrees/nice-goldstine-063551

# 2. Read THIS handoff (you are here)
cat docs/superpowers/handoffs/2026-05-26-session-close-audits-c-d-deferred.md

# 3. Read the audit findings doc — context for what Audits C + D extend
cat docs/superpowers/specs/2026-05-26-v4.4.x-and-v9.4.x-cycle-audit-findings.md

# 4. Read the roadmap — strategic shape
cat docs/superpowers/specs/2026-05-26-roadmap-to-v5-and-v10-design.md

# 5. Verify state (both repos)
cd /Users/juanlentino/Projects/signal-and-noise-tools
git log --oneline -3   # Expect: v4.4.3, v4.4.2, v4.4.1 (top 3)
grep "^ \* Version:" signal-and-noise-tools.php   # Expect: 4.4.3

cd /Users/juanlentino/projects/signal-and-noise/.claude/worktrees/nice-goldstine-063551
git log --oneline -5   # Expect: cap-drop, re-gate, v9.4.3, v9.4.2, v9.4.1
grep "^Version:" style.css   # Expect: 9.4.3

# 6. Confirm tests baseline
cd /Users/juanlentino/Projects/signal-and-noise-tools
for f in tests/*.php; do echo "=== $f ==="; php "$f" 2>&1 | tail -1; done
# Expect: 21 swept suites + contracts-smoke (manual), 888 assertions, 0 failed

cd /Users/juanlentino/projects/signal-and-noise/.claude/worktrees/nice-goldstine-063551
for f in tests/*.php; do echo "=== $f ==="; php "$f" 2>&1 | tail -1; done
# Expect: 5 suites, 303 assertions, 0 failed

# 7. Pick a thread
#    OPTION 1 (recommended): Dispatch Audits C + D as parallel opus subagents
#    OPTION 2: Ship Plugin v4.4.4 (v5.0.0-scope.md's single REMOVE as a normal patch)
#    OPTION 3: v5.0.0 brainstorm-checkpoint (legitimate outcome may be "no v5.0.0 yet")
#    OPTION 4: Plugin v4.5.0 or Theme v9.5.0 if real new capabilities warrant
```

### Recommended dispatch shape for Audits C + D

Same template as Audits A/B/E from this session. Each gets its own opus subagent dispatched in background (parallel — read-only inspection, no file conflicts). Categorized findings (🐛 bug / 🎨 UI-UX / 📋 observation / ✅ pass). Structured report. Synthesize both into a unified doc analogous to `docs/superpowers/specs/2026-05-26-v4.4.x-and-v9.4.x-cycle-audit-findings.md`.

---

## Section 7: Meta-takeaways from this session

### The audit/QA discipline paid for itself

The narrow QA pass after v4.4.0 + v9.4.0 reported GREEN. The deeper audit (Audits A/B/E) — dispatched at user pushback — surfaced 1 CRITICAL + 4 other bugs. The CRITICAL one was a remote unauthenticated destructive-action URL live on the internet. Without the user's "are there really no more bugs?" push, that URL would have stayed exposed until someone hit it.

**Lesson:** when reporting QA verdicts, distinguish between "did the new minor ship correctly per its spec?" (narrow) and "is the project healthy?" (deep). The first is a *patch verification*; the second is a *project audit*. They need different scopes.

### Cap dropping was earned by the audit insight

The v4.4.x audit revealed v5.0.0 was being scoped to "1 REMOVE + counter reset" — major-by-cap-math, not actual breaking change. That's when the cap stopped being discipline scaffolding and became a forcing function for fictional majors. Dropping it was the right call.

**Lesson:** rules that worked at one scale (3-patch cap, 7-patch cap) can become anti-patterns at another scale when better discipline mechanisms emerge (roadmap, audits, post-ship cycles). Periodic willingness to drop the scaffolding is healthy.

### Two-stage subagent review caught things the implementer self-review didn't

Multiple times this session: implementer report said "DONE, clean self-review"; spec or code-quality reviewer found real issues. The v4.4.0 ship-prep implementer missed the require_once that referenced a theme file; the v4.4.0 Task 5 implementer flagged audit outcome as DONE_WITH_CONCERNS (correct framing); the v9.4.2 implementer's color claim ("identical hue, imperceptible difference") on `#dc3232 → #d63638` was slightly inaccurate per the v4.4.3 review.

**Lesson:** subagent self-review is necessary but not sufficient. Two-stage review (spec + quality) catches things self-review misses. Worth the cost on non-trivial work.

---

## One-line summary

**Session shipped 10 releases (5 per repo) + a roadmap framework + deep audit infrastructure + a critical security patch + dropped the version caps after they were caught forcing fictional majors. Next session picks up Audits C (project hygiene) + D (perf + a11y) on a clean context slate, then decides which findings warrant patches. Both cycles are gated closed; both repos are stable; caps are off; v5.0.0 and v10.0.0 are no longer forced.**
