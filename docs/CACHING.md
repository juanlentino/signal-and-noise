# Caching

How HTML caching works for `juanlentino.com` across the four caching
layers, why default Cloudflare doesn't cache HTML, and how to opt in.

> **Status (v8.2.0+):** Cloudflare purge and its admin UI now live in the **companion plugin** ([signal-and-noise-tools](https://github.com/juanlentino/signal-and-noise-tools)), not the theme. Where this doc says to configure Cloudflare under *Appearance → Signal & Noise* or references an `inc/cloudflare-purge.php` "in this theme," read that as the plugin — the theme only triggers the purge through a filter contract (`inc/template-maintenance.php`). The caching-layer architecture below is otherwise current.

## The four caching layers (in path order)

1. **Browser cache** — set via `Cache-Control` and `ETag` response
   headers. Per-visitor; doesn't help repeat-visitor traffic on its
   own but combines with #2 and #3 for instant back-button loads.
2. **Cloudflare edge cache** — global CDN. Caches static assets by
   default; **does not cache HTML by default**. We opt-in via a
   Cache Rule (below).
3. **Cloudways Varnish / Breeze page cache** — origin-side. Caches
   rendered HTML at the server before WordPress regenerates it.
   Already enabled by default on the host.
4. **WordPress object cache** — in-memory cache of database queries
   and computed values. Keeps repeat queries fast.

This doc focuses on layer #2 — Cloudflare edge HTML caching — which
is the highest-impact for global TTFB and origin load reduction. The
other layers are already in place.

## Why Cloudflare doesn't cache HTML by default

Cloudflare's "Standard" cache level only caches static file
extensions (`.css`, `.js`, `.jpg`, `.png`, `.pdf`, fonts, etc.). HTML
is excluded out-of-the-box because most sites have dynamic,
personalized HTML and a stale HTML cache is much more user-visible
than a stale image.

For a content site like this — where most visitors see the same
homepage, the same notes index, the same long-form essays — caching
HTML at the edge is a big win:

- **Origin load**: 90%+ of visits don't reach Cloudways at all
- **Global TTFB**: cached HTML serves from the nearest CF datacenter
  (~30ms typical) instead of round-trip to Cloudways (~100-300ms)
- **Resilience**: if origin is briefly down (PHP-FPM restart, MySQL
  blip), CF can serve stale HTML to keep the site responsive
- **Cost**: free with Cloudflare's free plan via Cache Rules

## Two paths to enable

### Path A: Cloudflare APO (paid, easiest)

Cloudflare APO ("Automatic Platform Optimization") is a $5/month
add-on that handles HTML caching, cookie-based bypass for logged-in
users, and auto-purging on WordPress post saves via the official
Cloudflare WordPress plugin.

**Setup** (15 min):

1. Cloudflare dashboard → Speed → Optimization → "APO" → Subscribe ($5/mo).
2. WordPress: install the official **Cloudflare** plugin.
3. Plugin settings → Authenticate with API token → Select the zone.
4. Toggle "Automatic Platform Optimization" on.

After this you can ignore the rest of this doc — APO handles
everything in this stack.

### Path B: Cache Rule + this theme's purge module (free)

The free Cloudflare plan supports **Cache Rules** that can opt
specific URL patterns into HTML caching. We pair that with the
`inc/cloudflare-purge.php` module shipped in this theme to purge
edges when content changes.

**Setup time:** ~15 min, one-time.

#### Step 1 — Generate a Cloudflare API token

The token's only purpose is to authorize cache purges from this
WordPress install.

1. Go to: <https://dash.cloudflare.com/profile/api-tokens>
2. Click **Create Token**
3. Choose **Custom token** → **Get started**
4. Configure:
   - **Token name**: `juanlentino.com — cache purge`
   - **Permissions**: `Zone` → `Cache Purge` → `Purge`
   - **Zone Resources**: `Include` → `Specific zone` → `juanlentino.com`
   - (Optional) **Client IP Address Filtering**: leave empty
   - (Optional) **TTL**: leave empty (token doesn't expire)
5. Click **Continue to summary**, then **Create Token**.
6. **Copy the token immediately** — Cloudflare won't show it again.

#### Step 2 — Find your zone ID

1. Cloudflare dashboard → click `juanlentino.com`
2. Scroll down on the **Overview** tab
3. Right sidebar → **API** section → **Zone ID** (32-char hex string)
4. Copy.

#### Step 3 — Save credentials in the theme

**Either** add to `wp-config.php` (more secure — token isn't in DB):

```php
define( 'SN_CLOUDFLARE_API_TOKEN', 'paste-your-token-here' );
define( 'SN_CLOUDFLARE_ZONE_ID',   'paste-your-zone-id-here' );
```

**Or** save via WP admin:

1. Appearance → Signal & Noise → Dashboard tab
2. Scroll to the **Cloudflare** section
3. Paste **API Token** and **Zone ID**
4. Click **Save Cloudflare Settings**

The status badge should change to "✓ Configured — auto-purge active".

#### Step 4 — Create the Cloudflare Cache Rule

This is what actually turns on HTML caching at the edge.

1. Cloudflare dashboard → click `juanlentino.com`
2. **Caching** → **Cache Rules** → **Create rule**
3. Configure:

   **Rule name**: `Cache HTML — non-logged-in users`

   **When incoming requests match...** (use the expression editor):
   ```
   (http.host eq "juanlentino.com")
   and not (http.cookie contains "wordpress_logged_in_")
   and not (http.cookie contains "wp-postpass_")
   and not (http.cookie contains "comment_author_")
   and not (http.request.uri.path contains "/wp-admin/")
   and not (http.request.uri.path contains "/wp-login.php")
   and not (http.request.uri.path contains "/wp-cron.php")
   and not (http.request.uri.path contains "/wp-json/")
   and not (http.request.uri.path contains "/feed/")
   and not (starts_with(http.request.uri.path, "/?"))
   ```

   Note: the `/feed/` line is stale too. Measured 2026-10-03, `/feed/` and
   `/notes/feed/` answer `cf-cache-status: REVALIDATED`, so the live rule
   lets feeds through. A rule that still carries the line must drop it, or
   the listed feeds (below) never reach the edge cache.

   Note: the `/wp-json/` line does not describe what the live edge does.
   Measured 2026-09-27, public `/wp-json/` GETs are edge-cached (HIT) and
   origin cache headers decide; see "What still hits origin" below.

   **Then...**
   - **Cache eligibility**: `Eligible for cache`
   - **Edge TTL**:
     - Use cache-control header if present: ON
     - Otherwise use: Cloudflare's default TTL

   Observed 2026-10-03: the live rule ("JL Cache") reads "Use cache-control
   header if present, cache with Cloudflare's default TTL if not". Earlier
   versions of this doc said "Override origin, 1 day"; that is not what is
   deployed. Cloudflare's documented default for a 200 with no cache header is
   120 minutes
   (developers.cloudflare.com/cache/how-to/configure-cache-status-code/).
   - **Browser TTL**:
     - Override origin: `Respect existing headers` (or `5 minutes` for fresher local cache)

4. **Deploy**.

Once deployed, anonymous HTML responses are cached at the edge for
2 hours, with auto-purge handled by the theme module on every post
save and theme update.

#### Step 5 — Verify

```bash
# First request: should populate cache (cf-cache-status: MISS or DYNAMIC briefly)
curl -sI https://juanlentino.com/notes/ | grep -i cf-cache-status

# Second request: should serve from edge (cf-cache-status: HIT)
curl -sI https://juanlentino.com/notes/ | grep -i cf-cache-status

# Verify cookie bypass (admin sessions)
curl -sI -H "Cookie: wordpress_logged_in_xxx=fake" https://juanlentino.com/notes/ | grep -i cf-cache-status
# Expected: BYPASS or DYNAMIC (rule should exclude the request)
```

Expected sequence after a few warm-up requests:

```
cf-cache-status: HIT
```

If you keep seeing `MISS` or `DYNAMIC`, check:

- Did the Cache Rule deploy? (status should be `Active`)
- Are origin headers sending `Cache-Control: private` or
  `no-cache`? Some WP plugins do this. The rule's "Override origin"
  TTL bypasses this.
- Is the route in the rule's URL exclusions (`/wp-admin/` etc.)?

## What the origin sends (two headers)

`inc/cache-headers.php` (lifetimes, feed list), `inc/cache-headers-rules.php`
(who gets them) and `inc/cache-headers-hooks.php` (sending) put two headers on
anonymous GET/HEAD responses, and nothing on anything else (a session,
post-password or commenter cookie, a POST, a 404, search results, a preview, a
password form, or a response that already set its own `Cache-Control`):

| Response | `Cache-Control` | `Cloudflare-CDN-Cache-Control` |
|---|---|---|
| Public HTML pages | `public, max-age=0` | `max-age=7200, stale-while-revalidate=86400, stale-if-error=604800` |
| `/llms.txt`, `/llms-full.txt`, `/.well-known/agents.json`, `/opensearch.xml`, the listed feeds | `public, max-age=0` | `max-age=300, stale-while-revalidate=3600, stale-if-error=86400` |

`Cache-Control` is what browsers and Varnish read: revalidate every time,
store nothing. It never carries `s-maxage`: Varnish would start holding HTML
that no post save purges, and Cloudflare documents `s-maxage` as switching
stale serving off (it implies `proxy-revalidate`).
`Cloudflare-CDN-Cache-Control` is read by Cloudflare alone, in place of
`Cache-Control`, and is not passed downstream. The edge keeps the copy (two
hours for HTML, which is what Cloudflare's default TTL already did), refreshes
it in the background, and serves it when the origin answers 5xx. The seconds
are constants, filterable through `sn_edge_cache_lifetimes`.

Because Cloudflare reads the edge header INSTEAD of `Cache-Control`, a
`nocache_headers()` call later in a template would be ignored at the edge. A
`header_register_callback` recheck removes the edge header whenever the
`Cache-Control` is no longer the one this module wrote, or the status is no
longer 200.

**One cache tag.** Every response that gets the edge lifetime also carries
`Cache-Tag: sn-render` (15.2.0). The plugin purges that tag on a save, one
call, and Cloudflare strips the header before the visitor sees it. This only
works with Breeze's "Cache Full Page HTML" off: a Breeze file hit skips
WordPress and so carries neither the lifetime nor the tag.

**The listed feeds** are a closed set (`sn_edge_cache_feed_paths()`): the posts
feed as rss2, rss, rdf, atom and json under `/feed/` and `/notes/feed/`, plus
`/?feed=json`. The plugin purges exactly that list on a save. Any other feed
(comments, a tag, another query form) keeps Breeze's `no-cache`, because it
could not be purged by name.

**Two routes exit before the hook.** `/notes` (and its tag views) renders at
`template_redirect` priority 0 and exits, so its handler calls the policy
itself. `/index` and `/notes/tags` are postless: WordPress has already sent
its 404 no-cache headers by then, the policy sees a `Cache-Control` it did not
write, and both stay uncached at the edge as before.

What the headers depend on, none of it in this repo:

- **The Cache Rule's Edge TTL must not override the origin.** Cloudflare's
  docs: the Edge Cache TTL rule "overrides directives in
  `Cloudflare-CDN-Cache-Control/CDN-Cache-Control` which manage how long an
  asset is cached on the edge". The rule should use the origin's header when
  present (Step 4). Whether that mode counts `Cloudflare-CDN-Cache-Control` as
  "present" is not stated in the docs: check `cf-cache-status` and `age` after
  deploy.
- **Always Online decides whether the stale directives do anything.** With
  it on, Cloudflare ignores `stale-while-revalidate` and `stale-if-error`;
  the edge lifetime still applies. It is on in the live zone and stays on by
  the owner's choice (2026-10-03), so stale serving is dormant until that
  changes. The plugin's edge posture read shows the setting.
- **A Breeze page-cache hit carries neither header.** Breeze answers a hit
  before WordPress loads, and its `breeze_custom_headers_allow` list cannot
  replay them (it snapshots header values once, from a HEAD of the home page
  through the edge, where Cloudflare has already dropped the edge header).
  Stale serving applies to origin misses only; a purge empties Breeze too, so
  the copy the edge stores after one is a fresh render.

## How auto-purge works

Once configured, the theme's `inc/cloudflare-purge.php` module
automatically purges Cloudflare's cache when content changes:

| Trigger | What gets purged |
|---|---|
| Post (any status) saved as `publish` | The post's URL + homepage + `/notes/` + `/provenance/` + `/notes/feed/` + parent permalink (if any) |
| Theme update via WP self-updater | Entire zone (theme files can change global elements) |
| Manual button on Signal & Noise dashboard | Entire zone |

The purge calls go through `wp_remote_post` with `blocking => false`,
so they never delay the admin save. If the API call fails (network
issue, expired token, wrong zone ID) the post-save itself still
succeeds — the purge is best-effort.

## What still hits origin (uncached)

Even with the Cache Rule active, these always bypass edge cache and
hit origin PHP:

- All `/wp-admin/` URLs
- `/wp-login.php`
- `/wp-cron.php`
- `/wp-json/` is NOT on this list. Measured 2026-09-27: public
  `/wp-json/` GET responses are cached at the edge (`cf-cache-status: HIT`,
  `age` of 35 s and up), whatever the expression above says. The edge
  honours origin cache headers, so a REST endpoint that must never be cached
  opts out with its own headers. Example: the feed-open pixel
  (`/wp-json/signal-noise/v1/feed-open`) sends `Cache-Control: no-store` and
  reads `cf-cache-status: BYPASS`. A public REST response without such
  headers should be assumed cacheable at the edge.
- The listed posts feeds are NOT on this list either: the live edge stores
  feeds (measured 2026-10-03, `cf-cache-status: REVALIDATED` under Breeze's
  `no-cache`), and the listed ones now get five minutes at the edge. A post
  save purges them. Comment, tag and other feeds keep `no-cache`.
- Any request with a `wordpress_logged_in_*`, `wp-postpass_*`, or
  `comment_author_*` cookie (you, while logged in)

This means: when you're logged in as admin and visiting the site,
**you'll always see the live origin version**. Logged-out visitors
(everyone else) see the edge-cached version. After a content edit,
they'll see the new version within seconds (the purge fires on save).

## Troubleshooting

**"I edited a note but visitors still see the old version."**

1. Check that `inc/cloudflare-purge.php` ran on the save: visit
   Appearance → Signal & Noise → Dashboard, look at "Last purge"
   timestamp. Should match the time you saved.
2. If "Last purge" didn't update: the module isn't configured. Set
   token + zone in either wp-config or the admin form.
3. If "Last purge" is recent but visitors still see old content:
   click the manual **Purge Cloudflare** button on the dashboard.
4. Verify the API token still works: regenerate it on Cloudflare
   if it was revoked or expired.

**"All visitors are seeing 502 / 503 errors."**

This is an origin issue, not a caching issue. Check:

- Cloudways monitoring → CPU / RAM (the 2026-05-07 incident
  pattern — see `CHANGELOG.md`)
- WordPress error logs
- The smoke-test workflow on GitHub — should be red

If origin is down, edge cache will serve stale-but-200 HTML to
anonymous visitors for as long as TTL permits. That buys time to
fix origin without total user-visible downtime.

**"I'm logged in and seeing weird styling."**

You're seeing the live origin version while logged-out visitors
see the cached version. If you recently edited CSS without bumping
asset versions, edge HTML may reference old asset URLs. The mtime-
based asset versioning (`sn_asset_ver()` since v6.5.4) avoids this
in normal operation, but if you've manually edited theme files via
SFTP without changing mtimes, you might see drift. Click the manual
purge button to resync.
