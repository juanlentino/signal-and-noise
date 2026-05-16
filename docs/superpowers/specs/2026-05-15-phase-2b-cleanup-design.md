# Phase 2b — delete dead updater/self-heal code + CF purge on auto-deploy

**Date:** 2026-05-15
**Status:** Approved (brainstorm complete; writing-plans next)
**Releases:**
- Theme `signal-and-noise` — `8.2.1` → **`8.3.0`** (minor — substantial code removal + architectural shift to auto-deploy-only)
- Plugin `signal-and-noise-tools` — `1.1.0` → **`1.2.0`** (minor — user-visible admin UI removal)

## Context

Phase 2a (shipped 2026-05-15) put Cloudways auto-deploy on every annotated-tag push to the theme repo. With the deploy pipeline now event-driven and atomic, ~1,400 LOC of theme-side infrastructure becomes dead weight:

- **`inc/updater.php` (~683 LOC)** — GitHub-poll self-updater. The `pre_set_site_transient_update_themes` filter no longer matters; Cloudways has already deployed by the time any WP-Cron tick would fire. The SWR refresh job, the `admin_init` warmer, the `upgrader_*` hooks, the `http_request_args` token injector, the `load-update-core.php` clearer, the admin notices — all unreachable.
- **`inc/template-self-heal.php` (~488 LOC)** — file-drift recovery against the GitHub branch. With `git pull`-based deploys, the file tree is atomically consistent with `main`. Self-heal has nothing to recover.
- **`inc/template-maintenance.php` (~304 LOC, trim to ~200)** — `sn_purge_all_caches()` + `sn_clear_template_overrides()` stay. The `upgrader_process_complete` hook + mtime-based template-cache tracking (which existed to detect "deploy didn't take effect") go.

The plugin's admin UI + REST endpoints read from these now-dead contracts. They render with defaults (empty strings, zero counts) — not technically broken, but visually wrong and confusing. They get trimmed in lockstep.

Separately, the plugin's `inc/cloudflare-purge.php` hooks `upgrader_process_complete` to purge Cloudflare's edge cache after a theme update. Under auto-deploy, that hook never fires. **Without a replacement, theme deploys leave stale CF cache up to 30 minutes.** The replacement is a workflow step that POSTs to the plugin's existing `/purge-cache` REST endpoint after `/git/pull` succeeds.

## Goal

After this phase lands:

1. Theme repo carries ~1,400 fewer LOC. Only files relevant to *rendering* remain.
2. Plugin admin UI no longer renders or links to update-checking behavior. The "Latest on GitHub" status row, "Check Now" buttons, and `/check-updates` + `/heal-templates` REST routes are gone.
3. A theme deploy on tag push automatically purges Cloudflare edge cache. The maintainer's release ritual is unchanged: `git tag -a vX.Y.Z && git push origin vX.Y.Z`. Within ~30–60s the new code is live AND the edge cache is fresh.
4. The cross-package contract surface shrinks from 7 hooks to 2 (`sn_purge_all_caches_result`, `sn_clear_template_overrides_result`). The five updater/self-heal contracts dissolve (`sn_self_heal_force_run_result`, `sn_updater_branch`, `sn_updater_revcount`, `sn_updater_force_check`, `sn_updater_clear_error`).

## Architecture

### Deletion surface

```
[ Theme repo ]                                  [ Plugin repo ]
─────────────                                   ──────────────
inc/updater.php ........................ DEL    inc/admin-page.php ...... TRIM
inc/template-self-heal.php .............. DEL    inc/admin-bar.php ....... TRIM
inc/template-maintenance.php ........... TRIM    inc/rest-api.php ........ TRIM
functions.php (3 require_once lines) ... EDIT    inc/cloudflare-purge.php  TRIM
.github/workflows/deploy.yml ........... EDIT    style.css (Version field) EDIT
style.css (Version field) .............. EDIT    CHANGELOG.md ............ EDIT
CHANGELOG.md ........................... EDIT
```

### New deploy flow (after this phase)

```
[ git tag v8.3.0 + git push origin v8.3.0 ]
              │
              ▼
[ GitHub Actions (.github/workflows/deploy.yml) ]
              │
              ▼ POST /api/v1/oauth/access_token       (Cloudways auth)
              ▼ POST /api/v1/git/pull                 (Cloudways deploys theme)
              │
              ▼ sleep 5                               (let git pull settle)
              │
              ▼ POST /wp-json/signal-noise/v1/purge-cache
                Authorization: Basic <base64(user:app_password)>
              │
              ▼
[ Theme files updated AND Cloudflare edge cache purged ]
```

