# Session handoff — 2026-05-16 (end of Phase 5)

Picks up after Phase 3 wrapped, adds the WP-native update integration the user requested when they noticed the theme + plugin weren't appearing in `wp-admin/update-core.php` alongside other plugins. Also surfaces an unresolved architectural preference (click-to-update vs auto-deploy) that should drive a Phase 6 if/when the user decides to revisit.

## Where the project is right now

### Live versions

| Package | Version | Deployment |
|---|---|---|
| Theme `signal-and-noise` | `v8.5.0` | Auto-deploys on tag push (Phase 2a) + auto-purges CF (Phase 2b) + registered in WP update registry (Phase 5) |
| Plugin `signal-and-noise-tools` | `v1.4.0` | Auto-deploys via SSH on tag push (Phase 2c) + auto-purges CF + registered in WP update registry (Phase 5) |

### Both repos are now public

Visibility changed during this session via `gh repo edit --visibility public`. Reason: Phase 5's GitHub Tags API polling needs to work anonymously (no PAT on the WP server). Anonymous API rate limit is 60 req/hr, plenty given the 12h transient cache (≤4 req/day total).

If you ever want to make them private again, you'd need to:
1. Add `SN_GITHUB_PAT` to `wp-config.php` as a constant
2. Patch `inc/wp-update-integration.php` in both packages to read it and add `Authorization: Bearer <PAT>` to the wp_remote_get headers
3. Generate a fine-grained PAT scoped to those two repos with `contents: read`

### Phase 5 commits

**Theme repo** (`juanlentino/signal-and-noise` → `main`):

| SHA | Title |
|---|---|
| `a38c858` | `v8.5.0: WP-native update integration for theme` (tagged) |

**Plugin repo** (`juanlentino/signal-and-noise-tools` → `main`):

| SHA | Title |
|---|---|
| `8337574` | `v1.4.0: WP-native update integration for plugin` (tagged) |

### What Phase 5 added

Two ~70-LOC modules (one per package) that hook into WordPress's native update system:

- **`theme/inc/wp-update-integration.php`** — hooks `pre_set_site_transient_update_themes`, polls `api.github.com/repos/juanlentino/signal-and-noise/tags` every 12h, registers the theme in WP's update registry.
- **`plugin/inc/wp-update-integration.php`** — same pattern for `pre_set_site_transient_update_plugins`.

**Effect:** both packages appear in WP's normal admin UIs (`themes.php`, `plugins.php`) with full metadata (description, version, "View details" link to GitHub). Under normal operation (auto-deploy keeps local in sync with GitHub) they land in WP's `->no_update` bucket — registered but not shown in `update-core.php`'s "Updates Available" section because there's nothing to update. They WOULD surface in `update-core.php` if auto-deploy ever falls behind a tag — useful deploy-health indicator.

**"Update Now" button:** intercepted by `upgrader_pre_install` filter, returns a `WP_Error` with a friendly message: "managed via auto-deploy on tag push — push a git tag instead." This prevents WP's installer from overwriting the `.git` checkout that auto-deploy depends on.

### What Phase 5 reframed

When the user noticed their packages weren't showing in `update-core.php` even with `?force-check=1`, they made the sharp observation: **"Because they updated automatically."** Auto-deploy means by the time WP polls, local already === GitHub latest, so there's never an "update available" state to show in update-core.php's UI.

This surfaced an architectural preference we'd never explicitly elicited: the user wanted **click-to-update** behavior in WP admin, not auto-deploy. The current architecture has auto-deploy as the only install path, with WP's "Update Now" button intercepted and refused.

**The user explicitly said "let's move on then..." rather than asking for a rework.** Phase 6 is queued as an optional revisit.

## Phase 6 candidate (queued, optional)

**Click-to-update via WP admin** — would be a meaningful deploy-architecture revisit. Three possible shapes, in increasing scope:

