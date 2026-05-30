# Design — `/notes` index pagination

**Date:** 2026-05-30
**Status:** Approved (brainstorm complete) → ready for implementation plan
**Repos:** theme `signal-and-noise` (Release 1), plugin `signal-and-noise-tools` (Release 2)

---

## Problem

`https://juanlentino.com/notes` lists all published notes with no pagination. Today: 13 published (+ 9 scheduled = 22 total). The custom renderer queries `posts_per_page => 50`, so everything fits on one page and no paging UI exists. As published volume grows past a page-length, the index becomes an unbounded scroll. Add pagination.

**Count clarification (resolved during brainstorm):** the renderer queries `post_status => 'publish'` only. Scheduled posts are status `future` and never appear on the index regardless of pagination — they surface automatically as they publish. So "13 now" is the published count; pagination activates once *published* notes exceed the per-page size.

## Constraints discovered in source

1. **Exact-path router.** `sn_notes_is_index_request()` (`inc/page-notes-template.php`) returns true only when the bare path equals `/notes` or `/notes/` — the query string is stripped before comparison. Therefore **`/notes/?paged=N` works for free** (matches the router), but a pretty path like `/notes/page/2/` would NOT match (longer path → falls through to WP resolution → 404/category-archive risk). This page has a documented 3-incident stale-render history, so minimizing routing surface is a hard preference.
2. **`no_found_rows => true`** in `sn_notes_query_posts()` disables `found_posts`/`max_num_pages` — which pagination requires. Must flip to `false`.
3. **PHP-authoritative render.** `/notes` is rendered by `inc/page-notes-render.php` via a `template_redirect` priority-0 short-circuit (`include; exit`). Pagination lives entirely in that PHP renderer + its inlined `<style>`. No block-template involvement.

## Decisions (from brainstorm)

| Decision | Choice | Rationale |
|---|---|---|
| Paging mechanism | **Query string** `/notes/?paged=N` | Works within the exact-path router; zero routing changes; lowest risk on an incident-prone page |
| Per-page count | **Default 20**, overridable via `sn_notes_per_page` filter | At 13 published, no paging UI appears yet — latent until needed. Plugin sets the value later (Release 2). |
| Control style | **`paginate_links()` numbered** `← 01 02 03 →` | WP-idiomatic, scales, handles edge cases; mono numerals match `.sn-notes-section-count` |
| Setting home | Plugin **Site → Identity & SEO** area, beside `notes_title`/`notes_description` — **with a section refactor** (form is getting cluttered; see Release 2) | All `/notes` config in one place; user flagged the clutter |
| Sequencing | **Theme first** (Release 1), plugin setting + paged-SEO later (Release 2) | Each half independently useful; honors space-out-releases preference |
| Paged SEO | **Release 2** (plugin-side) | Keeps Release 1 truly theme-only; ≤2 pages today makes default canonical harmless short-term |

---

## Release 1 — Theme (this cycle)

Self-contained. Pagination works at the default 20/page with no plugin dependency. Likely **theme v9.6.0** (minor — new user-visible capability) OR a v9.5.x patch — decide at plan time per the versioning rule.

### 1A. Query change — `sn_notes_query_posts()` (`inc/page-notes-render.php`)

```php
function sn_notes_query_posts() {
    $per_page = (int) apply_filters( 'sn_notes_per_page', 20 );
    $per_page = max( 1, min( 100, $per_page ) ); // clamp: defend against a bad filter return
    $paged    = max( 1, (int) get_query_var( 'paged' ) );
    // Belt-and-suspenders: the short-circuit router may not populate the
    // query var cleanly (documented routing ambiguity). Fall back to the
    // raw query-string param, which is what the ?paged=N links carry.
    if ( 1 === $paged && isset( $_GET['paged'] ) ) {
        $paged = max( 1, (int) $_GET['paged'] );
    }
    return new WP_Query( array(
        'post_type'      => 'post',
        'post_status'    => 'publish',
        'posts_per_page' => $per_page,
        'paged'          => $paged,
        'orderby'        => 'date',
        'order'          => 'DESC',
        'no_found_rows'  => false, // pagination needs found_posts / max_num_pages
    ) );
}
```

**Filter contract (new — 5th theme/plugin contract):** `sn_notes_per_page` — theme APPLIES the filter with a default of 20 (so it works standalone); plugin OPTIONALLY hooks it to supply the configured value (Release 2). Graceful degradation: plugin absent/unset → theme default. No cross-package test in Release 1 (the theme is the one applying the filter, not listening to a plugin-dispatched one). The plugin's producer side gets a contract test in Release 2, mirroring the existing 4 contract stubs.

### 1B. Pagination control (after the index `</ol>`)

Render only when `$query->max_num_pages > 1`:

