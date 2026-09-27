# Changelog

All notable changes to the Signal & Noise theme are documented here.

This file holds two things only: **`## [Unreleased]`**, the working log that
accumulates across pull requests, and the **current release**. Everything older
lives in [docs/changelog/](docs/changelog/).

A pull request does not bump `Version` and does not tag — it closes an issue and
adds a bullet below. A release is a separate, deliberate act:
`tools/cut-release.sh`, which stamps `style.css` **and** `readme.txt` together.

## [Unreleased]

## [14.4.1] - 2026-09-27 — one retired-tag map; provenance lands on the hub

### Fixed
- **One retired-tag map, in the plugin.** The theme's `sn_notes_retired_tags()` ran at priority 0 and overrode the plugin's map (plugin 19.1.1 sent `/tag/provenance/` to the hub; this theme kept sending it to `/notes/`). The theme's three entries were already in the plugin's `inc/tag-retired-map.php`, so the theme copy and its handler branch are gone; the plugin now answers every retired tag.
- **`/tag/provenance/` lands on the hub.** The retired `provenance` tag now 301s to `/provenance/` instead of `/notes/`. The hub is not one of the tag's three successors; it covers all of them, so a provenance reader lands on the track, not the whole index. Matches plugin 19.1.1, which does the same for `music-provenance`, `cryptographic-provenance` and `falsifiability`.

