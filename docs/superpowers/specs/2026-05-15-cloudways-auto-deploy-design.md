# Phase 2a — Cloudways auto-deploy via GitHub Actions

**Date:** 2026-05-15
**Status:** Approved (mechanism setup only; code cleanup deferred to Phase 2b in a future session)
**Releases:**
- Theme `signal-and-noise` — minor commit adding workflow file (no version bump per CLAUDE.md `docs/`-equivalent rule, since `.github/workflows/` is infra)
- Plugin `signal-and-noise-tools` — same shape, same commit nature

## Context

The user's stated motivation for Phase 2 was "faster updates." The original Phase 2 spec proposed moving updater code from theme to plugin, framed around code-consolidation. During brainstorming we surfaced that the code-location refactor does NOT improve update speed — both theme and plugin would still ride the same WP-Cron + Cloudways system-cron pipeline (5–10 min lag minimum).

The actual speed lever is the **deployment mechanism**: switching from GitHub-poll (current) to a webhook-triggered push deploy. Cloudways supports this via their Git Integration feature, but the user previously abandoned it because each release required manual dashboard clicks. This spec resolves that by automating the dashboard clicks: GitHub Actions calls Cloudways' REST API on every tag push.

## Goal

After this phase lands, the maintainer workflow becomes:

1. Edit code, commit, tag (`git tag -a v8.3.0 && git push origin v8.3.0`).
2. Live site has the new code within ~30–60 seconds.

No WP admin clicks. No Cloudways dashboard clicks. No SFTP. The maintainer's release ritual is exactly the existing `vX.Y.Z` tag-push pattern — only the deploy mechanism behind it changes.

## Architecture

```
[ git tag v8.3.0 + git push origin v8.3.0 ]
              │
              ▼
[ GitHub Actions (.github/workflows/deploy.yml) fires on tag push ]
              │
              ▼ POST /api/v1/oauth/access_token  (email + api_key → bearer)
              ▼ POST /api/v1/git/pull            (server_id, app_id, branch, deploy_path)
              │
              ▼
[ Cloudways executes `git pull origin main` in the configured deploy_path ]
              │
              ▼
[ Files on live server now match the tagged commit; next request serves new code ]
```

The same workflow lands in both repos, with `deploy_path` differing:
- Theme repo: `wp-content/themes/signal-and-noise`
- Plugin repo: `wp-content/plugins/signal-and-noise-tools`

Both repos point at the same Cloudways `server_id` + `app_id` (the same WordPress install hosts both).

## Components

### 1. GitHub Actions workflow (one per repo)

`.github/workflows/deploy.yml` — runs on `push: tags: ['v*']` + `workflow_dispatch` (manual trigger for ad-hoc deploys).

Two steps:
- **Authenticate** — POST to `/api/v1/oauth/access_token` with email + api_key; receive a short-lived bearer token. Masked in workflow logs via `::add-mask::`.
- **Trigger pull** — POST to `/api/v1/git/pull` with the deploy parameters. Cloudways API returns 200 (or 202 if queued). Workflow exits non-zero on any other status, surfacing the failure.

Implementation note: plain `curl` + `jq` in bash, no third-party Actions. Smaller attack surface, no version-pinning maintenance.

### 2. GitHub repository secrets (one set per repo)

The workflow reads these from `secrets.*`:

| Secret | Value | Where to find |
|---|---|---|
| `CLOUDWAYS_EMAIL` | Account email | Cloudways profile |
| `CLOUDWAYS_API_KEY` | Long-lived API key | Cloudways → Account → API Keys → Generate |
| `CLOUDWAYS_SERVER_ID` | Numeric server ID for `syntharchy-wp` | Cloudways URL when viewing the server |
| `CLOUDWAYS_APP_ID` | Numeric app ID for the WP install | Cloudways URL when viewing the app |

Both repos use the same four secrets. They identify the same Cloudways application — only the `deploy_path` in the workflow body differs between repos.

### 3. Cloudways one-time setup (manual)

Steps the maintainer runs once. Documented in each repo's README so future-you can re-do them if needed.

1. **Generate API key.** Cloudways → Account → API Keys → "Generate API Key." Copy + store in GitHub repo secrets (both repos).
2. **Note server + app IDs.** Cloudways → Applications → click the WP app → copy from URL (`/console/app/<APP_ID>/server/<SERVER_ID>`). Store as repo secrets.
3. **Configure git for theme path.** Cloudways → app → Deployment Via Git → set repo URL `git@github.com:juanlentino/signal-and-noise.git`, branch `main`, deploy path `wp-content/themes/signal-and-noise`. Copy the SSH deploy key Cloudways generates.
4. **Add deploy key to theme GitHub repo.** GitHub → repo Settings → Deploy keys → "Add deploy key" → paste public key (read-only).
5. **Configure git for plugin path.** Repeat step 3 for `git@github.com:juanlentino/signal-and-noise-tools.git`, branch `main`, deploy path `wp-content/plugins/signal-and-noise-tools`. Different SSH key generated.
6. **Add deploy key to plugin GitHub repo.** Same as step 4 but on the plugin repo.

The setup needs to happen once per repo. After it's done, GitHub Actions handles every release automatically.

