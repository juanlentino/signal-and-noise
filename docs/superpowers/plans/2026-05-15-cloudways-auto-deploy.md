# Cloudways Auto-Deploy Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Land GitHub Actions workflows in both repos that call Cloudways' Git Pull API on tag push, so deploys go from ~5–10 min (GitHub-poll lag) to ~30–60s (webhook-fast) without any per-release manual clicks.

**Architecture:** One workflow per repo, identical shape except for `deploy_path`. Workflow uses plain `curl` + `jq` (no third-party Actions). Two-step: auth (email + api_key → bearer token) then trigger (`POST /api/v1/git/pull`). All untrusted input via env vars to avoid command-injection in `run:` blocks per the security guidance.

**Tech Stack:** GitHub Actions (YAML), Bash, curl, jq. Targets Cloudways Platform API v1.

**Commit policy:** This is build-infrastructure under `.github/workflows/`. Per CLAUDE.md analogous to `docs/`, no version bump in either repo. Plain `docs:`-style or `ci:`-style commits.

---

## Prerequisites (user runs in parallel)

These steps are out of the agent's scope but block the **Task 8 verification step**. The user does them once via Cloudways dashboard + GitHub UI. The agent's tasks 1–7 can complete independently while the user works through this.

1. Cloudways → Account → API Keys → Generate API Key. Copy.
2. Note `server_id` + `app_id` from the URL when viewing the WP app: `/console/app/<APP_ID>/server/<SERVER_ID>`.
3. Cloudways → app → Deployment Via Git → configure for `git@github.com:juanlentino/signal-and-noise.git`, branch `main`, deploy path `wp-content/themes/signal-and-noise`. Copy generated SSH public key.
4. GitHub → `juanlentino/signal-and-noise` → Settings → Deploy keys → Add deploy key → paste (read-only).
5. Repeat steps 3–4 for plugin: `git@github.com:juanlentino/signal-and-noise-tools.git`, branch `main`, deploy path `wp-content/plugins/signal-and-noise-tools`.
6. Add four GitHub repo secrets to **both** repos:
   - `CLOUDWAYS_EMAIL` (Cloudways account email)
   - `CLOUDWAYS_API_KEY` (from step 1)
   - `CLOUDWAYS_SERVER_ID` (numeric)
   - `CLOUDWAYS_APP_ID` (numeric)

---

### Task 1: Theme repo — create the deploy workflow file

**Files:**
- Create: `/Users/juanlentino/projects/signal-and-noise/.claude/worktrees/nice-goldstine-063551/.github/workflows/deploy.yml`

- [ ] **Step 1: Create the workflows directory if missing**

```bash
mkdir -p /Users/juanlentino/projects/signal-and-noise/.claude/worktrees/nice-goldstine-063551/.github/workflows
ls -la /Users/juanlentino/projects/signal-and-noise/.claude/worktrees/nice-goldstine-063551/.github/workflows
```

Expected: directory exists. (`smoke-test.yml` already there from a prior release; this is the second workflow.)

- [ ] **Step 2: Write the deploy workflow**

Write the following to `.github/workflows/deploy.yml`. Note: all secrets passed via `env:` blocks (the SAFE pattern per the security guidance about workflow injection); no `${{ github.event.* }}` interpolated into shell.

