# Changelog

All notable changes to the Signal & Noise theme are documented here.

This file holds two things only: **`## [Unreleased]`**, the working log that
accumulates across pull requests, and the **current release**. Everything older
lives in [docs/changelog/](docs/changelog/).

A pull request does not bump `Version` and does not tag — it closes an issue and
adds a bullet below. A release is a separate, deliberate act:
`tools/cut-release.sh`, which stamps `style.css` **and** `readme.txt` together.

## [Unreleased]

## [14.4.2] - 2026-09-27 — the /notes title setting sets the title

### Fixed
- **The "/notes title" setting now sets the /notes `<title>`.** The theme built "Notes — Site" itself in `pre_get_document_title`, which short-circuits the plugin's title filter, so the Settings › Identity & SEO field only ever changed `og:title`. `sn_notes_index_title()` now uses the setting when it is filled, keeps the "— Page N" suffix, and falls back to the default when blank.

