# B4 — "Feeds, blocks & pages" (theme v9.11.0) — design

**Status:** Design approved (forks resolved 2026-06-07); contracts verified; ready for `writing-plans`.
**Repo:** theme (`signal-and-noise`). **Version target:** **v9.11.0** (current `9.10.0`). Minor — six additive, reader-facing capabilities; no breaking changes.
**Worktree:** `claude/v9.11.0` off `origin/main` (`9c2eeec`). Baseline green: 20 suites / ~610 assertions.
**Provenance:** Track B4 of the [upgrade-opportunities roadmap](2026-06-06-upgrade-opportunities-roadmap.md) (locked A→B→C; B1/B2/B3 shipped). Grounded by a 4-agent contract-verification workflow (`wf_923523e4-1bc`, 2026-06-07) that read real WP-core + plugin source under refute-by-default. **This spec embeds the load-bearing contract facts so the grounding is preserved in-repo** (the prior B4 grounding was lost to session-temp).

---

## Goals

Ship the six "Feeds, blocks & pages" items as one front-end/editor minor:

1. **JSON Feed 1.1** endpoint for the Notes corpus.
2. **RSS enrichment** — `media:content` (OG image) + reading-time/tags on RSS items.
3. **Sidenote + pull-quote custom blocks** (buildless dynamic blocks) superseding the existing patterns.
4. **Block Bindings source** `signal-noise/post-field`, migrating only `parts/post-frontmatter.html`.
5. **`/colophon`** FSE page template + editable pattern + quiet footer link (defer `/now`).
6. **Reader ⌘K palette** — Notes-scoped navigation overlay (vanilla JS, accessible).

### Forks resolved (user, 2026-06-07)
- **Sidenote/pull-quote → full custom blocks** (not just block-style variations; not patterns-only).
- **Reader palette → Notes-scoped** (search notes + recent notes + pillar pages; *not* full-site — consistent with the v9.8.0 "Notes-scoped, Pages dropped" decision).
- **Block Bindings → build now in B4** (not deferred to the v10.0.0 cleanup major).

## Project constraints (apply to every item)

- **Theme must NEVER flush rewrite rules** — the companion plugin owns the permalink structure + the single flush. Anything needing a flush degrades to query-arg-first.
- **No JS build step anywhere** — buildless ES5 only for any editor/front-end JS. No `package.json`, no `@wordpress/scripts`, no `.asset.php` sidecars.
- **Modules ≤ ~150 lines**, one purpose each, loaded from `functions.php`. Named functions (never closures) for anything hooked, so the standalone CLI tests can `require` and call helpers directly.
- **`function_exists()`-guard every cross-package (plugin) read** so the theme degrades gracefully when the plugin is absent.
- **Brutalist/white-first, single-author.** Use WP preset tokens (`var(--wp--preset--color--…)`, `--font-family--{heading=Bebas,body=DM Mono}`), not bespoke vars. 11px type floor. No dark mode. No new wp-admin chrome.
- **Tests:** standalone CLI `php tests/<name>.php` fixtures; 404-guard on web; stub WP primitives; behavioral assertions (observable output), not just registration shape. Falsify the linter (inject a violation) before trusting "phpcs clean" — the worktree lives under a `.claude/` path that has tripped exclude-pattern false-greens.
- **Drift-safe versioning:** never hardcode a version constant; derive from `wp_get_theme()->get('Version')` or asset `filemtime` (`sn_asset_ver`).

---

## Item 1 — JSON Feed 1.1 (`inc/feed-json.php`)

**What:** A JSON Feed 1.1 document over the Notes corpus, registered via `add_feed('json', …)`.

