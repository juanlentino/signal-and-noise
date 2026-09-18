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
- **An empty body never leaves the origin cacheable.** Twice on 2026-09-17 and 18 Cloudflare cached a 358-byte 200 for `/provenance/` (the object-cache footnote and nothing else) and served it for about ninety minutes, until a purge; Bing's second scan read the page as missing its H1 and description because that is what it got. The page-wide output buffer's callback now returns the original page when `preg_replace()` fails (it returns NULL on a PCRE error, and a NULL from a buffer callback is an empty body), and any body under 4 KB leaves with `Cache-Control: no-store`, so whatever produces an empty render cannot be kept by the edge. The cause of the empty render itself is not established; this closes the caching of it. Pinned through a header seam.

## [13.3.0] - 2026-09-17 — the index knows both names

### Added
- **`llms-full.txt` leads each note with its search title and gains a Topics section.** A note's row now reads "Aphorism: plain words" (the plugin's `_sn_seo_title` override) when it has one, so an engine reading the index gets both names for every note. Before the notes, a Topics section lists every tag with three or more notes and its owner-written description, the same hub line the plugin's sitemap uses (15.10.0): the hubs first, then the corpus. Pinned; the basic `llms.txt` is unchanged.

