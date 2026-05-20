# Cron Dashboard — design spec

**Project:** Signal & Noise Tools (companion plugin)
**Target release:** plugin v2.6.0 (minor bump — net-new user-visible capability)
**Phase:** Phase 15 net-new (post-WP-7.0 plugin absorption roadmap)
**Date:** 2026-05-20
**Status:** Approved (pending user spec review) — proceed to writing-plans

---

## 1. Purpose

Surface WP-Cron health in the Signal & Noise wp-admin under a new "Cron" tab. For every scheduled cron event in the WordPress installation, show its next run time, recurrence interval, last-fired timestamp, and provide a guarded "Run now" button. Expose read-only views via the WP 7.0 Abilities API and the desktop-mode ⌘K AI Copilot so the user can ask "how's cron looking?" without leaving the editor.

**Why this matters:** WP-Cron is invisible by default — there's no admin UI for it in WordPress core. When something stops firing (transient race, plugin deactivation, hosting cron mistakes), the symptoms surface elsewhere (stale Plausible cache, RSS prune skipped, plugin update notifications not appearing) without an obvious diagnostic path. This feature gives one.

**Scope:** All WP-Cron events (WP core + every active plugin + the 3 SN-owned hooks), not just SN-owned. Same diagnostic value as WP-Crontrol but tailored to this plugin's 4-surface architecture (wp-admin / legacy REST / Abilities API / desktop-mode ⌘K).

## 2. Architecture — 4-surface dispatch through one impl set

```
                       ┌────────────────────────────────┐
                       │  snt_cron_get_events_impl()    │
                       │  snt_cron_get_event_impl()     │  ← pure functions
                       │  snt_cron_run_event_impl()     │     (inc/cron-dashboard.php)
                       │  snt_cron_last_fired_for()     │
                       └──────▲────▲──────▲─────────▲───┘
                              │    │      │         │
        ┌─────────────────────┘    │      │         └────────────────────────┐
        │                          │      │                                  │
[ wp-admin Cron tab          [ POST /signal-    [ /wp-abilities/v1/      [ desktop-mode ⌘K
  table + Run-now buttons      noise/v1/         abilities/signal-         readonly cmds:
  (inc/cron-dashboard-         cron/run          noise/                     "show cron health"
   admin.php +                  (rest-api.php,    list-cron-events/run     "list cron events"
   assets/                      manage_options    + get-cron-event/run ]    (aiCallable: true,
   cron-dashboard.js) ]         gate) ]                                      nav-only) ]
```

All four surfaces converge on the same `snt_cron_*_impl()` pure functions. Read paths (abilities + desktop-mode) are read-only. Write path (`/cron/run`) is wp-admin REST only — NOT exposed to AI, per the safety precedent set by v2.5.5's destructive-command opt-out.

## 3. File layout

### New files

| Path | LOC est. | Role |
|---|---|---|
| `inc/cron-dashboard.php` | ~105 | Impl functions (`snt_cron_get_events_impl`, `snt_cron_get_event_impl`, `snt_cron_run_event_impl`, `snt_cron_is_sn_owned`, `snt_cron_record_last_fired`, `snt_cron_last_fired_for`), `snt_cron_track_last_fired_cb` named tracker callback, DOING_CRON-gated wp_loaded listener registrar |
| `inc/cron-dashboard-admin.php` | ~110 | Renders the Cron tab — sortable HTML table, client-side filter input, empty state, "Run now" button per row (disabled for orphans) |
| `assets/cron-dashboard.js` | ~55 | Run-now click handler with `confirm()` prompt, live filter input, inline row updates after successful run |

### Modified files