### Option A: Read-only dashboard with deep links (smallest)
- Plugin admin Dashboard tab gains a "Deploy Status" panel
- Shows: current theme version, current plugin version, latest GitHub tag for each, last deploy timestamp (from GHA API)
- Includes deep links: "Trigger theme deploy" → opens [GitHub Actions deploy workflow](https://github.com/juanlentino/signal-and-noise/actions/workflows/deploy.yml) in new tab, where user clicks "Run workflow"
- No new secrets. No new install path. Just visibility + an obvious "go here to deploy manually" hand-off.
- Scope: ~80 LOC plugin-side. Ships as plugin v1.5.0 (minor — new admin UI). No theme change.

### Option B: WP-admin buttons that trigger workflow_dispatch via GitHub API (medium)
- Plugin admin Dashboard tab gains "Deploy Theme" and "Deploy Plugin" buttons
- Each button calls `POST api.github.com/repos/<owner>/<repo>/actions/workflows/deploy.yml/dispatches` with the latest tag as ref
- Auto-deploy still exists as a fallback on tag push; clicks become an alternative path
- Requires a GitHub fine-grained PAT (one PAT for both repos — `actions: write`)
- Scope: ~120 LOC plugin-side + PAT management UI. Ships as plugin v1.5.0.

### Option C: WP "Update Now" actually installs (largest)
- Remove the `upgrader_pre_install` intercept
- Let WP's installer download the GitHub zipball, extract, overwrite the plugin dir
- Disable the auto-deploy workflow on tag push (keep workflow_dispatch only as fallback)
- Accept that the SSH auto-deploy architecture is retired in favor of WP-installer-as-deployer
- Need to handle: `.git` directory will be deleted by WP's install (since the zip doesn't contain it); subsequent SSH-based "git fetch" deploys will fail until a re-clone
- Scope: real architectural revisit, deserves its own brainstorm-spec-plan cycle. Both packages bump major (theme v9.0.0, plugin v2.0.0 — auto-deploy retirement is a real behavioral change).

**Recommendation if Phase 6 ever happens:** Option A. Gives the click-based control surface in WP admin without changing the deploy mechanism. Auto-deploy stays as the safety net for cases when you forget to click. Cleanest upgrade path; no need to retire anything.

## Outstanding manual ops (carried over)

1. **Visit `/wp-admin/`** at your convenience — fires the OG card backfill (`sn_migrate_backfill_og_cards` migration runs on `admin_init` exactly once per environment, gated by `sn_og_backfilled_v1` option).
2. **Delete the legacy MU plugin file** via SFTP: `rm wp-content/mu-plugins/rss-plausible-tracker.php`. Zero functional impact today; just cleanup.
3. **Remove master-level SSH key** from Cloudways Server Management → SSH Public Keys (the `sn-tools-deploy` entry added during the wrong-direction setup in Phase 2c). The app-scoped `sn-plugin` key is the only one we actually use. Removing the master one is the final lockdown step from Phase 2c.

## Architecture snapshot (post-Phase 5)

```
[ Theme repo (PUBLIC) ]                    [ Plugin repo (PUBLIC) ]
─────────────────────                      ──────────────────────
auto-deploy via Cloudways API              auto-deploy via SSH (Phase 2c)
on tag push (Phase 2a)                     on tag push as app-scoped sn-plugin
        │                                          │
        │ tag push                                 │ tag push
        ▼                                          ▼
[ deploy.yml (theme repo) ]                [ deploy.yml (plugin repo) ]
        │                                          │
        │ /oauth + /git/pull                       │ ssh + git checkout
        │                                          │
        └────────┬────────────────┬────────────────┘
                 │                │
                 ▼                ▼
         POST /wp-json/signal-noise/v1/purge-cache
         (CF edge cache invalidation)

[ Independently of deploys ]
─────────────────────────────
WP polls GitHub Tags API every 12h (cached in site transients)
└─ pre_set_site_transient_update_themes  → registers theme in WP update registry
└─ pre_set_site_transient_update_plugins → registers plugin in WP update registry
└─ upgrader_pre_install                  → intercepts "Update Now" with WP_Error

[ User experience ]
───────────────────
- Push tag    → ~30s later, live + CF purged + WP registry refreshed within 12h
- wp-admin/themes.php   → theme tile with proper metadata
- wp-admin/plugins.php  → plugin row with proper metadata
- wp-admin/update-core.php → silent (no updates available — local always === latest)
```

