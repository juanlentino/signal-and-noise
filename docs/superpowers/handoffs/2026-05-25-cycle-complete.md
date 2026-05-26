# Handoff — 2026-05-25 (cycle complete; both repos at MAX cap)

**Why this exists:** the session that spanned v4.1.2 → v4.1.7 (plugin) + v9.1.7 (theme) + WP-REF docs landed every queued audit residual + every plugin absorption roadmap residual. Both repos are now at maximum patch cap (7/7) — any next change in either rolls a MINOR. This handoff captures the cycle state, the discipline lessons that landed mid-cycle, and the realistic next-session options.

---

## TL;DR

- **Plugin absorption roadmap:** fully closed (15 phases + 5 residuals shipped).
- **2026-05-25 audit:** 42 of 50 findings shipped; 8 in Tier C deferred indefinitely per original classifications.
- **Caps:** both repos at 7/7 patches. Next change in either repo = minor rollover (v4.2.0 plugin OR v9.2.0 theme).
- **Open work:** none queued. Next session needs to scope a deliberate minor or close.
- **Live verification still required:** the v4.1.7 plugin install lands the v4.1.5 self-heal + the Recent Deploys backfill machinery — confirm dashboard shows v4.1.7 after wp-admin install (the test-only ship doesn't exercise the fix again; the prior v4.1.5 ship is what proved the pattern).

---

## What shipped this cycle (chronological)

| Tag / commit | Repo | Headline |
|---|---|---|
| `v4.1.2` (`7b2fa32`) | plugin | U-03 modal/confirm chrome → CSS class catalog (~50 inline-style strings removed) |
| `v4.1.3` (`503b23a`) | plugin | B-11 plugin abilities split (1660 → 55 LOC + 8 feature files) |
| `v9.1.7` (`b832868`) | theme | B-11 theme abilities split (1814 → 52 LOC + 5 feature files) ⚠️ LAST patch in v9.1.x |
| `v4.1.4` (`b616416`) | plugin | Recent Deploys panel — wp-admin install logging (had self-obs gap; user reported as bug) |
| `v4.1.5` (`c26c7c4`) | plugin | Self-bootstrap fix — admin_init version-check + autoloaded sentinel for hot-path dedupe |
| `v4.1.6` (`cc01f33`) | plugin | Tier B audit batch (D-10, D-11, D-12, D-13, U-13, U-15) — D-11 caught a silent 5-month regression |
| `v4.1.7` (`3db21b7`) | plugin | Test catch-up for v3.8.0+ IA architecture (orphan commits) ⚠️ LAST patch in v4.1.x |
| `095b174` | theme | WP-REF docs append #37 (install-hook self-obs) + #38 (strpos IA-reorg trap) — no version bump |

### Net code shipped

~2,800 LOC across the cycle (plugin abilities split alone netted +249 LOC of docblock overhead; modal CSS extract netted -55 JS / +286 CSS; the rest was pure refactor with net-zero or small net-positive deltas).

---

## Audit closure inventory

50-finding audit at `signal-and-noise-tools/.planning/audit-2026-05-25/`:

| Tier | Findings | Ship status |
|---|---|---|
| A (high-value) | U-03, B-11 | ✅ shipped v4.1.2, v4.1.3, v9.1.7 |
| B (quick wins) | D-10, D-11, D-12, D-13, U-13, U-15 | ✅ shipped v4.1.6 |
| C (design-decision dependent) | D-02, D-06, D-09, B-06, B-07, U-05, U-11, U-12 | ⏸ Deferred indefinitely per original tier classifications |
| D (already covered) | X-09, X-10 | ✅ shipped in P6 doc work pre-cycle |

**Tier C inventory** (for future reference):

- **D-02** `sn_admin_pages()` legacy table maintained alongside canonical `sn_admin_top_tabs()` — needs migration plan + smoke test (legacy URL bookmarks like `?page=sn-login` would 404-silent on POST otherwise).
- **D-06** Direct `get_option('sn_settings')` bypassing `sn_setting()` accessor — latent cache bug, no current consequences.
- **D-09** Drift detect + suggest prompts not cross-referenced — pure docs, 2 comments.
- **B-06** Hook side-effect on `wp_update_post` after drift splice — acknowledged design limitation, not a bug.
- **B-07** PHP 8.0 `const` at file scope future-fragility — stylistic note.
- **U-05** Insights Dismiss form pattern uses inline `display:inline-block` overriding `.sn-fieldset-actions` — needs design decision (dedicated `.sn-fieldset-actions--inline` modifier vs restructured form wrapper).
- **U-11** Cron filter input inline-styled — same as U-05 (needs design decision).
- **U-12** Audit-log `.sn-audit-card` duplicates shared `.sn-state-card` — refactor with visual regression potential.

**Tier C deferrals are intentional, not technical debt.** Each was classified by the audit author as "needs design decision" or "acknowledged limitation, not bug." If a future session wants to act on any of these, the audit doc has full context.

---

## Plugin absorption roadmap closure

15 phases from `docs/superpowers/specs/2026-05-16-plugin-absorption-roadmap.md`, all closed:

| Phase | Title | Status | Tag |
|---|---|---|---|
| 6 | OG card / TSF diagnostic | ✅ | plugin v1.4.1 |
| 7 | WP 7.0 upgrade + AI provider | ✅ | (infra) |
| 8 | wps-hide-login absorption | ✅ | plugin v1.5.0 |
| 9 | Deploy status surface | ✅ | plugin v1.14.0 (as tab, not widget — per user preference memory) |
| 10 | SEO foundation | ✅ | plugin v1.6.0 |
| 11 | SEO schema + admin UI | ✅ | plugin v1.7.0 + v1.10.0 |
| 12 | AI-assisted SEO | ✅ | plugin v1.16.0 |
| 13 | TSF cutover | ✅ | plugin v2.0.0 |
| 14 | Abilities API registration | ✅ EXPANDED | plugin v2.0.4+ |
| 15 | Net-new features (API monitor, webhooks, cron, health) | ✅ | various |
| 16+ | AI features (excerpt, OG title, RAG-lite chat) | ✅ | plugin v2.4.0+ |

Plus the 5 reconciliation residuals (all resolved):

1. ✅ Login hardening audit log — shipped v3.8.3
2. ✅ AI-assisted content-health fixes — shipped v4.0.0–v4.1.0 (massively over-scope: alt-text + inline-alt + drift + orphan)
3. ✅ Native Breadcrumbs adoption — verified no-action (zero breadcrumb refs in theme templates; JSON-LD-only emission is correct architecture)
4. ✅ GSC sitemap submission — user-confirmed submitted
5. ✅ WORDPRESS-REFERENCE.md gotchas — appended #37 + #38 in commit `095b174`

---

## Discipline lessons codified mid-cycle

These memory entries were created or reinforced as direct consequences of incidents in this cycle. The next session should treat them as load-bearing:

1. **[feedback_skills_plugins_docs_always.md](../../../../../memory/feedback_skills_plugins_docs_always.md)** — HARD RULE reinforced after the v4.1.4 self-observation bug. EVERY coding task: invoke relevant superpowers skill + read full source + read official docs. v4.1.4 was shipped on the assumption that `upgrader_process_complete` would observe its own install — a 30-second read of `WP_Upgrader::run()` source would have falsified it.
2. **[feedback_install_hooks_cannot_self_observe.md](../../../../../memory/feedback_install_hooks_cannot_self_observe.md)** — NEW. The general pattern: WP install-time hooks fire in the same PHP request as the install; the OLD code is what's in memory. Pair every install hook with an `admin_init` version-check + autoloaded sentinel for the hot path.
3. **[feedback_do_not_flip_recommendation.md](../../../../../memory/feedback_do_not_flip_recommendation.md)** — reinforced by my consistency across the cycle (handoff plan stayed locked once recommended).
4. **WORDPRESS-REFERENCE.md #37 + #38** — promoted the install-hook self-observation lesson + the strpos IA-reorg trap to the canonical reference doc.

The session that ships v4.1.4-without-skills → debugs the failure live with the user → re-ships v4.1.5 with skills invoked is the practical proof of why the hard rule exists.

---

## Next-session entry points (in recommended order)

The realistic options when both repos are at MAX patch cap. Each requires a brainstorming pass before any code lands.

### A. Plugin v4.2.0 minor — scope undefined (NEEDS BRAINSTORM)

Plugin's last shipped feature was v4.1.0's orphan-media Suggest+Apply (2026-05-25). Plausible v4.2.0 themes the next session could explore (these are **prompts for brainstorming**, not commitments):

- **Color-drift Health check** — listed in v4.0.x roadmap doc as a "cheap zero-AI candidate" for v4.1.0 that got punted. Detect images whose dominant colors fall outside the brand palette per `theme.json color.palette`. Plain SQL + GD library, no AI calls.
- **Block-pattern usage analytics** — also from the v4.0.x roadmap. Track which patterns get inserted into which post types via `transition_post_status` hook → emit a usage panel in the Insights tab.
- **AI-assisted health check expansion** — the deferred "stale posts" check from the original v4.0.0 brainstorm (dropped mid-flight as evergreen-site mismatch — see handoff `2026-05-25-v4.1.0-brainstorm-paused.md`). Worth revisiting if the user's content cadence has changed.
- **Audit log retention controls** — v3.8.3's 90-day hard-coded retention could become a Setting → "Retain audit log for [30 / 60 / 90 / 180 / 365] days" with a manual prune button.

**Brainstorming skill mandatory at session start.** Per the global HARD-GATE: no implementation until user approves a design.

### B. Theme v9.2.0 minor — scope undefined (NEEDS BRAINSTORM)

Theme's last feature ship was v9.1.x maintenance cycle. v9.2.0 candidates:

- **Native View Transitions integration** — partly done in v9.0.0 (added support). Could extend to cross-page transitions for the /notes catalog browse experience.
- **Color palette extension** — currently brutalist white-first with blood-red accents. Adding a small set of accent variants for /notes pillar-specific theming could ship with the existing pillar metadata.
- **Pattern library expansion** — only 2 patterns exist (`hero-dossier`, `section-constrained`). The theme abilities registration code (`abilities-content.php` post-v9.1.7) registers a `list-block-patterns` ability — adding 3-5 more patterns would make that ability actually useful.

### C. Continue Tier C work (NOT RECOMMENDED for next session)

Tier C deferrals were design-decision-dependent, not technical debt. If the user wants to revisit any of the 8 items, brainstorm-first. But the audit explicitly classified these as "acknowledged limitations or pending design decisions" — they don't have user-visible failures to fix.

### D. Documentation deep-pass

A complete `docs/WORDPRESS-REFERENCE.md` rewrite or `docs/PROJECT.md` consolidation. Useful before onboarding anyone else to the codebase (currently there's just Juan). Low priority; do only if other tracks are blocked.

---

## State invariants the next session can rely on

Verified during this handoff write:

1. Plugin: `main` branch is at `3db21b7` (v4.1.7), pushed to origin.
2. Theme: `claude/nice-goldstine-063551` worktree at `095b174` (WP-REF docs), pushed to origin/main.
3. Both working trees are CLEAN (no uncommitted changes, no untracked files relevant to this scope).
4. All tags pushed: plugin `v4.1.2`–`v4.1.7`, theme `v9.1.7`. Local + remote match.
5. Test suites green: `tests/abilities-integration.php` → 157/0, `tests/health-checks.php` → 76/0, plus the orphan tests (now committed): `tests/admin-tabs.php` → 189/0, `tests/theme-ability-commands.php` → 37/0.
6. Live site state (from the v4.1.4 / v4.1.5 ship verification): Plugin v4.1.7 should land via wp-admin Updates UI; the Recent Deploys panel will populate via `admin_init` version-check on the first admin page load post-install.

---

## Cold-start resume recipe

```bash
# 1. From any directory, navigate to the theme worktree
cd /Users/juanlentino/Projects/signal-and-noise/.claude/worktrees/nice-goldstine-063551

# 2. Read THIS handoff first
cat docs/superpowers/handoffs/2026-05-25-cycle-complete.md

# 3. Verify both repos still clean (mtime may have drifted; HEAD shouldn't)
git status -sb && git log --oneline -3
cd /Users/juanlentino/Projects/signal-and-noise-tools && git status -sb && git log --oneline -3

# 4. Verify tags + caps
git tag --list "v4.*" --sort=-v:refname | head -3      # Expect: v4.1.7, v4.1.6, v4.1.5
cd /Users/juanlentino/Projects/signal-and-noise/.claude/worktrees/nice-goldstine-063551
git tag --list "v9.*" --sort=-v:refname | head -3      # Expect: v9.1.7, v9.1.6, v9.1.5

# 5. Run the test suites to confirm baseline
cd /Users/juanlentino/Projects/signal-and-noise-tools
php tests/abilities-integration.php 2>&1 | tail -3     # Expect: 157/0
php tests/health-checks.php 2>&1 | tail -3             # Expect: 76/0
php tests/admin-tabs.php 2>&1 | tail -3                # Expect: 189/0
php tests/theme-ability-commands.php 2>&1 | tail -3    # Expect: 37/0

# 6. Confirm both repos are at MAX patch cap. The next change rolls a minor.
grep "^ \* Version:" /Users/juanlentino/Projects/signal-and-noise-tools/signal-and-noise-tools.php  # 4.1.7
grep "^Version:" /Users/juanlentino/Projects/signal-and-noise/.claude/worktrees/nice-goldstine-063551/style.css  # 9.1.7
```

If any of steps 3-6 surface unexpected state, that's the new bug to debug — invoke `superpowers:systematic-debugging` before anything else.

---

## One-line summary

**Cycle ends with both repos at MAX patch cap, plugin absorption roadmap fully closed, 42/50 audit findings shipped (8 in Tier C deferred per design), 2 new WORDPRESS-REFERENCE.md gotchas codified from this cycle's incidents (install-hook self-observation + strpos IA-reorg trap), and no queued work. Next session needs to brainstorm v4.2.0 (plugin) OR v9.2.0 (theme) — both are clean minor rollovers waiting on a deliberate scope conversation. Invoke `superpowers:brainstorming` BEFORE any code lands.**
