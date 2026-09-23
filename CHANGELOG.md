# Changelog

All notable changes to the Signal & Noise theme are documented here.

This file holds two things only: **`## [Unreleased]`**, the working log that
accumulates across pull requests, and the **current release**. Everything older
lives in [docs/changelog/](docs/changelog/).

A pull request does not bump `Version` and does not tag — it closes an issue and
adds a bullet below. A release is a separate, deliberate act:
`tools/cut-release.sh`, which stamps `style.css` **and** `readme.txt` together.

## [Unreleased]

## [14.3.0] - 2026-09-23 — Home, like every page

### Changed
- **Home is edited like every other page.** `templates/front-page.html` now renders the front-page Page's content (Settings → Reading → Homepage), the way About and Services do, instead of hardcoding the hero. A hero edit made in the Site Editor was a template override, and `inc/template-maintenance.php` deletes those on every activation and every Purge All Caches, so the owner's line kept disappearing (2026-09-23); Page content is never touched by updates or purges. The companion plugin seeds the Page once (install plugin 17.8.0 first). `inc/front-page-fallback.php` renders the new `patterns/home-hero.php` (not in the inserter) whenever that Page is empty, so the home page can never render blank. The hero's raw-HTML wrapper became a Group block so the editor can hold it; measured in headless Chrome at 1440 and 375px, the hero rendered from Page content is pixel-identical to today's (0 differing rows; a 4px control shift shows 257). `tests/front-page-cms.php` (9) pins the template, the pattern and the fallback (dropping the front-page check fails it); `tests/layout-width-system.php` now finds the wide track in the pattern.

