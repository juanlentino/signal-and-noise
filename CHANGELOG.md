# Changelog

All notable changes to the Signal & Noise theme are documented here.

This file holds two things only: **`## [Unreleased]`**, the working log that
accumulates across pull requests, and the **current release**. Everything older
lives in [docs/changelog/](docs/changelog/).

A pull request does not bump `Version` and does not tag — it closes an issue and
adds a bullet below. A release is a separate, deliberate act:
`tools/cut-release.sh`, which stamps `style.css` **and** `readme.txt` together.

## [Unreleased]

## [13.4.0] - 2026-09-20 — a tag files itself

### Added
- **A tag files itself: the Group field on Posts › Tags.** `/notes/tags` always read the vocabulary live; the one fact that was code was which of the four headings a tag sat under, so filing a new tag cost a theme release (13.3.3 was that release, for `fair-use`). The grouping now lives on the tag: `sn_tag_group` term meta (registered, in REST, sanitized to one of the four ids `record`, `settles`, `built`, `work`, or unfiled), a select beside the description on WordPress's own add and edit forms saved on `created_post_tag` and `edited_post_tag` behind the handler's own `edit_term` check and the tag form's nonce (the create hook fires from `wp_insert_term()` on every path that mints a tag, a Contributor's REST or `tax_input` create included, so relying on the form's gate would have let a Contributor file a tag under a heading; found in review), and a Group column on the tags list. The page files by meta first, then by the theme's slug lists, which stay as the seed for tags nobody has filed by hand; a tag in neither still shows under "Not yet filed", loud by design. The groups' titles and deks stay in the theme. `tests/notes-tags-group-meta.php` (19), `tests/notes-tags-index.php` (20).