| Path | Δ LOC | Change |
|---|---|---|
| `inc/abilities-registration.php` | +~80 | Append `signal-noise/list-cron-events` + `signal-noise/get-cron-event` abilities (category: `diagnostics`, both `readonly`, `idempotent: true`) |
| `inc/rest-api.php` | +~30 | Append POST `/signal-noise/v1/cron/run` handler with `manage_options` gate |
| `inc/desktop-mode-integration.php` | +~25 | Register 2 ⌘K commands: `sn-cmd-cron-health` (navigates to Cron tab) + `sn-cmd-cron-list` (toasts a one-line summary) |
| `assets/desktop-mode.js` | +~10 | Wire the 2 commands with `aiCallable: true` |
| `inc/admin-page.php` | +~5 | Append 9th entry to `sn_admin_pages()`: `slug: sn-cron`, `tab: cron`, `label: 'Cron'`, subtitle "Scheduled jobs — next run, last fired, manual trigger." Insert AFTER Reading Time, BEFORE Links. |
| `signal-and-noise-tools.php` | +~3 | `require_once` for the 2 new inc/ files |
| `CHANGELOG.md` | +~30 | v2.6.0 entry |
| `style.css` plugin header & `SNT_VERSION` | +~2 | Version bump to 2.6.0 |

**Total: ~455 LOC across 3 new files + 7 modified files.**

## 4. Data structures

### 4.1 Flat event row (returned by `snt_cron_get_events_impl()`)

```php
array(
    'hook'           => 'wp_version_check',          // string
    'args_signature' => '40cd750bba9870f18aada2478b24840a', // md5 of serialized args; used to disambiguate multiple schedulings of same hook
    'next_run_ts'    => 1747936800,                  // int, unix timestamp (UTC)
    'schedule'       => 'twicedaily',                // string|false — false for single events
    'interval_s'     => 43200,                       // int|null — null for single events
    'args'           => array(),                     // array — the cron args as scheduled
    'last_fired_ts'  => 1747850412,                  // int|null — null if never fired since v2.6.0 install
    'has_handler'    => true,                        // bool — has_action($hook) result
    'is_sn_owned'    => false,                       // bool — true for the 3 SN hooks
)
```

### 4.2 Run-now response payload

```php
array(
    'success'           => true,                     // bool
    'elapsed_ms'        => 1247.3,                   // float
    'error'             => null,                     // string|null — present when success === false
    'last_fired_ts'     => 1747938000,               // int — updated timestamp after dispatch
    'hook'              => 'sn_rss_tracker_daily_prune',
)
```

### 4.3 Last-fired storage

WordPress `wp_options` table, autoload `false`, key format: `snt_cron_last_fired_<md5(hook_name)>`, value: unix timestamp (int).

