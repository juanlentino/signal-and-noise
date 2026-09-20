# Changelog

All notable changes to the Signal & Noise theme are documented here.

This file holds two things only: **`## [Unreleased]`**, the working log that
accumulates across pull requests, and the **current release**. Everything older
lives in [docs/changelog/](docs/changelog/).

A pull request does not bump `Version` and does not tag — it closes an issue and
adds a bullet below. A release is a separate, deliberate act:
`tools/cut-release.sh`, which stamps `style.css` **and** `readme.txt` together.

## [Unreleased]

### Fixed
- **`fair-use` is filed under "Why it isn't built".** The tag was minted on 2026-09-19 for the two notes on the Justice Department's fair-use brief (plugin 17.0.0's tag-fit pass read their rights tags as attached for reach); `/notes/tags` showed it under the fallback heading "Not yet filed", which is the page doing its job. It now sits beside Music Rights, Music Royalties and AI Training.

## [13.3.2] - 2026-09-19 — the file is named, not read

### Fixed
- **The header logo pointed at a file that no longer exists.** `parts/header.html` hardcodes the logo path (deliberately: no DB read on the LCP element), and the February PNGs it named were replaced on 2026-09-19 by WebP uploads under `uploads/2026/09/`; every page rendered a broken image. The path is now the 150 and 300 WebP. The trap stays: the file is named, not read; if the logo is ever replaced again, this line moves with it.

