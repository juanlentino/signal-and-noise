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
- Sidenotes work on Pages, not just notes. Both rules — the wide float and the narrow inline fallback — were scoped to `.single-post`, so a sidenote inserted on a Page rendered with no styling at all: not a degraded sidenote, an invisible one. They are now scoped to the prose column, which is what the device actually needs and the same scope the pull-quote already used — which is exactly why that block worked everywhere and this one did not.

## [12.20.3] - 2026-09-10 — the rail knows where it sits

### Added
- The Pillar Essays block takes a heading level for its essay titles. They were always `<h2>`, which is right while the rail is the page — as on /provenance today — and wrong the moment that page grows sections of its own, because three essay titles then read as siblings of those sections instead of items in a list. Default stays H2, so nothing existing changes; pick H3 in the block sidebar when the rail sits under page prose.

