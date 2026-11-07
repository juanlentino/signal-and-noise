# Changelog

All notable changes to the Signal & Noise theme are documented here.

This file holds two things only: **`## [Unreleased]`**, the working log that
accumulates across pull requests, and the **current release**. Everything older
lives in [docs/changelog/](docs/changelog/).

A pull request does not bump `Version` and does not tag — it closes an issue and
adds a bullet below. A release is a separate, deliberate act:
`tools/cut-release.sh`, which stamps `style.css` **and** `readme.txt` together.

## [Unreleased]

## [2026.11.6] - 2026-11-07 — The record sleeve for /services

### Added
- **A record sleeve for `/services`.** `.sn-sides` and `.sn-side` draw Side A and Side B: a photo, a big name, a line, a numbered track list and one link stretched over the whole side. The home page's pulse and playhead close the sleeve. `.sn-service-crop` keeps the Consulting photos at one shape. Three crops of the desk photo ship in `assets/images/services/`, so Consulting has images like the old service cards. No image is generated.

### Removed
- **The `.sn-door-link` styles.** The hub no longer uses them. `.sn-door` stays for the rows on `/services/consulting`, now two columns.

