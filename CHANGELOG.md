# Changelog

All notable changes to the Signal & Noise theme are documented here.

This file holds two things only: **`## [Unreleased]`**, the working log that
accumulates across pull requests, and the **current release**. Everything older
lives in [docs/changelog/](docs/changelog/).

A pull request does not bump `Version` and does not tag — it closes an issue and
adds a bullet below. A release is a separate, deliberate act:
`tools/cut-release.sh`, which stamps `style.css` **and** `readme.txt` together.

## [Unreleased]

## [13.1.1] - 2026-09-12 — the furniture blocks are visible on the editor canvas

### Fixed
- **The furniture blocks are visible on the site-editor canvas.** The eight post-dependent blocks (chip, record, related, cited-by, share, reply, updated date, pillar) render empty in the editor's preview — it has no post to read — so they existed in List View only. An empty render under a REST request now shows a labelled placeholder (`.sn-block-placeholder`, editor-only; the front end never renders through REST and is untouched).

### Changed
- `docs/changelog/v12.md` → `docs/changelog/archive.md`: it has held every past release since 13.0.0, not v12's alone. `tools/cut-release.sh` follows.

