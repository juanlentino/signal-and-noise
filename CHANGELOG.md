# Changelog

All notable changes to the Signal & Noise theme are documented here.

This file holds two things only: **`## [Unreleased]`**, the working log that
accumulates across pull requests, and the **current release**. Everything older
lives in [docs/changelog/](docs/changelog/).

A pull request does not bump `Version` and does not tag — it closes an issue and
adds a bullet below. A release is a separate, deliberate act:
`tools/cut-release.sh`, which stamps `style.css` **and** `readme.txt` together.

## [Unreleased]

## [2026.11.8] - 2026-11-10 — Machine files on their own rewrite rules, with their cache lifetime

### Removed
- **The `REQUEST_URI` fallbacks for the five machine files.** Live permalinks are flushed and `/opensearch.xml`, `/humans.txt`, `/.well-known/security.txt`, `/llms.txt` and `/llms-full.txt` answer 200 through the `sn_machine` rewrite rules, so each handler now serves only on its query var. The forced `status_header( 200 )` in each send function is gone too: the route is no longer a 404. Content, content types, cache headers and routes are unchanged. Tests: `tests/machine-routes.php`, `tests/humans-txt.php`, `tests/security-txt.php`, `tests/opensearch.php`, `tests/llms-txt.php`.

### Added
- **The theme flushes its machine-file rules once when they change.** `inc/machine-routes.php` stores a signature of its rule list and, on `wp_loaded`, soft-flushes (no `.htaccess` write) only when the stored one differs, as on a fresh install or a release that edits the list. Every other request is one autoloaded option read. Test: `tests/machine-routes.php`.