```yaml
name: Deploy to Cloudways

# Fires on annotated-tag push (vX.Y.Z) — matches the project's existing
# release ritual. workflow_dispatch allows manual re-runs from the
# Actions UI if a tagged release ever needs to be re-deployed.
on:
  push:
    tags:
      - 'v*'
  workflow_dispatch:

# Serialise deploys: if a new tag fires while one is in flight, queue
# rather than cancel — never drop a release.
concurrency:
  group: deploy
  cancel-in-progress: false

jobs:
  deploy:
    name: Trigger Cloudways git pull
    runs-on: ubuntu-latest
    timeout-minutes: 5
    steps:
      - name: Authenticate with Cloudways
        id: auth
        env:
          CW_EMAIL: ${{ secrets.CLOUDWAYS_EMAIL }}
          CW_API_KEY: ${{ secrets.CLOUDWAYS_API_KEY }}
        run: |
          set -euo pipefail
          response=$(curl -fsS -X POST 'https://api.cloudways.com/api/v1/oauth/access_token' \
            --data-urlencode "email=$CW_EMAIL" \
            --data-urlencode "api_key=$CW_API_KEY")
          token=$(printf '%s' "$response" | jq -r '.access_token // empty')
          if [ -z "$token" ]; then
            printf 'Cloudways auth failed. Response:\n%s\n' "$response"
            exit 1
          fi
          printf '::add-mask::%s\n' "$token"
          printf 'token=%s\n' "$token" >> "$GITHUB_OUTPUT"

      - name: Trigger git pull (theme deploy path)
        env:
          CW_TOKEN: ${{ steps.auth.outputs.token }}
          CW_SERVER_ID: ${{ secrets.CLOUDWAYS_SERVER_ID }}
          CW_APP_ID: ${{ secrets.CLOUDWAYS_APP_ID }}
        run: |
          set -euo pipefail
          response=$(curl -sS -w '\n%{http_code}' -X POST 'https://api.cloudways.com/api/v1/git/pull' \
            -H "Authorization: Bearer $CW_TOKEN" \
            --data-urlencode "server_id=$CW_SERVER_ID" \
            --data-urlencode "app_id=$CW_APP_ID" \
            --data-urlencode "branch_name=main" \
            --data-urlencode "deploy_path=wp-content/themes/signal-and-noise")
          status=$(printf '%s' "$response" | tail -n1)
          body=$(printf '%s' "$response" | sed '$d')
          printf 'HTTP %s\n' "$status"
          printf '%s\n' "$body"
          case "$status" in
            200|202) ;;
            *) exit 1 ;;
          esac
```

- [ ] **Step 3: Verify the file landed and is valid YAML**

```bash
cd /Users/juanlentino/projects/signal-and-noise/.claude/worktrees/nice-goldstine-063551
ls -la .github/workflows/
python3 -c "import yaml; yaml.safe_load(open('.github/workflows/deploy.yml'))" && echo 'valid YAML'
```

