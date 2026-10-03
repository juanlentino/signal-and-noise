# Changelog

All notable changes to the Signal & Noise theme are documented here.

This file holds two things only: **`## [Unreleased]`**, the working log that
accumulates across pull requests, and the **current release**. Everything older
lives in [docs/changelog/](docs/changelog/).

A pull request does not bump `Version` and does not tag — it closes an issue and
adds a bullet below. A release is a separate, deliberate act:
`tools/cut-release.sh`, which stamps `style.css` **and** `readme.txt` together.

## [Unreleased]

- **Public pages tell the edge to serve the copy it holds when the origin fails.** HTML left with no `Cache-Control` at all, so Cloudflare kept it for its default two hours and had nothing that allowed a stale answer: when the origin returned 503, real pages returned 503 with no stale copy. Anonymous GET/HEAD pages now send two headers. `Cache-Control: public, max-age=0` is for browsers and Varnish: revalidate, store nothing. It carries no `s-maxage`, which would have Varnish holding HTML no save purges and which Cloudflare documents as switching stale serving off. `Cloudflare-CDN-Cache-Control: max-age=7200, stale-while-revalidate=86400, stale-if-error=604800` is read by Cloudflare alone and not passed on: the edge keeps the two hours it already kept (the live Cache Rule uses the origin's header when present and Cloudflare's default TTL when not; the documented default for a 200 is 120 minutes), refreshes in the background, and answers from its copy for up to seven days of origin 5xx. Nothing is sent with a login, post-password or commenter cookie, on a 404, search results, a preview, a password form, or over a `Cache-Control` another caller already set; a `nocache_headers()` call or an error status after the fact takes the edge header back. Needs the Cache Rule's Edge TTL to leave the origin's value alone. Always Online is on in the live zone and stays on by the owner's choice, and while it is on Cloudflare ignores both stale directives, so stale serving is dormant and the edge lifetime still applies; a Breeze page-cache hit leaves without the headers, so stale serving applies to origin misses only (`docs/CACHING.md`). Pinned in `tests/cache-headers.php`.
- **The machine files and the posts feeds are held at the edge for five minutes.** `/llms.txt`, `/llms-full.txt`, `/.well-known/agents.json` and `/opensearch.xml` left with the nocache headers WordPress commits for a postless path, so every read bypassed the cache and waited on PHP (about 0.4 s); the feeds left with Breeze's `no-cache`, and the feed is fetched about 2,300 times a month. An anonymous read now gets `Cache-Control: public, max-age=0` with `Cloudflare-CDN-Cache-Control: max-age=300, stale-while-revalidate=3600, stale-if-error=86400`. The feeds that get it are a closed list (`sn_edge_cache_feed_paths()`: rss2, rss, rdf, atom and json under `/feed/` and `/notes/feed/`, plus `/?feed=json`), so the companion plugin PR can purge exactly those on a save; comment, tag and other feeds keep `no-cache`.

## [15.0.1] - 2026-10-03 — the cron lock survives a purge


- **An update inside WP-Cron keeps Core's cron lock.** 15.0.0's transient-group flush (and the manual purge's full flush) deleted `doing_cron`, the lock that stops two cron runs overlapping; automatic updates and the plugin's rollover run inside cron. The purge saves the lock and puts it back right after each flush. Codex on #470; pinned in `tests/template-maintenance.php`.
- **Cited by is asked once per note render.** Related notes (14.9.0) ran the same leading-wildcard `LIKE` scan the Cited by footer runs, on every note request; `sn_cited_by_query()` now answers once per request per post and limit. Codex on #468.
- **The next essay matches the pillar by its full path** (`get_page_uri()` against the descriptor's slug), so a page elsewhere sharing a pillar's last segment shows nothing. Codex on #468.