## Phase history (all phases since session start)

- 2026-04-13 v6.0.0 — initial modularization
- 2026-05-15 v8.1.1 — handbook hygiene pass
- 2026-05-15 v8.2.0 (theme) + v1.0.0 (plugin) — Phase 1 split (9 modules moved theme→plugin)
- 2026-05-15 v8.2.1 + v1.1.0 — Phase 4 early slice (RSS Plausible Tracker)
- 2026-05-15 Phase 2a — Cloudways auto-deploy via GitHub Actions for theme
- 2026-05-15 v8.3.0 + v1.2.0 — Phase 2b (delete obsolete updater/self-heal; deploy-time CF purge)
- 2026-05-16 Phase 2c — plugin SSH-based auto-deploy via app-scoped sn-plugin user
- 2026-05-16 v8.4.0/8.4.1 + v1.3.0 — Phase 3 (theme-coupled file moves)
- 2026-05-16 v8.5.0 + v1.4.0 — Phase 5 (WP-native update integration) ← THIS SESSION ENDS HERE

## Quick-start commands for next session

**Trigger a deploy manually (Phase 6 candidate Option A's deep-link target):**

```bash
# Theme:
gh workflow run deploy.yml --repo juanlentino/signal-and-noise --ref v8.5.0
# Plugin:
gh workflow run deploy.yml --repo juanlentino/signal-and-noise-tools --ref v1.4.0
```

(Both workflows have `workflow_dispatch:` triggers from earlier phases. They run idempotently — running v8.5.0 again on a server already at v8.5.0 is a no-op git-pull.)

**Force WP to re-poll GitHub Tags API:**

```bash
# Purge the integration's transients (and everything else)
auth=$(printf '%s:%s' 'juanlentino' '<APP_PASSWORD>' | base64)
curl -X POST 'https://juanlentino.com/wp-json/signal-noise/v1/purge-cache' \
  -H "Authorization: Basic $auth" -H 'Content-Length: 0'
# Then visit wp-admin/update-core.php?force-check=1 — WP re-runs the filters
```

**See current WP-registered versions (authoritative, bypasses all caches):**

```bash
curl -sS -H "Authorization: Basic $auth" \
  'https://juanlentino.com/wp-json/wp/v2/themes?status=active'
curl -sS -H "Authorization: Basic $auth" \
  'https://juanlentino.com/wp-json/wp/v2/plugins/signal-and-noise-tools/signal-and-noise-tools'
```

## Things NOT to do in next session

- **Don't** re-make the repos private without first patching `inc/wp-update-integration.php` to use a PAT. Anonymous API calls 404 on private repos; integration silently fails.
- **Don't** click "Update Now" in WP admin for these packages and expect it to do something. The `upgrader_pre_install` intercept will return a WP_Error — that's by design.
- **Don't** start Phase 6 without explicit user direction. The user's "let's move on" was direction for THIS session; revisit is opt-in.
- **Don't** assume the OG card backfill ran. It runs on `admin_init` only — until the user visits `/wp-admin/`, cards on existing posts will fall back to site icon via Yoast.

## Session statistics

| Metric | Value |
|---|---|
| Phases completed | 5 (Phase 3 + Phase 5) |
| Releases shipped | Theme v8.4.0, v8.4.1, v8.5.0 + Plugin v1.3.0, v1.4.0 |
| Atomic commits | 20+ across both repos |
| Lines added (Phase 5 alone) | ~250 (120 theme + 130 plugin) |
| Repos flipped to public | 2 |
| New WP filter integrations | 2 (one per package, hooking native WP update transients) |
| Cross-package contracts | Unchanged at 3 (Phase 5 is intra-package — no theme↔plugin coupling) |
| User-facing decisions surfaced | 1 (click-to-update preference, queued as Phase 6) |
