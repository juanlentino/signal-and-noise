# Companion plugin — Phase 1: scaffold + easy moves

**Date:** 2026-05-15
**Status:** Approved
**Release:**
- Plugin `signal-and-noise-tools` `v1.0.0` (new repo, first release)
- Theme Signal & Noise `v8.2.0` (coordinated minor)

**Scope:** Multi-session initiative — this spec covers Phase 1 only. Phases 2–4 get their own brainstorming → spec → plan → execute cycles.

## Context

The theme has grown to hold operational tooling that, per the WordPress Theme Developer Handbook, belongs in a plugin. The v8.1.1 hygiene pass aligned what could be aligned without architectural change; the deliberate divergence remaining is "business logic in `inc/`" + "`mu-plugins/` shipped from the theme repo." This initiative refactors that into a proper theme + companion plugin split.

The user's stated motivation was "faster and lighter." Reframed honestly during brainstorming:

- **Lighter:** yes, real win. Theme directory becomes ~5 files in `inc/`; theme becomes swappable; concerns separate cleanly.
- **Faster:** mostly no — same WP-Cron pipeline, same Cloudways system-cron interval. The one place "faster" is achievable is *plugin distribution mechanism* (Phase 2 decision: GitHub-poll vs server-side `git pull` vs Composer); the plugin has flexibility the theme doesn't.

## Phase decomposition (parent project, four phases)

1. **Phase 1 — Scaffold + easy moves.** New plugin repo, plugin skeleton, 9 low-coupling modules move from theme to plugin. Site behavior bit-identical after activation. **This spec.**
2. **Phase 2 — Updater migration.** Plugin self-updater + theme updater migrates to plugin (plugin manages both). Plugin update mechanism gets decided.
3. **Phase 3 — Theme-coupled moves.** Decide which of `og-image.php`, `reading-time.php`, `notes-and-provenance.php`, `page-notes-*.php` move vs stay.
4. **Phase 4 — `mu-plugins` migration + final cleanup.** Pull `rss-plausible-tracker.php` into plugin; drop `mu-plugins/` from theme repo; settings ownership audit; docs update.

## Goal

Create `signal-and-noise-tools` plugin in a new repo, scaffold it with proper WP plugin structure, and move 9 low-coupling modules from theme `inc/` to the plugin. Site behavior is bit-identical after activation. Lowest-risk shake-out before Phases 2–4.

## New repo + plugin scaffolding

- **Repo:** `juanlentino/signal-and-noise-tools` (created 2026-05-15, private, GPL-2.0).
- **Plugin slug / directory:** `signal-and-noise-tools/`.
- **Bootstrap file:** `signal-and-noise-tools/signal-and-noise-tools.php` with standard WP plugin header. Fields: `Plugin Name`, `Plugin URI`, `Description`, `Version`, `Author`, `Author URI`, `License`, `License URI`, `Requires at least`, `Requires PHP`. No `Text Domain` (matches v8.1.1 hygiene — single-author surface, no i18n infrastructure).
- **Initial plugin version:** `1.0.0`. The plugin ships production code from day one (modules being moved are already in production via the theme).
- **Module organization:** flat `inc/` directory mirroring current theme structure. One file per concern. Same `sn_` and `signal_noise_` function-prefix conventions retained — function names don't change, because external callers (REST clients, cron hooks, action listeners, transient consumers) reference them by name.
- **Per-module includes pattern:** `signal-and-noise-tools.php` does `require_once` of each `inc/<module>.php` in dependency order, same shape as theme's [functions.php](../../functions.php).
- **Files at repo root:**
  - `signal-and-noise-tools.php` (bootstrap)
  - `inc/` (modules)
  - `README.md`
  - `CHANGELOG.md`
  - `LICENSE` (added by `gh repo create`)
  - `composer.json` (matches theme repo for consistency)
  - `.github/workflows/lint.yml` (PHP `-l` lint on every `.php` file, mirroring theme's existing lint job; HTTP smoke is theme's responsibility since the live URLs are theme-rendered)
  - `.gitignore` (vendor/, .DS_Store, etc.)

