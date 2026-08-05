# Monitoring

Layered defenses against the kind of incident documented in
`CHANGELOG.md` ("Real `/notes` hang root cause", 2026-05-07).

## Tier 1: Architectural (in code)

Already shipped. The OG card generator no longer runs synchronously
in the request path; cache miss returns the site default OG image
instead of attempting on-demand generation. See the function-header
contract on `sn_og_image_url_for_post()` in the companion plugin's
`inc/og-card-generator.php` (moved out of the theme's `inc/og-image.php`
in v8.4.0 / Tools v1.3.0 — see WORDPRESS-REFERENCE §10.0).

**Rule baked into the codebase:** decorative work never blocks
essential rendering. Anything in the request path that calls GD,
network, large file I/O, or contains an unbounded loop should be
audited against this rule.

## Tier 2: Smoke tests (CI)

The `.github/workflows/smoke-test.yml` workflow runs on every push
to `main` and on a 15-minute schedule. It does two things:

1. **PHP syntax lint** (`php -l`) on every `.php` file in the
   repository. Catches parse errors before they hit production.
2. **Smoke test against the live site**: for each of six key
   routes, fetch the URL and assert (a) HTTP 200, (b) response
   time under 5 seconds, (c) body over 1 KB, (d) expected content
   marker present in the body. Marker checks defeat false-positive
   200s from cached error pages or empty shells.

Routes covered:

| Route | Marker |
|---|---|
| `https://juanlentino.com/` | `Juan Lentino` |
| `https://juanlentino.com/notes/` | `Notes` |
| `https://juanlentino.com/provenance/` | `On Provenance` |
| `https://juanlentino.com/provenance/over-detection/` | `verification problem` |
| `https://juanlentino.com/provenance/as-substrate/` | `identification problem` |
| `https://juanlentino.com/notes/feed/` | `<rss` |

**Detection latency**:
- Newly deployed change that breaks something: up to ~15 min after
  the user clicks Update in WP admin (next scheduled run).
- Push that includes a parse error: blocks immediately at lint job
  before any smoke check runs.
- Server-side issue with no recent push (e.g., MySQL down,
  Cloudflare misconfig): up to 15 min.

**Failure surface**:
- Red ❌ on the commit page.
- GitHub email to the committer (default behavior on workflow
  failures for repository owners).
- The "Actions" tab shows the failing run with annotated errors
  (each `::error::` line shows up as a problem in the UI).

To trigger a manual run: GitHub → Actions → Smoke Test → "Run
workflow" → choose `main` → run.

## Tier 3: Better Stack monitors

