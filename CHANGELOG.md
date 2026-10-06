# Changelog

All notable changes to the Signal & Noise theme are documented here.

This file holds two things only: **`## [Unreleased]`**, the working log that
accumulates across pull requests, and the **current release**. Everything older
lives in [docs/changelog/](docs/changelog/).

A pull request does not bump `Version` and does not tag — it closes an issue and
adds a bullet below. A release is a separate, deliberate act:
`tools/cut-release.sh`, which stamps `style.css` **and** `readme.txt` together.

## [Unreleased]

## [15.6.1] - 2026-10-06 — the notes month label is muted by rust alone

### Fixed
- **The notes archive's month labels fell under AA in dark.** The label is rust, then faded again with opacity .75, which took it to 4.25:1 on the dark ground. Rust alone is the mute (7.4:1 dark, 5.7:1 light); the opacity is gone. Found by a sitewide computed contrast audit.

