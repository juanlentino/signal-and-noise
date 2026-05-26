# Audits C + D Cycle — Unified Findings

**Status:** Synthesis of the two deferred audits from the v4.4.x + v9.4.x cycle, dispatched as parallel read-only opus subagents in a clean-slate next-session pickup.

**Compiled:** 2026-05-26 after v4.4.3 + v9.4.3 ship, post-cap-drop.

**Plugin state:** v4.4.3 (HEAD `2ad81d0`).
**Theme state:** v9.4.3 (HEAD `277144f`, worktree `nice-goldstine-063551`).

**Detail sources (this doc is a synthesis — full evidence lives in the per-audit files):**
- Audit C — `2026-05-26-audit-c-project-hygiene-findings.md` (14 findings, 7 passes)
- Audit D — `2026-05-26-audit-d-perf-a11y-findings.md` (18 findings, 7 passes)

---

## 1. Executive summary

**Verdict: 🟢 GREEN with two real bugs and a doc-hygiene backlog.**

The audits surfaced **no critical security issues**, **no critical performance regressions**, and **WCAG-AA contrast compliance** across every brand color pairing. The findings split cleanly into three buckets:

1. **Two real bugs** worth code patches (PA-07 Turnstile leak on `/notes/`; PA-03 plugin admin tabs missing `aria-current`).
2. **One content-level WCAG failure** (PA-01 heading hierarchy) — fix is a DB / editor sweep, not a code change.
3. **A doc-hygiene backlog** — three HIGH-severity doc surfaces (`CLAUDE.md`, theme `readme.txt`, plugin missing `readme.txt`) describe a project state 60+ versions / 8+ weeks behind reality. All doc-only patches; no version bump required.

**Combined counts across both audits:**

| Severity | C (hygiene) | D (perf+a11y) | Total |
|---|---|---|---|
| 🔴 CRITICAL | 0 | 0 | **0** |
| 🟠 HIGH | 3 | 1 | **4** |
| 🟡 MEDIUM | 5 | 2 | **7** |
| 🎨 UI-UX | 0 | 4 | **4** |
| 📋 OBSERVATION | 6 | 5 | **11** |
| ✅ PASS | 7 | 7 | **14** |

**The cap-drop changes the calculus.** Pre-drop, a v4.4.4 + v9.4.4 cycle would consume cap budget from the v4.4.x / v9.4.x quota and force eventual major rollover. Post-drop, both repos can ship indefinite v4.4.x / v9.4.x patches as needed — the question is purely "is this change worth a bump?" not "do we have headroom?"

---

## 2. The 2 audits and what they cover

| Audit | Scope | Status | Tool |
|---|---|---|---|
| **C — Project hygiene** | CLAUDE.md accuracy, memory dedup, TODO/FIXME, orphan files, dead code spot-check, WP/PHP compat declarations, open GH issues + PRs | ✓ Complete; live `gh` calls succeeded | opus subagent |
| **D — Performance + a11y** | JS/CSS budget, inline-style topology, render-blocking, image opt; WCAG 2.1 AA scan: contrast, keyboard nav, headings, ARIA, form labels, screen-reader compat, reduced-motion | ✓ Complete; live HTTP probes succeeded (unlike prior cycle) | opus subagent |

Both audits were dispatched in parallel; they did not overlap scope. Both wrote full findings docs; this synthesis pulls only the headlines + tiering.

---

## 3. HIGH severity (4 findings)

### HYG-01 — `CLAUDE.md` phase-status sentence + line-24 auto-deploy contradiction

Read every session start. Sentence at line 7 says "Phases 1, 4, 2a shipped; 2b/2c/3 queued" — actual is **all 15 phases shipped over v1.4.1 → v4.1.x**. Plus the line-24 sentence still claims theme auto-deploys on tag push, contradicting the actual workflow at lines 38–51.

**Impact:** future-Claude reads stale framing and mis-models project state.

**Fix:** doc-only edit. Tier C.

### HYG-02 — Theme `readme.txt` ~60 versions and a complete redesign behind

`Tested up to: 6.7` (WP 7.0 live). Description still says "Dark industrial design with film grain" — theme inverted to white-first brutalist at v2.0.0. Recommends Yoast SEO — the plugin has owned SEO since v2.0.0. Changelog stops at 3.9.5 (62 missing entries).

**Impact:** the Yoast recommendation is an active footgun — installing it would conflict with plugin SEO emitters.

**Fix:** doc-only. Either rewrite (~20 lines) or delete (not required for non-WP.org themes; `style.css` carries the same metadata). Tier C.

