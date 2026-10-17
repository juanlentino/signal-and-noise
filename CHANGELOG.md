# Changelog

All notable changes to the Signal & Noise theme are documented here.

This file holds two things only: **`## [Unreleased]`**, the working log that
accumulates across pull requests, and the **current release**. Everything older
lives in [docs/changelog/](docs/changelog/).

A pull request does not bump `Version` and does not tag — it closes an issue and
adds a bullet below. A release is a separate, deliberate act:
`tools/cut-release.sh`, which stamps `style.css` **and** `readme.txt` together.

## [Unreleased]

- **Four new reading aids for notes: timeline, key term, checklist and comparison table.** All four are in the editorial-conventions registry, marked unused, so `sn-site-facts{editorial_conventions}` hands out a pasteable exemplar for each. **Timeline** (`signal-noise/timeline`): a dated sequence on a vertical hairline with a blood marker per item and the date set above its sentence in small tracked capitals, so it never reads as the 01/02/03 steps panel. All core blocks, and each item opens with `<strong>Date.</strong>` like a steps item. Where the browser supports scroll-driven animation and the reader has not asked for less motion, each marker fills as its item scrolls into place, with no script involved. **Key term** (`signal-noise/term`): a dynamic block, `<dl class="sn-term">` with one `<dt>` and one `<dd>`. Both fields are plain text, escaped with `esc_html`, and edited through `PlainText`. It stays in the column at every width with a blood left rule, one size under the prose, so it cannot be mistaken for the sidenote's margin float. Each cell wraps its text in a `<p>` so the public verifier rebuilds the same paragraph break the ledger signs (`tests/blocks-render-parity.php` now covers it, and goes red without the `<p>`). **Checklist** (`is-style-checklist` on core/list, `signal-noise/checklist`): an empty box per item drawn as a CSS border. Nothing to tick and nothing stored, and the boxes print. **Comparison table** (`is-style-sn-table` on core/table): hairline rows, a small-caps header row, no zebra, a caption set like the steps label. A 30rem minimum width makes a phone scroll the table inside its figure while the page stays put (measured at 375px: page 375 wide, table 137px of in-figure scroll), and the caption stays pinned while it scrolls. Core's table editor never writes `scope` on header cells, so `inc/table-header-scope.php` adds `scope="col"` to this style's `<thead>` cells on render with the HTML API, and keeps any scope the author set. All four print cleanly (`assets/css/print.css`). Text and strokes are currentColor and the accent is the blood token in both palettes. Inserting each in the editor, saving and reloading left all 49 blocks valid with no recovery warning, on WordPress 7.1.3.
- **The Spanish header nav writes its links through core's HTML API.** `sn_lang_nav_link_html()` (`inc/lang-chrome.php`) swapped the href with a regex on `href="..."` and added `aria-current` with another; it now uses `WP_HTML_Tag_Processor` (`next_tag`, `get_attribute`, `set_attribute`), so a `>` inside an earlier attribute value, a single-quoted or unquoted href, and `$1` or `\1` in the URL are parsed by core. Output for the rendered navigation-link is byte for byte the same; an odd source tag now comes out with a double-quoted href. `tests/lang-chrome.php` pins three such tags (red against the regex: 21 passed, 3 failed).

## [2026.10.10] - 2026-10-16 — emoji is back for readers

### Fixed
- **Emoji is back for readers.** 2026.10.9 removed WordPress's emoji loader from pages and oEmbed frames for CSP prep. The owner had already decided on 2026-09-29 that emoji stays on the public site, feeds and email, off in wp-admin only. The loader is restored, and a test now fails if the theme ever unhooks it. A strict CSP allows the loader's inline module by hash instead.

