# Session handoff — 2026-05-15 (end of Phase 2b)

Picks up after the 2026-05-15 session that shipped Phase 2b — deleted ~1,400 LOC of obsolete updater + self-heal infrastructure across both repos and replaced the dead `upgrader_process_complete` Cloudflare-purge path with a deploy-time REST call.

## Where the project is right now

### Live versions

| Package | Version | Deployment |
|---|---|---|
| Theme `signal-and-noise` | `v8.3.0` | Cloudways auto-pulls on tag push + auto-purges CF edge cache via GHA workflow |
| Plugin `signal-and-noise-tools` | `v1.2.0` | Manual install via WP admin Upload Plugin |

### Verified live state

- **`https://juanlentino.com/wp-content/themes/signal-and-noise/style.css`** returns `Version: 8.3.0`.
- **`/wp-content/themes/signal-and-noise/inc/updater.php`** returns 404 (file deleted).
- **`/wp-content/themes/signal-and-noise/inc/template-self-heal.php`** returns 404 (file deleted).
- **`POST /wp-json/signal-noise/v1/purge-cache`** with App Password + `Content-Length: 0` returns `{"ok":true,"message":"All caches purged.","data":{"cleared":0}}` — confirmed end-to-end on workflow run [25951921700](https://github.com/juanlentino/signal-and-noise/actions/runs/25951921700) (all 3 steps green: auth, git pull, purge cache, completed in 11s).
- **CF cache invalidation verified manually:** asset MISS → HIT → (purge call) → MISS cycle confirmed against `style.css`.

### Commits landed this session

**Plugin repo** (`juanlentino/signal-and-noise-tools` → `main`):

| SHA | Title |
|---|---|
| `7b1977f` | `rest: drop /check-updates + /heal-templates; refactor /full-reset` |
| `fdf3b97` | `admin-page: drop 'Latest on GitHub' row + Check Now button` |
| `86f26a5` | `admin-page: drop Heal Templates form handler + UI card` |
| `4275b92` | `admin-bar: drop 'Check updates' quick action` |
| `f5407ed` | `cf-purge: drop upgrader_process_complete hook` |
| `a631df7` | `v1.2.0: drop updater UI + REST surface; deploy-time CF purge` (tagged) |

**Theme repo** (`juanlentino/signal-and-noise` → `main`):

| SHA | Title |
|---|---|
| `ae7b9a0` | `docs: spec for Phase 2b` |
| `3474d36` | `docs: implementation plan for Phase 2b (13 atomic commits)` |
| `89fe55e` | `remove inc/updater.php — obsolete under Cloudways auto-deploy` |
| `e048d26` | `remove inc/template-self-heal.php — atomic deploys make it redundant` |
| `f8b53c5` | `template-maintenance: drop deploy-detector + mtime-tracker hooks` |
| `e936e6f` | `docs(WP-REFERENCE): mark §10.1 + §10.2 retired; shrink §10.0 table` |
| `71258e0` | `ci: purge Cloudflare cache after Cloudways /git/pull` |
| `cd1cbe0` | `v8.3.0: delete obsolete updater + self-heal; deploy-time CF purge` (tagged) |
| `99cea27` | `ci: send Content-Length: 0 on /purge-cache POST to satisfy Cloudways WAF` |

The `99cea27` post-release commit was the WAF fix landed via `workflow_dispatch` re-run, not a new tag. Future tag pushes inherit the fix.

## What's queued next

### Outstanding manual op (one-time)

**Delete the live MU plugin file:** Cloudways → SSH or SFTP → `rm wp-content/mu-plugins/rss-plausible-tracker.php`. With the plugin's guard #2 in place, the plugin currently defers tracker loading to that legacy MU file. Until you delete it, the plugin's tracker module stays dormant. Functional impact today: zero — tracking works via the legacy MU file. Just one-time cleanup.

### Phase 2c — plugin auto-deploy (optional, not blocking)

Cloudways' Deployment Via Git supports only one repo per app. We used that slot for the theme. To get the plugin auto-deploying too, the realistic path is SSH-based deploy from GitHub Actions to Cloudways' `master_user` (`master_syguxtyfsh`, public IP `157.245.116.64`). Defer until plugin update cadence justifies it.

### Phase 3 — theme-coupled file moves

Original Phase 1 spec deferred these because they're presentation-coupled. Per-file judgment call: `og-image.php`, `reading-time.php`, `notes-and-provenance.php`, `page-notes-template.php`, `page-notes-render.php`. Lowest priority.

### Deferred hygiene (still rejected by default)

- Inline-styles → external CSS refactor (124 instances). Real handbook violation, no operational payoff.
- Full i18n coverage. Stripped in v8.1.1; re-introducing requires ~hundreds of strings + `.pot` file. Zero value for a single-author tool.

## Phase 2b: things worth remembering

### What went smoothly

- **Spec-first → plan → subagent-driven execution.** The spec (commit `ae7b9a0`) caught the right scope; the plan (commit `3474d36`) made the 13 commits mechanical; subagent dispatches with bounded prompts kept main-session context lean.
- **Existing REST endpoint discovery.** The plugin's `/purge-cache` endpoint was already wired correctly end-to-end before this phase. The CF-purge problem reduced to "call the existing thing with the right auth headers" — zero new plugin code.
- **WP Application Passwords for the workflow auth.** Set up once via `wp-admin/profile.php`, stored as GHA secrets (`WP_DEPLOY_USER=juanlentino`, `WP_DEPLOY_APP_PASSWORD`), no plugin-side auth code. Revocable independently.

### What needed firefighting

- **Cloudways WAF rejected the workflow's POST.** First v8.3.0 deploy succeeded on git-pull but failed on CF purge with empty-body HTTP 400 (run `25951865956`). Root cause: Cloudways' default ModSecurity ruleset rejects zero-byte POSTs at the WAF layer before they reach WP. Fix: `-H 'Content-Length: 0'` on the curl call. Documented as gotcha #13 in [docs/WORDPRESS-REFERENCE.md §13](../../WORDPRESS-REFERENCE.md) + memory entry [feedback_cloudways_waf_zero_byte_post.md](/Users/juanlentino/.claude/projects/-Users-juanlentino-Projects-signal-and-noise/memory/feedback_cloudways_waf_zero_byte_post.md). **Diagnostic fingerprint:** WP always returns JSON error bodies on 4xx; a bare 400 with no body is always upstream (WAF/proxy/web server).
- **Plan gap: `heal_templates` form handler in admin-page.php.** The plan listed the "Latest on GitHub" row + Check Now button but didn't mention the parallel "Heal Templates" form handler + UI card. Caught during Task 2 execution (commit `86f26a5`). Reminder for future plans: when the spec says "drop the updater/self-heal UI entirely," enumerate ALL of it, not just the conceptually obvious pieces.

### What stayed deferred

- Orphaned-options cleanup: `sn_github_local_sha`, `sn_github_branch_*` transients, `sn_heal_cooldown_*`, `sn_deployed_version`, `sn_templates_latest_mtime`. Non-blocking; defer to a one-shot maintenance WP-CLI script if/when desired.

## Architecture snapshot post-Phase 2b

```
[ Theme repo ]                       [ Plugin repo ]
─────────────                        ──────────────
Theme presentation, FSE templates    Operational tooling, admin UI,
+ deploy.yml (auto-pulls,            REST surface, CF purge,
+ purges CF after deploy)            Plausible, RSS tracker

           │                                  │
           │    Cross-package contracts        │
           │     (2 hooks, was 7):              │
           │  • sn_purge_all_caches_result      │
           │  • sn_clear_template_overrides_    │
           │       result                       │
           │                                    │
           └────────── shared WP install ───────┘
                       at juanlentino.com
                       (Cloudways syntharchy-wp)
```

Theme `inc/` is now 11 files (was 13). `functions.php` is 9 lines of `require_once` (was 11). The retired `inc/updater.php` (683 LOC) and `inc/template-self-heal.php` (488 LOC) live in git history at `v8.2.1` if anyone needs to fork them out for a non-Cloudways stack.

## Quick-start commands for next session

**Smoke-test the deploy any time:**

```bash
cd /Users/juanlentino/projects/signal-and-noise   # or worktree path
git tag -a v8.3.1-smoke -m "smoke" && git push origin v8.3.1-smoke
sleep 30 && gh run list --repo juanlentino/signal-and-noise --workflow=deploy.yml --limit 1
# expect: completed success, 3 steps green
git tag -d v8.3.1-smoke && git push origin :refs/tags/v8.3.1-smoke
```

**Manual CF purge from CLI** (same path the workflow uses):

```bash
auth=$(printf '%s:%s' 'juanlentino' '<APP_PASSWORD>' | base64)
curl -X POST https://juanlentino.com/wp-json/signal-noise/v1/purge-cache \
  -H "Authorization: Basic $auth" -H 'Content-Length: 0'
```

**Start Phase 2c (when ready):** Read [docs/superpowers/specs/2026-05-15-phase-2b-cleanup-design.md §"Out of scope"](../specs/2026-05-15-phase-2b-cleanup-design.md) for the SSH-deploy approach. Cloudways credentials live in the prior session's transcript / Cloudways dashboard.

## Anti-checklist (things NOT to do)

- **Don't** push a deploy-time REST call without `Content-Length: 0` (or a body). Cloudways WAF rejects with empty 400 and you'll lose 10 minutes debugging WP that's already healthy.
- **Don't** restore `inc/updater.php` or `inc/template-self-heal.php` from history thinking they're useful. They were dead under Cloudways auto-deploy; that's why Phase 2b removed them. Restore them ONLY if migrating off Cloudways to a stack that needs in-WP update polling.
- **Don't** tag the theme without setting up the GHA secrets first (`WP_DEPLOY_USER`, `WP_DEPLOY_APP_PASSWORD`). They're set now; if you ever rotate them, retain the names.
- **Don't** treat the workflow's CF-purge step failure as a deploy failure. The git-pull step is the deploy. If purge fails, the site is still updated; just hit "Purge All Caches" in the plugin admin manually.

## Session statistics

| Metric | Value |
|---|---|
| Phases completed | 2b |
| Releases shipped | Theme v8.3.0 + Plugin v1.2.0 |
| Atomic commits | 14 (6 plugin + 8 theme, including spec/plan/handoff docs and one post-release WAF fix) |
| Lines of code deleted from theme | ~1,400 |
| Cross-package contracts retired | 5 (out of 7); 2 remain |
| Plugin REST routes retired | 2 (`/check-updates`, `/heal-templates`); 1 refactored (`/full-reset` now 2-step) |
| Plugin admin UI elements retired | 3 (status row, Check Now button, Heal Templates card, admin-bar quick action) |
| Workflow runs (deploy verifications) | 2 (1 failed on WAF, 1 success after fix) |
| New memory entries | 2 (cloudways WAF gotcha; updated project architecture) |
| Open manual ops remaining | 1 (delete legacy `mu-plugins/rss-plausible-tracker.php` via SFTP) |
