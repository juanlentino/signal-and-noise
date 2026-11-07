# Changelog

All notable changes to the Signal & Noise theme are documented here.

This file holds two things only: **`## [Unreleased]`**, the working log that
accumulates across pull requests, and the **current release**. Everything older
lives in [docs/changelog/](docs/changelog/).

A pull request does not bump `Version` and does not tag — it closes an issue and
adds a bullet below. A release is a separate, deliberate act:
`tools/cut-release.sh`, which stamps `style.css` **and** `readme.txt` together.

## [Unreleased]

## [2026.11.5] - 2026-11-07 — Ruled rows for the service pages

### Added
- **Ruled rows for the service pages.** `.sn-door` and its label, line and link classes draw the two doors on `/services` and the services on `/services/consulting`, in the same shape as the `/index` rows. They load with the shared components stylesheet. The plugin migration that creates those pages reads best with this theme, so install it first.

### Changed
- **`llms.txt` lists the service doors.** Key pages now name `/services/music/` and `/services/consulting/`, and the Services line describes the hub.

### Fixed
- **The discography script stays on the discography pages.** `/services/music/` shares the slug `music`, so a bare `is_page( 'music' )` loaded the script there. The gate now asks the plugin's `sn_lang_is_page()`, which means the top-level page.

