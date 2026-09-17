# Changelog

All notable changes to the Signal & Noise theme are documented here.

This file holds two things only: **`## [Unreleased]`**, the working log that
accumulates across pull requests, and the **current release**. Everything older
lives in [docs/changelog/](docs/changelog/).

A pull request does not bump `Version` and does not tag — it closes an issue and
adds a bullet below. A release is a separate, deliberate act:
`tools/cut-release.sh`, which stamps `style.css` **and** `readme.txt` together.

## [Unreleased]

## [13.2.7] - 2026-09-17 — updated means the prose moved

### Fixed
- **The "Updated" line follows the prose, not the save.** `post_modified` bumps on any save; on 2026-09-17 a title-tag override on every note made all 43 read "Updated 2026.09.17" over prose that had not moved. The byline now reads `_sn_prov_last_commit_gmt`, the moment the plugin's provenance chain last committed the normalized prose (the clock its stale-posts check has used since plugin 11.11.8), and falls back to `post_modified` only for a post with no commit. Pinned: a commit three days after publish with a save forty days after paints no line; a commit thirty days after paints the commit date; an empty commit falls back. Red on the old clock.

