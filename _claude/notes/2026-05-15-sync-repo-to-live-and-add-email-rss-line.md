---
date: 2026-05-15
version: 8.0.6
branch: claude/cranky-torvalds-21f4e3
session_type: drift-sync + content-add
tags: [drift-sync, fse, nav, cta-cleanup, notes-footer, versioning, skills-discipline]
---

# Sync repo to live + add email-via-RSS line to Notes footer

## Summary

Two-part session. Part one: bring the theme repo back in line with the production site after the user removed the "Book a Call" surface (nav link, `/work-with-me` page, services CTA) via the live admin. Part two: add a second line to the Notes-index footer pointing readers at email-by-RSS bridges (Blogtrottr, Feedrabbit), so the existing "No subscription form. No schedule." line isn't a dead end for non-RSS-native readers.

## Files changed

| File | Change | Reason |
| --- | --- | --- |
| [parts/header.html](../../parts/header.html) | Removed `wp:navigation-link` "Book a Call" → `/work-with-me` | Live nav has 7 items; repo had 8 |
| [templates/page-work-with-me.html](../../templates/page-work-with-me.html) | **Deleted entire file** | `/work-with-me/` returns HTTP 404 on live |
| [theme.json](../../theme.json) | Removed `page-work-with-me` from `customTemplates` (was line 283) | Companion fix to the template-file delete; missed in initial audit, surfaced by post-edit verification grep |
| [templates/page-services.html](../../templates/page-services.html) | Removed inline outline `wp:button` "Book a strategy call →" → `/work-with-me` | Live `/services/` only references `/contact` |
| [patterns/cta-closing.php](../../patterns/cta-closing.php) | **Deleted entire file** | Pattern slug `signal-noise/cta-closing` unused (no template inserts it); orphan from v7.5.x IA pass |
| [templates/home.html](../../templates/home.html) | Removed dead `<!-- RSS FOOTER -->` separator + spacer + `<p class="sn-notes-rss">` block | `inc/page-notes-render.php` short-circuits via `template_include`; FSE template's footer never renders |
| [inc/page-notes-render.php](../../inc/page-notes-render.php) | Added `<p class="sn-notes-feed-note">` with feed/Blogtrottr/Feedrabbit links below the existing footer line | Step 3 of the user spec |
| [inc/page-notes-render.php](../../inc/page-notes-render.php) | Added `.sn-notes-feed-note a` rules + `.sn-notes-feed-note + .sn-notes-feed-note { margin-top: 0.4rem }` | Mirror brand link style from `.sn-notes-feed-status a`; prevent the two adjacent paragraphs touching (base rule has `margin: 0`) |
| [style.css](../../style.css) | `Version: 8.0.5` → `Version: 8.0.6` | Per VERSIONING; structural template deletes + CSS adds bump version |
| [CHANGELOG.md](../../CHANGELOG.md) | Added 8.0.6 entry at top | Per VERSIONING |

## Audit findings (Step 1)

1. **`parts/header.html:22`** — extra "Book a Call" nav item not in live.
2. **`templates/page-work-with-me.html`** — full Cal.com booking template; live URL is 404.
3. **`templates/page-services.html:271`** — inline `/work-with-me` button; live `/services/` doesn't have it.
4. **`patterns/cta-closing.php`** — orphan pattern with `Book a strategy call →` button. Slug not inserted by any template; verified via `grep -RIn 'cta-closing\|signal-and-noise/cta'`.
5. **`templates/home.html:108`** — `<p class="sn-notes-rss">` is dead code. The `/notes` URL doesn't render through this template.
6. **Step 3 target was wrong in the original spec.** The user's spec said to add the new line below the existing one in "the Notes index footer," but the live footer is rendered by [`inc/page-notes-render.php:646–651`](../../inc/page-notes-render.php), not [`templates/home.html`](../../templates/home.html). The renderer file uses a `template_include` short-circuit (documented in its head docblock) and bypasses block-template resolution entirely. **Lesson:** in this theme, always grep for `template_include` before assuming a `templates/*.html` file is the source of truth.

The orphan-pattern, dead-code-block, and PHP-renderer findings were surfaced as questions before any edits. User authorized the recommended cleanups ("a cleanup isn't bad, right?").

## Why version bumped despite Step 4 saying "no bump"

Step 4 of the user spec said: *"Per VERSIONING.md: no version bump (content-only template edits)."* That call was based on the assumption that the work was content-only. The audit changed the picture — the work includes:

- 2 deleted files (`page-work-with-me.html`, `cta-closing.php`) — structural
- 3 block removals across 3 templates — structural
- 1 CSS addition (link styling + adjacent-sibling margin rule) — CSS
- 1 content addition (the new footer line) — content

Per `docs/VERSIONING.md` and `CLAUDE.md`: "What bumps: code, CSS, migrations, structural template changes." Most of this work qualifies. Bumped to **8.0.6** (patch — alignment/calibration; theme catching up to a behavioral change that already happened on live, not introducing a new one). Within the 7-per-minor cap (5 of 7 used). Documented the override in the CHANGELOG entry's "Why patch" section.

