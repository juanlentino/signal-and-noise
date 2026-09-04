# Changelog

All notable changes to the Signal & Noise theme are documented here.

This file holds two things only: **`## [Unreleased]`**, the working log that
accumulates across pull requests, and the **current release**. Everything older
lives in [docs/changelog/](docs/changelog/).

A pull request does not bump `Version` and does not tag — it closes an issue and
adds a bullet below. A release is a separate, deliberate act:
`tools/cut-release.sh`, which stamps `style.css` **and** `readme.txt` together.

## [Unreleased]

## [12.18.5] - 2026-09-04 — guards that stop trusting hand-kept lists

### Fixed
- Block style variations are validated from the directory, not from a
  hand-written list of three. A fourth variation was checked by nothing: one
  carrying an invented font-size preset *and* a raw `letterSpacing` literal
  shipped with the whole suite green. Preset references are now RESOLVED against
  `theme.json` rather than only shape-checked, because a phantom slug paints
  nothing and errors nowhere. (#268)

