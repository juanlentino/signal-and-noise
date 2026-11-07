# Changelog

All notable changes to the Signal & Noise theme are documented here.

This file holds two things only: **`## [Unreleased]`**, the working log that
accumulates across pull requests, and the **current release**. Everything older
lives in [docs/changelog/](docs/changelog/).

A pull request does not bump `Version` and does not tag — it closes an issue and
adds a bullet below. A release is a separate, deliberate act:
`tools/cut-release.sh`, which stamps `style.css` **and** `readme.txt` together.

## [Unreleased]

### Added
- **Ruled rows for the service pages.** `.sn-door` and its label, line and link classes draw the two doors on `/services` and the services on `/services/consulting`, in the same shape as the `/index` rows. They load with the shared components stylesheet. The plugin migration that creates those pages reads best with this theme, so install it first.

### Changed
- **`llms.txt` lists the service doors.** Key pages now name `/services/music/` and `/services/consulting/`, and the Services line describes the hub.

### Fixed
- **The discography script stays on the discography pages.** `/services/music/` shares the slug `music`, so a bare `is_page( 'music' )` loaded the script there. The gate now asks the plugin's `sn_lang_is_page()`, which means the top-level page.

## [2026.11.4] - 2026-11-02 — Arrival bar on phones, prerender opt-outs, CSS specificity helper


- **Fixed:** a heading's arrival rule stays inside the 16px gutter on narrow screens (its offset is capped at 14px below 782px; desktop unchanged); the /resume lane prerender rule now honors core's opt-outs (`rel=nofollow`, `.no-prerender`, `.no-prefetch`); and the shared test specificity helper follows Selectors 4 for `:not()`, `:is()`, `:where()`, attributes and pseudo-elements, which `tests/resume-marks.php` now uses instead of its own copy.


