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
- **The hero reserved a header height the body stopped using, and the inline nav rule was missing its underline reset.** Both found by the new parity pin, not by looking. `.sn-hero { min-height: calc(100vh - 108px - 90px) }` still subtracted the pre-13.6.0 header in `critical.css` and `layout.css` after `body` padding-top moved to 100px in v13.6.0; the two breakpoint variants tracked the change, desktop did not, so the desktop hero was 8px short. And `critical.css`'s `.wp-block-navigation a` lacked the `text-decoration: none !important` that `layout.css` carries under a comment claiming it mirrored critical: `theme.json` sets `elements.link` `:hover` to `underline` and core's nav reset is zero-specificity `:where(a)`, so a desktop nav link hovered before the combined sheet arrived got that underline on top of the theme's own `::after` blood rule.

### New
- **`tests/critical-deferred-parity.php` (10) guards the inline-critical / deferred-sheet split.** That design ships two copies of 19 rules on purpose, and its one failure mode is an edit landing in one copy only, which nothing catches because both files stay individually valid CSS. Group 1 asserts that where both copies declare the same property they agree; a property in only one copy is the critical-subset design (`.sn-footer { position: fixed }` belongs to `layout.css` alone) and is counted, never failed. Asserting identical declaration *sets* was the first version and it reported eleven divergences of which two were real, which is the shape of a check people learn to ignore. Group 2 pins the shared constant as a relationship rather than a literal: whatever `body` pads at a breakpoint, the hero subtracts the same number.

### Changed
- **The note-footer rows ship as a block stylesheet instead of riding the every-page payload.** First migration of the `components.css` split, following the Theme Handbook's [`wp_enqueue_block_style()`](https://developer.wordpress.org/themes/features/block-stylesheets/). The `.sn-related-notes` / `.sn-cited-by` rules apply only on a single note, the one template carrying those blocks, but sat in `components.css` and so shipped to every page. They move byte-identically to `assets/css/blocks/related-notes.css`, registered in the new `inc/block-styles-enqueue.php`; core enqueues the sheet only where one of the blocks renders and inlines it in `<head>` via `path`, so the conditional load adds no request. The every-page combined payload drops 4,319 bytes (150,582 to 146,263) and `components.css` drops to 69,012. One sheet serves both blocks under one handle, because the rules are grouped selectors serving both footers and core dedupes by handle; that is also why this uses the PHP API rather than `block.json` `"style"`, which is relative to a single block's directory. Pinned by `tests/block-style-related-notes.php` (20) against a committed fixture of the pre-move slice. `tests/lib/component-css.php` is the durable half: three suites pinned these rules by FILE and went red on a move that changed nothing they assert about, so they now read the component layer through one helper with an explicit list, and each future migration adds one line there.
- **Raised surfaces use the theme's own hard offset shadow, and the docs stop overclaiming.** The footnote popover and the note-link preview carried `0 4px 12px var(--sn-shadow-soft)`, a generic blurred drop shadow, while the theme already had a raised-surface device in two places: the command palette and the keyboard modal use `8px 8px 0`, hard, no blur. Both cards now use `4px 4px 0 var(--wp--preset--color--concrete)`, the same token as their own hairline, so it flips with the palette; 4px rather than 8 because these are 340px glance cards, not a 36rem panel. `--sn-panel-shadow` was deliberately not reused (it is a white hard shadow behind a black palette card in light mode and would have been invisible here), and `--sn-shadow-soft` had no consumers left, so its three declarations are gone. Kept on purpose: the two `blood` glows on button hover and the discography play badge, which are accent halos rather than an elevation ramp, and the hero veil gradient. `README.md`, `readme.txt` and `style.css` now lead with "clinical-industrial with brutalist typography", which is what `style.css` already said and what the stylesheets are, and the Aesthetic bullet names the real counts instead of claiming "no gradients" and "no elevation ramp" against a CSS that had one decorative gradient and four blurred shadows.

### Documentation
- **ADR-0001 takes a first-party WordPress amendment,** the same text the plugin repo carries: skills published by the WordPress organisation are in scope as reference material, everything else stays extract-not-install. Four were read against this theme and the plugin before installing (documentation only, no network calls, no credential handling); `wp-block-themes`, `wp-interactivity-api` and `wp-patterns` are the three that touch this repository.

### Changed
- **`docs/adr/adr-0001-third-party-agent-skills.md` no longer names private product repos.** The ADR's scope line, stack sentence and extraction note now say "private product repos"; the zero-tolerance rule stands as "private product repos are zero-tolerance: Anthropic first-party skills only", one sentence removed. Docs only; no version bump; history not rewritten.

## [13.6.1] - 2026-09-21 — the tab icon

### Fixed
- **The tab icon draws the mark: `favicon.ico` was a solid black square, and the icon URLs are versioned.** Chrome takes the `sizes="any"` `.ico` over the SVG, and the shipped `.ico` was one colour (`#000000`) at 48, 32 and 16, rasterised from the `var()` SVG that 13.6.0 fixed; fixing the SVG could not change what the tab showed. The `.ico` is re-rendered from the corrected tile with headless Chrome (three 32bpp frames, 39/36/26 distinct pixels). The three icon `<link>`s now carry `?ver=` from `sn_asset_ver()`: a browser's favicon cache is keyed on the URL and outlives every page purge, which is why the fixed SVG did not show either. `tests/home-screen-icon-opacity.php` (+5) reads the `.ico` frames and fails on a single-colour one (3 red on the shipped file) and pins the versioned links; `tests/head-sweep.php` pins `?ver=` on all three. Reported by the owner after installing 13.6.0.

### Changed
- **Footer signature: the stamp is 32px and the descriptor tracks `wide`.** At 28px the stamp sat beside a taller text block instead of anchoring it; at `ultra` (0.3em) the 11px descriptor came out 1.8x the wordmark's width, so the subordinate line led the lockup. `ultra` is right for the OG card at 16px across 1200px; in a 58px bar it over-tracks. `tests/footer-signature.php` re-pinned.

