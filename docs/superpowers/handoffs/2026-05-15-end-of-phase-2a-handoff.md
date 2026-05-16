# Session handoff — 2026-05-15

Picks up after the 2026-05-15 session that shipped Phase 1, Phase 4 (early slice), and Phase 2a of the theme/plugin split + auto-deploy initiative. Next session should read this first.

## Where the project is right now

### Live versions (deployed to juanlentino.com)

| Package | Version | Deployment |
|---|---|---|
| Theme `signal-and-noise` | `v8.2.1` | Cloudways Git Integration → auto-pulls on tag push to `main` |
| Companion plugin `signal-and-noise-tools` | `v1.1.0` | Manual install via WP admin Upload Plugin (no auto-deploy yet) |

### What works end-to-end

- **Theme auto-deploy:** push any annotated tag (`v*`) to `juanlentino/signal-and-noise` → GitHub Actions fires `.github/workflows/deploy.yml` → calls Cloudways `/api/v1/git/pull` → live in ~30s. Verified via `v8.2.2-deploy-test` (since deleted). Manual `workflow_dispatch` also verified.
- **Cross-package contracts (7 hooks):** theme registers listeners for `sn_purge_all_caches_result`, `sn_clear_template_overrides_result`, `sn_self_heal_force_run_result`, `sn_updater_branch`, `sn_updater_revcount`, `sn_updater_force_check`, `sn_updater_clear_error`. Plugin dispatches. Documented in [docs/WORDPRESS-REFERENCE.md](../../WORDPRESS-REFERENCE.md) §10.0.
- **RSS Plausible Tracker:** runs from plugin since v1.1.0. Same `wp_rss_feed_log` table, same option keys, same `sn_rss_tracker_daily_prune` cron. Plugin's pre-flight guard #2 protects against the legacy MU file co-existing.
- **Plugin pre-flight guards:** guard #1 (theme has legacy `inc/admin-page.php` on disk → bail) and #2 (MU plugin still active → bail) both shipped in [signal-and-noise-tools.php](https://github.com/juanlentino/signal-and-noise-tools/blob/main/signal-and-noise-tools.php). Defense-in-depth; never fire in normal operation.

### What still needs maintenance attention

1. **The live `wp-content/mu-plugins/rss-plausible-tracker.php` was never deleted** (per the v8.2.1 + plugin v1.1.0 release notes). With guard #2 in place, the plugin currently defers tracker loading to that MU file. Next manual step on the live server (via SFTP): `rm wp-content/mu-plugins/rss-plausible-tracker.php`. After that, the plugin's tracker module takes over seamlessly (same DB table, same options, same cron). **Until you do this, the plugin's RSS tracker module is dormant.** Verify: plugin's admin notice asks for the deletion.
2. **Plugin GitHub secrets** (`CLOUDWAYS_*`) are pre-loaded but unused (no `deploy.yml` in the plugin repo). They're harmless. They become useful if/when plugin auto-deploy lands (see Phase 2c below).

## Phase 2a release commits

Theme repo (`juanlentino/signal-and-noise`) on `main`:

| SHA | Title |
|---|---|
| `705809c` | `ci: add Cloudways auto-deploy workflow (Phase 2a)` |
| `d0e8591` | `ci: surface 403 body + custom User-Agent for Cloudways auth debug` |
| `37806f9` | `ci: debug — print email/api_key lengths + runner IP` |
| `6a3a793` | `ci: drop debug lines from deploy.yml, keep error-body surfacing` |

No version bump for Phase 2a — `.github/workflows/` was treated as build infra, analogous to `docs/`. The theme version stayed at `8.2.1`. (If you'd rather rationalize the change as a version-bumping behavioral shift, retroactive `8.2.2` is fine — it'd just be a `style.css` Version field change.)

## What's queued next

### Phase 2b — delete dead code

Cloudways auto-deploy makes large parts of the theme's update infrastructure obsolete. The deletions are mechanical; the open questions are around the *plugin-side admin UI* that surfaces now-empty data.

**Files to delete (theme):**

- **`inc/updater.php`** (~700 lines) — the GitHub-poll self-updater. The `pre_set_site_transient_update_themes` filter no longer needs to fire — Cloudways has already pulled by the time WP-Cron would run. The `sn_updater_refresh_cache()` SWR job, the `admin_init` warmer, the `upgrader_*` hooks, the `http_request_args` token injector, the `load-update-core.php` clearer, the `admin_notices` for missing token / errors — all dead.
- **`inc/template-self-heal.php`** (~470 lines) — file-drift recovery. With `git pull`-based deploys, the file tree is atomically consistent. Self-heal has nothing to do.

**Files to trim (theme):**

