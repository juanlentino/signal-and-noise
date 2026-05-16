# Phase 2b Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Delete ~1,400 LOC of obsolete updater/self-heal infrastructure from the theme; trim the plugin admin UI + REST surface that depended on it; replace the dead `upgrader_process_complete` Cloudflare-purge path with a workflow step that calls the existing `/purge-cache` REST endpoint after Cloudways `/git/pull`.

**Architecture:** Two-repo coordinated release. Plugin v1.2.0 ships first (manual install) to remove now-stale UI/REST surface; theme v8.3.0 ships second (auto-deploys on tag push) and deletes the contract implementations. New `deploy.yml` step authenticates to WP via Application Password and POSTs to `/wp-json/signal-noise/v1/purge-cache`.

**Tech Stack:** WordPress FSE (PHP), GitHub Actions (YAML/bash), Cloudways REST API, Cloudflare REST API, WordPress Application Passwords (Basic Auth over HTTPS).

**Spec:** [docs/superpowers/specs/2026-05-15-phase-2b-cleanup-design.md](../specs/2026-05-15-phase-2b-cleanup-design.md) (commit `ae7b9a0`).

**Working directories:**
- Theme (this worktree): `/Users/juanlentino/projects/signal-and-noise/.claude/worktrees/nice-goldstine-063551` — branch `claude/nice-goldstine-063551` tracking `main`. Push pattern: `git push origin HEAD:main`.
- Plugin: `/Users/juanlentino/Projects/signal-and-noise-tools` — branch `main`.

**Preconditions verified:**
- GHA secrets `WP_DEPLOY_USER=juanlentino` + `WP_DEPLOY_APP_PASSWORD` set on `juanlentino/signal-and-noise` (2026-05-15).
- No test infrastructure in either repo. Verification = hand-execution + smoke-test.

---

## PLUGIN PHASE — ships first as v1.2.0

### Task 1: Trim plugin `inc/rest-api.php`

**Files:**
- Modify: `/Users/juanlentino/Projects/signal-and-noise-tools/inc/rest-api.php`

- [ ] **Step 1: Remove `/heal-templates` route registration** (lines 121-126)

Delete the block:

```php
	register_rest_route( SN_REST_NAMESPACE, '/heal-templates', array(
		'methods'             => WP_REST_Server::CREATABLE,
		'permission_callback' => 'sn_rest_can_manage',
		'callback'            => 'sn_rest_heal_templates',
	) );
```

- [ ] **Step 2: Remove `/check-updates` route registration** (lines 133-138)

Delete the block:

```php
	register_rest_route( SN_REST_NAMESPACE, '/check-updates', array(
		'methods'             => WP_REST_Server::CREATABLE,
		'permission_callback' => 'sn_rest_can_manage',
		'callback'            => 'sn_rest_check_updates',
	) );
```

- [ ] **Step 3: Remove `sn_rest_heal_templates` callback** (function starts at line 199, ends before `sn_rest_full_reset` at line 239)

Locate the full function body (from `function sn_rest_heal_templates(` to its closing `}`). Delete the entire function + its docblock.

- [ ] **Step 4: Remove `sn_rest_check_updates` callback** (function at line 260-297)

Locate the full function body (from `function sn_rest_check_updates(` to its closing `}`). Delete the entire function + its docblock.

- [ ] **Step 5: Refactor `sn_rest_full_reset` to drop heal-templates step** (line 239+)

Read the function. It currently bundles purge + clear-overrides + heal-templates. Remove the heal-templates dispatch (likely `apply_filters( 'sn_self_heal_force_run_result', ... )` or a call to `sn_rest_heal_templates()`). Update the returned message to reflect the new 2-step behavior.

If the existing message is e.g. `'Full reset complete: %d caches purged, %d overrides cleared, %d templates healed.'`, replace with: `'Full reset complete: %d caches purged, %d overrides cleared.'` and drop the third `%d`/argument.

- [ ] **Step 6: Verify no orphan references remain**

Run:

