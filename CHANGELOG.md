# Changelog

All notable changes to the Signal & Noise theme are documented here.

This file holds two things only: **`## [Unreleased]`**, the working log that
accumulates across pull requests, and the **current release**. Everything older
lives in [docs/changelog/](docs/changelog/).

A pull request does not bump `Version` and does not tag — it closes an issue and
adds a bullet below. A release is a separate, deliberate act:
`tools/cut-release.sh`, which stamps `style.css` **and** `readme.txt` together.

## [Unreleased]

### Fixed
- **The notes index has one page, and says so.** Browse mode returns the whole corpus in one response (v11.10.0), yet `/notes/?paged=2` served the full list again under its own canonical and a "Page 2" title, and `/notes/page/2/` served an empty 200: two phantom pages for crawlers. Both now 301 to `/notes/` (`sn_notes_paged_index_target()`); a search keeps its pages.

### Changed
- **Release cuts skip the security scan; nothing else does.** Same contract as the plugin repo (#1751): `.github/scripts/scan-scope.sh` skips only when every file is docs/Markdown, `style.css` whose only changed line is `Version:`, or `readme.txt` whose only changed line is `Stable tag:`. A code file or any other edit riding a cut is scanned; any doubt scans. The script runs from the base commit, so a PR cannot rewrite it to waive its own scan. Pinned by `tests/ci-scan-scope.php`.

## [14.4.2] - 2026-09-27 — the /notes title setting sets the title

### Fixed
- **The "/notes title" setting now sets the /notes `<title>`.** The theme built "Notes — Site" itself in `pre_get_document_title`, which short-circuits the plugin's title filter, so the Settings › Identity & SEO field only ever changed `og:title`. `sn_notes_index_title()` now uses the setting when it is filled, keeps the "— Page N" suffix, and falls back to the default when blank.

