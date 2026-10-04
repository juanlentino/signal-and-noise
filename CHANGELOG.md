# Changelog

All notable changes to the Signal & Noise theme are documented here.

This file holds two things only: **`## [Unreleased]`**, the working log that
accumulates across pull requests, and the **current release**. Everything older
lives in [docs/changelog/](docs/changelog/).

A pull request does not bump `Version` and does not tag — it closes an issue and
adds a bullet below. A release is a separate, deliberate act:
`tools/cut-release.sh`, which stamps `style.css` **and** `readme.txt` together.

## [Unreleased]

## [15.4.0] - 2026-10-04 — a frame for the workflow page

### Added
- **A frame for the new /workflow page.** The companion plugin writes /workflow as a real Page with the `page-workflow` template; the theme now ships that template (the same bare frame as /uses), registers it in `theme.json` `customTemplates`, and loads a new `assets/css/workflow.css` on that Page only. The page sits on the 760px reading track by its token (`--wp--style--global--content-size`), centered with the shared page padding, with the dek at 46ch and prose at 72ch, because it is one column of prose and a code sample with no grid to earn the wide frame. The sample `<pre>` scrolls inside itself so the page never scrolls sideways, shows a focus ring for keyboard users, keeps a system-color border under forced colors, and wraps on paper instead of clipping. Pinned by `tests/cms-page-styles.php` (template, registration, enqueue only on /workflow, the scroll, focus, forced-colors and print rules) and `tests/layout-width-system.php` (the page is on the reading track and centered).

