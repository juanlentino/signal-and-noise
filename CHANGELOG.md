# Changelog

All notable changes to the Signal & Noise theme are documented here.

This file holds two things only: **`## [Unreleased]`**, the working log that
accumulates across pull requests, and the **current release**. Everything older
lives in [docs/changelog/](docs/changelog/).

A pull request does not bump `Version` and does not tag — it closes an issue and
adds a bullet below. A release is a separate, deliberate act:
`tools/cut-release.sh`, which stamps `style.css` **and** `readme.txt` together.

## [Unreleased]

## [15.1.0] - 2026-10-03 — the edge holds a copy


- **Public pages tell the edge to serve the copy it holds when the origin fails.** HTML left with no `Cache-Control` at all, so Cloudflare kept it for its default two hours and had nothing that allowed a stale answer: when the origin returned 503, real pages returned 503 with no stale copy. Anonymous GET/HEAD pages now send two headers. `Cache-Control: public, max-age=0` is for browsers and Varnish: revalidate, store nothing. It carries no `s-maxage`, which would have Varnish holding HTML no save purges and which Cloudflare documents as switching stale serving off. `Cloudflare-CDN-Cache-Control: max-age=7200, stale-while-revalidate=86400, stale-if-error=604800` is read by Cloudflare alone and not passed on: the edge keeps the two hours it already kept (the live Cache Rule uses the origin's header when present and Cloudflare's default TTL when not; the documented default for a 200 is 120 minutes), refreshes in the background, and answers from its copy for up to seven days of origin 5xx. Nothing is sent with a login, post-password or commenter cookie, on a 404, search results, a preview, a password form, or over a `Cache-Control` another caller already set; a `nocache_headers()` call or an error status after the fact takes the edge header back. Needs the Cache Rule's Edge TTL to leave the origin's value alone. Always Online is on in the live zone and stays on by the owner's choice, and while it is on Cloudflare ignores both stale directives, so stale serving is dormant and the edge lifetime still applies; a Breeze page-cache hit leaves without the headers, so stale serving applies to origin misses only (`docs/CACHING.md`). Pinned in `tests/cache-headers.php`.
- **The machine files and the posts feeds are held at the edge for five minutes.** `/llms.txt`, `/llms-full.txt`, `/.well-known/agents.json` and `/opensearch.xml` left with the nocache headers WordPress commits for a postless path, so every read bypassed the cache and waited on PHP (about 0.4 s); the feeds left with Breeze's `no-cache`, and the feed is fetched about 2,300 times a month. An anonymous read now gets `Cache-Control: public, max-age=0` with `Cloudflare-CDN-Cache-Control: max-age=300, stale-while-revalidate=3600, stale-if-error=86400`. The feeds that get it are a closed list (`sn_edge_cache_feed_paths()`: rss2, rss, rdf, atom and json under `/feed/` and `/notes/feed/`, plus `/?feed=json`), so the companion plugin PR can purge exactly those on a save; comment, tag and other feeds keep `no-cache`.
- **`/notes` carries the edge headers too.** Its handler renders at `template_redirect` priority 0 and exits, before the priority-100 hook; it now runs the same policy before the include. `/index` and `/notes/tags` are postless and keep WordPress's 404 no-cache headers, so they stay uncached as before (`docs/CACHING.md`, which also drops the stale `/feed/` exclusion from the documented Cache Rule: the live rule lets feeds through). Codex on #474.


