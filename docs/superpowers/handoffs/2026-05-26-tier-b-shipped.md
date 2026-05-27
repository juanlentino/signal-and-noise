# Handoff — 2026-05-26 — Tier B micro-cycle shipped (Plugin v4.4.5 + Theme v9.4.5)

**Why this exists:** the prior handoff ([`2026-05-26-audits-c-d-cycle-shipped.md`](2026-05-26-audits-c-d-cycle-shipped.md)) deferred 6 Tier B items to a future session. User said "keep going with what's left while I install everything" — so this micro-cycle picked up the 4 actionable Tier B items autonomously (3 fixed, 1 explicitly deferred with rationale, 1 reaffirmed as user action, 1 reaffirmed as v5.0.0 cleanup).

---

## TL;DR

- **Plugin v4.4.5 shipped.** PA-10 (`social_same_as[]` per-input `aria-label`) + a test catch-up for the v4.4.4 docblock re-framing that broke `tests/legacy-url-redirect.php` Test 5 — caught by re-running tests this cycle.
- **Theme v9.4.5 shipped.** PA-08 (`loading="lazy" decoding="async"` on 7 static-template `<img>` tags; live HTML probe confirmed WP core was NOT injecting them) + PA-11 (footnote popover keyboard parity via `focusin`/`focusout` listeners).
- **PA-05 explicitly deferred** with rationale: the audit conditioned it on "if Breeze is ever swapped" — premature to fix.
- **HYG-08 option 2 reaffirmed deferred** to v5.0.0 cleanup pass (Audit E U-01's original disposition).
- **PA-01 (heading hierarchy h3→h2) still outstanding** — user action (content sweep). Recipe in the prior handoff §3.
- **Two cycle stumbles** documented honestly in §3 below — both recoverable, both worth recording as discipline lessons.

---

## Section 1: What shipped this micro-cycle

### Plugin (`signal-and-noise-tools`)

| Version | Commit | Notes |
|---|---|---|
| **v4.4.5** | `e1d8939` | PA-10 social-input `aria-label` + test catch-up after v4.4.4's `@deprecated 4.2.0` → `@internal` framing change broke `tests/legacy-url-redirect.php` Test 5. Test now accepts either framing. |

### Theme (`signal-and-noise`)

| Version | Commit | Notes |
|---|---|---|
| **v9.4.5** | `358c16d` | PA-08 (`loading="lazy" decoding="async"` on 7 imgs) + PA-11 (footnote keyboard parity). |
| docs follow-up | `0ebac9d` | CHANGELOG entry added separately — see §3 for the recovery story. |

---

## Section 2: Tier B disposition (all 6 items resolved)

| ID | Status | What happened |
|---|---|---|
| PA-08 | ✅ Shipped in v9.4.5 | Live probe confirmed missing attrs on 7 imgs (/about/ portrait + 6 /services/ cards); added explicit `loading="lazy" decoding="async"` |
| PA-10 | ✅ Shipped in v4.4.5 | Server-rendered repeating social inputs now match the JS-added rows' `aria-label="Profile URL"` |
| PA-11 | ✅ Shipped in v9.4.5 | Footnote popover opens on `focusin`, dismisses on `focusout` — keyboard users now get parity with hover |
| PA-05 | ⏸ Explicitly deferred | Audit's own framing: *"consider as v9.5.x enhancement if Breeze is ever swapped"*. Current state (Breeze active, CSS concatenated) is fine. The tradeoff (visible FOUC during the swap-to-deferred-stylesheets path) is a UX regression for hypothetical robustness. Re-evaluate if Breeze ever changes. |
| HYG-08 option 2 | ⏸ Defer to v5.0.0 | Reaffirms Audit E U-01's original "defer to v5.0.0 cleanup" call. v4.4.4 took option 1 (rename `@deprecated 4.2.0` → `@internal`), which is the right short-term framing. The full `sn_admin_pages()` refactor (replace allowlist with `array_column(sn_admin_top_tabs(), 'slug')`) is a v5.0.0 cleanup. |
| PA-01 | ⏳ User action outstanding | Content-side h3→h2 sweep across published notes. SQL one-liner in prior handoff §3. |

---

## Section 3: Cycle stumbles + lessons

Two real discipline lapses landed in this micro-cycle. Both recovered, both worth recording.

### Stumble 1: v4.4.4 shipped with an undetected test failure

**What happened:** v4.4.4's HYG-08 fix changed `sn_admin_pages()`'s docblock from `@deprecated 4.2.0` → `@internal`. `tests/legacy-url-redirect.php` had a Test 5 that grepped for `/@deprecated\s+4\.2\.0/` as a regression guard. The test broke at v4.4.4 edit time. **Plugin tests were not re-run between the edit and the `git tag` step.** The previous handoff's "888 assertions / 21 plugin suites — all green" claim was inaccurate (it was 887 pass + 1 fail).

**Detection:** caught this cycle when I ran the test suite *before* shipping v4.4.5 (per verification-before-completion skill discipline).

**Recovery:** updated the test in v4.4.5 to accept either `@internal` or `@deprecated` framing (forward-compatible with v5.0.0 re-deprecation). v4.4.5 ships green: 888 / all pass / verified.

**Lesson:** version-bump commits must include a test run between the last edit and the `git tag` step. Even for "small" patches (docblock-only edits, header-only edits) where the change set looks behaviorally inert — tests sometimes have grep-style assertions on the exact text being changed. The verification-before-completion skill applies uniformly; not just to functional changes.

### Stumble 2: v9.4.5 CHANGELOG dropped from the tagged commit

**What happened:** the Edit operation to add the v9.4.5 entry to theme CHANGELOG looked for the v9.4.4 heading anchor "Turnstile via script_loader_tag" but the actual text was "Turnstile **strip** via script_loader_tag" — a single missed word. The Edit failed silently; the downstream Bash sequence (`git add CHANGELOG.md` + commit + tag + push) proceeded under the assumption that all 5 files were modified. `git add` of an unchanged file is a no-op, so the commit captured 4 files instead of 5. v9.4.5 tag landed with no CHANGELOG entry.

**Detection:** caught it immediately because the Edit returned an error before the Bash ran — but I missed that the Bash succeeded anyway because git silently treats no-op stages as fine.

**Recovery:** added a follow-on CHANGELOG-only commit (`0ebac9d`) with the v9.4.5 entry. Per CLAUDE.md "CHANGELOG-only commits don't bump" rule, no version change. Per CLAUDE.md "Never run destructive git commands" rule, NOT re-tagging or force-pushing. The cost: the v9.4.5 tag itself doesn't include the entry — but the entry is now on `main`, queryable, indexed.

**Lesson:** when chaining "Edit + Bash" across multiple files in a single shipment, check `git status` between the last Edit and the first `git add` to verify the staged set matches the planned set. Or use a single Bash that does `git add` per file with explicit names (which catches "unchanged file" via the `add`'s output) rather than `git add file1 file2 file3`.

---

## Section 4: Current strategic state

| Repo | Current | Patches in v4.4.x / v9.4.x | Open backlog |
|---|---|---|---|
| Plugin (`signal-and-noise-tools`) | **v4.4.5** | 5 (v4.4.1, v4.4.2, v4.4.3, v4.4.4, v4.4.5) | None from Audit C/D cycle. HYG-08 option 2 → v5.0.0 |
| Theme (`signal-and-noise`) | **v9.4.5** | 5 (v9.4.1, v9.4.2, v9.4.3, v9.4.4, v9.4.5) | PA-05 contingent on Breeze swap. PA-01 user action. |

Caps dropped 2026-05-26 — no rollover pressure. Both repos can keep shipping v4.4.x / v9.4.x or roll v4.5.0 / v9.5.0 as appropriate breaking-change scope emerges.

The audit-to-ship loop now has 3 consecutive cycles (v4.4.x → v9.4.x cycle audit + Audits C+D synthesis + Tier B follow-on). Pattern is stable.

---

## Section 5: User actions owed (consolidated)

Install:
- ⏳ Plugin v4.4.5 (replaces v4.4.4) via wp-admin → Updates
- ⏳ Theme v9.4.5 (replaces v9.4.4) via wp-admin → Updates

Verification after install:
- Plugin: Identity → Social settings, Tab through Profile URL rows — VoiceOver/NVDA should announce each as "Profile URL"
- Theme: view source on `/about/` + `/services/`, every `<img>` should have `loading="lazy" decoding="async"` (except header logo which stays eager)
- Theme: on a `/notes/<slug>/` post with footnotes, Tab to `<sup>` anchor — popover should open on focus, dismiss on blur

Carried over (still outstanding):
- ⏳ PA-01 — h3→h2 content sweep across published notes (WCAG 1.3.1 Level A fix). SQL one-liner in prior handoff §3.
- ⏳ Aikido Security check for prior exploitation of `tests/contracts-smoke.php` during the v4.4.0 → v4.4.2 exposure window

---

## Section 6: Cold-start resume recipe

```bash
# 1. Navigate to theme worktree
cd /Users/juanlentino/projects/signal-and-noise/.claude/worktrees/nice-goldstine-063551

# 2. Read THIS handoff (you are here)
cat docs/superpowers/handoffs/2026-05-26-tier-b-shipped.md

# 3. Read the prior cycle handoff for context
cat docs/superpowers/handoffs/2026-05-26-audits-c-d-cycle-shipped.md

# 4. Read the cycle audit synthesis
cat docs/superpowers/specs/2026-05-26-audits-c-d-cycle-findings.md

# 5. Verify state
cd /Users/juanlentino/Projects/signal-and-noise-tools
git log --oneline -3   # Expect: v4.4.5, v4.4.4, v4.4.3
grep "^ \* Version:" signal-and-noise-tools.php   # Expect: 4.4.5

cd /Users/juanlentino/projects/signal-and-noise/.claude/worktrees/nice-goldstine-063551
git log --oneline -5   # Expect: docs follow-up, v9.4.5, audits-c-d-handoff, v9.4.4, docs commits
grep "^Version:" style.css   # Expect: 9.4.5

# 6. Confirm tests baseline (BOTH repos this time — discipline)
cd /Users/juanlentino/Projects/signal-and-noise-tools
for f in tests/*.php; do echo "=== $f ==="; php "$f" 2>&1 | tail -1; done
# Expect: 21 swept suites + contracts-smoke (manual), 888 assertions, 0 failed — NOW verified

cd /Users/juanlentino/projects/signal-and-noise/.claude/worktrees/nice-goldstine-063551
for f in tests/*.php; do echo "=== $f ==="; php "$f" 2>&1 | tail -1; done
# Expect: 5 suites, 303 assertions, 0 failed

# 7. Pick a thread
#    OPTION 1: PA-01 content sweep (h3 → h2 in published notes)
#    OPTION 2: v5.0.0 brainstorm-checkpoint (post-cap-drop, no longer forced)
#    OPTION 3: v4.5.0 / v9.5.0 net-new capability work
#    OPTION 4: Revisit deferred PA-05 if Breeze swap is ever on the table
```

---

## One-line summary

**Tier B micro-cycle picked up 4 actionable items from the deferred-backlog: PA-08 confirmed real via live probe + fixed (7 imgs got lazy/async), PA-10 fixed (server-rendered social inputs got aria-label parity with JS-added rows), PA-11 fixed (footnote popover now opens on keyboard focus). PA-05 explicitly deferred with audit-justified rationale (contingent on Breeze swap). HYG-08 option 2 reaffirmed as v5.0.0 work. Two cycle stumbles caught and recovered: v4.4.4 shipped an undetected test failure (caught + fixed in v4.4.5 with honest CHANGELOG disclosure); v9.4.5 CHANGELOG dropped from the tagged commit via a string-mismatch (recovered with a follow-on docs-only commit). Both lessons recorded. Plugin at v4.4.5, theme at v9.4.5, both green: 888 + 303 assertions verified. PA-01 (h3→h2 content sweep) still outstanding as user action.**
