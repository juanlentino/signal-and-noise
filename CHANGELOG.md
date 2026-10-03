# Changelog

All notable changes to the Signal & Noise theme are documented here.

This file holds two things only: **`## [Unreleased]`**, the working log that
accumulates across pull requests, and the **current release**. Everything older
lives in [docs/changelog/](docs/changelog/).

A pull request does not bump `Version` and does not tag — it closes an issue and
adds a bullet below. A release is a separate, deliberate act:
`tools/cut-release.sh`, which stamps `style.css` **and** `readme.txt` together.

## [Unreleased]

## [15.3.0] - 2026-10-03 — a pageview ID on every beacon event

### Added
- **The beacon sends a pageview ID.** One random integer per page view rides every event as `pid`, so the collector (analytics worker 1.24.0 and later) can tie a view's scroll, time, vital and custom events to its pageview. It is held in memory only, redrawn on every pageview (a back-button restore included), never stored and never reused, so it cannot link two page views or two visits. The beacon still sets no cookie and uses no storage.

