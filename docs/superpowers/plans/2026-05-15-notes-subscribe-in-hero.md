# v8.1.0 — `/notes` subscribe info nested in hero — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Move the subscribe info inside `<header class="sn-notes-hero">` as a single compact `<p class="sn-notes-subscribe">` element, drop the standalone `<footer class="sn-notes-feed">` block, and swap the supporting CSS so the page hero gets a proper visual hierarchy and the pillar essays section returns to column 2 of the `.sn-notes-top` 5fr/7fr grid.

**Architecture:** Single PHP file (`inc/page-notes-render.php`) carries both the markup and the inline `<style>` block — all visual changes happen there. Companion edits: version bump in `style.css`, CHANGELOG entry, session-note append, and a `.gitignore` add for `.superpowers/` (brainstorm artifacts directory). No new files. No build step. The change ships through the standard `repo → push to main → WP self-updater → admin-side Update click → live` flow.

**Tech Stack:** PHP, HTML, CSS (inline `<style>` in PHP). WordPress FSE theme. Cloudways hosting. Git for version control. No test framework — verification is structural grep + curl + visual eyeball.

**Spec:** [docs/superpowers/specs/2026-05-15-notes-subscribe-in-hero-design.md](../specs/2026-05-15-notes-subscribe-in-hero-design.md)

---

## File structure

| File | What changes |
| --- | --- |
| `inc/page-notes-render.php` | Markup: add `<p class="sn-notes-subscribe">` inside hero, remove `<footer class="sn-notes-feed">` block. CSS (inline `<style>`): remove `.sn-notes-feed-*` rules, add `.sn-notes-subscribe` and `.sn-notes-cursor` rules, rename selector in reduced-motion media query. |
| `style.css` | Version header `8.0.7` → `8.1.0` |
| `CHANGELOG.md` | New `## [8.1.0]` entry at top with cap-rollover note |
| `_claude/notes/2026-05-15-sync-repo-to-live-and-add-email-rss-line.md` | Append "Companion: v8.1.0 — subscribe-in-hero" section |
| `.gitignore` | Add `.superpowers/` entry if not present (brainstorm artifacts directory) |

No files created, no files deleted. The two spec-doc edits (write new spec + add SUPERSEDED banner to v8.0.7 spec) were already shipped in commit `5639623` — not part of this plan.

---

## Task 1: Add the new `<p class="sn-notes-subscribe">` element inside the hero

**Files:**
- Modify: `inc/page-notes-render.php` — markup region around line 591 (just inside `</header>` close)

- [ ] **Step 1.1: Verify the current `</header>` line is in the expected position**

Run:
```bash
grep -n '</header>' inc/page-notes-render.php
```
Expected: Single match, around line 591, content `\t\t</header>` (2 tabs of indent).

- [ ] **Step 1.2: Verify the current `<p class="sn-notes-meta">` block is in the expected form**

Run:
```bash
sed -n '584,591p' inc/page-notes-render.php
```
Expected output (tab characters shown as actual tabs in the file):
```php
			<p class="sn-notes-meta">
				<span><?php echo esc_html( sprintf( _n( '%d entry', '%d entries', $entry_count, 'signal-noise' ), $entry_count ) ); ?></span>
				<?php if ( $latest_date ) : ?>
					<span class="sn-notes-meta-bullet" aria-hidden="true">&middot;</span>
					<span>Last updated <?php echo esc_html( $latest_date ); ?></span>
				<?php endif; ?>
			</p>
		</header>
```

- [ ] **Step 1.3: Insert the new `<p class="sn-notes-subscribe">` element between `</p>` (closing the meta paragraph) and `</header>`**

Use the Edit tool with these exact strings (3 tabs for the new `<p>`, 4 tabs for its content lines if any — but in this case the content is on one line):

`old_string` (the `</p>` of the meta + the `</header>`):
```
			</p>
		</header>
```

`new_string` (insert the subscribe paragraph between them):
```
			</p>
			<p class="sn-notes-subscribe">
				No subscription form. No schedule. Notes via <a href="/notes/feed/">RSS</a>, or via email through <a href="https://blogtrottr.com/" target="_blank" rel="noopener noreferrer">Blogtrottr</a> or <a href="https://www.feedrabbit.com/" target="_blank" rel="noopener noreferrer">Feedrabbit</a>.<span class="sn-notes-cursor" aria-hidden="true"></span>
			</p>
		</header>
```

