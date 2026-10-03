# Changelog

All notable changes to the Signal & Noise theme are documented here.

This file holds two things only: **`## [Unreleased]`**, the working log that
accumulates across pull requests, and the **current release**. Everything older
lives in [docs/changelog/](docs/changelog/).

A pull request does not bump `Version` and does not tag — it closes an issue and
adds a bullet below. A release is a separate, deliberate act:
`tools/cut-release.sh`, which stamps `style.css` **and** `readme.txt` together.

## [Unreleased]

## [15.0.1] - 2026-10-03 — the cron lock survives a purge


- **An update inside WP-Cron keeps Core's cron lock.** 15.0.0's transient-group flush (and the manual purge's full flush) deleted `doing_cron`, the lock that stops two cron runs overlapping; automatic updates and the plugin's rollover run inside cron. The purge saves the lock and puts it back right after each flush. Codex on #470; pinned in `tests/template-maintenance.php`.
- **Cited by is asked once per note render.** Related notes (14.9.0) ran the same leading-wildcard `LIKE` scan the Cited by footer runs, on every note request; `sn_cited_by_query()` now answers once per request per post and limit. Codex on #468.
- **The next essay matches the pillar by its full path** (`get_page_uri()` against the descriptor's slug), so a page elsewhere sharing a pillar's last segment shows nothing. Codex on #468.