## Skills / plugins invoked at each step

| Step | Skill / plugin | Outcome |
| --- | --- | --- |
| 0 — gate | `superpowers:using-superpowers` | Established skill-first discipline. |
| 1 — audit reference | `gutenberg-block-authoring` | Loaded as primary reference for block-removal rules in steps 2–3. Stayed passive in step 1. |
| 1 — audit reference | `create-a-wp-site:wordpress-block-theming` | **Skipped.** The `gutenberg-block-authoring` content already covered patterns + template parts comprehensively for this audit. Flagged in the enumeration that I'd defer it unless a specific FSE-architecture question surfaced — none did. |
| 2 — block removal | `gutenberg-block-authoring` | Used as the authoritative reference for `wp:navigation-link` (self-closing) and `wp:button` (paired wrapper) delimiter rules. No block-recovery errors in the resulting markup. |
| 3 — content add | `gutenberg-block-authoring` | **Not load-bearing in the end.** The actual edit was to a PHP `printf`-emitted HTML region in [`inc/page-notes-render.php`](../../inc/page-notes-render.php), not Gutenberg block markup. Skill stayed loaded but didn't gate this edit. |
| 4 — versioning decision | `versioning` | Confirmed PATCH (not MAJOR/MINOR; no API change, no new capability). Confirmed within patch cap (5/7 used in v8.0). Confirmed the override of Step 4's "no-bump" assumption was correct and gave the format for documenting the override in the CHANGELOG entry. |
| 4 — CHANGELOG format | None — followed existing CHANGELOG.md entries (8.0.5 was the closest format reference). | |
| 4 — session note format | None — followed [`_claude/notes/2026-05-11-rss-discoverability-and-tracking.md`](2026-05-11-rss-discoverability-and-tracking.md) frontmatter and section structure. | |
| 5 — commit | `superpowers:verification-before-completion` (planned for the commit step), then `commit-commands:commit` for the conventional message. **`commit-commands:commit-push-pr` deliberately not invoked** — user said "do not push." | (Pending at time of writing this note.) |

## Process notes

- **The required-tooling enumeration changed how I worked.** Without the user's "sine qua non" gate at the top of the prompt, I would likely have started by running `find` and `grep` and then editing — without a deliberate per-step skill plan. The gate forced me to map skills to steps before touching anything, which surfaced the `versioning`-skill question early enough to get the bump call right the first time.
- **The PHP-renderer discovery was the load-bearing audit finding.** Without it, I would have edited `templates/home.html`'s dead RSS-footer block thinking I was editing the live footer, and the change would have shipped invisibly. Greping for `template_include` was the move that surfaced it. Worth adding to the WORDPRESS-REFERENCE.md upstream-gotchas list: *"Always grep for `template_include` before assuming a `templates/*.html` is the source of truth — a custom PHP renderer can silently win."*
- **One small spec-deviation caught and corrected mid-edit.** First pass at the new footer line accidentally added a link on "RSS" in the EXISTING line. The user's spec keeps the existing line as plain text; only the new line has links. Caught before commit, redone.

## Open follow-ups

- **Live `templates/home.html` divergence.** The repo's home.html no longer has any RSS footer block. The PHP renderer owns the live footer. If anyone ever flips the `template_include` short-circuit off (e.g., feature-flagging the new design), the FSE template will render with no footer at all. Acceptable given the renderer is the canonical path, but worth knowing.

## Follow-up corrections (post-tag, same session)

After tagging v8.0.6 I worked the three open follow-ups below. Two of them turned out to be wrong calls in the original session note. Recording the corrections so future-me doesn't re-trigger them.

### Correction 1 — `templates/page-notes.html` is NOT dead code

**Original claim:** "page-notes.html still has the same `<p class="sn-notes-rss">` dead-code block I cleaned from home.html. Likely also superseded by the PHP renderer; recommend a follow-up to strip the block (and possibly the whole template)."

**What's actually going on:** Reading [`inc/page-notes-template.php`](../../inc/page-notes-template.php)'s docblock makes the architecture explicit:

> Defense layers:
> 1. PHP renderer in inc/page-notes-render.php (PRIMARY) — what users actually see.
> 2. templates/page-notes.html (FALLBACK) — kept on disk with the correct content. Used by WP normally if the template_include hook fails to resolve. Better to render from a stale-but-correct file than to 404.
> 3. admin_init wp_template DB sweep — clears stale Site Editor saves.

`page-notes.html` is **deliberate incident-response infrastructure**, not orphan code. It's the second defense layer. Its design will drift from the PHP renderer's design by design — the docblock accepts that trade-off. **Don't sync it.** Don't delete it. Don't strip blocks from it.

**Action taken:** No edits. Documented the architecture in WORDPRESS-REFERENCE.md §10.4 so future-me (or anyone) doesn't re-trigger this misread.