- [ ] **Step 1.4: Verify the new element is in place**

Run:
```bash
grep -nE 'sn-notes-subscribe|sn-notes-cursor' inc/page-notes-render.php
```
Expected: At least 2 matches (the markup line + the closing `</p>`-adjacent context). The line containing `<p class="sn-notes-subscribe">` should be inside the hero region (near line 591–592).

- [ ] **Step 1.5: NO commit yet** — multiple coupled changes follow; commit after the whole task list completes.

---

## Task 2: Remove the standalone `<footer class="sn-notes-feed">` block (the v8.0.7 placement)

**Files:**
- Modify: `inc/page-notes-render.php` — markup region around lines 593–599 (the v8.0.7 footer that lives inside `.sn-notes-top` between `</header>` and `<section class="sn-notes-pillars-section">`)

- [ ] **Step 2.1: Verify the current `<footer class="sn-notes-feed">` block exists at the expected position**

Run:
```bash
grep -nE '<footer class="sn-notes-feed"|</footer>' inc/page-notes-render.php
```
Expected: Two matches — one `<footer class="sn-notes-feed" aria-label="RSS feed">` opener around line 593–595 and one `</footer>` closer around line 599–601 (line numbers may have shifted by 4 from the Task 1 insertion). Both should be inside `.sn-notes-top` (indent: 2 tabs on `<footer>` and `</footer>`).

- [ ] **Step 2.2: Remove the entire `<footer class="sn-notes-feed">` block**

Use the Edit tool with these exact strings:

`old_string` (the full footer block + the blank line that follows + the pillars-section opener — large enough context to be unique):
```
		</header>

		<footer class="sn-notes-feed" aria-label="RSS feed">
			<p class="sn-notes-feed-status">
				Feed &mdash; <a href="/notes/feed/">/notes/feed/</a><span class="sn-notes-feed-cursor" aria-hidden="true"></span>
			</p>
			<p class="sn-notes-feed-note">No subscription form. No schedule. Notes available via RSS.</p>
			<p class="sn-notes-feed-note">For email, pipe the <a href="/notes/feed/">feed</a> through <a href="https://blogtrottr.com/" target="_blank" rel="noopener noreferrer">Blogtrottr</a> or <a href="https://www.feedrabbit.com/" target="_blank" rel="noopener noreferrer">Feedrabbit</a>.</p>
		</footer>

		<section class="sn-notes-pillars-section" aria-labelledby="sn-pillars-heading">
```

`new_string` (collapse to just the hero close + blank line + pillars opener):
```
		</header>

		<section class="sn-notes-pillars-section" aria-labelledby="sn-pillars-heading">
```

- [ ] **Step 2.3: Verify the footer block is gone**

Run:
```bash
grep -cE '<footer class="sn-notes-feed"' inc/page-notes-render.php
```
Expected: `0` (zero matches — the element no longer exists in the file).

Run:
```bash
grep -nE 'sn-notes-pillars-section' inc/page-notes-render.php | head -3
```
Expected: First match should be `<section class="sn-notes-pillars-section" aria-labelledby="sn-pillars-heading">` directly after `</header>` (separated by blank line) — confirming pillars is now the second `.sn-notes-top` child.

---

## Task 3: Remove the `.sn-notes-feed-*` CSS rule block from the inline `<style>`

**Files:**
- Modify: `inc/page-notes-render.php` — CSS region around lines 486–543 (the `/* RSS FEED FOOTER — terminal status line */` comment-block-fenced section)

- [ ] **Step 3.1: Verify the CSS block exists at the expected position**

Run:
```bash
grep -nE '/\* RSS FEED FOOTER|\.sn-notes-feed[^-]|\.sn-notes-feed-' inc/page-notes-render.php
```
Expected: 8–10 matches starting around line 486 (the `/* RSS FEED FOOTER */` comment) and continuing through `.sn-notes-feed-note a:hover` around line 543.

- [ ] **Step 3.2: Remove the entire `.sn-notes-feed-*` CSS block, keeping `@keyframes sn-blink`**

