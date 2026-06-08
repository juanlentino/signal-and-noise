# Settings-Hygiene Batch A — Design

**Date:** 2026-06-08
**Status:** Approved (design); ready for implementation plan.
**Scope:** Paired theme (v9.12.0) + plugin (next minor) release.
**Origin:** The v5/v10 opportunity research (`2026-06-06` roadmap follow-up) surfaced that recently-added features ship with hardcoded magic numbers and no UI. The user selected the "Core 5 + 2 cheap" bundle; the insights-advisor cadence (#6 in the audit) is **parked for later**.

## Goal

Expose, as plugin settings, the hardcoded values in recently-added theme + plugin features that a site owner would plausibly want to control — and route them through the established theme↔plugin filter contract so the theme stays standalone-safe. Also fix a real bug uncovered during the audit (the dead `$limit` param in the related-notes shortcode).

## Decision: keep the hand-rolled settings pattern (no Settings API)

Grounded in the WordPress developer handbook (Settings API, Options API, Security):

- The handbook recommends `register_setting()` / the Settings API primarily because it provides nonce verification, `manage_options` gating, sanitization, and native-admin UI **for free**. The plugin's `sn_handle_admin_post()` dispatcher already discharges every one of those (nonce → capability → page-allowlist → per-action sanitize → PRG redirect). Adopting the Settings API would mean running two parallel settings systems — strictly worse for maintainability.
- `register_setting()`'s remaining unique value (`type` / `show_in_rest`) only affects the REST `/wp/v2/settings` endpoint, which this plugin deliberately does not expose. No REST consumer exists, so it buys nothing here.
- The handbook **endorses** the project's single-nested-`sn_settings`-array model: *"storing [related options] as an array can have a positive impact on overall performance … a single transaction is ideal."* (Options API handbook.)

**Verdict:** extend the existing pattern. Add the handbook's per-setting obligations (type-correct sanitize on input, escape on output) — which the project already does for its existing fields.

## Scope: 7 settings (defaults = current values)

**Critical invariant:** every default equals the value hardcoded today, so installing the update changes nothing until the owner opts in. No behavioral change on upgrade.

| # | Setting | Default (= today) | Type | Sanitizer / validator |
|---|---|---|---|---|
| 1 | Related-notes count | `3` | int | `max(1, min(12, (int)…))` — **also fixes the dead-param bug** (see below) |
| 2 | Palette recent count | `8` | int | `max(0, min(20, (int)…))` (0 = hide recents) |
| 3 | Palette enabled | `true` | bool | `! empty($post[...])` (checkbox) |
| 4 | JSON-feed items | `20` | int | `max(1, min(50, (int)…))` |
| 5 | Updated-badge threshold (days) | `14` | int | `max(1, min(90, (int)…))` |
| 6 | Reading-time WPM | `225` | int | `max(100, min(400, (int)…))` |
| 7 | AI model | `claude-sonnet-4-6` (current pin) | select | **allowlist** `in_array($v, $allowed, true)` else keep prior/default |

### The related-notes bug (fixed as part of #1)

`inc/related-notes.php` defines `sn_related_notes_query($post_id, $limit = 3)`, but the shortcode calls it at `:141` with a **literal `3`** (`sn_related_notes_query($post_id, 3)`), so the `$limit` parameter is dead today. Fix: call `sn_related_notes_query($post_id, apply_filters('sn_related_count', 3))` — this both wires the new setting and restores the parameter.

## Storage + admin IA

- **Storage:** new top-level `theme` subtree inside the existing `sn_settings` option (parallel to `og` / `seo_copy`). Non-secret render config → autoloaded inside `sn_settings`, read via the existing batched-`get_option` + static-cached `sn_setting('theme.x', $default)`. **Not** standalone option rows.
- **Defaults:** add the `theme` subtree to `sn_settings_defaults()` so a missing key reads as the sane fallback (the hand-rolled read path has no `register_setting(default)` to lean on).
- **Admin IA:** new **"Front-End" sub-tab under the existing Tools top-tab** (`sn_admin_top_tabs()` in `inc/admin-tabs-data.php`). One bundled form, one save button, native-WP styling (no brutalist treatment in wp-admin). New form file `inc/admin-forms/front-end.php` modeled on `identity-and-seo.php`. (Verify the Tools sub-tab count still reads cleanly under desktop-mode's horizontal submenu — memory `feedback_desktop_mode_horizontal_submenu_warning`.)
- **Save path — avoid the whole-option-replace hazard:** the new form uses its own `sn_action=save_theme` and writes via `sn_setting_update()` (sparse, cache-busting, doesn't touch sibling subtrees). Do **not** bolt these onto the `save_identity` form, whose `sn_settings_save()` does a whole-option replace and already needs manual `login`/`audit` subtree preservation.

### Files (plugin)
- `inc/settings.php` — add `theme` subtree to `sn_settings_defaults()`.
- `inc/admin-post-actions.php` — `sn_handle_save_theme()` + `sn_theme_ai_models()` (the allowlist, single source for render + validate).
- `inc/admin-post-handler.php` — register `'save_theme' => 'sn_handle_save_theme'` in `sn_admin_post_handlers()`.
- `inc/admin-tabs-data.php` — add `front-end` sub-tab to Tools.
- `inc/admin-forms/front-end.php` — **new** bundled form (nonce + `sn_action=save_theme` + 7 fields + savebar).
- `inc/admin-flash-messages.php` — `theme_saved` / `theme_unchanged` flash strings.
- Sub-tab render dispatch (where Cloudflare/audit sub-tabs route) — wire the new form.
- `inc/theme-filters.php` — **new**; `add_filter` callbacks supplying the configured value to the theme/plugin filters.

## Wiring: three categories

**1 — New theme filters ← plugin (standalone-safe).** Theme calls `apply_filters('sn_x', <default>)` at the source line; plugin `add_filter` returns the clamped `sn_setting()` value. If the plugin is inactive, the filter never registers and the theme keeps its own default. Theme default **must equal** the plugin default.

| Setting | New theme filter | Theme source site | Plugin callback returns |
|---|---|---|---|
| #1 related count | `sn_related_count` | `inc/related-notes.php:141` (call site) | `max(1,(int)sn_setting('theme.related_count',3))` |
| #2 palette recent count | `sn_palette_recent_count` | `inc/command-palette.php:30` (`posts_per_page`) | `max(0,(int)sn_setting('theme.palette_recent_count',8))` |
| #3 palette enabled | `sn_palette_enabled` | enqueue gate `inc/command-palette.php:90` + body-class | `(bool)sn_setting('theme.palette_enabled',true)` |
| #4 JSON-feed items | `sn_json_feed_items` | `inc/feed-json.php:29` (`posts_per_page`) | `max(1,(int)sn_setting('theme.json_feed_items',20))` |

**2 — Feed EXISTING filters (no new theme code).**

| Setting | Existing filter | Owner | Plugin callback |
|---|---|---|---|
| #5 updated threshold | `sn_updated_date_threshold_days` (theme, `post-updated-date.php:50`) | theme | `add_filter` → `(int)sn_setting('theme.updated_threshold_days',14)` |
| #6 reading WPM | `sn_reading_time_wpm` (plugin, `reading-time.php:40`) | plugin | `add_filter` → `(int)sn_setting('theme.reading_wpm',225)` |

**3 — Plugin-internal (no theme involvement).**

- #7 AI model: the theme never calls the AI client; the plugin does (alt-text, meta-desc, excerpt, OG-title, drift, orphan, insights). The setting feeds the plugin's **existing model-preference mechanism** (`snt_ai_model_preference` filter, currently pinning `claude-sonnet-4-6` at `inc/ai-bootstrap.php`). The setting supplies the value to that filter; the filter remains the code-level override.

### Palette enable/disable mechanism (v9.11.3 wrinkle)

The reader command-palette trigger is now **static HTML in the footer template** (v9.11.3), not PHP-injected — so a PHP gate alone can't remove it. When `sn_palette_enabled` is false:
- (a) **Skip the enqueue** — `sn_cmdk_enqueue()` early-returns when `apply_filters('sn_palette_enabled', true)` is false, so no palette JS/CSS loads.
- (b) **Hide the static trigger** — add `body_class('sn-cmdk-off')` (theme `body_class` filter, gated on the same value) and a one-line `.sn-cmdk-off .sn-cmdk-trigger { display: none; }` rule in an **always-loaded** stylesheet (`critical.css`), so the footer button disappears even though the palette CSS isn't loaded.

No inline styles; the kill-switch fully removes the surface (it caused the v9.11.1–v9.11.3 incidents, so a clean off-switch is warranted).

### Theme source sites + matching defaults

| Theme site | Today | Becomes |
|---|---|---|
| `related-notes.php:141` | `sn_related_notes_query($post_id, 3)` | `…($post_id, apply_filters('sn_related_count', 3))` |
| `command-palette.php:30` | `'posts_per_page' => 8` | `'posts_per_page' => apply_filters('sn_palette_recent_count', 8)` |
| `command-palette.php:65/90` | always enqueues | early-return + body-class on `apply_filters('sn_palette_enabled', true)` |
| `feed-json.php:29` | `'posts_per_page' => 20` | `'posts_per_page' => apply_filters('sn_json_feed_items', 20)` |
| `post-updated-date.php:50` | already `apply_filters('sn_updated_date_threshold_days', 14)` | unchanged (plugin supplies value) |

## Security (per WordPress security handbook)

Order in `sn_handle_save_theme()`: nonce → capability → sanitize → persist (already enforced upstream by `sn_handle_admin_post()`; restated for the new handler).

- **Capability:** `current_user_can('manage_options')` (already gated in the dispatcher at `admin-post-handler.php:62`; the cap is independent of the nonce — *"nonces should never be relied on for authorization"*).
- **Nonce:** `wp_nonce_field('sn_theme_options_nonce')` in the form; `check_admin_referer(...)` in the dispatcher (already present).
- **Sanitize on input (type-correct):** ints via `(int)` + explicit **range clamp** (`absint` alone allows 0 and unbounded — clamp it); bool via `! empty()` (unchecked checkbox = missing key — handle explicitly); the AI-model select via **allowlist validation** `in_array($v, array_keys(sn_theme_ai_models()), true)` (handbook: *"validation is preferred over sanitization"* for closed sets), not free-text.
- **Unslash** new `$_POST` reads (`wp_unslash`) before sanitizing — use the audit/login handler style, not the legacy `sn_settings_save()` (which intentionally doesn't unslash old fields).
- **Escape on output:** `esc_attr()` on `value=`; `selected($current, $id, false)` on the model `<option>`; `checked($enabled, true, false)` on the palette checkbox; `esc_html()` on labels. Escape **late**, at the echo.
- **Defense-in-depth at the filter boundary:** plugin filter callbacks clamp/cast on the way out, so a hand-edited corrupt option can't reach the theme. The theme still escapes the filtered value at its own echo site.

## Testing (TDD)

Standalone fixture tests (CLI, stub WP), per the existing `tests/*.php` harness. Write failing tests first.

**Plugin tests** (new `tests/front-end-settings.php` or extend an existing settings test):
- Each setting clamps correctly: below-min → min, above-max → max, non-numeric → default. (`related_count` 0→1, 99→12; `palette_recent_count` -1→0, 99→20; `json_feed_items` 0→1, 999→50; `updated_threshold_days`, `reading_wpm` bounds.)
- AI-model allowlist: a valid id persists; an off-list id falls back to the prior/default (never stored).
- Palette-enabled bool: present→true, absent→false.
- `sn_theme_ai_models()` keys are non-empty and the default is a member.
- The five `add_filter` callbacks return the clamped/typed value and fall back to the supplied default when the option/subtree is missing.

**Theme tests** (extend `tests/related-notes.php`, `tests/command-palette.php`, `tests/feed-json.php`):
- **Related-notes bug regression:** with `sn_related_count` filtered to 5, the shortcode requests 5 (proves the call-site param is live, not the dead literal `3`). Default (no filter) → 3.
- Palette recent count honors `sn_palette_recent_count`.
- Palette disabled (`sn_palette_enabled` → false): `sn_cmdk_enqueue()` does not enqueue; the body class is emitted.
- JSON feed honors `sn_json_feed_items`.

Falsification discipline (memory `feedback_falsification_test_before_trusting_clean`): inject a violating value and confirm the test reports it before trusting green; stubs must model real WP function names.

## Release

- **Paired:** theme **v9.12.0** (filters + palette gate + bug fix + tests) and plugin next-minor (settings UI + handler + filter callbacks + tests). Bump both; CHANGELOG (Mimestream categories) in each; tag each.
- **Defaults preserve current behavior**, so the two repos can install independently without coordination risk (the theme's `apply_filters` defaults match the plugin's, so order of install is irrelevant).

## Open items to confirm during implementation

1. **AI model ids + mechanism:** confirm the exact current Anthropic model ids and the plugin's pin mechanism against `inc/ai-bootstrap.php` on the **current** plugin main and the `claude-api` skill (current ids: Opus 4.8 `claude-opus-4-8`, Sonnet 4.6 `claude-sonnet-4-6`, Haiku 4.5 `claude-haiku-4-5-20251001`; verify which the wp-ai-client provider actually exposes). Build `sn_theme_ai_models()` from the verified set.
2. **Plugin checkout state:** the local non-worktree plugin checkout reads v4.7.0 while shipped handoffs reference ~v4.11.0 — fetch and work against current plugin `origin/main` before any plugin edit (mirror the theme fast-forward done this session).
3. **Reading-time filter name/owner:** confirm `sn_reading_time_wpm` exists at `inc/reading-time.php` on current plugin main (read source before wiring — memory `feedback_verify_impl_contracts_behavioral_tests`).
4. **Desktop-mode submenu count:** after adding the Tools "Front-End" sub-tab, verify the horizontal submenu still reads cleanly.
