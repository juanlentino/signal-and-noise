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
- **/workflow accessibility pass.** Proof links are underlined at rest, so they read as links without relying on color (WCAG 1.4.1). Long unbroken titles or URLs in list items and the h1 wrap instead of scrolling the page sideways at 320px (1.4.10). A row's bold lead is matched by its `__title` class rather than its position, so a map step with no title no longer turns its sentence into the lead.

## [15.4.1] - 2026-10-04 — a map step name on its own line

### Fixed
- **/workflow: a map step's name sits on its own bold line.** In "What else runs this way" each name ran into its sentence ("Voice rulebooks A written set of rules..."). The name now takes the same styling as a rule's lead: bold, on its own line above the sentence.

