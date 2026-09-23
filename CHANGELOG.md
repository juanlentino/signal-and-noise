# Changelog

All notable changes to the Signal & Noise theme are documented here.

This file holds two things only: **`## [Unreleased]`**, the working log that
accumulates across pull requests, and the **current release**. Everything older
lives in [docs/changelog/](docs/changelog/).

A pull request does not bump `Version` and does not tag — it closes an issue and
adds a bullet below. A release is a separate, deliberate act:
`tools/cut-release.sh`, which stamps `style.css` **and** `readme.txt` together.

## [Unreleased]

### Changed
- **Home is edited like every other page.** `templates/front-page.html` now renders the front-page Page's content (Settings → Reading → Homepage), the way About and Services do, instead of hardcoding the hero. A hero edit made in the Site Editor was a template override, and `inc/template-maintenance.php` deletes those on every activation and every Purge All Caches, so the owner's line kept disappearing (2026-09-23); Page content is never touched by updates or purges. The companion plugin seeds the Page once (install plugin 17.8.0 first). `inc/front-page-fallback.php` renders the new `patterns/home-hero.php` (not in the inserter) whenever that Page is empty, so the home page can never render blank. The hero's raw-HTML wrapper became a Group block so the editor can hold it; measured in headless Chrome at 1440 and 375px, the hero rendered from Page content is pixel-identical to today's (0 differing rows; a 4px control shift shows 257). `tests/front-page-cms.php` (9) pins the template, the pattern and the fallback (dropping the front-page check fails it); `tests/layout-width-system.php` now finds the wide track in the pattern.

## [14.2.1] - 2026-09-23 — the readme, licensed

### Fixed
- **readme.txt meets the Theme Handbook's metadata rules.** `Contributors:` is now the owner's wordpress.org username (`juanml`), not a display name, and a `== Copyright ==` section declares the two bundled fonts: Bebas Neue (Copyright 2019 The Bebas Neue Project Authors) and DM Mono (Copyright 2020 The DM Mono Project Authors), both SIL OFL 1.1, with sources and the files they cover. The copyright lines are read from each font's own `name` table.

### Docs
- **`docs/RESUME-PDF.md` describes the resume PDF as shipped** (plugin 17.7.0 through 17.7.2): the Website field, the location fallback to the web contact line, the phone checkbox off by default with a private copy that is never stored, why that control must be a checkbox and not an `os-switch`, and times shown in the site timezone.

