# Changelog

All notable changes to the Signal & Noise theme are documented here.

This file holds two things only: **`## [Unreleased]`**, the working log that
accumulates across pull requests, and the **current release**. Everything older
lives in [docs/changelog/](docs/changelog/).

A pull request does not bump `Version` and does not tag — it closes an issue and
adds a bullet below. A release is a separate, deliberate act:
`tools/cut-release.sh`, which stamps `style.css` **and** `readme.txt` together.

## [Unreleased]

## [2026.11.16] - 2026-11-22 — Arriving at a numbered note heading keeps its numeral

### Fixed
- **Arriving at a numbered note heading no longer squashes its numeral.** A note with three or more sections prints 01, 02 above each h2 in the heading's `::before`, and the arrival bar drew in the same `::before`, so a jump to that heading (by a #fragment link, and since 2026.11.15 by a contents click) turned the numeral into the 2px bar and pulled the heading up a line. On those headings the numeral keeps `::before` and the bar draws in `::after` (`assets/css/article.css`). Seen on a live note with the rule injected: the numeral stays, the heading does not move. Test: `tests/transmission-motion.php` (+6).

