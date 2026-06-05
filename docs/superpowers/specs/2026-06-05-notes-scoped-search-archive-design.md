# Design — Notes-scoped search & archive (theme v9.8.0)

**Date:** 2026-06-05
**Status:** Approved (brainstorm) — pending spec review → plan → execute
**Supersedes:** the v9.7.0 header search trigger (the "black blob") + the global posts+pages search surface.
**Ships as:** theme **v9.8.0** (the prep-minor slot in the master execution sequence).

## 1. Motivation

The v9.7.0 on-site search shipped three things: a `core/search` trigger in `parts/header.html`, a global results template `templates/search.html` (grouped **Notes + Pages**), and a query-vars injector `inc/search-query.php`. Two problems surfaced live:

1. The header trigger rendered as a **large black blob** — `core/search` drags the theme's `.wp-element-button` chrome (black bg + pill radius + padding), and the v9.7.0 CSS only set the SVG fill to black, so it was a black icon on a black pill. Invisible to headless checks; only the live render exposed it.
2. The user does **not** want search in the header, and across follow-ups refined the intent to: search should **live in Notes as an archive**, be **Notes-only (no Pages)**, and the Notes section may be **redesigned/refactored** to make this clean.

This design reverses the v9.7.0 placement and **consolidates two search surfaces into one Notes-scoped surface built into `/notes`**.

## 2. Key facts that shape the design

- **All blog posts *are* Notes.** [`inc/page-notes-render.php:124-134`](../../../inc/page-notes-render.php) — `post_type=post` with no custom post type or taxonomy filter. "Notes-only search" ⇒ a `WP_Query` scoped to `post_type=post` with an `s=` term. "No Pages" ⇒ simply don't build a pages query.
- **`/notes` is PHP-authoritative**, off WP's block-template chain ([`inc/page-notes-template.php`](../../../inc/page-notes-template.php)) after three stale-render incidents. Adding search *inside* the existing renderer is low-risk; adding a *separate* archive route would extend the incident-prone routing to a second URL (rejected — that was brainstorm Approach C).
- **Routing already cooperates with `?s=`.** `sn_notes_is_index_request()` strips the query string (`strtok($req, '?')`), so `/notes/?s=term` resolves to the index render. Our `template_redirect` priority 0 beats `redirect_canonical` (priority 10), so no canonical-redirect fight.
- **Two existing search surfaces to remove:** `templates/search.html` (posts+pages, two query loops) and `inc/search-query.php` (injects `s` into both loops, registered at [`functions.php:48`](../../../functions.php)). Test fixture `tests/search-query.php` exercises the injector.
- **Test harness:** standalone CLI fixtures in `tests/*.php` that stub WP functions (no WP/DB). CI auto-enumerates `tests/*.php` and **requires a `N passed, N failed` summary line** per file (a missing line is flagged as a silent skip). `inc/page-notes-render.php` already supports `define('SN_NOTES_RENDER_TEST', true)` to return before the render body so its pure helpers are testable.

## 3. Decision

**Approach A — search built into `/notes`, server-side, Notes-only.** Chosen over instant client-side filter (would undo the v9.6.0 pagination, adds a JS dependency as the base — can be layered on later as pure enhancement) and a separate archive page (second incident-prone route). Approach A is robust (no-JS), scalable, paginated, accessible, SEO-friendly, reuses the proven renderer, and *is* the "Notes refactor" the user sensed: two surfaces → one.

## 4. Behavior — `/notes` gets two states, one page

### Browse state (`?s=` absent or empty/whitespace)
Today's layout unchanged — hero + 2 pillar cards + tabular index + pagination — **plus** a search form in the "Notes — Index" section header.

### Search state (`?s=term`, term non-empty after trim)
- **Hero stays** (page identity).
- **Pillars section + the divider rule hide** (focus on results). *(Pillars link to `/provenance/*` Pages, never posts, so they would never match a Notes search anyway — hiding them removes noise, not relevant results.)*
- The index section becomes a **results** section:
  - Heading: `Notes — Search` with the query echoed (`esc_html`) — e.g. `Notes — Search · "provenance"`.
  - Count = `found_posts` for the search query.
  - A **Clear / All notes** link back to `/notes/` (resets to browse).
  - Matching notes render in the **identical catalog row vocabulary** (date + reading-time spec column | title + excerpt).
  - Branded empty state when no matches: `No notes match "term".` in the existing `.sn-notes-empty` mono style.
  - Pagination works on results; the base URL carries `s` so page 2+ stays in the search.