### HYG-03 — Plugin has no `readme.txt` and no `Tested up to:` header

Plugin shows "compatibility unknown" warnings in WP's Updates UI because the header omits `Tested up to:`. No `readme.txt` at all.

**Fix:** add ` * Tested up to: 7.0` line to plugin docblock header. Bundle with next routine plugin patch (header edit is ride-along, not its own version per project convention). Tier D.

### PA-01 — Single-note posts skip `<h2>`, breaking WCAG 1.3.1

Verified on live post: `<h1>` → `<h3>` directly, no intervening `<h2>`. Author-time pattern: 3-of-3 body headings on the sampled post are `<h3>`.

**Impact:** WCAG 1.3.1 (Level A) categorical fail. Screen readers expose this as missing section level.

**Fix:** content-side. SQL one-liner OR editor sweep across published notes. NOT a code change — template doesn't constrain heading level. **User action**, no version bump. Tier B (depends on user availability for content sweep).

---

## 4. MEDIUM severity (7 findings)

### HYG-04 — Memory `project_architecture.md` is 9 days stale

Claims theme v9.1.7 + plugin v4.1.7 with caps at 7/7 MAX. Reality: v9.4.3 + v4.4.3, caps dropped.

**Fix:** regenerate the memory entry. Tier C.

### HYG-05 — Memory `feedback_no_dashboard_widgets` contradicts existing plugin code

The rule reads as a blanket prohibition but predates 4 existing Plausible dashboard widgets + 1 admin bar dropdown in plugin v4.4.3. Rule scope is about *new* surfaces, not *existing* ones.

**Fix:** rephrase rule to clarify "new" scope + acknowledge grandfathered surfaces. Tier C.

### HYG-06 — `abilities-registration.php` docblock says 28 abilities; actual is 30

Missing entry for `abilities-ai-pattern-adoption.php` (2 abilities, added in v4.3.0). The `require_once` list at lines 47–56 correctly loads the file; only the prose summary missed updating.

**Fix:** doc-only docblock edit. Tier C.

### HYG-07 — Orphan memory file `feedback_no_brutalist_in_admin_ui.md` not in MEMORY.md index

File exists with full frontmatter but isn't referenced in `MEMORY.md`, so the slash-command memory loader won't expose it. Sibling from the same session (`feedback_no_dashboard_widgets`) IS indexed.

**Fix:** add one-line entry to `MEMORY.md`. Tier C.

### HYG-08 — `sn_admin_pages()` marked `@deprecated 4.2.0` but still load-bearing

Audit E previously flagged this as U-01 ("defer to v5.0.0"). Hygiene finding: at minimum the docblock framing is wrong (should be `@internal`, not `@deprecated`) — the function is permanent legacy infrastructure.

**Fix (option 1 — minimum):** docblock framing edit. Tier C.
**Fix (option 2 — full):** refactor allowlist usage. Tier B (defer to v5.0.0 cleanup pass per Audit E).

### PA-03 — Plugin admin tabbed UI missing `aria-current="page"`

`.sn-sub-tab.is-active` and `.sn-toc` active items get visual styling but no programmatic affordance for screen readers. WCAG 4.1.2 (Level A).

**Fix:** ~6 LOC across `inc/admin-page.php:202-212` (TOC) + `:238-245` (sub-tabs). Tier A (plugin v4.4.4).

### PA-07 — Turnstile script + dns-prefetch leak onto `/notes/`

Root cause: `inc/page-notes-template.php:104-114` registers a `template_redirect` at priority 0 that calls `include $render; exit;`. The `exit` bypasses the ob_start callback in `inc/frontend-filters.php` (priority 10) that strips Turnstile on non-contact pages.

**Impact:** ~17 KB of render-blocking JS on a page that has no contact form.

**Fix:** route Turnstile strip through `script_loader_tag` filter so it works regardless of renderer short-circuit. ~6 LOC in theme `inc/frontend-filters.php`. Tier A (theme v9.4.4).

---

## 5. UI-UX (4 findings — all Audit D)

| ID | Title | LOC | Tier |
|---|---|---|---|
| PA-06 | `page-notes-render.php` inline CSS is justified (atomic-deploy invariant) | 0 (informational) | n/a — keep as-is |
| PA-10 | Repeating `social_same_as[]` inputs lack per-row labels (plugin admin) | ~3 LOC | Tier B (low traffic) |
| PA-11 | Footnote popover JS is hover-only, no keyboard equivalent | ~10 LOC | Tier B (progressive-enhancement still works without it) |
| PA-12 | Service-card + button hover `transform:` not gated on `prefers-reduced-motion` | ~8 LOC | Tier A (theme v9.4.4) |

