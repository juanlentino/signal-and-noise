# Signal & Noise — WordPress API Adoption Map

Generated: 2026-05-08 (knowledge cutoff: WP 7.0, releasing 2026-05-20)
Researcher: Claude (R2 research pass)
Repo: juanlentino/signal-and-noise
Theme version at audit: v7.2.7

## Purpose

This document maps every developer-facing WordPress API in the [Common APIs Handbook](https://developer.wordpress.org/apis/) (plus 6.9 and 7.0 dev-note additions) to a specific adoption decision for the Signal & Noise theme. Refresh this doc when a new major WP version ships.

The judgement throughout is "is this worth adopting *for a single-author, no-build, brutalist FSE theme that already self-updates from GitHub*?" — not "is this a good API?". Several genuinely interesting APIs (Connectors, sync providers, AI Client) get a hard skip because the theme has no plugin-distribution surface to expose them on.

## Top-line recommendations

1. **Adopt Block Bindings (WP 6.5+, expanded in 6.9/7.0)** for reading time, current year, OG image URLs, and Plausible widget values. Replaces `[sn_reading_time]` + `[current_year]` shortcodes with native FSE-editable bindings. **Highest ROI on this list.**
2. **Register patterns under `/patterns`** (file-header convention). The theme has 13 templates and zero patterns — patterns are how authors reuse hero/section layouts without duplicating template HTML.
3. **Register a custom REST route under `signal-noise/v1`** to wrap the existing maintenance actions (purge cache, heal templates, force update check, refresh Plausible). Then the admin page calls REST instead of admin-post + nonce form actions, and the same endpoints are reusable from CLI/external automation.
4. **Add a `/styles` style variation** (e.g. `monolith.json` for an even more reduced black-on-white scheme). Cheap, ships native FSE switcher.
5. **Register a custom template-part area** (`brand-mark`, `provenance-strip`, or similar) via `default_wp_template_part_areas` — currently every theme uses default header/footer only.
6. **Switch `[sn_reading_time]` to a Block Bindings source first, drop the shortcode in v8.0**. Same logic, modern surface, editor-visible value, no nested-shortcode pitfalls.
7. **Skip the AI Client / Abilities API / Connectors API entirely** for now. They are designed for distributed plugins exposing capabilities to external agents; a single-author site has nothing to expose and no agent driving it.
8. **Skip the Interactivity API** unless a future feature genuinely needs reactive UI. It mandates a build pipeline and the theme's "no-build" stance is load-bearing for the GitHub-driven self-updater.

## Adoption matrix

Sorted by descending Value, then ascending Cost. Value 1 (niche) → 5 (unlocks new product surface or solves an existing pain).

| API | WP version | Used? | Value | Cost | Risk | Suggestion |
|---|---|---|---|---|---|---|
| [Block Bindings API](https://developer.wordpress.org/block-editor/reference-guides/block-api/block-bindings/) | 6.5 (6.9 expanded, 7.0 overrides) | No | 5 | S | Low | Register `signal-noise/reading-time`, `signal-noise/current-year`, `signal-noise/og-image-url`, `signal-noise/plausible-pageviews` sources. Retire shortcodes in 8.0. |
| [Block Patterns (theme `/patterns/`)](https://developer.wordpress.org/themes/patterns/registering-patterns/) | 5.5+ | No | 5 | S | Low | Add `/patterns/` directory. Extract repeated hero/section markup from 13 templates into 4–6 patterns. Register a `signal-noise/*` category. |
| [REST API custom routes](https://developer.wordpress.org/rest-api/extending-the-rest-api/adding-custom-endpoints/) | 4.4+ | No (uses admin-post forms) | 4 | S | Low | Move maintenance actions to `signal-noise/v1/{purge-cache,heal-templates,refresh-plausible,check-updates}`. Permission callback = `manage_options`. |
| [Style Variations (`/styles/`)](https://developer.wordpress.org/themes/global-settings-and-styles/style-variations/) | 6.0+ | No | 4 | XS | Low | Ship one or two variations (`monolith.json`, `inverted.json`). Theme is already brutalist — variations are essentially free. |
| [Transients API](https://developer.wordpress.org/apis/transients/) | core | Yes (5 callsites) | 4 | — | — | Already used well in plausible-api/template-self-heal/admin-page. No change needed. |
| [HTTP API](https://developer.wordpress.org/plugins/http-api/) | core | Yes (`wp_remote_get` in updater, self-heal, plausible) | 4 | — | — | Already correct. Audit timeouts (10s self-heal, 5s default elsewhere) — that's the right call. |
| [WP-Cron API](https://developer.wordpress.org/plugins/cron/) | core | Partial (`wp_schedule_single_event` only) | 3 | XS | Low | Add `wp_schedule_event('twicedaily', 'sn_check_updates')` so update polling doesn't depend on admin page loads. |
| [Hooks API (actions/filters)](https://developer.wordpress.org/apis/hooks/) | core | Yes (every file) | 3 | — | — | Add a few `do_action('signal_noise/before_heal_template', $slug)` style hooks so future modules / debugging can subscribe. Cheap. |
| [Settings API](https://developer.wordpress.org/apis/settings/) | core | No (admin-page.php uses raw `update_option`) | 3 | S | Low | Replace ad-hoc form handling in plausible-admin.php with `register_setting()` + section/field. Solves the "is this nonced correctly?" question once. |
| [Custom Template Part Areas](https://developer.wordpress.org/themes/templates/template-parts/) | 5.9+ | No (only default header/footer) | 3 | XS | Low | Filter `default_wp_template_part_areas` to add a `brand-mark` or `provenance-strip` area. Lightweight FSE polish. |
| [Filesystem API](https://developer.wordpress.org/apis/filesystem/) | core | Yes (template-self-heal.php uses `WP_Filesystem`) | 3 | — | — | Already correct. Don't regress to direct `file_put_contents`. |
| [`customTemplates` in theme.json](https://developer.wordpress.org/themes/global-settings-and-styles/settings/custom-templates/) | 5.9+ | Partial (registered but no payoff visible) | 3 | XS | Low | Audit current `customTemplates` block in theme.json — check every entry maps to a real `/templates/*.html` and is reachable in the page editor. |
| [Streaming Block Parser (`WP_Block_Processor`)](https://make.wordpress.org/core/2025/11/19/introducing-the-streaming-block-parser-in-wordpress-6-9/) | 6.9 | No | 3 | M | Medium | Use it inside `inc/reading-time.php` to count words from `post_content` without `parse_blocks()`. Real perf win on long Notes posts. |
| [HTML API (6.9 updates)](https://make.wordpress.org/core/2025/11/21/updates-to-the-html-api-in-6-9/) | 6.2+ (6.9 expanded) | No | 3 | M | Low | Use in `og-image.php` to extract first image / heading text from a post safely instead of regex. |
| [Pattern Overrides (custom blocks)](https://make.wordpress.org/core/2026/03/16/pattern-overrides-in-wp-7-0-support-for-custom-blocks/) | 7.0 | No | 2 | M | Medium | Skip — theme has no custom blocks. Revisit if v8 introduces any. |
| [Block Visibility (viewport)](https://make.wordpress.org/core/2026/03/15/block-visibility-in-wordpress-7-0/) | 7.0 (basic in 6.9) | No | 2 | XS | Low | Useful for the brutalist mobile-vs-desktop differences. Mostly an authoring affordance — no theme code change needed, but worth documenting for the maintainer. |
| [Options API](https://developer.wordpress.org/apis/options/) | core | Yes (`get_option`/`update_option` in plausible-admin, updater) | 2 | — | — | Already used. Watch autoload — large options should pass `'no'` for autoload. Audit current options for autoload abuse. |
| [Shortcode API](https://developer.wordpress.org/apis/shortcode/) | 2.5+ | Yes (`[sn_reading_time]`, `[current_year]`) | 2 | — | — | Hold for now, retire in 8.0 once Block Bindings replacements ship. |
| [WP-CLI commands](https://developer.wordpress.org/cli/commands/) | external | No custom commands | 2 | S | Low | Register `wp signal-noise purge-cache | heal-templates | refresh-plausible`. Wraps the same callbacks as the new REST routes. |
| [Pattern Editing (7.0 contentOnly)](https://make.wordpress.org/core/2026/03/15/pattern-editing-in-wordpress-7-0/) | 7.0 | N/A | 2 | XS | Low | Once patterns are registered (rec #2), audit content-attribute roles. No work until then. |
| [Hide Blocks (6.9)](https://make.wordpress.org/core/2025/12/01/ability-to-hide-blocks/) | 6.9 | N/A | 2 | XS | Low | Editor-only feature. No theme code needed. Document for maintainer. |
| [Roster of design tools per block (7.0)](https://make.wordpress.org/core/2026/04/22/roster-of-design-tools-per-block-wordpress-7-0/) | 7.0 | N/A | 2 | — | — | Reference doc only — informs theme.json edits as they come up. |
| [Abilities API (PHP, 6.9)](https://make.wordpress.org/core/2025/11/10/abilities-api-in-wordpress-6-9/) | 6.9 | No | 1 | M | Medium | Skip — single-author site has no need to expose capabilities to external agents. Revisit only if a personal AI workflow needs to drive the site. |
| [Client-Side Abilities API (7.0)](https://make.wordpress.org/core/2026/03/24/client-side-abilities-api-in-wordpress-7-0/) | 7.0 | No | 1 | L | Medium | Skip — requires a JS build, single-user, no agents involved. |
| [AI Client (7.0)](https://make.wordpress.org/core/2026/03/24/introducing-the-ai-client-in-wordpress-7-0/) | 7.0 | No | 1 | M | Medium | Skip — `wp_ai_client_prompt()` is for content/feature plugins. The theme has no UI surface that calls AI. Auto-tagging Notes could be a future fit. |
| [Connectors API (7.0)](https://make.wordpress.org/core/2026/03/18/introducing-the-connectors-api-in-wordpress-7-0/) | 7.0 | No | 1 | M | Medium | Skip — connectors are a *site-admin* surface, not a theme one. The Cloudflare/Plausible integrations live in `inc/` because they're theme behaviour, not user-configurable connections. |
| [Interactivity API](https://developer.wordpress.org/block-editor/reference-guides/interactivity-api/) | 6.5+ | No | 1 | L | Medium | Skip until a feature actually needs it. Adopting it forces in `wp-scripts` + `node_modules` and breaks the no-build invariant the GitHub self-updater depends on. |
| [Custom sync providers (7.0)](https://make.wordpress.org/core/2026/04/01/building-a-custom-sync-provider-for-real-time-collaboration/) | 7.0 | No | 1 | L | High | Skip — single author. Multi-user collab is a non-goal. |

## Per-API detail

Sections below cover only the APIs in the **Top-line recommendations** plus the items already in active use in the codebase. Lower-value rows in the matrix are intentionally not expanded — the matrix line is the whole answer.

### Block Bindings API — adopt

Docs: [block-api/block-bindings](https://developer.wordpress.org/block-editor/reference-guides/block-api/block-bindings/) · added 6.5, expanded 6.9 (`core/post-data`, `core/term-data`), 7.0 extends overrides to custom-block attributes.

**What it is.** A server-side registry that lets you bind a block's attribute (e.g. a Paragraph's `content`, an Image's `url`) to a value produced by a PHP callback. The block in the editor still renders the literal placeholder; the front end resolves the binding on render. Built-in sources include `core/post-meta`, `core/post-data`, `core/term-data`, and `core/pattern-overrides`.

**S&N integration.** The theme has two shortcodes (`[sn_reading_time]`, `[current_year]`) and a few rendered values (OG image URL, Plausible page-view count for the current post) that all want to live inside Paragraph/Heading blocks rather than in raw template HTML. Block Bindings is exactly the right replacement.

Suggested file: `inc/block-bindings.php` (new, ~60 lines), wired from `functions.php`.

```php
add_action( 'init', function () {
    register_block_bindings_source( 'signal-noise/reading-time', array(
        'label'              => __( 'Reading time', 'signal-noise' ),
        'get_value_callback' => function ( $args, $block ) {
            $post_id = $block->context['postId'] ?? get_the_ID();
            return signal_noise_reading_time( $post_id ); // existing fn
        },
    ) );

    register_block_bindings_source( 'signal-noise/current-year', array(
        'label'              => __( 'Current year', 'signal-noise' ),
        'get_value_callback' => fn() => date( 'Y' ),
    ) );
} );
```

**Caveats.** Bindings only work on the [supported core block attributes](https://developer.wordpress.org/block-editor/reference-guides/block-api/block-bindings/#compatible-blocks). For OG image URL on `core/image` you bind `url`. The shortcodes can stay in parallel through 7.x and be removed in 8.0 with a migration note in CHANGELOG.

### Block Patterns under `/patterns/` — adopt

Docs: [Registering patterns](https://developer.wordpress.org/themes/patterns/registering-patterns/).

**What it is.** Drop a PHP file with a header comment into `theme/patterns/`. WordPress reads the header (Title, Slug, Categories) and registers it. No PHP function call needed.

**S&N integration.** The theme has 13 page templates (`page-about.html` … `page-work-with-me.html`) which almost certainly share repeated layouts: hero, two-column manifesto, project-card row, contact CTA, etc. Right now every duplication lives as raw block markup in each template — a refactor target.

Suggested structure:
```
patterns/
  hero-statement.php        # cat: signal-noise/hero
  manifesto-two-column.php  # cat: signal-noise/section
  project-card-row.php      # cat: signal-noise/section
  contact-cta.php           # cat: signal-noise/cta
  notes-index-grid.php      # cat: signal-noise/notes
```

Plus a small registrar in `inc/patterns.php` that calls `register_block_pattern_category( 'signal-noise/hero', [ 'label' => 'Signal & Noise — Hero' ] )` etc. on `init`.

**Why now.** Patterns survive across templates and across self-heal: when `template-self-heal.php` re-fetches a template HTML file, the pattern reference inside it stays a single source of truth.

### REST API custom route under `signal-noise/v1` — adopt

Docs: [Adding custom endpoints](https://developer.wordpress.org/rest-api/extending-the-rest-api/adding-custom-endpoints/).

**What it is.** `register_rest_route( 'vendor/v1', '/foo', $args )`. Takes a `permission_callback` (must return bool/`WP_Error`), a `callback`, and an optional `args` map for validation/sanitization.

**S&N integration.** Right now `inc/admin-page.php` and `inc/template-maintenance.php` handle maintenance via classic admin-post forms with nonces. That works, but it's not scriptable from outside the admin UI and is harder to test. A REST surface is a strict superset.

Suggested file: `inc/rest-api.php`.

```php
add_action( 'rest_api_init', function () {
    $permission = fn() => current_user_can( 'manage_options' );

    register_rest_route( 'signal-noise/v1', '/purge-cache', array(
        'methods'             => 'POST',
        'permission_callback' => $permission,
        'callback'            => 'signal_noise_purge_all_caches', // existing fn
    ) );

    register_rest_route( 'signal-noise/v1', '/heal-templates', array(
        'methods'             => 'POST',
        'permission_callback' => $permission,
        'callback'            => 'signal_noise_heal_templates_now',
    ) );

    register_rest_route( 'signal-noise/v1', '/check-updates', array(
        'methods'             => 'POST',
        'permission_callback' => $permission,
        'callback'            => 'signal_noise_force_update_check',
    ) );
} );
```

**Caveats.** Don't accept untrusted input on these — they're admin-only operations. The `permission_callback` is mandatory in modern REST; do not use `__return_true` here.

### Style Variations under `/styles/` — adopt

Docs: [Style variations](https://developer.wordpress.org/themes/global-settings-and-styles/style-variations/).

**What it is.** Drop alternative `theme.json`-shaped files into `theme/styles/`. WordPress shows them in the Site Editor → Styles browser. User selects → JSON is migrated into the database as a customization.

**S&N integration.** Theme is currently single-style. A second variation costs effectively nothing and shows off the brutalist palette flexibility. Suggested: `monolith.json` (even more reduced — drop accent red entirely) and/or `inverted.json` (white-on-black). One file each, ~30 lines.

**Caveats.** Selecting a variation copies it to the database, so subsequent theme updates do not retroactively update the user's selection. Document this in the maintainer's mental model — switching back to default and reselecting is the only way to pick up new variation tweaks.

### Custom template-part area — adopt

Docs: [Template parts](https://developer.wordpress.org/themes/templates/template-parts/).

**What it is.** `default_wp_template_part_areas` filter registers a new area (slug, label, icon, description). The area is then referenced in `theme.json` and in template-part block attributes.

**S&N integration.** Theme has only `header.html` and `footer.html` in `parts/`. A `provenance-strip.html` part (used on `page-provenance.html`, single-post footer, and Notes index) would be a real win — it dedupes content and gives the maintainer a single edit point.

Suggested wiring in `inc/setup.php`:

```php
add_filter( 'default_wp_template_part_areas', function ( $areas ) {
    $areas[] = array(
        'area'        => 'provenance-strip',
        'area_tag'    => 'aside',
        'label'       => __( 'Provenance strip', 'signal-noise' ),
        'description' => __( 'Citation/provenance band reused across single posts and the Notes pillar page.', 'signal-noise' ),
        'icon'        => 'sidebar',
    );
    return $areas;
} );
```

Plus the corresponding `theme.json` `templateParts` entry and a `parts/provenance-strip.html` file.

### Settings API — adopt for plausible-admin only

Docs: [Settings API](https://developer.wordpress.org/apis/settings/) · [Options API](https://developer.wordpress.org/apis/options/).

**Current state.** `inc/plausible-admin.php` uses `update_option` directly with manual nonce handling. That is fine but reinvents the wheel. The Settings API gives form-action + nonce + capability check + sanitization in one bundle.

**Suggested change.** `register_setting( 'signal_noise_plausible', 'sn_plausible_stats_token', [...] )` plus `add_settings_section` and `add_settings_field` calls under an `admin_init` hook. The form posts to `options.php` instead of self-posting. Lines saved: maybe 30. Bug surface eliminated: real.

Don't touch the main S&N admin page (`inc/admin-page.php`) — that page is action buttons, not a settings form. Migrate it to REST per the rec above instead.

### WP-Cron API — partial adoption

Docs: [Plugin Handbook → Cron](https://developer.wordpress.org/plugins/cron/), [`wp_schedule_event()`](https://developer.wordpress.org/reference/functions/wp_schedule_event/).

**Current state.** Theme uses `wp_schedule_single_event` only — for self-heal and Plausible refresh — and only on demand. There's no recurring cron in the theme.

**Recommended addition.** `wp_schedule_event( time(), 'twicedaily', 'sn_check_updates' )` paired with the existing GitHub-SHA poll in `inc/updater.php`, so update detection no longer depends on the admin page being viewed. Register a custom interval via the `cron_schedules` filter only if `twicedaily` is too coarse — usually it isn't.

**Caveats.** WP-Cron is page-load-driven by default. On a low-traffic personal site this matters: an event scheduled for 02:00 won't fire until somebody hits a page. For the SHA poll that's actually fine (the next admin visit gets the freshest poll). For anything time-critical, set up a real cron job hitting `wp-cron.php` and define `DISABLE_WP_CRON` — but the theme doesn't have any time-critical scheduled work today.

### HTML API + Streaming Block Parser — adopt selectively

Docs: [HTML API 6.9 updates](https://make.wordpress.org/core/2025/11/21/updates-to-the-html-api-in-6-9/), [streaming block parser](https://make.wordpress.org/core/2025/11/19/introducing-the-streaming-block-parser-in-wordpress-6-9/).

**Where they help.** Two specific places:
- `inc/reading-time.php` currently almost certainly word-counts the full post HTML. `WP_Block_Processor` with `extract_full_block_and_advance()` lets it walk only `core/paragraph` and `core/heading` blocks, skipping image captions and embed shells. Faster and more accurate, especially on Notes posts that include embeds and pull-quotes.
- `inc/og-image.php` currently uses GD to render text. If it ever needs to extract the first heading or first image from a post for OG fallback, the HTML API (`WP_HTML_Tag_Processor`) is the right tool — it handles malformed markup safely, regex doesn't.

**Cost honestly assessed.** This is M (4–16h) only because the existing reading-time logic is 396 lines and likely has caching coupling that needs careful refactoring. The streaming API itself is small.

### Already-used APIs (no-change baseline)

These are fine as-is. Documented for completeness.

- **Transients API** — 5 callsites. `inc/plausible-api.php` uses stale-while-revalidate cache on the Plausible Stats responses; `inc/template-self-heal.php` caches drift-check timestamps; `inc/admin-page.php` caches GitHub release lookups. All pass arrays (per the docs' "don't store bare booleans" pitfall). Nothing to fix.
- **HTTP API** — `wp_remote_get` in `updater.php` (GitHub API), `template-self-heal.php` (raw file fetch with 10s timeout), `plausible-api.php`, and `admin-page.php`. All use `wp_remote_retrieve_body` / `wp_remote_retrieve_response_code` correctly and check `is_wp_error`. Audit only: confirm timeouts aren't unbounded.
- **Filesystem API** — `inc/template-self-heal.php` calls `WP_Filesystem()` before any write. Correct. Don't regress to direct `file_put_contents`.
- **Hooks API** — used everywhere. The one missing pattern is *theme-internal* `do_action` hooks. Adding `do_action( 'signal_noise/before_heal_template', $slug )` etc. is cheap and pays back the next time the maintainer wants to instrument this.
- **Shortcode API** — `[sn_reading_time]` and `[current_year]` only. Hold until 8.0, then migrate to Block Bindings and remove.

### Hard skips with reasons

- **Abilities API (PHP) / Client-Side Abilities API / AI Client / Connectors API** — these four are the new WP 6.9/7.0 feature stack and they look exciting on the dev-note tag, but they all assume a *site* exposing capabilities to *external agents/users/services*. S&N is a single-author personal site. There is no agent driving it, no plugin distribution surface, no marketplace integration. The Cloudflare and Plausible integrations live in the theme intentionally — they're not user-configurable connections, they're theme behaviour. Re-evaluate when (a) the maintainer wants to drive the site from a personal AI workflow, or (b) any of these stop being plugin-shaped and become theme-shaped APIs.
- **Interactivity API** — would force in `wp-scripts` / `node_modules` / a build step. The GitHub-driven self-updater (`inc/updater.php`) ships built theme files directly from `main`. Introducing a build step means either committing built artifacts (ugly) or running CI on every push (overkill for a personal theme). Skip until a feature genuinely needs reactive UI.
- **Custom sync providers (7.0)** — explicitly multi-user collaboration. Non-goal.
- **Pattern Overrides for custom blocks (7.0)** — theme has no custom blocks. Revisit if v8 adds any.

## Adjacent surfaces (not in Common APIs Handbook but relevant)

### `@wordpress/*` JS packages — do not introduce a build pipeline yet

Survey of relevant packages on npm: `@wordpress/interactivity`, `@wordpress/interactivity-router`, `@wordpress/abilities`, `@wordpress/core-abilities`, `@wordpress/block-editor`, `@wordpress/blocks`, `@wordpress/create-block-interactive-template`. All are part of the Gutenberg monorepo and ship with core for any feature that's been promoted to core (Interactivity, Abilities client, AI Client).

**Recommendation.** Don't add `package.json` + `wp-scripts` to S&N. The theme's no-build invariant is load-bearing. If a single feature in the future genuinely needs JS (e.g. a Notes-page filter UI), prefer a vanilla JS file enqueued via `wp_enqueue_script_module` over a wp-scripts build. Re-evaluate the build question only when there are 3+ such features.

### WP-CLI commands worth wrapping

Once the REST routes exist (rec #3), wrapping them as WP-CLI commands is small. Suggested:

```
wp signal-noise purge-cache
wp signal-noise heal-templates
wp signal-noise check-updates
wp signal-noise refresh-plausible
```

Implementation: a single `inc/cli.php` file gated on `defined( 'WP_CLI' ) && WP_CLI` that registers a class with `WP_CLI::add_command( 'signal-noise', Signal_Noise_CLI::class )`. Each method calls the same callbacks as the REST routes. Cost: S.

### Theme Handbook surfaces still on the table

- `customTemplates` in `theme.json` — already declared. Audit pass to confirm every entry maps to a real template file.
- `templateParts` in `theme.json` — declared. Will need an entry per new custom area registered.
- Block style variations (per-block, e.g. `core/button` brutalist variant) — `register_block_style()` is a clean way to expose theme-specific button/heading styles in the inserter without raw CSS class memory. Worth a small adoption pass — call it Cost: S, Value: 2.

### WordPress Coding Standards / PHPCS

Not currently integrated. For a single-author theme with no PR review, the ROI of full WPCS adoption is moderate. **Recommendation:** add a minimal `phpcs.xml.dist` that runs `WordPress-Core` ruleset only on `inc/` and `functions.php`, run it as a pre-commit hook locally. Skip the heavier `WordPress-Extra` and `WordPress-Docs` rulesets — they generate noise that doesn't pay off without a team enforcing it.

### Block style variations (`register_block_style`)

[register_block_style](https://developer.wordpress.org/reference/functions/register_block_style/). Lets the theme add named CSS classes selectable from the block inspector ("Outline button", "All-caps heading"). For a brutalist theme this is the right place to expose the small set of stylistic variants instead of asking the maintainer to remember class names.

## Refresh cadence

Re-run this audit:
- After each WordPress **minor** release (currently every ~6 months) — light pass, just check the dev-note tag and update any "skip" rationales that flipped.
- After each **major** release — full re-pass.
- When the theme adopts a build step, a custom block, or a public REST route — that changes the answer for several rows (Interactivity API, Pattern Overrides, Abilities client all become more attractive once there's a JS build).

## Sources

- [Common APIs Handbook (index)](https://developer.wordpress.org/apis/)
- [Abilities API handbook page](https://developer.wordpress.org/apis/abilities-api/)
- [Block Bindings API reference](https://developer.wordpress.org/block-editor/reference-guides/block-api/block-bindings/)
- [Interactivity API reference](https://developer.wordpress.org/block-editor/reference-guides/interactivity-api/)
- [REST API — adding custom endpoints](https://developer.wordpress.org/rest-api/extending-the-rest-api/adding-custom-endpoints/)
- [Settings API handbook page](https://developer.wordpress.org/apis/settings/)
- [Options API handbook page](https://developer.wordpress.org/apis/options/)
- [Transients API handbook page](https://developer.wordpress.org/apis/transients/)
- [HTTP API plugin handbook](https://developer.wordpress.org/plugins/http-api/)
- [`wp_remote_get` reference](https://developer.wordpress.org/reference/functions/wp_remote_get/)
- [WP-Cron plugin handbook](https://developer.wordpress.org/plugins/cron/)
- [`wp_schedule_event` reference](https://developer.wordpress.org/reference/functions/wp_schedule_event/)
- [Filesystem API handbook page](https://developer.wordpress.org/apis/filesystem/)
- [Shortcode API handbook page](https://developer.wordpress.org/apis/shortcode/)
- [Hooks API handbook page](https://developer.wordpress.org/apis/hooks/)
- [Theme Handbook — index](https://developer.wordpress.org/themes/)
- [Theme Handbook — Registering patterns](https://developer.wordpress.org/themes/patterns/registering-patterns/)
- [Theme Handbook — Style variations](https://developer.wordpress.org/themes/global-settings-and-styles/style-variations/)
- [Theme Handbook — Template parts](https://developer.wordpress.org/themes/templates/template-parts/)
- [Theme Handbook — Custom templates](https://developer.wordpress.org/themes/global-settings-and-styles/settings/custom-templates/)
- [WP 7.0 dev-notes tag](https://make.wordpress.org/core/tag/dev-notes+7-0/)
- [Client-Side Abilities API in WP 7.0](https://make.wordpress.org/core/2026/03/24/client-side-abilities-api-in-wordpress-7-0/)
- [Introducing the AI Client in WP 7.0](https://make.wordpress.org/core/2026/03/24/introducing-the-ai-client-in-wordpress-7-0/)
- [Introducing the Connectors API in WP 7.0](https://make.wordpress.org/core/2026/03/18/introducing-the-connectors-api-in-wordpress-7-0/)
- [Pattern Editing in WP 7.0](https://make.wordpress.org/core/2026/03/15/pattern-editing-in-wordpress-7-0/)
- [Pattern Overrides for custom blocks in WP 7.0](https://make.wordpress.org/core/2026/03/16/pattern-overrides-in-wp-7-0-support-for-custom-blocks/)
- [Block Visibility in WP 7.0](https://make.wordpress.org/core/2026/03/15/block-visibility-in-wordpress-7-0/)
- [Roster of design tools per block (WP 7.0)](https://make.wordpress.org/core/2026/04/22/roster-of-design-tools-per-block-wordpress-7-0/)
- [Building a custom sync provider for real-time collaboration (WP 7.0)](https://make.wordpress.org/core/2026/04/01/building-a-custom-sync-provider-for-real-time-collaboration/)
- [WP 6.9 dev-notes tag](https://make.wordpress.org/core/tag/dev-notes+6-9/)
- [Abilities API in WP 6.9](https://make.wordpress.org/core/2025/11/10/abilities-api-in-wordpress-6-9/)
- [Streaming block parser in WP 6.9](https://make.wordpress.org/core/2025/11/19/introducing-the-streaming-block-parser-in-wordpress-6-9/)
- [HTML API updates in WP 6.9](https://make.wordpress.org/core/2025/11/21/updates-to-the-html-api-in-6-9/)
- [Hide Blocks in WP 6.9](https://make.wordpress.org/core/2025/12/01/ability-to-hide-blocks/)
- [Miscellaneous editor changes in WP 6.9](https://make.wordpress.org/core/2025/11/25/miscellaneous-editor-changes-in-wordpress-6-9/)
- [PHP 8.5 support in WP 6.9](https://make.wordpress.org/core/2025/11/21/php-8-5-support-in-wordpress-6-9/)
- [`@wordpress/interactivity` on npm](https://www.npmjs.com/package/@wordpress/interactivity)
- [WP-CLI commands index](https://developer.wordpress.org/cli/commands/)
- [`register_block_style` reference](https://developer.wordpress.org/reference/functions/register_block_style/)