- The search form persists in both states (so a search can be refined from the results page).

## 5. Components

### 5.1 Renderer ([`inc/page-notes-render.php`](../../../inc/page-notes-render.php))
- **New pure helper `sn_notes_search_term(): string`** — read the term from `get_query_var('s')`, fall back to `$_GET['s']` (mirroring the `paged` pattern in `sn_notes_current_page()`), `wp_unslash` + `sanitize_text_field`, `trim`. Returns `''` when absent/whitespace. Declared **above** the `SN_NOTES_RENDER_TEST` early-return so it is unit-testable.
- **`sn_notes_query_posts()`** — when `sn_notes_search_term()` is non-empty, add `'s' => $term` to the `WP_Query` args. Everything else unchanged (`post_type=post`, `post_status=publish`, per-page, paged, `no_found_rows=false`). Notes-only and pages-excluded by construction.
- **New pure helper `sn_notes_pagination_base(string $term): string`** — returns the `paginate_links()` base: `add_query_arg('paged','%#%', home_url('/notes/'))` in browse mode, and with `s` added when searching. Declared above the test guard (testable).
- **Hand-rolled search form** (no `core/search` block — this eliminates the `.wp-element-button` blob class entirely):
  ```html
  <form class="sn-notes-search" role="search" method="get" action="/notes/">
    <label class="screen-reader-text" for="sn-notes-q">Search notes</label>
    <input type="search" id="sn-notes-q" name="s" value="<?php echo esc_attr( $term ); ?>"
           placeholder="Search notes" autocomplete="off">
    <button type="submit" aria-label="Search">
      <span aria-hidden="true">↵</span>
    </button>
  </form>
  ```
  Styled brutalist (thin underline field, DM Mono placeholder, no black pill). The submit is a plain text/glyph button (return-arrow glyph `↵`, or the word "GO" in tracked DM Mono — final glyph decided at build) with transparent chrome — bone → blood on hover. No `core/search`, so no `.wp-element-button`.
- **Bump `SN_NOTES_OVERRIDE_BUILD`** (the build marker convention) to a new value, e.g. `2026-06-05-notes-search-v11`.
- New inline CSS block for `.sn-notes-search` (form lives in the inlined `<style>` with the rest of the catalog CSS).

### 5.2 Search funnel — `/?s=` → `/notes/?s=` ([`inc/page-notes-template.php`](../../../inc/page-notes-template.php))
- **New pure helper `sn_notes_search_redirect_target(string $term): string`** — returns `home_url('/notes/')` with `s` query arg when `$term` is non-empty (testable in isolation).
- A `template_redirect` action (priority 1 — after the notes-render priority 0, before `redirect_canonical` priority 10): if `is_search()` **and** the request is not already the `/notes` index, `wp_safe_redirect()` to the target and `exit`. No loop: `/notes/?s=term` resolves to the index branch and is rendered, not redirected. This guarantees exactly one search surface and no orphan global-search page after `templates/search.html` is deleted.
- *(Reversible decision — if the user later prefers `/?s=` to 404/ignore instead, drop this hook.)*

### 5.3 Removals (the consolidation / refactor)
- **Delete** [`templates/search.html`](../../../templates/search.html) (global posts+pages results template).
- **Delete** [`inc/search-query.php`](../../../inc/search-query.php) and **delete** [`tests/search-query.php`](../../../tests/search-query.php).
- **Edit** [`functions.php`](../../../functions.php): remove `require_once __DIR__ . '/inc/search-query.php';` (line 48) and the docblock line referencing it (line 12).
- **Revert the header** ([`parts/header.html`](../../../parts/header.html)): remove the `core/search` block (line 27) **and** unwrap the `sn-header-actions` group (lines 14-15, 29-30) so the navigation returns to being a direct child of `.sn-header` (the pre-v9.7.0 shape — flex `space-between` puts logo left, nav right).
- **Remove CSS** ([`assets/css/components.css:597-611`](../../../assets/css/components.css)): `.sn-header-actions`, `.sn-header-search …` rules, and the reduced-motion rule for it.