---

## 6. Observations (11 findings — all informational)

**From Audit C:**
- OBS-HYG-01 — CHANGELOG cap-math framing on v4.4.3 + v9.4.3 entries (historical; immutable). Next entries should omit cap-math.
- OBS-HYG-02 — Theme `style.css` `Tested up to: 6.9` while WP 7.0 live. Tier D (bundle with next theme patch).
- OBS-HYG-03 — Plugin has no `docs/superpowers/handoffs/` dir; cross-repo convention puts handoffs in theme repo.
- OBS-HYG-04 — Spec docs split between repos is organic / inconsistent. Not actionable.
- OBS-HYG-05 — Zero TODO/FIXME/XXX/HACK markers across either repo. ✅ Commendation.
- OBS-HYG-06 — Zero `.bak`/`.swp`/`.orig`/`.tmp`/`~`/`.DS_Store` files. ✅ Commendation.

**From Audit D:**
- PA-04 — JS frontend budget clean. Plugin emits 0 frontend scripts. ✅
- PA-05 — CSS render-blocking depends on Breeze; theme delegates this. Optional v9.5.x enhancement for Breeze-independence.
- PA-08 — Verify `<img>` in static block templates gets `loading="lazy"` injected by WP core's `wp_filter_content_tags`. Requires live HTML check.
- PA-09 — Cache + CDN + security headers are healthy. ✅
- PA-16 (PASS) — Color contrast measured ratios; consider recording in `docs/ACCESSIBILITY.md` as a watch baseline.

---

## 7. Color contrast — measured ratios (Audit D)

WCAG AA: normal text ≥ 4.5:1, large text ≥ 3:1, non-text UI ≥ 3:1.

| Pairing | Ratio | AA verdict |
|---|---|---|
| `blood` `#e00404` on `void` `#ffffff` | **5.01:1** | ✓ PASS |
| `blood` on `asphalt` `#f5f5f5` | **4.60:1** | ✓ PASS (0.1 margin — flag if asphalt darkens) |
| `signal` `#ff4c47` on `void` (hover) | **3.29:1** | ✓ large text only; underline restores affordance |
| `rust` `#666666` on `void` | **5.74:1** | ✓ PASS |
| `bone` `#000000` on `void` | **21.00:1** | ✓ PASS (max contrast) |
| Plugin admin `#dc3232` on `void` | **4.62:1** | ✓ PASS |
| Plugin admin `#d63638` on `void` | **4.73:1** | ✓ PASS |

**Verdict:** AA-clean for every text pairing. Underlined hover state preserves non-color affordance per WCAG 1.4.1.

**Watch thresholds for future palette tweaks:** if `--asphalt` darkens at all (`#eaeaea` would push blood-on-asphalt under 4.5:1), or if `--blood` lightens toward `#e02828`, both would drop below AA.

---

## 8. Cross-references to prior audits

- HYG-05 (memory rule contradiction) ⟷ Audit E **OBS-01** — re-flagged; still unfixed.
- HYG-06 (abilities docblock total) ⟷ Audit B **OBS-3** — re-flagged with deeper evidence; still unfixed.
- HYG-08 (`@deprecated` framing) ⟷ Audit E **U-01** — Audit E's full refactor stays deferred to v5.0.0; this audit adds the docblock-framing sub-finding for ship-now.

All three were "noted but not patched" in the v4.4.x post-ship cycle. They roll forward to this synthesis with no change in classification.

---

## 9. Patch tiering — recommended sequence

### Tier C — Doc-only sweep (no version bump, safe to ship immediately)

Per CLAUDE.md "What doesn't bump" rule, none of these require a version bump. Recommend a single bundled docs-hygiene commit per repo (or two commits — one per repo).

**Theme worktree:**
1. `CLAUDE.md` — replace line 7 phase-status sentence + fix line 24 auto-deploy contradiction (HYG-01).
2. `readme.txt` — rewrite to current state OR delete (HYG-02). Recommend delete; `style.css` carries the metadata and the theme isn't on WP.org.

**Memory (`/Users/juanlentino/.claude/projects/-Users-juanlentino-Projects-signal-and-noise/memory/`):**
3. Regenerate `project_architecture.md` to reflect v9.4.3 + v4.4.3 + cap-drop (HYG-04).
4. Rephrase `feedback_no_dashboard_widgets.md` to clarify "new" scope + acknowledge grandfathered surfaces (HYG-05).
5. Add `MEMORY.md` index line for `feedback_no_brutalist_in_admin_ui.md` (HYG-07).

