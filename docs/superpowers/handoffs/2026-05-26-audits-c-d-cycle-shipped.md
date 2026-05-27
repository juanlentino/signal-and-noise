# Handoff — 2026-05-26 — Audits C + D cycle shipped (Plugin v4.4.4 + Theme v9.4.4)

**Why this exists:** the previous handoff (`2026-05-26-session-close-audits-c-d-deferred.md`) deferred Audits C + D for context budget reasons. This session picked them up on a clean slate, dispatched both as parallel opus subagents, synthesized findings into a unified tiered plan, applied the Tier C doc-only sweep, and shipped the Tier A code patches as Plugin v4.4.4 + Theme v9.4.4.

---

## TL;DR

- **Both deferred audits ran.** Audit C (project hygiene) returned 14 findings + 7 passes; Audit D (perf + a11y) returned 18 findings + 7 passes. Overall verdict: **🟢 GREEN.** Zero critical issues. WCAG-AA contrast clean across the entire brutalist palette.
- **3 audit specs docs written.** `2026-05-26-audit-c-project-hygiene-findings.md`, `2026-05-26-audit-d-perf-a11y-findings.md`, `2026-05-26-audits-c-d-cycle-findings.md` (synthesis + tiering).
- **Tier C doc-only sweep landed (no version bumps).** CLAUDE.md phase-status + auto-deploy contradictions fixed (HYG-01); theme readme.txt rewritten from a v3.9.5-era state to current (HYG-02); 3 memory entries refreshed (HYG-04 architecture regenerate, HYG-05 dashboard widgets rule scoped to "new", HYG-07 brutalist-in-admin-UI entry indexed).
- **Tier A code patches shipped.** Plugin v4.4.4 closes PA-03 (admin tabs `aria-current`), HYG-06 (abilities docblock 28→30), HYG-08 (`@deprecated`→`@internal` framing), HYG-03 (Tested up to: 7.0 header). Theme v9.4.4 closes PA-07 (Turnstile leak on /notes/ via script_loader_tag), PA-12 (reduced-motion gate on hover transforms), OBS-HYG-02 (Tested up to: 7.0).
- **Tier B + PA-01 deferred.** Content-side WCAG h2-skip fix (PA-01) is user action. Other Tier B items: PA-10 social-input labels, PA-11 footnote popover keyboard parity, PA-05 Breeze-independent CSS, PA-08 verify static-template `<img>` lazy.

---

## Section 1: What shipped this session

### Plugin (`signal-and-noise-tools`)

| Version | Commit | Notes |
|---|---|---|
| **v4.4.4** | `a29a221` | Audit C + D fixes: admin tabs `aria-current` (WCAG 4.1.2), abilities docblock 28→30, `@deprecated 4.2.0`→`@internal` framing, `Tested up to: 7.0` header |

### Theme (`signal-and-noise`)

| Version | Commit | Notes |
|---|---|---|
| (docs commit 1) | `f5e4f73` | 3 audit findings docs added (Audit C, Audit D, synthesis) |
| (docs commit 2) | `ba75f49` | HYG-01 + HYG-02 doc-hygiene (CLAUDE.md + readme.txt) — no version |
| **v9.4.4** | `802e59d` | Audit D fixes: Turnstile strip via `script_loader_tag` (closes /notes/ leak), reduced-motion gate on two hover transforms, `Tested up to: 7.0` |

### Memory (out-of-repo file writes — `~/.claude/projects/.../memory/`)

| File | Change |
|---|---|
| `project_architecture.md` | Full regeneration: v9.4.3 + v4.4.3 → v9.4.4 + v4.4.4, cap-drop framing, Audits A/B/E/C/D cycle context |
| `feedback_no_dashboard_widgets.md` | Rule scoped to "new" surfaces; Plausible widgets + admin bar dropdown explicitly grandfathered |
| `MEMORY.md` | Refreshed line 3 (architecture entry); rephrased line 9 (no_dashboard_widgets); added new line for `feedback_no_brutalist_in_admin_ui.md` (HYG-07 orphan fixed) |

---

## Section 2: Audit findings disposition

All 32 findings (14 C + 18 D) have a verdict. Quick reference:

| Tier | Count | Disposition |
|---|---|---|
| Tier A — Code patches shipped this cycle | 7 | PA-03 + PA-07 + PA-12 in code; HYG-03 + HYG-06 + HYG-08 (option 1) + OBS-HYG-02 as ride-along docblock/header edits |
| Tier C — Doc-only sweep applied | 5 | HYG-01, HYG-02, HYG-04, HYG-05, HYG-07 |
| Tier B — Backlog (defer to future session) | 6 | PA-01 (content fix), PA-10, PA-11, PA-05, PA-08, HYG-08 option 2 |
| Observations / Passes (no action) | 14 | Recorded in audit docs; no patch needed |

