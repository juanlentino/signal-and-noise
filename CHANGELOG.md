# Changelog

All notable changes to the Signal & Noise theme are documented here.

This file holds two things only: **`## [Unreleased]`**, the working log that
accumulates across pull requests, and the **current release**. Everything older
lives in [docs/changelog/](docs/changelog/).

A pull request does not bump `Version` and does not tag — it closes an issue and
adds a bullet below. A release is a separate, deliberate act:
`tools/cut-release.sh`, which stamps `style.css` **and** `readme.txt` together.

## [Unreleased]

## [12.20.5] - 2026-09-10 — the steps list learns to be unordered

### Added
- The steps list takes an unordered variant. The 01/02/03 numerals come from a CSS counter on the list class, not from the `<ol>` tag, so switching a steps list to `<ul>` fixed its semantics and left the numbers rendering — markup saying unordered, pixels saying ordered. `sn-steps__list--plain` stops the counter and marks items with an en-dash instead, for sets whose items are alternatives rather than steps. The bleed panel and label are unchanged.

