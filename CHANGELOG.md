# Changelog

All notable changes to the Signal & Noise theme are documented here.

This file holds two things only: **`## [Unreleased]`**, the working log that
accumulates across pull requests, and the **current release**. Everything older
lives in [docs/changelog/](docs/changelog/).

A pull request does not bump `Version` and does not tag — it closes an issue and
adds a bullet below. A release is a separate, deliberate act:
`tools/cut-release.sh`, which stamps `style.css` **and** `readme.txt` together.

## [Unreleased]

## [13.9.1] - 2026-09-22 — /notes starts under the mark

### Fixed
- **`/notes` starts under the mark, like the four text pages.** Measured live on 13.9.0: its title sat at 43px against the mark's 36px at 1440 (20px against 16px on phones), from its own side padding `clamp(1.25rem, 3vw, 3rem)`, and its 1400px track was `margin: 0 auto`, so on screens wider than 1400px it centred and its left edge moved further off the mark. Both predate 13.9.0, which widened `/notes` but left its alignment alone. The sides now read `--sn-gutter` and the container sits at `margin: 0`. Previewed on the live page before release: title and mark at 36px (1920 and 1440) and 16px (375), no horizontal scroll. `tests/layout-width-system.php` now pins all five pages' gutter (directly or through `padPage`) as well as their margin, red on the old rule both ways; `tests/notes-css-extraction.php`'s geometry pin moves from `0 auto` to `0`.