The `/purge-cache` endpoint already exists (`signal-and-noise-tools/inc/rest-api.php:109`). Its callback `sn_rest_purge_cache` dispatches the `sn_purge_all_caches_result` filter, whose theme-side implementation calls `sn_cf_purge_everything()` at `inc/template-maintenance.php:120` (conditional on `function_exists('sn_cf_purge_everything')`).

**Zero new plugin code is required for the CF purge.** The existing endpoint is wired correctly end-to-end; we just need an authenticated caller from the workflow.

## Components

### 1. Theme: delete `inc/updater.php`

Whole-file delete. Remove `require_once __DIR__ . '/inc/updater.php';` from `functions.php:47`.

Side-effects to verify after deletion:
- Filter listeners disappear: `sn_updater_branch`, `sn_updater_revcount`, `sn_updater_force_check`, `sn_updater_clear_error`. Plugin-side `apply_filters()` calls on these return defaults (`'main'`, `0`, no-op).
- Options created by updater: `sn_github_local_sha` (option), `sn_github_branch_*` (transients). They become orphaned but don't cause runtime errors. Optional one-time cleanup: `delete_option('sn_github_local_sha')` in a maintenance script, deferred (not blocking).
- WP-Cron events scheduled by updater: `sn_updater_refresh_cache`. Will fire once after the deploy with no listener, then disappear from the cron schedule on next WP-Cron tick (callback gone → unscheduled). Acceptable.
- Admin notices: gone (their `add_action('admin_notices', ...)` registrations vanish with the file).

### 2. Theme: delete `inc/template-self-heal.php`

Whole-file delete. Remove `require_once __DIR__ . '/inc/template-self-heal.php';` from `functions.php:44`.

Side-effects:
- Filter `sn_self_heal_force_run_result` has no listener. Plugin's `/heal-templates` endpoint's `has_filter()` guard returns false. (We're also removing `/heal-templates` in step 4 below, but this guard is the safety net during the transient state where plugin v1.2.0 isn't installed yet.)
- Cron event `sn_self_heal_run` will fire once with no callback then unschedule. Acceptable.
- Per-file failure cooldown options (`sn_heal_cooldown_*`) become orphaned. Defer cleanup.

### 3. Theme: trim `inc/template-maintenance.php`

Keep:
- `sn_purge_all_caches( $args )` (line 66) — called from plugin via `sn_purge_all_caches_result` filter contract.
- `sn_clear_template_overrides()` (line 145) — called from plugin via `sn_clear_template_overrides_result` filter contract.
- `add_action( 'after_switch_theme', ...)` (line 167) — fires once when theme activates; runs `sn_purge_all_caches()`.
- The filter listeners for `sn_purge_all_caches_result` (line 290) and `sn_clear_template_overrides_result` (line 302) — these are the cross-package contract surface.

Remove:
- `add_action( 'upgrader_process_complete', ...)` block (around line 180) — under auto-deploy this hook never fires.
- The two `add_action( 'admin_init', ... )` blocks at lines 198 and 238 — these are the mtime-based template-cache tracking that compares against a stored mtime option. They exist to detect "deploy didn't take effect," which is irrelevant when Cloudways `/git/pull` is atomic.

Net: ~200 lines kept of ~304.

### 4. Plugin: trim `inc/rest-api.php`

Remove:
- `register_rest_route( SN_REST_NAMESPACE, '/check-updates', ...)` (lines 133-138).
- `sn_rest_check_updates()` callback (lines 260-297).
- `register_rest_route( SN_REST_NAMESPACE, '/heal-templates', ...)` (lines 121-126).
- `sn_rest_heal_templates()` callback (lines 199-237 approximately).

Refactor:
- `register_rest_route( SN_REST_NAMESPACE, '/full-reset', ...)` (lines 127-132) stays.
- `sn_rest_full_reset()` callback (lines 239-258) currently bundles purge + clear-overrides + heal-templates. **Remove the heal-templates step.** New behavior: purge + clear-overrides only. Update the success message accordingly.

Keep: `/purge-cache`, `/clear-overrides`, `/plausible/stats`, `/plausible/realtime`, `/plausible/test`.

### 5. Plugin: trim `inc/admin-page.php`

