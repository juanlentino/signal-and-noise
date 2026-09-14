# Changelog

All notable changes to the Signal & Noise theme are documented here.

This file holds two things only: **`## [Unreleased]`**, the working log that
accumulates across pull requests, and the **current release**. Everything older
lives in [docs/changelog/](docs/changelog/).

A pull request does not bump `Version` and does not tag — it closes an issue and
adds a bullet below. A release is a separate, deliberate act:
`tools/cut-release.sh`, which stamps `style.css` **and** `readme.txt` together.

## [Unreleased]

### Added
- **The editorial conventions as data: `inc/editorial-conventions.php` and `signal-and-noise/get-editorial-conventions`.** House conventions were discoverable only by reading a post that already used them; on 2026-09-13 a correction notice went live as a plain `<em>` paragraph because the convention turned out to be a core paragraph carrying `sn-correction`, registered nowhere and present in one note. `sn_theme_editorial_conventions()` is one array, fourteen entries, each carrying id, kind (pattern | block_style | class_convention | dynamic_block | idiom), block, selector, an exemplar to paste, when to use it, placement, `anchor_reachable` and `unused`. From the corpus walk (60 bodies): references, correction, svg-figure, sidenote, lead, steps-enumerated, compare-columns, h2-subheads in use; epigraph, pull-quote, hero-dossier, section-constrained, signal-quote, hairline registered but chosen by no note, kept and flagged. Two decisions encoded: the standfirst (three notes as a bare `<em>` paragraph) gets a class, **`sn-lead`**, styled in `article.css`; the SVG figure's house form is `role="img"` + `aria-labelledby` + `<title>` + `<desc>` + `currentColor` with the accent through the blood token, so figures follow both palettes (four shipped at `#222` and were invisible in dark). The ability returns the registry with a note that says to call it before composing. `tests/editorial-conventions.php` (55) is the anti-stale test: every selector styled by the theme CSS (comments stripped, whole token), every block style registered, every named pattern shipped, every exemplar carrying its selector, and every className the pattern files use present in the registry; mutations red: the lead's CSS renamed, a pattern growing a class. Abilities 15 → 16.

### Documentation
- README states the WordPress version the theme is tested on and runs on: 7.1 (`style.css` and `readme.txt` already declared `Tested up to: 7.1`; the prose only said 7.0+).

### Changed
- The nine furniture shortcodes stay registered indefinitely (owner decision 2026-09-12); the 13.1.0 note that 13.2.0 would retire them is withdrawn. Comment and spec only.

### Fixed
- `tools/cut-release.sh` no longer fails on an archive whose first line is already a release heading — BSD `head` rejects `-n 0` (#340). `docs/WORDPRESS-REFERENCE.md` stops naming a core constant that never existed (#341).

## [13.1.2] - 2026-09-12 — the bug sweep

### Fixed
- **Bug sweep: 28 confirmed defects across the theme, each with a pin (#310–#336).** Four read-only reviewers covered `inc/`, the block templates, `theme.json`, every stylesheet and script, and the release tooling; every finding was verified against a concrete input before it was filed, and every fix went in red → green.
  - **Routes and abilities:** `/notes/?feed=rss2` answered the HTML index under an RSS content-type — the render short-circuit had no `is_feed()` guard (#313); `get-active-template-structure` reported `page.html` for the eleven slug-matched page templates (#310); `get-llms-txt` omitted the Pillar essays section the route serves (#311); every pillar's `last_modified` was empty because a child page was looked up by its last path segment (#314); numbered list items were never counted (#315); `/index` listed password-protected posts (#331); titles carried HTML entities in llms.txt, the content JSON twin and the reply-by-email subject (#328, #335); `reading-path-slot.php` lacked the direct-access guard (#336).
  - **Updates and maintenance:** the auto purge after a WordPress update only fired for the bulk-upgrade shape, so a single-package update left the stale render it exists to clear (#312); the update check had a dead branch and fetched twice on "Check again" (#332); the download step now resolves the release redirect itself so the request header applies to one host only; `cut-release.sh` exited silently on an archive with no heading — after rewriting the version files (#327); `agents.json`'s `updated` stamp was two months stale (#329).
  - **Front end:** 140px of dead scroll under the footer on every block-template page at phone width (#316); the 404 search field zoomed WebKit (#317); printing a note dropped the whole closing part including the provenance record, and the dark palette survived into print (#318, #319); the closing rule lost its color to the separator override (#320); the touch link reset recolored every explicitly styled link (#321); the sticky TOC bar lagged the header shrink and the discography rail sat below the header (#322); the footnote popover closed before the pointer could reach it (#323); the 404 template's decorative "404" broke block validation (#324); choosing a second preview before the first started stopped the second (#325); a dead nested hover media block (#326); the theme-color meta and favicon followed the OS scheme rather than the toggle (#333); a generated heading could duplicate an author-set id (#330); JSON Feed `tags` now carries the notes' tags after the category, as RSS2 does (#334).

