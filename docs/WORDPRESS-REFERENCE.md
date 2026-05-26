# WordPress Reference — Signal & Noise

Project-specific WordPress knowledge for this theme: the gotchas we've hit, the architectural decisions baked in, and pointers into the broader documentation. This is **not** a generic WordPress tutorial — it's the cheatsheet for working on **this codebase** specifically.

For broader WordPress knowledge:

- **Block markup, attribute schemas, validation rules**: invoke the `gutenberg-block-authoring` skill (auto-loads on any block-editing task). 3157 lines, covers all core blocks with their attributes, save-function contracts, and validation triggers.
- **FSE theme architecture, theme.json, patterns**: invoke the `wordpress-block-theming` skill (bundled in the `cowork-create-wp-site` plugin, loads after restart). Covers theme.json, template parts, pattern registration, functions.php conventions.
- **WordPress core source**: when integrating with a core primitive (block render callbacks, filters, hooks), **read the source first**. `wp-includes/blocks/<name>.php` for block render callbacks. `wp-includes/option.php` for transient/option/site_transient APIs. `wp-admin/includes/upgrade.php` for `dbDelta`. `wp-includes/cron.php` for WP-Cron. The `https://raw.githubusercontent.com/WordPress/WordPress/master/...` URL pattern works for fetching.

What follows is everything else — the patterns specific to this theme that no skill covers, and the upstream gotchas we've already paid for.

---

## 1. Gutenberg gotchas we've actually hit

### 1.1 The `wp:social-link` relative-URL trap

**The bug:** `wp-includes/blocks/social-link.php`'s `render_block_core_social_link()` callback contains:

```php
if ( ! parse_url( $url, PHP_URL_SCHEME ) && ! str_starts_with( $url, '//' ) && ! str_starts_with( $url, '#' ) ) {
    $url = 'https://' . $url;
}
```

The comment claims "not a relative link" but the check only recognizes protocol-relative (`//`) and fragment (`#`) URLs. **Path-relative URLs (`/foo`) fall through** and get `https://` prepended → `https:///foo` (three slashes, empty host). Chrome normalizes to `https://foo/...` and routes to a non-existent server.

**Our fix lives at** [inc/frontend-filters.php](../inc/frontend-filters.php) — a `render_block_data` filter that swaps any path-relative URL on a `core/social-link` block for `home_url($path)` *before* core's render runs. So core sees a complete URL and skips the broken branch.

**If you add a new social-link with a relative URL anywhere in this theme, it just works** — the filter catches it. Don't hardcode the host. The filter is the host-aware contract.

When WP core ships a fix (adds `! str_starts_with( $url, '/' )` to the check), this filter becomes a no-op. Comment in `inc/frontend-filters.php` documents when it can be retired.

### 1.2 FSE template parts don't execute PHP

`parts/*.html` and `templates/*.html` are **pure HTML**. PHP tags are stripped, shortcodes are NOT processed. This means:

- ❌ `<?php bloginfo('rss2_url'); ?>` — silently dropped
- ❌ `home_url('/')` — not callable
- ❌ Even `[some_shortcode]` won't run inside arbitrary positions; only specific block types (like the post-content block) trigger shortcode processing

**The pattern this theme uses:** hardcode URLs as paths (`/notes/feed/`, `/feed/`, `/about/`) and rely on the social-link filter (above) or block-block defaults to resolve them at render. For URLs in `core/html` blocks or paragraph hrefs, paths are fine — the browser resolves them against the current host.

### 1.3 Avoid `wp:html` when a core block exists

`<!-- wp:html -->` is an opaque blob to the block editor. Users can't select, style, or rearrange individual elements inside it. The gutenberg-block-authoring skill flags this with the rule: *"If you find yourself reaching for `wp:html`, stop and decompose the content into the correct core blocks with `className` attributes and CSS instead."*

We tripped on this in v8.0.0 (used `wp:html` for an inline SVG link), corrected in the v8.0.0 brainstorming pass to `wp:social-link` with service `feed`. Stick with the post-brainstorming pattern.

### 1.4 No decorative HTML comments

Only Gutenberg block delimiter comments are valid:

- ✓ `<!-- wp:group -->`, `<!-- /wp:group -->`, `<!-- wp:social-link {...} /-->`
- ✗ `<!-- HERO SECTION -->`, `<!-- TODO: ... -->`, any other comment

Non-delimiter comments break the block editor. If you need section labeling, use `className` and CSS comments in `style.css` instead.

### 1.5 Other validation triggers worth knowing

The gutenberg-block-authoring skill has the full list. Highlights of what's specific or surprising:

- **`<p>` NEVER gets `wp-block-paragraph` class** — the paragraph block's save function doesn't add it. Adding it causes mass block recovery.
- **Custom hex text color uses `style.color.text`, NOT `style.typography.color`** — the latter path doesn't exist in Gutenberg.
- **`level` on headings must be an integer**, not a string: `"level":3` not `"level":"3"`.
- **`<figure>` is the required wrapper for `wp:image`** with class `wp-block-image`; `<figcaption>` gets `wp-element-caption`.
- **`wp:column` outside `wp:columns` is a validation error.**

---

## 2. WP-Cron + the SWR pattern this theme uses

### 2.1 The architectural choice

Several subsystems in this theme need to call out to slow external services (GitHub for the updater, Plausible for stats). All of them follow the same **Stale-While-Revalidate** pattern, refactored in v7.2.6 and v7.3.1:

1. **Read path is read-only.** A page-render function (filter, dashboard widget) reads a transient. Never calls `wp_remote_get`. Returns the cached value, or an empty payload if cache is cold.
2. **Refresh runs in a `spawn_cron()` loopback.** An `admin_init` warmer checks the cache's embedded `fetched` timestamp. If stale, schedules a single WP-Cron event. WP fires `spawn_cron()` at `wp_loaded`, which dispatches a non-blocking loopback HTTP request to `wp-cron.php`. The actual `wp_remote_get` happens in *that* parallel process while the admin response is already on its way to the browser.
3. **Long retention, short freshness window.** Cache survives for `DAY_IN_SECONDS` so stale data is always visible during outages. Freshness is gated by the embedded `fetched` field (seconds since last successful fetch), NOT the WordPress transient TTL.

**Reference implementations:**

- [inc/plausible-api.php](../inc/plausible-api.php) — full SWR pattern with batch + realtime accessors. Read the file header for the architectural commentary.
- [inc/updater.php](../inc/updater.php) — GitHub branch HEAD + remote Version + revcount, all in one refresh function.

### 2.2 The freshness gate constants

As of v8.0.5 in `inc/updater.php`:

```php
const SN_UPDATER_FRESHNESS        = 30;                       // seconds — schedules refresh when cache older
const SN_UPDATER_RETENTION        = DAY_IN_SECONDS;           // long survival, stale data still visible
const SN_UPDATER_RETENTION_SHORT  = 2 * MINUTE_IN_SECONDS;    // empty-sentinel TTL after failed fetch
```

Decision rule: when adding a new SWR subsystem, mirror this layout. Pick `FRESHNESS` based on how stale the maintainer can tolerate before noticing. 30s feels instant. 5 minutes is "slow."

### 2.3 The hooks you need to know

| Hook | What it does | Lives in |
| --- | --- | --- |
| `admin_init` | Fires on every admin pageview. Used as a warmer for SWR — checks cache age, schedules background refresh. Capability-gate before scheduling. | [inc/updater.php:367](../inc/updater.php:367), [inc/plausible-api.php:323](../inc/plausible-api.php:323) |
| `wp_schedule_single_event(time(), HOOK)` | Queues a one-shot cron event. `wp_next_scheduled(HOOK)` guards against stacking. | Same files |
| `spawn_cron()` | Implicit — fires at `wp_loaded`. Dispatches loopback HTTP request to `wp-cron.php`. Non-blocking (timeout 0.01). | WP core; not directly called |
| Cron callback (`add_action('your_hook', 'callback')`) | Runs in the loopback. Makes the actual `wp_remote_get`. | Same files |

### 2.4 WP-Core's own update_themes gate

`_maybe_update_themes()` in WP core skips its own update check if the `update_themes` site transient was refreshed within the last 7200 seconds (2 hours). This means a fresh SN cache holding a new SHA goes nowhere visible until either (a) the gate expires, (b) `?force-check=1` is hit, or (c) **something deletes the `update_themes` site transient**.

Our v8.0.1 patch in [inc/updater.php](../inc/updater.php) does option (c) — when `sn_updater_refresh_cache` detects a SHA change, it calls `delete_site_transient('update_themes')` so WP re-runs the filter on the next admin pageview. Safe to do because the refresh runs in a spawn_cron loopback, not during a page render (the original `fbd6b30` race is unreachable from this code path).