Remove the entire "Latest on GitHub" status row in the Dashboard tab and the "Check Now" button. Concretely: every block that:
- Dispatches `apply_filters( 'sn_updater_branch', ... )` or `apply_filters( 'sn_updater_revcount', ... )`.
- Renders rows reading `get_transient( 'sn_github_branch_*' )`.
- Submits a form/AJAX call to `/check-updates`.

Adjacent rows in the same tab (purge cache, clear overrides) stay. The tab layout will compact naturally — no replacement text needed.

### 6. Plugin: trim `inc/admin-bar.php`

Remove the quick "Check updates" menu entry (the one that triggers `sn_updater_force_check` or calls `/check-updates`). Keep other admin-bar entries.

### 7. Plugin: trim `inc/cloudflare-purge.php`

Remove `add_action( 'upgrader_process_complete', ...)` block (line 220). Under auto-deploy this hook never fires. Keep everything else: `sn_cf_purge_everything()`, `sn_cf_purge_urls()`, the `wp_after_insert_post` listener, the `sn_admin_cloudflare_tab` UI block.

### 8. Theme: extend `.github/workflows/deploy.yml` with CF purge step

Add a third step after the existing "Trigger git pull" step:

```yaml
- name: Purge Cloudflare cache
  env:
    WP_DEPLOY_USER: ${{ secrets.WP_DEPLOY_USER }}
    WP_DEPLOY_APP_PASSWORD: ${{ secrets.WP_DEPLOY_APP_PASSWORD }}
    PURGE_URL: 'https://juanlentino.com/wp-json/signal-noise/v1/purge-cache'
  run: |
    set -euo pipefail
    sleep 5  # let git pull settle; OPcache reset
    auth=$(printf '%s:%s' "$WP_DEPLOY_USER" "$WP_DEPLOY_APP_PASSWORD" | base64 -w 0)
    response=$(curl -sS -w '\n%{http_code}' -X POST "$PURGE_URL" \
      -H "Authorization: Basic $auth" \
      -H 'Accept: application/json')
    status=$(printf '%s' "$response" | tail -n1)
    body=$(printf '%s' "$response" | sed '$d')
    printf 'HTTP %s\n' "$status"
    printf '%s\n' "$body"
    case "$status" in
      200) ;;
      *) exit 1 ;;
    esac
```

Three notes on the script:
1. `base64 -w 0` is required on Ubuntu runners — the default base64 wraps at 76 cols and breaks the header.
2. `sleep 5` covers OPcache invalidation lag after `git pull`. Cloudways doesn't atomically reset OPcache; the next page request triggers it. 5s is conservative.
3. The endpoint returns 200 (not 202) on success — `sn_rest_ok()` always wraps in a 200.

### 9. New GitHub repo secrets (theme repo) — **already set**

Both secrets were configured on `juanlentino/signal-and-noise` on 2026-05-15:

- `WP_DEPLOY_USER` = `juanlentino` (the WP admin user that owns the App Password).
- `WP_DEPLOY_APP_PASSWORD` = the 24-char WP Application Password generated via `wp-admin/profile.php → Application Passwords → "GitHub Deploy"`. Stored verbatim, including the spaces between 4-char groups — WP's Basic Auth handler strips them on receipt.

Set via `printf '%s' '...' | gh secret set NAME --repo juanlentino/signal-and-noise` (no `--body` flag, per the documented `gh secret set` stdin gotcha). Revocation path if compromised: WP admin → Profile → Application Passwords → Revoke "GitHub Deploy" → regenerate → re-run the `gh secret set` command with the new value.

### 10. Update `docs/WORDPRESS-REFERENCE.md §10`

The "cross-package contract surface" table in §10.0 currently lists 7 hooks. After Phase 2b it lists 2 (`sn_purge_all_caches_result`, `sn_clear_template_overrides_result`). The §10.1 (self-updater) and §10.2 (self-heal) subsections describe modules that no longer exist; replace each with a brief retired-as-of-v8.3.0 note that points to this spec doc. §10.3 ("synthetic update label") and §10.4 ("/notes route") stay as-is — they're independent.

The "Upstream WordPress core gotchas" running list at the bottom of the doc stays untouched.

### 11. Version bumps + CHANGELOG entries

**Theme `style.css`:** `Version: 8.2.1` → `Version: 8.3.0`.

**Theme `CHANGELOG.md`** (top, prepended):