**Approach (query-arg-first, no flush):**
- `add_feed('json', 'sn_feed_json_render')` on the **`init`** hook. This (a) registers the `do_feed_json` action and (b) appends `json` to `$wp_rewrite->feeds`. `?feed=json` resolves **immediately** (`feed` is a hardcoded public query var). The pretty `/feed/json/` path needs a rewrite rule that only materializes on the **plugin's next flush** — the theme must not flush.
- Render callback signature is `($is_comment_feed, $feed_name)` (dispatched by core's `do_feed()`), **not** the query. Build a fresh bounded `WP_Query(post_type=post, post_status=publish, posts_per_page=20, no_found_rows=true)`.
- **Content-Type:** core's `send_headers()` runs first and sets `application/octet-stream` for the unknown `json` type. The callback **must** `header('Content-Type: application/feed+json; charset='.get_option('blog_charset'))` (PHP `header()` replaces by default — same pattern as core `do_robots()`). Belt-and-suspenders: also `add_filter('feed_content_type', …)` returning `application/feed+json` for `$feed==='json'`.
- **Body:** PHP assoc array → `wp_json_encode($feed, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)`. **Raw values into the array** — `wp_json_encode` does the escaping; do **not** `esc_html` (would double-encode HTML entities into JSON). `echo` then `exit`.
- **JSON Feed 1.1 shape:** `version: "https://jsonfeed.org/version/1.1"`, `title`, `home_page_url` (`/notes/`), `feed_url` (`/feed/json/`), `language`, `items[]`. Per item: `id` (permalink, stable), `url`, `title`, `content_html` (`apply_filters('the_content', …)`), `date_published`/`date_modified` (`get_post_time('c', true, $post)` — RFC 3339), `tags` (category names), optional `summary` (excerpt).
- **Plausible:** the plugin's `is_feed()` tracker auto-fires for `?feed=json` (no plugin change). Caveat: it strips the query string, so `?feed=json` logs as `home_url('/')`; advertise the pretty `/feed/json/` (live after the plugin's next flush) for a distinct analytics bucket.

**Files:** new `inc/feed-json.php` (named `sn_feed_json_register` / `sn_feed_json_render` / `sn_feed_json_build_item`); `require_once` from `functions.php`. Optional `<link rel="alternate" type="application/feed+json">` advertisement (use `home_url('/?feed=json')` href until pretty path is live).