## Verification

After both workflow files are committed and the Cloudways setup is done, the maintainer tests by:

1. Pushing any tag (e.g., a no-op `v8.2.2-test` tag).
2. Watching the GitHub Actions tab — the `Deploy to Cloudways` workflow runs.
3. The workflow's output shows HTTP 200 from `/git/pull`.
4. Within ~30 seconds, the live server has the tagged commit in the deploy path.
5. `curl https://juanlentino.com/` still returns 200; nothing broke.

Rollback: delete the test tag (`git tag -d v8.2.2-test && git push origin :refs/tags/v8.2.2-test`).

## What this DOESN'T do (out of scope; Phase 2b)

Once auto-deploy works, the following becomes dead code:

- **`inc/updater.php`** (theme) — the GitHub-poll updater. With auto-deploy, files on server always match remote main. The updater's `pre_set_site_transient_update_themes` filter never finds drift to report. ~700 lines of inert code.
- **`inc/template-self-heal.php`** (theme) — the file-drift detection-and-recovery system. With `git pull`-based deploys, files on disk are atomically consistent. Self-heal has nothing to do. ~470 lines.
- **Most of `inc/template-maintenance.php`** (theme) — keep only the `mtime`-based cache purge (handles content-edit invalidation, unrelated to deploys). Drop the deploy-related parts. ~200 lines saved.
- **Plugin pre-flight guards #1 + #2** — defensive guards added in v1.0.1 + v1.1.0. With Cloudways managing the file tree, the conditions they detect can no longer occur via normal flow. Worth keeping as defense-in-depth but their fire rate goes to zero. ~30 lines of code (low priority for deletion).

Phase 2b (future session): delete the obsoleted code, ship a coordinated theme `v8.3.0` + plugin `v1.2.0` that removes ~1,400 lines of now-unused code.

## Post-execution corrections

The spec assumed both theme + plugin could each get their own Cloudways Git Integration deployment. **Wrong.** Cloudways' Deployment Via Git dashboard exposes **a single repository configuration per application** — only one repo can be auto-pulled. Discovered while configuring during execution.

**Resolution:** Cloudways auto-deploy goes to the **theme only** (the higher-frequency update surface that motivated this work in the first place). The plugin keeps its current manual zip-upload install path documented in its README. A future phase can address plugin auto-deploy via SSH-based deploy from GitHub Actions (bypasses the Cloudways-Git-Integration limit; works symmetrically for both packages) if/when plugin auto-deploy becomes desired.

**Practical impact:** plugin's `deploy.yml` is NOT created. Plugin's pre-existing manual install instructions stay. Plugin's GitHub repo has the Cloudways secrets pre-loaded (harmless; forward-compatible for the future SSH-based phase). Only the theme repo ships a working `deploy.yml` in this phase.

## Assumed (and verified during execution) Cloudways API shape

Verified during the Phase 2a setup on the live Cloudways app:

- `POST /api/v1/oauth/access_token` with `email` + `api_key` body params → returns `{ "access_token": "...", "token_type": "Bearer", "expires_in": 3600 }`. ✓
- `POST /api/v1/git/pull` with bearer auth and `server_id`, `app_id`, `branch_name`, `deploy_path` body params → returns `{ "status": true, "operation_id": "flex-NNNNNNNN" }`. ✓
- `GET /api/v1/operation/<operation_id>` with bearer auth → returns operation status. `is_completed: 1` = success, `-1` = error. ✓
- `deploy_path` is relative to the application's `public_html/` (do NOT prefix `public_html/`). E.g., `wp-content/themes/signal-and-noise` is correct. ✓

The operation_id only succeeds if Cloudways has a valid Git deployment configured for the given (server_id, app_id, deploy_path) tuple. Calling `/git/pull` without prior dashboard config returns `is_completed: -1, status: "An unexpected error has occurred."` — that's how we discovered the one-deploy-per-app limit.

### Setup race

Between (a) merging the workflow file and (b) completing Cloudways setup, a tag push would fire the workflow with bad/missing secrets → workflow fails → no deploy. **Sequence the work**: complete Cloudways setup FIRST (steps 1–6 above + add all secrets to GitHub), THEN merge the workflow file. After merge, the workflow is functional from the first tag push onwards.

### Cloudways dependency

Auto-deploy is now critical-path. If Cloudways API is down during a release, the deploy doesn't fire. Fallback paths:
- Manual `git pull` via SSH on Cloudways server.
- Re-trigger workflow via GitHub Actions UI (workflow_dispatch).
- Manual deploy via Cloudways dashboard (the original pre-automation path; still works).

The current GitHub-poll updater is itself dependent on GitHub being reachable + Cloudways WP-Cron firing. Different dependency surface, similar reliability.

## Versioning

Both repos: this is infrastructure-as-code added under `.github/workflows/`. Per CLAUDE.md's "what doesn't bump version" list (docs/, CHANGELOG-only commits), `.github/workflows/` is similarly out of scope — it doesn't affect runtime behavior of the theme or plugin code. Commit as plain `docs:`-style commits, no version bump.

The actual code cleanup (Phase 2b) WILL bump versions: theme `v8.3.0`, plugin `v1.2.0`.
