# Changelog

All notable changes to the Signal & Noise theme are documented here.

This file holds two things only: **`## [Unreleased]`**, the working log that
accumulates across pull requests, and the **current release**. Everything older
lives in [docs/changelog/](docs/changelog/).

A pull request does not bump `Version` and does not tag — it closes an issue and
adds a bullet below. A release is a separate, deliberate act:
`tools/cut-release.sh`, which stamps `style.css` **and** `readme.txt` together.

## [Unreleased]

## [2026.10.30] - 2026-10-26 — Google sees the whole favicon

### Fixed
- **Google shows the whole JL mark.** Google's search-result favicon was two short bars, a pause sign: read from its favicon cache on 2026-10-25, it was the top half of our SVG (the stems at their exact positions and width, everything below the midline gone, so the J's hook and the L's foot with it). The .ico frames, the touch icon and the SVG itself all render whole. `favicon.svg` had a viewBox and no width or height, which leaves the canvas to the renderer; its root now states `width="192" height="192"`, an explicit square canvas. Chrome draws it identically at tab size. Google refetches favicons on its own schedule. Pinned in tests/home-screen-icon-opacity.php.

