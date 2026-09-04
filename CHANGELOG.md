# Changelog

All notable changes to the Signal & Noise theme are documented here.

This file holds two things only: **`## [Unreleased]`**, the working log that
accumulates across pull requests, and the **current release**. Everything older
lives in [docs/changelog/](docs/changelog/).

A pull request does not bump `Version` and does not tag — it closes an issue and
adds a bullet below. A release is a separate, deliberate act:
`tools/cut-release.sh`, which stamps `style.css` **and** `readme.txt` together.

## [Unreleased]

### Fixed
- The notes search field no longer zooms the page on an iPhone and leave it
  magnified. `font-size: max(0.9rem, 12px)` computes to **14.4px** (0.9rem is
  14.4px at the default root), and WebKit zooms in when a text-entry control
  under 16px takes focus without zooming back out on blur. It is front-end, so
  nothing in wp-admin's CSS guarded it. Raised to 16px at 782px only; the
  desktop size does not move. (#276)

### Internal
- `tests/ios-form-zoom-guard.php`, ported from the companion plugin where the
  same audit ran. Two things the plugin's version had to learn first, both kept
  here: the value parser resolves `max()`, `min()` and `clamp()` **by function**
  rather than by taking the smallest length — a digit-anchored regex cannot see
  `max(0.9rem, 12px)` at all, and "take the smallest" would flag
  `max(1.1rem, 12px)`, which computes to a perfectly safe 17.6px — and
  pseudo-element rules are excluded, since a `::placeholder` size zooms nothing.
  The first cross-repo sweep of this theme reported exactly one problem and it
  was the wrong one: `input[type="search"]::placeholder` at 12.5px, a false
  positive, sitting directly below the real 14.4px control it had missed.
  The guard's two populations are walked, not listed: stylesheets include the
  root `style.css` (an `assets/`-only walk skips the one sheet every page
  loads), and control tags are read from `inc/`, `patterns/`, `parts/` and
  `templates/`. The derived control-class list is legitimately EMPTY here —
  the theme's single control is styled by element plus ancestor — so the pin is
  on the tag count, which is what distinguishes "no class-styled controls" from
  "the walk opened nothing". (#276)

## [12.18.6] - 2026-09-04 — a home-screen icon that is not a black tile

### Fixed
- The installed PWA's home-screen icon is no longer a black tile. Every
  `apple-touch-icon` on the site was transparent — measured at 63–69% of pixels
  — and iOS renders home-screen transparency as black. The pair is now opaque,
  flattened onto the two grounds the `theme-color` metas already declare, and
  core's own transparent `apple-touch-icon` is filtered out: it runs at
  `wp_head:99`, after this theme's `:1`, so it was the link iOS took. Browser-tab
  icons keep their transparency, which is correct for a tab. (#273)

