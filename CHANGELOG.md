# Changelog

All notable changes to the Signal & Noise theme are documented here.

This file holds two things only: **`## [Unreleased]`**, the working log that
accumulates across pull requests, and the **current release**. Everything older
lives in [docs/changelog/](docs/changelog/).

A pull request does not bump `Version` and does not tag — it closes an issue and
adds a bullet below. A release is a separate, deliberate act:
`tools/cut-release.sh`, which stamps `style.css` **and** `readme.txt` together.

## [Unreleased]

- **The Spanish header nav writes its links through core's HTML API.** `sn_lang_nav_link_html()` (`inc/lang-chrome.php`) swapped the href with a regex on `href="..."` and added `aria-current` with another; it now uses `WP_HTML_Tag_Processor` (`next_tag`, `get_attribute`, `set_attribute`), so a `>` inside an earlier attribute value, a single-quoted or unquoted href, and `$1` or `\1` in the URL are parsed by core. Output for the rendered navigation-link is byte for byte the same; an odd source tag now comes out with a double-quoted href. `tests/lang-chrome.php` pins three such tags (red against the regex: 21 passed, 3 failed).

## [2026.10.10] - 2026-10-16 — emoji is back for readers

### Fixed
- **Emoji is back for readers.** 2026.10.9 removed WordPress's emoji loader from pages and oEmbed frames for CSP prep. The owner had already decided on 2026-09-29 that emoji stays on the public site, feeds and email, off in wp-admin only. The loader is restored, and a test now fails if the theme ever unhooks it. A strict CSP allows the loader's inline module by hash instead.