## Modules moving in Phase 1 (the 9 easy moves)

Each row: file moves from `themes/signal-and-noise/inc/<file>` to `plugins/signal-and-noise-tools/inc/<file>` byte-identical, except where cross-module dependency contracts replace direct function calls (see next section).

| File | Surface | Cross-coupling concerns |
| --- | --- | --- |
| `seo.php` | Meta description filter, Breeze cache excludes | None. Self-contained. |
| `security-headers.php` | HTTP security headers + XML-RPC, REST-users, `?author=N` hardening | None — pure HTTP filters. |
| `cloudflare-purge.php` | Auto-purge CF on `save_post` / `upgrader_process_complete` | None — calls CF API only. |
| `admin-page.php` | *Appearance → Signal & Noise* options page (4 tabs) | Calls `sn_purge_all_caches()` and `sn_self_heal_force_run()` (both in theme modules that STAY). See dependency strategy below. |
| `admin-bar.php` | Top-bar quick-action dropdown | Calls purge functions — same concern as admin-page. |
| `plausible-api.php` | Plausible Stats API client + SWR cache | None — self-contained API client. |
| `plausible-admin.php` | Settings tab for Plausible API key | None — uses WP options API. |
| `plausible-widget.php` | Dashboard widget set (4 panels) | None — reads from plausible-api cache. |
| `rest-api.php` | `signal-noise/v1` REST namespace, 8 endpoints | Calls `sn_purge_all_caches()`, template override clear, `sn_self_heal_force_run()`, and `wp_update_themes()` — same concern. |

**REST namespace stays as `signal-noise/v1`.** Clients (none external, but the namespace is the brand) shouldn't care which package serves it. Renaming would break nothing visible but pollute the namespace history.

## Cross-module dependency strategy

The single nuanced architectural decision in Phase 1. Three plugin modules (`admin-page.php`, `admin-bar.php`, `rest-api.php`) call functions currently defined in theme modules that **stay** in the theme:

- `sn_purge_all_caches()` lives in [inc/template-maintenance.php](../../inc/template-maintenance.php)
- `sn_self_heal_force_run()` lives in [inc/template-self-heal.php](../../inc/template-self-heal.php)
- Updater transient operations live in [inc/updater.php](../../inc/updater.php) (moves in Phase 2)

Plugin code calling into theme code is structurally backwards: plugins load before themes, so plugin code can't safely depend on theme symbols being defined at load time. (At request-handling time both are loaded, but the layering is wrong.)

**Resolution: WP action/filter contract.** Replace direct calls with `do_action()` / `apply_filters()` dispatches. Theme registers the listeners. Decouples cleanly; standard WP pattern.

### Action contracts (fire-and-forget)

```php
// In plugin code (e.g., admin-page.php purge handler):
do_action( 'sn_purge_all_caches', array( 'template_overrides' => false ) );

// In theme code (inc/template-maintenance.php), at the bottom of the file:
add_action( 'sn_purge_all_caches', 'sn_purge_all_caches', 10, 1 );
```

The existing `sn_purge_all_caches()` function in the theme becomes a listener for an identically-named action. Plugin code dispatches via the action; theme code answers. If theme is deactivated, action is a no-op (the WP design — `do_action` on an unregistered hook silently no-ops, no error).

### Filter contracts (return-value)

Some calls (notably `sn_self_heal_force_run()` in [inc/admin-page.php](../../inc/admin-page.php)) currently use return values. Plugin code dispatches via a filter:

```php
// In plugin code:
$heal = apply_filters( 'sn_self_heal_force_run_result', null );
if ( is_array( $heal ) ) {
    // existing display logic
}

// In theme code:
add_filter( 'sn_self_heal_force_run_result', function( $value ) {
    return sn_self_heal_force_run();
} );
```

`apply_filters` with no listeners returns the seed value (`null`); plugin code handles that as "self-heal not available."

### Updater transient operations

[inc/admin-page.php](../../inc/admin-page.php) currently directly deletes `sn_github_branch_*` transients on the *Check Now* button and calls `wp_update_themes()`. These continue to call theme-side helpers via the same pattern — wrap in `do_action( 'sn_updater_force_check' )` and theme registers a listener. The `wp_update_themes()` call itself is WP-core and works fine from plugin context.

