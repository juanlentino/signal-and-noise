# Changelog

All notable changes to the Signal & Noise theme are documented here.

This file holds two things only: **`## [Unreleased]`**, the working log that
accumulates across pull requests, and the **current release**. Everything older
lives in [docs/changelog/](docs/changelog/).

A pull request does not bump `Version` and does not tag — it closes an issue and
adds a bullet below. A release is a separate, deliberate act:
`tools/cut-release.sh`, which stamps `style.css` **and** `readme.txt` together.

## [Unreleased]

## [2026.10.27] - 2026-10-22 — nav and footer links point straight at their pages


- **Header and footer links go straight to the page, not through a redirect.** The header nav (About, Resume, Music, Services, Notes, Contact), the footer meta-nav (Now, Accessibility, Stats, Privacy), the single note's "All Notes" link and the posts index's provenance link pointed at paths without the trailing slash, so each click and crawl took a 301 to the canonical `/about/`-style URL first. They now link the canonical URL directly, Spanish pages included. A new guard, `tests/chrome-trailing-slash.php`, fails on any slashless internal link in the parts, templates, block renders and Spanish chrome.


