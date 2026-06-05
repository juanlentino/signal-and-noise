# Design — On-site search (FSE `search.html`)

**Date:** 2026-06-05
**Status:** Approved (brainstorm complete) → ready for implementation plan
**Repo:** theme `signal-and-noise` (Approach A is FSE-first; the only new PHP is one tested filter)
**Origin:** Committed frontier candidate #1 from the 2026-06-05 master execution sequence (`docs/superpowers/specs/2026-06-05-master-execution-sequence.md`).

---

## Problem

There is no on-site search experience. `templates/search.html` does not exist, so a `/?s=term` query falls through to `templates/index.html` (a Query Loop with `inherit:true`). Search therefore *technically returns results*, but with no "results for X" heading, no result count, no search input to initiate or refine, and an undifferentiated post list. There is no search trigger anywhere in the UI (`grep` finds zero `core/search` / `get_search_form` / `is_search` usage across `templates/`, `parts/`, `inc/`).

## Constraints discovered in source

1. **`index.html` is the silent search fallback** — a `wp:query {"inherit":true, perPage:10}` with `query-pagination`. It works but isn't tailored to search.
2. **`404.html` defines the brutalist empty-state vocabulary** — eyebrow (`Error · 404 · No Signal`), large `aria-hidden` display text, an `h1` carrying the page name, a rust descriptive line, a button. This is the vocabulary a "no results" state echoes.
3. **`parts/header.html`** is a flex group: logo (`wp:html`) + `wp:navigation` (7 links, `overlayMenu:"mobile"`). A search trigger lives here, after the nav.
4. **Brand vocabulary** — preset colors void(#fff)/bone(#000)/rust(#666)/blood(#e00404); `heading` font = Bebas Neue, `body` = DM Mono; existing brutalist classes (`.sn-notes-section-wrap` hairline label/count, `.sn-catalog-eyebrow`, `.sn-404-*`).
5. **All posts are "Notes"** — there is no separate post type; "all content" = posts (Notes) + pages (the utility pages: Services/Music/Resume/About/Contact/Provenance).

### WP-core primitives (verified against source 2026-06-05 — do not re-derive from memory)

| Primitive | Confirmed behavior | Source |
|---|---|---|
| `query_loop_block_query_vars` filter | Since 6.1.0. `apply_filters('query_loop_block_query_vars', $query, $block, $page)`, returns the WP_Query args array passed to `new WP_Query()`. Invoked **only on the `inherit:false` path** (the Post Template render uses the global `$wp_query` when `inherit:true` and never calls the builder). `s` is already a first-class key (`build_query_vars_from_query_block()` maps `query.search`→`$query['s']`); setting `$query['s'] = get_search_query()` composes cleanly with `post_type` etc. | `wp-includes/blocks.php` `build_query_vars_from_query_block()`; `…/blocks/post-template.php` render |
| `core/search` icon-expand | `buttonPosition:"button-only"` drives the expandable behavior (`$is_expandable_searchfield = 'button-only' === $button_position`), `buttonUseIcon:true` renders the SVG, `showLabel:false` hides the label. Auto-enqueues `@wordpress/block-library/search/view` (Interactivity API) at render — we register/enqueue nothing. SSR'd initial state degrades to a non-expanding icon button if the script module is blocked. No `isSearchFieldHidden` attribute exists (it's a runtime CSS class). | `…/blocks/search.php` render; `search/view.js` |
| `core/query-title` `type:"search"` | Supported. Renders only when `is_search()`. With `showSearchTerm:true` (default) → `Search results for: “<term>”`; else `Search results`. Keys off the global query (`get_search_query()`), independent of the two loops. | `…/blocks/query-title.php` render |

---

## Decisions (from brainstorm 2026-06-05)

| Decision | Choice | Rationale |
|---|---|---|
| Scope | **All content, grouped by type** (Notes + Pages) | User choice. Surfaces the full site, not just the blog. |
| Architecture | **Approach A — FSE `search.html` + one PHP query-vars filter** | Lightest path to grouped results; stays in the FSE system; one focused, testable filter vs a second `/notes`-style short-circuit renderer. |
| Trigger | **Header search icon (expandable) + a refine field on the results page** | Keeps the minimal header minimal until invoked; gives a refine affordance on results. Pure FSE (`core/search` icon mode). |
| Grouping | Two `wp:query` blocks, `inherit:false` — `postType:post` ("Notes — Results") then `postType:page` ("Pages — Results") | The only way to split the merged native search query into sections. |
| Search-awareness | `query_loop_block_query_vars` injects `s` into the two custom queries, guarded by `is_search()` + a discriminator | The sanctioned mechanism; the guard prevents bleeding `s` into unrelated custom queries. |
| Pagination | **None** (show all matches per group) | At 13 notes + ~10 pages, true pagination across two queries is awkward + YAGNI. The `.sn-notes-pagination` PHP control does not carry over (grouped results moot it). |
| Empty state | **Per-group** `query-no-results` ("No notes/pages match.") | A unified "both empty" 404-style screen is hard in pure FSE (needs PHP to detect zero-total); per-group is FSE-native. |
| Reading time | **Omitted** from Notes result rows | It's post-meta, awkward in a core post-template (pre-Block-Bindings). Date + title + excerpt only. |

---

## Design (Approach A)

