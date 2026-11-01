# Changelog

All notable changes to the Signal & Noise theme are documented here.

This file holds two things only: **`## [Unreleased]`**, the working log that
accumulates across pull requests, and the **current release**. Everything older
lives in [docs/changelog/](docs/changelog/).

A pull request does not bump `Version` and does not tag — it closes an issue and
adds a bullet below. A release is a separate, deliberate act:
`tools/cut-release.sh`, which stamps `style.css` **and** `readme.txt` together.

## [Unreleased]

## [2026.11.1] - 2026-11-01 — The palette and shortcuts stay out of the way when sn-keys.js is missing

### Fixed
- **A cut keeps the paragraphs inside a bullet** (ported from plugin 2026.11.2). `tools/lib/consolidate-changelog-sections.awk` dropped every blank line in a section, so a bullet's second paragraph ran into the sub-bullet above it once rendered. A blank line before an indented line now survives; the one between bullets is still dropped. Pinned in `tests/changelog-section-consolidation.php`, red both ways.
- **The command palette and the keyboard shortcuts no longer throw on the first view after an install.** Seen live on 2026-10-31: a page the edge had not revalidated since the install served its old HTML, which loads no `sn-keys.js`, while its old `?ver=` URL for `command-palette.js` already returned the new file. That file read `window.snKeys` unguarded and threw (`Cannot read properties of undefined (reading 'singleKeysOn')`), so that view had no palette and no shortcuts. `command-palette.js` and `keyboard-nav.js` now return before doing anything when the helper is missing, as the `sn-copy.js` callers already do. `tests/js/single-key-shortcuts.test.cjs` pins both, seen red with the live TypeError first.

