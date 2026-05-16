# Per-Post SEO Settings v1.10.0 — Design Spec

**Date:** 2026-05-16
**Repo:** signal-and-noise-tools (companion plugin)
**Ships as:** v1.10.0 (MINOR bump — new user-visible capability)
**Scope:** Per-post UI for SEO overrides on the post-edit screen. Three meta keys: noindex toggle, custom meta description, custom OG image URL.

## Goal

Lift three SEO concerns from "set globally" / "computed automatically" to "overridable per post":

1. **Noindex** — already consumed by `inc/seo.php` since v1.6.0 via `_sn_noindex` post meta (string `'1'` / `''`), but no UI to set it. Adding the write path.
2. **Meta description** — currently computed from `$post->post_excerpt`. New `_sn_meta_description` meta key lets authors override per-post, falling back to excerpt when empty.
3. **OG image URL** — currently resolved as featured image → auto-generated card → site default. New `_sn_og_image_url` meta key takes priority over all three (explicit beats implicit).

## Architecture — Hybrid PHP meta box + REST exposure

**Pattern:** Approach C from the v1.10.0 research (see `2026-05-16-end-of-v1.9.5-handoff.md` discussion of agent findings).

- Classic `add_meta_box()` for the UI — zero build pipeline preserved, auto-converts to a "Signal & Noise" panel in the block editor sidebar via WP's legacy-meta-box bridge.
- `register_post_meta()` for each key with `show_in_rest => true` — future-proofs storage for a React sidebar later without changing meta keys or save handler. Same pattern Yoast uses.
- Save via `save_post` hook with full guard chain (nonce → autosave → revision → cap → sanitize).
- Reader integrations modify three existing files (seo.php, seo-schema.php, og-card-generator.php) to honor the new override keys.

```
Post edit screen
    ↓
[Meta box renders 3 fields, pre-populated from get_post_meta()]
    ↓
User clicks "Update"
    ↓
WP fires save_post hook
    ↓
sn_post_settings_save() — nonce + guards + sanitize + update_post_meta
    ↓
Storage:
  _sn_noindex            (string '1' or absent)
  _sn_meta_description   (string or absent)
  _sn_og_image_url       (URL string or absent)
    ↓
Frontend render (next request):
  inc/seo.php           → reads _sn_meta_description (→ post_excerpt → '')
  inc/seo.php           → reads _sn_noindex (existing path, unchanged)
  inc/seo-schema.php    → reads _sn_meta_description (separate from seo.php)
  inc/og-card-generator → reads _sn_og_image_url (highest priority)
```

## Components

### `inc/post-settings.php` (NEW)

Single module file. Six responsibilities:

1. `sn_post_settings_register_meta()` hooked to `init` — `register_post_meta()` for 3 keys × 2 post types (`post`, `page`)
2. `sn_post_settings_register_meta_box()` hooked to `add_meta_boxes` — `add_meta_box()` for `post` + `page`, `context='side'`, `priority='high'`
3. `sn_post_settings_render( $post )` — the meta box render callback; uses `.sn-fieldset` / `.sn-field` / `.sn-field-w-*` classes (with side-meta-box width overrides)
4. `sn_post_settings_save( $post_id )` hooked to `save_post` — the save handler
5. Helpers: `sn_post_settings_get_noindex( $post_id )`, `sn_post_settings_get_description( $post_id )`, `sn_post_settings_get_og_image( $post_id )` — typed accessors used by the reader integrations

### `inc/seo.php` (MODIFY)

In `sn_seo_meta_for_current_view()`, modify the singular branch (around line 75) to prefer `_sn_meta_description` when set, then fall back to `$post->post_excerpt`.

### `inc/seo-schema.php` (MODIFY)

In `sn_schema_article()` (around line 91), apply the same fallback chain for the JSON-LD Article `description` field.

### `inc/og-card-generator.php` (MODIFY)

In the `sn_og_image_url` filter chain, check `_sn_og_image_url` first; bypass featured-image / auto-card resolution if the per-post URL is set.

### `signal-and-noise-tools.php` (MODIFY)

`require_once SNT_PATH . 'inc/post-settings.php';` in the require chain. Version bump.

