# The shortcodes become blocks — design (2026-09-11)

**Source:** [2026-09-11-core-capabilities-the-theme-does-not-use.md](2026-09-11-core-capabilities-the-theme-does-not-use.md), rows 1–6.
**Repo:** theme only. **Release:** 13.1.0. **Owner:** "Yes.. Go."

## What a reader / editor gets

Nothing on the page changes. In the site editor, the eleven pieces of
template furniture that today are `wp:shortcode` blocks (reading time, pillar,
updated date, provenance chip and panel, related notes, cited-by, share,
reply, theme toggle) become real blocks: visible by name, movable, styleable,
with the value shown in place instead of a shortcode string. Two of them
become bound paragraphs. The provenance chip and the closing part are
inserted by rule on every single note, so a future template cannot forget
them. `/notes/*` opens faster.

## What exists (nothing below is new computation)

- `inc/block-bindings.php`: source `signal-noise/post-field`, keys
  `reading_time | pillar | canonical | og_title`, resolving against block
  context `postId`. Registered, fixed (BND-1/BND-4), tested, **unused** since
  the v9.11.1 revert.
- One PHP renderer per shortcode: `sn_reading_time_shortcode`,
  `sn_post_pillar_shortcode` (`inc/reading-time.php` / pillar), `sn_updated_date_shortcode`
  (`inc/post-updated-date.php`), `sn_prov_chip_shortcode` / `sn_prov_panel_shortcode`
  (`inc/provenance-surface.php`), `sn_related_notes_shortcode` (`inc/related-notes.php`),
  `sn_cited_by_shortcode` (`inc/cited-by.php`), `sn_note_share_shortcode`
  (`inc/post-share.php`), `sn_note_reply_shortcode`, `sn_dark_mode_toggle_markup`
  (`inc/dark-mode.php`).
- `inc/blocks-register.php` registers the three custom blocks from
  `blocks/*/block.json`; `inc/editor-block-palette.php` curates the inserter.
- Parts `post-frontmatter.html`, `post-closing.html` (undeclared in theme.json).

## 1. Bindings

`[sn_reading_time]` (×6: `parts/post-frontmatter.html`, the card loops in
`templates/home.html` and `templates/page-notes.html`, and the two pillar cards
with `slug="…"`) and `[sn_post_pillar]` (×1) become:

```html
<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"signal-noise/post-field","args":{"key":"reading_time"}}}},"className":"…same className…","style":{…same style…}} -->
<p class="…same classes…" style="…same inline style…"></p>
<!-- /wp:paragraph -->
```

— the v9.11.0 markup (`git show a683390`), re-applied. Inside `wp:post-template`
the binding reads `postId` from context; the pillar cards pass
`"args":{"key":"reading_time","slug":"<slug>"}` and the source gains one
branch: when `slug` is present, resolve it with `get_page_by_path( $slug, OBJECT, 'post' )`
and use that id; unknown slug → `''` (the paragraph renders empty, as today's
shortcode does for a missing post). No other source change.

**Why it will not fatal this time:** the standalone sweep has no WordPress
bootstrap, so `tests/block-bindings-templates.php` pins at parse level —
every `signal-noise/post-field` binding in templates/parts names a key the
source implements (derived from the source file), each bound `<p>` inner is
empty, and no migrated shortcode string survives — and the source itself is
exercised with a stubbed `$block_instance` context in
`tests/block-bindings.php`. The v9.11.1 fatal class (a mistyped core call)
is owned by PHPStan, now a required check, and the stub-parity sweep.

`sn_updated_date` stays a block (§2): it renders a `<time datetime>` element,
and a paragraph binding carries text, not markup.

