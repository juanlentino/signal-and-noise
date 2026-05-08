# Signal & Noise — WordPress FSE Theme

## Project
Custom WordPress Full Site Editing theme for juanlentino.com.
Repo: juanlentino/signal-and-noise. Hosted on Cloudways (syntharchy-wp), Cloudflare CDN.

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
