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
- **Diagram labels in eleven notes fell under AA.** The inline diagrams mute secondary labels with opacity (.5 to .7), measured as low as 3.95:1 at 10-12px. The diagrams are part of the signed notes, so `assets/css/article.css` renders those labels in rust at full strength instead: still visibly secondary, AA in both palettes, the notes' content untouched.

## [15.6.1] - 2026-10-06 — the notes month label is muted by rust alone

### Fixed
- **A reduced-motion setting now stops every transition and animation.** The plugin's motion scan counted 44 declared transitions and animations with no reduced-motion counterpart across the theme and plugin stylesheets: mostly hover fades, a few real movement (the roadmap fold, the pillar CTA arrow, the /verify check states). `assets/css/base.css` now sets `animation` and `transition` to none on everything under `prefers-reduced-motion: reduce`. Nothing changes for anyone else.

### Fixed
- **The notes archive's month labels fell under AA in dark.** The label is rust, then faded again with opacity .75, which took it to 4.25:1 on the dark ground. Rust alone is the mute (7.4:1 dark, 5.7:1 light); the opacity is gone. Found by a sitewide computed contrast audit.

