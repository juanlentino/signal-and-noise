# Admin UI Polish + Top-Level Menu (v1.8.1) — Design

**Date:** 2026-05-16
**Repo:** signal-and-noise-tools (companion plugin)
**Ships as:** v1.8.1 (patch bump within v1.8.x)
**Scope:** UX polish only — no schema, no API, no behavior change.
**Goal:** Replace 25+ duplicated inline-style instances in `inc/admin-page.php` with a single enqueued stylesheet of CSS variables + component classes, add long-form UX affordances (TOC + sticky save bar) to the Identity tab, and promote the admin page from `Appearance → Signal & Noise` to a top-level menu item.

## Architecture

- **New:** `assets/admin.css` (~100 LOC). CSS variables + component classes. Loaded only on the SN admin page via `admin_enqueue_scripts` with a hook-suffix guard.
- **Modified:** `inc/admin-page.php` — replace `add_theme_page()` with `add_menu_page()`; enqueue the stylesheet; swap inline style strings for class attrs; add Identity-tab TOC and sticky save bar; rename the auto-generated first submenu from "Signal & Noise" → "Dashboard".
- **Modified:** `docs/WP-7.0-CHECKLIST.md` — update two URLs from `wp-admin/themes.php?page=…` to `wp-admin/admin.php?page=…`.
- **Untouched:** `cloudflare-purge.php`, `plausible-admin.php`, `reading-time.php`, `rss-plausible-tracker.php`. Their inline styles win on specificity; cleaned up in a future v1.9.0 component pass.

## Menu move

| | Before | After |
|---|---|---|
| Registration | `add_theme_page()` | `add_menu_page()` |
| URL | `wp-admin/themes.php?page=sn-theme-options` | `wp-admin/admin.php?page=sn-theme-options` |
| Slug | `sn-theme-options` | `sn-theme-options` (unchanged — `?tab=` URLs stay valid) |
| Hook suffix | `appearance_page_sn-theme-options` | `toplevel_page_sn-theme-options` |
| Menu position | (submenu of Appearance) | `81` (right after Settings) |
| Icon | (inherited) | `dashicons-megaphone` |
| First submenu | "Signal & Noise" (auto-generated, duplicates parent) | "Dashboard" (renamed via `add_submenu_page` override) |

The hook suffix value is captured as a static so the enqueue guard can't typo it.

## CSS components

| Class | Purpose |
|---|---|
| `.sn-prose` | Intro paragraph block (muted, max 680px) |
| `.sn-card` | Surface (action cards on Dashboard) |
| `.sn-card-grid` | Flex-wrap container for card rows |
| `.sn-helper` | Sub-label helper text below buttons |
| `.sn-pill` + `--ok / --warn / --err` | Status badges with colored dot prefix |
| `.sn-section-h` | Section header inside Identity form |
| `.sn-toc` | Anchor strip atop Identity tab — section jumplinks |
| `.sn-savebar` | Sticky bottom bar with save button, Identity tab only |

## CSS variables (single source of truth)

```css
:root {
  --sn-surface: #fff;
  --sn-border: #c3c4c7;
  --sn-text: #1d2327;
  --sn-text-muted: #646970;
  --sn-radius: 4px;
  --sn-space-1: 4px;
  --sn-space-2: 8px;
  --sn-space-3: 12px;
  --sn-space-4: 16px;
  --sn-space-5: 24px;
  --sn-ok: #00a32a;
  --sn-warn: #dba617;
  --sn-err: #d63638;
  --sn-link: #2271b1;
}
```

Status colors match WP core's palette so pills feel native.

## Identity tab UX changes

1. **TOC** at the top — `Identity · Social · Open Graph · Login · SEO Copy`. Each `<h2>` gets a matching `id` for anchor jumps.
2. **Sticky save bar** at the bottom — `position: sticky; bottom: 0;` pure CSS. Always visible while editing the long form. No JS dirty-tracking (out of scope).
3. **Tightened form-table row padding** — reduce default `8px` to `6px` so the form feels less endless.

## Dashboard polish

1. Status row: `<span class="sn-pill sn-pill--ok">Clean</span>` etc. — scannable at a glance.
2. Action cards: swap inline styles for `.sn-card`.

## Enqueue strategy

```php
add_action( 'admin_enqueue_scripts', function( $hook ) {
    if ( $hook === sn_admin_page_hook() ) {
        wp_enqueue_style( 'sn-admin', SNT_URL . 'assets/admin.css', array(), SNT_VERSION );
    }
} );
```

`sn_admin_page_hook()` returns the static hook suffix captured at registration time. Cache-busted by `SNT_VERSION`.

## Acceptance criteria

1. New top-level menu "Signal & Noise" appears with megaphone icon at position 81. Submenu shows "Dashboard" (not "Signal & Noise / Signal & Noise").
2. Visiting `wp-admin/admin.php?page=sn-theme-options&tab=identity` loads the Identity tab.
3. Visiting the old URL `wp-admin/themes.php?page=sn-theme-options` 404s (acceptable — patch-level URL change).
4. `assets/admin.css` loads on the SN admin page; does NOT load on other admin pages (verify via DevTools Network).
5. Dashboard status row shows pill badges; action cards render as `.sn-card`.
6. Identity tab shows TOC at top; clicking each link jumps to the section.
7. Identity tab shows sticky save bar at the bottom; Save button stays visible while scrolling.
8. All other tabs (Cloudflare/Plausible/RSS/Reading Time/Links) render exactly as before.

## Ships as

**v1.8.1.** PATCH bump per project caps (still within `1.8.x`). CHANGELOG entry under "Changed".

## Out of scope (deferred to v1.9.0)

- Refactor `cloudflare-purge.php`, `plausible-admin.php`, `reading-time.php` to use the new `.sn-card` / `.sn-pill` classes.
- PHP helper functions (`sn_admin_card()`, `sn_admin_status_pill()`).
- JS-driven dirty-tracking on the Identity form save bar.
- Promoting each tab to its own submenu entry (overkill at 7 tabs).
