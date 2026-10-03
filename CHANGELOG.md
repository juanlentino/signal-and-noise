# Changelog

All notable changes to the Signal & Noise theme are documented here.

This file holds two things only: **`## [Unreleased]`**, the working log that
accumulates across pull requests, and the **current release**. Everything older
lives in [docs/changelog/](docs/changelog/).

A pull request does not bump `Version` and does not tag — it closes an issue and
adds a bullet below. A release is a separate, deliberate act:
`tools/cut-release.sh`, which stamps `style.css` **and** `readme.txt` together.

## [Unreleased]

## [15.2.1] - 2026-10-03 — a broken render is never stored


- **A broken render is never stored.** Two renders can come out wrong and still answer 200. An empty one: on 2026-10-03, minutes after a full purge, `/provenance/` rendered 359 bytes and the edge served that for 21 minutes; the guard theme 13.3.1 built for this (no-store under 4 KB) had lived in an output buffer #400 removed, and since 15.1.0 the page also said it was cacheable. And one made in the gap of the companion plugin's own update, when the plugin is not loaded: some routes 404 and the navigation loses its styles. Now an HTML body under 4 KB leaves as `Cache-Control: no-store` (a pass-through buffer measures the page and changes nothing in it), and a page rendered while the plugin's `SNT_VERSION` is undefined is refused the same way (filter `sn_edge_cache_requires_companion`). Why the render comes out empty after a full purge is still not established; this makes it harmless, and the next request renders normally. Pinned in `tests/cache-headers.php`.


