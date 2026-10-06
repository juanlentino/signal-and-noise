# Changelog

All notable changes to the Signal & Noise theme are documented here.

This file holds two things only: **`## [Unreleased]`**, the working log that
accumulates across pull requests, and the **current release**. Everything older
lives in [docs/changelog/](docs/changelog/).

A pull request does not bump `Version` and does not tag — it closes an issue and
adds a bullet below. A release is a separate, deliberate act:
`tools/cut-release.sh`, which stamps `style.css` **and** `readme.txt` together.

## [Unreleased]

## [15.8.0] - 2026-10-06 — reduced motion reaches the menu, docs match the code

### Fixed
- **Reduced motion now stops the open mobile menu's link fade too.** Its `transition: color .2s !important` (critical.css, layout.css) outranked base.css's `transition: none !important` reset; each now carries its own reduce override. `tests/reduced-motion-important.php` fails on any `!important` transition or animation without one.

### Changed
- **The theme screenshot is current.** `screenshot.png` (the README image and the wp-admin theme card) was the v3.6.0 site from March; it is now the live home page at 1200×900 in light.
- **The cron-liveness guard watches `contrast.yml`** (#511): a live contrast check that stops running now fails CI instead of going quiet.
- **README and readme.txt match the code.** No hero veil (removed in 13.8.0; the gradients left are the scanline, the link underline and an embed mask), the panel shadow token, Workflow and Personal in the page list, `/notes` as a PHP-laid-out Page, fifteen dynamic blocks (three authoring, twelve template), and a fixed single-height header rather than a shrinking one.
- **Docs:** `docs/ACCESSIBILITY.md` lists the live contrast check, the dim-text link guard and the reduced-motion reset, and says what the live check can see; `docs/MONITORING.md` documents `contrast.yml`; `readme.txt` names both.
- **README:** Now and Uses described as spec sheets; a Front-end entry for the accessibility rules and the live contrast check.

