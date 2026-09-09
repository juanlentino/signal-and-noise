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
- Sub-pillar essays now read as subordinate to their pillar in the rail, on both /notes and /provenance. The designation already said so — major is the pillar, minor is an essay under it — and the rail was rendering them as peers. Derived from the number, so no sub-pillars, one, or nine all render correctly with no further work.

### Fixed
- The pillar rail no longer underlines a whole row on hover. Compact rows are links, so WordPress's own hover rule drew a line across the number, title and reading time together; the affordance was always the title turning red. Keyboard focus keeps a real outline.

### Fixed
- The pillar rail in the /notes hero now has room below it. It ends on a hairline and sat 14px above the subscribe text, which read as attached to it; the gap is now 28px, about one and a half lines. The corpus stamp gets back the 1rem it had before the rail existed. The notes start 19px lower as a result.

## [12.19.1] - 2026-09-09 — the notes body is a slot, and its editor says so

### Fixed
- Editing the Notes page now offers only the Pillar Essays block. Its body is not a page body — it renders into one place, the hero's right column opposite the title — and the editor had no way of saying so, which left a full palette pointed at a 577px rail. Every other Page, and the Site Editor, are untouched.