Expected: `valid YAML`. (If `python3` doesn't have `yaml` module, `pip3 install pyyaml` first, or skip — GitHub will reject invalid YAML on push.)

- [ ] **Step 4: Stage the file**

```bash
cd /Users/juanlentino/projects/signal-and-noise/.claude/worktrees/nice-goldstine-063551
git add .github/workflows/deploy.yml
```

---

### Task 2: Theme repo — create docs/DEPLOYMENT.md

**Files:**
- Create: `/Users/juanlentino/projects/signal-and-noise/.claude/worktrees/nice-goldstine-063551/docs/DEPLOYMENT.md`

- [ ] **Step 1: Write the deployment doc**

Write the following to `docs/DEPLOYMENT.md`:

```markdown
# Deployment

Signal & Noise auto-deploys to the live site via Cloudways' Git Pull API, triggered by GitHub Actions on every annotated-tag push. This replaces the prior "click Update in WP admin" flow (which was bounded by the 5–10 min Cloudways system-cron interval). Tag → live in ~30–60 seconds.

The same architecture is used by the [signal-and-noise-tools companion plugin](https://github.com/juanlentino/signal-and-noise-tools) — each repo has its own `deploy.yml` workflow with a different `deploy_path`, but they share the same Cloudways app + same four GitHub secrets.

## How it works

```
[ git tag v8.3.0 + git push origin v8.3.0 ]
              │
              ▼
[ .github/workflows/deploy.yml fires on tag push ]
              │
              ▼ POST /api/v1/oauth/access_token  (email + api_key → bearer)
              ▼ POST /api/v1/git/pull            (server_id, app_id, branch, deploy_path)
              │
              ▼
[ Cloudways executes `git pull origin main` in deploy_path ]
              │
              ▼
[ Live site has the tagged commit ]
```

## One-time setup (already done by maintainer; documented here for future-self)

### 1. Generate Cloudways API key

Cloudways → Account → API Keys → "Generate API Key." Copy.

### 2. Note server + app IDs

Cloudways → Applications → click the WP app. URL is `https://platform.cloudways.com/server/<SERVER_ID>/applications/<APP_ID>` (or similar). Note both numeric IDs.

### 3. Configure git for theme deploy path

Cloudways → app → Deployment Via Git:
- Repository URL: `git@github.com:juanlentino/signal-and-noise.git`
- Branch: `main`
- Deploy path: `wp-content/themes/signal-and-noise`

Cloudways generates an SSH public key. Copy it.

### 4. Add deploy key to GitHub theme repo

`juanlentino/signal-and-noise` → Settings → Deploy keys → "Add deploy key":
- Title: `cloudways-syntharchy-wp`
- Key: (paste from step 3)
- Allow write access: **unchecked** (read-only is sufficient for pull-only deploys)

### 5. Repeat steps 3–4 for the plugin

- Repo: `git@github.com:juanlentino/signal-and-noise-tools.git`
- Branch: `main`
- Deploy path: `wp-content/plugins/signal-and-noise-tools`
- Add the second generated SSH key to the plugin repo's Deploy keys.

### 6. Add GitHub repo secrets

Both repos need the same four secrets (Settings → Secrets and variables → Actions → New repository secret):

| Secret | Source |
| --- | --- |
| `CLOUDWAYS_EMAIL` | Cloudways account email |
| `CLOUDWAYS_API_KEY` | From step 1 |
| `CLOUDWAYS_SERVER_ID` | From step 2 |
| `CLOUDWAYS_APP_ID` | From step 2 |

## Per-release workflow

After setup:

1. `git commit -m "vX.Y.Z: <summary>"`
2. `git push origin main`
3. `git tag -a vX.Y.Z -m "vX.Y.Z — <summary>"`
4. `git push origin vX.Y.Z`
5. Watch the GitHub Actions tab — the `Deploy to Cloudways` workflow runs.
6. ~30–60s later, live site has the new code.

No WP admin clicks. No Cloudways dashboard clicks.

## Manual trigger (for re-deploys)

GitHub → Actions → `Deploy to Cloudways` → "Run workflow." Select branch `main`. Workflow runs the same flow without needing a fresh tag.

## Failure modes

### Workflow fails at "Authenticate with Cloudways"

- Check `CLOUDWAYS_EMAIL` + `CLOUDWAYS_API_KEY` secrets are correctly set.
- API key may have expired or been revoked — regenerate via Cloudways dashboard, update the secret.

### Workflow fails at "Trigger git pull"

- HTTP 401: bearer token rejected. Auth step likely succeeded but the token is being misread. Check workflow logs for the actual response body.
- HTTP 403: API key lacks permission for this server/app, or the IDs are wrong.
- HTTP 404: endpoint path mismatch — Cloudways may have changed the API. Compare against current docs at https://developers.cloudways.com/.
- HTTP 422: parameter validation failed. The response body will say which param. Most likely `deploy_path` doesn't match a configured deployment.

### Deploy succeeds but live site doesn't reflect changes

- Cloudways executed `git pull` but Breeze cache is serving stale HTML. Visit WP admin → Breeze → Purge All Cache (or use the SN admin bar quick-purge).
- The deploy path is wrong — git pulled into the wrong directory. Verify the deploy path in Cloudways app settings.

### Need to deploy without tagging

Use the `workflow_dispatch` manual trigger described above.

## Fallback: manual deploy

If the API is unreachable (Cloudways outage, GitHub Actions outage), the original manual paths still work:

1. Cloudways dashboard → app → Deployment Via Git → "Start Deployment" button.
2. SSH to Cloudways server and `cd wp-content/themes/signal-and-noise && git pull origin main`.

## Phase 2b (future)

Once this deploy mechanism is verified working, the theme's `inc/updater.php` (~700 lines), `inc/template-self-heal.php` (~470 lines), and most of `inc/template-maintenance.php` become dead code — they exist to detect / repair SHA drift between GitHub and the live site, and auto-deploy makes that drift impossible by construction. Phase 2b deletes that code in a coordinated theme `v8.3.0` + plugin `v1.2.0` release.

## Spec

[docs/superpowers/specs/2026-05-15-cloudways-auto-deploy-design.md](superpowers/specs/2026-05-15-cloudways-auto-deploy-design.md)
```

- [ ] **Step 2: Verify the file landed**

```bash
cd /Users/juanlentino/projects/signal-and-noise/.claude/worktrees/nice-goldstine-063551
wc -l docs/DEPLOYMENT.md
head -5 docs/DEPLOYMENT.md
```

Expected: file is ~110 lines; first line is `# Deployment`.

- [ ] **Step 3: Stage**

```bash
git add docs/DEPLOYMENT.md
```

---

### Task 3: Theme repo — pointer in WORDPRESS-REFERENCE.md

**Files:**
- Modify: `/Users/juanlentino/projects/signal-and-noise/.claude/worktrees/nice-goldstine-063551/docs/WORDPRESS-REFERENCE.md`

Add a brief §14 section pointing at `DEPLOYMENT.md`. This is a discoverability fix — anyone reading WORDPRESS-REFERENCE.md should know the deploy mechanism is documented separately.

- [ ] **Step 1: Read the end of WORDPRESS-REFERENCE.md to find the insertion point**

Use the Read tool on `docs/WORDPRESS-REFERENCE.md` with `offset=420, limit=20` to find the bottom of the file. The §13 "Upstream WordPress core gotchas" table ends with "If you hit a new one, add it here…" line.

- [ ] **Step 2: Append new §14 section after the §13 closing line**

Use Edit on `docs/WORDPRESS-REFERENCE.md`. Find the §13 closing line:

OLD:

```
If you hit a new one, add it here with the source pointer and the workaround. Future-you will thank present-you.
```

NEW:

```
If you hit a new one, add it here with the source pointer and the workaround. Future-you will thank present-you.

---

## 14. Deploy mechanism

Since v8.2.2 (this commit), deploys happen via GitHub Actions → Cloudways Git Pull API on every annotated-tag push. The legacy WP-self-updater code in [inc/updater.php](../inc/updater.php) is still present and functional, but **the actual deploy path no longer flows through it** — Cloudways auto-pulls before WP-Cron would even fire the next update check. Phase 2b will delete the now-dead updater + self-heal code.

Full deploy workflow + one-time Cloudways setup documented in [docs/DEPLOYMENT.md](DEPLOYMENT.md).
```

- [ ] **Step 3: Verify**

```bash
cd /Users/juanlentino/projects/signal-and-noise/.claude/worktrees/nice-goldstine-063551
grep -n "^## 14" docs/WORDPRESS-REFERENCE.md
grep -n "DEPLOYMENT.md" docs/WORDPRESS-REFERENCE.md
```

Expected: §14 heading present; one reference to `DEPLOYMENT.md`.

- [ ] **Step 4: Stage**

```bash
git add docs/WORDPRESS-REFERENCE.md
```

---

### Task 4: Theme repo — commit + push

**Files:** No new files. Aggregates the staged changes from Tasks 1–3.

- [ ] **Step 1: Verify staged set**

```bash
cd /Users/juanlentino/projects/signal-and-noise/.claude/worktrees/nice-goldstine-063551
git status --short
```

Expected:

```
A  .github/workflows/deploy.yml
A  docs/DEPLOYMENT.md
M  docs/WORDPRESS-REFERENCE.md
```

(Plus the spec + plan docs if they're not yet committed. The spec was committed earlier in commit `3d54602`; the plan should be staged at the end of Task 7.)

- [ ] **Step 2: Commit**

```bash
cd /Users/juanlentino/projects/signal-and-noise/.claude/worktrees/nice-goldstine-063551
git commit -m "$(cat <<'EOF'
ci: add Cloudways auto-deploy workflow (Phase 2a)

GitHub Actions workflow triggers Cloudways Git Pull API on every
annotated-tag push. Tag → live site in ~30–60s. No WP admin clicks,
no Cloudways dashboard clicks per release.

Files:
  - .github/workflows/deploy.yml: the workflow itself. Plain
    curl + jq, no third-party Actions. All secrets via env vars
    (the safe pattern; no github.event.* in shell).
  - docs/DEPLOYMENT.md: full setup steps (one-time) + failure
    modes + manual fallback path.
  - docs/WORDPRESS-REFERENCE.md §14: pointer to DEPLOYMENT.md.

No version bump per CLAUDE.md (analogous to docs/ — affects build
infra, not theme runtime). Phase 2b will delete now-dead updater +
self-heal code in a coordinated v8.3.0 / v1.2.0 release.

Requires four GitHub repo secrets configured by maintainer:
CLOUDWAYS_EMAIL, CLOUDWAYS_API_KEY, CLOUDWAYS_SERVER_ID,
CLOUDWAYS_APP_ID. Setup steps in DEPLOYMENT.md.

Spec: docs/superpowers/specs/2026-05-15-cloudways-auto-deploy-design.md
EOF
)"
```

- [ ] **Step 3: Push**

```bash
git push origin HEAD:main
```

Expected: push succeeds. Smoke-test CI runs (unrelated to deploy.yml — that only fires on tags). Deploy workflow does NOT run yet (no new tag).

---

### Task 5: Plugin repo — create the deploy workflow

**Files:**
- Create: `/Users/juanlentino/projects/signal-and-noise-tools/.github/workflows/deploy.yml`

The plugin's workflow is identical to the theme's except for one line: `deploy_path=wp-content/plugins/signal-and-noise-tools`.

- [ ] **Step 1: Workflows directory already exists from `lint.yml`**

```bash
ls /Users/juanlentino/projects/signal-and-noise-tools/.github/workflows/
```

Expected: `lint.yml` present. `deploy.yml` does not yet exist.

- [ ] **Step 2: Write the workflow**

Write the following to `/Users/juanlentino/projects/signal-and-noise-tools/.github/workflows/deploy.yml`:

```yaml
name: Deploy to Cloudways

# Fires on annotated-tag push (vX.Y.Z). Identical shape to the
# signal-and-noise theme repo's deploy.yml; only the deploy_path
# differs. workflow_dispatch allows manual re-runs.
on:
  push:
    tags:
      - 'v*'
  workflow_dispatch:

concurrency:
  group: deploy
  cancel-in-progress: false

jobs:
  deploy:
    name: Trigger Cloudways git pull
    runs-on: ubuntu-latest
    timeout-minutes: 5
    steps:
      - name: Authenticate with Cloudways
        id: auth
        env:
          CW_EMAIL: ${{ secrets.CLOUDWAYS_EMAIL }}
          CW_API_KEY: ${{ secrets.CLOUDWAYS_API_KEY }}
        run: |
          set -euo pipefail
          response=$(curl -fsS -X POST 'https://api.cloudways.com/api/v1/oauth/access_token' \
            --data-urlencode "email=$CW_EMAIL" \
            --data-urlencode "api_key=$CW_API_KEY")
          token=$(printf '%s' "$response" | jq -r '.access_token // empty')
          if [ -z "$token" ]; then
            printf 'Cloudways auth failed. Response:\n%s\n' "$response"
            exit 1
          fi
          printf '::add-mask::%s\n' "$token"
          printf 'token=%s\n' "$token" >> "$GITHUB_OUTPUT"

      - name: Trigger git pull (plugin deploy path)
        env:
          CW_TOKEN: ${{ steps.auth.outputs.token }}
          CW_SERVER_ID: ${{ secrets.CLOUDWAYS_SERVER_ID }}
          CW_APP_ID: ${{ secrets.CLOUDWAYS_APP_ID }}
        run: |
          set -euo pipefail
          response=$(curl -sS -w '\n%{http_code}' -X POST 'https://api.cloudways.com/api/v1/git/pull' \
            -H "Authorization: Bearer $CW_TOKEN" \
            --data-urlencode "server_id=$CW_SERVER_ID" \
            --data-urlencode "app_id=$CW_APP_ID" \
            --data-urlencode "branch_name=main" \
            --data-urlencode "deploy_path=wp-content/plugins/signal-and-noise-tools")
          status=$(printf '%s' "$response" | tail -n1)
          body=$(printf '%s' "$response" | sed '$d')
          printf 'HTTP %s\n' "$status"
          printf '%s\n' "$body"
          case "$status" in
            200|202) ;;
            *) exit 1 ;;
          esac
```

- [ ] **Step 3: Verify YAML**

```bash
cd /Users/juanlentino/projects/signal-and-noise-tools
python3 -c "import yaml; yaml.safe_load(open('.github/workflows/deploy.yml'))" && echo 'valid YAML'
```

Expected: `valid YAML`.

- [ ] **Step 4: Confirm deploy_path differs from theme**

```bash
grep 'deploy_path=' .github/workflows/deploy.yml
```

Expected: `--data-urlencode "deploy_path=wp-content/plugins/signal-and-noise-tools"`. (Note: not `themes/signal-and-noise`. Easy to copy-paste-wrong; verify.)

- [ ] **Step 5: Stage**

```bash
git add .github/workflows/deploy.yml
```

---

### Task 6: Plugin repo — update README.md install section

**Files:**
- Modify: `/Users/juanlentino/projects/signal-and-noise-tools/README.md`

The current README describes manual zip-upload install. With auto-deploy via Cloudways, that path is obsolete (post-setup). Update the Installation section.

- [ ] **Step 1: Read the existing Installation section**

Use the Read tool on `/Users/juanlentino/projects/signal-and-noise-tools/README.md` with `offset=1, limit=30` to find the current Installation section. It starts with `## Installation (Phase 1, manual)`.

- [ ] **Step 2: Replace the Installation section**

Use Edit on `/Users/juanlentino/projects/signal-and-noise-tools/README.md`:

OLD:

```
## Installation (Phase 1, manual)

**Order matters.** The companion theme (Signal & Noise) must be at v8.2.0+ before this plugin can load. v8.2.0 is the theme release that deleted the 9 module files from the theme's `inc/`; without that deletion, both packages declare the same function names and PHP fatals. Since v1.0.1, the plugin's bootstrap detects this situation and bails out with an admin notice instead of fataling — but the maintainer still needs to ship the theme update to actually use the plugin.

1. Update the Signal & Noise theme to v8.2.0+ (WP admin → Dashboard → Updates → click *Update* on the theme tile, or visit `…/wp-admin/update-core.php?force-check=1` to surface it faster).
2. Download a release zip from this repo's *Releases* tab (or `https://github.com/juanlentino/signal-and-noise-tools/archive/refs/tags/v1.0.1.zip`).
3. WP admin → Plugins → Add New → Upload Plugin → choose the zip → Install Now → Activate.
4. If WP unzips to `wp-content/plugins/signal-and-noise-tools-1.0.1/` (with the version suffix), rename via SFTP to `wp-content/plugins/signal-and-noise-tools/`.

Phase 2 will add a GitHub-poll self-updater that handles install/update automatically and removes the manual zip step.
```

NEW:

```
## Deployment

This plugin auto-deploys to the live site via Cloudways' Git Pull API, triggered by GitHub Actions on every annotated-tag push. See the theme repo's [docs/DEPLOYMENT.md](https://github.com/juanlentino/signal-and-noise/blob/main/docs/DEPLOYMENT.md) for the full one-time setup + per-release workflow.

After the one-time Cloudways + GitHub secrets setup is done, every `git push --tags` to this repo automatically triggers a `git pull` on the live server's `wp-content/plugins/signal-and-noise-tools/` directory. Tag → live in ~30–60s. No WP admin clicks, no Cloudways dashboard clicks.

### First-time install (before auto-deploy is configured)

If auto-deploy isn't yet set up on the live server (e.g., a fresh environment), install manually:

1. Ensure the companion theme is at **v8.2.1+** — required because earlier theme versions had duplicate module declarations that would fatal on plugin activation. v1.0.1+ of this plugin has a pre-flight guard that detects + bails gracefully, but the actual fix is the theme update.
2. Download the release zip: `https://github.com/juanlentino/signal-and-noise-tools/archive/refs/tags/v1.1.0.zip`.
3. WP admin → Plugins → Add New → Upload Plugin → Install → Activate.
4. If WP unzips to `signal-and-noise-tools-1.1.0/` (with version suffix), rename via SFTP to `signal-and-noise-tools/`.

After this manual install, configure auto-deploy per [docs/DEPLOYMENT.md](https://github.com/juanlentino/signal-and-noise/blob/main/docs/DEPLOYMENT.md) so future releases land automatically.
```

- [ ] **Step 3: Verify**

```bash
grep -n "^## Deployment" /Users/juanlentino/projects/signal-and-noise-tools/README.md
grep -n "Phase 1, manual" /Users/juanlentino/projects/signal-and-noise-tools/README.md
```

Expected: `## Deployment` found; `Phase 1, manual` no longer present (replaced by the new Deployment + first-time-install sections).

- [ ] **Step 4: Stage**

```bash
cd /Users/juanlentino/projects/signal-and-noise-tools
git add README.md
```

---

### Task 7: Plugin repo — commit + push

**Files:** No new files. Aggregates the staged changes from Tasks 5–6.

- [ ] **Step 1: Verify staged set**

```bash
cd /Users/juanlentino/projects/signal-and-noise-tools
git status --short
```

Expected:

```
A  .github/workflows/deploy.yml
M  README.md
```

- [ ] **Step 2: Commit**

```bash
cd /Users/juanlentino/projects/signal-and-noise-tools
git commit -m "$(cat <<'EOF'
ci: add Cloudways auto-deploy workflow (Phase 2a)

Mirror of the theme repo's deploy.yml. Same shape, same secrets, only
the deploy_path differs (wp-content/plugins/signal-and-noise-tools).
On annotated-tag push, GitHub Actions triggers Cloudways Git Pull API
and the live server's plugin directory updates in ~30–60s.

Files:
  - .github/workflows/deploy.yml: the workflow.
  - README.md: Installation section replaced with Deployment +
    First-time-install (cross-references theme repo's DEPLOYMENT.md
    for the canonical setup guide so we don't duplicate it).

No version bump (build infra). Requires four shared GitHub secrets
configured by maintainer.

Spec: theme repo docs/superpowers/specs/2026-05-15-cloudways-auto-deploy-design.md
EOF
)"
```

- [ ] **Step 3: Push**

```bash
git push origin main
```

Expected: push succeeds. Lint CI runs (unrelated to deploy.yml — that only fires on tags). Deploy workflow does NOT yet run (no new tag).

---

### Task 8: End-to-end verification (collaborative — user runs the Cloudways side)

**Files:** None — this is a runtime verification.

This task verifies the entire pipeline. The agent prompts the user to confirm Cloudways setup is complete, then pushes a no-op test tag and reports the workflow run status.

- [ ] **Step 1: Confirm user has completed Cloudways prerequisites**

Ask the user: "Have you completed steps 1–6 of the Prerequisites section? Specifically: API key generated, server/app IDs noted, git configured on both apps in Cloudways, SSH deploy keys added to both GitHub repos, and all four secrets (`CLOUDWAYS_EMAIL`, `CLOUDWAYS_API_KEY`, `CLOUDWAYS_SERVER_ID`, `CLOUDWAYS_APP_ID`) added to both GitHub repos?"

Wait for confirmation. If no, STOP. The agent can't proceed to verification without the user's setup work.

- [ ] **Step 2: Push a no-op test tag on the THEME repo**

Pick a sentinel tag name that doesn't collide with a real release: `v8.2.2-deploy-test`. The tag itself does not bump version; it's purely a deploy trigger.

```bash
cd /Users/juanlentino/projects/signal-and-noise/.claude/worktrees/nice-goldstine-063551
git tag -a v8.2.2-deploy-test -m "no-op tag for verifying Cloudways auto-deploy workflow"
git push origin v8.2.2-deploy-test
```

- [ ] **Step 3: Watch the workflow run**

Run periodically (every 15s for up to 2 min):

```bash
gh run list --workflow=deploy.yml --repo juanlentino/signal-and-noise --limit 1
```

Expected: a run appears with status `in_progress` then `completed` (success). If status becomes `failure`, run:

```bash
gh run view --repo juanlentino/signal-and-noise --log-failed
```

…and inspect the error. Most likely failure modes:
- Auth step fails → secret values wrong.
- Trigger step returns 404 → endpoint path differs from assumed. Check docs.
- Trigger step returns 422 → param name differs from assumed (`deploy_path` may be `path` etc.). Check response body.

If any of these fire, patch the workflow inline and re-run via `workflow_dispatch`.

- [ ] **Step 4: Verify the live server received the pull**

The tag points at the current `main` HEAD (which is the same commit `main` is already at — pure no-op). Cloudways `git pull` should succeed with "Already up to date." The HTTP smoke against juanlentino.com should still pass.

```bash
curl -sI https://juanlentino.com/ | head -3
```

Expected: `HTTP/2 200`.

- [ ] **Step 5: Test the PLUGIN repo workflow**

```bash
cd /Users/juanlentino/projects/signal-and-noise-tools
git tag -a v1.1.1-deploy-test -m "no-op tag for verifying Cloudways auto-deploy workflow"
git push origin v1.1.1-deploy-test
```

- [ ] **Step 6: Watch + verify**

```bash
gh run list --workflow=deploy.yml --repo juanlentino/signal-and-noise-tools --limit 1
```

Expected: same shape as theme — `in_progress` → `completed` (success).

- [ ] **Step 7: Clean up test tags (optional)**

If you want to keep tag history clean:

```bash
# Theme
cd /Users/juanlentino/projects/signal-and-noise/.claude/worktrees/nice-goldstine-063551
git tag -d v8.2.2-deploy-test
git push origin :refs/tags/v8.2.2-deploy-test

# Plugin
cd /Users/juanlentino/projects/signal-and-noise-tools
git tag -d v1.1.1-deploy-test
git push origin :refs/tags/v1.1.1-deploy-test
```

(Or leave them — they're harmless and document when the pipeline was first verified.)

- [ ] **Step 8: Report status**

After both workflows have run successfully, report to the user:
- Both `deploy.yml` workflows present, valid, and committed.
- One no-op tag pushed per repo; both workflows fired and reported HTTP 200/202 from Cloudways.
- Live site still healthy (HTTP 200 on `/`).
- Auto-deploy is live. Future releases will deploy automatically on tag push.
- Phase 2b (code deletion of obsoleted updater/self-heal) is ready to start in a future session.
