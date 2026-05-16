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

## 10. The self-updater + self-heal architecture (with companion plugin since v8.2.0)

This theme has a non-standard update mechanism AND a companion plugin that holds operational tooling. Understand both before touching `inc/updater.php`, `inc/template-self-heal.php`, or any of the WP hook contracts listed below.

### 10.0 The theme + companion plugin split (Phase 1 — v8.2.0 / Tools v1.0.0)

The theme is presentation; the companion plugin [`signal-and-noise-tools`](https://github.com/juanlentino/signal-and-noise-tools) holds operational tooling. They communicate via 7 WP hooks. **The split is partial as of v8.2.0** — 9 modules moved (Phase 1); Phases 2–4 will migrate the rest. See `docs/superpowers/specs/2026-05-15-companion-plugin-phase-1-design.md` and successors.

**Modules currently in plugin:**
- *Phase 1 moves (v8.2.0 / Tools v1.0.0):* `seo.php`, `security-headers.php`, `cloudflare-purge.php`, `plausible-api.php`, `plausible-admin.php`, `plausible-widget.php`, `admin-bar.php`, `admin-page.php`, `rest-api.php`.
- *Early Phase 4 slice (v8.2.1 / Tools v1.1.0):* `rss-plausible-tracker.php` (was theme's `mu-plugins/rss-plausible-tracker.php`).

**Modules still in theme (will migrate in Phases 2–3):** `updater.php`, `template-self-heal.php`, `template-maintenance.php` (Phase 2); `og-image.php`, `reading-time.php`, `notes-and-provenance.php`, `page-notes-*.php` (Phase 3).

**Phase 4 is now empty** — the only file it was scheduled to migrate (the RSS tracker MU plugin) shipped early in v8.2.1. The `mu-plugins/` directory no longer exists in the theme repo.

**Contract hooks — 2 remain (was 7 before v8.3.0 — see Phase 2b):**

| Hook | Type | Dispatched by plugin | Implemented by theme |
| --- | --- | --- | --- |
| `sn_purge_all_caches_result` | filter | `apply_filters( 'sn_purge_all_caches_result', 0, $args )` returns int count | [`inc/template-maintenance.php`](../inc/template-maintenance.php) wraps `sn_purge_all_caches()` |
| `sn_clear_template_overrides_result` | filter | `apply_filters( 'sn_clear_template_overrides_result', 0 )` returns int count | [`inc/template-maintenance.php`](../inc/template-maintenance.php) wraps `sn_clear_template_overrides()` |

> **Retired in theme v8.3.0 (Phase 2b):** the 5 updater/self-heal contracts (`sn_self_heal_force_run_result`, `sn_updater_branch`, `sn_updater_revcount`, `sn_updater_force_check`, `sn_updater_clear_error`). See [Phase 2b spec](superpowers/specs/2026-05-15-phase-2b-cleanup-design.md).

**Direct dependencies kept (no contract — stable by design):**
- `sn_*` option keys (e.g. `sn_github_local_sha`) — plugin reads via `get_option()`.
- `sn_github_*` transient keys — plugin reads via `get_transient()`. Option/transient *key names* are part of the public contract surface; renaming them would require migration shims for zero benefit.
- `SN_GITHUB_REPO` / `SN_THEME_SLUG` constants — plugin reads with `defined()` guard.

**When adding new cross-package interactions:** add a row to the table above and document the listener side in the theme file that owns the underlying function. **Never let plugin code directly call a theme function — even with `function_exists` guards.** The contract pattern is non-negotiable.

### 10.1 The updater — RETIRED in v8.3.0

The GitHub-poll self-updater (`inc/updater.php`) and the associated
`sn_updater_*` contracts were removed in theme v8.3.0 (2026-05-15) when
Phase 2b landed. Theme deploys now ride Cloudways' git-pull on tag push
(see [Phase 2a spec](superpowers/specs/2026-05-15-cloudways-auto-deploy-design.md))
which makes the WP-Cron SWR refresh + filter-injection layer redundant.

If you're maintaining a fork that still needs in-WP update polling,
restore from git history at the v8.2.1 tag.

### 10.2 Self-heal — RETIRED in v8.3.0

The file-drift recovery module (`inc/template-self-heal.php`) was removed
in theme v8.3.0. Under Cloudways' git-pull deploys, the file tree is
atomically consistent with the deployed commit — there's nothing to "heal."

The `/heal-templates` plugin REST endpoint was retired in plugin v1.2.0
to match.

### 10.3 The synthetic update label

Format: `{Version}{-rN}+{branch}.{sha7}`

- `{Version}` — theme `Version:` header from `style.css`
- `-rN` — count of commits ahead of `v{Version}` tag (suppressed if 0)
- `+branch.sha7` — branch + 7-char SHA being offered

Example: `8.0.5-r3+main.78048f2` reads as "3rd commit on main since v8.0.5 was tagged, at SHA 78048f2."

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

If you hit a new one, add it here with the source pointer and the workaround. Future-you will thank present-you.