---

## 3. Transients vs Options vs Site-Transients

Pick the right primitive — the difference matters for cache invalidation, autoload size, and multisite behavior.

| Primitive | API | TTL | Scope | Autoload | When to use |
| --- | --- | --- | --- | --- | --- |
| **Option** | `get_option` / `update_option` / `delete_option` | Permanent | Per-site | Yes by default (load on every request); pass `false` as third arg to opt out | Configuration that needs to survive forever |
| **Transient** | `get_transient` / `set_transient` / `delete_transient` | Optional TTL (deletes after) | Per-site | No | Cached computed values, throwaway state |
| **Site-transient** | `get_site_transient` / `set_site_transient` / `delete_site_transient` | Optional TTL | **Network-wide** on multisite, same as transient on single-site | No | WP-Core uses these for theme/plugin update transients; rarely needed in our code |

**Autoload trap:** every autoloaded option is loaded into PHP memory on every request. Don't store large structured data as an autoloaded option. The RSS tracker's `sn_rss_tracker_settings` option is autoloaded (default) because it's small and read on every feed hit; for anything larger, pass `false`:

```php
update_option( 'large_data_blob', $data, false );  // third arg = autoload
```

**Existing pattern reference:** [inc/plausible-api.php](../inc/plausible-api.php) uses transients for cache data. [inc/updater.php](../inc/updater.php) uses `sn_github_local_sha` as a permanent option (single small string) and `sn_github_branch_*` as transients (cache data).

---

## 4. dbDelta — install + migrate tables

`dbDelta()` lives in `wp-admin/includes/upgrade.php`. It's idempotent: pass a `CREATE TABLE` statement, it diffs against the actual schema and applies whatever ALTER statements are needed.

**Gotchas:**

1. **Whitespace is load-bearing.** dbDelta parses your SQL with regex. The function name MUST have a single space after `CREATE TABLE` (not multiple). Indices need two spaces after `KEY`. The parser is famously fragile — when in doubt, copy the format from an existing working `dbDelta` call.

2. **Always `require_once ABSPATH . 'wp-admin/includes/upgrade.php';` first.** dbDelta isn't loaded on front-end requests by default.

3. **Version-gate the install.** dbDelta is idempotent but not free — running it on every request is wasteful. Store a version option, check it before calling:

   ```php
   if ( get_option( 'mything_db_version' ) !== MYTHING_DB_VERSION ) {
       mything_install();
   }
   ```

4. **Hook it on `init`, not `admin_init`,** if anything front-end depends on the table existing. The RSS tracker module needs this because feed-request inserts arrive before any admin pageview on a cold install.

**Reference implementation:** `sn_rss_tracker_install()` + `sn_rss_tracker_maybe_install()` in the [signal-and-noise-tools companion plugin's inc/rss-plausible-tracker.php](https://github.com/juanlentino/signal-and-noise-tools/blob/main/inc/rss-plausible-tracker.php). Read the file header for the cold-install race rationale. (Migrated from theme's `mu-plugins/` directory in v8.2.1 / Tools v1.1.0.)

---

## 5. MU plugins vs regular plugins

Same code, different lifecycle. The RSS tracker was originally an MU plugin (v8.0.0–v8.2.0); migrated to the companion plugin as a regular module in v8.2.1 / Tools v1.1.0. Either path is viable; the table below documents the trade-offs.

| Aspect | MU plugin (`wp-content/mu-plugins/`) | Regular plugin (`wp-content/plugins/`) |
| --- | --- | --- |
| Activation | Loads on every request — file presence = active | Requires explicit Activate click |
| `register_activation_hook` | **No-op** — never fires | Fires on Activate click |
| `register_deactivation_hook` | **No-op** | Fires on Deactivate click |
| Cron scheduling | Must use `wp_next_scheduled` guard + `wp_schedule_event` in an early hook (`init` or similar) | Schedule in activation hook |
| Cleanup on removal | None — deleting the file orphans any cron jobs / options it created. Document manual cleanup in the README. | Deactivation hook can `wp_clear_scheduled_hook` etc. |
| Compatibility | A single file supports both paths — `register_activation_hook` and `register_deactivation_hook` are no-ops on the MU path, so calling them is safe in both contexts. | Same |

**When to use MU plugin:** infrastructure that must survive theme switches and can never be accidentally deactivated. Subscriber metrics (RSS tracker), security hardening, must-always-run instrumentation.

**When to use regular plugin:** anything the user might reasonably want to turn off. Optional features. Anything that should be auditable from the Plugins admin page.

---

## 6. `wp_remote_post` non-blocking pattern

For fire-and-forget HTTP (analytics events, webhook fanouts), use the non-blocking pattern. The feed-response should never wait on analytics.

```php
wp_remote_post( $endpoint, array(
    'timeout'  => 2,        // connection cap; we never wait for response anyway
    'blocking' => false,    // don't read the response
    'headers'  => array( /* ... */ ),
    'body'     => wp_json_encode( /* payload */ ),
) );
```

