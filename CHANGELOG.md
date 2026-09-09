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
- The pillar rail in the /notes hero now has room below it. It ends on a hairline and sat 14px above the subscribe text, which read as attached to it; the gap is now 28px, about one and a half lines. The corpus stamp gets back the 1rem it had before the rail existed. The notes start 19px lower as a result.

## [12.19.1] - 2026-09-09 — the notes body is a slot, and its editor says so

### Fixed
- Editing the Notes page now offers only the Pillar Essays block. Its body is not a page body — it renders into one place, the hero's right column opposite the title — and the editor had no way of saying so, which left a full palette pointed at a 577px rail. Every other Page, and the Site Editor, are untouched.

