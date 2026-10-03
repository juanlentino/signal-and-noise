# Changelog

All notable changes to the Signal & Noise theme are documented here.

This file holds two things only: **`## [Unreleased]`**, the working log that
accumulates across pull requests, and the **current release**. Everything older
lives in [docs/changelog/](docs/changelog/).

A pull request does not bump `Version` and does not tag — it closes an issue and
adds a bullet below. A release is a separate, deliberate act:
`tools/cut-release.sh`, which stamps `style.css` **and** `readme.txt` together.

## [Unreleased]

- **Each pillar essay names the next one.** A new `signal-noise/next-essay` block in `page-provenance.html`, after the signed content (so no ledger version): "Next essay →" and the next pillar's title, in pillar order from the `_sn_pillar` descriptors. The last pillar, the /provenance hub and every other page render nothing. Goal `next_essay`. Pinned in `tests/next-essay.php`; the block registry pins twelve blocks.
- **A note's "Start with the pillar →" goes to the first essay**, /provenance/over-detection/, not the /provenance hub (the frontmatter pillar link already did). Goal `note_pillar`.
- **"More on this" skips notes the page already offers.** A note linked in the text or listed under Cited by no longer repeats in the related list; the list still fills to its count. Done in `sn_related_notes_query()`, so every caller gets it; pinned in `tests/related-notes.php`.

- **The page-ending lines are removed** from /music and /provenance (and /resume, plugin 20.6.0), with their `.sn-page-next` style and the 1320px template exception. The header stays on screen while scrolling, so the lines repeated links the reader could already see (owner, 2026-10-02). The header order from 14.8.0 stays.

- **CI: the scheduled-workflow liveness check reads GitHub's run list three times and keeps the newest stamp**, as the plugin repo has since #1808. On 2026-10-02 it failed a PR saying the hourly smoke test was 690 hours stale while it had run 40 minutes earlier; a re-run passed. One stale API answer can no longer red the check; a truly stopped cron still does.

## [14.8.1] - 2026-10-02 — the page endings live in the templates


- **/music and /provenance end with a next step, in their templates.** 14.8.0 printed /music's line from the discography block, so it sat between the catalog and the Verified Credits section, mid-page. Both lines now sit in the page templates after the post content, not in the content: the content is signed into the provenance ledger, and a navigation line is not authored text, so it must not mint a new signed version. /music: "Beyond the catalog: the record · get in touch" in a 1320px band matching the content's bands (one documented exception in `tests/layout-width-system.php`). /provenance (new, owner-approved): "Beyond the research: the record · the notes" (/resume, /notes; goals `next_record`, `next_notes`) at the page's reading width. Pinned in `tests/discography-render.php`.


