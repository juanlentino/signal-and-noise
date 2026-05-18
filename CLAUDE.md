# Signal & Noise — WordPress FSE Theme

## Project
Custom WordPress Full Site Editing theme for juanlentino.com.
Repo: juanlentino/signal-and-noise. Hosted on Cloudways (syntharchy-wp), Cloudflare CDN.

**Companion plugin (since v8.2.0):** Operational tooling lives in [juanlentino/signal-and-noise-tools](https://github.com/juanlentino/signal-and-noise-tools). Phases 1, 4 (RSS tracker), 2a (auto-deploy) shipped; 2b / 2c / 3 queued.

**Start of session:** read the most recent handoff in [docs/superpowers/handoffs/](docs/superpowers/handoffs/) for current state + open questions. Contract surface + phase plan in [docs/WORDPRESS-REFERENCE.md](docs/WORDPRESS-REFERENCE.md) §10.0.

## Stack
- WordPress FSE (block theme)
- PHP, HTML, CSS, theme.json
- Cloudways server, Cloudflare for CDN/DNS/headers

## Versioning

Full workflow + rationale: [docs/VERSIONING.md](docs/VERSIONING.md). Short version below.

**Caps (override global):** Patch cap is **7 per minor**, minor cap is **5 per major**. `7.1.0`–`7.1.7` valid → next bump rolls to `7.2.0`. `7.0`–`7.5` valid → next bump rolls to `8.0.0`. When the cap fires, document the rollover in the CHANGELOG entry.

**What bumps:** code, CSS, migrations, structural template changes. **What doesn't:** `docs/`, `CLAUDE.md`, content-only copy edits, CHANGELOG-only commits.

**Workflow per release:** edit code → bump `Version:` in [style.css](style.css) (theme) or [signal-and-noise-tools.php](https://github.com/juanlentino/signal-and-noise-tools/blob/main/signal-and-noise-tools.php) (plugin) → CHANGELOG entry at top → commit `vX.Y.Z: summary` → `git push origin HEAD:main` → annotated tag `vX.Y.Z` → `git push origin vX.Y.Z` → **both theme and plugin auto-deploy + auto-purge CF edge cache in ~30s** (theme via Cloudways API on Phase 2a; plugin via SSH from GHA as app-scoped `sn-plugin` user on Phase 2c).

**Commit + tag format:**
```bash
git commit -m "vX.Y.Z: summary"
git tag -a vX.Y.Z -m "vX.Y.Z — summary"
git push origin vX.Y.Z
```

## Security
- CSP unsafe-inline/unsafe-eval are accepted risks (WordPress architectural constraint).
- Cloudflare Transform Rules pending for full CSP + HSTS on static assets.
- Aikido Security monitors this domain.

## Build & Deploy

**Theme (since v8.5.1):** does NOT auto-deploy on tag push. The workflow is `on: workflow_dispatch:` only — per the comment at [.github/workflows/deploy.yml](.github/workflows/deploy.yml): *"v8.5.1+: tag pushes no longer auto-deploy. Theme updates land via the WP admin Updates page."* Two install paths, same as the plugin:

1. **Canonical (user-driven):** wp-admin → Dashboard → Updates → "Update theme" for Signal & Noise. Powered by [inc/wp-update-integration.php](inc/wp-update-integration.php) registering the theme with WP's native update system + renaming the unpacked GitHub archive to the correct stylesheet slug. `.git` preserved via the pre/post-install filter pair (v8.5.2+).
2. **Emergency manual deploy:** `gh workflow run deploy.yml --repo juanlentino/signal-and-noise --ref vX.Y.Z`.

**Plugin (since v1.10.1):** does NOT auto-deploy on tag push. Two ways to land a plugin release:

1. **Canonical (user-driven):** wp-admin → Dashboard → Updates → "Update plugin" for Signal & Noise Tools. Powered by [inc/wp-update-integration.php](https://github.com/juanlentino/signal-and-noise-tools/blob/main/inc/wp-update-integration.php) registering the plugin with WP's native update system. `.git` is preserved through this install path via the pre/post-install filter pair from v1.11.2.

2. **Emergency manual deploy:** `gh workflow run deploy.yml --repo juanlentino/signal-and-noise-tools --ref vX.Y.Z`. Runs the SSH-into-Cloudways path as the app-scoped `sn-plugin` user (NOT master). **Will fail if the working tree on the server is dirty from a prior WP UI install** — symptom: `git checkout` "local changes would be overwritten" error on `CHANGELOG.md` / `inc/seo*.php` / `signal-and-noise-tools.php`. If that happens, install via path (1) instead, or SSH in and reset the working tree first (`git reset --hard && git clean -fd`).

The earlier auto-on-tag-push behavior was removed in v1.10.1 so releases land at user discretion. **Security:** if the GHA `SSH_PRIVATE_KEY` ever leaks, blast radius is bounded to this WP app's filesystem + DB, not the whole Cloudways server.

**Worktree push:** `git push origin HEAD:main` (worktree branch name differs from `main`).
No build step. WordPress handles rendering.

## WordPress reference

**Read [docs/WORDPRESS-REFERENCE.md](docs/WORDPRESS-REFERENCE.md) before touching anything WordPress-internal** — block render callbacks, FSE template parts, WP-Cron, transients/options, dbDelta, MU plugins, escaping, filter timing, the self-updater + self-heal architecture. It's the project-curated cheatsheet of every upstream gotcha we've already paid for. Maintain the "Upstream WordPress core gotchas" running list at the bottom whenever you hit a new one.

For broader WordPress knowledge:
- **Block markup, attributes, validation rules:** invoke the `gutenberg-block-authoring` skill (auto-loads on any block-editing task).
- **FSE architecture, theme.json, patterns:** invoke the `wordpress-block-theming` skill (bundled in `cowork-create-wp-site` plugin; available after Claude Code restart).
- **WordPress core source:** when integrating with a core primitive, read the actual source file first. Don't reason from memory. `raw.githubusercontent.com/WordPress/WordPress/master/<path>` is the fetch pattern.
