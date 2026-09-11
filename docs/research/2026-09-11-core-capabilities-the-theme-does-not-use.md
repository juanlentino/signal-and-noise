# What WordPress already offers that this theme does not use (survey, 2026-09-11)

**Ask:** "Research things to add to the theme from WordPress repos and handbooks
that would add something we don't have."
**Method:** inventory of what the theme declares (`theme.json` v3, 17 templates,
4 parts, 8 patterns, 3 custom blocks, 1 style variation, 1 block-bindings
source), read against the block-theme handbook, the theme.json reference, the
7.0 / 7.1 source-of-truth posts, and this repo's own prior audits
(`docs/WP-API-MAP.md`, `docs/WP-7.0-CHECKLIST.md`, `docs/AUDIT-THEME-HANDBOOK.md`).
Ranked by the rule from the plugin's search arc: **one seam away from something
the theme or plugin already computes beats anything net-new.**

## What the theme already has (so nothing below re-proposes it)

theme.json v3 with fluid typography, a palette, spacing scale, `viewport`
breakpoints (7.1), pseudo-state element styles (7.0), three block-style
partials (`styles/blocks/`), one style variation (`high-contrast`), eleven
custom page templates, two declared template parts (header, footer), a
Block Bindings source (`signal-noise/post-field`: `reading_time`, `pillar`,
`canonical`, `og_title`), three server-rendered custom blocks (pillar-essays,
pull-quote, sidenote), Interactivity-API script modules with `fetchpriority=low`,
a footnotes stylesheet gate, a curated editor block palette, and eight patterns.

## The finding that matters most — and its history

**The theme registers a Block Bindings source that nothing binds to.**
`inc/block-bindings.php` resolves four post fields; `grep bindings templates
parts patterns` → 0. The templates instead carry **20 `wp:shortcode` blocks**
(`[sn_reading_time]` ×6, `[sn_theme_toggle]` ×2, `[sn_related_notes]` ×2,
`[sn_reading_path]` ×2, `[sn_updated_date]`, `[sn_prov_panel]`, `[sn_prov_chip]`,
`[sn_post_pillar]`, `[sn_note_share]`, `[sn_note_reply]`, `[sn_cited_by]`) and
**19 `wp:html` blocks** (five inline SVG icons and four separators in the
footer, the hero wrapper, the logo link, the 404 search form).

This is not an oversight; it is an abandoned migration. `docs/WP-API-MAP.md`
(v7.2.7 era) made Block Bindings its #1 recommendation; v9.11.0 (2026-06-07,
`a683390`) shipped the source AND bound the front-matter reading-time and
pillar slots; **v9.11.1 (2026-06-08, `2cf1e2f`) reverted the template half the
next day** after a single-note fatal — the `get_the_queried_object_id()`
typo class (`phpstan.neon` header names it). The source stayed, was fixed
(`a6ae3ac`, BND-1/BND-4), and was never re-bound. `docs/WP-7.0-CHECKLIST.md`
still lists it as "Top R2 recommendation, skipped in Phase 1". Since then:
PHPStan is a required check on `main`, the stub-parity sweep pins every
WP-shaped call, and the theme has a 122-suite sweep. **The thing that broke
the migration cannot reach `main` any more.** The retry is one seam away.

## Candidates, ranked

