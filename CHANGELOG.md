# Changelog

All notable changes to the Signal & Noise theme are documented here.

This file holds two things only: **`## [Unreleased]`**, the working log that
accumulates across pull requests, and the **current release**. Everything older
lives in [docs/changelog/](docs/changelog/).

A pull request does not bump `Version` and does not tag — it closes an issue and
adds a bullet below. A release is a separate, deliberate act:
`tools/cut-release.sh`, which stamps `style.css` **and** `readme.txt` together.

## [Unreleased]

## [2026.10.10] - 2026-10-16 — emoji is back for readers

### Fixed
- **Emoji is back for readers.** 2026.10.9 removed WordPress's emoji loader from pages and oEmbed frames for CSP prep. The owner had already decided on 2026-09-29 that emoji stays on the public site, feeds and email, off in wp-admin only. The loader is restored, and a test now fails if the theme ever unhooks it. A strict CSP allows the loader's inline module by hash instead.

