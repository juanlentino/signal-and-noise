# Changelog

All notable changes to the Signal & Noise theme are documented here.

This file holds two things only: **`## [Unreleased]`**, the working log that
accumulates across pull requests, and the **current release**. Everything older
lives in [docs/changelog/](docs/changelog/).

A pull request does not bump `Version` and does not tag — it closes an issue and
adds a bullet below. A release is a separate, deliberate act:
`tools/cut-release.sh`, which stamps `style.css` **and** `readme.txt` together.

## [Unreleased]

## [15.4.3] - 2026-10-05 — a plugin release with no public change leaves the caches warm

### Changed
- **A companion-plugin update that changes nothing public leaves the caches warm.** Owner, 2026-10-05: the edge was purged 50 times in a week, 42 by plugin updates, most of them admin-only releases. The update purge now reads the freshly installed plugin's `Front-End Change:` header (written by its release tool): when the update is the companion plugin alone and its release says `no`, nothing is purged. Any theme update, any other plugin, a batch, and a missing or unreadable header purge as before.

