# Audit C — Project Hygiene Findings (post v4.4.3 + v9.4.3)

**Status:** Deferred audit C from the v4.4.x + v9.4.x cycle, run clean-slate after the v4.4.0 → v4.4.3 + v9.4.0 → v9.4.3 ship sequence. Audit D (perf + a11y) runs in parallel and does not overlap this scope.

**Compiled:** 2026-05-26 against:
- Plugin: `/Users/juanlentino/Projects/signal-and-noise-tools` — HEAD `2ad81d0`, tag **v4.4.3**
- Theme:  `/Users/juanlentino/projects/signal-and-noise/.claude/worktrees/nice-goldstine-063551` — HEAD `277144f`, tag **v9.4.3** (worktree of `juanlentino/signal-and-noise`)
- Memory: `/Users/juanlentino/.claude/projects/-Users-juanlentino-Projects-signal-and-noise/memory/` — 34 entries indexed, 35 files on disk

**Scope:** CLAUDE.md accuracy, memory hygiene, TODO/FIXME markers, orphaned files, dead code, WP/PHP compat declarations, open GitHub issues + PRs. Read-only audit; this doc is the only artifact produced.

---

## 1. Executive summary

**Verdict: 🟡 YELLOW — no bugs, no security issues, but multiple stale-doc surfaces drifting away from the live project state.**

| Severity | Count | Theme |
|---|---|---|
| 🔴 CRITICAL | 0 | — |
| 🟠 HIGH | 3 | `CLAUDE.md` phase-status sentence, theme `readme.txt` ~60 versions behind, plugin missing `readme.txt` + `Tested up to:` |
| 🟡 MEDIUM | 5 | `project_architecture.md` memory entry severely stale; `feedback_no_dashboard_widgets` contradiction; `abilities-registration.php` docblock total + missing pattern-adoption entry; orphan memory file; `@deprecated 4.2.0` legacy still load-bearing |
| 📋 OBSERVATION | 6 | CHANGELOG cap-math vestigial framing; handoff dir size; tags-list date drift; `Tested up to: 6.7` in theme but WP 7.0 live; etc. |
| ✅ PASS | 7 | TODO/FIXME, orphan files, `/tmp/` refs, GH issues + PRs, CHANGELOG heads, function-level dead code, deploy-mechanism docs (after the phase-status sentence is patched) |

**Headline:** the worktree's CLAUDE.md, theme `readme.txt`, and the `project_architecture.md` memory file all describe a project state from **two-to-eight weeks of shipping ago** (plugin v4.1.x era / pre-v4.4.0). No code is broken — but a fresh maintainer (or future-Claude) reading these documents will materially mis-model the current state. The 3 HIGH findings are all the same kind of finding: **doc surfaces that lie about reality.** All three are doc-only patches; no version bump required for any of them per CLAUDE.md's "What doesn't bump" rule.

---

## 2. HIGH severity — Doc surfaces drifting from reality

### HYG-01 — `CLAUDE.md` phase-status sentence is ~6 phases behind

**File:** `CLAUDE.md` (theme, worktree copy), line 7

**Current text:**
```
**Companion plugin (since v8.2.0):** Operational tooling lives in
[juanlentino/signal-and-noise-tools](https://github.com/juanlentino/signal-and-noise-tools).
Phases 1, 4 (RSS tracker), 2a (auto-deploy) shipped; 2b / 2c / 3 queued.
```

**Evidence — phases 2b / 2c / 3 / 13 all shipped:**

```
$ ls docs/superpowers/handoffs/ | grep -E "phase-2|phase-3|phase-13"
2026-05-15-end-of-phase-2a-handoff.md
2026-05-15-end-of-phase-2b-handoff.md
2026-05-16-end-of-phase-2c-handoff.md
2026-05-16-end-of-phase-3-handoff.md
2026-05-17-end-of-phase-13-handoff.md
```

Memory entry `project_architecture.md` explicitly states: *"Plugin absorption roadmap fully closed. 15 phases from docs/superpowers/specs/2026-05-16-plugin-absorption-roadmap.md shipped over the v1.4.1 → v4.1.7 arc."*