The block to remove starts at `/* RSS FEED FOOTER — terminal status line ─` and ends at the closing `}` of `.sn-notes-feed-note a:hover`. **Keep** the `@keyframes sn-blink` rule (it's referenced by the new `.sn-notes-cursor`).

Read the exact text first:
```bash
sed -n '485,545p' inc/page-notes-render.php
```

Use the Edit tool with the exact text from that read as `old_string`. The `new_string` is the empty equivalent of that section — just the surrounding context (the closing `}` of the previous rule + a blank line + the `/* PAGE ENTRY ANIMATION` comment that follows). Specifically, identify the rule that immediately precedes the `/* RSS FEED FOOTER` comment and the rule that immediately follows the last `.sn-notes-feed-*` selector — both stay in place; only the in-between is removed.

**Important:** if the `@keyframes sn-blink` rule is INSIDE the block being removed (verify via the read above), preserve it by keeping its lines in `new_string`. Per the spec, `@keyframes sn-blink` must remain because `.sn-notes-cursor` references it.

- [ ] **Step 3.3: Verify the `.sn-notes-feed-*` selectors are gone**

Run:
```bash
grep -cE '\.sn-notes-feed[^-]|\.sn-notes-feed-' inc/page-notes-render.php
```
Expected: `0`.

Run:
```bash
grep -nE '@keyframes sn-blink|sn-blink' inc/page-notes-render.php
```
Expected: At least 2 matches — the `@keyframes sn-blink {` definition and any references to `sn-blink` in animation properties.

---

## Task 4: Add the new `.sn-notes-subscribe` and `.sn-notes-cursor` CSS rules

**Files:**
- Modify: `inc/page-notes-render.php` — CSS region where the removed `.sn-notes-feed-*` block used to live (around the original line 486 area, now empty)

- [ ] **Step 4.1: Identify insertion point**

The new rules go where the old block was. They sit logically between the rule that came before (`.sn-notes-rule` is around line 251 — too far up, so the right neighbor is whatever rule preceded the removed block) and `/* PAGE ENTRY ANIMATION */` (which followed the removed block).

Run:
```bash
grep -nE '/\* PAGE ENTRY ANIMATION|@keyframes sn-blink' inc/page-notes-render.php
```
Use the line number for `/* PAGE ENTRY ANIMATION */` as the boundary — insertion point is just before it.

- [ ] **Step 4.2: Insert the new CSS rules**

Use the Edit tool. `old_string` is just the marker line (the comment that follows the deleted region):
```
/* PAGE ENTRY ANIMATION — staggered reveal on first paint */
```

`new_string` adds the new rules immediately above, separated by a blank line:
```
/* SUBSCRIBE NOTE — compact colophon nested in the hero column.
   Tertiary in the hero hierarchy (title > dek > meta > subscribe).
   Same DM Mono / 0.7rem / uppercase as eyebrow + meta; inline links
   in blood. Cursor terminates the sentence as a "live system" beat
   inherited from the previous footer aesthetic. */

.sn-notes-subscribe {
	margin: 1.25rem 0 0;
	font-family: 'DM Mono', 'Courier New', monospace;
	font-size: 0.7rem;
	letter-spacing: 0.18em;
	text-transform: uppercase;
	line-height: 1.7;
	color: var(--wp--preset--color--rust, #666);
	max-width: 48ch;
}
.sn-notes-subscribe a {
	color: var(--wp--preset--color--blood, #e00404);
	text-decoration: none;
	border-bottom: 1px solid transparent;
	transition: border-color 0.2s ease;
}
.sn-notes-subscribe a:hover {
	border-bottom-color: var(--wp--preset--color--blood, #e00404);
}
.sn-notes-cursor {
	display: inline-block;
	width: 0.4em;
	height: 0.95em;
	background: var(--wp--preset--color--blood, #e00404);
	margin-left: 0.4em;
	vertical-align: -0.1em;
	animation: sn-blink 1.05s steps(2, end) infinite;
}

/* PAGE ENTRY ANIMATION — staggered reveal on first paint */
```

- [ ] **Step 4.3: Verify new CSS is in place**

Run:
```bash
grep -nE '\.sn-notes-subscribe[^-]|\.sn-notes-subscribe |\.sn-notes-cursor' inc/page-notes-render.php
```
Expected: At least 4 matches — the four selectors `.sn-notes-subscribe`, `.sn-notes-subscribe a`, `.sn-notes-subscribe a:hover`, `.sn-notes-cursor`.

---

## Task 5: Update the reduced-motion media query

**Files:**
- Modify: `inc/page-notes-render.php` — the `@media (prefers-reduced-motion: reduce)` block, around lines 545–551 (will have shifted)

- [ ] **Step 5.1: Locate the current rule**

Run:
```bash
grep -nE 'prefers-reduced-motion|sn-notes-feed-cursor|sn-notes-cursor' inc/page-notes-render.php
```
Expected: One match for `@media (prefers-reduced-motion: reduce)` and one match for `.sn-notes-feed-cursor { animation: none; opacity: 0.6; }` inside that block.

- [ ] **Step 5.2: Rename the selector inside the reduced-motion block**

Use the Edit tool:

`old_string`:
```
	.sn-notes-feed-cursor { animation: none; opacity: 0.6; }
```

`new_string`:
```
	.sn-notes-cursor { animation: none; opacity: 0.6; }
```

- [ ] **Step 5.3: Verify the rename landed and no stale `.sn-notes-feed-cursor` remains anywhere**

Run:
```bash
grep -nE '\.sn-notes-feed-cursor' inc/page-notes-render.php
```
Expected: `(no matches)` — zero references to the old class name across the entire file (markup AND CSS).

Run:
```bash
grep -cE '\.sn-notes-cursor' inc/page-notes-render.php
```
Expected: `2` — the rule definition (Task 4) plus the reduced-motion override (this task).

---

## Task 6: Bump version, CHANGELOG, session note, .gitignore

- [ ] **Step 6.1: Bump `style.css` Version header**

Use the Edit tool:

`file_path`: `style.css`
`old_string`: `Version: 8.0.7`
`new_string`: `Version: 8.1.0`

Verify:
```bash
grep '^Version:' style.css
```
Expected: `Version: 8.1.0`

- [ ] **Step 6.2: Add `## [8.1.0]` CHANGELOG entry at top**

Use the Edit tool to insert a new entry above the existing `## [8.0.7]` heading. The new entry must include:

1. Title: `## [8.1.0] — Notes subscribe info nested in hero (cap rollover from 8.0.7; not a new capability)`
2. Brief context paragraph about what changed and WHY (v8.0.7's column-2 placement broke hero hierarchy; this iteration nests the subscribe info inside the hero column).
3. `### Changed` section listing:
   - `inc/page-notes-render.php`: removed `<footer class="sn-notes-feed">` block, added `<p class="sn-notes-subscribe">` inside hero, removed `.sn-notes-feed-*` CSS, added `.sn-notes-subscribe` + `.sn-notes-cursor` CSS, renamed reduced-motion selector.
4. `### Why minor (cap rollover)` section explaining: change is patch-shaped (UX calibration, no new capability, no breaking API), but the v8.0 patch cap (7-per-minor) was exhausted at v8.0.7, so per project versioning rules this rolls to v8.1.0. The minor digit reflects the cap rollover, not a new feature.
5. `### Migration` section: none required — placement-only change.

`old_string`:
```
All notable changes to Signal & Noise are documented here.

## [8.0.7] — Relocate /notes feed footer above the fold (move-and-replace)
```

`new_string`:
```
All notable changes to Signal & Noise are documented here.

## [8.1.0] — Notes subscribe info nested in hero (cap rollover from 8.0.7; not a new capability)

The v8.0.7 placement put the `<footer class="sn-notes-feed">` block in column 2 of the `.sn-notes-top` 5fr/7fr grid (because adding a third grid child to a 2-column grid placed it where the pillar essays section had been, displacing pillars to a second row). The visual result was co-equality with the hero — nothing read as the focal point. This release nests the subscribe info inside `<header class="sn-notes-hero">` as a single compact `<p>`, drops the standalone footer block, and lets the pillars section return to column 2 of the grid.

### Changed

- **[`inc/page-notes-render.php`](inc/page-notes-render.php) — markup.** Removed the `<footer class="sn-notes-feed">` block (was at the top of `.sn-notes-top` between hero and pillars after v8.0.7). Added `<p class="sn-notes-subscribe">` as the last child of `<header class="sn-notes-hero">`, with a `<span class="sn-notes-cursor">` blinking-cursor span at the sentence end. Single sentence: *"No subscription form. No schedule. Notes via RSS, or via email through Blogtrottr or Feedrabbit."* Three inline links (RSS internal, Blogtrottr + Feedrabbit external with `target="_blank" rel="noopener noreferrer"`).
- **[`inc/page-notes-render.php`](inc/page-notes-render.php) — CSS.** Removed the entire `.sn-notes-feed-*` rule block (`.sn-notes-feed`, `.sn-notes-feed-status`, `.sn-notes-feed-status a`, `.sn-notes-feed-status a:hover`, `.sn-notes-feed-cursor`, `.sn-notes-feed-note`, `.sn-notes-feed-note + .sn-notes-feed-note`, `.sn-notes-feed-note a`, `.sn-notes-feed-note a:hover`). Added `.sn-notes-subscribe`, `.sn-notes-subscribe a`, `.sn-notes-subscribe a:hover`, and `.sn-notes-cursor`. Renamed the selector inside `@media (prefers-reduced-motion: reduce)` from `.sn-notes-feed-cursor` to `.sn-notes-cursor`. The `@keyframes sn-blink` rule is preserved (referenced by the new cursor class).
- **Layout restored.** Pillar essays section now occupies column 2 of the desktop 5fr/7fr grid as it did in v8.0.6 and prior. The two-row layout introduced by v8.0.7 is gone.

### Why minor (cap rollover, not a new capability)

This change is patch-shaped — UX calibration, no new feature, no breaking API change, no migration. But v8.0.7 used the 7th and final patch slot in the v8.0 minor (per the project's 7-per-minor cap documented in [docs/VERSIONING.md](docs/VERSIONING.md)). The cap forces a roll to **v8.1.0**. Future-readers: the minor-digit bump reflects the cap rollover, not a new capability — read the `### Changed` section above for what actually shipped.

### Migration

None required. Placement-only change. Existing RSS subscribers unaffected. The `<footer class="sn-notes-feed">` element no longer exists in the rendered HTML; any external CSS or JS that selected it would break, but no external code does.

### Spec

[docs/superpowers/specs/2026-05-15-notes-subscribe-in-hero-design.md](docs/superpowers/specs/2026-05-15-notes-subscribe-in-hero-design.md). Authored via the `superpowers:brainstorming` skill flow with the visual companion this round; supersedes the v8.0.7 spec which is preserved on disk with a SUPERSEDED banner.

## [8.0.7] — Relocate /notes feed footer above the fold (move-and-replace)
```

- [ ] **Step 6.3: Append "Companion v8.1.0" section to the session note**

Use the Edit tool on `_claude/notes/2026-05-15-sync-repo-to-live-and-add-email-rss-line.md`. Append after the existing "Companion: v8.0.7" section.

`old_string` (the very end of the existing v8.0.7 companion section — pick a unique closing paragraph):
```
`8.0.7` is the last patch in v8.0 per the project's 7-per-minor cap. Any further bump in this branch rolls to `8.1.0`. Documented in the CHANGELOG entry's "Why patch + cap note" section.
```

`new_string`:
```
`8.0.7` is the last patch in v8.0 per the project's 7-per-minor cap. Any further bump in this branch rolls to `8.1.0`. Documented in the CHANGELOG entry's "Why patch + cap note" section.

## Companion: v8.1.0 — subscribe-in-hero (immediate post-v8.0.7 redesign)

v8.0.7 shipped and the user rejected the visual result on first viewing. The screenshot showed the `<footer class="sn-notes-feed">` block in the right column of the `.sn-notes-top` grid (because adding a third child to a 5fr/7fr 2-column grid placed it where pillars had been). The hero and the feed read as co-equal columns; nothing had focal-point weight.

### Root-cause lesson (recorded for future-me)

The v8.0.7 spec analyzed the markup change in isolation — moving a `<footer>` block from one position to another — without analyzing how `.sn-notes-top`'s 5fr/7fr grid would treat a third grid child. The grid was the load-bearing layout primitive, and it got skipped in the design pass. **Anti-pattern to avoid: brainstorming markup-position decisions without first reading the CSS that lays them out.** The `superpowers:brainstorming` skill's "explore project context" step should explicitly include reading the CSS for the surrounding layout when proposing markup-position changes.

### Brainstorming v2 (`superpowers:brainstorming` + visual companion)

This iteration accepted the visual companion (declined the first round, which contributed to the bad call). Three options were mocked up at full layout fidelity (replicating the 5fr/7fr grid with brand fonts and colors). User chose Option A (compact subscribe note inside hero, blinking cursor preserved at sentence end). User then refined: keep the two-column grid intact (pillars in col 2), put the subscribe sentence INSIDE the hero column.

Spec at [docs/superpowers/specs/2026-05-15-notes-subscribe-in-hero-design.md](../../docs/superpowers/specs/2026-05-15-notes-subscribe-in-hero-design.md). v8.0.7 spec marked as SUPERSEDED (preserved on disk as a record of the iteration).

Implementation followed `superpowers:writing-plans` skill flow with the plan at [docs/superpowers/plans/2026-05-15-notes-subscribe-in-hero.md](../../docs/superpowers/plans/2026-05-15-notes-subscribe-in-hero.md).

### Files changed (v8.1.0)

| File | Change |
| --- | --- |
| [inc/page-notes-render.php](../../inc/page-notes-render.php) | Removed `<footer class="sn-notes-feed">` block + `.sn-notes-feed-*` CSS; added `<p class="sn-notes-subscribe">` inside hero + `.sn-notes-subscribe` + `.sn-notes-cursor` CSS; renamed selector in reduced-motion media query. |
| [style.css](../../style.css) | `Version: 8.0.7` → `Version: 8.1.0`. |
| [CHANGELOG.md](../../CHANGELOG.md) | New 8.1.0 entry at top with explicit "rollover, not a new capability" note. |
| [.gitignore](../../.gitignore) | Added `.superpowers/` (brainstorm artifacts directory) if not already present. |
| [docs/superpowers/specs/2026-05-15-notes-feed-relocation-design.md](../../docs/superpowers/specs/2026-05-15-notes-feed-relocation-design.md) | SUPERSEDED banner at top (committed in `5639623`). |
| [docs/superpowers/specs/2026-05-15-notes-subscribe-in-hero-design.md](../../docs/superpowers/specs/2026-05-15-notes-subscribe-in-hero-design.md) | New spec (committed in `5639623`). |
| [docs/superpowers/plans/2026-05-15-notes-subscribe-in-hero.md](../../docs/superpowers/plans/2026-05-15-notes-subscribe-in-hero.md) | New plan (committed in this release commit). |

### v8.0 cap rollover

v8.0.7 used patch slot 7 of 7. v8.1.0 is the cap-rollover release — patch-shaped change, minor-digit bump because the patch cap forced it. Documented in CHANGELOG.
```

- [ ] **Step 6.4: Add `.superpowers/` to `.gitignore` if not present**

Run:
```bash
grep -E '^\.superpowers' .gitignore 2>/dev/null && echo "ALREADY PRESENT" || echo "MISSING"
```

If "MISSING", append:
```bash
echo '' >> .gitignore
echo '# Brainstorm artifacts (visual companion mockups, session state)' >> .gitignore
echo '.superpowers/' >> .gitignore
```

Verify:
```bash
grep -nE '\.superpowers' .gitignore
```
Expected: At least one match for the new entry.

If `.gitignore` doesn't exist, create it with just the `.superpowers/` rule:
```bash
test -f .gitignore || (echo '# Brainstorm artifacts (visual companion mockups, session state)' > .gitignore && echo '.superpowers/' >> .gitignore)
```

---

## Task 7: Pre-commit verification

- [ ] **Step 7.1: Working-tree summary**

Run:
```bash
git status
git diff --stat
```
Expected: 4 or 5 files modified (inc/page-notes-render.php, style.css, CHANGELOG.md, _claude/notes/...md, optionally .gitignore). No untracked files relevant to this commit other than the plan doc itself.

- [ ] **Step 7.2: Structural sanity check on the edited PHP**

Run:
```bash
echo "=== Subscribe element present (should be 1)"
grep -cE '<p class="sn-notes-subscribe"' inc/page-notes-render.php

echo "=== Old footer absent (should be 0)"
grep -cE '<footer class="sn-notes-feed"' inc/page-notes-render.php

echo "=== Old CSS classes absent (should be 0)"
grep -cE '\.sn-notes-feed[^-]|\.sn-notes-feed-' inc/page-notes-render.php

echo "=== New CSS classes present (should be >= 4)"
grep -cE '\.sn-notes-subscribe[^-]|\.sn-notes-subscribe |\.sn-notes-cursor' inc/page-notes-render.php

echo "=== sn-blink keyframe preserved (should be >= 2)"
grep -cE '@keyframes sn-blink|sn-blink' inc/page-notes-render.php

echo "=== Reduced-motion rule renamed (old absent, new present)"
grep -cE '\.sn-notes-feed-cursor' inc/page-notes-render.php
grep -cE '\.sn-notes-cursor' inc/page-notes-render.php
```

If any check disagrees with its expectation, halt and investigate before committing.

- [ ] **Step 7.3: PHP syntax check**

Run:
```bash
php -l inc/page-notes-render.php
```
Expected: `No syntax errors detected in inc/page-notes-render.php`. If PHP isn't installed locally, skip this step (the WP server will catch syntax errors on next request).

- [ ] **Step 7.4: Confirm version, CHANGELOG, and gitignore changes**

Run:
```bash
grep '^Version:' style.css
head -3 CHANGELOG.md | tail -1
grep -E '\.superpowers' .gitignore 2>/dev/null
```
Expected:
- `Version: 8.1.0`
- `## [8.1.0] — Notes subscribe info nested in hero (cap rollover from 8.0.7; not a new capability)`
- `.superpowers/` (or similar) present in .gitignore

---

## Task 8: Commit, push, tag, push tag

- [ ] **Step 8.1: Stage the changed files explicitly**

```bash
git add \
  inc/page-notes-render.php \
  style.css \
  CHANGELOG.md \
  _claude/notes/2026-05-15-sync-repo-to-live-and-add-email-rss-line.md \
  .gitignore \
  docs/superpowers/plans/2026-05-15-notes-subscribe-in-hero.md
```

(Add `.gitignore` only if Task 6.4 modified it. Add the plan doc — it should be tracked alongside the implementation.)

- [ ] **Step 8.2: Commit with the conventional message**

```bash
git commit -m "$(cat <<'EOF'
v8.1.0: notes subscribe info nested in hero (cap rollover from 8.0.7; not a new capability)

The v8.0.7 placement put <footer class="sn-notes-feed"> in column 2
of the .sn-notes-top 5fr/7fr grid (auto-placement behavior of a 3rd
child in a 2-column grid), creating visual co-equality with the hero
and demoting pillars to a second row. This release moves the subscribe
info INSIDE <header class="sn-notes-hero"> as a single compact
<p class="sn-notes-subscribe"> below the meta line. Pillars section
returns to column 2 of the grid because there are again only two
top-level .sn-notes-top children.

Markup: removed <footer class="sn-notes-feed"> block; added
<p class="sn-notes-subscribe"> with a <span class="sn-notes-cursor">
blinking-cursor span at the sentence end. Single sentence folds the
disclaimer + bridge mention. Three inline links (RSS internal,
Blogtrottr + Feedrabbit external w/ rel=noopener noreferrer).

CSS: removed the entire .sn-notes-feed-* rule block; added
.sn-notes-subscribe + .sn-notes-cursor; renamed selector in the
reduced-motion media query. @keyframes sn-blink preserved (still
referenced by the new cursor class).

Cap rollover: v8.0.7 used patch slot 7 of 7. This change is
patch-shaped (UX calibration, no new capability, no breaking API)
but ships as v8.1.0 because the cap forces it. Per project versioning
rules; documented in CHANGELOG.

Designed via superpowers:brainstorming + visual-companion. Spec at
docs/superpowers/specs/2026-05-15-notes-subscribe-in-hero-design.md.
Plan at docs/superpowers/plans/2026-05-15-notes-subscribe-in-hero.md.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

Verify:
```bash
git log -1 --oneline
```
Expected: New commit at HEAD with subject starting `v8.1.0: notes subscribe info nested in hero...`

- [ ] **Step 8.3: Push HEAD to origin/main (normal operation — repo to live web)**

```bash
git push origin HEAD:main
```
Expected output: includes `<previous-sha>..<new-sha>  HEAD -> main` with no rejection.

This commit, plus the standalone spec commit `5639623` (which has been waiting), will both fast-forward to origin/main in this push.

- [ ] **Step 8.4: Create and push the annotated tag**

```bash
git tag -a v8.1.0 -m "v8.1.0 — notes subscribe info nested in hero (cap rollover from 8.0.7; not a new capability)"
git push origin v8.1.0
```
Expected output for second command: `[new tag] v8.1.0 -> v8.1.0`.

- [ ] **Step 8.5: Verify origin state**

```bash
git ls-remote --tags origin | grep v8.1.0
git status
```
Expected:
- One tag entry (the annotated tag) and optionally a `v8.1.0^{}` dereferenced entry pointing at the same commit
- Working tree clean, branch up to date with origin/main

---

## Task 9: Live verification + visual companion cleanup

- [ ] **Step 9.1: Wait briefly for deploy chain**

Per past sessions, Cloudways picks up `inc/*.php` changes within ~30 seconds to a few minutes (varies). The WP self-updater offers the new version in `/wp-admin/` within ~30 seconds of the next admin pageview.

- [ ] **Step 9.2: Curl-check the live page once it has shipped**

```bash
echo "=== Confirm new subscribe element on live"
curl -sS -A "Mozilla/5.0 (verify-claude-code)" "https://juanlentino.com/notes/" 2>/dev/null \
  | grep -oE '<p class="sn-notes-subscribe"[^>]*>[^<]*(?:<[^/][^>]*>[^<]*</[^>]+>[^<]*)*</p>' | head -1

echo ""
echo "=== Confirm old footer absent on live (should be 0)"
curl -sS -A "Mozilla/5.0 (verify-claude-code)" "https://juanlentino.com/notes/" 2>/dev/null \
  | grep -cE '<footer class="sn-notes-feed"'
```

If the new element doesn't appear yet, the deploy hasn't completed — note for the user that they may need to click Update in `/wp-admin/` to pull the PHP change.

- [ ] **Step 9.3: Stop the visual companion server**

Find and stop the server:
```bash
ls -d .superpowers/brainstorm/*/ 2>/dev/null | tail -1
# Find the server-info file:
cat .superpowers/brainstorm/*/state/server-info 2>/dev/null
# Stop it (use the stop-server.sh script with the session dir):
/Users/juanlentino/.claude/plugins/cache/superpowers-marketplace/superpowers/5.0.7/skills/brainstorming/scripts/stop-server.sh \
  "$(ls -d .superpowers/brainstorm/*/ 2>/dev/null | tail -1)"
```

If stop-server.sh isn't available, find the PID from `server-info` and `kill -TERM <PID>`. The server also auto-exits after 30 minutes of inactivity, so this is just hygiene.

---

## Self-review

**Spec coverage** (skim the spec, point each requirement at a task):
- ✓ Decision: subscribe info inside `<header class="sn-notes-hero">` — Task 1
- ✓ Drop the standalone `<footer class="sn-notes-feed">` element — Task 2
- ✓ Pillars restored to col 2 (automatic via grid behavior) — verified in Task 7.2
- ✓ Markup: `<p class="sn-notes-subscribe">` + `<span class="sn-notes-cursor">` — Task 1
- ✓ CSS: add `.sn-notes-subscribe` + `.sn-notes-cursor` — Task 4
- ✓ CSS: remove `.sn-notes-feed-*` block — Task 3
- ✓ CSS: keep `@keyframes sn-blink` — Task 3, verified Task 7.2
- ✓ CSS: rename selector in reduced-motion media query — Task 5
- ✓ Single-sentence subscribe copy with three inline links — Task 1
- ✓ External link `target="_blank" rel="noopener noreferrer"` — Task 1
- ✓ FSE fallback `templates/page-notes.html` NOT touched — out of scope, no task touches it
- ✓ Version bump 8.0.7 → 8.1.0 — Task 6.1
- ✓ CHANGELOG entry with rollover note — Task 6.2
- ✓ Session note append — Task 6.3
- ✓ Verification via grep + curl — Tasks 7 and 9.2

No coverage gaps.

**Placeholder scan:** No "TBD", no "TODO", no "implement appropriately." Every step has either exact `old_string`/`new_string` content or exact commands with expected output. Task 3.2 says "use the exact text from the read above" — this is intentional because the precise indentation and surrounding content depend on what's in the file at that moment; the engineer is told what to read and what bounds to use.

**Type/name consistency:**
- `.sn-notes-subscribe` used consistently across Tasks 1, 4, 7.2 ✓
- `.sn-notes-cursor` used consistently across Tasks 1, 4, 5, 7.2 ✓
- `<p class="sn-notes-subscribe">` not `<div>` — consistent with spec ✓
- Cursor class name `sn-notes-cursor` (generic), not `sn-notes-subscribe-cursor` — matches spec decision ✓
- `@keyframes sn-blink` name unchanged — matches spec ✓

No bugs found. Plan ready.
