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
- **The footer's RSS link reads "Suscribite por RSS" on Spanish pages** (`inc/lang-chrome-footer.php`). The label core prints for the link, and any `aria-label` or `title` that repeats it, is Spanish wherever the theme treats the page as Spanish. English pages keep "Subscribe via RSS" byte for byte, the template part keeps its English label, and the feed URL is the same.
- **Two frame names a screen reader reads are Spanish on Spanish pages.** The featured player's `title` is "Reproductor destacado de Spotify" (`inc/music-featured-render.php`) and a played cover's is "Reproductor de Spotify" (`assets/js/discography.js`, by `<html lang>`, like the command palette). Neither is painted. English is unchanged.
- **The share row and discography scripts are deferred, and the footnotes script is versioned like the rest** (`inc/assets-frontend.php`). Both scripts wait for `DOMContentLoaded` themselves and have no inline dependent. The share row loading as a blocking script made core print its clipboard helper blocking too. The footnotes script takes `sn_asset_ver()`, so its URL changes when the file does.

### Fixed
- **A page restored from the back/forward cache no longer resends the referrer the reader first arrived with** (`assets/js/sn-beacon.js`). The restored view is a new page view, and it carried the original `document.referrer` (a search engine, say), so one arrival could count twice. It now sends the site's own origin, as an in-site navigation does, which the collector files as same-site. Custom events on that view carry the same value. UTM was already first-view only.
- **A page too short to scroll reports its scroll milestones on a restored view too.** The restore reset the milestones and never evaluated them again.
- **`SN_BEACON.event` is a callable no-op when the beacon has no endpoint or token.** The script returned before defining it, so a caller could throw.
- **`[sn_email]` brings its own script** (`inc/contact-email.php`). The alias script loaded on the page with the slug `contact` only, so a Spanish contact page paired by the plugin, or any other page carrying the shortcode, would have shown "[at]/[dot]" text. The shortcode now enqueues the registered handle wherever it renders.
- **A Spotify embed with no query string gets `?theme=0`** (`inc/frontend-filters.php`). `&theme=0` was appended blind, which on such a `src` is part of the path and not a parameter. It goes on with `add_query_arg()` now.
- **The command palette finds accented titles** (`assets/js/command-palette.js`). The word split was ASCII-only, so "música" lost its prefix rank and "musica" matched nothing. Query and titles are compared with accents folded. Rows show the titles as written.

## [2026.11.17] - 2026-11-22 — A CLS reading names the element that moved

### Added
- **A layout-shift reading now says which element moved.** The beacon's CLS event (`vc`) may carry `s`, a short selector for the element that moved most in the largest shift: the lower-case tag, the id, and up to three class names, 96 characters at most. It names the page's own markup and nothing about the reader: no text, no attributes, no ancestors. `html` and `body` are named by tag alone, since WordPress puts `logged-in` among the body classes. An id or class with a character outside letters, digits, `_` and `-` is left out, and when there is nothing to name, or the selector code throws, the event goes out exactly as before. LCP and INP are unchanged. It reads the `entries` the vendored web-vitals build already passes, so no new script (`assets/js/sn-beacon.js`). The collector has to accept the new key before it shows anywhere. Test: `tests/js/beacon-cls-selector.test.cjs` (9).