### Audit needed during execution

Each of `admin-page.php`, `admin-bar.php`, and `rest-api.php` needs a full grep for `sn_*` function calls during execution. The three known cross-calls (`sn_purge_all_caches`, `sn_self_heal_force_run`, transient ops) are the main load-bearing ones; any others surface during the move and either join the contract pattern or move with their owning module if it makes sense.

## Install + activation flow (Phase 1)

This is intentionally manual for Phase 1; the plugin's own self-updater is Phase 2's deliverable.

1. **Release on plugin repo.** Commit + push to `juanlentino/signal-and-noise-tools` `main`, tag `v1.0.0`, push tag.
2. **Download zipball.** Maintainer downloads `https://github.com/juanlentino/signal-and-noise-tools/archive/refs/tags/v1.0.0.zip`.
3. **WP admin install.** Plugins → Add New → Upload Plugin → choose zip → Install Now → Activate.
4. **Rename if necessary.** WP unzips to `wp-content/plugins/signal-and-noise-tools-1.0.0/`. If the directory name mismatch causes activation issues, manually rename to `wp-content/plugins/signal-and-noise-tools/` via SFTP. (Phase 2's `upgrader_source_selection` filter will handle this automatically going forward, mirroring the theme's existing handler.)
5. **Activation hook is a no-op.** No cron events to schedule on activate (the moved modules schedule their own events the same way they did in the theme).
6. **Theme update.** Ship coordinated `v8.2.0` of the theme that drops the 9 `require_once` lines from [functions.php](../../functions.php) and registers the action/filter listeners.

## Theme-side changes for Phase 1

These ship as theme `v8.2.0`.

- [functions.php](../../functions.php): drop `require_once` for the 9 moved modules. Update the module-map docblock to reflect the reduced surface.
- Delete the 9 moved files from theme `inc/`: `seo.php`, `security-headers.php`, `cloudflare-purge.php`, `admin-page.php`, `admin-bar.php`, `plausible-api.php`, `plausible-admin.php`, `plausible-widget.php`, `rest-api.php`.
- Add `add_action`/`add_filter` listeners for the cross-module contracts. Listeners go at the bottom of the file that *owns* the underlying function:
  - [inc/template-maintenance.php](../../inc/template-maintenance.php): listener for `sn_purge_all_caches` action.
  - [inc/template-self-heal.php](../../inc/template-self-heal.php): listener for `sn_self_heal_force_run_result` filter.
  - [inc/updater.php](../../inc/updater.php): listener for `sn_updater_force_check` action.
- [docs/WORDPRESS-REFERENCE.md](../../docs/WORDPRESS-REFERENCE.md) §10: replace with a "Theme + companion plugin architecture" section describing the new split, the action/filter contract pattern, and which package owns what.
- [docs/WORDPRESS-REFERENCE.md](../../docs/WORDPRESS-REFERENCE.md) §13 (running list): add gotcha entry for the plugin-before-theme load order if it surfaces during execution.
- [CLAUDE.md](../../CLAUDE.md): mention the companion plugin repo + relationship.

## Versioning

- **Plugin: `v1.0.0`** — first release. SemVer from day one; no pre-1.0 churn.
- **Theme: `8.1.1` → `8.2.0`** (minor). Reasoning: meaningful capability shift in the theme — PHP includes shrink, contracts are introduced, the docblock module map changes — but no breaking user-visible change. First minor in the 8.x line; well within the 5-per-major cap.
- **Coordinated release.** Two-step deploy in this exact order:
  1. **Update theme to v8.2.0 first.** This deletes the 9 module files from the theme. After this step, the theme exposes only the action/filter listeners; the modules' implementations are absent. The *Signal & Noise* admin page disappears, REST endpoints 404, Plausible widgets vanish — but the public site keeps rendering normally.
  2. **Install + activate plugin v1.0.0.** This restores the admin surface (now plugin-owned) and wires its dispatches into the theme's listeners.
