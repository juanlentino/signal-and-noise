# Changelog

All notable changes to the Signal & Noise theme are documented here.

This file holds two things only: **`## [Unreleased]`**, the working log that
accumulates across pull requests, and the **current release**. Everything older
lives in [docs/changelog/](docs/changelog/).

A pull request does not bump `Version` and does not tag — it closes an issue and
adds a bullet below. A release is a separate, deliberate act:
`tools/cut-release.sh`, which stamps `style.css` **and** `readme.txt` together.

## [Unreleased]

## [13.5.0] - 2026-09-21 — the JL mark

### Fixed
- **The header logo renders again, and it no longer depends on an upload.** `parts/header.html` hardcoded `<img src="/wp-content/uploads/2026/09/cropped-jl_logo-min-150x150.webp">`, a specific attachment thumbnail; the file stopped resolving (`naturalWidth === 0`) and nothing in the theme, in wp-admin, or in any cache purge could repair a string pointing at a missing upload. The header now carries the JL mark inline as SVG (`viewBox 0 0 146 128`, stroke 20, `stroke="currentColor"`) inside the home link (`aria-label="Juan Lentino — home"`, the SVG `aria-hidden`), so there is no logo request at all. The mark is inked with `bone`, the token the dark layer already flips, so one declaration covers both schemes and the `--sn-mark-invert` filter token and its `invert()` rule are gone with the raster. It keeps the exact heights the image had (80/48, 56/38, 44/34 across the header states and breakpoints, all above the 16px floor) so the header height, and `body` padding-top, `scroll-padding-top` and `article-toc.js`'s offsets keyed to it, do not move; width follows the viewBox and is never set alone. Pinned in `tests/brand-mark.php` (12): inline SVG with the canonical geometry, one accessible name, no `<img>` or uploads URL, zero `cropped-jl_logo` references across the theme, every `.jl-mark` height at or above 16px, no fixed width, the `bone` ink and no second dark rule; 6 red against the old part.

### Changed
- **One icon set, from `assets/brand/`.** `inc/dark-mode.php` emits `favicon.svg` (the JL tile; it carries its own `prefers-color-scheme` rule and inverts with the OS), `favicon.ico` (`sizes="any"` fallback) and one opaque 180px `apple-touch-icon.png`, in that order, and the light/dark PNG pairs (`favicon-32*`, `app-icon-180*`) go with the `jl-logo-*` and `app-icon-192/512` rasters nothing referenced. Core's `site_icon_meta_tags` are now removed wholesale (`__return_empty_array`) rather than only the apple-touch-icon: `wp_site_icon()` runs at `wp_head:99`, after the theme's block at 1, and with `icon-512.png` set as the Site Icon it would have printed a second, competing favicon set; the uploaded Site Icon still serves wp-admin, the login screen and the manifest, which read it directly. `assets/js/dark-mode-toggle.js` stops rewriting favicon `href`s on toggle: the SVG inverts by itself, and the old pair-swap run against the new head wrote the `.ico`'s href onto the SVG link (`tests/dark-mode-chrome-sync.php` goes red on the old script for exactly that). The reader-toggle-against-OS case now updates `theme-color` only; the tab icon follows the OS, which the brief accepts. `tests/head-sweep.php` pins the single-set shape and the SVG-before-ico order; `tests/home-screen-icon-opacity.php` pins both brand PNGs opaque (colour type 2) and the wholesale filter (red on `__return_false`).
- **The site-default `og:image` is the 1200x630 JL card.** The companion plugin seeds `sn_og_image_url` with the wp-admin "Default OG image URL" setting (a small square logo); `inc/notes-og-card.php` now replaces that seed at priority 5 with `assets/brand/og-image.png`, before the plugin's per-post cards (10) and the `/notes` card (20), so singular views are unchanged and home, archives and search preview with the card. The plugin already declares `twitter:card=summary_large_image` and `twitter:image` from the same value. `og-image.png` was re-rendered headlessly from `og-image.html` with the theme's own `dm-mono-400/500-latin.woff2` (the shipped PNG used a substitute monospace); the template's `@font-face` points at `assets/fonts/` and documents the render command. `tests/notes-og-card.php` (+4) and `tests/cross-package-listeners.php` Contract 7 pin the new default.
- **Buttons are square.** `theme.json` `styles.elements.button.border.radius` was `999px`, a pill, against the README's "no rounded corners"; it is `0`. The sweep of `assets/css`, `blocks/`, `patterns/` and `styles/` found four other non-zero radii and left all of them, each deliberate: `50%` on three true circles (`.sn-disco-play-circle`, the 7px `.sn-music-featured__dot` and `.sn-availability__dot`) and `--sn-spotify-radius: 12px`, documented in place as matching Spotify's own embed chrome.