| # | What | Core capability (version) | What already exists | Seam | Reader/editor gain |
|---|---|---|---|---|---|
| 1 | **Re-bind `[sn_reading_time]` and `[sn_post_pillar]` to the registered source** — a paragraph with `metadata.bindings.content = { source: "signal-noise/post-field", args: { key: "reading_time" } }`; the v9.11.0 template diff is the starting point | Block Bindings API (6.5; editor UI 7.0) | `inc/block-bindings.php`, registered, fixed, tested; the June template diff | template edits; `tests/` pin that `single.html` renders through the real source | the value is visible and styleable as a paragraph; seven shortcodes gone; the source finally has a consumer |
| 2 | **Turn the remaining shortcode renderers into PHP-only server-rendered blocks** — `register_block_type` with `autoRegister` + `render_callback`; no JS build | PHP-only block registration (7.0) | one PHP renderer per shortcode | wrap each renderer; swap 13 template lines | insertable, movable, styleable in the site editor; `wp:shortcode` gone from a block theme; alignment/spacing supports for free |
| 3 | **Hook the provenance chip and the closing part into every single by rule** — `hooked_block_types`: chip after `core/post-title`, `post-closing` after `core/post-content` | Block Hooks API (6.4; template-part hooks 6.5) | `post-frontmatter` / `post-closing` parts | one filter + a pin | a new single template can never forget them; the hub's "every signed note carries its chip" becomes enforced, not hoped-for |
| 4 | **Declare `post-frontmatter` and `post-closing` as template-part areas** | `theme.json.templateParts[].area` (+ `default_wp_template_part_areas` for a custom area) | the two parts | two lines | they stop showing as "General" in the site editor (already on the 7.0 checklist) |
| 5 | **Footer icons through the SVG Icon API** — register the five inline SVGs as a `signal-noise` icon collection; use the Icon block | Icon block (7.0) + public Custom SVG Icons API (7.1) | five hand-inlined SVGs in `parts/footer.html` | one registration + five block swaps | icons editable and reusable; nine of the 19 raw-HTML blocks gone |
| 6 | **Raise speculative loading for the notes index** — `wp_speculation_rules_configuration` → `{ mode: prerender, eagerness: moderate }` scoped to `/notes/*`, excluding `?s=` | Speculative Loading (6.8, on by default at "conservative prefetch") | nothing to build | one filter | near-instant note opens from the index, measurable in the rollups the plugin already keeps |
| 7 | **`enhancedPagination` on the Notes query loop** | Query Loop client-side navigation (Interactivity API) | the `wp:query` on `/notes` | one attribute — but the list is PHP-painted (`page-notes-render`); confirm that block is what paginates before flipping | no full reload between pages |
| 8 | **`@mobile` / `@tablet` overrides in theme.json** for rules `responsive.css` hand-writes | Responsive block styling (7.1) | `viewport` declared; `responsive.css` | rule by rule, or never | one source of truth for breakpoints; editor previews them |
| 9 | Core **Details** block for the notes-index year spine | Details (6.3) | spine painted in PHP | painter rewrite | editor-visible; not clearly better — low |
| 10 | **Breadcrumbs** on single notes / tag archives | Breadcrumbs (7.0) | nothing | insert | debatable on a two-level site |
| 11 | **Tabs** on `/uses` or `/resume` | Tabs (7.1) | pages exist | content edit | only if the page wants it — not a theme change |
| 12 | **Style variations** `monolith` / `inverted` (on the 7.0 checklist) | `styles/*.json` | `high-contrast.json` | XS | visible in the Styles browser; against the hash-frozen direction unless the owner wants it |

## Not proposed, and why

- **Playlist block** — `/music` embeds Spotify; Playlist plays uploaded audio.
- **Lightbox, duotone, gradients, cover video** — brutalist, white-first, image-light by design (`CLAUDE.md` §Stack).
- **Pattern overrides for the custom blocks** — single-instance blocks; nothing to override.
- **Font Library** — the stack is deliberate and self-hosted; no gain.
- **View Transitions on the front end** — still a feature plugin at 7.1, not core.

## Recommendation

**One arc: "the shortcodes become blocks."** Rows 1 → 2 → 3 → 4, in that order,
on the theme, plugin untouched. It finishes the migration this repo started
in June and reverted for a typo that CI now refuses; removes all 20
`wp:shortcode` blocks from a block theme; gives the site editor surfaces it
cannot see today; and every piece already exists as a renderer or a
registration. The search arc's shape again — no new computation, one seam.
Earns **13.1.0**.

Rows 5 and 6 are each an afternoon and can ride the same release. Rows 7–12
wait for a reason.

## Sources

[Block Editor Handbook — Global Settings & Styles](https://developer.wordpress.org/block-editor/how-to-guides/themes/global-settings-and-styles/) ·
[Block Bindings API](https://developer.wordpress.org/block-editor/reference-guides/block-api/block-bindings/) ·
[WordPress 7.0 Source of Truth](https://gutenbergtimes.com/wordpress-7-0-source-of-truth/) ·
[WordPress 7.1 Source of Truth](https://gutenbergtimes.com/wordpress-7-1-source-of-truth/) ·
[Icons, Tabs, and responsive styles (7.1)](https://gutenbergtimes.com/icons-tabs-and-responsive-styles-the-community-gets-to-work-on-wordpress-7-1-weekend-edition-374/) ·
[gutenberg#75707 — 7.1 responsive breakpoints in theme.json](https://github.com/WordPress/gutenberg/issues/75707) ·
this repo: `docs/WP-API-MAP.md`, `docs/WP-7.0-CHECKLIST.md`, commits `a683390`, `2cf1e2f`, `a6ae3ac`.
