# Changelog

All notable changes to the Signal & Noise theme are documented here.

This file holds two things only: **`## [Unreleased]`**, the working log that
accumulates across pull requests, and the **current release**. Everything older
lives in [docs/changelog/](docs/changelog/).

A pull request does not bump `Version` and does not tag — it closes an issue and
adds a bullet below. A release is a separate, deliberate act:
`tools/cut-release.sh`, which stamps `style.css` **and** `readme.txt` together.

## [Unreleased]

- **Nothing foreign inside the signed content.** A note's post-content element is what the public ledger's checker compares with the signed record. On 2026-10-02 MailPoet's slide-in form was switched on for posts; MailPoet appends its forms to `the_content`, so the form's markup landed inside that element and all 50 notes failed the ledger's served-page check for two days (`verify.yml` red on the schedule of 10-02 and 10-03), with every signature, hash and JSON twin intact. On a signed singular (every note; a page only when it carries a provenance UID) MailPoet's `the_content` callback is now taken off before the page renders and put back before the footer hooks run, which is the condition MailPoet's own footer path checks before it prints its overlay forms (slide-in, pop-up, fixed bar) there. They are position: fixed, so a reader sees no difference. A "below the post" form is not printed by that path and does not show on a signed page: nothing is appended to signed content. An ordinary page is left alone. `inc/signed-content-guard.php`; filter `sn_signed_content_appenders`; pinned in `tests/signed-content-guard.php`.

## [15.2.1] - 2026-10-03 — a broken render is never stored


- **A broken render is never stored.** Two renders can come out wrong and still answer 200. An empty one: on 2026-10-03, minutes after a full purge, `/provenance/` rendered 359 bytes and the edge served that for 21 minutes; the guard theme 13.3.1 built for this (no-store under 4 KB) had lived in an output buffer #400 removed, and since 15.1.0 the page also said it was cacheable. And one made in the gap of the companion plugin's own update, when the plugin is not loaded: some routes 404 and the navigation loses its styles. Now an HTML body under 4 KB leaves as `Cache-Control: no-store` (a pass-through buffer measures the page and changes nothing in it), and a page rendered while the plugin's `SNT_VERSION` is undefined is refused the same way (filter `sn_edge_cache_requires_companion`). Why the render comes out empty after a full purge is still not established; this makes it harmless, and the next request renders normally. Pinned in `tests/cache-headers.php`.


