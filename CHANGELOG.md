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
- **The admin bar no longer shows the theme as an update after it is installed.** The install request runs the old code to its end, and anything in it that rebuilt `update_themes` wrote "update available" for the version just installed; when that write landed after the version watchdog had already run (the desk fires many requests at once after an install), the badge stayed until WordPress re-checked. A read-time filter on `site_transient_update_themes` now moves an S&N entry whose new version is not newer than the installed one to `no_update`. The plugin's twin shipped in plugin 23.3.2.
- **The live contrast check closes its last three gaps** (#518). A link or wrapper with `display: contents` is measured by what it contains instead of skipped for having no box. Only a stylesheet or script that redirects into another directory makes a run inconclusive (a font or image is the same bytes wherever it came from). Asset failures are recorded with or without the allow-list token, so a local run is as strict as CI.
- **The live contrast check sees what it used to miss** (#509, the eight Codex P2s left from #508). Links: every visible text node is measured and the worst counts, so an sr-only label first in a link no longer stands in for the visible words; a sentence wrapped in sibling spans inside a prose element counts as running text. Colors: lab, lch, oklab, oklch, display-p3 and signed srgb are converted to clamped sRGB instead of skipped. Grounds: translucent backgrounds composite in order in both measures; a thin gradient that repeats down its box is a ground, not an underline. Pages: an unloaded page is not measured, so an edge block reads inconclusive, not failed. Runner: same-origin redirects are followed with the allow-list token inside the route, and a failed routed fetch is aborted instead of stalling the page. Verified: on the live site the new script matches the old (33 pages, 1,290 pairs, 100 links, 0 failures), and on fixtures it catches the five cases the old one missed or mismeasured.

## [15.8.0] - 2026-10-06 — reduced motion reaches the menu, docs match the code

### Fixed
- **Reduced motion now stops the open mobile menu's link fade too.** Its `transition: color .2s !important` (critical.css, layout.css) outranked base.css's `transition: none !important` reset; each now carries its own reduce override. `tests/reduced-motion-important.php` fails on any `!important` transition or animation without one.

### Changed
- **The theme screenshot is current.** `screenshot.png` (the README image and the wp-admin theme card) was the v3.6.0 site from March; it is now the live home page at 1200×900 in light.
- **The cron-liveness guard watches `contrast.yml`** (#511): a live contrast check that stops running now fails CI instead of going quiet.
- **README and readme.txt match the code.** No hero veil (removed in 13.8.0; the gradients left are the scanline, the link underline and an embed mask), the panel shadow token, Workflow and Personal in the page list, `/notes` as a PHP-laid-out Page, fifteen dynamic blocks (three authoring, twelve template), and a fixed single-height header rather than a shrinking one.
- **Docs:** `docs/ACCESSIBILITY.md` lists the live contrast check, the dim-text link guard and the reduced-motion reset, and says what the live check can see; `docs/MONITORING.md` documents `contrast.yml`; `readme.txt` names both.
- **README:** Now and Uses described as spec sheets; a Front-end entry for the accessibility rules and the live contrast check.