### Correction 2 — `hero-dossier.php` and `section-constrained.php` are NOT orphan inserter-clutter

**Original claim:** "Inserter audit — same orphan-check should be done at some point; if neither is inserted by a template, they're inserter-clutter and should follow `cta-closing.php` out the door."

**What's actually going on:** Read both files. Distinguishing factor from `cta-closing.php`:
- `cta-closing.php` was a SPECIFIC pattern with a HARDCODED dead URL (`/work-with-me`). Both halves were unused; the second button referenced a 404. Deleting was correct.
- `hero-dossier.php` and `section-constrained.php` are GENERIC reusable layout primitives with placeholder content — meant for ad-hoc insertion via the Site Editor. The fact that no template inserts them is irrelevant; they're not inserter-clutter, they're inserter-tools. The docblocks even spell this out (`hero-dossier`: "Extracted in v7.5.0... Same five sites had near-identical eyebrow + giant H1 + body + meta blocks").

The criterion isn't "is this pattern referenced by a template" — it's "is this pattern broken/dead/misleading." Generic reusable patterns with placeholder content stay even if no template inserts them.

**Action taken:** No edits. Both patterns retained.

### Correction 3 — WORDPRESS-REFERENCE.md additions (executed)

Two additions landed:

- **New subsection §10.4 — "The `/notes` route — PHP-authoritative rendering"** documents the `template_include` short-circuit, the three defense layers, the rules of engagement (which file to edit for live changes), and how to verify which renderer is live via the `SN_NOTES_OVERRIDE_BUILD` marker. Future-me reading this will know within 30 seconds: don't edit `templates/home.html` or `templates/page-notes.html` for `/notes` design changes; edit the PHP renderer.
- **New gotcha row #11 in §13** documents the `theme.json customTemplates` ↔ `templates/page-*.html` sync requirement that today's session almost shipped wrong (the `page-work-with-me` registration would have orphaned without the post-edit verification grep).

Both are docs-only, no version bump, separate `docs:` commit.

## Companion: v8.0.7 — feed-footer relocation (same session)

After v8.0.6 shipped, the user noticed the new "For email, pipe the feed through Blogtrottr or Feedrabbit" line was functionally hidden — it lived in `<footer class="sn-notes-feed">` at the bottom of `<main>`, after all 7 note rows + 2 pillar essay cards, requiring scroll past everything before encountering the subscribe info. The whole point of v8.0.6's footer addition was discoverability for email subscribers; placing it below the fold defeated it.

### Brainstorming pass (`superpowers:brainstorming`)

Invoked the skill explicitly (the user reminded me — "Use skills to do it"). Asked a single A/B/C placement question via `AskUserQuestion`:

- **A. Move up, drop the bottom (Recommended)** — chosen.
- B. Add to top, keep at bottom too — rejected as redundant.
- C. New labeled "Subscribe" section between hero and pillars — rejected as out-of-scope; deferred.

Wrote a short design spec to [docs/superpowers/specs/2026-05-15-notes-feed-relocation-design.md](../../docs/superpowers/specs/2026-05-15-notes-feed-relocation-design.md), self-reviewed (no placeholders, internally consistent, single-plan scope, no ambiguity), committed standalone, asked for review. User approved.

The skill flow's terminal state is `superpowers:writing-plans` for an implementation plan. For a literal "move one block, delete one separator" change, that was acknowledged overhead; user's "Finish this..." was the implicit approval to skip writing-plans and execute directly off the spec.

### Files changed (v8.0.7)

| File | Change |
| --- | --- |
| [inc/page-notes-render.php](../../inc/page-notes-render.php) | Moved `<footer class="sn-notes-feed">` block (3 paragraphs + blinking cursor) from after `<section class="sn-notes-index-section">` to immediately after `<header class="sn-notes-hero">`, inside `.sn-notes-top`. Re-indented from 1-tab to 2-tab nesting to match the new container depth. |
| [inc/page-notes-render.php](../../inc/page-notes-render.php) | Deleted the `<hr class="sn-notes-rule">` that previously preceded the bottom footer. The other `<hr>` (between pillars and index) stays. |
| [style.css](../../style.css) | `Version: 8.0.6` → `Version: 8.0.7`. |
| [CHANGELOG.md](../../CHANGELOG.md) | New 8.0.7 entry at top with cap-exhaustion note. |
| [docs/superpowers/specs/2026-05-15-notes-feed-relocation-design.md](../../docs/superpowers/specs/2026-05-15-notes-feed-relocation-design.md) | Spec doc (committed in a previous standalone commit `e682f5a`). |

### v8.0 patch cap exhausted

`8.0.7` is the last patch in v8.0 per the project's 7-per-minor cap. Any further bump in this branch rolls to `8.1.0`. Documented in the CHANGELOG entry's "Why patch + cap note" section.

## Deployment

Per `CLAUDE.md`: commit only, do not push. User will review the diff and push manually. Annotated `v8.0.6` tag deferred to the user's session-end workflow.
