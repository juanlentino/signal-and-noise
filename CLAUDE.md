# Signal & Noise — WordPress FSE Theme

## Project
Custom WordPress Full Site Editing theme for juanlentino.com.
Repo: juanlentino/signal-and-noise. Hosted on Cloudways (syntharchy-wp), Cloudflare CDN.

## Stack
- WordPress FSE (block theme)
- PHP, HTML, CSS, theme.json
- Cloudways server, Cloudflare for CDN/DNS/headers

## Versioning
IMPORTANT: Only bump version for code/functional changes. Never for content-only template edits.

Patch cap is **7 per minor** for this project (overrides the global Apple-style 3-patch rule). So 6.2.0 through 6.2.7 are all valid before the next change must bump to 6.3.0. Minor cap remains 5 per major (6.0–6.5, then 7.0).

## Security
- CSP unsafe-inline/unsafe-eval are accepted risks (WordPress architectural constraint).
- Cloudflare Transform Rules pending for full CSP + HSTS on static assets.
- Aikido Security monitors this domain.

## Build & Deploy
Edit theme files → push to repo → deploy to Cloudways via git or SFTP.
No build step. WordPress handles rendering.
