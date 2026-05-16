# Session handoff — 2026-05-16 (end of Phase 2c)

Picks up after the 2026-05-16 session that shipped Phase 2c — plugin auto-deploy via SSH from GitHub Actions, with an application-scoped user (`sn-plugin` → `nffqxsrgxz`) rather than the master server user. Both packages now auto-deploy on tag push; the maintainer's release ritual is `git tag -a vX.Y.Z && git push origin vX.Y.Z` for either repo, full stop.

## Where the project is right now

### Live versions

| Package | Version | Deployment |
|---|---|---|
| Theme `signal-and-noise` | `v8.3.0` | Cloudways auto-pulls + auto-purges CF on tag push (Phase 2a + 2b workflow) |
| Plugin `signal-and-noise-tools` | `v1.2.0` | **SSH auto-deploy + auto-purges CF on tag push (Phase 2c, this session)** |

Both repos now use the same release ritual; the only difference is which auth mechanism the workflow uses internally (theme = Cloudways API for `/git/pull`, plugin = direct SSH because Cloudways' Git Integration only supports one repo per app).

### Phase 2c security model

The deploy SSH key is bound to a **dedicated, application-scoped Cloudways user** called `sn-plugin`. Cloudways aliases this user to `nffqxsrgxz` (the underlying application user). Concrete properties:

- **Filesystem access:** only `/home/master/applications/nffqxsrgxz/` (this WP app's directory tree). No access to other Cloudways apps on the same server. No `sudo`. No `/etc/`, no `/var/`, no other users' homes.
- **WP-CLI access:** yes — runs against this app's DB only.
- **Shell:** real shell (Cloudways grants when you add an "additional SSH user" in Application Settings).
- **Key location:** the GitHub deploy key the user uses to pull from `juanlentino/signal-and-noise-tools` lives in their writable `~/.openssh/cw-to-gh-deploy_ed25519` (Cloudways convention — `~/.ssh/` is root-owned for additional users; `~/.openssh/` is user-writable).

**Blast radius if the GHA `SSH_PRIVATE_KEY` secret ever leaks:** this WP app's content + DB rows (same as a compromised WP admin password). NOT the whole Cloudways server. An attacker can:
- Modify plugin code (and the cron job/REST endpoints it registers)
- Read/modify WP DB (everything WP-CLI can do)
- Read uploaded media files
- NOT touch other apps, other users, system files, or escalate to root

**Earlier intermediate setup (now retired):** when first wiring this up, the deploy key was added at Cloudways → Server Management → SSH Public Keys (master_user scope). That gave root-equivalent server access. We caught it before committing the workflow and switched to the application-scoped user.

### Commits landed this session

**Plugin repo** (`juanlentino/signal-and-noise-tools` → `main`):

| SHA | Title |
|---|---|
| `8860101` | `ci(Phase 2c): SSH-based auto-deploy via app-scoped sn-plugin user` |

That's the only plugin-repo commit — single commit adding `.github/workflows/deploy.yml` + the CHANGELOG infrastructure note. No version bump (`.github/workflows/` is build infra per CLAUDE.md convention).

**Theme repo** (this worktree's `main` branch): only the handoff doc + memory updates. No code change.

### One-time live cutover (irreversible, already done)

The plugin was previously installed via WP admin → Upload Plugin, which placed it at `wp-content/plugins/signal-and-noise-tools-1.2.0/` — an artifact of the zip filename. To make `git checkout` work cleanly across future versions, we renamed it to the canonical `signal-and-noise-tools/`:

1. `wp plugin deactivate signal-and-noise-tools-1.2.0` (graceful — settings stored in `wp_options` survive)
2. `mv signal-and-noise-tools-1.2.0 signal-and-noise-tools-1.2.0-old` (backup)
3. `git clone git@github.com:juanlentino/signal-and-noise-tools.git signal-and-noise-tools` (as `sn-plugin` user, so ownership = `nffqxsrgxz:www-data` — matches the rest of WP)
4. `git checkout v1.2.0`
5. `wp plugin activate signal-and-noise-tools` (canonical slug)

Sub-second downtime (between deactivate and activate). All plugin options/transients survived.

### GHA secrets on plugin repo

10 secrets configured on `juanlentino/signal-and-noise-tools`. Five are actively used by `deploy.yml`; the other five are inherited from Phase 1 setup or leftover from prior approaches.

| Secret | Used by | Notes |
|---|---|---|
| `SSH_PRIVATE_KEY` | deploy.yml step 1 | ED25519, paired with the `sn-plugin` SSH key on Cloudways |
| `SSH_HOST` | deploy.yml step 2 | `157.245.116.64` |
| `SSH_USER` | deploy.yml step 2 | `sn-plugin` |
| `SSH_KNOWN_HOSTS` | deploy.yml step 1 | Cloudways SSH host fingerprint |
| `WP_DEPLOY_USER` | deploy.yml step 3 | `juanlentino` (WP user with manage_options) |
| `WP_DEPLOY_APP_PASSWORD` | deploy.yml step 3 | WP Application Password for the user above |
| `CLOUDWAYS_*` (4) | unused | Pre-loaded from Phase 1 plan; harmless. Could be deleted, but they cost nothing |

## Smoke test (already passed, 2026-05-16)

Tag `v1.2.1-smoke` pushed → workflow fired automatically → ran 4 steps in 10s → returned success → `/wp-json/signal-noise/v1/purge-cache` returned 200 → cleaned up tag (locally + remote + on the live server's `git fetch --prune` + explicit local delete). Run: [25952592622](https://github.com/juanlentino/signal-and-noise-tools/actions/runs/25952592622).

## What needs your attention

### Outstanding cleanup (lower priority)

1. **(YOU) Remove the master-level SSH public key from Cloudways.** Console → Servers → your server → **Server Management → SSH Public Keys** → delete the entry labelled `sn-tools-deploy` (the one we added at server scope during the wrong-direction setup). The application-level key on the `sn-plugin` user is the only one we actually need. Removing the master one is the final lockdown step — until you do this, the same private key (held in GHA secrets) could be used to SSH as master_syguxtyfsh if someone redirected the connection.

2. **(YOU) Remove the two backup plugin directories on the live server** once you're confident the new setup is stable (a week or two):
   - `wp-content/plugins/signal-and-noise-tools-1.2.0-old` (the original Upload Plugin install)
   - `wp-content/plugins/signal-and-noise-tools-OLD-MASTER` (the master_user-owned dir from the wrong-direction setup)
   
   From SSH as `sn-plugin`: `cd /home/master/applications/nffqxsrgxz/public_html/wp-content/plugins && rm -rf signal-and-noise-tools-1.2.0-old signal-and-noise-tools-OLD-MASTER` (the second one is master-owned but `sn-plugin` can rm-rf because the parent dir is group-writable).

3. **(YOU, eventually) Delete the legacy MU plugin** carried over from before Phase 4: `rm wp-content/mu-plugins/rss-plausible-tracker.php`. Zero functional impact today — the plugin's guard #2 defers tracker loading to that file. After deletion, the plugin's bundled tracker module takes over seamlessly.

### Future-proofing notes

- **Future plugin releases** (v1.3.0, v1.4.0, …) follow the same ritual as theme releases: edit code → bump `Version:` in `signal-and-noise-tools.php` → CHANGELOG entry → commit → push → annotated tag → push tag. Workflow fires automatically. ~30s tag-to-live.
- **Rollback** (any release): on the live server as `sn-plugin`: `cd .../plugins/signal-and-noise-tools && git checkout vPREVIOUS_TAG`. Or trigger workflow_dispatch with `--ref vPREVIOUS_TAG`. (Note: workflow_dispatch must target a tag where `deploy.yml` exists — anything from v1.2.1+ once we tag again.)
- **If `SSH_PRIVATE_KEY` is ever compromised:** regenerate with `ssh-keygen -t ed25519 -f /tmp/new -N "" -C "sn-tools-deploy-$(date +%Y-%m-%d)"`, replace the public key on Cloudways → Application Credentials → SSH Public Keys for `sn-plugin`, then `printf '%s' "$(cat /tmp/new)" | gh secret set SSH_PRIVATE_KEY --repo juanlentino/signal-and-noise-tools`. Delete `/tmp/new` and `/tmp/new.pub`.

## Architecture snapshot (post-Phase 2c)

```
[ Theme repo ]                  [ Plugin repo ]
─────────────                   ──────────────
auto-deploy via Cloudways       auto-deploy via SSH-from-GHA as
Git Integration API on          dedicated app-scoped user
tag push (Phase 2a)             on tag push (Phase 2c)
        │                                 │
        │ tag push                        │ tag push
        ▼                                 ▼
[ deploy.yml (theme repo) ]    [ deploy.yml (plugin repo) ]
        │                                 │
        │ Cloudways API:                  │ SSH to sn-plugin@host:
        │ /oauth/access_token             │ git fetch --tags
        │ /git/pull                       │ git checkout <tag>
        │                                 │
        └────────┬───────────────┬────────┘
                 │               │
                 ▼               ▼
        POST /wp-json/signal-noise/v1/purge-cache
        (Basic auth via WP Application Password,
         with Content-Length: 0 to satisfy Cloudways WAF)
                 │
                 ▼
        Plugin's REST endpoint dispatches sn_purge_all_caches_result
        filter; theme listener runs sn_cf_purge_everything()
                 │
                 ▼
        [ Cloudflare edge cache invalidated ]
```

Both deploy paths converge on the same `/purge-cache` REST call. The plugin endpoint, the WP App Password auth, the CF purge function — all reused. Phase 2b's investment in `/purge-cache` paid off twice.

## Files / artifacts in this session

**Plugin repo:**
- `.github/workflows/deploy.yml` (new, 91 lines)
- `CHANGELOG.md` (+12 lines under existing v1.2.0 entry, sub-heading "Infrastructure — Phase 2c (no version bump)")

**Theme repo:**
- `docs/superpowers/handoffs/2026-05-16-end-of-phase-2c-handoff.md` (this file)

**Live Cloudways server (irreversible state changes):**
- New canonical plugin path: `wp-content/plugins/signal-and-noise-tools/` (git checkout, on tag v1.2.0)
- Two backup directories kept (see cleanup list above)
- `sn-plugin` additional SSH user created (via Cloudways UI)
- ED25519 public key added to `sn-plugin`'s `authorized_keys` (via Cloudways UI)
- GitHub deploy key (read-only) registered on `signal-and-noise-tools` repo
- Private GitHub deploy key + known_hosts placed in `/home/1432404.cloudwaysapps.com/nffqxsrgxz/.openssh/`

**Memory:** updated `project_architecture.md` to reflect Phase 2c.

## Things NOT to do in next session

- **Don't** add deploy keys at Cloudways server-level (master_user scope). Always app-level (Application Settings → Application Credentials). Server-level grants full server access — overkill and a real risk if the GHA secret leaks.
- **Don't** put SSH keys in master_user's `~/.ssh/` for plugin-deploy purposes. Use the application user's `~/.openssh/` directory.
- **Don't** rename the plugin directory away from canonical `signal-and-noise-tools/`. WP identifies plugins by directory name + bootstrap file; renaming triggers a deactivate/reactivate cycle on the next pageload and may lose admin state.
- **Don't** push smoke tags that don't match `v[0-9]*` pattern — the workflow validates this and will fail. If you need a non-version-format smoke tag, change the validation pattern in deploy.yml first.
- **Don't** dispatch `workflow_dispatch` against a tag where `deploy.yml` doesn't exist (e.g., any tag from v1.2.0 or earlier). Use a freshly-pushed tag on main, or `--ref main` (which then fails the validation by design — refusing to deploy unbuilt code is a feature).

## What's next (post-Phase 2c)

The auto-deploy story is fully closed. Remaining roadmap items from the original plan:

- **Phase 3 — theme-coupled file moves.** Five files in `inc/` that are presentation-coupled and need per-file judgment: `og-image.php`, `reading-time.php`, `notes-and-provenance.php`, `page-notes-template.php`, `page-notes-render.php`. Brainstorm per-file. Probably some stay in theme (rendering), some move to plugin (analytics-adjacent like reading-time tracking). Lower priority — these don't affect the deploy or runtime story.
- **Deferred hygiene:** inline-styles → external CSS refactor (124 instances), full i18n coverage. Both stay rejected.

No outstanding deploy infrastructure work. The maintainer's release ritual is now uniform across both packages.
