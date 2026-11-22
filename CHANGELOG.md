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
- **A layout-shift reading now says which element moved.** The beacon's CLS event (`vc`) may carry `s`, a short selector for the element that moved most in the largest shift: the lower-case tag, the id, and up to three class names, 96 characters at most. It names the page's own markup and nothing about the reader: no text, no attributes, no ancestors. An id or class with a character outside letters, digits, `_` and `-` is left out, and when there is nothing to name, or the selector code throws, the event goes out exactly as before. LCP and INP are unchanged. It reads the `entries` the vendored web-vitals build already passes, so no new script (`assets/js/sn-beacon.js`). The collector has to accept the new key before it shows anywhere. Test: `tests/js/beacon-cls-selector.test.cjs` (8).

## [2026.11.16] - 2026-11-22 — Arriving at a numbered note heading keeps its numeral

### Fixed
- **Arriving at a numbered note heading no longer squashes its numeral.** A note with three or more sections prints 01, 02 above each h2 in the heading's `::before`, and the arrival bar drew in the same `::before`, so a jump to that heading (by a #fragment link, and since 2026.11.15 by a contents click) turned the numeral into the 2px bar and pulled the heading up a line. On those headings the numeral keeps `::before` and the bar draws in `::after` (`assets/css/article.css`). Seen on a live note with the rule injected: the numeral stays, the heading does not move. Test: `tests/transmission-motion.php` (+6).

