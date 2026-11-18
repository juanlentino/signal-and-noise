# Changelog

All notable changes to the Signal & Noise theme are documented here.

This file holds two things only: **`## [Unreleased]`**, the working log that
accumulates across pull requests, and the **current release**. Everything older
lives in [docs/changelog/](docs/changelog/).

A pull request does not bump `Version` and does not tag — it closes an issue and
adds a bullet below. A release is a separate, deliberate act:
`tools/cut-release.sh`, which stamps `style.css` **and** `readme.txt` together.

## [Unreleased]

## [2026.11.14] - 2026-11-18 — security.txt says there is no bug bounty

### Changed
- **security.txt says there is no bug bounty.** Three comment lines at the top of `/.well-known/security.txt`: no bug bounty program and no payment for reports; a report with the affected URL, steps to reproduce and impact is welcome through the contact form. Comments, so the RFC 9116 fields are unchanged. Test: `tests/security-txt.php` (+1).