```markdown
## [8.3.0] - 2026-05-15

### Removed
- `inc/updater.php` (~683 LOC) — GitHub-poll self-updater, obsolete since Cloudways auto-deploy (Phase 2a).
- `inc/template-self-heal.php` (~488 LOC) — file-drift recovery, redundant under atomic git-pull deploys.
- `inc/template-maintenance.php` — mtime-based template-cache tracking + `upgrader_process_complete` hook (~100 LOC).

### Added
- `.github/workflows/deploy.yml` — third step posts to `/wp-json/signal-noise/v1/purge-cache` after Cloudways `/git/pull` so theme deploys atomically refresh Cloudflare edge cache.

### Changed
- Cross-package contract surface shrinks from 7 hooks to 2. Updater filters (`sn_updater_branch`, `sn_updater_revcount`, `sn_updater_force_check`, `sn_updater_clear_error`) and the self-heal filter (`sn_self_heal_force_run_result`) are retired. Plugin v1.2.0 expects this and renders correctly.
- `docs/WORDPRESS-REFERENCE.md §10` updated to reflect the new contract surface.
```

**Plugin `signal-and-noise-tools.php`:** `Version: 1.1.0` → `Version: 1.2.0`.

**Plugin `CHANGELOG.md`** (top, prepended):

```markdown
## [1.2.0] - 2026-05-15

### Removed
- "Latest on GitHub" status row + "Check Now" button in admin Dashboard tab (`inc/admin-page.php`).
- Quick "Check updates" entry in WP admin bar (`inc/admin-bar.php`).
- REST routes `/check-updates` and `/heal-templates` (`inc/rest-api.php`). Their backing theme modules retired in theme v8.3.0.
- `upgrader_process_complete` hook in `inc/cloudflare-purge.php` — replaced by deploy-time REST call from GitHub Actions.

### Changed
- `/full-reset` REST endpoint no longer includes a "heal templates" step. New behavior: purge caches + clear DB template overrides only.

### Notes
- Requires theme v8.3.0+. If installed against an older theme, the plugin still loads cleanly — the removed UI elements were the only readers of the retired contracts.
```

## Sequence of operations (release order)

1. **Plugin v1.2.0 first** (manual install via WP admin → Upload Plugin).
   - Removes admin UI + REST surface that reads dead contracts.
   - Theme is still at v8.2.1; its filter listeners still respond, but nothing in the plugin calls them anymore.
   - Site is healthy through this step.

2. **Theme v8.3.0 second** (auto-deploys on tag push).
   - Plugin (already at v1.2.0) is no longer reading the deleted contracts — clean.
   - `.github/workflows/deploy.yml` extension lands as part of the same PR, fires on the v8.3.0 tag push.
   - The CF-purge step requires `WP_DEPLOY_USER` + `WP_DEPLOY_APP_PASSWORD` to exist in repo secrets *before* the tag push. **Already set as of 2026-05-15.** Verifiable via `gh secret list --repo juanlentino/signal-and-noise`.

3. **Verify the auto-deploy + auto-purge end-to-end.**
   - Watch the GA run: `gh run list --repo juanlentino/signal-and-noise --workflow=deploy.yml --limit 1` returns `completed success`.
   - Optional curl check: `curl -I https://juanlentino.com/` should return `cf-cache-status: MISS` on the first request after deploy (proof the edge was purged) and `HIT` on subsequent requests.

## Auth model: WordPress Application Passwords

WP Application Passwords have been in core since 5.6. They flow through `current_user_can()` cleanly — the plugin's `sn_rest_can_manage()` (rest-api.php:79) already handles them without modification.

Why this over alternatives:
- **vs. shared-secret header (`X-SN-Deploy-Secret`):** requires patching `sn_rest_can_manage()` to recognize the header for a specific route. Bespoke auth, more code, separate revocation surface. No benefit.
- **vs. GitHub OIDC → JWT bouncer endpoint:** overkill for a single-purpose deploy hook.
- **vs. raw user password:** WP recommends against; some hosts disable Basic Auth for non-app-password credentials.

Revocation path: WP admin → user → Application Passwords → Revoke. Takes < 30 seconds. If the GHA secret leaks, revoke + regenerate, then `gh secret set WP_DEPLOY_APP_PASSWORD < new_value.txt`.

## Acceptance criteria