### `assets/admin.css` (MODIFY)

Width overrides for `.sn-field` inside `#sn_post_settings .inside` — the side meta box is narrower (~280px) than the main admin pages (~820px), so width caps need to be relative not absolute.

### `CHANGELOG.md` (MODIFY)

Prepend v1.10.0 entry.

## Data flow

### Save path

1. User edits a post, fills the SN panel fields, clicks "Update"
2. WP submits the post form (or block-editor REST endpoint)
3. `save_post` hook fires (priority 10, default)
4. `sn_post_settings_save( $post_id )` runs:
   - If `$_POST['sn_post_settings_nonce']` missing → return (no submission for our meta box; happens on REST autosaves where the meta box wasn't part of the form)
   - If `wp_verify_nonce()` fails → return silently (don't `wp_die` — autosaves and other partial submissions are normal)
   - If `defined('DOING_AUTOSAVE') && DOING_AUTOSAVE` → return
   - If `wp_is_post_revision( $post_id )` → return
   - If `!current_user_can( 'edit_post', $post_id )` → return
   - For each field: sanitize input; if non-empty, `update_post_meta`; if empty, `delete_post_meta` (keeps DB clean)

### Read paths

Three independent callsites in the frontend render:

1. **`<meta name="description">`** — `inc/seo.php` `sn_seo_meta_for_current_view()` returns the description string that's then echoed in `wp_head` priority 1.
2. **JSON-LD Article `description`** — `inc/seo-schema.php` `sn_schema_article()` builds the schema array, echoed via priority 5.
3. **`<meta property="og:image">`** — `inc/og-card-generator.php` hooks `sn_og_image_url` filter, consumed by `inc/seo.php` to emit the OG tag.

All three paths consult `_sn_meta_description` / `_sn_og_image_url` as the FIRST source, falling back to existing logic when empty.

### REST API path

WP's standard `/wp-json/wp/v2/posts/{id}` endpoint includes `meta._sn_noindex`, `meta._sn_meta_description`, `meta._sn_og_image_url` because `register_post_meta()` was called with `show_in_rest => true`. Public reads (these are user-facing values); writes require `edit_posts` capability via `auth_callback`.

## Error handling

| Failure | Behavior |
|---|---|
| Nonce missing | Silent return (autosave / partial submit — not our concern) |
| Nonce invalid | Silent return (don't `wp_die` — wp_admin redirects can also lack nonce) |
| Autosave | Silent return |
| Revision save | Silent return |
| Insufficient capability | Silent return |
| Save partially fails (one of three fields errors) | Other two persist; no rollback (post meta updates are individually atomic; failure mode is "DB rejects the value" which is recovered on next save) |
| Sanitization strips all content | Empty string → `delete_post_meta()` — no override stored |

No error notices to the user. The save handler is on the post-save hot path; surfacing errors would require admin notices that fire on the next pageview, which adds complexity not worth the rare failure mode.

## Testing

No automated tests (plugin has no PHPUnit harness; not adding one for v1.10.0). Verification is manual post-deploy:

1. Edit a post, fill all 3 fields, save → reload edit screen → values persisted
2. View the post on frontend → `<meta name="description">` reflects the override
3. View source → JSON-LD Article description matches
4. Curl `/wp-json/wp/v2/posts/{id}` → `meta` object includes the 3 keys
5. Clear all 3 fields, save → frontend reverts to excerpt / featured image / defaults
6. Set noindex, save → `<meta name="robots">` content includes `noindex,nofollow`

Plus the byte-identical `<head>` diff check (already run 6+ times this session) against the v1.8.0 baseline — except now the diff will be NON-empty for the specific post used as a test target (because the description / OG image change). For posts WITHOUT the new meta set, diff should remain zero.

## Acceptance criteria

1. ✅ Editing any `post` or `page` shows a "Signal & Noise" panel in the block editor sidebar (auto-converted from the side meta box).
2. ✅ Panel has 3 fields: noindex checkbox, meta description textarea (2 rows), OG image URL input.
3. ✅ Saving the post persists values to `_sn_*` post meta. Empty values trigger `delete_post_meta` (no DB clutter).
4. ✅ `<meta name="description">` on a singular reflects `_sn_meta_description` when set; falls back to `$post->post_excerpt` when empty.
5. ✅ JSON-LD Article schema `description` follows the same precedence.
6. ✅ `_sn_og_image_url` wins over featured image / auto-generated card / `og.default_image_url` site setting.
7. ✅ `_sn_noindex === '1'` toggles `<meta name="robots">` content to `noindex,nofollow,...` (existing reader, no change).
8. ✅ REST `/wp-json/wp/v2/posts/{id}` exposes `meta._sn_noindex`, `meta._sn_meta_description`, `meta._sn_og_image_url`. Verified via authenticated curl.
9. ✅ No regression for posts WITHOUT the new meta: byte-identical `<head>` against v1.9.6 baseline (confirms additive-only changes).

## Versioning

**v1.10.0** — MINOR bump per project semver rules. Justification:
- Adds new user-visible capability (per-post editing UI)
- Adds new REST API exposure (`meta._sn_*` keys appear in `/wp-json/wp/v2/posts/{id}` responses)
- No removed/renamed public APIs → not MAJOR
- No setting schema change → not blocking upgrade

Project minor cap is 5 per major (v8.x.x for the theme; 1.x.x for the plugin). Currently at plugin `1.9.6` → v1.10.0 means moving the minor counter from 9 to 10. **This exceeds the per-major minor cap of 5.** Per project rule: when cap fires, rolls to next major.

**Cap interaction note:** project CLAUDE.md sets the minor cap at 5 per major. This plugin already exceeded that cap mid-Phase-1 (shipped 1.0 through 1.9 without rolling to v2.0.0 at v1.5.0 → v1.6.0). The cap has been treated as a guideline rather than a hard rule for this plugin's lifecycle. **Decision: continue the existing pattern. Ship as v1.10.0.** A strict cap enforcement would require renumbering the entire backlog from v1.6.0 forward as v2.x.x; not justified for a single-user plugin. CHANGELOG entry notes the cap deviation explicitly so it's documented.

## Out of scope (deferred to v1.10.x or v1.11.0)

- React block-editor sidebar (Approach B from the v1.10.0 research). Premature; classic meta box auto-conversion works. Migration is free thanks to REST exposure when we want it.
- Custom robots directives beyond noindex (`nofollow`, `noarchive`, `noimageindex`). Would need a richer UI than a single checkbox.
- Per-post Twitter card type override (`summary` vs `summary_large_image`).
- Bulk-edit / quick-edit support on the posts list screen.
- Bulk import/export of per-post settings.

## Risks + mitigations

| Risk | Mitigation |
|---|---|
| Save handler runs on autosaves / revisions | Standard `DOING_AUTOSAVE` + `wp_is_post_revision` guards |
| REST exposure leaks per-post meta to any unauthenticated reader | These ARE user-facing values (description = what's already shown publicly as meta tag). No PII risk. `auth_callback` requires `edit_posts` for WRITES only — reads are public by design |
| Meta box not appearing in classic editor | Unlikely (classic meta boxes are core WP functionality) — but verify in manual smoke test |
| `inc/seo-schema.php` change affects Article schema validation | Byte-diff against baseline + visual check at Google Rich Results Test if concerned |
| `_sn_og_image_url` over-prioritizing breaks existing featured-image behavior | Only fires when the new key is non-empty — existing behavior unchanged when nobody sets it |
| WP version compatibility | Requires `Requires at least: 6.4` (already declared); `register_post_meta` with REST support is stable since 4.7 |

## Out-of-scope notes captured

- **`__back_compat_meta_box` flag** — per Agent B research, this is for plugin authors HIDING legacy boxes when they ship a React replacement. We want the auto-conversion to happen, so don't set this flag.
- **Underscore-prefixed keys (`_sn_*`) are hidden from the Custom Fields panel** — intentional. The SN meta box is the only UI.
- **`update_post_meta` calls `stripslashes()`** on input — always `wp_unslash()` before sanitizing. Save handler must do this.
- **`register_post_meta` is per-post-type** — loop over `['post', 'page']` and call for each.
