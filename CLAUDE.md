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

## Security
- CSP unsafe-inline/unsafe-eval are accepted risks (WordPress architectural constraint).
- Cloudflare Transform Rules pending for full CSP + HSTS on static assets.
- Aikido Security monitors this domain.

## Build & Deploy
Edit theme files → push to repo → deploy to Cloudways via git or SFTP.
No build step. WordPress handles rendering.
