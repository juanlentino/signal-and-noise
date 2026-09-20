# Changelog

All notable changes to the Signal & Noise theme are documented here.

This file holds two things only: **`## [Unreleased]`**, the working log that
accumulates across pull requests, and the **current release**. Everything older
lives in [docs/changelog/](docs/changelog/).

A pull request does not bump `Version` and does not tag — it closes an issue and
adds a bullet below. A release is a separate, deliberate act:
`tools/cut-release.sh`, which stamps `style.css` **and** `readme.txt` together.

## [Unreleased]

### Added
- **A tag files itself: the Group field on Posts › Tags.** `/notes/tags` always read the vocabulary live; the one fact that was code was which of the four headings a tag sat under, so filing a new tag cost a theme release (13.3.3 was that release, for `fair-use`). The grouping now lives on the tag: `sn_tag_group` term meta (registered, in REST, sanitized to one of the four ids `record`, `settles`, `built`, `work`, or unfiled), a select beside the description on WordPress's own add and edit forms saved on `created_post_tag` and `edited_post_tag` (core checks the nonce and the capability first), and a Group column on the tags list. The page files by meta first, then by the theme's slug lists, which stay as the seed for tags nobody has filed by hand; a tag in neither still shows under "Not yet filed", loud by design. The groups' titles and deks stay in the theme. `tests/notes-tags-group-meta.php` (19), `tests/notes-tags-index.php` (20).

## [13.3.3] - 2026-09-20 — the tag is filed

### Fixed
- **`fair-use` is filed under "Why it isn't built".** The tag was minted on 2026-09-19 for the two notes on the Justice Department's fair-use brief (plugin 17.0.0's tag-fit pass read their rights tags as attached for reach); `/notes/tags` showed it under the fallback heading "Not yet filed", which is the page doing its job. It now sits beside Music Rights, Music Royalties and AI Training.

