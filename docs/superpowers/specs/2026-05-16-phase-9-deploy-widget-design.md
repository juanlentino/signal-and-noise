# Phase 9 — Deploy status widget (design spec)

**Date:** 2026-05-16
**Target release:** plugin v1.12.0
**Status:** Approved 2026-05-16 — proceed to implementation

## Goal

Surface the entire WP-update + deploy plumbing built earlier in this session as a single readable surface in `wp-admin`. Two complementary surfaces:

1. **Dashboard widget** — information-dense, read on login.
2. **Admin bar item** — at-a-glance state visible on every wp-admin page.

## Surfaces

### Dashboard widget — `Signal & Noise · Deploy status`

Three sections, ~120-160px tall:

**Versions (top section, ~40px):**
```
Theme   v8.5.3   ✓ up to date           [↗ repo]
Plugin  v1.12.0  ✓ up to date           [↗ repo]
```
- `current Version → latest GitHub tag` per repo.
- Status pill: `up-to-date` (green) / `update available` (amber, with the new version) / `error` (red, with last error excerpt).
- Reuses existing `sn_gh_latest_theme_tag()` and `sn_gh_latest_plugin_tag()` — no new GitHub API hits.

**Recent deploys (middle section, ~80px):**
```
✓ theme   v8.5.3   workflow_dispatch    59s   2 min ago
✓ plugin  v1.11.2  workflow_dispatch    13s   33 min ago
✓ theme   v8.5.2   workflow_dispatch    12s   34 min ago
✓ plugin  v1.11.1  workflow_dispatch    18s   41 min ago
✓ theme   v8.5.1   workflow_dispatch    14s   3 hr ago
```
- Last 5 GitHub Actions runs merged across both repos, sorted by `created_at` DESC.
- Each row: status icon (✓/✗/●), repo (theme/plugin), ref, trigger (push/workflow_dispatch), duration, human-readable relative time.
- New file: `inc/github-actions-api.php`. Endpoint: `GET /repos/<owner>/<repo>/actions/workflows/deploy.yml/runs?per_page=5&exclude_pull_requests=true`. Cached 60s in `sn_gh_recent_runs_<repo>` site transient. Authenticated if `SNT_GITHUB_TOKEN` defined; unauthenticated otherwise (60/h limit is fine — widget polls at most once per minute per admin pageview, transient absorbs the load).

**Quick actions (bottom section, ~40px):**
```
[ Force-check updates ]   [ Theme repo ]   [ Plugin repo ]
```
- `[ Force-check updates ]` button — POSTs (PRG) to a new admin-post handler that deletes both update transients then redirects to `wp-admin/update-core.php?force-check=1`. Belt-and-braces force refresh.
- `[ Theme repo ]` / `[ Plugin repo ]` — external `<a target="_blank" rel="noopener">` links to the GitHub repos.

### Admin bar item — top-right area

Two pills side-by-side on the right of the admin bar:

```
[T 8.5.3 ✓]  [P 1.12.0 ✓]
```

Color-coded background:
- Green: `up-to-date`
- Amber: `update available` (with new version on hover via `title` attribute)
- Red: `error` (with last error message on hover)

Each pill is a `<a href>` to wp-admin/index.php (the dashboard with the widget — natural deep-link).

Implementation: ~30 LOC added to the existing `inc/admin-bar.php`. Reuses the same cached `sn_gh_latest_*_tag()` data the dashboard widget uses; zero additional API hits.

**Why both surfaces:** dashboard widget is for "let me read recent history when I log in"; admin bar is for "is my code current as I'm editing a post?" Different reading contexts.

## File map

- **NEW** `inc/github-actions-api.php` (~80 LOC) — `snt_gh_recent_runs($repo, $count = 5)` returning array of normalized run records. Same caching pattern as `sn_gh_latest_plugin_tag()`. Honors `SNT_GITHUB_TOKEN` for higher rate limits.
- **NEW** `inc/deploy-widget.php` (~150 LOC) — registers the dashboard widget via `wp_dashboard_setup`. Renders the 3 sections. Includes a PRG handler for the force-check button (`admin_post_sn_force_update_check`).
- **EDIT** `inc/admin-bar.php` — add `add_action('admin_bar_menu', ..., 100)` callback that emits the two version pills. ~30 LOC.
- **EDIT** `signal-and-noise-tools.php` — `require_once` for the two new files. 2 lines.
- **EDIT** `assets/admin.css` — add minimal styles for the deploy-status pills if not already covered by existing status-pill classes. ~20 lines.

Total new code: ~250-280 LOC across 4 files.

## Versioning

- **v1.12.0** (minor) — new user-visible capability (the widget + admin bar surface).
- Plugin minor count goes from 11/5 → 12/5 — continuing the over-cap pattern documented in memory as "user preference."

## Dependencies + risks

- **External:** GitHub Actions REST API (`api.github.com/repos/.../actions/workflows/.../runs`). Unauthenticated rate limit: 60/h per IP. Cached 60s means worst-case 60 requests/h per repo = 120/h total → exceeds the limit only if there are 2+ admins refreshing simultaneously every minute. Mitigation: documentation that `SNT_GITHUB_TOKEN` constant raises to 5000/h.
- **WP version:** uses only `wp_dashboard_setup`, `admin_bar_menu`, `admin_post_*`, `wp_remote_get`, `set_site_transient` — all WP 4.x+ APIs. Works on current 6.x and incoming 7.0.
- **Cross-package:** zero new contract hooks. The widget reads from option/transient names that are part of the documented "Direct dependencies kept (no contract — stable by design)" surface in WP-REFERENCE §10.0.

## Non-goals (YAGNI)

- No "click to install" button in the widget that triggers WP UI install — would be a great future addition but adds complexity (would essentially be a thin wrapper around `wp-admin/update.php?action=upgrade-theme` with proper nonces). Force-check + the existing wp-admin Updates page covers the user's actual workflow.
- No GitHub Actions workflow_dispatch trigger from inside wp-admin — that's a meaningful new permission surface (someone with WP admin access can deploy code), better as a separate phase with a permission gate. The widget shows status; deploys still happen via `gh workflow run` from CLI.
- No per-deploy log streaming. The widget links to GH for that.
- No settings UI in v1.12.0. Defaults work; configurability is a future phase.

## Compatibility rules met (per absorption roadmap)

1. ✅ **Pure functions for every meaningful action.** `snt_gh_recent_runs($repo)` returns data; widget render is separate from data fetch.
2. ✅ **Filter every computed value.** Run normalization will pass through `apply_filters('sn_deploy_widget_run_record', $record, $raw)` for future AI-summary phases.
3. ✅ **Data-model first, UI second.** Storage in `sn_gh_recent_runs_*` site transients; UI reads from them; future Abilities API endpoint can read the same.

## Verification

After ship + bootstrap deploy:
1. Log into wp-admin → dashboard shows widget at default position.
2. Admin bar shows two version pills, both green.
3. Click `[ Force-check updates ]` → redirected to update-core.php → no errors in `error_log`.
4. Push a no-op v1.12.1 tag (workflow_dispatch). Wait for cache (or `?force-check=1`). Widget shows "v1.12.1 available". Recent deploys list shows the new workflow_dispatch run within 60s.