External monitoring lives on Better Stack Uptime — migrated from the
self-hosted Uptime Kuma on Railway in July 2026 (arc 3). Two monitor
classes: HTTP keyword probes hitting the site from outside, and a
heartbeat the site pushes out (the "is WP-Cron alive" signal an HTTP
probe can't see).

Better Stack probes send the User-Agent `Better Stack Better Uptime
Bot Mozilla/5.0 …`. Both bot classifiers know it (plugin
`inc/rss-feed-tracker.php` via the bare `bot` token, pinned in tests;
analytics worker `BOT_UA` since v1.9.2), so probes never pollute
subscriber or visitor counts. Probe IPs rotate without notice —
match on User-Agent, never allowlist by IP.

### HTTP keyword monitors

For each route below, Better Stack → Uptime → Monitors → Create:

- **Alert us when**: URL becomes unavailable
- **Required keyword in response**: per the table — a keyword match
  defeats false-positive 200s from cached error pages or empty shells
- **Check frequency**: per the table (clamps to the plan minimum)
- **Request timeout**: 15 seconds
- **Confirmation period**: 30–60 s (the anti-flapping damper; the
  Kuma "Retries: 2" equivalent)

| Name | URL | Keyword | Frequency |
|---|---|---|---|
| Home | `https://juanlentino.com/` | `Juan Lentino` | 1 min (or plan min) |
| /notes/ | `https://juanlentino.com/notes/` | `Notes` | 3 min |
| /provenance/ | `https://juanlentino.com/provenance/` | `On Provenance` | 3 min |
| /provenance/over-detection/ | `https://juanlentino.com/provenance/over-detection/` | `verification problem` | 5 min |
| /provenance/as-substrate/ | `https://juanlentino.com/provenance/as-substrate/` | `identification problem` | 5 min |
| /notes/feed/ | `https://juanlentino.com/notes/feed/` | `<rss` | 5 min |
| login-guard freshness | `https://juanlentino.com/_sn/login-guard/status` | `"stale": false` | 15 min |

The shorter frequencies on the homepage and indexes reflect that those
pages get the most traffic; long-form essays change rarely and probe
frequency can be lower. The login-guard row watches the worker's
derived freshness field: if the FireHOL denylist stops refreshing
(>48 h without a successful compile) the JSON flips to
`"stale": true`, the keyword match fails, and the monitor alerts.

### WP-Cron heartbeat (push)

The companion plugin GETs a heartbeat URL every 5 minutes from
WP-Cron (`inc/uptime-heartbeat.php` in signal-and-noise-tools).
Better Stack → Uptime → Heartbeats → Create:

- **Expect a heartbeat every**: 5 minutes (matches the cron cadence)
- **Grace period**: 5 minutes (one missed beat tolerated — WP-Cron
  is traffic-dependent, so a quiet stretch can late-fire a beat)

Paste the heartbeat URL into wp-admin → the plugin's Webhooks tab →
Uptime monitoring → Heartbeat URL, and tick Enabled. The plugin
appends `status=up` (Uptime Kuma legacy; Better Stack ignores it).
Silence flips the heartbeat to an incident: site down, PHP dead, or
WP-Cron stuck — the failure class Tier 2's outside-in probes share
with this tier, plus the cron-only deaths they can't see.

### In-admin status surfaces (plugin v8.2.0 → v8.4.0)

The Better Stack states render natively inside wp-admin, split by
altitude (owner-shaped across v8.2.0–v8.4.0):

- **Glance** (statuses only): an Uptime section at the bottom of the
  "S&N Health" dashboard widget, and a "Better Stack status" panel on
  Connections → Webhooks. Name + up/down pill, nothing else.
- **Review** (the monitor): Dashboard → Analytics gains a per-resource
  table — status, 30-day and 90-day availability, average response
  time over the last 24 h, checked-ago — plus a recent-incidents log
  (ongoing incidents flagged, cause + duration). Deliberately NOT a
  Better Stack console replica: charts, escalations, and monitor
  config stay in their console.

Powered by a read-scoped Uptime API token pasted next to the
heartbeat URL (or `SN_BETTERSTACK_API_TOKEN` in wp-config.php); data
flows through the readonly `signal-noise/uptime-status` ability
(detail=true for the monitor tier) with independently cached,
circuit-broken server-side tiers (90 s statuses, 1 h/6 h
availability, 15 min response times, 5 min incidents). No iframe, no
embed — and deliberately no public route: a status page served by
the site it reports on dies with the site.

### Notification routing

Better Stack alerts via e-mail and its mobile app push out of the
box — no SMTP or webhook setup required. Add Slack/Telegram/webhook
integrations per-monitor if wanted; escalation policies exist but
are overkill for a solo project.

### Status page (optional)

Better Stack can publish a public status page from the monitors
(optionally on a custom domain like `status.juanlentino.com`).
Useful if anyone other than you needs to know whether the site is
up. Optional for a solo project.

## Tier 4: Future (not yet implemented)

Flagged for future iterations:

- **Production error logging.** Forward Cloudways `error.log` to a
  searchable destination — Better Stack Logs is the natural fit now
  that Uptime lives there, but log ingestion is explicitly out of
  scope until the owner asks. Would have shown the OG truncation
  loop firing repeatedly before user-visible impact.
- **Local PHP runtime.** Install `wp-env` or a Docker-based local
  WordPress to exercise PHP changes before pushing. Closes the
  "shipped a UTF-8 byte-vs-char bug without a runtime check" gap.
- **Pre-commit hook (local).** Once local PHP is available, a
  `.git/hooks/pre-commit` running `php -l` on staged `.php` files
  catches parse errors before push without waiting for CI. Belt
  and suspenders alongside the workflow lint.

## Incident response

When a smoke test fails or Better Stack alerts:

1. **Check what's red.** The workflow run or Better Stack monitor names the
   broken route(s). All routes failing simultaneously usually
   means server-side (Cloudways down, Cloudflare issue, MySQL).
   Per-route failures usually mean a code or content issue scoped
   to that page or feature.
2. **Check the most recent commit.** `git log --oneline -5
   origin/main` shows what was last shipped. Anything touching the
   broken route's code path is the prime suspect.
3. **Check the Cloudways monitoring dashboard.** Sustained 100%
   CPU is the signature of a stuck PHP-FPM worker (the 2026-05-07
   pattern). RAM exhaustion is a different signature (memory
   leak).
4. **Restart PHP-FPM via Cloudways** if CPU is pinned. Stuck
   workers don't recover until restarted (or `max_execution_time`
   kills them, default 300 s).
5. **Roll back if needed.** If the most recent commit is the
   suspect: `git push origin <prev-sha>:main --force` from the
   worktree, then click Update in WP admin to pull the rolled-back
   theme. Avoid `--force` unless you're sure.
6. **Diagnose root cause** using the
   `superpowers:systematic-debugging` skill before shipping a fix.
   The CHANGELOG entry for 2026-05-07 documents how going through
   that systematically — gather evidence before proposing fixes —
   beat my first attempt that guessed at the wrong code path.
