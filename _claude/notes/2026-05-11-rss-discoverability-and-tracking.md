---
date: 2026-05-11
version: 8.0.0
branch: claude/interesting-banach-9f8554
session_type: feature
tags: [rss, plausible, analytics, mu-plugin, accessibility, admin-ui]
---

# RSS Discoverability + Subscriber Metrics + Settings Tab

Shipped three things in one release: site-wide RSS surfacing, a server-side subscriber tracker (no Jetpack / no FeedBlitz / no third-party tracker), and an admin tab under **Appearance → Signal & Noise → RSS** that exposes every operational setting.

## What landed

| Change | File | Notes |
| --- | --- | --- |
| RSS link in global footer (idiomatic Gutenberg) | [parts/footer.html](../../parts/footer.html) | `wp:social-link service:"feed"` with `label:"Subscribe via RSS"` |
| MU plugin (tracker + DB + widget + admin tab + form handler) | [mu-plugins/rss-plausible-tracker.php](../../mu-plugins/rss-plausible-tracker.php) | 482 lines, single self-contained file, hooks 5 actions |
| Bot detection regression test | [mu-plugins/tests/bot-detection.php](../../mu-plugins/tests/bot-detection.php) | 33 fixtures, runs with bare `php`, regression-locked against the Feedly bug |
| Deployment note | [mu-plugins/README.md](../../mu-plugins/README.md) | Manual copy to `wp-content/mu-plugins/` |
| Admin tab registration | [inc/admin-page.php](../../inc/admin-page.php) | `'rss'` added to valid tabs + label map + dispatch elseif |
| Version bump | [style.css](../../style.css) | 7.5.6 → 8.0.0 |
| Changelog | [CHANGELOG.md](../../CHANGELOG.md) | 8.0.0 entry at top |

## Process notes

This session got redone after the user called out that I'd skipped the superpowers skill protocol. First pass was a direct-execute that produced working code with a real bug in it. Second pass invoked `superpowers:brainstorming` retroactively and that single discipline surfaced three real issues that warranted fixing before commit:

1. **Bot regex bug** (Feedly silently filtered — would have wrecked the metric)
2. **Footer over-engineering** (custom inline SVG + 30 lines of CSS that idiomatic Gutenberg made unnecessary)
3. **Precision issues** in the bot regex (broad substrings instead of named tools)

The lesson is procedural, not technical. The "spec is clear, just execute" instinct cost real quality. Skill protocol exists for that exact reason.

## Key design decisions

**MU plugin, not theme `inc/`.** Subscriber metrics need to survive theme switches. Trade-off: one manual copy step at deploy time, documented in `mu-plugins/README.md`. The settings tab integrates with the theme's tab dispatch via `do_action('sn_admin_rss_tab')`; the theme's `has_action()` fallback gracefully shows "tracker not installed" when the MU plugin file isn't yet deployed.

**Local DB table is the source of truth; Plausible is fan-out.** The widget and the activity tab read from `wp_rss_feed_log`. Plausible outage doesn't blank the trend data. This inverts the more common "Plausible is canonical, DB is debug log" pattern.

**UTC throughout.** Wrote rows with `current_time('mysql', true)` (UTC) and queried with `UTC_TIMESTAMP() - INTERVAL %d DAY`. Caught myself almost using `NOW()`, which would have silently slid the window by Cloudways's local-TZ offset.

**Hashed UA, no IP storage.** `substr(sha256(UA), 0, 16)` gives 64 bits of identity-stability — plenty for rough unique-client counting, zero PII surface. IP is forwarded to Plausible at request time (so its geo lookup works) but never persisted locally.

**Settings exposed, regex hardcoded.** Plausible URL / domain / event name / retention / on-off toggle are option-backed and form-edited. The bot regex stays in code — there's no safe way to validate a user-input regex at form-submit time, and a bad regex could silently break all tracking.

**Bot regex is generous on aggregators, conservative on bots.** Decision rule: when in doubt, count it. After the Feedly bug, the bias is firmly toward false-negative (crawler noise) over false-positive (silently dropped real subscribers). Crawler noise is detectable in the data; dropped subscribers aren't.

**No header RSS icon.** Spec made it conditional on existing social links being in the header. There aren't any — the header is logo + 8-item nav, already dense at desktop, hamburger overlay on mobile. Adding a ninth element causes regressions for no incremental discoverability over the global footer.

## Aggregator caveat

Feedly, Inoreader, NetNewsWire cloud sync, etc. poll feeds server-side and serve cached versions to their users. The metric reflects **feed-fetch events**, not precise unique human subscribers. One Feedly subscriber will look like one unique `ua_hash` (Feedly's poller UA) — they might have 50 readers behind that. This is a trend indicator, not a subscriber roll-call. Documented in the CHANGELOG so future-me doesn't try to read it as the latter.

## Deployment

1. Push to repo, deploy theme to Cloudways via git/SFTP
2. **Manually copy** `mu-plugins/rss-plausible-tracker.php` → `wp-content/mu-plugins/rss-plausible-tracker.php` on syntharchy-wp
3. Visit `/wp-admin/` once → `dbDelta` runs, creates `wp_rss_feed_log`
4. Visit **Appearance → Signal & Noise → RSS** → confirm defaults render correctly
5. Smoke test: hit `/feed/` from a real browser, confirm Plausible event and DB row
6. Run `php mu-plugins/tests/bot-detection.php` on the host (33 fixtures, should print all-pass)
7. Tag `v8.0.0` at session end

## Open questions / follow-ups

- **Privacy policy entry needed.** Storing hashed UA in DB — not strict PII under GDPR but plausibly an "online identifier" for EU readers. Add a one-sentence mention to the site privacy policy. Out of scope for 8.0.0, flagged for next content pass.
- **Sparkline?** The dashboard widget shows a single number. A 30-day sparkline would be more informative once there's enough data to see a meaningful curve — revisit after a month.
- **Per-feed-URL breakdown?** The `feed_url` column already stores `/feed/` vs `/notes/feed/` vs category feeds. Would just need a `GROUP BY` in the widget or a new section on the settings tab.
- **Bot regex maintenance.** Add to a quarterly review checklist. New crawlers appear; the fixture test makes additions cheap (add a row, run the test, iterate).
- **Cron-based retention?** Currently log retention is manual via the "Purge now" button. A weekly WP-Cron job would auto-prune. Deferred until the table actually has enough rows to matter.
