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
- **/resume's lane switch: Music and Operations & AI Strategy.** Owner brief 2026-11-01 #2. The plugin stores both résumés in the page and hides the one not asked for at render time (`?lane=operations`); the theme draws and drives the switch. `assets/css/resume-switch.css`, English /resume only: the switch is drawn like the EN | ES switch (one hairline box split in two, the current lane filled, blood on hover and focus, 44px targets, underlined in forced colors), each lane's headline and subhead, the receipt marks (`verify`, `paper`, `code`), and "What I've Built" as publication cards. `.sn-resume-variant[hidden]` stays hidden over any group display rule. `assets/js/resume-switch.js`, deferred, English /resume only: a click shows the other lane in place, keeps `?lane=operations` (or the bare /resume/) in the address, and Back and Forward follow; a modified or middle click opens the link as a link, and every link works without the script. Each lane carries its own Download, so the download follows the lane with nothing rewritten.
- **llms.txt names both résumés' files.** The résumé formats line adds "JSON Resume, operations" and "PDF, operations" when the plugin lists them.

### Changed
- **Paper prints the lane that shows,** with the bands inside a lane flattened as the top-level ones were, and without the switch or the receipt marks.

  Tests: `tests/resume-switch.php` (15), `tests/llms-txt.php` (+1), `tests/dark-mode.php` (the switch's current-lane fill is allowed, the same case as the EN | ES switch).

## [2026.11.1] - 2026-11-01 — The palette and shortcuts stay out of the way when sn-keys.js is missing

### Fixed
- **A cut keeps the paragraphs inside a bullet** (ported from plugin 2026.11.2). `tools/lib/consolidate-changelog-sections.awk` dropped every blank line in a section, so a bullet's second paragraph ran into the sub-bullet above it once rendered. A blank line before an indented line now survives; the one between bullets is still dropped. Pinned in `tests/changelog-section-consolidation.php`, red both ways.
- **The command palette and the keyboard shortcuts no longer throw on the first view after an install.** Seen live on 2026-10-31: a page the edge had not revalidated since the install served its old HTML, which loads no `sn-keys.js`, while its old `?ver=` URL for `command-palette.js` already returned the new file. That file read `window.snKeys` unguarded and threw (`Cannot read properties of undefined (reading 'singleKeysOn')`), so that view had no palette and no shortcuts. `command-palette.js` and `keyboard-nav.js` now return before doing anything when the helper is missing, as the `sn-copy.js` callers already do. `tests/js/single-key-shortcuts.test.cjs` pins both, seen red with the live TypeError first.

