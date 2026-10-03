# Changelog

All notable changes to the Signal & Noise theme are documented here.

This file holds two things only: **`## [Unreleased]`**, the working log that
accumulates across pull requests, and the **current release**. Everything older
lives in [docs/changelog/](docs/changelog/).

A pull request does not bump `Version` and does not tag — it closes an issue and
adds a bullet below. A release is a separate, deliberate act:
`tools/cut-release.sh`, which stamps `style.css` **and** `readme.txt` together.

## [Unreleased]

## [15.2.0] - 2026-10-03 — one tag on every cached response


- **Every cached response carries one cache tag.** Pages, the listed feeds and the machine files leave with `Cache-Tag: sn-render` beside the edge lifetime, so the plugin can refresh everything a save touches with one tag purge in place of a URL list that had to name every address. One tag, not one per post: every page embeds the site-wide notes index, so a save changes them all. Cloudflare strips the header before it reaches a visitor. Needs Breeze's "Cache Full Page HTML" off (it is, since 2026-10-03): a page served from Breeze's files left without the theme's headers and would carry no tag.