Why md5: WP options key column is `varchar(191)` (since WP 4.4) but some cron hook names contain `/` or `\` characters (rare but legal) or are very long (e.g., compound action-scheduler hooks). md5 gives us a deterministic 32-char hex key that's always safe.

Symmetric read/write helpers in `inc/cron-dashboard.php`:
- `snt_cron_record_last_fired( $hook )` — writes
- `snt_cron_last_fired_for( $hook )` — reads (returns `int|null`)

## 5. Behavior — read path

### 5.1 Tab render

1. User loads `/wp-admin/admin.php?page=sn-cron`
2. `sn_theme_options_page()` (existing dispatcher in inc/admin-page.php) routes to the `cron` tab
3. `do_action( 'sn_admin_cron_tab' )` fires
4. `snt_cron_render_admin_tab()` (in cron-dashboard-admin.php, hooked to that action) calls `snt_cron_get_events_impl()`
5. Impl walks `_get_cron_array()`:
   ```
   $cron = _get_cron_array(); // [timestamp => [hook => [sig => [schedule, args, interval]]]]
   foreach ( $cron as $ts => $hooks ) {
       foreach ( $hooks as $hook => $events ) {
           foreach ( $events as $sig => $data ) {
               $rows[] = [
                   'hook' => $hook,
                   'args_signature' => $sig,
                   'next_run_ts' => $ts,
                   'schedule' => $data['schedule'],
                   'interval_s' => $data['interval'] ?? null,
                   'args' => $data['args'],
                   'last_fired_ts' => snt_cron_last_fired_for( $hook ),
                   'has_handler' => has_action( $hook ),
                   'is_sn_owned' => snt_cron_is_sn_owned( $hook ),
               ];
           }
       }
   }
   ```
6. Rows sorted: SN-owned first, then by `next_run_ts` ascending
7. Render HTML table with 6 columns: Hook / Next run / Recurrence / Last fired / Args / Actions

**Empty state:** if `_get_cron_array()` returns empty (extremely unusual), render an explainer card: "No scheduled events. This is unusual — WordPress core typically schedules `wp_version_check`, `wp_update_plugins`, `wp_update_themes`, and `wp_scheduled_delete` at install. If your cron is empty, something has cleared it. Check your hosting provider's cron configuration."

### 5.2 ⌘K AI Copilot — `sn-cmd-cron-health`

Command label: "Show cron health overview"
`aiCallable: true`, nav-only (no destructive side effect)

JS-side run callback:
```js
function() {
  // Quick summary toast for fast-glance use
  var data = window.snDesktopData.cronSummary || {};
  toast(
    'Cron: ' + (data.total || 0) + ' events scheduled, ' +
    (data.sn_count || 0) + ' SN-owned, ' +
    (data.orphans || 0) + ' orphans',
    'info'
  );
  // Then navigate for the full view
  navigate( data.cron_tab_url );
}
```

Summary data localized into `snDesktopData.cronSummary` via PHP at script enqueue time (cheap — same as existing summaries).

### 5.3 ⌘K AI Copilot — `sn-cmd-cron-list`

Command label: "List scheduled cron events"
`aiCallable: true`, nav-only — simply `navigate( cron_tab_url )`.

Both commands are read-only and safe for AI invocation.

### 5.4 Abilities — `list-cron-events`

```php
wp_register_ability( 'signal-noise/list-cron-events', array(
    'label'              => 'List Cron Events',
    'description'        => 'Returns all scheduled WP-Cron events with next-run, recurrence, last-fired, and handler-registration status.',
    'category'           => 'diagnostics',
    'input_schema'       => array(
        'type'       => array( 'object', 'null' ),
        'properties' => array(
            'sn_only' => array(
                'type'        => 'boolean',
                'default'     => false,
                'description' => 'If true, filter to the 3 SN-owned hooks only.',
            ),
        ),
    ),
    'output_schema'      => array(
        'type'  => 'array',
        'items' => array( /* row schema from §4.1 */ ),
    ),
    'execute_callback'   => function( $input ) {
        if ( ! function_exists( 'snt_cron_get_events_impl' ) ) {
            return new WP_Error( 'snt_cron_unavailable', 'Cron dashboard module not loaded.' );
        }
        $sn_only = ! empty( $input['sn_only'] );
        return snt_cron_get_events_impl( $sn_only );
    },
    'permission_callback' => function() {
        return current_user_can( 'manage_options' );
    },
    'meta' => array(
        'annotations' => array(
            'readonly'        => true,
            'idempotent'      => true,
            'open_world_hint' => false,
        ),
    ),
) );
```

### 5.5 Abilities — `get-cron-event`

Same shape, takes `{ hook, args_signature }` as required input, returns single row.

## 6. Behavior — write path (Run-now)

### 6.1 JS button click

```js
function runNow( hook, argsSignature ) {
  if ( ! window.confirm( "Run cron event '" + hook + "' now?" ) ) {
    return;
  }
  wp.apiFetch({
    path: '/signal-noise/v1/cron/run',
    method: 'POST',
    data: { hook: hook, args_signature: argsSignature }
  })
  .then( function( res ) {
    if ( res.success ) {
      // Update the row's last-fired cell inline
      updateRowLastFired( hook, argsSignature, res.last_fired_ts );
      toast( hook + ' fired in ' + res.elapsed_ms.toFixed(0) + 'ms', 'success' );
    } else {
      toast( 'Run failed: ' + res.error, 'error' );
    }
  })
  .catch( function( err ) {
    toast( 'Run failed: ' + err.message, 'error' );
  });
}
```

### 6.2 Server-side handler

```php
function snt_cron_run_event_impl( $hook, $args = array() ) {
    // Safety pre-flights
    if ( ! current_user_can( 'manage_options' ) ) {
        return new WP_Error( 'snt_cron_forbidden', 'Insufficient permissions.', array( 'status' => 403 ) );
    }
    if ( ! has_action( $hook ) ) {
        return new WP_Error(
            'snt_cron_no_handler',
            sprintf( 'No handler registered for "%s" — this is an orphan event.', $hook ),
            array( 'status' => 400 )
        );
    }

    // Spoof DOING_CRON so handlers that guard on wp_doing_cron() actually execute.
    // Standard pattern from WP-Crontrol; without it Action Scheduler hooks and
    // many WP core hooks silently bail outside cron context.
    if ( ! defined( 'DOING_CRON' ) ) {
        define( 'DOING_CRON', true );
    }

    @set_time_limit( 30 ); // Best-effort; some hosts disable this.

    // Register the last-fired tracker NOW (since wp_loaded already fired
    // before the REST request reached us, the normal DOING_CRON gate at
    // wp_loaded didn't register listeners for this request). Uses the same
    // named callback as the wp_loaded path to avoid closure proliferation.
    add_action( $hook, 'snt_cron_track_last_fired_cb', PHP_INT_MAX );

    $start = microtime( true );
    $success = true;
    $error = null;

    // PHP 7+ Throwable catches both Exception and Error (which fatal errors
    // throw as Error subclasses since PHP 7.0). This handles every recoverable
    // failure mode. Truly unrecoverable cases (segfault, OOM) will return a
    // 502 to the browser — accepted tradeoff for synchronous run-now per §12.
    try {
        do_action_ref_array( $hook, $args );
    } catch ( Throwable $e ) {
        $success = false;
        $error = $e->getMessage();
    }

    $elapsed_ms = ( microtime( true ) - $start ) * 1000;

    return array(
        'success'       => $success,
        'elapsed_ms'    => $elapsed_ms,
        'error'         => $error,
        'last_fired_ts' => snt_cron_last_fired_for( $hook ),
        'hook'          => $hook,
    );
}
```

### 6.3 REST registration (in inc/rest-api.php)

```php
register_rest_route( 'signal-noise/v1', '/cron/run', array(
    'methods'             => WP_REST_Server::CREATABLE, // POST
    'callback'            => function( WP_REST_Request $req ) {
        return rest_ensure_response(
            snt_cron_run_event_impl(
                $req->get_param( 'hook' ),
                $req->get_param( 'args' ) ?? array()
            )
        );
    },
    'permission_callback' => function() {
        return current_user_can( 'manage_options' );
    },
    'args' => array(
        'hook' => array(
            'required' => true,
            'type'     => 'string',
        ),
        'args' => array(
            'type' => 'array',
            'default' => array(),
        ),
    ),
) );
```

## 7. Last-fired tracking — universal via DOING_CRON-gated PHP_INT_MAX listeners

### 7.1 The mechanism

```php
add_action( 'wp_loaded', function() {
    if ( ! ( defined( 'DOING_CRON' ) && DOING_CRON ) ) {
        return; // Zero overhead in normal page loads.
    }

    if ( ! function_exists( '_get_cron_array' ) ) {
        return; // Defensive — _get_cron_array exists since WP 2.1 but underscore-prefixed.
    }

    $crons = _get_cron_array();
    if ( empty( $crons ) ) {
        return;
    }

    $seen = array();
    foreach ( $crons as $events ) {
        foreach ( $events as $hook => $_ ) {
            if ( isset( $seen[ $hook ] ) ) {
                continue;
            }
            $seen[ $hook ] = true;
            // Use named callback (not closure) so identical add_action calls
            // share one function reference and PHP_INT_MAX listeners are
            // deduped by WordPress's internal callback hash.
            add_action( $hook, 'snt_cron_track_last_fired_cb', PHP_INT_MAX );
        }
    }
}, 1 );