```php
if ( $query->max_num_pages > 1 ) {
    $links = paginate_links( array(
        'base'      => add_query_arg( 'paged', '%#%', home_url( '/notes/' ) ),
        'format'    => '',
        'current'   => max( 1, $paged ),
        'total'     => (int) $query->max_num_pages,
        'type'      => 'array',
        'prev_text' => '←',
        'next_text' => '→',
    ) );
    // Note: paginate_links() does NOT zero-pad numerals. If the "01 02"
    // look is wanted, post-process the returned array (str_pad the page
    // number inside each <a>/<span>) or accept plain "1 2 3" — decide at
    // plan time. Plain numerals are acceptable; zero-pad is cosmetic.
    // render $links inside <nav class="sn-notes-pagination" aria-label="Notes pages">
}
```
- `$paged` hoisted to a render-scope variable (computed once, reused by query + control + count).
- `aria-label` on the `<nav>`; current page marked `aria-current="page"` (paginate_links adds `.current` — style + a11y).
- New CSS `.sn-notes-pagination` in the inlined `<style>`: DM Mono numerals, mid-dot/space separators, 11px floor, current page highlighted. Honor `prefers-reduced-motion` (no animated transitions, consistent with the file's other rules).

### 1C. Count display fix (index header, ~line 617)

Currently `sprintf( '%02d / %02d', $entry_count, $entry_count )` where `$entry_count = post_count` (this page). With paging this is misleading ("20 / 20" on page 1 of 22). Change to the **grand total** now that `found_posts` is available:

```php
$total = (int) $query->found_posts;
// e.g. show total, or range "01–20 / 22"
echo esc_html( sprintf( '%02d', $total ) );
```
`$entry_count` for the pillar/hero meta stays as-is (it's a "latest post" concern, not the index count) — verify no other consumer of `$entry_count` regresses.

### 1D. Tests — `tests/notes-pagination.php` (standalone fixture)

Stub `WP_Query` + `apply_filters` + `get_query_var`. Assert:
- default 20 when no `sn_notes_per_page` filter;
- filter override respected; clamped to [1,100] for out-of-range returns;
- `paged` read from query var, falls back to `$_GET['paged']`;
- `no_found_rows` is `false` in the args;
- control hidden when `max_num_pages <= 1`, shown when > 1.
Headless, no WP load — matches existing theme test pattern. Adds to the theme suite (currently 361/8).

### 1E. Build marker

Bump `SN_NOTES_OVERRIDE_BUILD` in `inc/page-notes-template.php` (the page's deploy-verification marker — convention is to bump on any commit touching /notes rendering).

---

## Release 2 — Plugin (later, separate release)

Additive enhancement; ships whenever, after Release 1 is stable. Likely a plugin minor.

### 2A. `sn_notes_per_page` setting
- New setting key for notes-per-page (e.g. `content.notes_per_page` — new `content` category in `sn_settings`, or extend an existing one; decide at BC).
- Plugin hooks `add_filter( 'sn_notes_per_page', fn() => (int) sn_setting('content.notes_per_page', 20) )`.
- Sanitize/clamp on save (int, 1–100). Default 20 (matches theme default → no behavior change until explicitly set).
- Producer-side contract test in the plugin suite (mirrors the existing 4 contract stubs).

### 2B. Site-tab section refactor (the clutter fix)
The Site → Identity & SEO form already bundles 4 sub-sections (identity/social/OG/SEO-copy) under one Save. Adding a Notes-display field tips it past comfortable. Refactor at BC — options to weigh then: a dedicated "Content"/"Notes" sub-tab, or an internal-TOC section within the existing bundle (per the internal-TOC-vs-sub-tabs decision memory). **Scoped here, detailed at Release 2 BC.**

### 2C. Paged SEO
- Plugin `seo.php`: `/notes/?paged=N` self-canonicals (does NOT collapse to `/notes/`); guard the canonical emitter to append `?paged=N` when `paged > 1`.
- Title: append `— Page N` (theme owns `pre_get_document_title` for /notes; coordinate so the plugin SEO title + theme title filter don't double-append — decide ownership at BC).

---

## Out of scope (YAGNI)
- Pretty-path paging (`/notes/page/2/`) — rejected for router-collision risk.
- Load-more / infinite scroll — against the zero-JS grain of the page.
- Pagination on `/notes/<slug>/` single posts — single posts don't paginate.
- Scheduled-post preview on the index — `future` posts correctly excluded.

## Risks
- **Router/query-var coupling:** `get_query_var('paged')` may be 0 under the short-circuit; the `$_GET['paged']` fallback covers it. Tested in 1D.
- **`found_posts` perf:** flipping `no_found_rows` adds one COUNT query — negligible at tens of posts.
- **Count-display consumers:** confirm `$entry_count` isn't relied on elsewhere as "total" before repurposing the header to `found_posts`.
