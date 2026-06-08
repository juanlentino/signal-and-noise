# /music Redesign — Cover-Grid Gallery (theme v9.14.0)

**Date:** 2026-06-08
**Status:** Design approved (user reviewed + loved a rendered mockup with real catalog data).
**Type:** Theme-side redesign of the `[sn_discography]` shortcode. Net-new UX, no breaking change → **theme v9.14.0** (plugin untouched).
**Visual contract:** `docs/superpowers/mockups/2026-06-08-music-redesign.html` (rendered with the live 11-album catalog).

## 1. Problem

The shipped `/music` (theme v9.13.0) renders the discography as a **vertical list of rows** — a 120px cover on the left, text on the right, hairline dividers. It's on-brand but text-forward and linear: the album covers (a producer's most appealing asset) are small, and there's no way to *surf* the catalog by role or scan it at a glance. For 11 releases (growing) it's a long scroll.

## 2. Approved design

Make the covers the hero and add light surfing controls, in the existing brutalist vocabulary (Bebas Neue display, DM Mono labels, `blood` red accents, `concrete` hairlines, white-first).

- **Cover-grid gallery** — a responsive grid of large square album covers (auto-fill, ~232px min), grouped by year (descending). Title (Bebas) / artist / roles (mono) sit under each cover.
- **Role filter rail** (sticky) — `All` + one chip per distinct credited role (Producer · Mixing · Mastering · Engineer · Composer · Keyboard · Synthesizer · …), plus a live **count** ("11 releases · 2007 → 2025"). Filtering by a role shows only releases carrying that credit and updates the count; empty year sections collapse; an empty state shows when nothing matches. This is the "easy to surf" win.
- **Click-to-play** — the whole cover is the play affordance: clicking swaps the cover for the lazy Spotify embed in place (album or track per `type`). Zero eager iframes (unchanged performance contract).
- **Unchanged:** the page header, the curated "press play" Spotify hero player (stays in the page content), and the Muso.AI "View all credits" CTA. The plugin store contract (`sn_discography_entries`) is untouched.

## 3. Architecture (theme-side only)

| Unit | Change |
|---|---|
| `inc/discography-render.php` | `[sn_discography]` emits: a `.sn-disco-controls` rail (count + role chips) → per-year `.sn-disco-year` sections, each a `.sn-disco-grid` of `.sn-disco-card`s → a hidden `.sn-disco-empty`. Standalone-safe (`''` when no entries); every external field escaped. |
| `assets/css/components.css` | Replace the `.sn-disco*` row styles with the cover-grid styles (grid, cover-wrap with hover scale + play badge, chips, sticky controls). |
| `assets/js/discography.js` | Add role filtering (chips → show/hide cards, collapse empty years, update count, empty state) + adapt click-to-play to the cover-wrap. |

### Output contract (what the test asserts + the JS reads)

```html
<div class="sn-discography">
  <div class="sn-disco-controls">
    <p class="sn-disco-count"><strong data-disco-count>11</strong> releases · 2007 → 2025</p>
    <div class="sn-disco-filters">
      <button class="sn-disco-chip is-active" data-role="*">All</button>
      <button class="sn-disco-chip" data-role="Producer">Producer</button> …
    </div>
  </div>
  <section class="sn-disco-year" data-year="2025">
    <h2 class="sn-disco-year__label">2025 <span class="sn-disco-year__count">1 release</span></h2>
    <div class="sn-disco-grid">
      <article class="sn-disco-card" data-roles="Mastering|Engineer" data-spotify="<albumId>" data-type="album">
        <div class="sn-disco-cover-wrap" role="button" tabindex="0" aria-label="Play ARGIRIA">
          <img class="sn-disco-art" loading="lazy" decoding="async" src="<cover>" alt="ARGIRIA" width="300" height="300">
          <span class="sn-disco-play-badge"><span class="sn-disco-play-circle"></span></span>
        </div>
        <div class="sn-disco-meta">
          <h3 class="sn-disco-title">ARGIRIA</h3>
          <p class="sn-disco-artist">Cande Schulman</p>
          <p class="sn-disco-roles">Mastering · Engineer</p>
          <a class="sn-disco-credits" href="<muso_url>" target="_blank" rel="noopener">Credits ↗</a>
        </div>
      </article> …
    </div>
  </section>
  <p class="sn-disco-empty" hidden>No releases with that credit.</p>
</div>
```

- A card with no `spotify_id` renders a plain (non-button) cover-wrap (no play badge); its Credits link still works. Roles render `data-roles` pipe-joined (filter source) + a visible ` · `-joined string.
- Chip order: a preferred sequence (Producer, Mixing, Mastering, Engineer, Composer, Keyboard, Synthesizer) then any remaining roles appended; only roles actually present are shown.

## 4. Accessibility

Cover-wrap is a keyboard-operable button (`role=button`, `tabindex=0`, Enter/Space plays, `:focus-visible` reveals the play badge); filter chips are real `<button>`s; the empty state is announced via the count change. Min font floor 11px holds (mono labels).

## 5. Non-goals (YAGNI)

No search (11 items), no sort control (year grouping is the order), no per-release pages, no change to the plugin/store/cron, no auto-styling of the user's curated featured player (it stays user-managed in page content).

## 6. Versioning

theme v9.13.0 → **v9.14.0** (net-new surfable grid UX; MINOR). Plugin unchanged.
