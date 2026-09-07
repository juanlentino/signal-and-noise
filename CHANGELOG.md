# Changelog

All notable changes to the Signal & Noise theme are documented here.

This file holds two things only: **`## [Unreleased]`**, the working log that
accumulates across pull requests, and the **current release**. Everything older
lives in [docs/changelog/](docs/changelog/).

A pull request does not bump `Version` and does not tag — it closes an issue and
adds a bullet below. A release is a separate, deliberate act:
`tools/cut-release.sh`, which stamps `style.css` **and** `readme.txt` together.

## [Unreleased]

## [12.18.10] - 2026-09-07 — Fix theme preference persistence and composing keyboard input

### Fixed
- Color-mode toggles retain the current page choice when browser storage is unavailable, keeping both labels and repeated clicks consistent.
- Search and note keyboard shortcuts leave IME composition keys alone, so accepting or dismissing a candidate does not navigate or close the palette.

