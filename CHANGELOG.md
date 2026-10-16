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
- **Emoji is back for readers.** 2026.10.9 removed WordPress's emoji loader from pages and oEmbed frames for CSP prep. The owner had already decided on 2026-09-29 that emoji stays on the public site, feeds and email, off in wp-admin only. The loader is restored, and a test now fails if the theme ever unhooks it. A strict CSP allows the loader's inline module by hash instead.

## [2026.10.9] - 2026-10-16 — security prep for a stricter CSP, and whole excerpts on /notes

### Fixed
- **A note's excerpt on /notes is whole.** The row reveal clamped it to three lines, so a sentence could end mid-thought ("stop being..."). The clamp is gone; the reveal takes the excerpt's own height.

### Security
- The command palette's URL guard refuses any backslash, so `/\host` (which browsers read as `//host`) can no longer leave the site.
- **A note title can no longer add a line to the reply email.** The Reply row's `mailto:` subject now turns every control character (CR, LF, NUL, DEL and the rest of C0) into one space before it is encoded, so a title cannot start a new mail header such as `Bcc:` in a reader's mail client.
- **A Spanish page URL is copied into the header link literally.** The Spanish header nav swaps a link's `href` with a callback instead of a regex replacement string, so a `$1` or `\1` in a URL is never read as a backreference.

### Changed
- **The beacon and the command palette read their data from JSON data islands (CSP prep).** `window.SN_BEACON=…` and `window.SN_CMDK=…` were inline scripts; they are now `<script type="application/json" id="sn-beacon-data">` and `id="sn-cmdk-data"`, printed at `wp_footer` priority 1 with `JSON_HEX_TAG`, and the scripts read them with `JSON.parse`. A data island does not run, so it needs no `'unsafe-inline'` in the edge CSP. `window.SN_BEACON` stays the public `event()` API. For one release both scripts still fall back to the old global, for a page cached before this change. The palette now navigates only to a same-site path taken from its data, never a `javascript:` or `data:` URL.
- **The dark-mode head script (`#sn-theme-init`) is pinned to its CSP hash**, `sha256-+aDIk0AQYDEK+tK47t/wNITT4nprLRPhL+LH2Hb8THs=`, in `SN_THEME_INIT_SHA256` and its doc comment. `tests/theme-init-csp-hash.php` fails if the script's bytes change without the hash, so the edge policy is never silently out of date.

### Removed
- **WordPress's emoji loader no longer prints on pages or in oEmbed frames.** It was an inline `<script type="module">` the theme does not own, which would need `'unsafe-inline'` or a hash that changes with core. Every current browser draws emoji itself, and nothing in the theme styles the `img.emoji` it swapped in, so emoji now show as the system's glyphs. The feed and email emoji filters are untouched.

