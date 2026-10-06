# Changelog

All notable changes to the Signal & Noise theme are documented here.

This file holds two things only: **`## [Unreleased]`**, the working log that
accumulates across pull requests, and the **current release**. Everything older
lives in [docs/changelog/](docs/changelog/).

A pull request does not bump `Version` and does not tag — it closes an issue and
adds a bullet below. A release is a separate, deliberate act:
`tools/cut-release.sh`, which stamps `style.css` **and** `readme.txt` together.

## [Unreleased]

### Changed
- **humans.txt and the colophon now share one source.** humans.txt's stack lines are no longer copied by hand: they print the colophon's own facts from the companion plugin (`sn_colophon_plain_facts()`, plugin colophon rewrite of 2026-10-06), so the two cannot drift. The plugin-absent fallback drops the two claims the colophon corrected ("vanilla ES5" and the thin "SEO, search & ops" line). The /colophon search description now matches the page: how the site is built, who maintains it, and how each note is signed and timestamped. The README's Type line names where each face is used, and its IndieWeb line no longer claims a live `[sn_build]` line on the colophon (the page ends with the linked theme and plugin versions; the shortcode stays registered).

## [15.4.4] - 2026-10-05 — a plugin release's no public change holds only from its baseline forward

### Fixed
- **A plugin release's "no public change" holds only from its baseline forward.** 15.4.3 skipped the update purge whenever the companion plugin's release said `Front-End Change: no`. That header describes one step, and the updater installs the latest tag directly, so an update that jumped past a release that did change the public site, or a rollback, would have kept the old pages cached (Codex P1 on plugin #1924). The skip now needs a forward update from the version still loaded (`SNT_VERSION`) to a release whose `Front-End Baseline:` is at or below it. A jump, a rollback, an unknown prior version, or a missing baseline purges. No plugin release carried "no" yet, so 15.4.3 never skipped a purge it needed.