- **`inc/template-maintenance.php`** — keep `sn_purge_all_caches()` (still called from `save_post` on content edits AND from plugin's purge button via filter contract). Keep `sn_clear_template_overrides()`. Keep the cross-package filter listeners. Drop the mtime-based template cache tracking that compares against a stored mtime option (that exists to detect "deploy didn't take effect" — irrelevant under auto-deploy). Net: ~100 lines kept of ~300.

**Files to keep (theme, but their contract listeners need a rewrite):**

- None — the listeners in `inc/updater.php` (for `sn_updater_branch`, `sn_updater_revcount`, `sn_updater_force_check`, `sn_updater_clear_error`) all live in `updater.php` and disappear with it. After deletion, the plugin's filter calls return defaults: `apply_filters('sn_updater_branch', 'main')` returns `'main'`, `apply_filters('sn_updater_revcount', 0, ...)` returns `0`. Plugin's actions become no-ops.

**Plugin-side changes needed:**

The plugin's admin UI currently shows updater state read via these now-dead contracts. After Phase 2b, those reads return defaults. Decisions:

1. **`signal-and-noise-tools/inc/admin-page.php` — "Latest on GitHub" status row.** Reads `sn_github_branch_*` transient (no longer populated) and dispatches `sn_updater_branch` / `sn_updater_revcount` filters (return defaults). The row would render with empty/zero values. **Options:** (a) drop the row entirely; (b) replace with "Auto-deploys from `main` via Cloudways" static text; (c) read theme version from `wp_get_theme()->get('Version')` and show that.
2. **`signal-and-noise-tools/inc/admin-page.php` — "Check Now" button.** Currently dispatches `sn_updater_force_check` action (becomes no-op). **Options:** (a) remove; (b) repurpose to trigger a Cloudways `/git/pull` via the REST surface (scope creep — interesting future feature though).
3. **`signal-and-noise-tools/inc/admin-bar.php` — quick check-updates button.** Same fate. Remove.
4. **`signal-and-noise-tools/inc/rest-api.php` — `/check-updates` endpoint.** Same fate. Remove the endpoint OR repurpose to trigger Cloudways pull.

Recommended approach: option (a) for everything. Remove the status row, remove the buttons, remove the endpoint. Simpler architecture. The maintainer can always look at GitHub Actions for deploy state.

**Open question: Cloudflare cache purge on deploy.**

The plugin's `inc/cloudflare-purge.php` hooks `upgrader_process_complete` to purge CF edge cache after a theme update. With Cloudways auto-deploy, `upgrader_process_complete` never fires for the theme. **Without a fix, theme deploys won't auto-purge CF.** Options:

- **Add a step to the GitHub Actions workflow:** after `/git/pull` succeeds, call a WP-REST endpoint that triggers `sn_cf_purge_everything()`. Requires a new REST endpoint with auth.
- **Cloudways post-deploy hook:** if Cloudways supports running a shell command after `git pull`, run `wp-cli cf purge` or similar. Requires Cloudways shell access + WP-CLI configured.
- **Cron-based polling:** plugin compares deployed SHA (from `git rev-parse` on the theme dir) to a stored SHA every minute; if changed, purge CF. Adds latency.

Best path: REST endpoint + workflow step. ~50 lines of new code; brings the auto-deploy story end-to-end (deploy + cache invalidate atomically).

**Versioning for Phase 2b:**

- Theme: `8.2.1` → **`8.3.0`** (minor). Substantial code removal; architectural shift to auto-deploy-only. Patch cap doesn't force this — it's a judgment call to mark the architectural milestone.
- Plugin: `1.1.0` → **`1.2.0`** (minor). Admin UI changes are user-visible.

### Phase 2c — plugin auto-deploy (optional)

Cloudways' Deployment Via Git supports only **one repo per application**. We used that slot for the theme. To get the plugin auto-deploying too, the realistic path is **SSH-based deploy from GitHub Actions:**

- GitHub Actions workflow on tag push uses an SSH key (stored as a GitHub repo secret) to SSH into Cloudways.
- Runs `cd /home/master/applications/<app_user>/public_html/wp-content/plugins/signal-and-noise-tools && git pull origin main`.
- One-time setup: SSH key pair generation, public key into Cloudways `master_user`'s `authorized_keys`, initial `git clone` into the plugin path via SSH.

Cloudways' SSH credentials are available (master_user `master_syguxtyfsh`, public_ip `157.245.116.64`; full creds in earlier session transcript at the server-detail API response). For the secrets work, see the `gh secret set` gotcha [in memory](/Users/juanlentino/.claude/projects/-Users-juanlentino-Projects-signal-and-noise/memory/feedback_gh_secret_set_stdin.md).

Defer this until you actually want plugin auto-deploy. The plugin updates infrequently in steady state, and the SSH approach also unblocks symmetric theme deploys if you ever want to retire Cloudways' Git Integration.

### Phase 3 — theme-coupled file moves

Per the original Phase 1 spec, these files were deferred because they're presentation-coupled and need real judgment calls per file:

- `inc/og-image.php` — uses theme typography (Bebas Neue + DM Mono). Could stay in theme as a "presentation utility" OR move to plugin if we accept the typography coupling.
- `inc/reading-time.php` — rendered in post meta via shortcode. Same coupling question.
- `inc/notes-and-provenance.php` — defines the `/notes` content surface + Provenance pillar page. Mostly content/template logic.
- `inc/page-notes-template.php` + `inc/page-notes-render.php` — `template_include` override + PHP-authoritative renderer for `/notes`. The renderer is heavily styled in theme typography (per [docs/WORDPRESS-REFERENCE.md](../../WORDPRESS-REFERENCE.md) §10.4).

Recommendation: brainstorm per-file. Probably some stay in theme (renderers), some move to plugin (analytics-adjacent like reading-time tracking). Lowest priority.

### Deferred hygiene items (from earlier in session)

These were considered and deliberately skipped during the v8.1.1 hygiene pass:

- **Inline-styles → external CSS refactor.** 124 instances across 6 files (admin-page.php — 42, plausible-admin.php — 23, cloudflare-purge.php — 21, notes-and-provenance.php — 19 public-facing, reading-time.php — 14, plausible-widget.php — 5). All currently rendered as inline `style="..."` attributes. Real handbook violation but no operational payoff; rejected for the v8.1.1 pass.
- **Full i18n coverage.** Currently zero translation strings; the v8.1.1 hygiene pass stripped the textdomain bootstrap entirely. Re-introducing requires wrapping ~hundreds of admin UI strings + shipping a `.pot` file. Rejected as zero-value for single-author tool.

Both stay rejected by default. Listed here only so they don't get forgotten if priorities ever shift.

## Required reading for next session

In order:

1. This handoff doc (you are here).
2. [docs/WORDPRESS-REFERENCE.md §10.0–§14](../../WORDPRESS-REFERENCE.md) — contract surface, deploy mechanism, gotcha list.
3. [docs/superpowers/specs/2026-05-15-cloudways-auto-deploy-design.md](../specs/2026-05-15-cloudways-auto-deploy-design.md) — Phase 2a design + the post-execution correction block.
4. Recent CHANGELOG entries for v8.2.0 / v8.2.1 in [CHANGELOG.md](../../../CHANGELOG.md).
5. Plugin repo's [CHANGELOG.md](https://github.com/juanlentino/signal-and-noise-tools/blob/main/CHANGELOG.md) — v1.0.0 → v1.1.0.

## Quick-start commands for next session

**Maintenance: delete the live MU plugin file (one-time, completes Phase 4 early-slice).**

Cloudways → SSH or SFTP → `rm wp-content/mu-plugins/rss-plausible-tracker.php`. Plugin's admin notice will clear on the next admin pageview.

**Smoke test the auto-deploy:**

```bash
cd /Users/juanlentino/projects/signal-and-noise   # or worktree path
git tag -a v8.2.2-smoke -m "smoke"
git push origin v8.2.2-smoke
sleep 30
gh run list --repo juanlentino/signal-and-noise --workflow=deploy.yml --limit 1
# expect: completed success
git tag -d v8.2.2-smoke && git push origin :refs/tags/v8.2.2-smoke
```

**Start Phase 2b:**

```bash
# In a fresh session, with the project loaded:
# 1. Read this handoff doc + the references in "Required reading."
# 2. Invoke superpowers:brainstorming to scope Phase 2b's plugin-side UI decisions
#    (the open questions in this doc are the agenda).
# 3. After spec approval, invoke superpowers:writing-plans for the mechanical
#    deletions. Most of Phase 2b is `rm` + small edits to plugin admin files.
# 4. Ship as theme v8.3.0 + plugin v1.2.0 (coordinated, but Cloudways
#    auto-deploys the theme so the live transition is automatic).
```

## Session statistics (this session)

| Metric | Value |
|---|---|
| Phases completed | 1, 4 (early slice), 2a |
| Releases shipped | Theme v8.1.1, v8.2.0, v8.2.1 + Plugin v1.0.0, v1.0.1, v1.1.0 |
| GitHub Actions workflow runs (auto-deploy verifications) | 5 (3 failed, 2 success after the `gh secret set` bug was diagnosed) |
| Lines of code moved theme → plugin (cumulative) | ~3,000 across 10 modules |
| Cross-package contract hooks introduced | 7 |
| Lines of code to delete in Phase 2b | ~1,400 |
| Bugs surfaced + fixed live | 3 (function-redeclare fatal on Phase 1 install; missed cross-coupling in Phase 1 audit; `gh secret set --body -` literal-value footgun) |
| New memory entries | 2 (default-to-recommendation feedback; gh-secret-set stdin pattern) |

## Anti-checklist (things NOT to do in next session)

- **Don't** try to add the plugin to Cloudways' Git Integration. One-repo-per-app limit; verified during this session. Phase 2c is the path.
- **Don't** keep the GitHub-poll updater code "just in case." It's dead. Cloudways auto-deploys before any WP-Cron tick can fire. Deleting it is the whole point of Phase 2b.
- **Don't** assume `gh secret set --body -` reads stdin. It doesn't. See [memory](/Users/juanlentino/.claude/projects/-Users-juanlentino-Projects-signal-and-noise/memory/feedback_gh_secret_set_stdin.md).
- **Don't** re-spec Phase 4. The early slice (RSS tracker) shipped in v8.2.1 / Tools v1.1.0. Phase 4 is empty.
- **Don't** push to `main` from the worktree without `git push origin HEAD:main` (the worktree branch name doesn't match `main`).
