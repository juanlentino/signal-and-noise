# Changelog

All notable changes to the Signal & Noise theme are documented here.

This file holds two things only: **`## [Unreleased]`**, the working log that
accumulates across pull requests, and the **current release**. Everything older
lives in [docs/changelog/](docs/changelog/).

A pull request does not bump `Version` and does not tag — it closes an issue and
adds a bullet below. A release is a separate, deliberate act:
`tools/cut-release.sh`, which stamps `style.css` **and** `readme.txt` together.

## [Unreleased]

## [14.8.1] - 2026-10-02 — the page endings live in the templates


- **/music and /provenance end with a next step, in their templates.** 14.8.0 printed /music's line from the discography block, so it sat between the catalog and the Verified Credits section, mid-page. Both lines now sit in the page templates after the post content, not in the content: the content is signed into the provenance ledger, and a navigation line is not authored text, so it must not mint a new signed version. /music: "Beyond the catalog: the record · get in touch" in a 1320px band matching the content's bands (one documented exception in `tests/layout-width-system.php`). /provenance (new, owner-approved): "Beyond the research: the record · the notes" (/resume, /notes; goals `next_record`, `next_notes`) at the page's reading width. Pinned in `tests/discography-render.php`.


