# Changelog

All notable changes to the Signal & Noise theme are documented here.

This file holds two things only: **`## [Unreleased]`**, the working log that
accumulates across pull requests, and the **current release**. Everything older
lives in [docs/changelog/](docs/changelog/).

A pull request does not bump `Version` and does not tag — it closes an issue and
adds a bullet below. A release is a separate, deliberate act:
`tools/cut-release.sh`, which stamps `style.css` **and** `readme.txt` together.

## [Unreleased]

### Changed
- **A companion-plugin update that changes nothing public leaves the caches warm.** Owner, 2026-10-05: the edge was purged 50 times in a week, 42 by plugin updates, most of them admin-only releases. The update purge now reads the freshly installed plugin's `Front-End Change:` header (written by its release tool): when the update is the companion plugin alone and its release says `no`, nothing is purged. Any theme update, any other plugin, a batch, and a missing or unreadable header purge as before.

## [15.4.2] - 2026-10-04 — an accessibility pass on /workflow

### Fixed
- **/workflow accessibility pass.** Proof links are underlined at rest, so they read as links without relying on color (WCAG 1.4.1). Long unbroken titles or URLs in list items and the h1 wrap instead of scrolling the page sideways at 320px (1.4.10). A row's bold lead is matched by its `__title` class rather than its position, so a map step with no title no longer turns its sentence into the lead.

