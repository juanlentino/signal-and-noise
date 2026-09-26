# Changelog

All notable changes to the Signal & Noise theme are documented here.

This file holds two things only: **`## [Unreleased]`**, the working log that
accumulates across pull requests, and the **current release**. Everything older
lives in [docs/changelog/](docs/changelog/).

A pull request does not bump `Version` and does not tag — it closes an issue and
adds a bullet below. A release is a separate, deliberate act:
`tools/cut-release.sh`, which stamps `style.css` **and** `readme.txt` together.

## [Unreleased]

## [14.4.0] - 2026-09-26 — feed and share click-throughs show up as visits

### Added
- **Shared notes are counted, and their links say where they came from.** The share row's Copy link hands out the permalink tagged `utm_source=share&utm_medium=copy`, the phone's share sheet `utm_medium=native`, so a link pasted into a chat app (which sends no referrer) lands as a campaign visit instead of "direct". Each tap also fires a named goal (`share_copy` / `share_native`, `data-sn-goal`), so a share counts even if nobody clicks it; the plugin's north star sums them as "Notes shared".
- **JSON Feed click-throughs show up as visits.** Each item's `url` carries `utm_source=jsonfeed&utm_medium=feed`, so a reader who clicks through from a feed reader lands as a campaign visit the edge worker records. The `id` stays the bare permalink, so readers keep their read state. The plugin tags RSS and Atom the same way (`utm_source=rss`), and its north star counts every `utm_medium=feed` visit.

