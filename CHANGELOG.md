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
- **The header logo pointed at a file that no longer exists.** `parts/header.html` hardcodes the logo path (deliberately: no DB read on the LCP element), and the February PNGs it named were replaced on 2026-09-19 by WebP uploads under `uploads/2026/09/`; every page rendered a broken image. The path is now the 150 and 300 WebP. The trap stays: the file is named, not read; if the logo is ever replaced again, this line moves with it.

## [13.3.1] - 2026-09-18 — a body that is not a page is not cached

### Fixed
- **An empty body never leaves the origin cacheable.** Twice on 2026-09-17 and 18 Cloudflare cached a 358-byte 200 for `/provenance/` (the object-cache footnote and nothing else) and served it for about ninety minutes, until a purge; Bing's second scan read the page as missing its H1 and description because that is what it got. The page-wide output buffer's callback now returns the original page when `preg_replace()` fails (it returns NULL on a PCRE error, and a NULL from a buffer callback is an empty body), and any body under 4 KB leaves with `Cache-Control: no-store`, so whatever produces an empty render cannot be kept by the edge. The cause of the empty render itself is not established; this closes the caching of it. Pinned through a header seam.

