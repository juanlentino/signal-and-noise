# Changelog

All notable changes to the Signal & Noise theme are documented here.

This file holds two things only: **`## [Unreleased]`**, the working log that
accumulates across pull requests, and the **current release**. Everything older
lives in [docs/changelog/](docs/changelog/).

A pull request does not bump `Version` and does not tag — it closes an issue and
adds a bullet below. A release is a separate, deliberate act:
`tools/cut-release.sh`, which stamps `style.css` **and** `readme.txt` together.

## [Unreleased]

## [15.5.0] - 2026-10-06 — Uses is a spec sheet

### Changed
- **Uses is a spec sheet, like the colophon.** Each section is a band: its label and count on the left, its items two across on the right, each item its name over its note. One column of items under 640px, and the label stacks above them under 900px. CSS only (`assets/css/uses.css`); the markup, the items and their words are unchanged. At 1440px the page drops from 1,848px to about 1,370px tall.
- The new `uses.css` section comment is dated rather than naming a version it was never cut as.

