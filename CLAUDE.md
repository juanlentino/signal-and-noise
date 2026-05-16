# Signal & Noise — WordPress FSE Theme

## Project
Custom WordPress Full Site Editing theme for juanlentino.com.
Repo: juanlentino/signal-and-noise. Hosted on Cloudways (syntharchy-wp), Cloudflare CDN.

**Companion plugin (since v8.2.0):** Operational tooling (REST surface, Plausible integration, admin UI, security headers, Cloudflare purge) lives in a separate companion plugin: [juanlentino/signal-and-noise-tools](https://github.com/juanlentino/signal-and-noise-tools). The split is partial — see [docs/WORDPRESS-REFERENCE.md](docs/WORDPRESS-REFERENCE.md) §10.0 for the contract surface and the migration phase plan.

## Stack
- WordPress FSE (block theme)
- PHP, HTML, CSS, theme.json
- Cloudways server, Cloudflare for CDN/DNS/headers

## Versioning

Full workflow + rationale: [docs/VERSIONING.md](docs/VERSIONING.md). Short version below.

**Caps (override global):** Patch cap is **7 per minor**, minor cap is **5 per major**. `7.1.0`–`7.1.7` valid → next bump rolls to `7.2.0`. `7.0`–`7.5` valid → next bump rolls to `8.0.0`. When the cap fires, document the rollover in the CHANGELOG entry.

**What bumps:** code, CSS, migrations, structural template changes. **What doesn't:** `docs/`, `CLAUDE.md`, content-only copy edits, CHANGELOG-only commits.

**Workflow per release:** edit code → bump `Version:` in [style.css](style.css) → CHANGELOG entry at top → commit `vX.Y.Z: summary` → push → annotated tag `vX.Y.Z` at session end → smoke test runs on push → Update in WP admin.

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
Edit theme files → push to repo → deploy to Cloudways via git or SFTP.
No build step. WordPress handles rendering.

## WordPress reference

**Read [docs/WORDPRESS-REFERENCE.md](docs/WORDPRESS-REFERENCE.md) before touching anything WordPress-internal** — block render callbacks, FSE template parts, WP-Cron, transients/options, dbDelta, MU plugins, escaping, filter timing, the self-updater + self-heal architecture. It's the project-curated cheatsheet of every upstream gotcha we've already paid for. Maintain the "Upstream WordPress core gotchas" running list at the bottom whenever you hit a new one.

For broader WordPress knowledge:
- **Block markup, attributes, validation rules:** invoke the `gutenberg-block-authoring` skill (auto-loads on any block-editing task).
- **FSE architecture, theme.json, patterns:** invoke the `wordpress-block-theming` skill (bundled in `cowork-create-wp-site` plugin; available after Claude Code restart).
- **WordPress core source:** when integrating with a core primitive, read the actual source file first. Don't reason from memory. `raw.githubusercontent.com/WordPress/WordPress/master/<path>` is the fetch pattern.