```bash
cd /Users/juanlentino/Projects/signal-and-noise-tools && \
grep -n 'sn_rest_heal_templates\|sn_rest_check_updates\|sn_self_heal_force_run_result' inc/rest-api.php
```

Expected: no matches.

- [ ] **Step 7: Commit**

```bash
cd /Users/juanlentino/Projects/signal-and-noise-tools && \
git add inc/rest-api.php && \
git commit -m "rest: drop /check-updates + /heal-templates; refactor /full-reset

Both routes depended on theme-side contracts (sn_updater_*, sn_self_heal_force_run_result)
being retired in theme v8.3.0. /full-reset now bundles only purge + clear-overrides."
```

---

### Task 2: Trim plugin `inc/admin-page.php`

**Files:**
- Modify: `/Users/juanlentino/Projects/signal-and-noise-tools/inc/admin-page.php`

- [ ] **Step 1: Remove form handlers for updater actions** (around lines 84-100)

Locate the `if ( isset( $_POST['sn_action'] ) )` block. Inside it, find and delete the two case-handlers that:
  - Call `do_action( 'sn_updater_force_check' )` (line 90).
  - Call `do_action( 'sn_updater_clear_error' )` (line 100).

Keep other case-handlers in the same `$_POST['sn_action']` switch (e.g., `purge_caches`, `clear_overrides`).

- [ ] **Step 2: Remove the "Latest on GitHub" rendering block**

Find the contiguous block in the Dashboard tab that:
  - Reads `apply_filters( 'sn_updater_branch', 'main' )` (line 116 + line 148).
  - Reads `apply_filters( 'sn_updater_revcount', 0, $branch, null )` (line 152).
  - Reads `get_transient( 'sn_github_branch_' . sanitize_key( $branch ) )` (line 162).
  - Outputs `<tr><th>Latest on GitHub</th>...` (line 220).
  - Outputs `<button ... value="check_updates">Check Now</button>` (line 290).

Delete the whole block start-to-end. The adjacent table rows (Purge All Caches, Clear Overrides) stay.

- [ ] **Step 3: Verify no orphan references remain**

```bash
cd /Users/juanlentino/Projects/signal-and-noise-tools && \
grep -n 'sn_updater_branch\|sn_updater_revcount\|sn_updater_force_check\|sn_updater_clear_error\|sn_github_branch\|Latest on GitHub\|check_updates' inc/admin-page.php
```

Expected: no matches.

- [ ] **Step 4: Commit**

```bash
cd /Users/juanlentino/Projects/signal-and-noise-tools && \
git add inc/admin-page.php && \
git commit -m "admin-page: drop 'Latest on GitHub' row + Check Now button

These read theme-side updater contracts (sn_updater_branch, sn_updater_revcount,
sn_updater_force_check, sn_updater_clear_error) retired in theme v8.3.0.
Adjacent purge/clear-overrides rows in the same tab stay."
```

---

### Task 3: Trim plugin `inc/admin-bar.php`

**Files:**
- Modify: `/Users/juanlentino/Projects/signal-and-noise-tools/inc/admin-bar.php`

- [ ] **Step 1: Remove menu entry definition** (line 64-65)

Delete from the menu items array:

```php
		'sn-quick-check-updates' => array(
			'action' => 'sn_quick_check_updates',
```

Including the full array entry through its closing `),`.

- [ ] **Step 2: Remove handler mapping** (line 134)

Delete from the handler map:

```php
		'sn_quick_check_updates'   => 'sn_handle_quick_check_updates',
```

- [ ] **Step 3: Remove handler function** (line 184-197)

Delete the entire `function sn_handle_quick_check_updates() { ... }` block including its docblock if present.

- [ ] **Step 4: Verify no orphan references remain**

```bash
cd /Users/juanlentino/Projects/signal-and-noise-tools && \
grep -n 'sn_quick_check_updates\|sn_handle_quick_check_updates\|sn_updater_force_check' inc/admin-bar.php
```

