# Signal & Noise — desktop-mode integration (plugin v1.15.0)

**Date:** 2026-05-16
**Target release:** plugin v1.15.0
**Status:** Approved — proceeding to implementation
**External dependency:** [`WordPress/desktop-mode`](https://github.com/WordPress/desktop-mode) (optional — every integration `function_exists()`-gated)

## Goal

Make the SN plugin a first-class participant in desktop-mode when the user has it active. SN actions and pages become reachable via:
1. **Dock icon + submenu + desktop icons** (always-on visibility)
2. **Command palette (Cmd+K)** for 13 commands (Maintenance + Navigation + Info)
3. **Desktop widget** showing live deploy status

All gated on `function_exists('desktop_mode_register_*')` — plugin behavior is **identical** when desktop-mode is inactive or uninstalled.

## API reference (verified against desktop-mode docs)

| Function | Args | Hook |
|---|---|---|
| `desktop_mode_register_command( array )` | `slug, label, description?, icon?, hint?, script?` | `admin_enqueue_scripts` |
| `desktop_mode_register_widget( id, array )` | `label, script, sort?` | `init` or `admin_enqueue_scripts` |
| `desktop_mode_register_icon( id, array )` | `title, icon, window\|url, position?` | `init` or `admin_enqueue_scripts` |
| `add_filter('desktop_mode_dock_items', cb)` | items array: `slug, title, icon, url, badge, submenu` | filter |

JS-side: registered scripts call `wp.desktop.registerCommand({ slug, run: fn })` to attach run callbacks. Widget scripts call `wp.desktop.registerWidget({ id, render: fn })` to attach render callbacks.

## File map

### New: `inc/desktop-mode-integration.php` (~200 LOC)

- **Top-level guard:** wrap every integration in `if ( function_exists('desktop_mode_register_command') ) { ... }`.
- **Dock filter** — adds 1 SN item with submenu of all 8 settings tabs.
- **Desktop icons** — Dashboard + Identity (most-used tabs).
- **Command registrations** — 13 commands with `'script' => 'sn-desktop-mode'` handle.
- **Widget registration** — 1 widget `sn-deploy-status` with `'script' => 'sn-desktop-mode-widget'`.
- **Script registrations** — `wp_register_script('sn-desktop-mode', ..., ['wp-api-fetch'], SNT_VERSION, true)` + same for widget.
- **REST endpoints** — `signal-noise/v1/cmd/*` for the 4 maintenance actions. `permission_callback` = `manage_options`.

### New: `assets/desktop-mode.js` (~140 LOC)

- IIFE pattern, no globals.
- Waits for `wp.desktop` ready event.
- Registers 13 command run-callbacks. Navigation commands set `window.location.href`. Maintenance commands `wp.apiFetch({ path: '/signal-noise/v1/cmd/...' })` then dispatch a `wp.desktop.notify()` toast on response. Info commands read from `window.snDesktopData` (localized).

### New: `assets/desktop-mode-widget.js` (~80 LOC)

- IIFE, no globals.
- Registers `sn-deploy-status` widget with a render callback that fetches `/signal-noise/v1/cmd/status` (read-only — version + last deploy time) and renders a compact card.
- Click handler opens the SN Dashboard tab in a desktop-mode window via `wp.desktop.openWindow()` (or fallback to URL nav).

### Edit: `signal-and-noise-tools.php`

- Add `require_once __DIR__ . '/inc/desktop-mode-integration.php';`
- Bump `Version: 1.15.0` + `SNT_VERSION`.

## Command list (final)

### Maintenance (REST → toast)

| Slug | Label | REST endpoint | What it does |
|---|---|---|---|
| `sn-cmd-force-check` | SN: Force-check updates | `POST /signal-noise/v1/cmd/force-check` | Clears all GH + WP update transients, returns ok |
| `sn-cmd-purge-caches` | SN: Purge all caches | `POST /signal-noise/v1/cmd/purge-caches` | Fires `sn_purge_all_caches_result` filter |
| `sn-cmd-clear-overrides` | SN: Clear template overrides | `POST /signal-noise/v1/cmd/clear-overrides` | Fires `sn_clear_template_overrides_result` filter |
| `sn-cmd-full-reset` | SN: Full reset | `POST /signal-noise/v1/cmd/full-reset` | Clear overrides + purge caches in one shot |

### Navigation (window.location nav)

| Slug | Label | Target |
|---|---|---|
| `sn-cmd-nav-dashboard` | SN: Open Dashboard | `admin.php?page=sn-theme-options` |
| `sn-cmd-nav-identity` | SN: Open Identity | `admin.php?page=sn-identity` |
| `sn-cmd-nav-login` | SN: Open Login | `admin.php?page=sn-login` |
| `sn-cmd-nav-cloudflare` | SN: Open Cloudflare | `admin.php?page=sn-cloudflare` |
| `sn-cmd-nav-plausible` | SN: Open Plausible | `admin.php?page=sn-plausible` |
| `sn-cmd-nav-rss` | SN: Open RSS | `admin.php?page=sn-rss` |
| `sn-cmd-nav-reading-time` | SN: Open Reading Time | `admin.php?page=sn-reading-time` |

### Info (read from localized data, toast)

| Slug | Label | What it shows |
|---|---|---|
| `sn-cmd-version-theme` | SN: Theme version | Toast: `Theme: vX.Y.Z (up to date / vA.B.C available)` |
| `sn-cmd-version-plugin` | SN: Plugin version | Toast: `Plugin: vX.Y.Z (up to date / vA.B.C available)` |

## REST endpoint design

All under `signal-noise/v1/cmd/` namespace. Each:

```php
register_rest_route( 'signal-noise/v1', '/cmd/(?P<action>[a-z-]+)', array(
    'methods'             => array( 'GET', 'POST' ),
    'callback'            => 'snt_desktop_cmd_handler',
    'permission_callback' => function() { return current_user_can( 'manage_options' ); },
) );
```

Single handler dispatches on `action` param. Response shape:

```json
{ "ok": true, "message": "Caches purged.", "data": { /* optional */ } }
```

Nonce: WP REST API handles `_wpnonce` header automatically when JS uses `wp.apiFetch` (which our scripts will use via the `wp-api-fetch` dependency).

## Widget design

Single widget: **SN Deploy Status**. Renders:

```
┌──────────────────────────┐
│ SIGNAL & NOISE           │
│                          │
│ Theme   8.5.3   ✓        │
│ Plugin  1.15.0  ✓        │
│                          │
│ Last deploy: 12m ago     │
│ ─────────────────────────│
│ [Open Dashboard]         │
└──────────────────────────┘
```

Click anywhere → navigates to SN Dashboard tab. Refresh interval: 60s (matches the GHA runs cache TTL).

Data source: existing `snt_deploy_status_for()` + `snt_gh_recent_runs_merged()` — exposed via a read-only REST endpoint `/signal-noise/v1/cmd/status` (no nonce required for read; capability still gates).

## Versioning

**MINOR bump (1.14.0 → 1.15.0)** — new user-visible capability (the desktop-mode integration). Continues over-cap pattern.

## Testing notes

Cannot fully test without desktop-mode active on a local WP. Verification plan after ship:
1. Bootstrap deploy lands code on live site (desktop-mode already installed there per user).
2. User opens wp-admin with desktop-mode active → check dock for SN icon.
3. User opens Cmd+K → search "SN:" → should see 13 commands.
4. User runs "SN: Purge all caches" → expect toast `Caches purged.` (or whatever desktop-mode's toast surface is).
5. User checks desktop for "SN Deploy Status" widget; click should open Dashboard.

If anything fails: iterate in a v1.15.1 patch.

## Non-goals (explicit)

- ❌ Custom wallpaper (brand-on-admin pushback applies).
- ❌ Native window (iframe-loaded existing pages are fine; native window adds maintenance burden for marginal UX gain).
- ❌ Settings tab in desktop-mode's settings (SN settings have their own home).
- ❌ AI provider / AI tool registration (Phase 7/12 work, post-WP 7.0).
- ❌ Multiple widgets (Plausible visitor count widget tempting, but ship one widget first, evaluate, expand later).
