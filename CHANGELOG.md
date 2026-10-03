# Changelog

All notable changes to the Signal & Noise theme are documented here.

This file holds two things only: **`## [Unreleased]`**, the working log that
accumulates across pull requests, and the **current release**. Everything older
lives in [docs/changelog/](docs/changelog/).

A pull request does not bump `Version` and does not tag — it closes an issue and
adds a bullet below. A release is a separate, deliberate act:
`tools/cut-release.sh`, which stamps `style.css` **and** `readme.txt` together.

## [Unreleased]

- **An update clears the page caches, not all of Redis.** The update purge used to empty the whole object cache twice (its own `wp_cache_flush()`, then Breeze's `breeze_clear_all_cache`, which ends in one), taking Core's update check and every stored reading with it; the owner was purging by hand after most installs (2026-10-03). Now `sn_purge_all_caches()` calls Breeze's page-file and minify clears directly (`breeze_cache_flush( false, false, true )`, no object-cache work), the update path passes `object_cache => false`, and transients clear as a group (`wp_cache_flush_group( 'transient' )`: every plugin's transients, never `update_core`). Its database `sn_*` delete found nothing here, since Object Cache Pro keeps them in Redis. The manual Purge All still empties everything. New args: `package_caches`, and `trigger` (manual, update, styles) for the plugin's purge log.
- **Any plugin or theme update purges the page caches once**, not only ours: the plugin removes Breeze's own update purge (owner's call), and this replaces it.

## [14.9.0] - 2026-10-03 — the essays lead on


- **Each pillar essay names the next one.** A new `signal-noise/next-essay` block in `page-provenance.html`, after the signed content (so no ledger version): "Next essay →" and the next pillar's title, in pillar order from the `_sn_pillar` descriptors. The last pillar, the /provenance hub and every other page render nothing. Goal `next_essay`. Pinned in `tests/next-essay.php`; the block registry pins twelve blocks.
- **A note's "Start with the pillar →" goes to the first essay**, /provenance/over-detection/, not the /provenance hub (the frontmatter pillar link already did). Goal `note_pillar`.
- **"More on this" skips notes the page already offers.** A note linked in the text or listed under Cited by no longer repeats in the related list; the list still fills to its count. Done in `sn_related_notes_query()`, so every caller gets it; pinned in `tests/related-notes.php`.

- **The page-ending lines are removed** from /music and /provenance (and /resume, plugin 20.6.0), with their `.sn-page-next` style and the 1320px template exception. The header stays on screen while scrolling, so the lines repeated links the reader could already see (owner, 2026-10-02). The header order from 14.8.0 stays.

- **CI: the scheduled-workflow liveness check reads GitHub's run list three times and keeps the newest stamp**, as the plugin repo has since #1808. On 2026-10-02 it failed a PR saying the hourly smoke test was 690 hours stale while it had run 40 minutes earlier; a re-run passed. One stale API answer can no longer red the check; a truly stopped cron still does.


