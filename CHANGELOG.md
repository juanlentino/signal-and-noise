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
- **The two live checks measure again (CI).** Smoke Test had failed every run since 2026-10-12 (about 400 in a row), the day the `note-markdown` surface joined agents.json: it probed the surface at its advertised url, `/notes/`, which is the HTML archive, while the surface is a convention (`/notes/<slug>.md`), like `content-json`. It now probes the newest note's own advertised `.md` twin (the feed names the note, its head names the twin, both fetches retried like every probe) through the normal check: 200 and a Markdown content type. A note whose head names no twin fails, with the response shown. Contrast (live) had died before measuring since 2026-10-18: the site's hash-only script-src blocked the measuring tool, injected as an inline script. It is now evaluated instead, which script-src does not govern, so the measured pages keep the CSP readers get.
- **Dependabot resolves composer again (dev tooling).** `roave/security-advisories` now conflicts with PHP 8.3 below 8.3.36, and Dependabot resolved against a bare 8.3 (`php is present at version 8.3`), so every composer update failed. `config.platform.php` is 8.3.36, the patched release of the line CI runs. When roave raises the 8.3 floor past it, bump the pin: Dependabot will not.

## [2026.10.30] - 2026-10-26 — Google sees the whole favicon

### Fixed
- **Google shows the whole JL mark.** Google's search-result favicon was two short bars, a pause sign: read from its favicon cache on 2026-10-25, it was the top half of our SVG (the stems at their exact positions and width, everything below the midline gone, so the J's hook and the L's foot with it). The .ico frames, the touch icon and the SVG itself all render whole. `favicon.svg` had a viewBox and no width or height, which leaves the canvas to the renderer; its root now states `width="192" height="192"`, an explicit square canvas. Chrome draws it identically at tab size. Google refetches favicons on its own schedule. Pinned in tests/home-screen-icon-opacity.php.

