# Changelog

All notable changes to the Signal & Noise theme are documented here.

This file holds two things only: **`## [Unreleased]`**, the working log that
accumulates across pull requests, and the **current release**. Everything older
lives in [docs/changelog/](docs/changelog/).

A pull request does not bump `Version` and does not tag — it closes an issue and
adds a bullet below. A release is a separate, deliberate act:
`tools/cut-release.sh`, which stamps `style.css` **and** `readme.txt` together.

## [Unreleased]

## [2026.10.29] - 2026-10-25 — declares its update source; the robots seam feeds core

### Docs
- Three comments no longer say the plugin removes core's `wp_robots`: since plugin 2026.10.64 it feeds the `sn_seo_robots_directives` seam into core's robots tag. The seam and the search-mode noindex are unchanged.

### Changed
- **The theme declares its own update source** (`Update URI: https://github.com/juanlentino/signal-and-noise`, core 6.1+). Core sends it in the update check, and WordPress.org then skips the theme instead of matching it by slug (without it, a same-named WordPress.org theme could answer); the plugin already declares one. The GitHub-tag updater is unchanged.