1. ☐ Plugin v1.2.0 installs cleanly on a site running theme v8.2.1 — no PHP errors, admin page renders without the removed rows, no broken JS in admin bar.
2. ☐ Theme v8.3.0 deploys via tag push; the `Deploy to Cloudways` workflow run shows all three steps green: auth, git pull, purge cache.
3. ☐ After v8.3.0 ships, `wp-content/themes/signal-and-noise/inc/` contains exactly: `assets-frontend.php`, `frontend-filters.php`, `notes-and-provenance.php`, `og-image.php`, `page-notes-render.php`, `page-notes-template.php`, `patterns.php`, `reading-time.php`, `seed-content/`, `setup.php`, `template-maintenance.php`. (No `updater.php`, no `template-self-heal.php`.)
4. ☐ `curl -I https://juanlentino.com/` returns `cf-cache-status: MISS` immediately after a deploy.
5. ☐ The plugin's existing "Purge All Caches" button in the admin still works end-to-end (sanity-check that we didn't break the existing flow during the cloudflare-purge.php trim).
6. ☐ `docs/WORDPRESS-REFERENCE.md §10` updated to reflect the retired contracts (4 hooks removed, 3 remain).

## Risks + mitigations

| Risk | Mitigation |
|---|---|
| GA step 3 fails (Cloudflare API down, WP REST 500) | Deploy itself already succeeded. Manual fallback: WP admin → "Purge All Caches" button. Workflow failure surfaces in GA UI. |
| Maintainer pushes theme v8.3.0 tag before plugin v1.2.0 is installed | Plugin v1.1.0 admin UI renders with empty values in the Updater row (no fatal error). Ugly but recoverable; install plugin v1.2.0 to clear. |
| App Password leaked in GHA logs | GitHub Actions automatically masks any value sourced from `${{ secrets.* }}` in step logs (replaces with `***`). Defense-in-depth: `set -euo pipefail` (no `set -x`) and indirect handling via `$WP_DEPLOY_APP_PASSWORD` env var prevent the raw value from ever entering a shell-traced command line. The `base64` intermediate (`$auth`) is also masked because it derives from a masked input. |
| Cloudways `/git/pull` succeeds but file changes haven't landed when we POST `/purge-cache` (race) | `sleep 5` is conservative for Cloudways' actual pull latency (typically 2-3s). If it ever races, the purge fires before the new theme is fully active — the next request triggers OPcache reset against the new files anyway. Self-healing. |
| Theme's WP-Cron events (`sn_updater_refresh_cache`, `sn_self_heal_run`) fire once after deploy with no callback | WP unschedules events with no registered action on the next cron tick. One harmless cron miss per event, then silence. |
| Orphaned options (`sn_github_local_sha`, `sn_heal_cooldown_*`) | Don't cause runtime errors. Optional cleanup deferred (not blocking). |

## Out of scope

- **Phase 2c (plugin auto-deploy via SSH)** — separate concern. Plugin still requires manual Upload Plugin step.
- **Phase 3 (theme-coupled file moves: og-image, reading-time, notes-and-provenance)** — different judgment call per file; deferred.
- **Live MU plugin deletion** (`rm wp-content/mu-plugins/rss-plausible-tracker.php`) — manual SFTP op, completes Phase 4 early-slice; not part of this phase.
- **Option cleanup for orphaned `sn_github_local_sha` / `sn_heal_cooldown_*`** — non-blocking; can be a one-shot maintenance script later.
- **Internationalization of the new strings in deploy.yml** — N/A, those are shell strings not user-facing.

## References

- Handoff: [docs/superpowers/handoffs/2026-05-15-end-of-phase-2a-handoff.md](../handoffs/2026-05-15-end-of-phase-2a-handoff.md)
- Phase 2a spec: [docs/superpowers/specs/2026-05-15-cloudways-auto-deploy-design.md](2026-05-15-cloudways-auto-deploy-design.md)
- Cross-package contract surface: [docs/WORDPRESS-REFERENCE.md §10.0](../../WORDPRESS-REFERENCE.md)
- Existing `/purge-cache` endpoint: `signal-and-noise-tools/inc/rest-api.php:109` (route) + `:166` (callback)
- Existing CF purge function: `signal-and-noise-tools/inc/cloudflare-purge.php:134` (`sn_cf_purge_everything`)
- WP Application Passwords introduction: WordPress 5.6 release notes (December 2020)
- `gh secret set` stdin gotcha: [memory/feedback_gh_secret_set_stdin.md](/Users/juanlentino/.claude/projects/-Users-juanlentino-Projects-signal-and-noise/memory/feedback_gh_secret_set_stdin.md)
