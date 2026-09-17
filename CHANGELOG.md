# Changelog

All notable changes to the Signal & Noise theme are documented here.

This file holds two things only: **`## [Unreleased]`**, the working log that
accumulates across pull requests, and the **current release**. Everything older
lives in [docs/changelog/](docs/changelog/).

A pull request does not bump `Version` and does not tag — it closes an issue and
adds a bullet below. A release is a separate, deliberate act:
`tools/cut-release.sh`, which stamps `style.css` **and** `readme.txt` together.

## [Unreleased]

## [13.3.0] - 2026-09-17 — the index knows both names

### Added
- **`llms-full.txt` leads each note with its search title and gains a Topics section.** A note's row now reads "Aphorism: plain words" (the plugin's `_sn_seo_title` override) when it has one, so an engine reading the index gets both names for every note. Before the notes, a Topics section lists every tag with three or more notes and its owner-written description, the same hub line the plugin's sitemap uses (15.10.0): the hubs first, then the corpus. Pinned; the basic `llms.txt` is unchanged.

