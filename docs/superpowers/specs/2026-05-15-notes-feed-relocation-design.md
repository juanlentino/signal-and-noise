# Spec — `/notes` feed footer relocation

**Date:** 2026-05-15
**Project:** signal-and-noise
**Targeted release:** v8.0.7
**Origin:** session 2026-05-15 (sync repo to live + add email-via-RSS line) — discoverability follow-up

## Problem

The feed/subscribe footer on `/notes/` is structurally at the bottom of `<main>`, after all note rows and both pillar essay cards. On a typical viewport, readers must scroll past everything before encountering the subscribe info. The footer is functionally hidden for the first-impression case — defeating the purpose of the email-via-RSS line added in v8.0.6.

## Goal

Make the feed/subscribe info discoverable without scrolling. Preserve the brutalist "Industrial Catalog" design language (terminal-status aesthetic, blinking cursor, brand colors).

## Decision

Relocate the existing `<footer class="sn-notes-feed">` block in `inc/page-notes-render.php` from its current bottom-of-main position to immediately after the hero `<header>`, inside the `.sn-notes-top` wrapper. Drop the bottom occurrence and the now-orphaned `<hr class="sn-notes-rule">` separator that preceded it.

## Approaches considered + rejected

- **Keep bottom + add compact top callout (redundancy):** Both visibility and preserved closing aesthetic, but two slightly different visual treatments on the same page and mild maintenance double-up. Rejected.
- **New labeled "Subscribe" section between hero and pillars:** Most design-coherent with the catalog metaphor (matches the existing `Pillar Essays — Featured` and `Notes — Index` labeled-section pattern) but adds a section the reader must scroll past before reaching pillar essays, and requires its own visual treatment. Rejected as out of scope; defer for a future redesign pass if the simple relocation doesn't surface enough subscriptions.

## Diff (markup)

```html
<main class="sn-notes-page" id="content">

    <div class="sn-notes-top">

        <header class="sn-notes-hero">
            <p class="sn-notes-eyebrow">…</p>
            <h1 class="sn-notes-headline">Notes.</h1>
            <p class="sn-notes-dek">…</p>
            <p class="sn-notes-meta">N entries · Last updated YYYY.MM.DD</p>
        </header>

+       <footer class="sn-notes-feed" aria-label="RSS feed">
+           <p class="sn-notes-feed-status">Feed — <a href="/notes/feed/">/notes/feed/</a><span class="sn-notes-feed-cursor"></span></p>
+           <p class="sn-notes-feed-note">No subscription form. No schedule. Notes available via RSS.</p>
+           <p class="sn-notes-feed-note">For email, pipe the <a href="/notes/feed/">feed</a> through <a href="https://blogtrottr.com/" target="_blank" rel="noopener noreferrer">Blogtrottr</a> or <a href="https://www.feedrabbit.com/" target="_blank" rel="noopener noreferrer">Feedrabbit</a>.</p>
+       </footer>

        <section class="sn-notes-pillars-section">…</section>

    </div>

    <hr class="sn-notes-rule" aria-hidden="true">  <!-- KEEP: divides pillars from index -->

    <section class="sn-notes-index-section">…</section>

-   <hr class="sn-notes-rule" aria-hidden="true">
-   <footer class="sn-notes-feed" aria-label="RSS feed">
-       …three paragraphs…
-   </footer>

</main>
```

## Element-level decisions

- **`<footer>` element retained.** Per HTML5, `<footer>` represents footer content for its nearest sectioning ancestor; placing it visually at the top is unusual but not invalid. Switching to `<aside>` would be better semantic for "tangentially related content" but the markup-semantic shift isn't worth a placement-only change. Open to flipping in a future iteration if it becomes load-bearing.
- **`aria-label="RSS feed"` retained.** Still accurate at the top.
- **Blinking cursor styling retained.** At the top of a notes catalog the blinking cursor reads as "live feed status" rather than "end of output" — arguably more apt for a continuously-updating directory than a closing flourish.
- **`templates/page-notes.html` (FSE fallback) NOT updated.** Per WORDPRESS-REFERENCE.md §10.4, the FSE template is intentional incident-response infrastructure (defense layer 2) that's allowed to drift from the live PHP renderer.
- **No new copy, no new links, no new design tokens.** Same three paragraphs, same `.sn-notes-feed-*` classes, same `aria-label`.

## CSS

No changes to the existing rules. The `.sn-notes-feed { margin-top: clamp(2rem, 4vw, 3rem); margin-bottom: clamp(2rem, 4vw, 3rem) }` translates cleanly to the new context: margin-top creates space between the hero meta paragraph and the feed block; margin-bottom creates space between the feed block and the pillars section. Will eyeball post-deploy and tighten only if the spacing reads visually off.

## Versioning

Patch: **8.0.6 → 8.0.7**. Structural change to the live `/notes` renderer. Per project rules ([docs/VERSIONING.md](../../VERSIONING.md)), code changes bump version. This is patch slot 7 of 7 in the v8.0 minor (cap). Any further bump in this branch rolls to 8.1.0; the CHANGELOG entry will note the cap-adjacent state for future-me.

## Out of scope

- Adding new subscribe copy or new bridging services
- Restyling the block (colors, fonts, spacing changes beyond the existing CSS)
- Updating the FSE fallback template
- Touching `templates/home.html` (already cleaned in v8.0.6) or any other template
- Compositional changes to the page sections (pillars, index, hero)

## Verification

After deploy:
1. `curl https://juanlentino.com/notes/` and confirm the `<footer class="sn-notes-feed">` block appears immediately after the `<p class="sn-notes-meta">` paragraph (inside `.sn-notes-top`), and is **absent** from the previous post-index position.
2. Confirm only ONE `<hr class="sn-notes-rule">` exists in the response (the one between pillars and index); the second one is gone.
3. Visual eyeball: the blinking cursor reads as "live feed status" rather than "system loading," and the vertical rhythm between hero meta → feed → pillars section is consistent (not too tight, not too airy).

## Files touched

- `inc/page-notes-render.php` (single file, markup-only edit)
- `CHANGELOG.md` (new 8.0.7 entry at top)
- `style.css` (`Version: 8.0.6` → `Version: 8.0.7`)
- `_claude/notes/2026-05-15-sync-repo-to-live-and-add-email-rss-line.md` (append a brief "Companion: feed-footer relocation v8.0.7" section so the original session note links forward to this work)