**Narrowed during implementation (2026-09-11):** three paragraphs bind, not
five, and the pillar is a block, not a binding. The two pillar-card eyebrows
on `/notes` are prefix text + figure in one `<p>` ("Pillar Essay · March 2026
· 4 min read"); a `content` binding replaces the whole paragraph, so the
prefix would vanish — they keep the plugin's `[sn_reading_time slug="…"]`,
and the `slug` arg this section proposed has no consumer, so it is not
added. `[sn_post_pillar]` sat in a `wp:shortcode` block (no wrapper); as a
bound paragraph the `<a>` would gain a `<p>` wrapper — not parity — so it
joins §2 as the ninth PHP-only block, `signal-noise/post-pillar`.

## 2. PHP-only blocks (WordPress 7.0)

Eight blocks, one file `inc/blocks-php-only.php`, registered on `init` after
`inc/blocks-register.php`:

| Block | Renderer | Context | Where it replaces a shortcode |
|---|---|---|---|
| `signal-noise/prov-chip` | `sn_prov_chip_shortcode` | `postId` | `parts/post-frontmatter.html` |
| `signal-noise/prov-panel` | `sn_prov_panel_shortcode` | `postId` | `parts/post-closing.html` |
| `signal-noise/related-notes` | `sn_related_notes_shortcode` | `postId` | `templates/single.html` |
| `signal-noise/cited-by` | `sn_cited_by_shortcode` | `postId` | `templates/single.html` |
| `signal-noise/note-share` | `sn_note_share_shortcode` | `postId` | `parts/post-closing.html` |
| `signal-noise/note-reply` | `sn_note_reply_shortcode` | `postId` | `parts/post-closing.html` |
| `signal-noise/updated-date` | `sn_updated_date_shortcode` | `postId` | `parts/post-frontmatter.html` |
| `signal-noise/theme-toggle` | `sn_dark_mode_toggle_markup` | — | `parts/header.html`, `parts/footer.html` |

Registration shape (7.0):

```php
register_block_type( 'signal-noise/prov-chip', array(
	'api_version'     => 3,
	'title'           => __( 'Provenance chip', 'signal-and-noise' ),
	'category'        => 'signal-noise',
	'icon'            => 'shield',
	'description'     => __( 'The byline verification chip for a signed note.', 'signal-and-noise' ),
	'uses_context'    => array( 'postId' ),
	'supports'        => array( 'autoRegister' => true, 'html' => false, 'multiple' => false ),
	'render_callback' => 'sn_block_render_prov_chip',
) );
```

Each `render_callback( $attributes, $content, $block )` sets up the post from
`$block->context['postId']` when the renderer reads the global post (the
shortcodes read `get_the_ID()` inside the loop today — inside a block
template the loop is set, inside `wp:post-template` the context id is the
truth; the callback wraps the renderer in `setup_postdata`/`wp_reset_postdata`
only when the context id differs from the global), then returns the renderer's
string unchanged. **Parity is the contract:** for the same post, block output
=== shortcode output, byte for byte, pinned per block.

No attributes (nothing to configure — the renderers take none), so no
auto-generated inspector UI. `multiple => false` where a second instance
makes no sense (chip, panel, share, reply, related, cited-by); the toggle
allows two (header and footer).

Palette: the eight names join `inc/editor-block-palette.php`'s allowlist.
Shortcodes: stay registered for 13.1.x (anything typed into post content
keeps working); a `_deprecated_function`-style notice is NOT added — they
are silent aliases until 13.2.0 retires them.

`[sn_reading_time]` is not one of the eight: it is the **plugin's**
shortcode (`signal-and-noise-tools`, not this theme). The theme stops
emitting it in templates — a Block Bindings source replaces it, per §1 —
but the plugin keeps registering `[sn_reading_time]` unchanged; nothing in
the plugin repo changes for this arc.

## 3. Block Hooks

```php
add_filter( 'hooked_block_types', function ( $hooked, $position, $anchor, $context ) {
	if ( ! $context instanceof WP_Block_Template || 'single' !== $context->slug ) { return $hooked; }
	if ( 'core/post-title'   === $anchor && 'after' === $position ) { $hooked[] = 'signal-noise/prov-chip'; }
	if ( 'core/post-content' === $anchor && 'after' === $position ) { $hooked[] = 'core/template-part'; /* post-closing, via hooked_block_core/template-part filter setting slug */ }
	return $hooked;
}, 10, 4 );
```

The template already places both explicitly, and WordPress records
`ignoredHookedBlocks` on save, so the hook is inert on the shipped template
and fires only on a single template that lacks them — the enforcement, never
a duplicate. Pinned: the hook returns the chip only for `single`, only after
`core/post-title`, and nothing for `home`/`page`.

## 4. Template-part areas

`theme.json.templateParts` gains `post-frontmatter` and `post-closing` with
`area: "article"`; `default_wp_template_part_areas` registers `article`
("Article — before and after a note's body"). Two entries, one filter.

## 5. Speculative loading

```php
add_filter( 'wp_speculation_rules_configuration', function ( $config ) {
	if ( ! is_array( $config ) ) { return $config; } // null = feature off; respect it
	return array( 'mode' => 'prerender', 'eagerness' => 'moderate' );
} );
add_filter( 'wp_speculation_rules_href_exclude_paths', function ( $paths ) {
	$paths[] = '/notes/?s=*'; $paths[] = '/notes/page/*';
	return $paths;
} );
```

Search and paginated results are excluded (each is a query, not a page). Not
scoped to `/notes/*` only: core has no per-path include; the exclusions carry
the intent. Pinned by shape.

## 6. Icons (7.1 Custom SVG Icons API) — last, only if the API holds

The five footer SVGs become a `signal-noise` icon collection; each
`wp:html` icon becomes `wp:icon`. **First step is verification:** read the
7.1 dev note and confirm `wp_register_icon_collection()` / the block name /
the attribute shape on the installed core (`grep -rn register_icon wp-includes`
on the Studio copy). If the API differs from the note, this section is
dropped from the arc and recorded, not improvised.

**Verified 2026-09-11 against the installed Studio core** (`~/.studio/server-files/wordpress-versions/latest`,
`$wp_version = '7.1'`): `wp-includes/icons.php` defines
`wp_register_icon_collection( $slug, $args )`, `wp_register_icon( $icon_name, $args )`,
and `wp_get_icon( $name, $args = array() )`, backed by
`WP_Icons_Registry`/`WP_Icon_Collections_Registry`; `core/icon`'s
`block.json` declares an `icon` attribute typed `string` with
`"role": "content"`, and `render_block_core_icon()` in
`wp-includes/blocks/icon.php` passes `$attributes['icon']` straight into
`wp_get_icon()` — i.e. the attribute names an icon by slug, not inline
markup. All three conditions in the decision rule hold. **Decision: YES —
Task 8 runs.**

**Dropped at implementation (2026-09-11):** the API is real, the fit is not.
`render_block_core_icon()` wraps every icon in `<div class="wp-block-icon">`
(`wp-includes/blocks/icon.php:129`), and the five footer icons sit inside
`<a aria-label title>` links that an Icon block cannot carry — the anchor
would still need the `wp:html` it has today, now with a wrapper div inside
it. Nothing gained, parity lost. The footer keeps its inline SVGs.

## Tests (all standalone, in the sweep)

- `tests/block-bindings-templates.php` — parse-level pin: every
  `signal-noise/post-field` binding in templates/parts names a key the
  source implements, each bound `<p>` inner is empty, no migrated
  shortcode string survives.
- `tests/block-bindings.php` — the real source exercised with a stubbed
  `$block_instance` context; `slug` arg resolves; unknown slug → empty.
- `tests/blocks-php-only.php` — every block registered with `autoRegister`;
  per block, render === shortcode output on the same fixture post (the
  parity pin, eight times); context id ≠ global id uses the context;
  `multiple` as declared; palette contains all eight.
- `tests/block-hooks.php` — chip after post-title in `single` only; nothing
  for other templates/positions/anchors.
- `tests/theme-json-template-parts.php` — the two areas declared; `article`
  registered.
- `tests/speculation-rules.php` — config shape; exclusions present; `null`
  passthrough.
- Existing: `tests/notes-index-row.php`, `notes-search-query.php`, `beacon.php`
  untouched; `bash tests/run.sh` green.
- Live after cut: `/notes/<a signed note>/` byte-diff of the front-matter and
  closing HTML against the pre-release capture (parity, end to end); the site
  editor lists the eight blocks; `view-source` shows `<script type="speculationrules">`
  with `prerender`.

## Out of scope

`[sn_reading_path]` (plugin-owned — see below); `[current_year]`,
`[sn_email]`, `[sn_availability]`, `[sn_build]`, `[sn_discography]`,
`[sn_music_featured]` (page-content shortcodes, not template furniture);
Interactivity-API rewrite of the toggle; retiring the shortcodes (13.2.0).

## For the plugin — nothing required; two optional follow-ups

1. **`[sn_reading_path]` is the plugin's** (`inc/reading-path-slot.php` in the
   theme is only a bridge). Making it a block would be a plugin arc:
   `signal-noise/reading-path` registered PHP-only in the plugin, the theme's
   `single.html` swapping the shortcode. Same shape as §2, other repo.
2. The plugin's `sn-apply` `block_insert` refuses unregistered block names —
   the eight new theme blocks register at `init`, so they are insertable by
   an agent the moment 13.1.0 installs. Nothing to change; worth a line in
   the plugin's CHANGELOG when 13.1.0 ships so the block list in
   `sn-site-facts{block_patterns}` is read as expected to grow.