**Tests:** `tests/feed-json.php` — stub WP, call `sn_feed_json_build_item()` directly: assert `id` is string, `content_html` present, `date_published` matches `/^\d{4}-\d{2}-\d{2}T/`, the assembled feed round-trips `json_decode(wp_json_encode(...))` with the correct `version`, and a value containing `"`/`\` survives encoding without HTML-entity mangling (proves JSON not `esc_html` escaping).

---

## Item 2 — RSS item enrichment (`inc/feed-enrichment.php`)

**What:** Enrich the existing core RSS2 feed with a media namespace, the post's OG image as `media:content`, and reading-time/tags. The theme does **no** RSS customization today — greenfield.

**Approach:**
- `add_filter('rss2_ns', …)` to declare `xmlns:media="http://search.yahoo.com/mrss/"`.
- `add_action('rss2_item', …)` to emit `<media:content url="…" medium="image"/>` for the post's featured image (and/or the plugin's OG card), plus `<sn:readingTime>`/category tags as appropriate. All `function_exists`-guarded for the plugin-owned OG/reading-time data; degrade to nothing when absent.
- Escape per XML context (`esc_url`, `esc_xml`/`esc_html` as appropriate inside the feed item).

**Files:** new `inc/feed-enrichment.php` (named functions); `require_once` from `functions.php`.

**Tests:** `tests/feed-enrichment.php` — assert the namespace string is emitted; with a stubbed featured image + reading-time, the item callback outputs a `media:content` URL and reading-time; with the plugin absent (guards false), it emits nothing (no fatal).

---

## Item 3 — Sidenote + pull-quote custom blocks (`blocks/`, `inc/blocks-register.php`)

**What:** Promote the two existing CSS-styled **patterns** to first-class **dynamic blocks** (`signal-noise/sidenote`, `signal-noise/pull-quote`). Keep the patterns as annotated fallbacks.

**Corrected framework facts (refuted myths):**
- **`autoRegister` IS real** (`_wp_enqueue_auto_register_blocks()`, `wp-includes/blocks.php`, `@since 7.0.0`) — the handoff's "myth" claim was wrong. **But** it supports only `string/number/boolean/enum` attributes via auto-generated *sidebar* controls — **no RichText, no inline editing**. Sidenote/pull-quote bodies are inline rich text → autoRegister is the **wrong** primary path. Use the buildless RichText `editor.js`.
- **Do NOT use `"editorScript": "file:./editor.js"`** in block.json. Without a build there is no `.asset.php` sidecar, so core registers the script with **empty deps** → `wp is undefined`. Instead: `wp_register_script('signal-noise-blocks-editor', …, ['wp-blocks','wp-element','wp-block-editor','wp-components'], …)` ourselves and reference it by **handle string** in both `block.json` files.
- **Block vs pattern categories are different registries.** The existing `register_block_pattern_category('signal-noise')` does **not** create a block-inserter category — add a separate `block_categories_all` filter.

**Approach:**
- `blocks/` dir: one shared buildless `editor.js` (ES5 IIFE registering both blocks via `wp.blocks.registerBlockType` + `wp.element.createElement` + `wp.blockEditor.RichText`/`useBlockProps`, no JSX) + `sidenote/{block.json,render.php}` + `pull-quote/{block.json,render.php}`.
- `block.json`: `apiVersion: 3`, `name`, `title`, `category: "signal-noise"`, attributes sourced from HTML (`source: "html"`, `selector`), `editorScript: "signal-noise-blocks-editor"` (handle), `render: "file:./render.php"`. Sidenote: one `content` attr. Pull-quote: `body` + `attribution` attrs (two separate RichText instances — RichText `multiline` is deprecated).
- `render.php` (dynamic, server-rendered front-end markup): use `get_block_wrapper_attributes(['class' => 'sn-sidenote'])` (resp. `sn-pull-quote`) so align/anchor support survives; `wp_kses_post` the content. **Emit `.sn-pull-quote`** (the class the CSS at `critical.css` targets) — note the pattern uses `.sn-pattern-pull-quote`; keep both hooks live during transition and verify the cascade (per the inline-style→class specificity lesson).
- Existing brutalist CSS for both already lives in `assets/css/critical.css` (`.sn-pull-quote*`, `.single-post .sn-sidenote`) — no new front-end stylesheet. For in-editor styling, consider adding `critical.css` to `add_editor_style` (currently not listed) or a block.json `style` handle; front-end is already covered.
- **Keep** `patterns/sidenote.php` + `patterns/pull-quote.php` with a one-line "superseded by the block; retained as fallback/scaffold" docblock. Do not delete.

**Files:** `blocks/editor.js`, `blocks/{sidenote,pull-quote}/{block.json,render.php}`, new `inc/blocks-register.php` (`signal_noise_register_block_editor_script` on `init`, `signal_noise_register_blocks` on `init`, `signal_noise_block_category` on `block_categories_all`); `require_once` from `functions.php` after `inc/patterns.php`.

**Tests:** `tests/blocks-registry.php` (mirror `tests/patterns-registry.php`) — (1) both `block.json` parse, with correct `name`/`apiVersion: 3`/`editorScript`/`render`/`category`; (2) **behavioral** render: `render.php` output contains `sn-sidenote` (resp. `sn-pull-quote__body`/`__attribution`) when attrs set and omits the empty slots when not; (3) under stubs, `wp_register_script` called with the handle + deps including `wp-blocks`/`wp-element`/`wp-block-editor`, `register_block_type` called for both dirs, and `block_categories_all` returns a `signal-noise` category.

---

## Item 4 — Block Bindings source `signal-noise/post-field` (`inc/block-bindings.php`)

**What:** One read-only bindings source resolving `reading_time | pillar | canonical | og_title` for the current post, migrating **only** `parts/post-frontmatter.html`. The v9.10.0 footer shortcodes (`[sn_related_notes]`, `[sn_note_share]`, `[sn_updated_date]`) are untouched. **Additive primitive** — not a rip-and-replace of working shortcodes.

**Corrected framework facts:**
- Real meta keys are **`_sn_canonical_url`** / **`_sn_og_card_title`** (plugin `inc/post-settings.php`) — use the plugin getters `sn_post_settings_get_canonical_url()` / `sn_post_settings_get_og_card_title()`.
- Reading time is a **real function** `sn_get_reading_time($post)` — call it directly (guarded), no `do_shortcode`.
- A **custom** bindings source bypasses the protected-meta / `show_in_rest` gates (those live only in core's `core/post-meta` source) — it can read `_sn_*` meta freely.
- `core/paragraph` `content` **is** bindable in WP 7.0.

**Approach:**
- `register_block_bindings_source('signal-noise/post-field', ['label', 'get_value_callback' => 'sn_post_field_binding_value', 'uses_context' => ['postId','postType']])` on **`init`**. (`$allowed_source_properties` is exactly `label`/`get_value_callback`/`uses_context` — any other key triggers `_doing_it_wrong`.)
- `sn_post_field_binding_value($source_args, $block_instance, $attribute_name)`: read `$source_args['key']`; resolve post id from `$block_instance->context['postId']` with `get_post()` fallback; `switch` on key:
  - `reading_time` → `sn_get_reading_time()` (guarded) → `"%d min read"` (min-1 floor); plugin absent → `null`.
  - `pillar` → **reuse `sn_post_pillar_shortcode()`** (DRY) → its anchor or `null` when no pillar matches.
  - `canonical` → `sn_post_settings_get_canonical_url()` (guarded) → `esc_url` or `null`.
  - `og_title` → `sn_post_settings_get_og_card_title()` (guarded) → `esc_html` or `null`.
- **Return `null` when a value is genuinely absent** (keeps the block's fallback markup; avoids an empty `<p>`); only return `''` to intentionally blank.
- **Migrate `parts/post-frontmatter.html`:** bind the reading-time paragraph (`{key:'reading_time'}`) and convert the `[sn_post_pillar]` `wp:shortcode` block to a bound `core/paragraph` (`{key:'pillar'}`, with its own `sn-post-frontmatter__pillar-slot` wrapper class so the returned anchor's class isn't clobbered). **Leave** `wp:post-date`, the two `·` dividers, `[sn_updated_date]`, and `wp:post-terms` byte-identical. **Do not** add visible slots for `canonical`/`og_title` — the source resolves them (completeness + testability) but binding them would duplicate the plugin's `<head>` output.
- **Editor behavior:** PHP-only source → the bound paragraphs are **read-only in the editor** (show the source label + the static fallback inner text). Acceptable. Ship a meaningful/empty fallback (it's user-facing in the editor canvas and on a plugin-deactivated front end).
- Leave `[sn_post_pillar]` shortcode registered (other content may use it).

**Files:** new `inc/block-bindings.php` (~110 lines, named callback); edited `parts/post-frontmatter.html`; `require_once` from `functions.php` after `inc/post-frontmatter.php`.

**Tests:** `tests/block-bindings.php` (mirror `tests/post-frontmatter.php`) — registry capture (name/uses_context/callback); behavioral on `sn_post_field_binding_value()`: `reading_time` formats + min-1 floor; `pillar` returns the anchor or `null` on no-match; `canonical`/`og_title` return value or `null` on empty; unknown/empty key → `null`; no-post → `null`; `postId`-context precedence; plugin-absent degradation (document the function_exists branch coverage where un-defining mid-run isn't feasible).

---

## Item 5 — `/colophon` FSE page template (`templates/page-colophon.html`, pattern, `theme.json`)

**What:** A `/colophon` page with a custom FSE template + editable pattern: static factual credits (stack, fonts, tooling, build). Quiet footer link. **Defer `/now`.**

**Approach:**
- `templates/page-colophon.html` — header/footer parts + a constrained content area pulling an editable pattern (`patterns/colophon.php`) so the copy is editable in the Site Editor. Named `page-colophon` so it **auto-applies by template hierarchy** to a Page with slug `colophon`.
- Add `{ "name": "page-colophon", "title": "Colophon", "postTypes": ["page"] }` to `theme.json` `customTemplates` (mirrors the existing `page-about` etc.) so it's **also selectable** in the editor's Template panel — matching how the theme already exposes its other `page-*` templates.
- **Brand line:** keep within the committed **anti-self-promotion** decision — factual credits/colophon (stack, fonts, tooling, build), not a self-promotional "about me" page. No `/now` (deferred).
- A quiet footer link to `/colophon` (in `parts/footer.html`).
- **No rewrite interaction, no flush** — a normal `page` slug. **Content prerequisite:** a published Page at `/colophon` must exist for the route to resolve and the footer link to land; the theme ships the template + pattern + footer link, and the user creates the Page (same as the existing About/Services/etc. pages). Note this in the release notes / handoff as a one-time manual step.

**Files:** `templates/page-colophon.html`, `patterns/colophon.php`, edits to `theme.json` (customTemplates) + `parts/footer.html`.

**Tests:** `tests/colophon-template.php` — assert `theme.json` `customTemplates` includes the `page-colophon` entry (name/title/postTypes), the template + pattern files exist and reference the expected parts/pattern slug, and the footer part contains the colophon link.

---

## Item 6 — Reader ⌘K palette (`inc/command-palette.php`, `assets/js/command-palette.js`, `assets/css/command-palette.css`)

**What:** A front-end Notes-scoped command palette overlay — open with **⌘/Ctrl-K** or **`/`** (outside form fields) or a visible trigger button. Three actions: (1) **search notes** → navigate to `/notes/?s={query}`, (2) **jump to a recent note**, (3) **jump to a pillar page**. Vanilla JS, buildless, front-end only. Distinct from the plugin's wp-admin `@wordpress/commands` palette (never coexist on one document).

**Corrected framework facts:**
- **XSS:** the data island MUST use `wp_json_encode($data, JSON_HEX_TAG | JSON_UNESCAPED_SLASHES)` — bare `wp_json_encode` defaults to `flags=0` and won't hex-escape `<`, so a note titled `</script>` breaks out of the inline `<script>` (this is exactly what core's `WP_Scripts::localize()` guards against).
- **Search is navigation, not a query.** The theme owns the `/notes` route (`inc/page-notes-template.php` `template_redirect` funnels any `?s=` to `/notes/?s=`); the palette just `location.assign(notesUrl + '?s=' + encodeURIComponent(q))`. **No REST/client search** (would duplicate the route + add a request).
- **Accessibility = APG dialog + combobox:** `role="dialog" aria-modal="true"` + focus trap + Escape restores focus to the trigger; the input is `role="combobox"` with `aria-activedescendant` tracking the active option — **DOM focus stays on the input**, do NOT rove real focus onto options.

**Approach:**
- **Data island:** `inc/command-palette.php` builds `window.SN_CMDK = { notesUrl, recent[≤8], pillars[] }` via `wp_add_inline_script('sn-command-palette', 'window.SN_CMDK=…;', 'before')` (lands before the deferred module). Recent notes: bounded `WP_Query(post_type=post, posts_per_page=8, no_found_rows=true)` → `{t: html_entity_decode(get_the_title), u: get_permalink}`. Pillars: `sn_theme_pillar_descriptors()` (`inc/abilities-helpers.php`, `function_exists`-guarded) → `{t, u: home_url('/'.slug.'/')}`.
- **Enqueue:** site-wide named function on `wp_enqueue_scripts` (NO `is_singular` guard — palette is global); footer + `strategy: defer`; `sn_asset_ver` cache-bust; CSS depends on `sn-components` to preserve cascade.
- **Trigger:** a server-rendered `<button class="sn-cmdk-trigger" aria-haspopup="dialog" aria-controls="sn-cmdk" aria-keyshortcuts="Control+K Meta+K /">` (better a11y/discoverability than keyboard-only); fixed bottom-right (brutalist-minimal), printed on `wp_footer` (no template edit needed).
- **JS (ES5 IIFE):** global keydown opener — ⌘/Ctrl-K everywhere (`preventDefault`); `/` only when `document.activeElement` is not INPUT/TEXTAREA/SELECT/contenteditable. Build a combined items model (synthetic "search for …" row + pillars + recent); substring filter; `aria-activedescendant` + `.is-active`, Arrow/Enter/Escape; `location.assign` on activate; restore focus on close; `textContent` only (never `innerHTML`). Keep usable on touch (only hide the `⌘K` kbd hint via `@media (pointer: coarse)` — do NOT early-return on coarse pointer).
- **CSS:** preset tokens only; overlay `z-index: 100002` (header/skip-link are 9998–9999 but some critical.css overlays reach 100000–100001); active option = `blood` field / `bone` text; animate only under `@media (prefers-reduced-motion: no-preference)` (the open itself must still happen instantly under reduced motion); `.sn-cmdk-hint` ≥ 11px.

**Files:** new `inc/command-palette.php` (`sn_cmdk_build_data` + `sn_cmdk_enqueue` + a `wp_footer` trigger printer; `SN_CMDK_TEST` sentinel so the test can require without registering hooks), `assets/js/command-palette.js`, `assets/css/command-palette.css`; `require_once` from `functions.php` after `inc/assets-frontend.php`.

**Tests:** `tests/command-palette.php` (mirror `tests/notes-search.php`) — `sn_cmdk_build_data()` returns `notesUrl`/`recent`/`pillars`; `notesUrl === home_url('/notes/')`; pillars map slug→`home_url('/'+slug+'/')`; recent decodes entities (`A &amp; B` → `A & B`) and the WP_Query uses `posts_per_page=8`/`no_found_rows=true`/`post_status=publish`; and a PHP-level assertion that `wp_json_encode(['t'=>'</script>'], JSON_HEX_TAG|JSON_UNESCAPED_SLASHES)` does not contain `</script>`. Enqueue wiring (handle/deps/`before`) verified by a live smoke test (confirm `window.SN_CMDK` defined before the module + ⌘K opens the dialog).

---

## Cross-cutting

### Module load order (`functions.php`)
Append, in this order (after existing requires): `inc/feed-json.php`, `inc/feed-enrichment.php`, `inc/blocks-register.php` (after `inc/patterns.php`), `inc/block-bindings.php` (after `inc/post-frontmatter.php`), `inc/command-palette.php` (after `inc/assets-frontend.php`). Update the module-map docblock. `/colophon` is template/theme.json only (no require).

### Testing strategy
One new CLI fixture per code item: `tests/feed-json.php`, `tests/feed-enrichment.php`, `tests/blocks-registry.php`, `tests/block-bindings.php`, `tests/colophon-template.php`, `tests/command-palette.php`. Behavioral assertions (observable output), not just registration shape. Full sweep must stay green; falsify phpcs (inject a violation, confirm it reports) before claiming lint-clean.

### Versioning + CHANGELOG
Single release **v9.11.0** (batch, not per-item). Bump `style.css` `Version:` last; CHANGELOG entry at top in the Mimestream-style `### Added / Improvements / Fixed / Tests` format with per-item `(file)` refs, mirroring the v9.10.0 entry. Commit `v9.11.0: …`; annotated tag; push `HEAD:main` then tag — **gate the tag on a successful (fast-forward) main push** (a non-FF push must not leave a tag pointing at unmerged work). No auto-deploy.