Expected: no matches.

- [ ] **Step 5: Commit**

```bash
cd /Users/juanlentino/Projects/signal-and-noise-tools && \
git add inc/admin-bar.php && \
git commit -m "admin-bar: drop 'Check updates' quick action

The action dispatched sn_updater_force_check, retired in theme v8.3.0."
```

---

### Task 4: Trim plugin `inc/cloudflare-purge.php`

**Files:**
- Modify: `/Users/juanlentino/Projects/signal-and-noise-tools/inc/cloudflare-purge.php`

- [ ] **Step 1: Remove `upgrader_process_complete` hook** (around line 220)

Locate and delete the entire block:

```php
add_action( 'upgrader_process_complete', function( $upgrader, $options ) {
	// ... body purges CF on theme update ...
}, 10, 2 );
```

Plus any docblock immediately above it that describes this hook (don't delete the file's top-level docblock).

- [ ] **Step 2: Verify no orphan references remain**

```bash
cd /Users/juanlentino/Projects/signal-and-noise-tools && \
grep -n 'upgrader_process_complete' inc/cloudflare-purge.php
```

Expected: no matches.

- [ ] **Step 3: Verify other CF infrastructure still intact**

```bash
cd /Users/juanlentino/Projects/signal-and-noise-tools && \
grep -n '^function sn_cf_\|wp_after_insert_post\|sn_admin_cloudflare_tab' inc/cloudflare-purge.php
```

Expected: all of `sn_cf_get_token`, `sn_cf_get_zone`, `sn_cf_is_configured`, `sn_cf_purge_urls`, `sn_cf_purge_everything`, `sn_cf_api_post`, the `wp_after_insert_post` listener, and the `sn_admin_cloudflare_tab` UI block still present.

- [ ] **Step 4: Commit**

```bash
cd /Users/juanlentino/Projects/signal-and-noise-tools && \
git add inc/cloudflare-purge.php && \
git commit -m "cf-purge: drop upgrader_process_complete hook

Hook never fires under Cloudways auto-deploy. Replaced by deploy.yml
step calling /wp-json/signal-noise/v1/purge-cache after /git/pull."
```

---

### Task 5: Bump plugin to v1.2.0 + CHANGELOG + tag

**Files:**
- Modify: `/Users/juanlentino/Projects/signal-and-noise-tools/signal-and-noise-tools.php`
- Modify: `/Users/juanlentino/Projects/signal-and-noise-tools/CHANGELOG.md`

- [ ] **Step 1: Bump `Version:` header**

In `signal-and-noise-tools.php`, find the plugin header comment block at the top. Change:

```php
 * Version: 1.1.0
```

to:

```php
 * Version: 1.2.0
```

If there is a `define( 'SN_TOOLS_VERSION', '1.1.0' );` constant elsewhere in the bootstrap (search with `grep -n "SN_TOOLS_VERSION\|'1.1.0'" signal-and-noise-tools.php`), bump that to `'1.2.0'` too.

- [ ] **Step 2: Prepend CHANGELOG entry**

In `CHANGELOG.md`, insert at the top (after the H1 title, before the previous `[1.1.0]` entry):

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

- [ ] **Step 3: Commit + tag + push**

```bash
cd /Users/juanlentino/Projects/signal-and-noise-tools && \
git add signal-and-noise-tools.php CHANGELOG.md && \
git commit -m "v1.2.0: drop updater UI + REST surface; deploy-time CF purge

Plugin no longer renders or exposes endpoints that read theme-side
updater/self-heal contracts. Coordinated release with theme v8.3.0,
which deletes those contracts. CF cache purge now triggered by
GitHub Actions deploy.yml step in the theme repo." && \
git push origin main && \
git tag -a v1.2.0 -m "v1.2.0 — drop updater UI + REST surface" && \
git push origin v1.2.0
```

- [ ] **Step 4: Verify push + tag landed**