Full tiering rationale in [`docs/superpowers/specs/2026-05-26-audits-c-d-cycle-findings.md`](../specs/2026-05-26-audits-c-d-cycle-findings.md) §9.

---

## Section 3: User actions owed

Install + verification:

- ⏳ **Install Plugin v4.4.4** via wp-admin → Dashboard → Updates → "Update plugin" for Signal & Noise Tools. Verify in the Updates UI that the "compatibility unknown" warning is gone (`Tested up to: 7.0` header is now present).
- ⏳ **Install Theme v9.4.4** via wp-admin → Dashboard → Updates → "Update theme" for Signal & Noise. Two visual checks after install:
  1. View source on a `/notes/<slug>/` page — search for `turnstile` and `challenges.cloudflare.com`. Both should return zero results (pre-v9.4.4 returned 2 references).
  2. With macOS System Settings → Accessibility → Display → "Reduce motion" ON, hover a service card on `/services/`. The image should NOT scale (filter + color change only). Hover a button — should NOT translate (only the shadow appears).
- ⏳ **Screen-reader verification** (optional but recommended): Tab through plugin sub-tabs in wp-admin → Signal & Noise with VoiceOver or NVDA. Active sub-tab should now announce as "current page" thanks to PA-03.

Content-side a11y fix (PA-01):

- ⏳ **Heading hierarchy on `/notes/<slug>/` posts.** Sampled post (`/notes/fingerprints-not-name-tags/`) has `<h1>` → `<h3>` directly, skipping `<h2>` (WCAG 1.3.1 Level A categorical fail). Fix is content-side — sweep published notes' body headings from `<h3>` to `<h2>` via SQL one-liner OR editor pass. Suggested SQL:
  ```sql
  UPDATE wp_posts
  SET post_content = REPLACE(
        REPLACE(post_content,
                '<!-- wp:heading {"level":3}',
                '<!-- wp:heading {"level":2}'),
        '<h3 class="wp-block-heading">',
        '<h2 class="wp-block-heading">')
  WHERE post_type='post' AND post_status='publish';
  ```
  Audit recommendation: editor sweep over SQL, since the SQL may catch h3s that were legitimately h3 (sub-section of an h2). User decides.

Carried over from prior handoff (still owed):

- ⏳ Plugin v4.4.3 verify (Bug-B2 pattern-adoption Suggest+Apply functional in UI)
- ⏳ Check Aikido Security alerts for prior exploitation of `tests/contracts-smoke.php` URL during the v4.4.0 → v4.4.2 exposure window

---

## Section 4: Tier B backlog (deferred, candidates for next cycle)

| ID | Title | Path / Trigger |
|---|---|---|
| **PA-10** | Repeating `social_same_as[]` inputs lack per-row labels | `signal-and-noise-tools/inc/admin-page.php:1128-1135` — wrap in `<fieldset><legend>` or add per-row `aria-label`. ~3 LOC. |
| **PA-11** | Footnote popover JS is hover-only (no keyboard equivalent) | `assets/js/footnotes-popover.js` — add focus/blur listeners. ~10 LOC. Progressive-enhancement only; `<sup>` anchor still works without it. |
| **PA-05** | Render-blocking CSS depends on Breeze | `inc/assets-frontend.php:102-160` — add `sn-base/layout/components/forms/responsive` to the `$defer_handles` array for Breeze-independence. Candidate for v9.5.x. |
| **PA-08** | Verify static template `<img>` lazy/async injection | Spot-check rendered HTML on `/about/` and `/services/` for `loading="lazy"` injection by WP core. If missing, add explicit attributes to templates. |
| **HYG-08 option 2** | Full `sn_admin_pages()` refactor (vs. current `@internal` framing) | Audit E originally classified as defer-to-v5.0.0. Reaffirmed in this cycle. |
| **PA-01** (content) | Heading hierarchy fix | Content sweep (user action, see §3 above). |

None blocking. None warrant a forced-version-bump under the no-caps rule.

---

## Section 5: Strategic state — both repos

| Repo | Current version | Patch count this minor | Cap | Next strategic step |
|---|---|---|---|---|
| Plugin | **v4.4.4** | 4 patches in v4.4.x | None (caps dropped 2026-05-26) | v5.0.0 BC unblocked but not forced; can keep shipping v4.4.x or roll v4.5.0 |
| Theme | **v9.4.4** | 4 patches in v9.4.x | None | v9.5.0 BC unblocked; can keep shipping v9.4.x or roll v9.5.0 |

The audit-to-ship loop is now an established pattern (v4.4.x cycle + this v4.4.4/v9.4.4 cycle). Cap drop is paying off — neither version-bump created cap-rollover pressure.

