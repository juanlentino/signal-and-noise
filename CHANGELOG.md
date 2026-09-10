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
- The Pillar Essays block takes a heading level for its essay titles. They were always `<h2>`, which is right while the rail is the page — as on /provenance today — and wrong the moment that page grows sections of its own, because three essay titles then read as siblings of those sections instead of items in a list. Default stays H2, so nothing existing changes; pick H3 in the block sidebar when the rail sits under page prose.

## [12.20.2] - 2026-09-09 — the accent stops being a texture

### Fixed
- The RSS and JSON Feed links in the notes hero now carry a visible underline at rest. They were distinguished from the surrounding prose by colour alone at 1.23:1, where WCAG 1.4.1 asks for 3:1 when colour is the only cue — the underline already existed but was spent entirely on hover, a state touch and keyboard users may never reach.
- The corpus stamp moved out of the hero and into the index section header, beside the count it was restating. "40 entries" in the masthead and "Notes: Index 40" two hundred pixels below were the same fact twice. The right column drops from three jobs to two and the first note rises 28px.
- The feed privacy sentence is its own paragraph again. Folding it into the line above saved 25px and cost the column its air.

### Changed
- The meta separator no longer uses the accent colour. The hero was carrying twelve red marks across six unrelated jobs; red now marks wayfinding or the work itself, not punctuation.

