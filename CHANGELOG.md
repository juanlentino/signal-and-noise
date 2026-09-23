# Changelog

All notable changes to the Signal & Noise theme are documented here.

This file holds two things only: **`## [Unreleased]`**, the working log that
accumulates across pull requests, and the **current release**. Everything older
lives in [docs/changelog/](docs/changelog/).

A pull request does not bump `Version` and does not tag — it closes an issue and
adds a bullet below. A release is a separate, deliberate act:
`tools/cut-release.sh`, which stamps `style.css` **and** `readme.txt` together.

## [Unreleased]

## [14.2.1] - 2026-09-23 — the readme, licensed

### Fixed
- **readme.txt meets the Theme Handbook's metadata rules.** `Contributors:` is now the owner's wordpress.org username (`juanml`), not a display name, and a `== Copyright ==` section declares the two bundled fonts: Bebas Neue (Copyright 2019 The Bebas Neue Project Authors) and DM Mono (Copyright 2020 The DM Mono Project Authors), both SIL OFL 1.1, with sources and the files they cover. The copyright lines are read from each font's own `name` table.

### Docs
- **`docs/RESUME-PDF.md` describes the resume PDF as shipped** (plugin 17.7.0 through 17.7.2): the Website field, the location fallback to the web contact line, the phone checkbox off by default with a private copy that is never stored, why that control must be a checkbox and not an `os-switch`, and times shown in the site timezone.