### Out of scope / deferred
- **`/now` page** (deferred per the fork; `/colophon` only).
- **Pretty `/feed/json/` + `/feed/`-pretty paths** activate on the plugin's next flush; the theme ships `?feed=json`-first and must not flush.
- **Binding `canonical`/`og_title` to visible paragraphs** (resolvable by the source but intentionally unbound — would duplicate the plugin `<head>` output).
- **AI relatedness / topic clustering** (Track C flagship, not B4).
- **Reader palette live REST search** (navigation-only by design).

### Risks
- **Block validation churn** when the pillar block changes type `core/shortcode` → bound `core/paragraph` in the part — a fresh paragraph with binding metadata + fallback text won't trip validation, but verify in the editor.
- **Pull-quote class drift** (`.sn-pattern-pull-quote` vs `.sn-pull-quote`) — the block emits `.sn-pull-quote`; verify the cascade renders identically to the pattern (inline-style→class specificity lesson).
- **In-editor block styling** — `critical.css` isn't in `add_editor_style`; brutalist styling may not show in the editor canvas until added. Front-end is unaffected.
- **`sn_get_reading_time()` lazy-write** on cache miss (an `update_post_meta` during a GET) is inherited from the existing shortcode — not a new regression; don't add a second cache layer.

### Cross-references
- Grounding workflow: `wf_923523e4-1bc` (4 verified contract reports, embedded above).
- Memory: `[[reference_fse_shortcodes_resolve_via_core]]`, `[[feedback_verify_impl_contracts_behavioral_tests]]`, `[[feedback_inline_style_extraction_specificity]]`, `[[reference_phpcs_parallel_batches_not_files]]`, `[[feedback_version_constants_must_derive_from_docblock]]`, `[[reference_command_palette_js_only]]`, `[[feedback_changelog_format_mimestream]]`, `[[feedback_batch_releases_not_per_fix]]`.
- Roadmap: [2026-06-06-upgrade-opportunities-roadmap.md](2026-06-06-upgrade-opportunities-roadmap.md) (Track B4).
