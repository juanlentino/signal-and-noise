# Spec — `/notes` subscribe info, nested in hero

**Date:** 2026-05-15
**Project:** signal-and-noise
**Targeted release:** v8.1.0 (cap rollover from v8.0.7 — see Versioning)
**Origin:** session 2026-05-15, post-v8.0.7 visual review. The user rejected the v8.0.7 placement: it solved discoverability but landed the feed footer in the right column of the `.sn-notes-top` 5fr/7fr grid (because it became the second grid child, displacing the pillars to row 2). The result was visual co-equality with the hero — nothing read as the focal point.

**Supersedes:** [2026-05-15-notes-feed-relocation-design.md](2026-05-15-notes-feed-relocation-design.md) (the v8.0.7 design, now shipped but rejected on visual review).

## Problem

After v8.0.7 shipped, the `<footer class="sn-notes-feed">` block sat as the second child of `.sn-notes-top` and got placed in column 2 of the 5fr/7fr grid (because that's the grid's auto-placement behavior). The pillar essays section — which had been the canonical column-2 element — was pushed to row 2. The visual result:

- Hero (left col) and feed footer (right col) read as **co-equal columns** at the same vertical band
- The hero title `NOTES.` lost its focal-point status — the terminal-status feed block competes for it
- Pillar essays got demoted from "side-by-side with the hero" to "below it on a second row," changing their visual prominence
- Empty space appeared below the (shorter) feed column while the (taller) hero column extended down

The root cause was missing from the v8.0.7 spec's analysis: **`.sn-notes-top` is a CSS grid with explicit 5fr/7fr columns at desktop (`>= 980px`)**, defined at lines 183–195 of `inc/page-notes-render.php`. Adding a third grid child without addressing the grid layout meant the new element took column 2 by default placement.

## Goal

Keep all three pieces of the v8.0.6 footer info — the RSS URL, the editorial-voice disclaimer ("No subscription form. No schedule."), and the email-bridge mention (Blogtrottr / Feedrabbit) — but present them in a way that:

1. Stays discoverable without scrolling (above the fold on desktop and most mobile)
2. Reads as **tertiary** in the hero's visual hierarchy (title > dek > meta > subscribe)
3. Doesn't compete for visual weight with the title or with the pillar essays
4. Restores the pillar essays section to column 2 of the desktop grid (its v8.0.6-and-prior position)

## Decision

Move the subscribe info **inside** `<header class="sn-notes-hero">` as a single small `<p class="sn-notes-subscribe">` element placed immediately after the existing `<p class="sn-notes-meta">` line. Drop the standalone `<footer class="sn-notes-feed">` element entirely. Keep the blinking cursor as the closing beat of the new sentence (same brand aesthetic, just inline rather than a dedicated block).

The new element being a child of `.sn-notes-hero` (which is the column-1 grid item) means it inherits column 1's width and never escapes into the grid layout. The pillars section becomes the second `.sn-notes-top` child again and returns to column 2 automatically.

## Markup

Inside `<header class="sn-notes-hero">`, after the existing meta paragraph:

```html
<header class="sn-notes-hero">
    <p class="sn-notes-eyebrow">…</p>
    <h1 class="sn-notes-headline">Notes.</h1>
    <p class="sn-notes-dek">…</p>
    <p class="sn-notes-meta">
        <span><?php echo esc_html( … entry count … ); ?></span>
        <?php if ( $latest_date ) : ?>
            <span class="sn-notes-meta-bullet" aria-hidden="true">·</span>
            <span>Last updated <?php echo esc_html( $latest_date ); ?></span>
        <?php endif; ?>
    </p>
    <p class="sn-notes-subscribe">
        No subscription form. No schedule. Notes via <a href="/notes/feed/">RSS</a>, or via email through <a href="https://blogtrottr.com/" target="_blank" rel="noopener noreferrer">Blogtrottr</a> or <a href="https://www.feedrabbit.com/" target="_blank" rel="noopener noreferrer">Feedrabbit</a>.<span class="sn-notes-cursor" aria-hidden="true"></span>
    </p>
</header>
```

The current `<footer class="sn-notes-feed">` block (located at lines 593–599 of `inc/page-notes-render.php` after the v8.0.7 edits) is removed entirely. The pillars section (`<section class="sn-notes-pillars-section">`) follows the hero directly, becoming the second child of `.sn-notes-top` and returning to column 2 of the grid.

## CSS

### Add

```css
.sn-notes-subscribe {
    margin-top: 1.25rem;
    font-family: 'DM Mono', 'Courier New', monospace;
    font-size: 0.7rem;
    letter-spacing: 0.18em;
    text-transform: uppercase;
    line-height: 1.7;
    color: var(--wp--preset--color--rust, #666);
    max-width: 48ch;
    margin-bottom: 0;
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
```

### Remove

The entire `/* RSS FEED FOOTER — terminal status line */` block (lines 486–543 of the current file), specifically:

- `.sn-notes-feed`
- `.sn-notes-feed-status` and `.sn-notes-feed-status a`, `.sn-notes-feed-status a:hover`
- `.sn-notes-feed-cursor`
- `.sn-notes-feed-note`, `.sn-notes-feed-note + .sn-notes-feed-note`, `.sn-notes-feed-note a`, `.sn-notes-feed-note a:hover`

The `@keyframes sn-blink` rule **stays** — it's referenced by the new `.sn-notes-cursor`.

### Update

