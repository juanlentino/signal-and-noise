# Changelog

All notable changes to the Signal & Noise theme are documented here.

This file holds two things only: **`## [Unreleased]`**, the working log that
accumulates across pull requests, and the **current release**. Everything older
lives in [docs/changelog/](docs/changelog/).

A pull request does not bump `Version` and does not tag — it closes an issue and
adds a bullet below. A release is a separate, deliberate act:
`tools/cut-release.sh`, which stamps `style.css` **and** `readme.txt` together.

## [Unreleased]

## [2026.11.17] - 2026-11-22 — A CLS reading names the element that moved

### Added
- **A layout-shift reading now says which element moved.** The beacon's CLS event (`vc`) may carry `s`, a short selector for the element that moved most in the largest shift: the lower-case tag, the id, and up to three class names, 96 characters at most. It names the page's own markup and nothing about the reader: no text, no attributes, no ancestors. `html` and `body` are named by tag alone, since WordPress puts `logged-in` among the body classes. An id or class with a character outside letters, digits, `_` and `-` is left out, and when there is nothing to name, or the selector code throws, the event goes out exactly as before. LCP and INP are unchanged. It reads the `entries` the vendored web-vitals build already passes, so no new script (`assets/js/sn-beacon.js`). The collector has to accept the new key before it shows anywhere. Test: `tests/js/beacon-cls-selector.test.cjs` (9).

