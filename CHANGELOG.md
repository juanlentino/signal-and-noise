# Changelog

All notable changes to the Signal & Noise theme are documented here.

This file holds two things only: **`## [Unreleased]`**, the working log that
accumulates across pull requests, and the **current release**. Everything older
lives in [docs/changelog/](docs/changelog/).

A pull request does not bump `Version` and does not tag — it closes an issue and
adds a bullet below. A release is a separate, deliberate act:
`tools/cut-release.sh`, which stamps `style.css` **and** `readme.txt` together.

## [Unreleased]

## [13.2.1] - 2026-09-14 — the .json twin serves signed Pages

### Fixed
- **The `.json` twin now serves signed Pages (#348).** `inc/content-json-document.php` emitted the `provenance` block for Notes only, from before pages could opt into signing (plugin 13.69); a signed page's twin carried no `note_uid`, so `/verify` could not resolve a pasted page URL. The block now follows the uid: any subject the plugin has minted carries it (the key stays `note_uid`, the docket's query parameter; the sentence says "page" for a page). A uid-less page stays silent, never a fabricated claim. The finished document also passes through a new `sn_content_json_document` filter so the plugin can add `content_signed`, the prose as the signing normalization sees it: the integrity sweep and `/verify` compared rendered `content_text` (where `[sn_reading_time]` reads "5 min read") against the raw-signed body and called every signed page drifted the moment it was minted. Test: `tests/content-json-document.php` 24 → 28 (signed page carries the block and its docket URL, uid-less page does not, the filter receives the post and its return is the twin). Mutations red: filter removed, page branch removed.

