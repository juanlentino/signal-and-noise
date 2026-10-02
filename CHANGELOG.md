# Changelog

All notable changes to the Signal & Noise theme are documented here.

This file holds two things only: **`## [Unreleased]`**, the working log that
accumulates across pull requests, and the **current release**. Everything older
lives in [docs/changelog/](docs/changelog/).

A pull request does not bump `Version` and does not tag — it closes an issue and
adds a bullet below. A release is a separate, deliberate act:
`tools/cut-release.sh`, which stamps `style.css` **and** `readme.txt` together.

## [Unreleased]

- **/music's closing line moves to the end of the page.** 14.8.0 printed it from the discography block, so it sat between the catalog and the Verified Credits section, mid-page. /music is a CMS page; its ending now lives in the page content's last band (same approved words, same beacon goals), and the discography no longer prints it. The `.sn-page-next` style stays. Pinned in `tests/discography-render.php`.

## [14.8.0] - 2026-10-02 — every page hands you to the next


- **The header puts Resume and Music beside About**, so each of the site's three audiences (hiring, artists, research) finds its page early: Home, About, Resume, Music, Services, Notes, Contact. Still seven items; Provenance stays out of the header because /notes already opens on the pillar essays and Start Here (owner, 2026-10-02). Pinned in `tests/header-nav-order.php`.
- **/music ends with a next step:** "Beyond the catalog: the record · get in touch" (owner-approved copy), linking /resume and /contact. The page used to end on the last cover. A new `.sn-page-next` style (the catalog-meta voice, a hairline above, links in blood; hover thickening inside `@media (hover: hover)`) is shared with /resume's closing line in plugin 20.5.0. Each link is a beacon goal (`next_record`, `next_contact`), so `sn-metrics{analytics_events}` counts which one readers take. Pinned in `tests/discography-render.php` (red without the line).