### 1. Header trigger — `parts/header.html`
Add a `core/search` block in icon-expand mode after the `wp:navigation`:
```html
<!-- wp:search {"showLabel":false,"buttonText":"Search","buttonPosition":"button-only","buttonUseIcon":true,"className":"sn-header-search"} /-->
```
- Submits a top-level `?s=` to `home_url('/')`, routing to `search.html`.
- WP auto-enqueues the search view script module (Interactivity API). Graceful degradation: if the module is blocked, the icon renders but doesn't expand (still a working submit button once focused) — acceptable, no hard dependency.
- `.sn-header-search` styling tunes the icon/expanded-input to the brand (bone glyph, rust→blood on hover, DM Mono input). Honor `prefers-reduced-motion` if any expand transition is added.

### 2. `templates/search.html` (new)
```
header part → <main class="wp-block-group" tagName=main constrained>
  • wp:query-title {"type":"search","level":1}        → “Search results for: “term””
  • wp:search (full field, prefilled)  → refine input (className sn-search-refine)
  • NOTES group:
      .sn-notes-section-wrap hairline label “Notes — Results”
      wp:query {inherit:false, postType:post, namespace:"signal-noise/search"} →
        post-template rows: post-date (DM Mono/rust/uppercase) · post-title (Bebas, isLink, hover→blood) · post-excerpt (rust)
        wp:query-no-results → “No notes match.”
  • PAGES group:
      .sn-notes-section-wrap hairline label “Pages — Results”
      wp:query {inherit:false, postType:page, namespace:"signal-noise/search"} →
        post-template rows: post-title (isLink) · post-excerpt
        wp:query-no-results → “No pages match.”
footer part
```
- Mirrors `index.html`'s `<main>` group shell + the `/notes` row aesthetic (without reading time).
- The two query blocks carry a discriminator (a custom namespace and/or `className`, e.g. `sn-search-query`) so the filter targets only them.

### 3. `inc/search-query.php` (new — the only new PHP)
```php
add_filter( 'query_loop_block_query_vars', 'sn_search_inject_term', 10, 2 );
function sn_search_inject_term( $query, $block ) {
    if ( ! is_search() ) {
        return $query;               // only on /?s= pages
    }
    if ( ! sn_is_search_loop( $block ) ) {
        return $query;               // only our two grouped loops (discriminator)
    }
    $query['s'] = get_search_query();
    return $query;
}
```
- `sn_is_search_loop( $block )` is the discriminator. **Plan-time detail to confirm:** how the namespace/className surfaces in `$block->context` — verify against `$block->context['query']` (source confirmed it carries the query config); fall back to `($block->context['query']['postType'] ?? '')` ∈ {post,page} + `is_search()` if the namespace does not propagate. **Plan must read `parts/footer.html` to confirm no other `inherit:false` Query Loop renders on a search page** (header has none); if footer is clean, `is_search()` is already near-sufficient and the discriminator is belt-and-suspenders.
- Wired via the existing flat `require_once` manifest (mirror how `inc/` modules load).

### 4. Styling
- Reuse `.sn-notes-section-wrap` for the group hairline labels.
- New rules (in `assets/css/components.css`, the modular loader — NOT inline, per the no-inline-style discipline): `.sn-search-results` row spacing/typography echoing `.sn-notes-row`, `.sn-search-no-results` (DM Mono, rust, uppercase, mirrors `.sn-notes-empty`), `.sn-header-search` + `.sn-search-refine` field styling. 11px type floor. Reduced-motion-safe.

### 5. Tests — `tests/search-query.php` (standalone fixture)
Stub `is_search()`, `get_search_query()`, and a `$block` shape with `context['query']`. Assert:
- not a search page → `$query` returned unchanged (no `s`);
- search page + a search loop (discriminator matches) → `$query['s']` === the term;
- search page + a non-search custom loop (discriminator fails) → unchanged;
- existing args (`post_type`, `order`) survive injection.
Headless, no WP load — matches `tests/notes-pagination.php` / `tests/post-frontmatter.php`. Adds to the theme suite (currently 377/9).

---

## Out of scope (YAGNI)
- **Pagination** on search results — content volume doesn't warrant it; revisit if a group routinely exceeds ~25 matches.
- **Unified 404-style "both empty" screen** — per-group `query-no-results` instead; the unified treatment would need PHP zero-total detection (pushes toward Approach B).
- **Reading time** in Notes result rows — post-meta, awkward pre-Block-Bindings.
- **Custom-post-type grouping** — none exist (all posts are Notes).
- **Search filters/facets, fuzzy matching, search analytics** — separate, larger initiatives.

## Risks
- **Filter over-reach** — `query_loop_block_query_vars` fires for every `inherit:false` Query Loop site-wide. Mitigated by the `is_search()` guard + the discriminator; the plan must confirm no stray custom loop renders on a search page (check footer part).
- **Search view-script dependency** — the icon-expand needs the Interactivity API module; degrades to a non-expanding icon if blocked (graceful, not a failure).
- **Editor preview blindness** — the filter is front-end only, so the two groups look empty/generic in the Site Editor. Expected; verify on the live front end (and in a test).
- **Excerpt quality** — pages may lack good excerpts; `post-excerpt` will auto-trim content. Acceptable; note for UAT.

## Version
Theme minor (new user-visible capability) → **v9.7.0**. Collision note: the prep-minor (`docs/superpowers/plans/2026-05-27-v9.6.0.md`, already slated to renumber off v9.6.0) also targets v9.7.0. Search ships first and is unblocked, so it takes **v9.7.0**; the prep-minor moves to **v9.8.0**. Resolve explicitly at ship (same pattern as R1/v9.6.0). Release gated on explicit user go (space-out-releases).