**Plugin:**
6. `inc/abilities-registration.php` — docblock total 28 → 30 + add `abilities-ai-pattern-adoption.php` line (HYG-06).
7. `inc/admin-page.php:53` — switch `@deprecated 4.2.0` to `@internal` (HYG-08 option 1).

### Tier A — Code patches (version bumps + ship)

**Plugin v4.4.4** (bundle):
- PA-03 — `aria-current="page"` on active sub-tab + TOC links (`inc/admin-page.php:202-245`).
- HYG-03 — add `Tested up to: 7.0` header (ride-along).

**Theme v9.4.4** (bundle):
- PA-07 — Route Turnstile strip through `script_loader_tag` filter (`inc/frontend-filters.php`).
- PA-12 — Wrap service-card + button hover `transform:` in `@media (prefers-reduced-motion: no-preference)` (`assets/css/components.css:25-37, 64-67`).
- OBS-HYG-02 — Bump `style.css` `Tested up to: 6.9 → 7.0` (ride-along).

### Tier B — Backlog (defer, not this cycle)

- PA-01 — heading hierarchy content fix (DB sweep + author convention). **User action.**
- PA-10 — repeating social-input labels (low-traffic admin form).
- PA-11 — footnote popover keyboard equivalent (progressive-enhancement only).
- PA-05 — Breeze-independent render-blocking handling (v9.5.x enhancement).
- PA-08 — verify static template `<img>` lazy/async injection (requires live HTML inspection).
- HYG-08 option 2 — full `sn_admin_pages()` refactor (defer to v5.0.0 per Audit E).
- PA-16 doc-only — record contrast baselines in `docs/ACCESSIBILITY.md`.

### Tier D — Ride-along metadata edits (bundle with Tier A patches above)

- HYG-03 (plugin `Tested up to:`) → ride-along on v4.4.4.
- OBS-HYG-02 (theme `Tested up to:`) → ride-along on v9.4.4.

---

## 10. Decision points for next-step scope

The user picks one (or some combination):

**Option 1 — Doc-only sweep only.** Apply Tier C (7 items, no version bumps, no deploys). Defer Tier A code patches to a future session. Lowest commitment, highest safety; clears the doc-drift findings.

**Option 2 — Full cycle (Tier C + Tier A).** Apply Tier C, then ship Plugin v4.4.4 + Theme v9.4.4 bundling Tier A + Tier D. Closes both real bugs (PA-03 + PA-07) and the reduced-motion gap (PA-12) plus rides the metadata edits. Matches the prior cycle's shape (audit → bundled patch → ship in same session).

**Option 3 — Synthesis only, ship nothing now.** Hand off to a future session with this doc as the work plan. Lowest commitment of all.

**Recommended (per "default to your recommendation"):** **Option 2.** The two bugs are real, the fixes are small (~20 LOC across both repos), and the cap-drop means the version-bump path has no downstream cost. The prior cycle's audit-to-ship loop is now an established pattern.

---

## 11. What was NOT in scope

From Audit C:
- Full dead-code audit (350 plugin + 39 theme global functions) — spot-check found no dead code among legacy-named candidates. Defer to a v5.0.0 cleanup pass.
- Full memory entry freshness audit (35 entries on disk; spot-checked the OBS-flagged subset + ~5 others).
- Full spec-doc "shipped" claim audit (40 specs across both repos).
- GHA workflow file currency.
- Composer dependency staleness.

From Audit D:
- In-browser Lighthouse / PSI runs (no headless Chrome in sandbox; source-side inference only).
- Dark mode considerations (intentionally omitted per `design_dark_mode_omitted.md`).

---

## 12. One-line summary

**Audits C + D found zero critical issues, four HIGH-severity items (three doc-drift, one content-WCAG), and seven MEDIUM items spread across hygiene + a11y. Two real bugs warrant code patches (PA-03 admin tabs `aria-current`, PA-07 Turnstile leak on `/notes/`); one a11y issue (PA-12 reduced-motion gate) rides along. Three HIGH-severity doc surfaces describe a project state 60+ versions stale; all are Tier C doc-only patches. Color contrast is WCAG-AA clean for every text pairing with a 0.1-ratio margin on the tightest. Recommended next step: full cycle (Tier C doc sweep + Plugin v4.4.4 + Theme v9.4.4 bundling Tier A bugs + Tier D metadata).**
