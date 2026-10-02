# Changelog

All notable changes to the Signal & Noise theme are documented here.

This file holds two things only: **`## [Unreleased]`**, the working log that
accumulates across pull requests, and the **current release**. Everything older
lives in [docs/changelog/](docs/changelog/).

A pull request does not bump `Version` and does not tag — it closes an issue and
adds a bullet below. A release is a separate, deliberate act:
`tools/cut-release.sh`, which stamps `style.css` **and** `readme.txt` together.

## [Unreleased]

- **The header puts Resume and Music beside About**, so each of the site's three audiences (hiring, artists, research) finds its page early: Home, About, Resume, Music, Services, Notes, Contact. Still seven items; Provenance stays out of the header because /notes already opens on the pillar essays and Start Here (owner, 2026-10-02). Pinned in `tests/header-nav-order.php`.
- **/music ends with a next step:** "Beyond the catalog: the record · get in touch" (owner-approved copy), linking /resume and /contact. The page used to end on the last cover. A new `.sn-page-next` style (the catalog-meta voice, a hairline above, links in blood; hover thickening inside `@media (hover: hover)`) is shared with /resume's closing line in plugin 20.5.0. Each link is a beacon goal (`next_record`, `next_contact`), so `sn-metrics{analytics_events}` counts which one readers take. Pinned in `tests/discography-render.php` (red without the line).

## [14.7.0] - 2026-10-01 — the selection and the count


- **/music carries a bridge line under the discography summary:** "A selection. Since 2022 alone, roughly 110 tracks have passed through my hands." It reconciles the 10-release selection on /music with the ~110-track count on /resume. Static copy in `inc/discography-render.php`, below the sticky controls rail (inside it, it would ride the rail on scroll), in the existing `.sn-catalog-meta` class; no new CSS. Pinned by `tests/discography-render.php` (verbatim, placed after the rail, unchanged by the release count).


