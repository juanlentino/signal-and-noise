# Changelog

All notable changes to the Signal & Noise theme are documented here.

This file holds two things only: **`## [Unreleased]`**, the working log that
accumulates across pull requests, and the **current release**. Everything older
lives in [docs/changelog/](docs/changelog/).

A pull request does not bump `Version` and does not tag — it closes an issue and
adds a bullet below. A release is a separate, deliberate act:
`tools/cut-release.sh`, which stamps `style.css` **and** `readme.txt` together.

## [Unreleased]

### Fixed
- **The lane switch reads a repeated `?lane=` the way the server does: the last one wins.** `?lane=operations&lane=music` rendered music (PHP's `$_GET` keeps the last value) and then the script, reading the first, flipped to operations after paint (PERF-1). With no `?lane`, a `#resume-operations` or `#resume-music` link now names its lane, where before it showed music with its target hidden; switching away from such an address drops that anchor, so Back and a reload land on the lane chosen. `assets/js/resume-switch.js`. Tests: `tests/js/resume-switch.test.cjs` (new, drives the script under node:vm: an inverted `show()` passed the source pins 19 of 19 and fails here), `tests/resume-switch.php` (+1).

## [2026.11.2] - 2026-11-02 — The résumé's lane switch: Music and Operations & AI Strategy

### Added
- **/resume's lane switch: Music and Operations & AI Strategy.** Owner brief 2026-11-01 #2. The plugin stores both résumés in the page and hides the one not asked for at render time (`?lane=operations`); the theme draws and drives the switch. `assets/css/resume-switch.css`, English /resume only: the switch is drawn like the EN | ES switch (one hairline box split in two, the current lane filled, blood on hover and focus, 44px targets, underlined in forced colors), each lane's headline and subhead, the receipt marks (`verify`, `paper`, `code`), and "What I've Built" as publication cards. `.sn-resume-variant[hidden]` stays hidden over any group display rule. `assets/js/resume-switch.js`, deferred, English /resume only: a click shows the other lane in place, keeps `?lane=operations` (or the bare /resume/) in the address, and Back and Forward follow; a modified or middle click opens the link as a link, and every link works without the script. Each lane carries its own Download, so the download follows the lane with nothing rewritten.
- **The script reads `?lane=` exactly as the server does** (lowercased, stripped to `[a-z0-9_-]`, then operations or music), so `?lane=Operations` keeps the lane the page was rendered with; a switch changes only `lane` in the address, so a draft preview keeps its preview query (independent review of #2346).
- **llms.txt names both résumés' files.** The résumé formats line adds "JSON Resume, operations" and "PDF, operations" when the plugin lists them.

### Changed
- **Paper prints the lane that shows,** with the bands inside a lane flattened as the top-level ones were, and without the switch or the receipt marks.

  Tests: `tests/resume-switch.php` (15), `tests/llms-txt.php` (+1), `tests/dark-mode.php` (the switch's current-lane fill is allowed, the same case as the EN | ES switch).

