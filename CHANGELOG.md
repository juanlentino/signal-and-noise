# Changelog

All notable changes to the Signal & Noise theme are documented here.

This file holds two things only: **`## [Unreleased]`**, the working log that
accumulates across pull requests, and the **current release**. Everything older
lives in [docs/changelog/](docs/changelog/).

A pull request does not bump `Version` and does not tag — it closes an issue and
adds a bullet below. A release is a separate, deliberate act:
`tools/cut-release.sh`, which stamps `style.css` **and** `readme.txt` together.

## [Unreleased]

## [2026.10.2] - 2026-10-13 — every note carries its own cache label

### Added
- **Each note, and every page that lists notes, carries a cache tag of its own.** Every cached response still carries `sn-render`. A single note now also carries `sn-note-<ID>`, and the pages, feeds and machine files listed here carry `sn-lists`: home, /notes/ and its pages, /notes/tags/, /index/, /provenance/, the tag and category archives, every feed (JSON included), llms.txt and llms-full.txt. With these, the companion plugin can refresh a note whose save changed nothing other pages list by purging those two tags in one call. That clears every copy of the note's page whatever its query string (share and feed links with `?utm_source=` are separate copies at the edge), in every tier, without emptying the whole site. `sn_edge_cache_note_tag()` and `SN_EDGE_CACHE_LIST_TAG` are the names the plugin reads, and without them it keeps the whole-site purge. No page output changes; only the `Cache-Tag` header, which Cloudflare strips before the visitor. Pinned by tests/cache-headers.php.