```bash
gh release list --repo juanlentino/signal-and-noise-tools --limit 3 || \
  git ls-remote --tags https://github.com/juanlentino/signal-and-noise-tools.git v1.2.0
```

Expected: `v1.2.0` appears in tags.

---

### Task 6: Install plugin v1.2.0 on live site (manual)

**Files:** N/A (manual WP admin operation)

- [ ] **Step 1: Build the plugin .zip**

```bash
cd /Users/juanlentino/Projects && \
git -C signal-and-noise-tools archive --format=zip --prefix=signal-and-noise-tools/ -o /tmp/signal-and-noise-tools-v1.2.0.zip v1.2.0 && \
ls -lh /tmp/signal-and-noise-tools-v1.2.0.zip
```

Expected: zip file written.

- [ ] **Step 2: Upload via WP admin**

Manual operation:
1. Open `https://juanlentino.com/wp-admin/plugin-install.php?tab=upload`.
2. Choose `/tmp/signal-and-noise-tools-v1.2.0.zip`.
3. Click Install Now → "Replace current with uploaded".
4. Activate (should already be active; replacement keeps activation).

- [ ] **Step 3: Smoke-test admin page**

Visit `wp-admin/admin.php?page=signal-noise` (the plugin's admin page). Verify:
- Dashboard tab renders without the "Latest on GitHub" row.
- No PHP warnings/notices in the page or in `/wp-admin/site-health.php → Info → WordPress debug log`.
- "Purge All Caches" + "Clear Overrides" buttons still present and functional.
- Admin bar no longer shows the quick "Check updates" entry.

If any check fails, STOP and diagnose before proceeding to the theme phase.

---

## THEME PHASE — ships second as v8.3.0

### Task 7: Delete theme `inc/updater.php`

**Files:**
- Delete: `inc/updater.php` (683 LOC)
- Modify: `functions.php` (remove require_once line 47)

- [ ] **Step 1: Delete the file**

```bash
cd /Users/juanlentino/projects/signal-and-noise/.claude/worktrees/nice-goldstine-063551 && \
git rm inc/updater.php
```

- [ ] **Step 2: Remove the require_once line in functions.php**

In `functions.php`, delete line 47:

```php
require_once __DIR__ . '/inc/updater.php';
```

- [ ] **Step 3: Verify no other files reference the deleted module**

```bash
cd /Users/juanlentino/projects/signal-and-noise/.claude/worktrees/nice-goldstine-063551 && \
grep -rn "inc/updater\|sn_updater_refresh_cache\|sn_github_branch_" --include="*.php" .
```

Expected: no matches outside the spec doc and CHANGELOG.md.

- [ ] **Step 4: Commit**

```bash
cd /Users/juanlentino/projects/signal-and-noise/.claude/worktrees/nice-goldstine-063551 && \
git add functions.php && \
git commit -m "remove inc/updater.php — obsolete under Cloudways auto-deploy

The GitHub-poll self-updater (~683 LOC) was redundant once Phase 2a put
event-triggered git-pull deploys in place. Filter listeners disappear with
the file; plugin v1.2.0 (already shipped) stopped calling them."
```

---

### Task 8: Delete theme `inc/template-self-heal.php`

**Files:**
- Delete: `inc/template-self-heal.php` (488 LOC)
- Modify: `functions.php` (remove require_once line 44)

- [ ] **Step 1: Delete the file**

```bash
cd /Users/juanlentino/projects/signal-and-noise/.claude/worktrees/nice-goldstine-063551 && \
git rm inc/template-self-heal.php
```

- [ ] **Step 2: Remove the require_once line in functions.php**

In `functions.php`, delete line 44:

```php
require_once __DIR__ . '/inc/template-self-heal.php';
```

- [ ] **Step 3: Verify no other files reference the deleted module**

```bash
cd /Users/juanlentino/projects/signal-and-noise/.claude/worktrees/nice-goldstine-063551 && \
grep -rn "template-self-heal\|sn_self_heal_force_run_result\|sn_self_heal_run" --include="*.php" .
```

Expected: no matches outside the spec doc and CHANGELOG.md.

- [ ] **Step 4: Commit**

```bash
cd /Users/juanlentino/projects/signal-and-noise/.claude/worktrees/nice-goldstine-063551 && \
git add functions.php && \
git commit -m "remove inc/template-self-heal.php — atomic deploys make it redundant

File-drift recovery (~488 LOC) existed to detect when WP didn't apply a
theme update cleanly. Cloudways git-pull deploys are atomic — the file
tree always matches the deployed commit."
```

---

### Task 9: Trim theme `inc/template-maintenance.php`

**Files:**
- Modify: `inc/template-maintenance.php`

- [ ] **Step 1: Remove `upgrader_process_complete` action block** (line 180-189 + preceding docblock)

Delete the entire block starting from the docblock that describes "Performance: clear caches after theme/plugin updates" (or similar) down through the closing `}, 10, 2 );` at line 189.

- [ ] **Step 2: Remove version-change-detector `admin_init` block** (line 198-218 + preceding docblock)

Delete the entire block starting from the docblock that mentions "Performance: Auto-flush theme cache when deployed version changes" through the closing `} );` of the admin_init action. This block is redundant with the new `deploy.yml` step that purges immediately after git-pull.

- [ ] **Step 3: Remove template-mtime-tracker `admin_init` block** (line 238 through end of its closing `} );`)

Delete the entire block starting from the docblock that mentions "Robustness: detect template-file changes between deploys" through the closing `} );` of the admin_init action.

- [ ] **Step 4: Verify the kept surface is intact**

```bash
cd /Users/juanlentino/projects/signal-and-noise/.claude/worktrees/nice-goldstine-063551 && \
grep -n "^function sn_purge_all_caches\|^function sn_clear_template_overrides\|after_switch_theme\|sn_purge_all_caches_result\|sn_clear_template_overrides_result" inc/template-maintenance.php
```

Expected: all 5 (purge function, clear-overrides function, `after_switch_theme` action, both filter listeners) still present.

- [ ] **Step 5: Verify removed surface is gone**

```bash
cd /Users/juanlentino/projects/signal-and-noise/.claude/worktrees/nice-goldstine-063551 && \
grep -n "upgrader_process_complete\|sn_deployed_version\|sn_templates_latest_mtime" inc/template-maintenance.php
```

Expected: no matches.

- [ ] **Step 6: Verify file is ~200 LOC**

```bash
wc -l inc/template-maintenance.php
```

Expected: roughly 180-220 lines (was 304).

- [ ] **Step 7: Commit**

```bash
cd /Users/juanlentino/projects/signal-and-noise/.claude/worktrees/nice-goldstine-063551 && \
git add inc/template-maintenance.php && \
git commit -m "template-maintenance: drop deploy-detector + mtime-tracker hooks

upgrader_process_complete never fires under Cloudways auto-deploy.
The two admin_init detectors (version-change + mtime-tracker) existed to
work around that gap — now redundant with the deploy.yml /purge-cache step.
Keeps sn_purge_all_caches(), sn_clear_template_overrides(), after_switch_theme
hook, and the cross-package filter listeners."
```

---

### Task 10: Update `docs/WORDPRESS-REFERENCE.md §10`

**Files:**
- Modify: `docs/WORDPRESS-REFERENCE.md` (sections 10.0, 10.1, 10.2)

- [ ] **Step 1: Update §10.0 contract surface table**

The current table (around line 302 area) lists 7 cross-package hooks. Reduce to 2 rows:
- `sn_purge_all_caches_result` — filter — `inc/template-maintenance.php` wraps `sn_purge_all_caches()`.
- `sn_clear_template_overrides_result` — filter — `inc/template-maintenance.php` wraps `sn_clear_template_overrides()`.

Remove rows for: `sn_self_heal_force_run_result`, `sn_updater_branch`, `sn_updater_revcount`, `sn_updater_force_check`, `sn_updater_clear_error`.

Add a one-line note above or below the table: *"Retired in theme v8.3.0 (Phase 2b): the 5 updater/self-heal contracts. See [Phase 2b spec](superpowers/specs/2026-05-15-phase-2b-cleanup-design.md)."*

- [ ] **Step 2: Mark §10.1 (The updater) as retired**

Replace the body of §10.1 with:

```markdown
### 10.1 The updater — RETIRED in v8.3.0

The GitHub-poll self-updater (`inc/updater.php`) and the associated
`sn_updater_*` contracts were removed in theme v8.3.0 (2026-05-15) when
Phase 2b landed. Theme deploys now ride Cloudways' git-pull on tag push
(see [Phase 2a spec](superpowers/specs/2026-05-15-cloudways-auto-deploy-design.md))
which makes the WP-Cron SWR refresh + filter-injection layer redundant.

If you're maintaining a fork that still needs in-WP update polling,
restore from git history at the v8.2.1 tag.
```

- [ ] **Step 3: Mark §10.2 (Self-heal) as retired**

Replace the body of §10.2 with:

```markdown
### 10.2 Self-heal — RETIRED in v8.3.0

The file-drift recovery module (`inc/template-self-heal.php`) was removed
in theme v8.3.0. Under Cloudways' git-pull deploys, the file tree is
atomically consistent with the deployed commit — there's nothing to "heal."

The `/heal-templates` plugin REST endpoint was retired in plugin v1.2.0
to match.
```

- [ ] **Step 4: Leave §10.3 (synthetic update label) and §10.4 (/notes route) untouched**

These are independent and remain accurate.

- [ ] **Step 5: Verify the doc still parses cleanly**

```bash
grep -n "^## §10\|^### §10\|^## 10\|^### 10" docs/WORDPRESS-REFERENCE.md
```

Expected: §10.0 (preserved), §10.1 (now "RETIRED"), §10.2 (now "RETIRED"), §10.3, §10.4 — section structure intact.

- [ ] **Step 6: Commit**

```bash
cd /Users/juanlentino/projects/signal-and-noise/.claude/worktrees/nice-goldstine-063551 && \
git add docs/WORDPRESS-REFERENCE.md && \
git commit -m "docs(WP-REFERENCE): mark §10.1 + §10.2 retired; shrink §10.0 table

Reflects Phase 2b deletions: 5 cross-package contracts retired, 2 remain
(sn_purge_all_caches_result, sn_clear_template_overrides_result).
§10.3/§10.4 unchanged."
```

---

### Task 11: Add Cloudflare purge step to `.github/workflows/deploy.yml`

**Files:**
- Modify: `.github/workflows/deploy.yml`

- [ ] **Step 1: Append the new step**

After the existing "Trigger git pull (theme deploy path)" step, append:

```yaml
      - name: Purge Cloudflare cache
        env:
          WP_DEPLOY_USER: ${{ secrets.WP_DEPLOY_USER }}
          WP_DEPLOY_APP_PASSWORD: ${{ secrets.WP_DEPLOY_APP_PASSWORD }}
          PURGE_URL: 'https://juanlentino.com/wp-json/signal-noise/v1/purge-cache'
        run: |
          set -euo pipefail
          # Let Cloudways' git pull settle + OPcache invalidate.
          sleep 5
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

Match the existing 2-space indentation style of the file.

- [ ] **Step 2: Verify YAML parses**

```bash
cd /Users/juanlentino/projects/signal-and-noise/.claude/worktrees/nice-goldstine-063551 && \
python3 -c "import yaml; yaml.safe_load(open('.github/workflows/deploy.yml'))" && echo "YAML OK"
```

Expected: `YAML OK`. If parse fails, fix indentation/quoting before continuing.

- [ ] **Step 3: Commit**

```bash
cd /Users/juanlentino/projects/signal-and-noise/.claude/worktrees/nice-goldstine-063551 && \
git add .github/workflows/deploy.yml && \
git commit -m "ci: purge Cloudflare cache after Cloudways /git/pull

Third step in deploy.yml POSTs to /wp-json/signal-noise/v1/purge-cache
with WP Application Password Basic auth (secrets pre-set: WP_DEPLOY_USER,
WP_DEPLOY_APP_PASSWORD). Replaces the upgrader_process_complete hook
retired in plugin v1.2.0."
```

---

### Task 12: Bump theme to v8.3.0 + CHANGELOG + tag

**Files:**
- Modify: `style.css` (Version field)
- Modify: `CHANGELOG.md` (prepend new entry)

- [ ] **Step 1: Bump style.css Version**

In `style.css`, find the header comment and change:

```css
Version: 8.2.1
```

to:

```css
Version: 8.3.0
```

- [ ] **Step 2: Prepend CHANGELOG entry**

In `CHANGELOG.md`, insert at the top (after the H1 title, before the previous `[8.2.1]` entry):

```markdown
## [8.3.0] - 2026-05-15

### Removed
- `inc/updater.php` (~683 LOC) — GitHub-poll self-updater, obsolete since Cloudways auto-deploy (Phase 2a).
- `inc/template-self-heal.php` (~488 LOC) — file-drift recovery, redundant under atomic git-pull deploys.
- `inc/template-maintenance.php` — `upgrader_process_complete` hook + two `admin_init` detectors (version-change + template-mtime tracker), ~100 LOC.

### Added
- `.github/workflows/deploy.yml` — third step posts to `/wp-json/signal-noise/v1/purge-cache` after Cloudways `/git/pull` so theme deploys atomically refresh Cloudflare edge cache.

### Changed
- Cross-package contract surface shrinks from 7 hooks to 2. Updater filters (`sn_updater_branch`, `sn_updater_revcount`, `sn_updater_force_check`, `sn_updater_clear_error`) and the self-heal filter (`sn_self_heal_force_run_result`) are retired. Plugin v1.2.0 expects this and renders correctly.
- `docs/WORDPRESS-REFERENCE.md §10` updated to reflect the new contract surface (§10.1 + §10.2 marked retired).
```

- [ ] **Step 3: Commit + push (worktree pattern) + tag + push tag**

```bash
cd /Users/juanlentino/projects/signal-and-noise/.claude/worktrees/nice-goldstine-063551 && \
git add style.css CHANGELOG.md && \
git commit -m "v8.3.0: delete obsolete updater + self-heal; deploy-time CF purge

Removes ~1,400 LOC of update-polling and file-drift infrastructure made
redundant by Phase 2a's auto-deploy pipeline. Cross-package contract
surface collapses from 7 hooks to 2. New deploy.yml step purges
Cloudflare edge cache after every git-pull deploy.

Coordinated with plugin v1.2.0 (shipped first)." && \
git push origin HEAD:main && \
git tag -a v8.3.0 -m "v8.3.0 — delete obsolete updater + self-heal; deploy-time CF purge" && \
git push origin v8.3.0
```

- [ ] **Step 4: Watch the GitHub Actions deploy fire**

Within 10s of the tag push, the `Deploy to Cloudways` workflow should start.

```bash
sleep 10 && \
gh run list --repo juanlentino/signal-and-noise --workflow=deploy.yml --limit 1
```

Expected: a run in `queued` or `in_progress` status with workflow_run trigger from the `v8.3.0` tag.

---

### Task 13: Verify end-to-end deploy + purge

**Files:** N/A (verification only)

- [ ] **Step 1: Wait for the workflow run to complete**

```bash
# Up to ~90s for the 3 steps to finish.
gh run watch --repo juanlentino/signal-and-noise --exit-status \
  $(gh run list --repo juanlentino/signal-and-noise --workflow=deploy.yml --limit 1 --json databaseId -q '.[0].databaseId')
```

Expected: exit code 0; the watched run completes with `success`.

- [ ] **Step 2: Inspect the three step results**

```bash
gh run view --repo juanlentino/signal-and-noise \
  $(gh run list --repo juanlentino/signal-and-noise --workflow=deploy.yml --limit 1 --json databaseId -q '.[0].databaseId')
```

Expected: all three steps (Authenticate with Cloudways, Trigger git pull, Purge Cloudflare cache) show ✓. The Purge step log should show `HTTP 200` and a body like `{"ok":true,"message":"All caches purged.","data":{"cleared":N}}`.

If the Purge step shows non-200, STOP — diagnose before proceeding. Likely causes: WP App Password mismatched, REST endpoint disabled (e.g., security plugin blocking `/wp-json/`), or Cloudflare API token misconfigured.

- [ ] **Step 3: Verify file deletion on live server**

Visit `https://juanlentino.com/wp-content/themes/signal-and-noise/inc/updater.php`.

Expected: 404 (file deleted on disk by `git pull`). If 200, OPcache may still hold a stale entry — wait ~30s and retry; if persistent, manual OPcache reset via Cloudways app dashboard.

- [ ] **Step 4: Verify Cloudflare cache was purged**

```bash
curl -sS -I https://juanlentino.com/ | grep -i 'cf-cache-status'
```

Expected: `cf-cache-status: MISS` (or `DYNAMIC` for non-cached responses) on the first request after deploy.

Second request:

```bash
curl -sS -I https://juanlentino.com/ | grep -i 'cf-cache-status'
```

Expected: `cf-cache-status: HIT` (cache rebuilt cleanly).

- [ ] **Step 5: Verify plugin admin page still renders cleanly**

Visit `https://juanlentino.com/wp-admin/admin.php?page=signal-noise`.

Expected:
- Dashboard tab renders without errors, no "Latest on GitHub" row.
- "Purge All Caches" button works (click → spinner → success notice).
- No PHP warnings in source (view-source: confirm no `<b>Warning</b>` strings).
- Admin bar has no "Check updates" entry.

- [ ] **Step 6: Verify the theme inc/ directory matches spec acceptance criterion 3**

```bash
# On the live server via Cloudways SSH OR by re-checking the worktree:
cd /Users/juanlentino/projects/signal-and-noise/.claude/worktrees/nice-goldstine-063551 && \
ls inc/ | sort
```

Expected output (exactly):
```
assets-frontend.php
frontend-filters.php
notes-and-provenance.php
og-image.php
page-notes-render.php
page-notes-template.php
patterns.php
reading-time.php
seed-content
setup.php
template-maintenance.php
```

No `updater.php`, no `template-self-heal.php`.

---

## Rollback paths

**If plugin v1.2.0 breaks the admin page:** WP admin → Plugins → re-upload the v1.1.0 zip (regenerate from git: `git -C signal-and-noise-tools archive --format=zip --prefix=signal-and-noise-tools/ -o /tmp/sn-tools-v1.1.0.zip v1.1.0`). Theme phase blocked until plugin works.

**If theme v8.3.0 deploys but the live site 500s:** Revert via Cloudways dashboard → Application Settings → roll back deployment. Or: push a hotfix tag from `v8.2.1`: `git checkout v8.2.1 && git tag -a v8.3.1 -m 'hotfix: revert 8.3.0' && git push origin v8.3.1`.

**If the deploy.yml CF-purge step fails but the site is otherwise healthy:** Manually click "Purge All Caches" in the plugin admin page. Then debug the workflow step (most likely the App Password expired/was revoked; regenerate per spec §9).

---

## Out of scope (deferred to other phases)

- Phase 2c: plugin auto-deploy via SSH (queued, not blocking).
- Phase 3: theme-coupled file moves (og-image, reading-time, notes-and-provenance).
- Live MU plugin deletion: `rm wp-content/mu-plugins/rss-plausible-tracker.php` via SFTP. Manual operation, completes Phase 4 early-slice.
- Orphaned options cleanup (`sn_github_local_sha`, `sn_heal_cooldown_*`, `sn_deployed_version`, `sn_templates_latest_mtime`). Non-blocking; do a one-shot maintenance script later if desired.