The `@media (prefers-reduced-motion: reduce)` rule references `.sn-notes-feed-cursor`. Update to `.sn-notes-cursor`:

```css
@media (prefers-reduced-motion: reduce) {
    .sn-notes-page > * { animation: none; }
    .sn-notes-cursor { animation: none; opacity: 0.6; }   /* renamed */
    .sn-notes-pillar { transition: none; }
    .sn-notes-pillar::before { transition: none; }
    .sn-notes-row { transition: none; }
}
```

## Element-level decisions

- **`<p>` not `<aside>` not `<footer>`.** The element is editorial subordinate text inside the hero — semantically a paragraph. `<aside>` would imply tangential content; it's not tangential, it's part of the colophon-style metadata. `<footer>` no longer fits because the element no longer terminates a section.
- **Single sentence, three inline links.** Folds the v8.0.6 disclaimer + bridge mention into one statement: *"No subscription form. No schedule. Notes via RSS, or via email through Blogtrottr or Feedrabbit."* Same total information, less visual weight than three separate paragraphs.
- **Cursor at sentence end.** Same `sn-blink` keyframe, same blood color, same dimensions. Reads as "live system" punctuation rather than "terminal prompt waiting for input."
- **External-link attributes.** `target="_blank" rel="noopener noreferrer"` on Blogtrottr and Feedrabbit (matches v8.0.6). The internal `/notes/feed/` link gets neither.
- **`max-width: 48ch`** on `.sn-notes-subscribe` matches the implicit comfort line-length of the dek (which uses `max-width: 48ch`). Keeps the subscribe line from running edge-to-edge in column 1.
- **`templates/page-notes.html` (FSE fallback) NOT updated.** Per WORDPRESS-REFERENCE.md §10.4, intentional incident-response infrastructure that's allowed to drift.

## Out of scope

- Changing the hero (`<h1>`, dek, meta) copy or layout
- Changing the pillar essays section (it's untouched — it just returns to its v8.0.6 position automatically)
- Modifying the bottom of the page (it now ends with the notes index, no footer block — same as v8.0.7)
- Touching `templates/page-notes.html` (FSE fallback) or `templates/home.html`
- Touching the FSE block-template surface in any way

## Versioning

Patch slot 7 of 7 in v8.0 was used by v8.0.7. The 7-per-minor cap is now exhausted. Per [docs/VERSIONING.md](../../VERSIONING.md), this change rolls to a **MINOR bump: v8.1.0**.

This is the cap-rollover case the global versioning skill explicitly documents — the change is patch-shaped (UX calibration, no new capability, no breaking API change) but ships under the next minor digit because the cap forces it. The CHANGELOG entry will note this rollover so future-readers don't infer a new capability from the minor digit.

## Files touched

| File | Change | Notes |
| --- | --- | --- |
| [inc/page-notes-render.php](../../inc/page-notes-render.php) | Add `<p class="sn-notes-subscribe">` inside `<header class="sn-notes-hero">`; remove the `<footer class="sn-notes-feed">` block; remove `.sn-notes-feed-*` CSS; add `.sn-notes-subscribe` + `.sn-notes-cursor` CSS; update reduced-motion media query | Single PHP file, both markup and inline `<style>` block |
| [style.css](../../style.css) | `Version: 8.0.7` → `Version: 8.1.0` | Cap rollover bump |
| [CHANGELOG.md](../../CHANGELOG.md) | New 8.1.0 entry at top, with explicit "rollover, not a new capability" note | Per versioning skill guidance for cap rollovers |
| [_claude/notes/2026-05-15-sync-repo-to-live-and-add-email-rss-line.md](../../../_claude/notes/2026-05-15-sync-repo-to-live-and-add-email-rss-line.md) | Append "Companion v8.1.0 — subscribe-in-hero" section documenting the v8.0.7 rejection + this redesign | Keeps the session note as a single canonical record |
| [docs/superpowers/specs/2026-05-15-notes-feed-relocation-design.md](2026-05-15-notes-feed-relocation-design.md) | Add "Superseded by …" note at top | So future-readers don't act on a stale spec |

## Verification

After deploy:
1. `curl https://juanlentino.com/notes/` and confirm:
   - The `<header class="sn-notes-hero">` element contains a `<p class="sn-notes-subscribe">…</p>` as its last child
   - There is exactly **zero** `<footer class="sn-notes-feed">` in the response (was 1 in v8.0.7, was 1 in v8.0.6)
   - There is exactly **one** `<hr class="sn-notes-rule">` (the one between pillars and index — same as v8.0.7)
2. Visual eyeball:
   - Pillar essays sit in column 2 of the desktop grid, side-by-side with the hero (restored from v8.0.7)
   - Subscribe sentence reads as tertiary in the hero hierarchy (title > dek > meta > subscribe)
   - Blinking cursor terminates the subscribe sentence and remains on-brand
   - Page ends with the notes index — no closing footer
3. Reduced-motion test: with `prefers-reduced-motion: reduce`, the cursor stops blinking (opacity 0.6 static).

## Why this needed two iterations

The v8.0.7 design failed because the spec analyzed the markup change in isolation — moving a `<footer>` block from one position to another — without analyzing how `.sn-notes-top`'s 5fr/7fr grid would treat a third grid child. The grid was the load-bearing layout primitive, and it got skipped in the design pass.

This iteration explicitly grounds the design in that grid: the new element lives **inside** a grid item (`.sn-notes-hero`), not as a sibling of grid items. The grid layout is preserved unchanged. Lesson recorded in the session note.