- **CORRECTION (post-release, see plugin v1.0.1 changelog).** The original spec proposed the *reverse* order — plugin first, then theme — and framed the "duplication window" between as cosmetic ("WP registers hooks twice"). That framing was wrong. The actual failure mode: while both packages have the 9 module files on disk simultaneously, PHP fatals at parse time with *"Cannot redeclare function sn_*"*. WordPress catches this during plugin activation and refuses to activate ("Plugin could not be activated because it triggered a fatal error."). WP hooks ARE idempotent (the `add_action` layer); PHP function declarations are NOT. These are two different WordPress layers and the original spec conflated them. The corrected order above (theme first) avoids the redeclare entirely — at the cost of a brief admin-surface-absent window between steps. Plugin v1.0.1 added a pre-flight guard that bails out gracefully if the maintainer tries to activate the plugin while the theme is still at v8.1.x; with v1.0.1+, attempting the reverse order produces an admin notice rather than a fatal, but the correct order remains: theme first.

## Verification

- **Plugin lint:** `find . -name '*.php' -exec php -l {} \;` from plugin repo root returns no errors. (CI workflow does this on every push.)
- **Plugin activates cleanly:** WP admin → Plugins → activate `signal-and-noise-tools` → no fatal, no admin notice complaints.
- **Admin page works:** *Appearance → Signal & Noise* loads, all 4 tabs render, *Purge All Caches* button still purges (verifies the action contract works), *Heal Templates* button still heals (verifies the filter contract works).
- **REST endpoints respond:** `curl -u <admin>:<pass> https://juanlentino.com/wp-json/signal-noise/v1/plausible/stats` returns the JSON shape it returned pre-move.
- **Plausible widgets render** on the WP dashboard with last-known cached data.
- **Cloudflare purge fires** on the next post save (verify in Plausible / CF dashboards or by triggering manually).
- **Smoke test CI** (theme repo): runs on push to theme `main`. The HTTP smoke against juanlentino.com still passes — pages render, no fatal from missing modules.

## Risks + rollback

- **Risk: activation order mishap.** Mitigation: action/filter contracts (`do_action`/`apply_filters` are no-ops when no listener is registered). Worst case: admin page shows "purge not available" instead of fataling.
- **Risk: missed cross-call.** Mitigation: full grep audit during execution (Step in Plan). Any missed `sn_*` call from plugin code into theme-only code surfaces as a PHP undefined-function fatal on the first request that hits it.
- **Risk: directory naming on install.** Mitigation: documented manual rename via SFTP if WP unzips to `signal-and-noise-tools-1.0.0/` instead of `signal-and-noise-tools/`. Phase 2 fixes this with the same `upgrader_source_selection` filter the theme uses.
- **Risk: plugin deactivated by mistake.** Recovery: reactivate. The options page disappears, REST 404s, but the theme keeps rendering the live site normally (none of the moved modules touch the public render path).
- **Rollback path.**
  1. Theme: revert the `v8.2.0` commit (restores the 9 deleted files + the requires + un-registers the listeners).
  2. Plugin: deactivate via WP admin, delete plugin directory.
  3. Both repos: tags remain in git history; can be re-deployed any time.

## Out of scope (Phase 1)

These are deliberately deferred:

- **Plugin's own self-updater** — Phase 2.
- **Migrating `updater.php`, `template-self-heal.php`, `template-maintenance.php` to plugin** — Phase 2 (architecturally entangled with the updater migration).
- **Plugin update mechanism decision** (GitHub-poll vs server-side `git pull` vs Composer) — Phase 2.
- **Moving `og-image.php`, `reading-time.php`, `notes-and-provenance.php`, `page-notes-*.php`** — Phase 3.
- **`mu-plugins/rss-plausible-tracker.php`** — Phase 4.
- **Settings/options key renaming** — never. Keep `sn_*` keys regardless of which package touches them. Renaming would require migration shims with zero benefit.
- **REST namespace rename** — never. `signal-noise/v1` stays.
- **Function name changes** — none. Same `sn_*` and `signal_noise_*` prefixes regardless of which package defines them. External callers (cron, REST, transient consumers, action listeners) reference by name.
