# RSS Plausible Tracker — early Phase 4 slice

**Date:** 2026-05-15
**Status:** Approved (compact spec/plan)
**Releases:**
- Plugin `signal-and-noise-tools` `v1.1.0`
- Theme `signal-and-noise` `v8.2.1`

## Context

The MU plugin at `mu-plugins/rss-plausible-tracker.php` in the theme repo is awkwardly positioned:

- Lives in the **theme repo** but explicitly justifies itself as "not part of the theme" (per its docblock — "subscriber metrics should survive theme switches").
- Gets deployed by theme update to `wp-content/themes/signal-and-noise/mu-plugins/` (inert there) and **manually copied via SFTP** to `wp-content/mu-plugins/` to actually be active.
- Two-copies-of-same-file situation on disk, with the active one outside any package's normal management.

Phase 1 (the theme/plugin split) intentionally left this for Phase 4. The user asked to bring it forward before Phase 2 so the dual-state doesn't noise up Phase 2's diff. This spec covers that mini-phase.

## Audit findings

A grep audit of `mu-plugins/rss-plausible-tracker.php` confirmed it's a clean self-contained module:

- All 24 `sn_*` calls are self-references (functions named `sn_rss_tracker_*` defined within the file).
- Zero cross-coupling to theme-resident helpers.
- Zero theme-path or `SN_THEME_SLUG` / `SN_GITHUB_REPO` references.

This means no new contract hooks are required. The migration is a file move + bootstrap guard.

## Scope

### Plugin v1.1.0

- **New module:** `signal-and-noise-tools/inc/rss-plausible-tracker.php` — copied from theme's MU version with two adaptations:
  1. Drop the `Plugin Name: Signal & Noise — RSS Plausible Tracker` header block — file becomes a `require_once`d module, not a standalone plugin. Replace with a brief docblock noting its origin (theme MU plugin v1.2.0) and the migration history.
  2. Drop the "Deployment: copy this file to wp-content/mu-plugins/" instructions — irrelevant in the new context.
- **Bootstrap pre-flight guard #2** in `signal-and-noise-tools.php`: before `require_once`ing the rss tracker module, check `file_exists( WPMU_PLUGIN_DIR . '/rss-plausible-tracker.php' )`. If the MU plugin is still active on the live server, skip loading our copy (and emit a one-line admin notice telling the maintainer to remove the MU file at their leisure). Same pattern as guard #1 from v1.0.1.
- **Bootstrap include order:** `rss-plausible-tracker.php` goes at the **end** of the `require_once` chain (after `rest-api.php`). It's the most independent module and has the most cross-cutting hooks (cron, REST? no — it has its own action handlers). Position matters only for declaration order; it doesn't depend on anything else.
- **No DB migration.** Same table (`wp_rss_feed_log`), same option keys (`sn_rss_tracker_settings`, `sn_rss_tracker_db_version`). The `sn_rss_tracker_maybe_install()` function checks the DB version and is idempotent — running it again on plugin load is a no-op if the table is already current.
- **CHANGELOG entry** documenting the migration + the migration steps.

### Theme v8.2.1

- **Delete:** `mu-plugins/rss-plausible-tracker.php` from the theme repo.
- **Delete:** the `mu-plugins/` directory entirely if no other MU plugins are there. (Audit will confirm.)
- **Update [docs/WORDPRESS-REFERENCE.md](../../docs/WORDPRESS-REFERENCE.md) §10.0:** move `mu-plugins/rss-plausible-tracker.php` from "Phase 4 (deferred)" to "Phase 4 — done early in v8.2.1." Update phase summary text.
- **Update functions.php docblock:** remove any mention of `mu-plugins/`.
- **CHANGELOG entry** as a patch (8.2.1).

## Migration order (with the guard preventing footguns)

The dual-existence problem is the same shape as Phase 1's. Solution is the same: pre-flight guard means **no fatal regardless of order**.

1. **Ship plugin v1.1.0 first.** Maintainer installs via WP admin → plugin's guard sees `wp-content/mu-plugins/rss-plausible-tracker.php` still active → skips loading our tracker module → MU plugin continues serving tracking → admin notice tells maintainer to remove the MU file. **No fatal, no downtime, no data loss.**
2. **Maintainer manually deletes** `wp-content/mu-plugins/rss-plausible-tracker.php` via SFTP at their leisure. (Or via WP CLI: `wp mu-plugin delete rss-plausible-tracker` if WP CLI is available.)
3. **Next request:** plugin's guard sees the MU file gone → loads our tracker module → tracking continues seamlessly via the plugin (same option keys, same DB table).
4. **Ship theme v8.2.1.** Removes the now-orphan file from the theme repo so future theme updates don't reintroduce the MU plugin to `wp-content/themes/signal-and-noise/mu-plugins/` (where it's inert anyway, but housekeeping).

Steps 1 and 4 can ship in either order, since the plugin's guard handles whichever state the live server is in. But 1-first is cleaner: by the time the theme is cleaned up, the plugin is already serving the functionality.

## Data continuity

- **`wp_rss_feed_log` table:** untouched. Plugin reads/writes the same rows.
- **Options:** `sn_rss_tracker_settings`, `sn_rss_tracker_db_version` — same keys, same values.
- **Cron event:** `sn_rss_tracker_daily_prune` — same hook name. The MU plugin schedules it; when the MU plugin stops loading, the event remains scheduled but its handler is now in the active plugin (same function name `sn_rss_tracker_cron_prune`). WP fires the event → plugin's handler runs. Seamless.

## Out of scope

- Renaming functions, constants, options, table name, hook names — all stay identical for continuity.
- Touching other Phase 3 / Phase 4 modules — only this one.
- Phase 2 (updater migration) — still deferred to its own session.
- A `mu-plugins/` shadow bootstrapper that auto-loads the plugin — overkill; user can SFTP-delete the file when ready.

## Verification

Plugin v1.1.0:
- `grep -c "function sn_rss_tracker_install" signal-and-noise-tools/inc/rss-plausible-tracker.php` → 1
- Bootstrap pre-flight guard #2 present in `signal-and-noise-tools.php`
- `grep "Version" signal-and-noise-tools.php` → `1.1.0`
- `grep "SNT_VERSION" signal-and-noise-tools.php` → `1.1.0`
- CHANGELOG top entry: `## [1.1.0]`

Theme v8.2.1:
- `ls mu-plugins/ 2>&1` → no such directory (or empty)
- `grep "^Version:" style.css` → `8.2.0` (no — should be 8.2.1 after bump)
- CHANGELOG top entry: `## [8.2.1]`
- WORDPRESS-REFERENCE.md §10.0 updated to reflect Phase 4 partial completion
