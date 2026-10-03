# Changelog

All notable changes to the Signal & Noise theme are documented here.

This file holds two things only: **`## [Unreleased]`**, the working log that
accumulates across pull requests, and the **current release**. Everything older
lives in [docs/changelog/](docs/changelog/).

A pull request does not bump `Version` and does not tag — it closes an issue and
adds a bullet below. A release is a separate, deliberate act:
`tools/cut-release.sh`, which stamps `style.css` **and** `readme.txt` together.

## [Unreleased]

- **An update inside WP-Cron keeps Core's cron lock.** 15.0.0's transient-group flush (and the manual purge's full flush) deleted `doing_cron`, the lock that stops two cron runs overlapping; automatic updates and the plugin's rollover run inside cron. The purge saves the lock and puts it back. Codex on #470; pinned in `tests/template-maintenance.php`.
- **Cited by is asked once per note render.** Related notes (14.9.0) ran the same leading-wildcard `LIKE` scan the Cited by footer runs, on every note request; `sn_cited_by_query()` now answers once per request per post and limit. Codex on #468.
- **The next essay matches the pillar by its full path** (`get_page_uri()` against the descriptor's slug), so a page elsewhere sharing a pillar's last segment shows nothing. Codex on #468.

## [15.0.0] - 2026-10-03 — an update purges the pages, not Redis


- **An update clears the page caches, not all of Redis.** The update purge used to empty the whole object cache twice (its own `wp_cache_flush()`, then Breeze's `breeze_clear_all_cache`, which ends in one), taking Core's update check and every stored reading with it; the owner was purging by hand after most installs (2026-10-03). Now `sn_purge_all_caches()` calls Breeze's page-file and minify clears directly (`breeze_cache_flush( false, false, true )`, no object-cache work), the update path passes `object_cache => false`, and transients clear as a group (`wp_cache_flush_group( 'transient' )`: every plugin's transients, never `update_core`). Its database `sn_*` delete found nothing here, since Object Cache Pro keeps them in Redis. The manual Purge All still empties everything. New args: `package_caches`, and `trigger` (manual, update, styles) for the plugin's purge log.
- **Any plugin or theme update purges the page caches once**, not only ours: the plugin removes Breeze's own update purge (owner's call), and this replaces it.