**Reference:** `sn_rss_tracker_send_plausible()` in the [companion plugin's inc/rss-plausible-tracker.php](https://github.com/juanlentino/signal-and-noise-tools/blob/main/inc/rss-plausible-tracker.php). The DB log row is the durable fallback when the non-blocking POST fails silently.

**When to also log on failure:** if the non-blocking POST is the only durable channel (no DB fallback), `error_log` the failure. If there IS a DB fallback (like in our case), the silent fail is acceptable design.

---

## 7. Sanitization + escaping

Decision table for the most common contexts:

| Output context | Function | Notes |
| --- | --- | --- |
| HTML text | `esc_html()` | Most common; use anywhere user/external data lands in HTML |
| HTML attribute value | `esc_attr()` | For `value=""`, `class=""`, `id=""`, etc. |
| URL in `href=""` or `src=""` | `esc_url()` | Filters bad schemes, normalizes |
| URL for storage in DB | `esc_url_raw()` | Like `esc_url` but suitable for DB write (no HTML entity encoding) |
| Plain text input from form | `sanitize_text_field()` | Strips tags, normalizes whitespace |
| Email | `sanitize_email()` | |
| Integer | `(int)` cast or `absint()` | Stronger guarantees than `intval()` |
| JS variable | `wp_json_encode()` | For inline scripts; respects WP's JSON encoding settings |
| Filename | `sanitize_file_name()` | |

**Always use `wp_unslash()` on `$_POST` / `$_GET` / `$_REQUEST` / `$_COOKIE` reads** before passing to sanitize functions. WordPress historically slash-escapes superglobals (`magic_quotes` legacy); `wp_unslash` reverses it.

```php
$value = isset( $_POST['field'] )
    ? sanitize_text_field( wp_unslash( $_POST['field'] ) )
    : '';
```

**Capability check + nonce verify pattern:**

```php
function my_handler() {
    if ( empty( $_POST['my_action'] ) ) {
        return;
    }
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    // Soft fail vs hard fail. check_admin_referer() dies on bad nonce
    // (wall-of-text error page). wp_verify_nonce returns bool — gives
    // you graceful handling. The project convention is the soft path.
    if ( ! isset( $_POST['_wpnonce'] )
         || ! wp_verify_nonce( wp_unslash( $_POST['_wpnonce'] ), 'my_nonce_action' ) ) {
        return;
    }
    // ... process ...
}
add_action( 'admin_init', 'my_handler' );
```

**Reference:** `sn_rss_tracker_handle_form()` in the [companion plugin's inc/rss-plausible-tracker.php](https://github.com/juanlentino/signal-and-noise-tools/blob/main/inc/rss-plausible-tracker.php). Has the soft nonce pattern, the three-state flash on `update_option` returns, and the explicit-fail-vs-success distinction on `$wpdb->query`.

---

## 8. Filter timing — `render_block_data` vs `render_block`

The block render pipeline fires three filters in order:

1. **`render_block_data`** — runs **before** the block's render callback. Receives the parsed block (with its attributes). Mutating `$parsed_block['attrs']` here changes what the render callback sees.
2. **`render_block`** — runs **after** the render callback. Receives the rendered HTML string. Mutating here means string surgery on rendered output.
3. **`pre_render_block`** — early short-circuit. Returning a string bypasses the entire render pipeline. Use sparingly.

**Decision rule for fixing block-render bugs:**

- Need to fix the input? → `render_block_data` (cleaner; works at the attribute level)
- Need to fix the output? → `render_block` (string regex; brittle but sometimes the only option)
- Need to completely replace the rendering? → `pre_render_block`

**Our v8.0.4 filter chose `render_block_data`** because the fix was at the attribute level — convert a path-relative URL to absolute before WP core's render even runs. See [inc/frontend-filters.php](../inc/frontend-filters.php) for the implementation.

---

## 9. `admin_init` hook priority

When you have multiple `admin_init` handlers across the codebase, priority matters. Lower numbers run first.

Priorities currently in use across `inc/`:

| Hook | Priority | What |
| --- | --- | --- |
| `admin_init` | 5 | Updater + Plausible cache warmers (schedule BEFORE `wp_loaded` so spawn_cron picks up the same request) |
| `admin_init` | 10 (default) | Form handlers, install gates, maintenance actions |
| `template_redirect` | 1 | RSS feed capture (run before caching plugins shortcircuit) |

**Rule:** if your hook needs to schedule a cron event that fires in the SAME request, hook at priority 5. Anything default-priority that does heavy work risks blocking the admin response.

---

## 10. The theme + companion plugin split (v8.2.0 → v8.4.0 complete; ongoing contract surface)

This theme ships with a companion plugin that holds all operational tooling. Understand both before touching `inc/template-maintenance.php` or any of the WP hook contracts listed below.

### 10.0 The theme + companion plugin split (Phases 1–3 complete as of v8.4.0 / Tools v1.3.0)

The theme is presentation; the companion plugin [`signal-and-noise-tools`](https://github.com/juanlentino/signal-and-noise-tools) holds operational tooling. They communicate via 4 WP hooks (3 since v8.4.0; +1 in v9.1.6/plugin v4.1.1). **The split is complete as of v8.4.0 / Tools v1.3.0 — no further migrations are planned.** See `docs/superpowers/specs/2026-05-15-companion-plugin-phase-1-design.md` and successors for the migration history.

**Modules currently in plugin:**
- *Phase 1 moves (v8.2.0 / Tools v1.0.0):* `seo.php`, `security-headers.php`, `cloudflare-purge.php`, `plausible-api.php`, `plausible-admin.php`, `plausible-widget.php`, `admin-bar.php`, `admin-page.php`, `rest-api.php`.
- *Early Phase 4 slice (v8.2.1 / Tools v1.1.0):* `rss-plausible-tracker.php` (was theme's `mu-plugins/rss-plausible-tracker.php`).
- *Phase 3 moves (v8.4.0 / Tools v1.3.0):* `og-card-generator.php` (was theme's `og-image.php`), `reading-time.php`, plus a 3-way split of the original `notes-and-provenance.php` into `content-surfaces.php` + `content-migrations.php` + `content-rendering-helpers.php`. Theme's `seed-content/` HTML moved alongside the migrations.

**Modules still in theme:** `setup.php`, `assets-frontend.php`, `frontend-filters.php`, `og-fonts.php`, `template-maintenance.php`, `page-notes-template.php`, `page-notes-render.php`, `patterns.php`, `wp-update-integration.php` (added v8.5.0), `wp-update-git-preservation.php` (added v8.5.2), `abilities-registration.php` (added v9.1.0). All presentation, theme-update visibility, or theme-specific defenses.

**Phase 4 is empty** — the only file it was scheduled to migrate (the RSS tracker MU plugin) shipped early in v8.2.1. The `mu-plugins/` directory no longer exists in the theme repo.

**Contract hooks — 4 cross-package hooks (3 added v8.4.0–v8.5.0; +1 added v9.1.6):**

| Hook | Type | Dispatched by | Listened by |
| --- | --- | --- | --- |
| `sn_purge_all_caches_result` | filter | plugin: `apply_filters( 'sn_purge_all_caches_result', 0, $args )` returns int count | theme: [`inc/template-maintenance.php`](../inc/template-maintenance.php) wraps `sn_purge_all_caches()` |
| `sn_clear_template_overrides_result` | filter | plugin: `apply_filters( 'sn_clear_template_overrides_result', 0 )` returns int count | theme: [`inc/template-maintenance.php`](../inc/template-maintenance.php) wraps `sn_clear_template_overrides()` |
| `sn_og_font_paths` | filter | plugin: `apply_filters( 'sn_og_font_paths', array() )` returns array with `bebas` + `dmmono` keys mapping to absolute TTF paths | theme: [`inc/og-fonts.php`](../inc/og-fonts.php) returns paths via `get_theme_file_path( 'assets/fonts/og/*.ttf' )` |
| `sn_gh_latest_theme_tag_result` | filter | plugin: `apply_filters( 'sn_gh_latest_theme_tag_result', null )` returns `string|null` (latest GitHub theme tag) | theme: [`inc/wp-update-integration.php`](../inc/wp-update-integration.php) wraps `sn_gh_latest_theme_tag()` |

> **Retired in theme v8.3.0 (Phase 2b):** the 5 updater/self-heal contracts (`sn_self_heal_force_run_result`, `sn_updater_branch`, `sn_updater_revcount`, `sn_updater_force_check`, `sn_updater_clear_error`). See [Phase 2b spec](superpowers/specs/2026-05-15-phase-2b-cleanup-design.md).

> **Added in theme v8.4.0 (Phase 3):** `sn_og_font_paths` — plugin owns OG card PHP GD rendering; theme owns the typography. See [Phase 3 spec](superpowers/specs/2026-05-16-phase-3-theme-coupled-moves-design.md).

> **Added in theme v9.1.6 / plugin v4.1.1 (audit X-01):** `sn_gh_latest_theme_tag_result` — plugin needed the latest theme tag for its deploy-status card but was reaching directly into the theme function via `function_exists`. The new filter contract makes the dependency tolerant of theme-absent/inactive states.

**Direct dependencies kept (no contract — stable by design):**
- `sn_*` option keys — plugin reads via `get_option()`. Option *key names* are part of the public contract surface; renaming them would require migration shims for zero benefit.
- `sn_github_*` transient keys — plugin reads via `get_transient()`. Same rationale.
- `[sn_reading_time]` shortcode — defined by plugin `inc/reading-time.php`; theme's `inc/page-notes-render.php` invokes via `do_shortcode()` with a `'5 min'` fallback if the plugin is absent.
- `sn_after_full_cache_flush` action — theme dispatches at the end of `sn_purge_all_caches()`; available for plugin extension (no current listener).

**Ability namespacing convention** (added v9.1.1 with the Abilities API integration; documented here in v9.1.6/plugin v4.1.1 per audit X-08):
- Theme abilities use `signal-and-noise/*` — matches `get_stylesheet()` so WP's `ai/ai` plugin classifies them as "Theme."
- Plugin abilities use `signal-noise/*` — matches the plugin slug.
- When adding a new ability, pick the namespace based on which package owns the underlying impl. Both files have `wp_has_ability_category()` guards on shared categories (`content`, `diagnostics`, `ai-generation`) to avoid `_doing_it_wrong` on dual registration.

**When adding new cross-package interactions:** add a row to the table above and document the listener side in the theme file that owns the underlying function. **Never let plugin code directly call a theme function — even with `function_exists` guards.** The contract pattern is non-negotiable.

### 10.1 The legacy updater — RETIRED in v8.3.0

The original GitHub-poll self-updater (`inc/updater.php`, 683 LOC) and the
associated `sn_updater_*` contracts were removed in theme v8.3.0 (2026-05-15)
when Phase 2b landed. Theme deploys then rode Cloudways' git-pull on tag push
(see [Phase 2a spec](superpowers/specs/2026-05-15-cloudways-auto-deploy-design.md))
which made the WP-Cron SWR refresh + filter-injection layer redundant.

If you're maintaining a fork that still needs the original in-WP update
polling, restore from git history at the v8.2.1 tag.

**Status note:** the *visibility* surface of the legacy updater (showing a
"new version available" badge in wp-admin) was partially restored in v8.5.0
as a much slimmer WP-native integration. See §10.5 below for the current
update infrastructure.

### 10.2 Self-heal — RETIRED in v8.3.0

The file-drift recovery module (`inc/template-self-heal.php`) was removed
in theme v8.3.0. Under Cloudways' git-pull deploys, the file tree is
atomically consistent with the deployed commit — there's nothing to "heal."

The `/heal-templates` plugin REST endpoint was retired in plugin v1.2.0
to match.

### 10.3 The synthetic update label — RETIRED in v8.3.0

The legacy updater produced an artificial version label of the form
`{Version}{-rN}+{branch}.{sha7}` (e.g. `8.0.5-r3+main.78048f2`) to
distinguish between "tagged release" and "commits ahead of tag" states.
Removed alongside the updater itself in v8.3.0. The current WP-native
integration (§10.5) shows only the plain tag version since deploys are
now strictly tag-aligned.

### 10.4 The `/notes` route — PHP-authoritative rendering

**Before editing any `templates/*.html`, check whether a `template_include` short-circuit overrides it.** This theme has one such short-circuit and it was non-obvious enough to ship a real bug from in v8.0.6 (an edit was almost made to the wrong file).

The `/notes` route lives off normal FSE template resolution. [inc/page-notes-template.php](../inc/page-notes-template.php) hooks `template_include` and returns [inc/page-notes-render.php](../inc/page-notes-render.php) when the request matches `is_page('notes')` OR has request URI `/notes` / `/notes/`. WP's normal block-template chain (file ↔ DB ↔ object cache ↔ registry) never runs for this page when our hook fires.

**Why it exists:** three incidents (2026-04 and two in 2026-05) where `/notes` rendered stale content despite `main` being correct. Each had a different proximate cause (silent deploy skip, broken self-heal, stale `wp_template` DB row surviving migration) but the common surface was WP's block-template resolution chain having too many layers that drift independently. Pulling the page off that chain entirely eliminated the failure mode.

**Defense layers (canonical order):**

1. **PRIMARY:** [inc/page-notes-render.php](../inc/page-notes-render.php) — what users actually see. The `<style>` block, `<header>`, `<main>` content, and the `sn-notes-feed-*` footer are all here. Inline `<style>` ships with the page render so the unique CSS classes (`.sn-notes-feed-status`, `.sn-notes-feed-cursor`, `.sn-notes-feed-note`) only exist on this code path.
2. **FALLBACK:** [templates/page-notes.html](../templates/page-notes.html) — the FSE template. Renders ONLY if the PHP renderer file is missing post-deploy. **Will drift from the live design by design** — the docblock explicitly accepts this trade-off ("better to render from a stale-but-correct file than to 404"). Don't try to keep this file in sync with every footer/copy change to the renderer; that's not its job.
3. **SWEEP:** an `admin_init` `wp_template` DB sweep clears any stale Site Editor save that would otherwise win in `get_block_templates()` results.

**Rules of engagement:**

- **Footer / copy / structural changes to the live `/notes` page → edit `inc/page-notes-render.php`,** not `templates/home.html` and not `templates/page-notes.html`. The two FSE templates are dead/fallback paths for this URL.
- **`templates/home.html` is fully dead for `/notes`.** It's the WP "blog index" template, which `/notes` resolves to in the template hierarchy, but the `template_include` hook overrides it before render. Anything in `home.html` after the query loop never ships.
- **`templates/page-notes.html` is the fallback,** so leaving it inconsistent with the live design is fine. Updating it is a maintenance burden with no payoff in normal operation.
- **Verify which renderer is live via curl:** the renderer emits `SN_NOTES_OVERRIDE_BUILD` as an HTML comment via `wp_footer`. If you don't see that build marker on a `curl https://juanlentino.com/notes/`, the override hook didn't fire and the fallback is rendering — that's an incident.

**When the override hook DOESN'T fire:** the fallback renders. Legitimate causes: file missing post-deploy, fatal PHP error before the hook registers, theme switched. All three are real failure modes; the fallback is the safety net.

**Could there be more `template_include` short-circuits?** Currently only `/notes`. If someone adds a second one (e.g., the homepage), this section needs a second subsection — the gotcha generalizes: greppable answer is `grep -rn 'template_include' inc/`.

### 10.5 The WP-native update integration (v8.5.0 → v8.5.3, mirror plugin v1.10.1 → v1.11.2)

**Lives in:** [inc/wp-update-integration.php](../inc/wp-update-integration.php) (registration + visibility) and [inc/wp-update-git-preservation.php](../inc/wp-update-git-preservation.php) (`.git` survives WP UI installs). Plugin has identical pair at `signal-and-noise-tools/inc/`.

**Why it exists:** the legacy updater (§10.1) shipped its own polling, SHA tracking, self-heal, and synthetic update label — ~683 LOC for what WP Core already does natively for plugins and themes via the `pre_set_site_transient_update_themes` filter. The Phase 2b cleanup deleted all of that. v8.5.0 reintroduced ~120 LOC of native-WP integration to restore the version-visibility surface in wp-admin without bringing back the polling-heavy machinery.

**Both install paths now coexist:**

| Path | Triggered by | Mechanism |
|---|---|---|
| **Canonical** (fast, default) | `gh workflow run deploy.yml --ref vX.Y.Z` | Cloudways `/api/v1/git/pull` (theme) or SSH `git checkout` (plugin) + CF cache purge. ~12s. Doesn't touch `wp-content/upgrade/`; preserves `.git` trivially. |
| **WP UI** (alternative) | wp-admin → Updates → Update Now | WP downloads GitHub tag ZIP. `upgrader_source_selection` filter renames `<repo>-X.Y.Z/` → `<slug>/`. `upgrader_pre_install` backs up `.git/` → `wp-content/upgrade/sn-<slug>-git-backup/`. WP runs `clear_destination()` + `move_dir`. `upgrader_post_install` restores `.git` into the new dir. `admin_init` self-recovery handles orphaned backups if post_install never fires. |

**The 3-patch arc that made WP UI actually work end-to-end:**

| Patch | What it unblocked | Symptom before |
|---|---|---|
| Theme v8.5.1 / plugin v1.10.1 | Enable infrastructure | WP_Error gate rejected all installs |
| Plugin v1.11.1 / theme v8.5.3 | Make WP actually *see* new tags | 12h cache hid newly-pushed tags from WP's update checker until expiry |
| Theme v8.5.2 / plugin v1.11.2 | Stop WP UI installs from destroying `.git` | Clicking Update Now broke the next workflow_dispatch |

**Atomic same-filesystem `rename()` is the key primitive** for `.git` preservation. No window where `.git` exists in both places or neither. Backup deliberately lives under `wp-content/upgrade/` to guarantee same-mount as `wp-content/themes/` and `wp-content/plugins/` — cross-FS rename silently falls back to copy+delete, which is NOT atomic.

**WP core source verifications baked in (don't trust memory):**

- `upgrader_source_selection` signature: `($source, $remote_source, $upgrader, $hook_extra)` — `accept_args=4`. Forgetting `accept_args` defaults to 1 and silently drops `$hook_extra`, making the `theme/plugin` guard match every upgrade in a batch.
- `upgrader_pre_install` signature: `($response, $hook_extra)` — `accept_args=2`. Returning `WP_Error` aborts the install entirely (used to abort if `.git` backup fails).
- `upgrader_post_install` signature: `($response, $hook_extra, $result)` — `accept_args=3`. `$result['destination']` tells you where to restore `.git`. Never return `WP_Error` here — the install itself succeeded; failed `.git` restore is post-hoc.
- Filter order is fixed: `pre_install → source_selection → clear_destination → move_dir → post_install`. Verified against `wp-admin/includes/class-wp-upgrader.php::install_package()`.
- `$hook_extra['theme']` vs `$hook_extra['plugin']` is THE guard key. Wrong key = matches every upgrade in a batch = renames other people's themes/plugins.

**Cache layer (theme + plugin both, since v1.11.1 / v8.5.3):**

- TTL: 1 hour (was 12h until each repo's third patch).
- Honors `WP_FORCE_UPDATE_CHECK` constant + `?force-check=1` query arg — clicking "Check Again" in wp-admin actually re-fetches.
- `admin_init` version-change detection: compares on-disk Version against `sn_last_seen_*_version` option; on mismatch, clears both `sn_gh_latest_*` and WP's own `update_themes` / `update_plugins` site transient. Handles the "upgrade just happened, why does WP still say update available?" case for both install paths.

**Theme update transient shape ≠ plugin update transient shape** — themes register via `pre_set_site_transient_update_themes` with **arrays** keyed by stylesheet; plugins use `pre_set_site_transient_update_plugins` with **stdClass objects** keyed by basename. Subtle WP core quirk; copy-adapting code between the two needs the shape conversion.

**Verification recipe** for the .git preservation (the only thing in §10.5 that hasn't been exercised end-to-end yet on this install):
1. Push a v8.5.4+ tag without running `gh workflow run`.
2. Wait for 1h cache or hit `?force-check=1` to surface the new version.
3. Click "Update Now" in wp-admin.
4. After install completes, run `gh workflow run deploy.yml --ref <same-tag>` against the same tag. If it succeeds, the destination still has `.git` — preservation worked.

**Manual recovery** (if both post_install + admin_init self-recovery fail):
```bash
mv wp-content/upgrade/sn-signal-and-noise-git-backup wp-content/themes/signal-and-noise/.git
# or for the plugin:
mv wp-content/upgrade/sn-signal-and-noise-tools-git-backup wp-content/plugins/signal-and-noise-tools/.git
```

---

## 11. The versioning rules (project-specific)

Recap from [CLAUDE.md](../CLAUDE.md) and [docs/VERSIONING.md](VERSIONING.md):

- **Caps OVERRIDE global:** patch cap 7 per minor, minor cap 5 per major.
- **`7.5.6` → next minor would be `7.6`, which exceeds the 5-cap → rolls to `8.0.0`.**
- **What bumps:** code, CSS, FSE templates with structural changes, dbDelta migrations.
- **What doesn't bump:** `docs/`, `CLAUDE.md`, content-only copy edits inside existing blocks, CHANGELOG-only commits.
- **Commit format:** `vX.Y.Z: short summary`
- **Tag format:** annotated, `git tag -a vX.Y.Z -m "vX.Y.Z — summary"`

---

## 12. Cloudways quirks worth remembering

- **MySQL `NOW()` is NOT guaranteed to be UTC** on the syntharchy-wp server. Always use `UTC_TIMESTAMP()` for time comparisons against `current_time('mysql', true)` rows. The v8.0.0 RSS tracker uses this throughout.
- **Breeze cache plugin** is installed. After a push, full-page-cache might serve stale HTML for up to its TTL. The self-heal `purge_caches` action (and the SN Dashboard "Purge All Caches" button) clears Breeze + transients.
- **Cloudflare CF-Connecting-IP** header is set by the edge. Trust it before falling back to `X-Forwarded-For` for real client IPs.
- **`wp-cron.php`** is publicly accessible by default — both Cloudways and WP themselves rely on this for cron dispatch via spawn_cron loopback. Don't block it in `.htaccess`.

---

## 13. Upstream WordPress core gotchas — running list

The bugs / surprises we've actually paid for. Reference this list before assuming WP behaves how you remember.

| # | Behavior | File:line in WP core | Our workaround |
| --- | --- | --- | --- |
| 1 | `block_core_social_link_render` mishandles path-relative URLs | `wp-includes/blocks/social-link.php` (the scheme check) | [inc/frontend-filters.php](../inc/frontend-filters.php) `render_block_data` filter |
| 2 | `_maybe_update_themes` gates re-checks at 7200s, even if our cache is fresher | `wp-includes/update.php` | v8.0.1 deletes `update_themes` site_transient on SHA change |
| 3 | `_maybe_update_themes` skipped early if `last_checked` < 7200s ago, no matter how recent the SHA cache is | Same file | Same workaround |
| 4 | `wp:html` blocks render as opaque content — block editor can't decompose them | Block editor JS | Use proper core blocks + `className` + CSS |
| 5 | `<p>` rendered by paragraph block must NOT have `wp-block-paragraph` class | `wp-includes/blocks/paragraph.php` save callback | Just don't add it |
| 6 | `style.typography.color` does not exist; only `style.color.text` does | Block editor schema | Use the right path |
| 7 | FSE template parts execute no PHP | wp-includes block-templates loader | Hardcode paths; rely on filters for host resolution |
| 8 | `register_activation_hook` is a no-op for MU plugins | `wp-includes/plugin.php` | Use `init` hook for install instead; document removal steps in README |
| 9 | dbDelta is whitespace-sensitive | `wp-admin/includes/upgrade.php` | Copy format from existing working calls |
| 10 | `update_option` returns `false` for "no change" AND for "real failure" — indistinguishable from the return value alone | `wp-includes/option.php` | Check `$wpdb->last_error` to disambiguate. v8.0.1 form handler does this. |
| 11 | Deleting a `templates/page-*.html` without removing the matching `theme.json` `customTemplates` entry leaves WP registering a phantom custom template (still appears in the Site Editor template picker, errors on selection). No warning at theme-load time. | `wp-includes/block-template-utils.php` (the `_register_block_templates_from_theme_json` walker) | When deleting any `templates/page-*.html`, grep `theme.json` for the slug and delete the matching `customTemplates` entry in the same commit. v8.0.6 caught this only on post-edit verification grep. |
| 12 | When the same function name is declared in both an active plugin and an active theme (e.g. during a theme→plugin code split's transition window), PHP fatals at parse time with *"Cannot redeclare function"*. WordPress catches this during plugin activation and refuses to activate — the user sees *"Plugin could not be activated because it triggered a fatal error."* with no further detail. **WP hooks (`add_action`/`add_filter`) are idempotent; PHP function declarations are not.** Conflating these two layers caused the v8.2.0 / signal-and-noise-tools v1.0.0 coordinated release to fatal on first activation attempt. | PHP language layer (not a WP layer) | When splitting code between theme and plugin: (1) **delete from theme first, then install plugin** — never the reverse, regardless of how cleaner the reverse order *feels*. (2) Defensive: in the receiving package, pre-flight `file_exists()` against one canonical file in the sending package's old location, and bail with an admin notice if found. signal-and-noise-tools v1.0.1 added this guard. |
| 13 | Cloudways' default ModSecurity ruleset rejects zero-byte POST requests to WP REST endpoints with **HTTP 400 + empty body** — *before* the request reaches WP. The diagnostic fingerprint: WordPress always returns a JSON error body with a `code` field on 4xx, so a bare 400 with no body is always upstream (WAF/reverse-proxy). | Cloudways stack (Apache + ModSecurity CRS), not WP core | Always send `Content-Length: 0` (or a body) on REST POSTs from external automation. Caught in Phase 2b (v8.3.0) when GitHub Actions called `/wp-json/signal-noise/v1/purge-cache` from `deploy.yml` and got empty 400. Fix: add `-H 'Content-Length: 0'` to the curl call. |
| 14 | **The first `add_submenu_page()` call for a parent silently auto-prepends a duplicate of the parent menu as the first submenu** — so a freshly-registered top-level menu shows `Parent / Parent` in the sidebar. The escape hatch is buried at [`wp-admin/includes/plugin.php:~2398`](https://raw.githubusercontent.com/WordPress/WordPress/master/wp-admin/includes/plugin.php) inside the condition `if ( ! isset( $submenu[ $parent_slug ] ) && $menu_slug !== $parent_slug )`. The Plugin Handbook page on submenus doesn't mention this mechanism. | `wp-admin/includes/plugin.php` (the `! isset && !==` guard inside `add_submenu_page()`) | Always immediately follow `add_menu_page( …, 'my-slug', … )` with `add_submenu_page( 'my-slug', …, 'my-slug', $callback )` — same slug as parent, which trips the `$menu_slug !== $parent_slug` check and suppresses the auto-prepend, while replacing the duplicate label with whatever you want ("Dashboard", "Overview", etc.). Plugin v1.8.1 [`inc/admin-page.php`](https://github.com/juanlentino/signal-and-noise-tools/blob/main/inc/admin-page.php) does this. |
| 15 | **`add_menu_page()` returns the hook suffix unconditionally, but `add_submenu_page()` returns `false` if the current user lacks the capability** — the asymmetry isn't in the Handbook signatures. Code that captures the return value for `admin_enqueue_scripts` guards and assumes a string will silently break for non-admin users on the submenu path. | `wp-admin/includes/plugin.php` `add_submenu_page()` capability gate (returns `false` before the hookname is generated) | When capturing submenu hook suffixes for enqueue, gate on `if ( false !== $hook )`. Or capture only the top-level hook (which is always a string) and use `in_array( $hook_suffix, $sn_hooks, true )` in the enqueue callback against a collected list of acceptable suffixes. |
| 16 | **Neither `add_menu_page()` nor `add_submenu_page()` deduplicate slugs.** Registering the same slug twice (e.g. by accident in a loop, or by re-registration in a late-bound `admin_menu` hook) silently appends both entries to the global `$menu` / `$submenu` array. WP renders whichever wins last-write — usually the first registration — but `$_registered_pages[$hookname] = true` overwrites idempotently so URL routing still works. Effect: a phantom duplicate entry in the sidebar with no error. | `wp-admin/includes/plugin.php` — append-only `$menu[] = $new_menu` / `$submenu[ $parent_slug ][] = $new_sub_menu` | If registering submenus in a loop, dedupe the input array first (`array_unique` on slug column). Don't register inside any hook that can fire twice (e.g. `init` instead of `admin_menu`). |
| 17 | **The `load-{hook_suffix}` action fires before HTML output and is the WP-recommended location for form processing** (handbook explicitly recommends it over inline processing in the page callback). The advantage: you can `wp_safe_redirect()` after save to implement Post/Redirect/Get; inline processing in the callback can't redirect because output has already started rendering. | `wp-admin/admin.php` dispatch loop (calls `do_action( "load-{$page_hook}" )` before the callback) | For new admin pages with form submission, prefer `add_action( 'load-' . $hook, 'my_form_handler' )` and put the `$_POST` processing + redirect there. For existing inline processing (like plugin v1.8.0's `sn_theme_options_page`), leave it — it works as long as nothing has echoed yet. |
| 18 | **The Plugin Handbook unambiguously recommends Settings API (`register_setting` + `add_settings_section` + `add_settings_field`) over custom `$_POST` form handling for admin settings pages.** Settings API gives you: nonce verification (`settings_fields()`), per-option sanitization callbacks, automatic save handling (form posts to `options.php` which WordPress owns), the **PRG redirect** that prevents stale-form-after-save, and `settings_errors()` flash messages that survive the redirect. **But** the API enforces "one option per registered setting" — it doesn't compose cleanly with a single nested-array option storing 5+ categories (the v1.8.0 `sn_settings` schema), and the `options.php` redirect loses `?tab=…` context for tabbed UIs. Every serious WP plugin doing complex settings (Yoast Free, ACF, WP Rocket, WooCommerce) bypasses Settings API for these reasons. **Deviating from the Handbook is a defensible architectural choice — but it means you own the work the API would have done for you: nonces, sanitization, PRG redirect, flash messages.** Forgetting any of those produces real UX bugs (see gotcha #19 for the PRG bug we shipped in v1.8.0). | Plugin Handbook → Settings → Using Settings API; `wp-admin/options.php` (the canonical save target) | When the schema warrants a nested option (saves DB row count + atomic updates), commit to custom forms and check off every Settings-API responsibility: (a) `wp_nonce_field` + `check_admin_referer` (we do); (b) per-tab sanitization (we do via `sn_settings_save`); (c) **PRG redirect after save** (we missed in v1.8.0 — fixed in v1.9.0); (d) success/error notices that survive the redirect (we do via query args). |
| 19 | **Custom settings pages with inline `$_POST` handling render with stale values after save** unless you implement Post/Redirect/Get (PRG). The bug: the page callback processes `$_POST` and calls `update_option()`, then continues rendering the form. Form fields read from `sn_setting()` which has a static cache populated *before* the save fired (or even at start-of-request via other modules). So the form re-renders with the *pre-save* values, making it look like the save failed. Settings API gives PRG for free; custom forms must do it themselves. The fix: after `update_option()`, `wp_safe_redirect( add_query_arg( 'saved', '1' ) )` + `exit;` — the next request reads fresh state. Pass save status through a query arg (`?saved=1` / `?error=msg`) since `$notices` arrays don't survive the redirect. | Same bug class as Handbook's recommendation — Settings API + `options.php` handles PRG internally; custom forms don't get it. | In `sn_theme_options_page()`, every `save_*` action handler must end with `wp_safe_redirect( add_query_arg( array( 'saved' => $tab ), remove_query_arg( array( 'saved', 'error' ) ) ) ) + exit`. Page-render code reads `$_GET['saved']` / `$_GET['error']` and pushes notices accordingly. Caught + fixed in plugin v1.9.0 after auditing the Plugin Handbook's Settings API chapter against our v1.8.0 inline-handler pattern. |
| 20 | **The `document_title_parts` filter joins ALL non-empty keys with the separator.** `wp_get_document_title()` in `wp-includes/general-template.php` runs `implode( " $sep ", array_filter( $title ) )` AFTER the filter. WP's defaults populate `'title'` (page name), `'page'` (pagination), `'tagline'` (site description from `bloginfo('description')`), and `'site'` (site name). Setting just `$parts['title']` leaves three other segments that get appended — you ship `<title>Page – Site – Tagline</title>` (three segments) when you wanted one. | `wp-includes/general-template.php` `wp_get_document_title()` | When pre-building a final-format title string (e.g. `"Page — Site"`), **REPLACE** the `$parts` array rather than augment it: `return array( 'title' => $built_string );`. Caught in Phase 13 TSF cutover verification — plugin v2.0.0 set `title` + `site` and shipped three-segment titles until v2.0.2 fixed it. |
| 21 | **WP core's `rel_canonical()` fires on every singular view including static front pages.** Registered at `wp_head` priority 10 by `wp-includes/default-filters.php`. The function checks `is_singular()` and emits `<link rel="canonical">`. Plugins emitting their own canonical at lower priorities will produce two canonical tags per page unless they explicitly remove the core action. Until Phase 13, TSF was suppressing core's `rel_canonical` for us behind the scenes. | `wp-includes/link-template.php` `rel_canonical()`; `wp-includes/default-filters.php` adds the action | When introducing custom canonical emission, immediately add `remove_action( 'wp_head', 'rel_canonical' )` on `init` priority 1. Defensive gate on `! function_exists( 'the_seo_framework' )` so accidental TSF reactivation doesn't double-suppress. Plugin v2.0.2 [`inc/seo.php`](https://github.com/juanlentino/signal-and-noise-tools/blob/main/inc/seo.php) does this. |
| 22 | **WP core's `wp_robots()` emits a competing `<meta name="robots">` only when `blog_public=0`** ("Discourage search engines" in Settings → Reading). Hooked at `wp_head` priority 1 since WP 5.7. When `blog_public=1` (production default) it emits nothing — so the conflict is latent. A staging clone or accidental toggle introduces a second robots tag with conflicting directives overnight. | `wp-includes/robots-template.php` `wp_robots()`; `wp-includes/default-filters.php` adds the action | Same defensive pattern as rel_canonical removal: `remove_action( 'wp_head', 'wp_robots', 1 )` on `init`, gated on TSF absence. Plugin v2.0.4 adds this — surfaced by the pre-7.0 audit subagent, not by user-visible breakage. |
| 23 | **Block themes do NOT auto-declare `title-tag` theme support.** Despite many tutorials claiming otherwise, `wp-includes/theme.php` on trunk has no auto-declaration for block themes. Without an explicit `add_theme_support('title-tag')` in `inc/setup.php`, `_wp_render_title_tag()` returns early and the page has NO `<title>` tag at all. The only reason this looked working pre-Phase-13 is that TSF was emitting `<title>` itself, masking the gap. | `wp-includes/theme.php` `_wp_render_title_tag()` capability check; `wp-includes/general-template.php` registers the hook | Always declare `add_theme_support('title-tag')` explicitly in block themes' `after_setup_theme` callback. Theme v8.5.5 added this when deactivating TSF made the gap visible. |
| 24 | **`WordPress/desktop-mode` auto-imports every `add_menu_page()` entry into its dock by default.** Per [`includes/core/payload.php`](https://github.com/WordPress/desktop-mode/blob/trunk/includes/core/payload.php), the default dock items array iterates `$menu` + `$submenu` globals and converts each accessible page to a dock entry. Plugins that ALSO register an explicit dock entry via the `desktop_mode_dock_items` filter end up with two visible entries for the same plugin — one with the plugin's registered icon, one with desktop-mode's generic fallback. | `WordPress/desktop-mode` `includes/core/payload.php` (default dock builder) | Use the documented `desktop_mode_dock_placement` filter to return `'hidden'` for your menu slug, suppressing the auto-import. Keep your explicit `desktop_mode_dock_items` entry for the richer submenu + badge. Plugin v2.0.1 [`inc/desktop-mode-integration.php`](https://github.com/juanlentino/signal-and-noise-tools/blob/main/inc/desktop-mode-integration.php) does this. |
| 25 | **`wp_register_ability()` silently returns `null` if the category slug isn't pre-registered.** Per `WordPress/abilities-api`, the registry checks `wp_has_ability_category()` and fires `_doing_it_wrong` + bails out. Categories MUST be registered via `wp_register_ability_category()` during the **separate** `wp_abilities_api_categories_init` action — registering on `wp_abilities_api_init` (the abilities-register hook) is too late. | `WordPress/abilities-api` `includes/abilities-api.php` registry guard | When using the Abilities API in WP 7.0+, hook BOTH actions: `wp_abilities_api_categories_init` first (register your category slugs), then `wp_abilities_api_init` (register the abilities that cite them). Plugin v2.0.4 [`inc/abilities-registration.php`](https://github.com/juanlentino/signal-and-noise-tools/blob/main/inc/abilities-registration.php) demonstrates the two-step pattern. Caught by an audit subagent before shipping — would have silently failed all 4 ability registrations on 7.0 launch. |
| 26 | **`is_plugin_active()` is a pure option lookup — it does NOT check whether the plugin file actually exists on disk.** If a plugin is removed via the filesystem (e.g., FTP/SSH/Cloudways File Manager delete) instead of WP's Deactivate flow, its slug stays in the `active_plugins` option as an orphan forever. WP silently skips the missing file on every page load (no error, no admin notice), but `is_plugin_active( $basename )` still returns `true`. Any plugin that gates behavior on another plugin's active-state via `is_plugin_active()` will misbehave indefinitely. | `wp-admin/includes/plugin.php` `is_plugin_active()` → `in_array( $plugin, (array) get_option( 'active_plugins', array() ), true )` | Always pair `is_plugin_active( $basename )` with `file_exists( WP_PLUGIN_DIR . '/' . $basename )` when conditioning on another plugin's presence. The `&&` of both signals is the only authoritative "actually running" check. Caught by a production login lockout in 2026-05-18 — plugin v2.1.1 [`inc/login-hide.php`](https://github.com/juanlentino/signal-and-noise-tools/blob/main/inc/login-hide.php) added the file_exists tightening (plus mirrored it in admin-page.php status display). Cleanup of orphan slug is optional — the consumer-side defense makes it irrelevant. |
| 27 | **Critical CSS scope is a TIME-DOMAIN question, not a viewport-domain question.** The conventional rule "critical CSS = above the fold" conflates render timing (initial paint, ~50-200ms) with interaction timing (any user input firing at any moment). Styles needed for state revealed by interaction (e.g., `.is-menu-open` cascade for a hamburger overlay) can be triggered milliseconds after first paint, before the deferred stylesheet roundtrip completes. If the styles live only in the deferred bundle, the user sees unstyled content for the brief race-condition window. The rule should be: **critical CSS = what can possibly render before the deferred stylesheet roundtrip completes**, which includes any interaction-triggered state on always-visible controls. | N/A — this is a perf-pattern misconception, not a WP gotcha per se | When pruning critical CSS, ask "could a user click/tap/hover to reveal this content before `<link rel='stylesheet'>` tags below the inline block have fetched?" If yes, keep the styles in critical. Theme v8.5.7 restored the `.is-menu-open` cascade after v8.5.6's overly-aggressive pruning shipped this race condition. Patterns at-risk: mobile nav overlays, dropdown menus, modals triggered by always-visible buttons, hover-revealed tooltips on the hero. Patterns safe-to-defer: animation keyframes (entrance fades are post-paint), `:hover` styles on non-critical elements, form widget styling for forms deep below the fold. |
| 28 | **Inlining critical CSS into HTML means HTML cache TTL effectively becomes critical-CSS TTL.** When critical CSS is inlined at render time (via `<style id="...">` instead of `<link rel="stylesheet">`), HTML caching plugins like Breeze hold the inlined copy in their cached HTML — independent of the source CSS file on disk. A theme update that changes critical.css will land on disk and even in Cloudflare's edge cache for the FILE, but the served HTML still has the OLD inlined version until the HTML cache also rolls over. Symptom: deploy completes, file is updated, but the page still renders the previous critical CSS. Visible as: WCAG/contrast/animation/state styles missing or stale on visitor pages even though `?bust=$(date +%s)` of the file URL returns the new version. | Theme `inc/assets-frontend.php:82` `file_get_contents()` + inline `<style>` pattern; Breeze plugin page cache | Two parts: (a) **diagnostic** — when "my CSS fix isn't visible," check the inlined `<style>` content in the served HTML BEFORE concluding the CSS file is stale (it almost certainly isn't); (b) **fix** — on theme/plugin version change, fire `sn_purge_all_caches_result` filter (via `do_action`/`apply_filters`) to roll over the HTML cache too. The watchdog in v8.5.4 / v1.15.1 cleared WP transients + plugin headers but NOT Breeze; this is the queued v9.0.0 enhancement. Surfaced when v8.5.7 mobile-nav fix didn't render until manual Purge All Caches. |
| 29 | **WP core's `class-wp-plugin-install-list-table.php` reads `$plugin['icons']['default']` without an `! empty()` guard.** This means a self-hosted plugin that filters `plugins_api` to provide its own info MUST set the `default` key in the `icons` array, OR a PHP notice fires on the Plugins → Add New screen. The other key reads (`svg`, `2x`, `1x`) on update-core.php's `list_plugin_updates()` are guarded properly, so omitting them is fine — but `default` is special-case unguarded. | `wp-admin/includes/class-wp-plugin-install-list-table.php` `display_rows()` ~L445 | Always emit `'default' => $icon_url` alongside any other keys. Plugin v2.1.2 [`inc/wp-update-integration.php`](https://github.com/juanlentino/signal-and-noise-tools/blob/main/inc/wp-update-integration.php) does this even though the plugin isn't on wordpress.org's search index (defense-in-depth costs nothing). Same SVG can fill every key; browsers render SVG fine inside `<img>` tags. |
| 30 | **WP 7.0's native `core/breadcrumbs` block emits visual `<nav><ol>` HTML only — NO `BreadcrumbList` JSON-LD.** A widespread assumption (echoed in our own `seo-schema.php` docblock + `docs/superpowers/specs/2026-05-16-plugin-absorption-roadmap.md` Phase 11 plan) is that the native block ships structured data alongside the markup. Inspection of [gutenberg/packages/block-library/src/breadcrumbs/index.php](https://github.com/WordPress/gutenberg/blob/trunk/packages/block-library/src/breadcrumbs/index.php) on 2026-05-20 shows the render callback returns `sprintf('<nav %s><ol>%s</ol></nav>', …)` with no `<script type="application/ld+json">` emission anywhere in the package. Pattern is consistent across all Gutenberg core blocks: visual HTML lives in `block-library`, structured-data emission is left to SEO plugins. Deleting our `sn_schema_breadcrumb_list()` in favor of the native block would silently drop breadcrumb rich results from SERPs. | [gutenberg/packages/block-library/src/breadcrumbs/index.php](https://github.com/WordPress/gutenberg/blob/trunk/packages/block-library/src/breadcrumbs/index.php) | Keep plugin-side BreadcrumbList JSON-LD emission. If/when visual breadcrumbs are added to theme templates, drop the native block in alongside the existing JSON-LD — they're independent layers. Caught 2026-05-20 by reading framework source before claiming the migration was safe (memory entry `feedback_read_framework_source`). |
| 31 | **WP 7.0's Command Palette (⌘K) has no PHP registration API — `@wordpress/commands` is JS-only.** Commands register via `wp.data.dispatch( 'core/commands' ).registerCommand( config )` (imperative) or `useCommand( config )` (React hook). The command object's `callback` is invoked as `callback( { close } )` per [components/command-menu.js](https://github.com/WordPress/gutenberg/blob/trunk/packages/commands/src/components/command-menu.js), where `close` dismisses the palette. Plugin integration pattern: enqueue a JS file in wp-admin with `wp-commands` in the dep array, call `registerCommand` imperatively for static commands. Icon field expects a JSX element — use `wp.element.createElement('span', { className: 'dashicons dashicons-…' })` to embed dashicons without a JSX build step. | [gutenberg/packages/commands/src/store/index.js](https://github.com/WordPress/gutenberg/blob/trunk/packages/commands/src/store/index.js), [components/command-menu.js](https://github.com/WordPress/gutenberg/blob/trunk/packages/commands/src/components/command-menu.js) | When adding palette commands: (1) call `args.close()` BEFORE the REST roundtrip so snackbars surface against the normal admin chrome, not behind the palette overlay; (2) emit results via `wp.data.dispatch('core/notices').createNotice( kind, msg, { type: 'snackbar' } )`; (3) bail with `if ( ! wp.commands || ! wp.data || ! wp.apiFetch ) return;` so the script no-ops on WP < 7.0. Plugin v2.3.0 [`assets/command-palette.js`](https://github.com/juanlentino/signal-and-noise-tools/blob/main/assets/command-palette.js) demonstrates the pattern. |
| 32 | **`method_exists()` returns `false` for methods routed via `__call` magic dispatch — `is_callable()` is the only reliable check.** The wp-ai-client `Prompt_Builder` wrapper class at [`includes/Builders/Prompt_Builder.php:191`](https://github.com/WordPress/wp-ai-client/blob/trunk/includes/Builders/Prompt_Builder.php) declares a `__call` method that dispatches snake_case → camelCase to the underlying php-ai-client `PromptBuilder`. The class has 30+ `@method` PHPDoc declarations (e.g., `is_supported_for_text_generation()`, `using_model_preference()`) for IDE autocompletion — but these are NOT real method declarations at the language level. PHP's `method_exists()` only sees declared methods; it returns `false` for `__call`-routed names. **Symptom:** a defensive guard like `if ( method_exists( $builder, 'is_supported_for_text_generation' ) )` returns false → entire AI feature silently disabled with no error. Plugin v2.5.0–v3.7.0 shipped this bug; the guard's presence made `snt_ai_can_text_generate()` return false unconditionally for 6 months until the v3.7.1 audit caught it. | [WordPress/wp-ai-client `includes/Builders/Prompt_Builder.php:191`](https://github.com/WordPress/wp-ai-client/blob/trunk/includes/Builders/Prompt_Builder.php); PHP language spec on `__call` | Two valid patterns: (1) `is_callable( $builder, 'is_supported_for_text_generation' )` — distinguishes declared, `__call`-dispatched, and `__callStatic` methods correctly; (2) Just call inside try/catch — `BadMethodCallException` is thrown from `__call` if the underlying class doesn't support the name. The try/catch is what plugin v3.7.1 [`inc/ai-bootstrap.php:77-85`](https://github.com/juanlentino/signal-and-noise-tools/blob/main/inc/ai-bootstrap.php) uses. **Rule:** when a wrapper class advertises methods via `@method` PHPDoc, those are runtime-dispatched — assume `method_exists()` is wrong about them. |
| 33 | **WordPress Application Passwords are a credential-rotation tar pit when SSH+wp-cli is already available.** App Passwords (introduced in WP 5.6) gate HTTP Basic Auth on REST endpoints. Once issued, they require manual rotation — and rotating them in CI requires getting the new credential into GitHub Actions secrets without human eyes, which requires SSH + `wp user application-password create` and piping output through `gh secret set`. But once you're in SSH-land, the HTTP layer is doing nothing the SSH layer can't do directly via `wp ability run`, `wp eval`, or a thin shell command. The credential exists to bridge HTTP requests from CI to WP — but CI doesn't NEED HTTP if it can SSH. **The fundamental insight:** the question to ask when designing a rotation system is "why does this credential exist?" — often the answer is "to solve a problem that disappears with an architectural rethink." Plugin v3.7.3 eliminated `WP_DEPLOY_APP_PASSWORD` + `WP_DEPLOY_USER` GHA secrets entirely by routing the deploy's Cloudflare cache purge through SSH+`wp eval` instead of REST POST. | Cloudways stack pattern (SSH access available to GHA via private key) — not a pure WP gotcha but a deploy-architecture gotcha | When asked "how do we rotate credential X," first ask "why does X exist?" Cheapest rotation strategy is "no credential to rotate." Concrete pattern: if your CI calls `POST /wp-json/.../cache-purge` with HTTP Basic Auth, the equivalent SSH+wp-cli call (`ssh user@host "cd /apps/.../public_html && wp eval 'do_action(\"sn_purge_all_caches\");'"`) requires zero rotatable credentials. Plugin v3.7.3 [`.github/workflows/deploy.yml`](https://github.com/juanlentino/signal-and-noise-tools/blob/main/.github/workflows/deploy.yml) ships this pattern. |
| 34 | **`desktop_mode_register_command()` silently strips fields outside its 6-key schema** (`slug`, `label`, `description`, `icon`, `hint`, `script`). Per [`WordPress/desktop-mode/includes/commands.php:148-155`](https://github.com/WordPress/desktop-mode/blob/trunk/includes/commands.php), the function constructs `$entry` from a hardcoded whitelist before storing in the registry. Extra keys passed by the caller (e.g., `ability`, `render_mode`, `input_fields`, `ai_callable`) are dropped at this layer with no warning — they never reach the dispatched script, the JS-side `wp.desktop.registerCommand` call, or the AI Copilot's tool registry. Symptom: code-time tests that capture the full args via a stub PASS; production behavior fails because the registry only has the 6 documented fields. The v3.7.4 ability command palette plan shipped this bug and reverted it as part of the v3.8.0 cancellation. | [`WordPress/desktop-mode/includes/commands.php:148-155`](https://github.com/WordPress/desktop-mode/blob/trunk/includes/commands.php) | When passing data to `desktop_mode_register_command()`, only the 6 whitelisted fields survive. For richer command metadata (ability dispatch, render modes, AI-callable flags), use one of: (1) JS-side `wp.desktop.registerCommand( { aiCallable: true, run: fn } )` — opts in via the JS API where extra fields ARE preserved per [src/commands.ts](https://github.com/WordPress/desktop-mode/blob/trunk/src/commands.ts); (2) `desktop_mode_register_ai_tool()` (since 0.17.0) for AI-callable server-side dispatch with JSON-Schema parameters; (3) When the upstream Agents framework ([PR #240](https://github.com/WordPress/desktop-mode/pull/240) step 3) ships, `wp_register_ability()` registrations are auto-harvested — no `desktop_mode_register_command` extras needed. **General rule:** when a registration API accepts an `$args` array, read the implementation's whitelist BEFORE assuming extra fields propagate. Don't trust test stubs that mirror the full args; they paper over the field-strip. |
| 35 | **Hardcoded `define('X_VERSION', ...)` constants in plugin/theme bootstrap drift from the docblock `Version:` header.** WordPress reads the docblock via `get_plugin_data()` / `WP_Theme::get('Version')` as its source of truth — for the update system's installed-version detection, plugin-list display, REST `/plugins/v1/plugin-info`, and consumers across the codebase. A PHP constant defined separately must be manually kept in sync with the docblock — and it won't be, until something subtle breaks. Plugin v3.8.1 shipped with docblock `3.8.1` but `SNT_VERSION` constant still at `3.7.6` from a missed bump; the SN admin Dashboard widget showed stale "3.7.6" AND the CSS cache-buster `?ver=3.7.6` continued serving stale CSS from Cloudflare edge — both surfaces broke from the same root cause. Neither break fires an error or is caught by code review; the two-source-of-truth pattern bites silently. | `wp-admin/includes/plugin.php` `get_plugin_data()`; `wp-includes/class-wp-theme.php` `WP_Theme::get('Version')` | **Derive the constant at bootstrap; never hardcode it separately.** Plugin pattern: `define( 'SNT_VERSION', get_file_data( __FILE__, [ 'Version' => 'Version' ], 'plugin' )['Version'] )` at the top of the main plugin file. Theme pattern: prefer `wp_get_theme()->get('Version')` directly at every consumer (no constant needed) — themes get this for free with no explicit declaration. **General rule:** when a framework owns the canonical source for a value, never duplicate it into a separate constant; always read through the framework's API. Plugin v3.8.2 [`signal-and-noise-tools.php`](https://github.com/juanlentino/signal-and-noise-tools/blob/main/signal-and-noise-tools.php) ships this. Theme is already drift-safe (no PHP version constants defined; `wp_get_theme()` is the only read path). |
| 36 | **WordPress/desktop-mode hoists left-sidebar WP submenu entries into a horizontal top-nav row in its portal chrome.** If your plugin renders BOTH an in-page tab nav (e.g., `?tab=`-driven) AND a set of WP-`add_submenu_page` entries on the same parent slug, desktop-mode hoists those submenu entries into a horizontal row at the top of the portal — producing what visually reads as two parallel nav rows of similar items. To a user unfamiliar with desktop-mode's chrome model, it appears to be a duplicate-nav UX bug. | WordPress/desktop-mode portal chrome (sidebar→top-nav hoisting; see [includes/core/payload.php](https://github.com/WordPress/desktop-mode/blob/trunk/includes/core/payload.php) for the menu walker feeding the portal) | **Match WP-submenu count to in-page tab count, or suppress one surface.** Plugin v3.8.0 shipped 12 WP submenu entries + 6 in-page tabs in desktop-mode — looked like duplicate-nav. v3.8.1 fixed by reducing submenu registrations to 6 entries that match the 6 in-page tabs (with `sn_admin_top_tabs()` as the single source of truth). v3.8.4 follow-up fixed dock-submenu drift after the v3.8.1 reduction missed the parallel `desktop_mode_dock_items` filter surface — single-source-of-truth lesson recurring at a different layer. **General rule:** when your plugin owns BOTH the WP-admin submenu surface AND an in-page tab nav, the two MUST mirror the same set — or one must be suppressed (e.g., via `desktop_mode_dock_placement => 'hidden'` per gotcha #24). Otherwise desktop-mode collapses the visual distinction and renders duplicate-looking nav rows. |

If you hit a new one, add it here with the source pointer and the workaround. Future-you will thank present-you.
