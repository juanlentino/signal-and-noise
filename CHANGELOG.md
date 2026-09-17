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
- **The "Updated" line follows the prose, not the save.** `post_modified` bumps on any save; on 2026-09-17 a title-tag override on every note made all 43 read "Updated 2026.09.17" over prose that had not moved. The byline now reads `_sn_prov_last_commit_gmt`, the moment the plugin's provenance chain last committed the normalized prose (the clock its stale-posts check has used since plugin 11.11.8), and falls back to `post_modified` only for a post with no commit. Pinned: a commit three days after publish with a save forty days after paints no line; a commit thirty days after paints the commit date; an empty commit falls back. Red on the old clock.

## [13.2.6] - 2026-09-17 — the first paragraph names the subject

### Changed
- **`llms.txt` names the subject.** Its summary read "writer, music producer, and audio engineer. Long-form notes on craft, a discography, and professional background" and never said the word provenance; for a generative engine that line is the first, often the only, paragraph read about the site. It now says: music provenance research by Juan Lentino (with the ORCID), why cryptographic records of authorship made at creation, not AI detection after the fact, should anchor music rights; forty-plus notes, two SSRN papers, a verifier in the reader's browser; and the producer's discography and background. The Provenance hub is the first key page, ahead of Notes. Research register, no product. Pinned in `tests/llms-txt.php`.

