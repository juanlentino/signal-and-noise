# Changelog

All notable changes to the Signal & Noise theme are documented here.

This file holds two things only: **`## [Unreleased]`**, the working log that
accumulates across pull requests, and the **current release**. Everything older
lives in [docs/changelog/](docs/changelog/).

A pull request does not bump `Version` and does not tag — it closes an issue and
adds a bullet below. A release is a separate, deliberate act:
`tools/cut-release.sh`, which stamps `style.css` **and** `readme.txt` together.

## [Unreleased]

- **Docs: the site-wide design research is in the repo.** `docs/research/2026-10-16-site-wide-design-ideas.md` ranks nine ideas in the transmission register, with a status block: four shipped in 2026.10.11, one declined, and four open (hairline-true display type, noise floor, crop-mark focus, signal lock links). It also records the owner's narrower home-page rule (no new content; site-wide design may reach home). No code change.

## [2026.10.12] - 2026-10-17 — keyboard-reachable comparison tables and a dark pulse that reads


- **A wide comparison table can be scrolled from the keyboard everywhere.** On a phone the table scrolls sideways inside its figure, and Safari does not make a scroll box focusable on its own, so a keyboard reader there could not reach the hidden columns (WCAG 2.1.1). `inc/table-header-scope.php` now gives the figure of an `is-style-sn-table` table `tabindex="0"`, `role="region"` and a name (its caption, or "Comparison table"), and `article.css` gives it the house 2px blood focus ring. A figure that already sets tabindex is left alone. `tests/table-header-scope.php` pins it, including a caption with a quote, which the HTML API escapes. `docs/ACCESSIBILITY.md` gains a section on 2026.10.11's reading aids, motion and decoration, and `readme.txt` lists the new features.
- **The home pulse reads as clearly in dark mode as in light.** Its hairlines were drawn in dark mode's concrete (#383838), 1.69:1 on the dark background, against 2.68:1 in light (#9e9e9e on white), so they all but vanished. A new `--sn-pulse` token, set only in the two dark blocks of `critical.css`, draws them at #565656 (2.70:1). The concrete token is unchanged everywhere else, and light mode still falls back to concrete. `tests/hero-pulse.php` pins both dark blocks and that light mode sets no pulse token.


