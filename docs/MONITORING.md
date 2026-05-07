# Monitoring

Layered defenses against the kind of incident documented in
`CHANGELOG.md` ("Real `/notes` hang root cause", 2026-05-07).

## Tier 1: Architectural (in code)

Already shipped. The OG card generator no longer runs synchronously
in the request path; cache miss returns the site default OG image
instead of attempting on-demand generation. See the function-header
contract on `sn_og_image_url_for_post()` in `inc/og-image.php`.

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

## Tier 3: Uptime Kuma probes

Uptime Kuma is already running on Railway as part of the
infrastructure stack. Add the following monitors via the UK web UI
to get continuous external monitoring with notification routing
that doesn't depend on opening GitHub.

### Monitors to add

For each route below, in Uptime Kuma → New Monitor:

- **Monitor Type**: HTTP(s) - Keyword
- **Method**: GET
- **Heartbeat Interval**: per the table
- **Heartbeat Retries**: 2 (avoid flapping on transient blips)
- **Request Timeout**: 15 seconds
- **Max Redirects**: 5
- **HTTP Status Codes**: 200-299
- **Notification**: assign your existing channel(s)

| Name | URL | Keyword | Interval |
|---|---|---|---|
| juanlentino.com — Home | `https://juanlentino.com/` | `Juan Lentino` | 60 s |
| juanlentino.com — /notes/ | `https://juanlentino.com/notes/` | `Notes` | 120 s |
| juanlentino.com — /provenance/ | `https://juanlentino.com/provenance/` | `On Provenance` | 120 s |
| juanlentino.com — /provenance/over-detection/ | `https://juanlentino.com/provenance/over-detection/` | `verification problem` | 300 s |
| juanlentino.com — /provenance/as-substrate/ | `https://juanlentino.com/provenance/as-substrate/` | `identification problem` | 300 s |
| juanlentino.com — /notes/feed/ | `https://juanlentino.com/notes/feed/` | `<rss` | 300 s |

The shorter intervals on the homepage and indexes reflect that
those pages get the most traffic; long-form essays change rarely
and probe-frequency can be lower.

### Notification routing

Uptime Kuma collects data even without a notification channel, but
won't alert you. At least one channel is recommended:

- **Discord webhook** is the simplest if you have a personal
  Discord — UK has built-in support; just paste the webhook URL.
- **Email (SMTP)** works but you need to configure UK's SMTP
  settings.
- **Telegram** if you prefer mobile push without Discord.

For each monitor, in the Notifications section, check the channel
you want it to alert on.

### Status page (optional)

Uptime Kuma can publish a public status page summarizing all
monitors. If you want one (e.g., `status.juanlentino.com`):

1. UK → Status Pages → New Status Page
2. Name: `juanlentino.com status`
3. Slug: `jl` (or whatever)
4. Add the monitors above to the page
5. Optionally configure a custom domain

Useful if anyone other than you needs to know whether the site is
up. Optional for a solo project.

## Tier 4: Future (not yet implemented)

Flagged for future iterations:

- **Production error logging.** Forward Cloudways `error.log` to a
  searchable destination (Loggly, BetterStack, or even just a
  shared Dropbox file via cron). Would have shown the OG truncation
  loop firing repeatedly before user-visible impact.
- **Local PHP runtime.** Install `wp-env` or a Docker-based local
  WordPress to exercise PHP changes before pushing. Closes the
  "shipped a UTF-8 byte-vs-char bug without a runtime check" gap.
- **Pre-commit hook (local).** Once local PHP is available, a
  `.git/hooks/pre-commit` running `php -l` on staged `.php` files
  catches parse errors before push without waiting for CI. Belt
  and suspenders alongside the workflow lint.

## Incident response

When a smoke test fails or UK alerts:

1. **Check what's red.** The workflow run or UK monitor names the
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
