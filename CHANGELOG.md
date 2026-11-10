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
- **Each AI ability's `tokens_used` now says what it is.** The output schema of all five generative abilities describes the figure as an output-only estimate (characters / 4) that leaves out the prompt. The field keeps its name, so nothing that reads it breaks. Test: `tests/ai-tokens-used-schema.php`.
- **The /notes summary ability runs on the official AI plugin when it can.** `signal-and-noise/ai-generate-page-note-summary` keeps its name and its `{summary, post_id, tokens_used}` answer, but when the AI plugin registers `ai/summarization` it now runs that ability for the post, with the /notes voice and the word cap appended to the official instruction for that one call. An error from the official ability is returned as is. Without the AI plugin, the theme's own path runs as before. Test: `tests/ai-note-summary-official.php`.
- **The essay tracklist and the résumé lanes read the stored content with the HTML API.** `sn_essay_tracks()` and `sn_resume_lane_axis_start()` walk the markup with `WP_HTML_Tag_Processor` instead of regular expressions. On block-built essays and résumés the output is the same. Three cases the regex got wrong now work: a rail with a single-quoted class, a date written with an encoded en dash, and an HTML comment between a "Section N" label and its heading. Tests: `tests/essay-tracklist.php`, `tests/surface-lanes.php`.
- **The provenance brow finds its place with the HTML API.** `sn_prov_brow_is_eyebrow()` and `sn_prov_brow_content_has_eyebrow()` look for a P element carrying `sn-catalog-eyebrow` or `sn-provenance-eyebrow` with `WP_HTML_Tag_Processor`, not a regular expression. A single-quoted or unquoted class now counts, and a page that only mentions the class in text or a comment no longer stops the badge from being placed. The output markup is unchanged. Test: `tests/provenance-brow-badge.php`.
- **The header logo is core's Site Title block.** `parts/header.html` places `core/site-title` (a P wrapper, `isLink`) instead of a hand-written anchor, so the name follows Settings > General and the link follows `home_url()`. `inc/site-title-logo.php` adds the `sn-wordmark` class and the same accessible name with the HTML API, and on a Spanish page sends the link to the Spanish home with the Spanish label. The wordmark looks the same: the wrapper keeps `sn-logo-link` and the link carries `sn-wordmark`. On the front page the link now also carries core's `aria-current="page"`. Test: `tests/site-title-logo.php`.
- **The Spanish header and footer strings are set with the HTML API.** `sn_lang_chrome_strings()` and `sn_lang_meta_nav()` find the menu's labels, the search trigger's word and each footer link (by its href) with `WP_HTML_Tag_Processor` instead of matching exact bytes, so a reordered attribute, an added class or a resave in the editor no longer stops the translation. The Spanish text is the same. English pages are untouched. Test: `tests/lang-chrome.php`.
- **`/llms-full.txt` summarizes each note with core's excerpt.** A note without a manual excerpt is summarized from `get_the_excerpt()` (shortcodes and the blocks core leaves out of an excerpt are dropped), trimmed to 28 words and decoded for plain text, instead of from raw `post_content`. The password gate on the query and the per-row check both stay, and a test now proves a protected note never reaches the file. Some summaries read differently. Test: `tests/llms-txt.php`.
- **The purge probe reads the render-epoch tag with the HTML API.** `sn_purge_probe()` finds `<meta name="sn-render-epoch">` with `WP_HTML_Tag_Processor`, so a minifier or edge rewrite that reorders or unquotes the attributes no longer turns a fresh page into "unknown". A non-digit value is still unknown, never a pass. Test: `tests/purge-verify.php`.
- **The speculation module's header says what core does in 7.1.** Core sets mode and eagerness to `auto`, which resolves to prefetch and conservative unless the host sets the `WP_SPECULATIVE_LOADING_DEFAULT_*` constants; the theme's explicit prerender and moderate take precedence over both. A comment change only.

### Added
- **The machine files are registered with the rewrite API.** `inc/machine-routes.php` adds a rewrite rule and the `sn_machine` query var for `/opensearch.xml`, `/humans.txt`, `/.well-known/security.txt`, `/llms.txt` and `/llms-full.txt`, the way core registers `robots.txt`, so WordPress resolves them as routes instead of 404s. The same `template_redirect` handlers answer them, after `send_headers` and `wp` have run. The rules take effect at the plugin's next rewrite flush; the theme does not flush. Until that flush is confirmed live, the `REQUEST_URI` handlers, their `status_header( 200 )` and the 404-header undo in `cache-headers-hooks.php` stay as the fallback. Test: `tests/machine-routes.php`.

## [2026.11.6] - 2026-11-07 — The record sleeve for /services

### Added
- **A record sleeve for `/services`.** `.sn-sides` and `.sn-side` draw Side A and Side B: a photo, a big name, a line, a numbered track list and one link stretched over the whole side. The home page's pulse and playhead close the sleeve. `.sn-service-crop` keeps the Consulting photos at one shape. Three crops of the desk photo ship in `assets/images/services/`, so Consulting has images like the old service cards. No image is generated.

### Removed
- **The `.sn-door-link` styles.** The hub no longer uses them. `.sn-door` stays for the rows on `/services/consulting`, now two columns.

