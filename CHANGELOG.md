# Changelog

All notable changes to the Signal & Noise theme are documented here.

This file holds two things only: **`## [Unreleased]`**, the working log that
accumulates across pull requests, and the **current release**. Everything older
lives in [docs/changelog/](docs/changelog/).

A pull request does not bump `Version` and does not tag — it closes an issue and
adds a bullet below. A release is a separate, deliberate act:
`tools/cut-release.sh`, which stamps `style.css` **and** `readme.txt` together.

## [Unreleased]

## [14.1.1] - 2026-09-22 — /resume uses the frame

### Fixed
- **`/resume` uses the wider frame.** From 1440px each experience entry's bullets set in two columns, so the entry fills the frame instead of stopping at 80ch with about a third of the row empty on a wide screen (owner, 2026-09-22); lines stay at about 55 characters at 1440 and 65 at 2000, and an item never splits across the columns. Below 1440, one column as before.
- **The resume summary no longer runs into the credentials box.** The two columns had core's 10.7px gap; they now have the `air--xl` step (64px from 1280 up). Measured live at 1440 and 2000: 64px between the summary and the box, no horizontal scroll.

