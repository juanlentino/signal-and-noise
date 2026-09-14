# Changelog

All notable changes to the Signal & Noise theme are documented here.

This file holds two things only: **`## [Unreleased]`**, the working log that
accumulates across pull requests, and the **current release**. Everything older
lives in [docs/changelog/](docs/changelog/).

A pull request does not bump `Version` and does not tag — it closes an issue and
adds a bullet below. A release is a separate, deliberate act:
`tools/cut-release.sh`, which stamps `style.css` **and** `readme.txt` together.

## [Unreleased]

## [13.2.0] - 2026-09-14 — the editorial conventions as data

### Added
- **The editorial conventions as data: `inc/editorial-conventions.php` and `signal-and-noise/get-editorial-conventions`.** House conventions were discoverable only by reading a post that already used them; on 2026-09-13 a correction notice went live as a plain `<em>` paragraph because the convention turned out to be a core paragraph carrying `sn-correction`, registered nowhere and present in one note. `sn_theme_editorial_conventions()` is one array, fourteen entries, each carrying id, kind (pattern | block_style | class_convention | dynamic_block | idiom), block, selector, an exemplar to paste, when to use it, placement, `anchor_reachable` and `unused`. From the corpus walk (60 bodies): references, correction, svg-figure, sidenote, lead, steps-enumerated, compare-columns, h2-subheads in use; epigraph, pull-quote, hero-dossier, section-constrained, signal-quote, hairline registered but chosen by no note, kept and flagged. Two decisions encoded: the standfirst (three notes as a bare `<em>` paragraph) gets a class, **`sn-lead`**, styled in `article.css`; the SVG figure's house form is `role="img"` + `aria-labelledby` + `<title>` + `<desc>` + `currentColor` with the accent through the blood token, so figures follow both palettes (four shipped at `#222` and were invisible in dark). The ability returns the registry with a note that says to call it before composing. `tests/editorial-conventions.php` (55) is the anti-stale test: every selector styled by the theme CSS (comments stripped, whole token), every block style registered, every named pattern shipped, every exemplar carrying its selector, and every className the pattern files use present in the registry; mutations red: the lead's CSS renamed, a pattern growing a class. Abilities 15 → 16.

### Documentation
- README states the WordPress version the theme is tested on and runs on: 7.1 (`style.css` and `readme.txt` already declared `Tested up to: 7.1`; the prose only said 7.0+).

### Changed
- The nine furniture shortcodes stay registered indefinitely (owner decision 2026-09-12); the 13.1.0 note that 13.2.0 would retire them is withdrawn. Comment and spec only.

### Fixed
- `tools/cut-release.sh` no longer fails on an archive whose first line is already a release heading — BSD `head` rejects `-n 0` (#340). `docs/WORDPRESS-REFERENCE.md` stops naming a core constant that never existed (#341).