---

## Section 6: Cold-start resume recipe

```bash
# 1. Navigate to theme worktree (session-continuity location)
cd /Users/juanlentino/projects/signal-and-noise/.claude/worktrees/nice-goldstine-063551

# 2. Read THIS handoff (you are here)
cat docs/superpowers/handoffs/2026-05-26-audits-c-d-cycle-shipped.md

# 3. Read the synthesis doc — what was found + the tiering decisions
cat docs/superpowers/specs/2026-05-26-audits-c-d-cycle-findings.md

# 4. Verify state (both repos)
cd /Users/juanlentino/Projects/signal-and-noise-tools
git log --oneline -3   # Expect: v4.4.4, v4.4.3, v4.4.2 (top 3)
grep "^ \* Version:" signal-and-noise-tools.php   # Expect: 4.4.4
grep "^ \* Tested up to:" signal-and-noise-tools.php   # Expect: 7.0 (new in v4.4.4)

cd /Users/juanlentino/projects/signal-and-noise/.claude/worktrees/nice-goldstine-063551
git log --oneline -5   # Expect: v9.4.4 + docs commits + cap-drop + re-gate + v9.4.3
grep "^Version:" style.css   # Expect: 9.4.4
grep "^Tested up to:" style.css   # Expect: 7.0

# 5. Confirm tests baseline
cd /Users/juanlentino/Projects/signal-and-noise-tools
for f in tests/*.php; do echo "=== $f ==="; php "$f" 2>&1 | tail -1; done
# Expect: 21 swept suites + contracts-smoke (manual), 888 assertions, 0 failed

cd /Users/juanlentino/projects/signal-and-noise/.claude/worktrees/nice-goldstine-063551
for f in tests/*.php; do echo "=== $f ==="; php "$f" 2>&1 | tail -1; done
# Expect: 5 suites, 303 assertions, 0 failed

# 6. Pick a thread
#    OPTION 1: User action sweep — install v4.4.4 + v9.4.4, run heading-hierarchy SQL/editor pass for PA-01
#    OPTION 2: Pick a Tier B item (PA-10 / PA-11 / PA-05 / PA-08) and ship as v4.4.5 / v9.4.5
#    OPTION 3: v5.0.0 brainstorm-checkpoint (legitimate outcome may be "no v5.0.0 yet" given the cap drop)
#    OPTION 4: Net-new minor work (v4.5.0 or v9.5.0) if real new capabilities warrant
```

---

## Section 7: Meta-takeaways from this session

### The audit-to-ship loop is now polished

This is the second consecutive session running this loop:
1. Dispatch deep audits as parallel read-only opus subagents
2. Synthesize findings into a tiered unified doc
3. Apply Tier C (doc-only, no version bump) immediately
4. Bundle Tier A into a single patch per repo and ship

The cap-drop removed the only friction point: prior cycles, "ship Tier A now" carried the implicit cost of consuming cap budget toward a forced rollover. Post-drop, the decision is purely about whether the fixes are worth a small version bump.

### Subagent autonomy stayed clean

Both Audit C and Audit D agents wrote their findings docs without scope creep across each other's lanes. The prompt-level constraint ("Audit X runs in parallel as another agent — don't stray into its scope") was load-bearing. Cross-references between findings (e.g., HYG-05 ⟷ Audit E OBS-01) lived in the synthesis layer, not in either agent's findings.

### Heading hierarchy slipped through both the spec and authoring workflow

PA-01 (`<h1>` → `<h3>` skip on /notes/ posts) is the only finding that's NOT closeable in this session because it's content-side. The CHANGELOG for v9.3.0 (long-form post layout) talked about "frontmatter spec card → post title → body sections" — that's the implied hierarchy. The author convention slipped to `<h3>` for body sub-sections, and the template doesn't constrain. Worth a long-term think: should the long-form post pattern document the expected heading levels, or should the editor have a guard (an `editor.BlockListBlock` filter that warns on level skips)?

---

## One-line summary

**Session ran Audits C + D as parallel opus subagents, synthesized findings into a tiered patch plan, applied Tier C doc sweep across CLAUDE.md + readme.txt + 3 memory entries (no version bumps), then shipped Plugin v4.4.4 + Theme v9.4.4 bundling Tier A code patches (PA-03 admin tabs `aria-current`, PA-07 Turnstile leak on /notes/ via script_loader_tag, PA-12 reduced-motion gate on hover transforms) plus Tier D ride-along metadata (`Tested up to: 7.0` on both). Tests green: plugin 888 / theme 303. Outstanding: install both versions via wp-admin Updates UI + PA-01 content fix (h3→h2 sweep across published notes).**
