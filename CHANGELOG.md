# Changelog

All notable changes to the Signal & Noise theme are documented here.

This file holds two things only: **`## [Unreleased]`**, the working log that
accumulates across pull requests, and the **current release**. Everything older
lives in [docs/changelog/](docs/changelog/).

A pull request does not bump `Version` and does not tag — it closes an issue and
adds a bullet below. A release is a separate, deliberate act:
`tools/cut-release.sh`, which stamps `style.css` **and** `readme.txt` together.

## [Unreleased]

### Changed
- **The share row, the cite line and the section links announce a copy through core's `wp.a11y.speak()`** (theme core-first audit, finding 18). `inc/sn-copy.php` registers `sn-copy` with `'wp-a11y'` as its dependency; `liveRegion()` is gone from `assets/js/sn-copy.js`, and `note-share.js` and `article-toc.js` hand their message to `wp.a11y.speak()` instead of writing a region of their own (one per share row, one per cite line, one on the body). Core keeps one polite region per page and repeats an identical message itself; ours cleared and set the text in the same tick, which does not reliably re-announce. The messages, the COPIED and COPY FAILED labels, `data-sn-state` and the keyboard behavior are unchanged. A page cached before this release, which loads the new scripts without `wp-a11y`, still copies and shows the state, silently and without throwing. Tests: `tests/js/note-share.test.cjs` and `tests/js/section-links.test.cjs` assert what is spoken and the stale-page case (seen red against the old scripts, and with the guard removed); `tests/block-view-scripts.php` pins the dependency.
- **CSP: notes (and attachment pages) now carry one more inline script, and its hash must be in the Cloudflare rule before this release is installed.** `wp-a11y` needs `wp-i18n`, and core prints wp-i18n's text direction after it: `<script id="wp-i18n-js-after">`, 99 bytes on WordPress 7.1.4 in en_US, source `'sha256-wv9t8ovqnH4ayVaS46LraLM76HAxxF+W6+UxmHbZ6oI='`. It is the only inline script on the chain (`wp-dom-ready`, `wp-hooks`, `wp-i18n` and `wp-a11y` are files). Without the hash nothing breaks (`wp.i18n` defaults to left to right), but every note logs a violation and the plugin's daily CSP check raises a watch. The hash is pinned as `SN_COPY_I18N_CSP_SHA256`; the new `tests/sn-copy-csp-hash.php` registers the theme's real handles into core's real `WP_Scripts`, prints the head and footer, and fails unless that hash is the only inline script there and `docs/CSP.md` lists it. It was seen red with the dependency dropped, a wrong constant, core's bytes changed, a second core inline script on the chain, and a translation file present.
- **The suite's core is WordPress 7.1.4**, the release the site runs (`WP_REF` in `tools/fetch-wp-test-core.sh`, from 7.1.2; the sweep is green on both), and the fetch now includes core's script dependency classes, `functions.wp-scripts.php` and the package registry.

## [2026.11.8] - 2026-11-10 — Machine files on their own rewrite rules, with their cache lifetime

### Removed
- **The `REQUEST_URI` fallbacks for the five machine files.** Live permalinks are flushed and `/opensearch.xml`, `/humans.txt`, `/.well-known/security.txt`, `/llms.txt` and `/llms-full.txt` answer 200 through the `sn_machine` rewrite rules, so each handler now serves only on its query var. The forced `status_header( 200 )` in each send function is gone too: the route is no longer a 404. Content, content types, cache headers and routes are unchanged. Tests: `tests/machine-routes.php`, `tests/humans-txt.php`, `tests/security-txt.php`, `tests/opensearch.php`, `tests/llms-txt.php`.

### Added
- **The theme flushes its machine-file rules once when they change.** `inc/machine-routes.php` stores a signature of its rule list and, on `wp_loaded`, soft-flushes (no `.htaccess` write) only when the stored one differs, as on a fresh install or a release that edits the list. Every other request is one autoloaded option read. Test: `tests/machine-routes.php`.

