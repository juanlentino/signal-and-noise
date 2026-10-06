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
- **A reduced-motion setting now stops every transition and animation.** The plugin's motion scan counted 44 declared transitions and animations with no reduced-motion counterpart across the theme and plugin stylesheets: mostly hover fades, a few real movement (the roadmap fold, the pillar CTA arrow, the /verify check states). `assets/css/base.css` now sets `animation` and `transition` to none on everything under `prefers-reduced-motion: reduce`. Nothing changes for anyone else.

### Fixed
- **The notes archive's month labels fell under AA in dark.** The label is rust, then faded again with opacity .75, which took it to 4.25:1 on the dark ground. Rust alone is the mute (7.4:1 dark, 5.7:1 light); the opacity is gone. Found by a sitewide computed contrast audit.

## [15.6.0] - 2026-10-06 — Now is a spec sheet, and links in gray text are underlined

### Changed
- **Now is a spec sheet, like Uses.** Same bands: each section's label and count on the left, its items two across on the right; one column of items under 640px, the label stacked above under 900px. CSS only (`assets/css/now.css`); the markup and words are unchanged. At 1440px the page drops from 1,213px to 995px tall.

### Fixed
- **Links in gray text read as plain text until hovered.** The theme marks links by color alone (red, no underline), which holds against the black body text but not against the gray (rust) text: 2.5:1 in light and 1.2:1 in dark, under the 3:1 a color-only link needs (WCAG 1.4.1). A sitewide audit of every page in both palettes found three places: the gray paragraph on /contact/personal, the paper sidenotes on /provenance (SSRN and DOI), and the LinkedIn link in the /resume rail. Links there are now underlined at rest (`assets/css/critical.css`), in gray paragraphs only, so a Note's frontmatter tag list stays bare. Buttons are untouched.