## 6. Edge cases & error handling
- **Empty/whitespace `?s=`:** treated as browse (no results mode, no "0 results" noise).
- **XSS:** term is `sanitize_text_field`'d on input and `esc_html`/`esc_attr`'d on every output (heading echo, input `value`). No raw term reaches the DOM.
- **Pagination beyond results:** `paginate_links()` with the search-aware base; out-of-range pages render the empty state.
- **No-JS:** fully functional — it's a GET form + server render. (Instant filter, if added later, is enhancement only.)
- **Reduced motion:** existing `.sn-notes-page` entry-animation guard covers new children; the form has no animation requiring a new guard beyond the hover color transition (already exempt as a color change, but gate to match the file's conventions if any transition is added).
- **Fallback template:** `templates/page-notes.html` remains the on-disk fallback for `/notes` if the render file is missing post-deploy; it does **not** gain search (it's a safety net, not the canonical surface) — documented as such.

## 7. Accessibility
- `role="search"` landmark on the form; visually-hidden `<label>` bound to the input via `for`/`id`.
- Submit button has `aria-label`; the glyph/SVG is `aria-hidden`.
- Results heading is a real `<h…>` in the existing heading hierarchy; the skip-link target (`#wp--skip-link--target` on `<main>`) is unaffected.
- 11px type floor honored (`max(0.7rem, 11px)` pattern already used in the catalog labels).

## 8. Testing & verification

### Headless unit tests (extend the standalone fixture harness)
- **New `tests/notes-search.php`** (mirrors `tests/notes-pagination.php`'s stub pattern; defines `SN_NOTES_RENDER_TEST`, requires the renderer, emits the `N passed, N failed` line CI requires):
  - `sn_notes_search_term()` — reads `get_query_var('s')`; falls back to `$_GET['s']`; trims; sanitizes; returns `''` for absent/whitespace.
  - `sn_notes_query_posts()` — injects `s` when a term is present; omits `s` when absent; keeps `post_type=post`, `post_status=publish`.
  - `sn_notes_pagination_base()` — carries `s` when searching, omits it when browsing; always targets `/notes/`.
- **Redirect helper test** (in `tests/notes-search.php` or a small fixture): `sn_notes_search_redirect_target()` builds `/notes/?s=…` for a term and the bare `/notes/` for an empty term.
- Remove `tests/search-query.php` from the sweep (deleted).

### Live render verification (NON-NEGOTIABLE — the "render ≠ static checks" lesson)
The blob was green on every headless check and still broke live. So verification must include a **real render**, not just passing tests:
- Verify the rendered `/notes/` (browse) shows the search field with **no black pill** and correct catalog layout.
- Verify `/notes/?s=<known-term>` renders results in catalog rows, the echoed query, the count, and a working Clear link.
- Verify `/notes/?s=` (empty) and a no-match term show the right states.
- Verify `/?s=term` redirects to `/notes/?s=term`.
- Confirm the header no longer renders the search blob and the nav is right-aligned.
- Method: local Studio site if available, else `Claude_in_Chrome` / `curl` against the deploy. Capture evidence before claiming done.

## 9. Versioning & release
- **theme v9.8.0** — minor (new user-visible capability: Notes archive search; plus the structural header revert + search-surface refactor). CHANGELOG entry in Mimestream categories (New / Improvements / Fixed / Removed). Bump `Version:` in `style.css`.
- Release gated on the user's go (space-out-releases). Install via wp-admin → Updates (canonical) after tag.
- Post-ship: a holistic audit pass per "audit-before-UAT," given this touches routing + render.

## 10. Non-goals
- Instant client-side type-to-filter (future enhancement; not in v9.8.0).
- A separate `/notes/archive/` route.
- Searching Pages or any non-`post` content.
- Redesigning the hero or pillar cards beyond hiding pillars during the search state.
