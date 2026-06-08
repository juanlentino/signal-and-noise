# Handoff — Music Identity SHIPPED (theme v9.13.0 + plugin v4.13.0)

**Date:** 2026-06-08
**Status:** ✅ Both halves built, TDD'd, adversarially verified, and released to `main` + tagged. Two manual steps remain (owner-only: `/music` placement + first live sync). The two `music-identity` feature worktrees were removed.

## What shipped

Paired minor — the full "flagship" Music Identity feature from the spec/plan:

- **Plugin v4.13.0** (`c1b581e`, tag `v4.13.0`) — the populate + control layer on top of the prior session's spine:
  - `inc/muso-api.php` (T2) — credential-free client for Muso's **unauthenticated public** credits endpoint (`api2.muso.ai`); paginates, fails closed, groups 60 track-credits → 11 albums by `album.id`.
  - `inc/spotify-api.php` (T3) — client-credentials token (cached) + `GET /v1/tracks/{id}` → album (id/url/album_type/artwork/full release_date). Fails soft to Muso-only.
  - `inc/discography-sync.php` (T4) — cron orchestrator (daily, scheduled on `init` with a `wp_next_scheduled` guard); last-good on failure; `sn_discography_entries` filter.
  - `inc/seo-schema-music.php` (spine, refined) — `MusicAlbum` JSON-LD, Person-as-producer, **full `YYYY-MM-DD` `datePublished`**.
  - `inc/discography-store.php` (spine) — normalized non-autoloaded store; cron is the sole writer.
  - **Monitoring → Music** admin sub-tab (T6) — status + masked/constant-lockable Spotify creds + Muso profile id + "Sync now".
- **Theme v9.13.0** (`11fddaa`, tag `v9.13.0`) — the display layer (built in the prior session, released here):
  - `inc/discography-render.php` — `[sn_discography]` year-grouped timeline, standalone-safe, fully escaped.
  - `assets/js/discography.js` — lazy click-to-play (zero eager iframes), enqueued only on `/music`.
  - `assets/css/components.css` — `.sn-discography*` brutalist styles. Cross-package Contract 5 test.

## Architecture (as built — supersedes the spec's credentialed-Muso framing)

`WP-Cron (daily) → muso-api (public, no key) → group by album.id → spotify-api (resolve album from a track id) → normalize → store option → { seo-schema-music JSON-LD on /music, theme [sn_discography] via sn_discography_entries filter }`. **No request-time API calls** — pages serve from the cache. **No Muso credential.** Spotify is optional (album embeds + artwork enrichment).

## Verification

- Plugin suite **61/61**, theme **27/27** (exit-code sweeps). phpcs falsified-clean in both repos (`--parallel=1` true file count + injected a real security violation, confirmed flagged, reverted).
- **Adversarial-verify workflow** (6 lenses → independent skeptics, crashed-verifier ≠ refutation): 1 confirmed HIGH — schema emitted a bare-year `datePublished`. **Fixed** (`3577f3b`): threaded a full `date` through store→muso→spotify→sync→schema; emits the real `YYYY-MM-DD`, honest year fallback, **no fabricated `-01-01`**. Re-verified by the schema suite.

## ⚠️ TWO MANUAL STEPS REMAIN (owner-only — could not be done from the worktree)

1. **`/music` placement.** `templates/page-music.html` renders `<!-- wp:post-content /-->`, so the hand-curated Spotify embeds live in the **Page's `post_content` (DB)**, not the template. In wp-admin → Pages → **Music**, replace the embed blocks in the page **content** with a **Shortcode** block containing `[sn_discography]`. Header + Muso CTA are in the template, so the content should hold only the shortcode. (Left as a template edit it would blank the area until the first sync and orphan the curated fallback — so it's intentionally manual.)
2. **First live sync.** Monitoring → **Music**: paste the Spotify Client ID + Secret (the working creds from the earlier chat; or set `SN_SPOTIFY_CLIENT_ID`/`SN_SPOTIFY_CLIENT_SECRET` in wp-config), click **Sync now**. Confirm the status shows ~11 releases, `/music` renders the timeline, `view-source` shows valid `MusicAlbum` JSON-LD, and the [Rich Results Test](https://search.google.com/test/rich-results) parses it. Muso needs no credential — even with Spotify left blank, "Sync now" populates the timeline with Muso artwork (no embeds). The daily cron keeps it fresh after that.

## Follow-up flagged (out of scope, separate task)

The masked-credential save in `pl_save` / `cf_save` (Plausible/Cloudflare) uses `'••••' !== substr($v,0,4)` — a bullet is 3 bytes, so `substr` cuts mid-character and the comparison never matches, meaning re-saving those tabs without re-typing **persists the literal `••••XXXX` placeholder over the real token**. The branch is untested. The new `music_save` handler uses the correct `0 === strpos($v,'••••')` and has a regression test. A background task was spawned to fix the two older handlers the same way.

## Worktrees

Both `claude/music-identity` feature worktrees (`signal-and-noise-tools` + `signal-and-noise`) were removed after release (`git worktree remove`). Nothing pending in them.
