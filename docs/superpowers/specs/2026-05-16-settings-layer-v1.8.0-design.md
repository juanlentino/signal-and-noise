# Settings layer — signal-and-noise-tools v1.8.0

**Date:** 2026-05-16
**Status:** Approved (brainstorm complete; writing-plans next)
**Release:**
- Plugin `signal-and-noise-tools` — `1.7.0` → **`1.8.0`** (minor — new admin UI surface + config-stored data)

## Context

Across Phases 8, 10, and 11 (plugin v1.5.0 → v1.7.0, all shipped 2026-05-16) the companion plugin gained ~700 LOC of new functionality (login URL hiding, OG/Twitter/canonical/robots emission, JSON-LD schema). Implementation took shortcuts on configurability: site-specific values like `'@juan_lentino'`, person/site name strings, `sameAs` profile URLs, the `'sn-login'` default slug, and per-route SEO copy strings were embedded as PHP literals.

Some of those values are `apply_filters()`-overridable (`sn_twitter_handle`, `sn_schema_same_as`, `sn_og_image_dimensions`) — code-configurable but not admin-configurable. The user pushed back:

> "Everything we're doing here should have a configuration in the plugin... Nothing hardcoded or whatever."

Phase 13 (the TSF + wps-hide-login cutover that lands as v2.0.0) should ship against a plugin that's actually portable to other sites. v1.8.0 is the prerequisite — it lifts the site-identity values out of PHP into wp_options + provides an admin UI to edit them.

## Goal

After v1.8.0:

1. **Zero site-identity-specific string literals** remain in plugin PHP outside of one-time activation migration code.
2. **An `Identity` tab** in the existing `Appearance → Signal & Noise` admin page lets the maintainer view + edit all site-identity settings: site name, description, person name, locale, Twitter handle, social profile URLs, OG image fallback + dimensions, login slug, and per-route SEO titles + descriptions.
3. **Live site output stays byte-identical** to v1.7.0 — a one-time activation migration seeds the JL-specific values into the new `sn_settings` wp_option so the live site keeps working without manual intervention.
4. **Plugin becomes portable** — installing v1.8.0 on a fresh site yields generic WP-built-in defaults (site name = `get_bloginfo('name')`, etc.) until the admin fills the form.
5. **Filter override layer preserved** — existing `apply_filters()` hooks (`sn_twitter_handle`, `sn_schema_same_as`, etc.) continue to work as code-level overrides on top of stored settings. Useful for `wp-config.php`-driven environment switches.

## Architecture

### Storage strategy

**Single `wp_option` row** keyed `sn_settings` storing a structured associative array. One DB row, autoloaded with the rest of the WP options on every request.

```
wp_options['sn_settings'] = [
    'identity' => [
        'site_name'        => 'Juan Lentino',
        'site_description' => 'Music Production & Creative Strategy',
        'person_name'      => 'Juan Lentino',
        'locale'           => 'en_US',
    ],
    'social' => [
        'twitter_handle' => '@juan_lentino',
        'same_as'        => [
            'https://x.com/juan_lentino',
            'https://instagram.com/juan_lentino',
            'https://linkedin.com/in/juanlentino',
        ],
    ],
    'og' => [
        'default_image_url' => 'https://juanlentino.com/wp-content/uploads/2026/02/cropped-jl_logo-min-300x300.png',
        'card_width'        => 1200,
        'card_height'       => 630,
    ],
    'login' => [
        'slug' => 'sn-login',
    ],
    'seo_copy' => [
        'home_title'             => 'Juan Lentino — Music producer & creative strategist',
        'home_description'       => 'Music producer, mix engineer, and creative strategist...',
        'notes_title'            => 'Notes — Juan Lentino',
        'notes_description'      => 'Working notes on music, AI, and the infrastructure underneath...',
        'provenance_title'       => 'Music has a verification problem...',
        'provenance_description' => "A short read on why the industry needs to prove what's human...",
    ],
]
```

The shape above represents the **live site's seeded state** after the v1.8.0 activation migration. Fresh installs get generic defaults from `sn_settings_defaults()`.

### Default behavior — hybrid

**PHP-level defaults** (returned by `sn_settings_defaults()`) are generic:

| Field | Generic default |
|---|---|
| `identity.site_name` | `get_bloginfo('name')` |
| `identity.site_description` | `get_bloginfo('description')` |
| `identity.person_name` | `get_bloginfo('name')` |
| `identity.locale` | `'en_US'` |
| `social.twitter_handle` | `''` (empty) |
| `social.same_as` | `[]` (empty array) |
| `og.default_image_url` | `''` (no fallback) |
| `og.card_width` | `1200` |
| `og.card_height` | `630` |
| `login.slug` | `'sn-login'` |
| `seo_copy.*_title` | `''` |
| `seo_copy.*_description` | `''` |

**One-time activation migration** runs once on first v1.8.0 activation, seeding the JL-specific values into `wp_options['sn_settings']`. Gated by `wp_options['sn_settings_migrated_v1']` flag — sets to `'1'` after running so subsequent activations don't re-seed (avoiding overwriting user edits).

```php
function sn_settings_seed_legacy_values() {
    if ( get_option( 'sn_settings_migrated_v1' ) ) {
        return;
    }
    $legacy = array(
        'identity' => array( 'site_name' => 'Juan Lentino', ... ),
        'social'   => array( 'twitter_handle' => '@juan_lentino', ... ),
        // etc.
    );
    update_option( 'sn_settings', $legacy );
    update_option( 'sn_settings_migrated_v1', '1' );
}
register_activation_hook( __FILE__, 'sn_settings_seed_legacy_values' );
```

For fresh installs (other sites), no `sn_settings_migrated_v1` flag means the seed runs, but it inserts the JL legacy values — which is wrong for those sites. **Refinement:** detect host first.

```php
if ( ! get_option( 'sn_settings_migrated_v1' ) ) {
    if ( parse_url( home_url(), PHP_URL_HOST ) === 'juanlentino.com' ) {
        update_option( 'sn_settings', $jl_legacy_values );
    }
    update_option( 'sn_settings_migrated_v1', '1' );
}
```