// Named callback referenced by both wp_loaded path (above) and run-now path (§6.2).
function snt_cron_track_last_fired_cb() {
    snt_cron_record_last_fired( current_action() );
}
```

### 7.2 Why PHP_INT_MAX (not 'all')

`add_action('all', ...)` fires for EVERY action dispatched anywhere in the request — even non-cron actions like `init`, `wp_loaded`, `pre_get_posts`. During a cron request, this means hundreds of unnecessary callback invocations and option-write checks.

Pre-walking `_get_cron_array()` and registering targeted listeners means our tracker fires exactly N times per cron request, where N = number of cron events actually due to fire. Far cheaper.

### 7.3 Why the DOING_CRON gate at `wp_loaded`

The gate is at priority 1 of `wp_loaded` — early but not so early that `_get_cron_array()` would be unavailable. Total cost in non-cron requests: one `defined()` check + one `&&`. Essentially free.

### 7.4 The `_get_cron_array()` private-API risk

`_get_cron_array()` is underscore-prefixed in WP core, indicating "internal use." However:
- Stable since WP 2.1 (2007)
- Used by every cron-management plugin (WP-Crontrol, Advanced Cron Manager, Action Scheduler internals)
- No public equivalent for "enumerate ALL scheduled events" (`wp_get_scheduled_event` requires you to already know the hook name)

We accept this as a known, low-probability risk. If WordPress ever ships a public alternative, we migrate. Documented in WORDPRESS-REFERENCE.md as a new gotcha.

## 8. Error handling — exhaustive failure matrix

| Failure mode | Detection | Behavior |
|---|---|---|
| User lacks `manage_options` | `current_user_can()` check in REST + ability `permission_callback` | REST returns 403; tab page returns WP's standard "You do not have sufficient permissions" |
| Hook scheduled but no handler registered | `has_action($hook) === false` pre-flight in `snt_cron_run_event_impl` | Returns `WP_Error('snt_cron_no_handler')`; table row's Run-now button is rendered disabled with tooltip "No handler — orphan event" |
| Handler throws `Throwable` (both `Exception` and `Error` since PHP 7+) | try/catch on Throwable around `do_action_ref_array` | Returns `{success: false, error: $e->getMessage()}`; JS shows error toast. Covers most fatals because PHP 7+ throws fatals as `Error` subclasses (TypeError, ParseError, ArgumentCountError, etc.) |
| Handler triggers truly unrecoverable fatal (segfault, OOM, process kill) | None — process dies | Browser receives 502/timeout. Same recovery as the time-limit case: refresh tab, inspect last-fired column for evidence of partial firing. |
| Handler exceeds 30s `set_time_limit` | PHP kills process | Browser receives 502/timeout. Documented limitation. User can manually verify via the Cron tab whether last-fired updated. |
| `_get_cron_array()` returns empty | `empty()` check | Tab renders the empty-state explainer card. Last-fired tracker no-ops. |
| `_get_cron_array()` ever removed from WP core | `function_exists('_get_cron_array')` guard | Tab renders an error card explaining the API is gone; last-fired tracker no-ops. Extremely unlikely. |
| Last-fired option missing (never fired since install) | `snt_cron_last_fired_for()` returns `null` | Table shows "—" in last-fired cell |
| `wp_options` write fails | `update_option` returns false | Last-fired record skipped silently — non-critical degraded state |
| Hook fires via direct PHP call (not via WP_Cron) | Tracker still registered if it's in `_get_cron_array()` | Last-fired records correctly; this is desired behavior |
| AI invokes `list-cron-events` without auth | Ability `permission_callback` | Returns `WP_Error('rest_forbidden')` |

## 9. Versioning & release

- **Plugin version:** bump from 2.5.5 → **2.6.0** (minor — net-new user-visible capability)
- **Theme version:** unchanged (this is plugin-only work)
- **Patch cap (7):** not consumed since we cross to minor
- **Minor cap (5 per major):** we're at v2.x; this is the 6th minor → **rolls to v3.0.0** per CLAUDE.md cap rules

**WAIT.** Re-checking caps: plugin is at v2.5.5. Minors used in v2.x: v2.0, v2.1, v2.2, v2.3, v2.4, v2.5 — that's 6 minors already. The minor cap is 5 per major. So v2.6.0 would be **invalid** under cap rules; the next minor MUST roll to v3.0.0.

**Corrected target release: plugin v3.0.0.**

Document the cap rollover in the CHANGELOG entry per CLAUDE.md convention: "v3.0.0 — Cron Dashboard. Minor cap rollover from v2.x (6 minors used: v2.0–v2.5)."

This is NOT a breaking-change major; it's a cap-driven rollover. No API breakage; existing surfaces preserved.

## 10. Testing strategy

### 10.1 PHPUnit additions in `tests/`

| Test | Assertion |
|---|---|
| `test_snt_cron_get_events_impl_returns_flat_list` | Schedules a known event; impl returns a row matching it |
| `test_snt_cron_get_events_impl_sorts_sn_owned_first` | Schedules SN + non-SN events; SN comes first in result |
| `test_snt_cron_run_event_impl_dispatches_handler` | Run-now triggers the registered handler |
| `test_snt_cron_run_event_impl_records_last_fired` | After run-now, `snt_cron_last_fired_for()` returns recent timestamp |
| `test_snt_cron_run_event_impl_rejects_orphan_hook` | Schedule event but no handler; run-now returns `WP_Error('snt_cron_no_handler')` |
| `test_snt_cron_run_event_impl_catches_throwable` | Register handler that throws; impl returns `{success: false, error}` |
| `test_snt_cron_run_event_impl_defines_doing_cron` | After call, `DOING_CRON === true` |
| `test_snt_cron_last_fired_tracker_only_registers_under_doing_cron` | wp_loaded fires without DOING_CRON; no listeners added |
| `test_list_cron_events_ability_rejects_unauthorized_user` | Set non-admin user; ability returns 403 |
| `test_list_cron_events_ability_sn_only_filter` | Pass `sn_only: true`; returns only the 3 SN hooks |

### 10.2 Manual smoke test (per v2.5.5 handoff precedent)

Post-install on live (juanlentino.com):
1. wp-admin → S&N → Cron tab opens
2. Table populated with WP core hooks + SN hooks + any 3rd-party
3. SN-owned rows pinned at top with visual indicator
4. Click "Run now" on `sn_rss_tracker_daily_prune` → confirm prompt → success toast → last-fired column updates inline
5. Press ⌘K → "show cron health" → toast with summary stats + navigate to Cron tab
6. Press ⌘K → ask AI "are any cron jobs orphaned?" → AI invokes `list-cron-events` ability → answers based on `has_handler: false` rows
7. Press ⌘K → ask AI "run my RSS prune now" → AI should respond chat-style suggesting manual ⌘K → "purge" (mirrors v2.5.5 destructive opt-out behavior — confirms no `aiCallable` leak)

## 11. Explicit non-changes (preserving stability)

To minimize regression surface per the user's explicit safety directive:

- **No changes to existing impl functions.** `snt_ai_*_impl`, `snt_*_impl` left untouched.
- **No reordering of existing abilities.** Append-only to `inc/abilities-registration.php`.
- **No reordering of existing ⌘K commands.** Append-only to `inc/desktop-mode-integration.php` and `assets/desktop-mode.js`.
- **No changes to existing REST routes.** Append-only `/cron/run`.
- **No changes to existing admin tabs.** Append-only 9th entry to `sn_admin_pages()`.
- **No new dependencies.** Pure WordPress core + existing plugin patterns.
- **No theme changes.** Plugin v3.0.0 ships independently.
- **No database schema changes.** Last-fired stored in `wp_options` (autoload `false`) with deterministic md5-keyed names.
- **No changes to existing cron event scheduling.** SN's existing 3 hooks (`sn_plausible_refresh_dashboard`, `sn_plausible_refresh_realtime`, `sn_rss_tracker_daily_prune`) continue to schedule and fire exactly as before.

## 12. Open risks (low probability, documented)

1. **`_get_cron_array()` private-API removal** — extremely unlikely; stable since 2007; no public alternative exists. If removed, both read path + last-fired tracker degrade gracefully via the `function_exists` guard.
2. **Synchronous run-now blocks admin request** — accepted tradeoff per Q3 design decision. Worst case: 30s admin hang.
3. **Heavy handler hits real php.ini max_execution_time below 30s** — `@set_time_limit(30)` is best-effort; some hosts disable it. Same degraded behavior (502 / timeout), same recovery (refresh tab, check last-fired).
4. **Race between two simultaneous Run-now clicks** — possible double-fire of the same hook. Accepted as benign (cron handlers are typically idempotent by design). If a specific hook is non-idempotent and this matters, the handler's author should already gate with their own transient lock.
5. **Cap rollover to v3.0.0** — confirms breaking semantic, but in name only; this release ships purely additive. CHANGELOG must document this clearly so users don't expect a real breaking change.

## 13. Out of scope (explicit YAGNI)

- Editing cron events (reschedule, change interval, unschedule). Read + run-now only.
- Adding NEW cron events from the UI. SN-owned events stay registered in code.
- Cron history / log of all firings. Last-fired (single timestamp per hook) only.
- Per-user filter preferences. Client-side ephemeral filter only.
- Pagination. Expected event count is 20-30; single-page table is fine.
- Exporting cron data. Use the abilities API or REST endpoint if needed externally.
- Visual chart of cron firings over time. Not a metrics tool.
- Special handling for Action Scheduler. Treat its hooks as regular cron events.

## 14. Implementation order (for the writing-plans phase)

Suggested phasing for the implementation plan:

1. **Foundation:** `inc/cron-dashboard.php` impl functions + last-fired tracker
2. **Read surface 1:** Admin tab — `inc/cron-dashboard-admin.php` + `assets/cron-dashboard.js` (filter only, no Run-now yet)
3. **Read surface 2:** Abilities — `list-cron-events` + `get-cron-event` in `inc/abilities-registration.php`
4. **Read surface 3:** ⌘K — 2 commands in `inc/desktop-mode-integration.php` + `assets/desktop-mode.js`
5. **Write surface:** REST `/cron/run` + Run-now JS handler + button rendering
6. **Tests:** PHPUnit additions
7. **Polish:** CHANGELOG, version bump, manual smoke test

Each step is independently verifiable.

## 15. References

- Existing 4-surface pattern: see `docs/superpowers/handoffs/2026-05-20-end-of-v2.5.5-handoff.md` § Architectural state
- Abilities API source: `https://raw.githubusercontent.com/WordPress/abilities-api/trunk/includes/abilities-api.php`
- desktop-mode `aiCallable` reference: `memory/reference_desktop_mode_ai_copilot.md`
- WP-REFERENCE gotchas: `docs/WORDPRESS-REFERENCE.md` (#32 abilities REST URL, #34 GET ?input= decoding)
- Cap rollover policy: `CLAUDE.md` § Versioning + `docs/VERSIONING.md`
- v2.5.5 destructive-command opt-out precedent: `docs/superpowers/specs/2026-05-20-phase16-aicallable-design.md`

---

**Status: APPROVED (pending user spec review). Next step: writing-plans skill.**
