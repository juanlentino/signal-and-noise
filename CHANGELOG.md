# Changelog

All notable changes to the Signal & Noise theme are documented here.

This file holds two things only: **`## [Unreleased]`**, the working log that
accumulates across pull requests, and the **current release**. Everything older
lives in [docs/changelog/](docs/changelog/).

A pull request does not bump `Version` and does not tag — it closes an issue and
adds a bullet below. A release is a separate, deliberate act:
`tools/cut-release.sh`, which stamps `style.css` **and** `readme.txt` together.

## [Unreleased]

## [15.4.1] - 2026-10-04 — a map step name on its own line

### Fixed
- **/workflow: a map step's name sits on its own bold line.** In "What else runs this way" each name ran into its sentence ("Voice rulebooks A written set of rules..."). The name now takes the same styling as a rule's lead: bold, on its own line above the sentence.

