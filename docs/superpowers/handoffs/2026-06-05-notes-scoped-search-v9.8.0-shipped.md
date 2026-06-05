# Handoff — Notes-scoped search & archive SHIPPED (theme v9.8.0, 2026-06-05)

**Supersedes** `2026-06-05-search-placement-and-header-blob.md` — that OPEN issue is now RESOLVED. Nothing search-related is outstanding.

## What shipped: theme v9.8.0 (tag `v9.8.0`, release `220522d`, on `main`, live-verified)

Resolved both halves of the prior session's open issue — the v9.7.0 header "black blob" **and** the search-placement re-decision (user: "search shouldn't live in the header").

- **Header trigger reverted.** Removed the `core/search` block + the `sn-header-actions` wrapper from `parts/header.html` (nav is a direct child of `.sn-header` again); removed the `.sn-header-*` CSS. No `.wp-element-button` chrome → no blob, by construction.
- **Global posts+pages search deleted.** `templates/search.html`, `inc/search-query.php` (+ its `functions.php` require + `tests/search-query.php`) are gone.
- **Search now lives in `/notes` as a two-state archive** (`inc/page-notes-render.php`, the PHP-authoritative renderer):
  - **Browse** (`/notes/`): today's layout + a hand-rolled `<form class="sn-notes-search">` above the index.
  - **Search** (`/notes/?s=term`): hero full-width (`.sn-notes-top.is-search`), pillars + divider hidden, `Notes — Search · "term"` heading, `Clear ✕` link, `N notes found` summary, catalog result rows, branded `No notes match "…"` empty state, pagination carries the term.
  - **Notes-only by construction**: `post_type=post` (the whole Notes corpus); Pages are never queried.
  - Stray site-wide `/?s=` 302-redirects to `/notes/?s=` (one search surface).
- New pure helpers: `sn_notes_search_term()`, `sn_notes_pagination_add_args()` (render), `sn_notes_search_redirect_target()` (template). Build marker → `2026-06-05-notes-search-v11`.

## Verification (all green)
- Headless: 11 suites / 394 assertions / 0 failures (incl. new `tests/notes-search.php` 13, `tests/notes-redirect.php` 4). PHPCS clean (falsification-confirmed on this worktree path).
- **Adversarial review workflow** (5 dimensions → refute-by-default verify): 13 findings, **6 confirmed & fixed**, 7 refuted. Notable fix: the hand-rolled search input had `outline:none` but `type=search` wasn't in the theme's global `:focus-visible` allow-list → restored a 2px blood keyboard ring (WCAG 2.4.7). Also: suppressed the hero meta line in search state (was mislabeling result counts as "N entries · Last updated"), WebKit search-cancel reset, scoped `.sn-notes-empty`.
- **Live render** (juanlentino.com, post-install): DOM matrix across browse/search/no-match all correct; `/?s=` → 302; header blob gone; **browser screenshots** confirm the field renders as a clean thin underline (no blob) in both states. (No local WP exists — live verify is post-deploy via curl matrix + Chrome screenshot. Lesson reinforced: render ≠ static checks; the v9.7.0 blob passed every headless gate.)
- Note: the `sn-notes-build` HTML-comment marker is invisible via `curl` on live — Cloudflare HTML auto-minify strips comments. Verify deploys via real DOM elements instead (`class="sn-notes-search"` present / `wp-block-search__button` absent), not the marker.

## Docs/specs
- Spec: `docs/superpowers/specs/2026-06-05-notes-scoped-search-archive-design.md`
- Plan: `docs/superpowers/plans/2026-06-05-notes-scoped-search-archive.md`

## Still queued (unchanged)
- **3 frontier candidates** not yet built: in-article TOC + progress rail, helpful 404 recovery, IndexNow.
- **Major cycle parked:** plugin v4.6.0 → v5.0.0, theme → v10.0.0. The v4.6.0 plan has caught drift to reconcile at BC (ability count 30→34, broken G6 gate, baseline v4.5.1→v4.5.6, nonexistent readme.txt) + WP 7.0 must be confirmed on Cloudways.
- **Prep-minor renumber:** the parked "prep for theme v10.0.0" minor (`docs/superpowers/plans/2026-05-27-v9.6.0.md`) now renumbers to **v9.9.0** (v9.8.0 was consumed by this search cycle).