The legacy values are guarded behind a hostname check. On any other host, the migration sets only the migrated flag (so it doesn't re-attempt) and lets generic defaults take over.

This is the **only** place site-identity strings appear in code. They're effectively a one-time data backfill scoped to a single deployment.

### Settings access — `sn_setting()` helper

```php
function sn_setting( $path, $default = null ) {
    static $merged = null;
    if ( null === $merged ) {
        $stored  = get_option( 'sn_settings', array() );
        $merged  = array_replace_recursive( sn_settings_defaults(), is_array( $stored ) ? $stored : array() );
    }
    $value = $merged;
    foreach ( explode( '.', $path ) as $key ) {
        if ( ! is_array( $value ) || ! array_key_exists( $key, $value ) ) {
            return $default;
        }
        $value = $value[ $key ];
    }
    return $value;
}
```

- Static cache: one `get_option()` per request, regardless of how many `sn_setting()` calls.
- Deep merge: stored values override defaults at the leaf level (so partial settings work).
- Dot-path: `sn_setting('identity.site_name')`, `sn_setting('social.same_as')`, etc.
- `$default` parameter: fallback if path doesn't resolve (rare — should only happen if schema changes).

### Filter compat layer

Existing `apply_filters()` hooks stay. They run on top of `sn_setting()`:

```php
// Before (v1.7.0 inc/seo.php line ~127):
$twitter_handle = (string) apply_filters( 'sn_twitter_handle', '@juan_lentino' );

// After (v1.8.0):
$twitter_handle = (string) apply_filters( 'sn_twitter_handle', sn_setting( 'social.twitter_handle', '' ) );
```

This pattern: stored setting is the value; the filter is the override stack. Backward compatible with any code that already hooks these filters.

### Admin UI — `Identity` tab in existing admin page

Add `'identity'` to `$valid_tabs` and `$tab_labels` in `inc/admin-page.php` (around lines 55, 111). Insert a new tab-render block following the existing `elseif ( 'X' === $active_tab )` chain.

The Identity tab renders one `<form method="post">` with all fields, grouped under HTML sections matching the 5 categories. Submission posts back to the same admin URL with `sn_action=save_identity`. Handler block at the top of `sn_theme_options_page()` checks for that action, calls `sn_settings_save( $_POST )`, adds a success notice.

Per-field sanitization in `sn_settings_save()`:
- `identity.site_name` / `.site_description` / `.person_name` → `sanitize_text_field()`
- `identity.locale` → `sanitize_text_field()` (could validate against `available_languages()` but YAGNI for v1.8.0)
- `social.twitter_handle` → `sanitize_text_field()` (no @ enforcement — user can include or omit)
- `social.same_as` → array of `esc_url_raw()`, filter empties
- `og.default_image_url` → `esc_url_raw()`
- `og.card_width` / `.card_height` → `max( 1, (int) $value )`
- `login.slug` → `sanitize_title()` (lowercases, replaces spaces with hyphens, etc.)
- `seo_copy.*_title` → `sanitize_text_field()`
- `seo_copy.*_description` → `sanitize_textarea_field()` (allows newlines)

Form field types (HTML):
- `<input type="text">` for short strings (titles, names, handles)
- `<textarea>` for descriptions (multi-line)
- `<input type="url">` for the OG image URL + each `same_as` entry
- `<input type="number">` for OG card dimensions
- `<input type="text">` (with `pattern` attribute hint) for the login slug

`same_as` is a dynamic list — multiple `<input type="url" name="social_same_as[]">` rendered for each existing entry, plus one empty row to add a new entry. No JS-driven add/remove for v1.8.0 (YAGNI — user can edit by overwriting an existing row + adding new ones via additional empty rows that get appended via a small inline JS). The form re-renders the saved state on each page load.

## Components (file-by-file)

### 1. `inc/settings.php` (NEW, ~180 LOC)

Holds:
- `const SN_SETTINGS_OPTION = 'sn_settings';`
- `sn_settings_defaults()` — returns full schema with generic WP-built-in defaults
- `sn_setting( $path, $default = null )` — read accessor (described above)
- `sn_settings_save( $raw )` — write handler with per-field sanitization
- `sn_settings_seed_legacy_values()` — one-time activation migration, hostname-gated to juanlentino.com
- `register_activation_hook( SN_PLUGIN_FILE, 'sn_settings_seed_legacy_values' );` registration

Does NOT include the admin tab HTML — that lives in `admin-page.php` alongside the existing tab renderers.

### 2. `inc/seo.php` (MODIFY, -30/+30 LOC)

In `sn_seo_meta_for_current_view()`:

```php
// Before:
$title       = 'Juan Lentino — Music producer & creative strategist';
$description = 'Music producer, mix engineer...';

// After:
$title       = sn_setting( 'seo_copy.home_title', '' );
$description = sn_setting( 'seo_copy.home_description', '' );
```

Same pattern for `/notes` and `/provenance` routes. If a setting returns empty, fall through to post-title-based defaults (the existing logic for singular posts).

In the OG/Twitter wp_head emission block:

```php
// Before:
echo '<meta property="og:site_name" content="Juan Lentino">' . "\n";
$default_og = home_url( '/wp-content/uploads/2026/02/cropped-jl_logo-min-300x300.png' );

// After:
$site_name = sn_setting( 'identity.site_name', get_bloginfo( 'name' ) );
echo '<meta property="og:site_name" content="' . esc_attr( $site_name ) . '">' . "\n";
$default_og = sn_setting( 'og.default_image_url', '' );
```

For `og:locale`:

```php
$locale = sn_setting( 'identity.locale', 'en_US' );
echo '<meta property="og:locale" content="' . esc_attr( $locale ) . '">' . "\n";
```

For `twitter:site` / `twitter:creator`:

```php
$twitter_handle = (string) apply_filters( 'sn_twitter_handle', sn_setting( 'social.twitter_handle', '' ) );
if ( $twitter_handle ) {
    echo '<meta name="twitter:site" content="' . esc_attr( $twitter_handle ) . '">' . "\n";
    echo '<meta name="twitter:creator" content="' . esc_attr( $twitter_handle ) . '">' . "\n";
}
```

For `og:image:width` / `og:image:height`:

```php
$dims = (array) apply_filters( 'sn_og_image_dimensions', array(
    sn_setting( 'og.card_width', 1200 ),
    sn_setting( 'og.card_height', 630 ),
), $og_image );
```

### 3. `inc/seo-schema.php` (MODIFY, -10/+15 LOC)

In `sn_schema_default_same_as()`:

```php
function sn_schema_default_same_as() {
    return sn_setting( 'social.same_as', array() );
}
```

In `sn_schema_person()`:

```php
$person_name = sn_setting( 'identity.person_name', get_bloginfo( 'name' ) );
return array(
    '@type'  => 'Person',
    '@id'    => $home . '#/schema/Person',
    'name'   => $person_name,
    'url'    => $home,
    'sameAs' => array_values( $same_as ),
);
```

In `sn_schema_website()`:

```php
return array(
    '@type'       => 'WebSite',
    '@id'         => $home . '#/schema/WebSite',
    'url'         => $home,
    'name'        => sn_setting( 'identity.site_name', get_bloginfo( 'name' ) ),
    'description' => sn_setting( 'identity.site_description', get_bloginfo( 'description' ) ),
    'inLanguage'  => str_replace( '_', '-', sn_setting( 'identity.locale', 'en_US' ) ),
    'publisher'   => array( '@id' => $home . '#/schema/Person' ),
);
```

### 4. `inc/login-hide.php` (MODIFY, -3/+5 LOC)

In `sn_login_get_slug()`:

```php
function sn_login_get_slug() {
    if ( defined( 'SN_LOGIN_SLUG' ) && SN_LOGIN_SLUG ) {
        return trim( (string) SN_LOGIN_SLUG, '/' );
    }
    return sn_setting( 'login.slug', 'sn-login' );
}
```

Constant remains the ultimate override (for `wp-config.php`-based emergency overrides). Settings is the second layer. Hardcoded `'sn-login'` becomes the final fallback if both setting and constant are absent.

### 5. `inc/admin-page.php` (MODIFY, +160 LOC)

Changes:
- Add `'identity'` to `$valid_tabs` array (line ~55)
- Add `'identity' => 'Identity'` to `$tab_labels` array (line ~111)
- Add a `save_identity` action handler in the existing `$_POST['sn_action']` switch (after `'full_reset'` block)
- Add a tab-render `elseif ( 'identity' === $active_tab )` block (after the existing `'links'` block or wherever the chain ends)

The tab-render block renders the form with all 5 categories as `<h2>` sub-headings, each containing the relevant fields. ~120 LOC of form HTML.

### 6. `signal-and-noise-tools.php` (MODIFY, +1 LOC)

Add `require_once __DIR__ . '/inc/settings.php';` BEFORE the require lines for `seo.php`, `seo-schema.php`, and `login-hide.php` so `sn_setting()` is defined when those files load.

Order:
```php
require_once __DIR__ . '/inc/settings.php';  // NEW — must be first
require_once __DIR__ . '/inc/seo.php';
require_once __DIR__ . '/inc/seo-schema.php';
require_once __DIR__ . '/inc/login-hide.php';
// ... other require_once lines unchanged
```

Bump `Version: 1.7.0` → `Version: 1.8.0` and `SNT_VERSION` constant likewise.

## Acceptance criteria

1. ☐ Plugin v1.8.0 activates without PHP errors. `wp_options['sn_settings']` row exists post-activation with the JL legacy values (live site). `wp_options['sn_settings_migrated_v1']` flag exists with value `'1'`.
2. ☐ Pre-deploy state (`curl https://juanlentino.com/notes/<slug>/`) and post-deploy state of the page `<head>` are **byte-identical** for OG/Twitter/canonical/robots/JSON-LD tag values. Diff with `diff <(curl pre) <(curl post)` returns empty.
3. ☐ `Appearance → Signal & Noise → Identity` tab renders. All 5 category groups visible. All fields pre-populated with the JL legacy values (since the migration ran).
4. ☐ Editing the `social.twitter_handle` field to `@test` and submitting the form persists the change. Reloading the page shows `@test`. The next request to a singular post emits `<meta name="twitter:site" content="@test">`. Reverting via the form restores `@juan_lentino`.
5. ☐ Sanitization works:
   - Pasting `<script>alert(1)</script>` into a text field strips it (sanitize_text_field).
   - Pasting `not-a-url` into `og.default_image_url` strips it to empty (esc_url_raw).
   - Pasting `1.5` into `og.card_width` casts to `1`.
   - Pasting `My Login Slug!` into `login.slug` saves as `my-login-slug` (sanitize_title).
6. ☐ Filter overrides still work: adding `add_filter('sn_twitter_handle', fn() => '@override');` in `wp-config.php` causes pages to emit `@override` instead of the stored value.
7. ☐ No site-identity string literals remain in `inc/seo.php`, `inc/seo-schema.php`, or `inc/login-hide.php` (verified via grep — only fallback `''` or generic `get_bloginfo()` calls or schema constants like `'WebSite'`/`'Person'` remain).
8. ☐ Fresh-install simulation: on a non-juanlentino.com host, the activation migration sets the `sn_settings_migrated_v1` flag without seeding JL legacy values. Generic WP-built-in defaults take over (site name = `get_bloginfo('name')`, etc.).

## Risks + mitigations

| Risk | Mitigation |
|---|---|
| Activation migration doesn't fire (e.g., user upgrades via git checkout instead of WP plugin upgrader, so `register_activation_hook` callback never invoked) | Add a "lazy migration" check on `admin_init` — if `sn_settings_migrated_v1` flag missing, run the migration. Hostname-gated identically. Covers the SSH-deploy upgrade path. |
| Site identity output regression — e.g., a typo in the migration's seeded values | Acceptance criterion #2 (byte-identical diff) catches this in verification. |
| User edits a setting, plugin v1.8.1 ships, and v1.8.1's `sn_settings_defaults()` adds a new field — does `array_replace_recursive` merge correctly? | Yes. `array_replace_recursive` walks the defaults array, replacing leaves with stored values where present and keeping the default leaf where stored is missing. New defaults appear automatically for fields the user hasn't set. |
| Static cache in `sn_setting()` returns stale values within a single request after a save | Acceptable — the static cache is per-request scope. After `update_option()`, the next request rebuilds the cache. Form submission flow: save → redirect → fresh request → fresh cache. |
| Filter compat layer breaks if `sn_setting()` returns a non-string for a string-typed filter | Type-cast at the call site: `(string) apply_filters( 'sn_twitter_handle', sn_setting( 'social.twitter_handle', '' ) )`. Filter callbacks are responsible for returning correct types. Existing pattern. |
| `register_activation_hook` requires the **plugin entry file path** as first arg; if we register from `inc/settings.php` the path is wrong (would be the inc file, not the bootstrap). Hook registration silently fails. | Register the activation hook from `signal-and-noise-tools.php` (the bootstrap), calling the function defined in `inc/settings.php`. ~3 LOC in the bootstrap. |

## Out of scope (per Phase 11.5 brainstorm)

- **Security toggles UI** (`sn_security_block_author_enum`, `sn_security_disable_xmlrpc`, `sn_security_lock_rest_users`, `sn_security_permissions_policy` from `inc/security-headers.php`). Stay filter-overridable only. Defer to v1.9.0 if/when desired.
- **Per-post settings UI** (noindex toggle, custom meta description override per post). Defer to a separate phase (Phase 11.7 candidate) that adds a `add_meta_box` UI.
- **JS-driven add/remove for `same_as` URL list.** v1.8.0 ships with multiple `<input type="url">` rows + one empty trailing row. Power-user friendly; not pretty. v1.9.0 candidate.
- **Language switcher / multi-locale.** Single `identity.locale` field for v1.8.0. Multi-locale is hypothetical.
- **Settings export/import.** YAGNI for a single-author site.
- **Multi-site / network admin support.** Site-level settings only. The plugin runs on a single-site install.

## References

- Plugin v1.7.0 source (where the hardcoded values live): commit `7d73373` in `juanlentino/signal-and-noise-tools`.
- Phase 8 (login module) shipped in v1.5.0 (`abb5c22`).
- Phase 10 (SEO foundation) shipped in v1.6.0 (`96250eb`).
- Phase 11 (JSON-LD schema) shipped in v1.7.0 (`7d73373`).
- Phase 13 (TSF + wps-hide-login cutover) follows this phase as v2.0.0.
- Existing tabbed admin pattern: `inc/admin-page.php` (264 LOC currently; will grow to ~420 LOC after this phase).
- Project memory: [feedback_plugin_absorption_strategic_direction.md](/Users/juanlentino/.claude/projects/-Users-juanlentino-Projects-signal-and-noise/memory/feedback_plugin_absorption_strategic_direction.md) — captures the absorption roadmap.
