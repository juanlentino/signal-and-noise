# Changelog

All notable changes to the Signal & Noise theme are documented here.

This file holds two things only: **`## [Unreleased]`**, the working log that
accumulates across pull requests, and the **current release**. Everything older
lives in [docs/changelog/](docs/changelog/).

A pull request does not bump `Version` and does not tag — it closes an issue and
adds a bullet below. A release is a separate, deliberate act:
`tools/cut-release.sh`, which stamps `style.css` **and** `readme.txt` together.

## [Unreleased]

## [2026.10.8] - 2026-10-16 — owner devices send their views, and the cookie line says readers

### Changed
- **Owner-device views are sent, so the analytics Worker can lane them (traffic lanes, Phase 1).** The beacon no longer skips a page view when the unsigned `sn_owner=1` cookie is present and no longer reads any cookie; the Worker files those views in an owner lane instead of losing them. The DNT/GPC skip and the Claude desktop pane (`Claude/<n>` user agent) skip stay. The beacon still sets no cookie and no storage. `/llms.txt` now says the site sets no cookies on readers and the owner's own enrolled devices carry an owner-only marker; the `gpc.json` comment matches. Deploy after the Workers and device enrollment.

