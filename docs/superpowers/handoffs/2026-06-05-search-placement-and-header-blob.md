# Handoff — search placement re-decision + header "black blob" bug (2026-06-05)

**Read this first.** Context was cleared after a large session. Top of the next session is the search-trigger issue below.

## TOP PRIORITY: the header search trigger (theme v9.7.0)

Two linked problems, observed on the LIVE site (single-post / `/notes` header):

1. **Renders as a large black "blob" top-right.** The `core/search` block added to `parts/header.html` in v9.7.0 (`{"buttonPosition":"button-only","buttonUseIcon":true,"className":"sn-header-search"}`) renders as an oversized solid-black rounded pill with no visible icon.
   - **Diagnosis:** the theme's global `.wp-element-button` styling gives the search `<button class="wp-block-search__button wp-element-button">` a **black background + border-radius + padding/min-width**. The v9.7.0 CSS (`assets/css/components.css`, `.sn-header-search`) only set the **SVG `fill` to `bone` (#000)** — so it's a black icon on a black button = invisible icon on a big black pill. The button *chrome* was never neutralized.
   - This was invisible to headless checks (tests + PHPCS green); only live render exposed it. (Cross-ref the audit's SR-01/AR-01 theme: "render ≠ what static checks see.")

2. **User does NOT want search living in the header.** ("I don't like the search there… shouldn't live there.") So this is a **placement re-decision, not just a CSS fix.** The original brainstorm choice ("header icon expands + refine field") is being reversed after seeing it live.

### What to do next session (re-open the "search trigger" decision)
Decide WHERE search lives, then implement. Options to put to the user:
- **A) Results-page only (simplest).** Remove the `core/search` block from `parts/header.html` entirely; rely on the refine field already in `templates/search.html`. Add a discoverable entry point — e.g. a **"Search" nav-link** in the header nav (`wp:navigation-link` to `/?s=`) that lands on `search.html` showing an empty state + the refine field. No icon, no blob.
- **B) Mobile-menu only.** Keep search out of the desktop header bar; surface it inside the `overlayMenu` (mobile) nav.
- **C) Footer search.** Put `core/search` in `parts/footer.html`.
- **D) Keep an icon but properly styled + repositioned** (only if the user accepts an icon somewhere) — strip the button chrome: `.sn-header-search .wp-block-search__button { background:transparent; border:0; box-shadow:none; padding:0 .5rem; min-width:0; }` so it's a clean bone icon (→ blood on hover). This is the CSS fix if any icon-trigger survives.

**Whatever is chosen, it likely needs to REVERT/replace the v9.7.0 header change** in `parts/header.html` (the `sn-header-actions` group wrapping nav + the `core/search` block) and adjust/remove the `.sn-header-search` CSS in `components.css`. Ships as **theme v9.7.1** (or fold into the placement change). Gate the release on the user's go per space-out-releases.

**Do NOT just patch the CSS and move on** — the user's signal is placement, not appearance. Confirm the new home before implementing.

## Everything else from the 2026-06-05 session is SHIPPED + clean
- **theme v9.6.0** — `/notes` pagination (tag `v9.6.0`). Working.
- **theme v9.7.0** — on-site search: `templates/search.html` (grouped Notes+Pages), `inc/search-query.php` filter, `components.css` styling, + the header trigger (the part being reconsidered). Tag `v9.7.0`.
- **plugin v4.5.7 → v4.5.8** — `/sn-login` X-Robots header + admin inline-style cleanup + post-ship-audit AR-01 fix. Tag `v4.5.8` (supersedes v4.5.7).
- Post-ship audit ran (12 agents): 5 LOW findings, 0 blockers, all fixed (theme docs `e8d964b`, plugin v4.5.8 `0d9bbb0`).
- Master roadmap: `docs/superpowers/specs/2026-06-05-master-execution-sequence.md`.

The search **filter + results template + grouping** are sound (the audit refuted the search-architecture concerns) — ONLY the header *trigger* placement/appearance is in question. `inc/search-query.php` and `templates/search.html` do not need changes for this.

## Still queued (master sequence)
- 3 frontier candidates not yet built: in-article TOC + progress rail, helpful 404 recovery, IndexNow.
- Major cycle parked: plugin v4.6.0 → v5.0.0, theme v9.8.0 → v10.0.0 (v4.6.0 plan has caught drift to reconcile at BC — ability count 30→34, broken G6 gate, baseline v4.5.1→v4.5.6, nonexistent readme.txt; + WP 7.0 must be confirmed on Cloudways).
