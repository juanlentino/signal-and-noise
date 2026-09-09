# Changelog

All notable changes to the Signal & Noise theme are documented here.

This file holds two things only: **`## [Unreleased]`**, the working log that
accumulates across pull requests, and the **current release**. Everything older
lives in [docs/changelog/](docs/changelog/).

A pull request does not bump `Version` and does not tag — it closes an issue and
adds a bullet below. A release is a separate, deliberate act:
`tools/cut-release.sh`, which stamps `style.css` **and** `readme.txt` together.

## [Unreleased]

## [12.19.0] - 2026-09-09 — the pillar rail moves into the notes hero

### Added
- The Notes page body now renders into the /notes hero's right column, so the pillar rail (and anything else) can be placed, moved, or removed from Pages → Notes without a deploy — the same control /now, /uses and /resume already had. The body was empty and inert before this; nothing appears until something is put there.
- The Pillar Essays block gained a **Compact** density: one row per essay carrying designation, title and reading time, in place of the full card's dek and Read-essay link. Toggle it in the block sidebar; the editor preview re-renders both ways. Full remains the default, so /provenance is unchanged.

### Changed
- The feed privacy sentence is now a span inside the subscribe paragraph rather than a paragraph after it. Same words, same links, same emphasis — but two paragraphs each rounded their last line up to a whole line box, costing seven line boxes for five lines of text.
- The subscribe copy's measure went from 48ch to 72ch. It sits in a 577px column and was rendering at 346px, so the cap, not the column, was holding it back. The dek keeps 48ch.

### Fixed
- The inserter-example rule in `tests/blocks-registry.php` exempted the pillar rail for two reasons welded together, one of which ("declares zero attributes") expired the moment the block gained one. The exemption is now derived from whether the editor previews a block through ServerSideRender, which is the reason that was actually load-bearing.