**Severity:** HIGH. CLAUDE.md is read at start of every session per the file itself ("**Start of session:** read the most recent handoff..."). Stale phase-status framing causes the model to ask "what about phase 2b?" or treat 2c/3 as TODO when they're closed.

**Recommended action:** doc-only patch. Replace the "Phases 1, 4, 2a shipped; 2b/2c/3 queued" sentence with something current. Suggestion:

> Phases 1–15 of the original plugin absorption roadmap all shipped (v1.4.1 → v4.1.x arc, 2026-05-15 → 2026-05-25). Plugin is now at v4.4.3 with the full SEO + login + admin-UI surface absorbed. See `docs/superpowers/specs/2026-05-16-plugin-absorption-roadmap.md` for the closed-out phase list.

No version bump (CLAUDE.md doesn't bump per the rule).

---

### HYG-02 — Theme `readme.txt` is ~60 versions and a complete redesign behind

**File:** `readme.txt` (theme), lines 1–8 + 60–173 (changelog)

**Drift evidence:**

| Header field | `readme.txt` says | Reality |
|---|---|---|
| `Tested up to:` | `6.7` | WP 7.0 ("Armstrong") live in production; theme actively tested against it (per `project_architecture.md` and v9.4.0 design doc) |
| `Stable tag:` | `6.5.5` | This is a WordPress version number, not a theme version — and stable theme is v9.4.3 |
| Description | "Dark industrial design with film grain and scanline overlays" | Theme is white-first brutalist now (memory: `design_dark_mode_omitted` — "white-first by design") |
| Features list | "Dark industrial design", "page templates for About, Services, Music, Resume, Contact, and Work With Me" | All five "page templates" are v1.x-era — current theme is FSE / block theme with templates and patterns |
| Recommended Plugins | "Yoast SEO — for search engine optimization" | Plugin owns ALL SEO emission since v2.0.0 (TSF replaced; per `project_architecture.md`); recommending Yoast actively misleads |
| Changelog | Stops at `3.9.5` | Theme is v9.4.3 — **62 missing version entries** (3.9.6 → 9.4.3) |

The two-line description is straightforwardly wrong: theme was inverted from "Dark industrial" to "white, clinical, brutalist" at v2.0.0 (per its own changelog line 130-131: "= 2.0.0 = Full palette inversion to match nin.com"). The readme.txt description never got updated.

**Severity:** HIGH. `readme.txt` is the WordPress.org-format readme. While this theme isn't on WP.org, the file is still shipped to production; anyone inspecting it will get a wrong picture. The "Yoast SEO" recommendation is the most active footgun — a future maintainer following readme.txt would install Yoast, which would then conflict with the plugin's SEO surface (canonical/robots/meta-desc emitters).

**Recommended action:** doc-only patch. Either:
1. **Rewrite** `readme.txt` to reflect current state (recommend; ~20-line rewrite of header + description + features + recommended plugins; collapse changelog to a "See CHANGELOG.md" pointer)
2. **Delete** the file (it isn't required for non-WP.org themes and `style.css` already carries the same metadata)

No version bump (doc-only; pure content).

---

### HYG-03 — Plugin has no `readme.txt` AND no `Tested up to:` header

**Evidence:**

```
$ ls /Users/juanlentino/Projects/signal-and-noise-tools/readme.txt
NO PLUGIN README.TXT
```

Plugin docblock header at `signal-and-noise-tools.php:3-15`:
```
 * Plugin Name: Signal & Noise Tools
 * Version:     4.4.3
 * Requires at least: 6.4
 * Requires PHP: 8.0
   (no Tested up to:)
```

**Why this matters:** the plugin registers with WP's native update system (`inc/wp-update-integration.php`) — and WP's "Tested up to" mismatch UI in wp-admin uses the value from the plugin header. Without it, WP defaults to showing a "compatibility unknown" warning on the Updates screen. The plugin has been live since v1.x and absorbing more responsibility each release without ever declaring its target WP range.

**Severity:** HIGH (lower-blast-radius than HYG-02 but more easily fixed).

**Recommended action:** doc-only patch on `signal-and-noise-tools.php`. Add ` * Tested up to: 7.0` between `Requires at least` and `Requires PHP`. Optionally create `readme.txt` to match the (now-fixed) theme readme. **This IS a Version: header edit, so per CLAUDE.md "What bumps: code, CSS, migrations" — header-only docblock metadata edits are gray-area, but historically the project doesn't bump for header-only adds (e.g., the plugin's `Update URI:` was never added with its own version).** Recommend: bundle this with a routine patch (e.g., next behavioural fix) rather than ship as its own version.

---

## 3. MEDIUM severity

### HYG-04 — `project_architecture.md` memory entry is ~9 days stale

**File:** `/Users/juanlentino/.claude/projects/-Users-juanlentino-Projects-signal-and-noise/memory/project_architecture.md`

The system pre-flagged this file as 9 days old on read. Confirmed drift:

| Memory says | Reality |
|---|---|
| Theme v9.1.7, plugin v4.1.7 (line 3) | Theme v9.4.3, plugin v4.4.3 |
| "Plugin cap 7/7 v4.1.x ⚠️ MAX" (line 3, repeated line 68) | Caps were DROPPED 2026-05-26 (per `feedback_versioning_patch_cap.md` + commit `aa1c9b9`) |
| "Theme cap 7/7 v9.1.x ⚠️ MAX. Next change EITHER repo rolls a minor" (line 3) | Caps dropped; this rule no longer applies |
| "v4.1.7" is the latest plugin shipped (line 68) | v4.4.3 latest; v4.2.0, v4.2.1, v4.3.0, v4.4.0, v4.4.1, v4.4.2, v4.4.3 all shipped after this entry |
| "v9.1.7" latest theme (line 67) | v9.4.3 latest; v9.2.0, v9.3.0, v9.4.0–v9.4.3 all shipped after |
| "Outstanding (post-v4.1.2)" section lists v4.1.3, v4.1.4 as queued (lines 81-82) | Both shipped, plus v4.2.x, v4.3.x, v4.4.x cycles all complete |

**Severity:** MEDIUM (the entry is auto-flagged stale by the memory system on every read — model is *warned* not to trust it — but it's still the top "current architecture" pointer in MEMORY.md and a maintainer doing an `@project_architecture` lookup will get a wildly wrong picture).

**Recommended action:** regenerate the entry. Either:
1. Replace with a new "current architecture (theme v9.4.3 + plugin v4.4.3, 2026-05-26)" entry that captures the v4.2.x login-refactor + v4.3.0 pattern-adoption + v4.4.x cross-package contracts arc — and removes all cap-headroom framing.
2. Delete the entry as point-in-time-only-useful; rely on `project_parallel_major_brainstorm.md` + `feedback_versioning_patch_cap.md` for current rules.

Option 1 is the cleaner choice — pattern is already established (the same file was regenerated for v4.1.7 from a v3.x predecessor). User decides; this audit can't write to the memory dir.

---

### HYG-05 — `feedback_no_dashboard_widgets.md` contradicts existing dashboard widgets + admin bar (OBS-01 from Audit E, still unfixed)

**File:** `/Users/juanlentino/.claude/projects/-Users-juanlentino-Projects-signal-and-noise/memory/feedback_no_dashboard_widgets.md`

**Memory rule:** *"Do not propose WordPress dashboard widgets (`wp_add_dashboard_widget`) or admin bar items (`admin_bar_menu`) as surfaces for Signal & Noise operational information."*

**Contradiction in plugin v4.4.3 source:**

```
$ grep -rn "wp_add_dashboard_widget\|admin_bar_menu" inc/ signal-and-noise-tools.php
inc/plausible-widget.php:33: wp_add_dashboard_widget('sn_plausible_snapshot', 'Plausible — Last 7 days', 'sn_pl_widget_snapshot');
inc/plausible-widget.php:34: wp_add_dashboard_widget('sn_plausible_realtime', 'Plausible — Right now', 'sn_pl_widget_realtime');
inc/plausible-widget.php:35: wp_add_dashboard_widget('sn_plausible_pages', 'Plausible — Top pages (7d)', 'sn_pl_widget_pages');
inc/plausible-widget.php:36: wp_add_dashboard_widget('sn_plausible_sources', 'Plausible — Top sources (7d)', 'sn_pl_widget_sources');
inc/admin-bar.php:73: add_action('admin_bar_menu', function($admin_bar) {
```

4 Plausible dashboard widgets + 1 admin bar dropdown in production from v7.x — predating the memory rule (rule was set 2026-05-16 after the v1.12.0 deploy-widget pushback).

**Sub-finding — MEMORY.md index entry has the wrong date:** index line 9 says "Set 2026-05-20 after 5 failed releases" — wait, that's a different entry. Line 9 says `feedback_no_dashboard_widgets` "Caught when v1.12.0 deploy widget shipped — user pushed back within 10 min." The MEMORY.md index doesn't carry a 'set date' for this entry; the file's own context says 2026-05-16 (plugin v1.12.0). Not a contradiction itself, but the rule scope is the contradiction.

**Severity:** MEDIUM. The rule is real — user does dislike NEW operational dashboard widgets — but the rule as currently written reads as a blanket prohibition that the plugin's own existing code violates. The Plausible widgets pre-existed the v1.12.0 deploy-widget incident; the rule is about *adding new ones*, not *removing the existing ones*.

**Recommended action:** rephrase the memory rule for accuracy:
- Current opening: *"Do not propose WordPress dashboard widgets (`wp_add_dashboard_widget`) or admin bar items (`admin_bar_menu`) as surfaces for Signal & Noise operational information."*
- Suggested: *"Do not propose **new** WordPress dashboard widgets or admin bar items for Signal & Noise operational tooling. The existing Plausible dashboard widgets (`inc/plausible-widget.php`, 4 widgets since v7.x) and admin bar dropdown (`inc/admin-bar.php`, since v7.x) are grandfathered — they predate this rule (set 2026-05-16 after the v1.12.0 deploy widget pushback). New operational surfaces go into SN admin tabs."*

---

### HYG-06 — `inc/abilities-registration.php` docblock total wrong + missing pattern-adoption entry (confirms OBS-3 from Audit B)

**File:** `/Users/juanlentino/Projects/signal-and-noise-tools/inc/abilities-registration.php`, lines 1–30

**Docblock claims:** *"Total: 28 abilities + 5 categories"* (line 29)

**Docblock's per-file inventory (lines 13-27):**
- abilities-system.php — 6 abilities
- abilities-content.php — 2
- abilities-cron.php — 4
- abilities-insights.php — 2
- abilities-audit.php — 4
- abilities-ai-post-editor.php — 3
- abilities-ai-health.php — 7
- **(missing: abilities-ai-pattern-adoption.php — 2 abilities)**

→ Listed sum: 28 ✓  → Actual sum: 30 ✗

**Verification:**
```
$ grep -rE "^\s*wp_register_ability\(" inc/abilities-*.php | grep -v function_exists | awk -F: '{print $1}' | sort | uniq -c
   7 abilities-ai-health.php
   2 abilities-ai-pattern-adoption.php    ← not in docblock
   3 abilities-ai-post-editor.php
   4 abilities-audit.php
   2 abilities-content.php
   4 abilities-cron.php
   2 abilities-insights.php
   6 abilities-system.php
                                          ─── = 30 total
```

Also: the docblock's per-file `require_once` list at lines 47-56 DOES include `abilities-ai-pattern-adoption.php` at line 56 — so the orchestrator loads it; only the prose summary missed updating when v4.3.0 added that file.

**Severity:** MEDIUM (cosmetic; docblock is the canonical "what abilities does this plugin register" reference, used by anyone trying to inventory the surface).

**Recommended action:** doc-only patch on the docblock. Add the missing line:
```php
 *   - inc/abilities-ai-pattern-adoption.php  — 2 abilities: pattern-adoption
 *     Suggest+Apply (pull-quote + steps-enumerated).
 *
 * Total: 30 abilities + 5 categories. Each feature file owns its
```

(Update total from 28 → 30 on line 29.)

No version bump (docblock-only edit; pure content).

---

### HYG-07 — Orphan memory file not indexed in MEMORY.md

**File:** `/Users/juanlentino/.claude/projects/-Users-juanlentino-Projects-signal-and-noise/memory/feedback_no_brutalist_in_admin_ui.md`

**Evidence:**

```
$ ls memory/*.md | wc -l
36                              # includes MEMORY.md → 35 entry files
$ grep -c "^- \[" memory/MEMORY.md
34                              # entries listed in the index
                                # gap of 1 ⇒ one entry file is unlisted
$ grep -rn "no_brutalist\|no-brutalist" memory/
(no matches outside the file itself)
```

The file exists, has full frontmatter (`type: feedback`, sessionId `7ffa68df-...`), and is a legitimate rule ("Don't apply the brutalist front-end aesthetic to wp-admin / plugin admin UI") — but it's not referenced from MEMORY.md so the slash-command memory loader won't expose it.

**Sub-evidence on the same `sessionId`:** the other entry from session `7ffa68df-efca-4fec-ba15-bca1a390fc97` is `feedback_no_dashboard_widgets.md` (HYG-05). Both came from the v1.12.0 / 2026-05-16 admin-UI session. The `no_dashboard_widgets` entry got indexed; the `no_brutalist` peer didn't.

**Severity:** MEDIUM. The rule is real and useful — the project explicitly *does* avoid brutalist treatment in wp-admin (per its own admin.css convention) — but a fresh session won't load it, so the model could redrift toward proposing Bebas/uppercase/red-accent treatments for admin surfaces.

**Recommended action:** add to MEMORY.md as a one-line index entry alongside related design-discipline entries (near `design_dark_mode_omitted` and the inline-style discipline notes):

```
- [No brutalist visual treatment in wp-admin](feedback_no_brutalist_in_admin_ui.md) — Brand vocabulary (Bebas Neue, uppercase tracked labels, mono numbers as brand, heavy red accents) belongs to the front-end only. Wp-admin reads as native WP. Structural discipline (no inline styles, hierarchy, whitespace) DOES translate. Set 2026-05-16 after v1.13.0 plugin redesign pushback.
```

---

### HYG-08 — `sn_admin_pages()` marked `@deprecated 4.2.0` but still load-bearing (confirms U-01 from Audit E)

**File:** `/Users/juanlentino/Projects/signal-and-noise-tools/inc/admin-page.php`, line 53

**Evidence:**

Line 53: `* @deprecated 4.2.0 Use sn_admin_top_tabs() instead. This table is`

**But the function is still called from production paths:**
```
$ grep -rn "sn_admin_pages" inc/
inc/admin-page.php:54: *   retained for backward compat with sn_admin_maybe_redirect_legacy()
inc/admin-page.php:413: * The legacy sn_admin_pages() still drives the WP submenu sidebar
inc/admin-page.php:427: * Single source of truth: every tab slug registered in sn_admin_pages()
inc/admin-page.php:452: * for its 12 entries
inc/admin-page.php:474: foreach (sn_admin_pages() as $page) {       ← active call site (POST allowlist)
inc/admin-page.php:513: * of the 12 legacy entries from sn_admin_pages(). Legacy entries' URLs still
inc/admin-page.php:535: * rename in sn_admin_pages() won't silently break the guard
```

Line 474 is the active load-bearing call site (POST allowlist). The `@deprecated 4.2.0` tag is misleading — the function isn't pending removal; it's permanent legacy infrastructure.

**Severity:** MEDIUM (already documented in Audit E as U-01; recommended fix was *"Replace allowlist usage with `array_column( sn_admin_top_tabs(), 'slug' )`"*). Restating here as project-hygiene because the docblock is the misleading surface, independent of whether the call site gets refactored.

**Recommended action:** options:
1. **Replace `@deprecated` with `@internal`** in the docblock — function is internal-legacy, not deprecated-pending-removal. (1-line edit, no version bump.)
2. **Actually deprecate** by replacing the load-bearing call at line 474 with `array_column( sn_admin_top_tabs(), 'slug' )` (Audit E's recommendation).

Audit E pre-classified U-01 as "defer to v5.0.0 (cleanup with audit)." This hygiene finding agrees with that classification — at minimum, fix the docblock framing (option 1) in the next routine patch so future-readers don't think this is dying code.

---

## 4. Observations (low priority)

### OBS-HYG-01 — CHANGELOG cap-math framing retained on v4.4.3 + v9.4.3 entries

Both repos' latest CHANGELOG entries close with cap-math:
- Plugin v4.4.3: *"Cap math: plugin patch 2/7 → **3/7** in v4.4.x. 4 patches remaining."*
- Theme  v9.4.3: *"Cap math: theme patch 2/7 → **3/7** in v9.4.x. 4 patches remaining."*

Both entries pre-dated the cap-drop commit (`aa1c9b9 docs(versioning): drop the 7/minor + 5/major caps`) by minutes-to-hours, so the cap-math is historically accurate at the time of writing. Don't backfill — CHANGELOG entries are immutable historical record. But future entries should drop the cap-math closer.

**Action:** nothing on existing entries. Next CHANGELOG entry (whichever repo) should omit the cap-math line and instead state actual semantic justification per `feedback_versioning_patch_cap`.

---

### OBS-HYG-02 — Theme `style.css` `Tested up to: 6.9` while WP 7.0 live in production

```
$ grep "Tested up to" style.css
Tested up to: 6.9
```

Memory `project_architecture.md` line 27: *"WP 7.0 'Armstrong' live with ai/ai core AI plugin + ai-provider-for-anthropic provider plugin active."*

Theme is actively tested against 7.0 (per the v9.2.0 / v9.3.0 / v9.4.0 design docs, all referencing 7.0 features), but `style.css` header says 6.9. Mismatch (and *also* mismatches `readme.txt`'s `6.7` per HYG-02).

**Action:** Bump to `Tested up to: 7.0` next time a theme code change ships. Pure metadata; bundle with a non-trivial change.

---

### OBS-HYG-03 — Handoff dir is 42 entries deep in theme, 0 in plugin

```
$ ls docs/superpowers/handoffs/ | wc -l   # theme
42
$ ls /Users/juanlentino/Projects/signal-and-noise-tools/docs/superpowers/handoffs/
(directory does not exist)
```

CLAUDE.md says: *"Start of session: read the most recent handoff in `docs/superpowers/handoffs/`."* That points at the theme repo's dir, but the plugin's own activity over the same period (Phases 13–15+ = TSF cutover, abilities refactor, AI work) is captured in the *theme* repo's handoff dir (e.g., `2026-05-26-v4.4.0-and-v9.4.0-shipped.md`) — meaning the plugin repo has no independent handoff record.

**Severity:** OBSERVATION. The cross-repo handoff convention works fine in practice (`v4.4.0-and-v9.4.0-shipped.md` covers both repos in one doc), but if someone clones the plugin alone without the theme repo, they have no handoff trail. Optional: symlink, or add a `docs/handoffs-elsewhere.md` pointer in the plugin.

---

### OBS-HYG-04 — Plugin specs dir doesn't include the v4.1.x / v4.2.x / v4.3.x / v4.4.x design docs

```
$ ls /Users/juanlentino/Projects/signal-and-noise-tools/docs/superpowers/specs/
2026-05-20-insights-tab-design.md
2026-05-24-plugin-v3.8.0-anthropic-provider-design.md
2026-05-25-admin-tabs-ia-reorganization-design.md
2026-05-25-login-hardening-audit-log-design.md
2026-05-25-v3.8.1-sub-tabs-and-cache-fix-design.md
2026-05-25-v4.0.0-ai-health-suggest-apply-design.md
2026-05-25-v4.0.2-inline-img-alt-suggest-design.md
2026-05-25-v4.0.3-before-after-modal-design.md
2026-05-25-v4.0.x-roadmap.md
2026-05-25-v4.1.0-orphan-media-suggest-apply-design.md
2026-05-25-v4.2.0-login-self-heal-tier-c-design.md
2026-05-25-viewport-fit-admin-pages-design.md
2026-05-26-v4.3.0-bulk-pattern-adoption-suggest-apply-design.md
2026-05-26-v4.4.0-cross-package-contracts-and-v5-readiness-design.md
2026-05-26-v5.0.0-scope.md
```

Plugin specs dir has 15 entries; theme specs dir has 25. Theme repo's specs dir contains plugin-specific design docs alongside theme docs (e.g., `2026-05-16-plugin-absorption-roadmap.md`, `2026-05-26-roadmap-to-v5-and-v10-design.md`). The split between which docs live in which repo is organic / inconsistent.

**Severity:** OBSERVATION. The cycle-audit doc itself (this report's sibling at `docs/superpowers/specs/2026-05-26-v4.4.x-and-v9.4.x-cycle-audit-findings.md`) lives in the theme repo but audits both. Convention has settled on theme-repo-as-canonical for cross-cutting docs. Not actionable; recording for the convention's archaeology.

---

### OBS-HYG-05 — Zero TODO/FIXME/XXX/HACK markers across either repo's source

**Verification across both repos:**
```
$ grep -rn -E "TODO|FIXME|XXX|HACK" --include='*.php' --include='*.js' --include='*.css' --include='*.html' \
       --exclude-dir=docs --exclude-dir=tests --exclude-dir=vendor
(zero results in either repo)
```

Even widening to include `tests/` and `docs/`: zero source-marker results in either repo. This is unusually clean for a project that's shipped 60+ versions on the theme and 100+ on the plugin.

**Severity:** OBSERVATION / commendation. No action needed — flagged here as an explicit ✅ for the project-hygiene record.

---

### OBS-HYG-06 — Zero `.bak`, `.swp`, `.orig`, `.tmp`, `~`, `.DS_Store` files in either tree

```
$ find . -type f \( -name "*.bak" -o -name "*.swp" -o -name "*.orig" -o -name "*.tmp" -o -name "*~" -o -name ".DS_Store" \) ! -path "./.git/*"
(zero results in either repo)
```

**Severity:** OBSERVATION / commendation. ✅.

---

## 5. Passes — what was checked clean

| ✅ | Check | Evidence |
|---|---|---|
| 1 | Zero TODO/FIXME/XXX/HACK in either repo | `grep -rn -E "TODO\|FIXME\|XXX\|HACK"` → 0 hits |
| 2 | Zero orphaned files (`.bak`/`.swp`/`.orig`/`.tmp`/`~`/`.DS_Store`) | `find` → 0 hits |
| 3 | Zero `/tmp/` or `/private/tmp/` references in source PHP | `grep -rn "\b/tmp/\|/private/tmp/" inc/` → 0 hits |
| 4 | Zero open GitHub issues — both repos | `gh issue list --state open` → 0 (both) |
| 5 | Zero open GitHub PRs — both repos | `gh pr list --state open` → 0 (both) |
| 6 | CHANGELOG heads match shipped tags (v4.4.3 / v9.4.3) | `head -25 CHANGELOG.md` → matches |
| 7 | `Requires PHP: 8.0` consistent across both repos | Header read → both `8.0` |

Dead-code spot-check (4 candidates verified live):
- `sn_admin_legacy_redirect_map` — actively called at admin-page.php:365, 832
- `sn_admin_maybe_redirect_legacy` — actively called at admin-page.php:879
- `sn_admin_pages` — actively called at admin-page.php:474 (also load-bearing for legacy URL redirects)
- `sn_apply_legacy_reading_time_cleanup` — actively called at admin-page.php:712

No dead code found in spot check. A full dead-code audit across all 350 plugin global functions + 39 theme global functions wasn't run; spot check sampled the most-likely-stale candidates from the `legacy_` / `_legacy_` naming convention.

---

## 6. Cross-references

**To existing audit findings:**
- HYG-05 ⟷ Audit E OBS-01 (`feedback_no_dashboard_widgets` contradiction)
- HYG-06 ⟷ Audit B OBS-3 (abilities-registration docblock 28 → 30)
- HYG-08 ⟷ Audit E U-01 (`sn_admin_pages()` deprecated-but-load-bearing)

**To memory entries that need touching:**
- `project_architecture.md` (HYG-04) — regenerate
- `feedback_no_dashboard_widgets.md` (HYG-05) — rephrase rule scope
- `feedback_no_brutalist_in_admin_ui.md` (HYG-07) — add to MEMORY.md index
- MEMORY.md index — also update the index line for `feedback_no_dashboard_widgets` once HYG-05's rule scope is fixed

**To CLAUDE.md (theme only — no plugin CLAUDE.md exists):**
- HYG-01 (phase-status sentence at line 7)
- Plus, while editing, consider: "Workflow per release" sentence at line 24 still claims *"both theme and plugin auto-deploy + auto-purge CF edge cache in ~30s"* — but the "Build & Deploy" section below at lines 38-51 correctly describes manual deploy. The line 24 sentence contradicts what follows. **Sub-finding folded into HYG-01.**

---

## 7. Proposed patch sequence

All findings are doc-only — no version bump required for any of them. Recommend bundling into a **single docs hygiene pass** rather than ad-hoc, since none are blocking.

**One commit, three repos / surfaces:**
1. Theme `CLAUDE.md` — replace phase-status sentence (HYG-01) + fix the "auto-deploy" subclaim at line 24 (HYG-01 sub-finding).
2. Theme `readme.txt` — collapse to current-state header + features + "see CHANGELOG.md" pointer (HYG-02). OR delete.
3. Plugin `signal-and-noise-tools.php` — add `Tested up to: 7.0` header line (HYG-03). Bundle with next behavioural patch.
4. Plugin `inc/abilities-registration.php` — fix docblock total + add pattern-adoption file entry (HYG-06).
5. Plugin `inc/admin-page.php:53` — switch `@deprecated 4.2.0` to `@internal` (HYG-08 option 1, minimum action; defer the actual refactor per Audit E classification).
6. Memory: regenerate `project_architecture.md` (HYG-04), rephrase `feedback_no_dashboard_widgets.md` (HYG-05), add MEMORY.md index entry for `feedback_no_brutalist_in_admin_ui.md` (HYG-07).
7. Theme `style.css` — bump `Tested up to: 6.9 → 7.0` (OBS-HYG-02) — bundle with next theme code change.

Items 1, 2, 4, 5, 6 are CHANGELOG-only-or-less per CLAUDE.md rule ("docs/ and CLAUDE.md don't bump"). Items 3 and 7 are header metadata edits that conventionally ride along on the next routine patch.

---

## 8. Items not audited (scope or tooling)

- **Full dead-code audit (350 plugin + 39 theme global functions)** — spot-checked the most likely candidates (legacy_/redirect_), all live. A complete audit would require name-mapping every `function` to all callers across `inc/` + `templates/` + `parts/` + `patterns/` + `tests/` + REST handlers + cron handlers + ability dispatchers + admin POST handlers + JS dispatch surfaces. Out of budget for this hygiene pass. Recommend deferring to a v5.0.0 cleanup pass (where Audit E's U-01 also lands).
- **All 35 memory entry file freshness** — read the 4 entries flagged as relevant by the OBS prompt + `project_architecture.md` + `feedback_versioning_patch_cap.md` + `project_wp_ui_updates_status_display_only.md` + `feedback_no_brutalist_in_admin_ui.md`. The remaining ~28 weren't individually re-verified against current code; they may have their own drift (lower likelihood for entries dated within the last 9 days; higher for any 2026-04-x entries if any exist).
- **Spec doc freshness** — 25 specs in theme + 15 in plugin; spot-checked 3 (cycle-audit findings, v5.0.0 scope, plugin-absorption roadmap). A full audit-by-spec would re-verify each design doc's "shipped" claim against actual commit history. Out of budget.
- **GitHub Actions workflow file currency** — `.github/workflows/deploy.yml` in both repos. CLAUDE.md says theme is `workflow_dispatch:` only (HYG-01 subfinding); verifying that against the file's actual current `on:` block was not done. Mentioned for completeness.
- **PHP composer/composer.lock dependency currency** — both repos have `composer.json` + `composer.lock`. Dependency staleness audit not in scope.

---

## 9. Tooling notes

`gh` calls **succeeded** (issues + PRs queried on both repos, returned 0/0). No sandbox blocker hit during this audit. All evidence is from source-side reads + grep + filesystem inspection.

No source files were modified. Only this findings doc was written.

---

## 10. One-line summary

**Project-hygiene audit on v4.4.3 + v9.4.3 found zero bugs and zero security issues; three HIGH severity doc-drift findings (CLAUDE.md phase-status sentence, theme readme.txt ~60 versions + a redesign behind, plugin lacks readme.txt + `Tested up to:` header), five MEDIUM findings (stale `project_architecture.md` memory entry, `feedback_no_dashboard_widgets` rule-vs-code contradiction, abilities-registration docblock wrong on total + missing pattern-adoption entry, orphan memory file `feedback_no_brutalist_in_admin_ui.md` not indexed in MEMORY.md, `sn_admin_pages` mislabeled `@deprecated`), and six observations. Zero TODO/FIXME, zero orphan files, zero open GitHub issues/PRs. All findings are doc-only patches — no version bump required for any.**
