# Changelog

All notable changes to the Signal & Noise theme are documented here.

This file holds two things only: **`## [Unreleased]`**, the working log that
accumulates across pull requests, and the **current release**. Everything older
lives in [docs/changelog/](docs/changelog/).

A pull request does not bump `Version` and does not tag — it closes an issue and
adds a bullet below. A release is a separate, deliberate act:
`tools/cut-release.sh`, which stamps `style.css` **and** `readme.txt` together.

## [Unreleased]

## [12.18.6] - 2026-09-04 — a home-screen icon that is not a black tile

### Fixed
- The installed PWA's home-screen icon is no longer a black tile. Every
  `apple-touch-icon` on the site was transparent — measured at 63–69% of pixels
  — and iOS renders home-screen transparency as black. The pair is now opaque,
  flattened onto the two grounds the `theme-color` metas already declare, and
  core's own transparent `apple-touch-icon` is filtered out: it runs at
  `wp_head:99`, after this theme's `:1`, so it was the link iOS took. Browser-tab
  icons keep their transparency, which is correct for a tab. (#273)

