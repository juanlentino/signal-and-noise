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
- **The provenance badge sat at the foot of the three provenance pages (#351).** The brow placement joined `.sn-catalog-eyebrow` or created a brow above a `core/post-title` block, and nothing else; the essays open with `.sn-provenance-eyebrow` and /provenance with an authored h1 and no eyebrow, so on all three the badge fell through to the plugin's content append and rendered after the closing CTA, the position the brow exists to leave. `sn_prov_brow_is_eyebrow()` now accepts either brow class, and `sn_prov_brow_heading_filter()` on `render_block_core/heading` creates the solo brow above the first authored level-1 heading when the content carries no eyebrow (an h2 never earns one; an authored brow anywhere hands the page to the paragraph filter). Suites: `tests/provenance-brow-badge-provenance-eyebrow.php` (7), `tests/provenance-brow-badge-authored-h1.php` (9, including the hook registration). Mutation red: catalog-only class match.

## [13.2.1] - 2026-09-14 — the .json twin serves signed Pages

### Fixed
- **The `.json` twin now serves signed Pages (#348).** `inc/content-json-document.php` emitted the `provenance` block for Notes only, from before pages could opt into signing (plugin 13.69); a signed page's twin carried no `note_uid`, so `/verify` could not resolve a pasted page URL. The block now follows the uid: any subject the plugin has minted carries it (the key stays `note_uid`, the docket's query parameter; the sentence says "page" for a page). A uid-less page stays silent, never a fabricated claim. The finished document also passes through a new `sn_content_json_document` filter so the plugin can add `content_signed`, the prose as the signing normalization sees it: the integrity sweep and `/verify` compared rendered `content_text` (where `[sn_reading_time]` reads "5 min read") against the raw-signed body and called every signed page drifted the moment it was minted. Test: `tests/content-json-document.php` 24 → 28 (signed page carries the block and its docket URL, uid-less page does not, the filter receives the post and its return is the twin). Mutations red: filter removed, page branch removed.

