# Changelog

All notable changes to the Signal & Noise theme are documented here.

This file holds two things only: **`## [Unreleased]`**, the working log that
accumulates across pull requests, and the **current release**. Everything older
lives in [docs/changelog/](docs/changelog/).

A pull request does not bump `Version` and does not tag — it closes an issue and
adds a bullet below. A release is a separate, deliberate act:
`tools/cut-release.sh`, which stamps `style.css` **and** `readme.txt` together.

## [Unreleased]

## [15.2.2] - 2026-10-03 — nothing foreign inside the signed content


- **Nothing foreign inside the signed content.** A note's post-content element is what the public ledger's checker compares with the signed record. On 2026-10-02 MailPoet's slide-in form was switched on for posts; MailPoet appends its forms to `the_content`, so the form's markup landed inside that element and all 50 notes failed the ledger's served-page check for two days (`verify.yml` red on the schedule of 10-02 and 10-03), with every signature, hash and JSON twin intact. On a signed singular (every note; a page only when it carries a provenance UID) MailPoet's `the_content` callback is now taken off before the page renders and put back before the footer hooks run, which is the condition MailPoet's own footer path checks before it prints its overlay forms (slide-in, pop-up, fixed bar) there. They are position: fixed, so a reader sees no difference. A "below the post" form is not printed by that path and does not show on a signed page: nothing is appended to signed content. An ordinary page is left alone. `inc/signed-content-guard.php`; filter `sn_signed_content_appenders`; pinned in `tests/signed-content-guard.php`.


