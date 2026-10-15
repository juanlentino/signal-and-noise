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
- **/cv/, the Spanish résumé, renders like /resume.** On Spanish pages the header nav label reads "Curriculum vitae" (owner, 2026-10-15). The résumé stylesheet now loads on /cv/ as well: it was gated on the slug `resume`, so the Spanish page would have rendered unstyled. The page itself is paired to /resume by the companion plugin's Spanish pages list.

## [2026.10.6] - 2026-10-15 — One EN | ES switch in the header on every page

### Changed
- **One language switch, in the header, on every page.** The EN | ES control is now a bordered, two-part switch drawn like the theme toggle. The current language is filled; the other is the link, with the toggle's hover and focus. On a page with a translation it goes there; on an English-only page (notes, résumé, colophon), ES goes to /inicio/ and its accessible name says "página de inicio". It renders nothing until the Spanish home is published.

### Removed
- **The footer's language link.** The header switch is the one place (owner, 2026-10-15).

