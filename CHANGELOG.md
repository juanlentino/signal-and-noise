# Changelog

All notable changes to the Signal & Noise theme are documented here.

This file holds two things only: **`## [Unreleased]`**, the working log that
accumulates across pull requests, and the **current release**. Everything older
lives in [docs/changelog/](docs/changelog/).

A pull request does not bump `Version` and does not tag — it closes an issue and
adds a bullet below. A release is a separate, deliberate act:
`tools/cut-release.sh`, which stamps `style.css` **and** `readme.txt` together.

## [Unreleased]

### Changed
- **`docs/adr/adr-0001-third-party-agent-skills.md` no longer names private product repos.** The ADR's scope line, stack sentence and extraction note now say "private product repos"; the zero-tolerance rule stands as "private product repos are zero-tolerance: Anthropic first-party skills only", with the sentence about held trade secrets removed. Docs only; no version bump; history not rewritten.

## [13.6.1] - 2026-09-21 — the tab icon

### Fixed
- **The tab icon draws the mark: `favicon.ico` was a solid black square, and the icon URLs are versioned.** Chrome takes the `sizes="any"` `.ico` over the SVG, and the shipped `.ico` was one colour (`#000000`) at 48, 32 and 16, rasterised from the `var()` SVG that 13.6.0 fixed; fixing the SVG could not change what the tab showed. The `.ico` is re-rendered from the corrected tile with headless Chrome (three 32bpp frames, 39/36/26 distinct pixels). The three icon `<link>`s now carry `?ver=` from `sn_asset_ver()`: a browser's favicon cache is keyed on the URL and outlives every page purge, which is why the fixed SVG did not show either. `tests/home-screen-icon-opacity.php` (+5) reads the `.ico` frames and fails on a single-colour one (3 red on the shipped file) and pins the versioned links; `tests/head-sweep.php` pins `?ver=` on all three. Reported by the owner after installing 13.6.0.

### Changed
- **Footer signature: the stamp is 32px and the descriptor tracks `wide`.** At 28px the stamp sat beside a taller text block instead of anchoring it; at `ultra` (0.3em) the 11px descriptor came out 1.8x the wordmark's width, so the subordinate line led the lockup. `ultra` is right for the OG card at 16px across 1200px; in a 58px bar it over-tracks. `tests/footer-signature.php` re-pinned.

