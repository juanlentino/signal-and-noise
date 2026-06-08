# Handoff — Music Identity: spine built + verified, Muso data source cracked (zero-credential)

**Date:** 2026-06-08
**Status:** Credential-free spine BUILT + adversarially verified + both findings fixed (committed in feature worktrees, NOT yet pushed/released). Data source SOLVED with **zero credentials**. Remaining: the populate layer (sync + admin) + `/music` placement + paired release.

## Canonical docs
- **Spec:** `docs/superpowers/specs/2026-06-08-music-identity-design.md` — ⚠️ **partially superseded** by the REVISED ARCHITECTURE below (no Muso API key; public endpoint; Spotify only for album-id resolution). Update it next.
- **Plan:** `docs/superpowers/plans/2026-06-08-music-identity.md` — Phase 0 done; Tasks 1/5/7/8 done; Tasks 2/3/4/6/9 remain (and simplify per below).

## 🔑 THE BREAKTHROUGH — Muso's public API (no key needed)
The Muso developer API (x-api-key) is gated + the user couldn't find a key. But the public credits SPA calls an **unauthenticated, CORS-open** API that is **reachable server-side** (unlike Cloudflare-walled `credits.muso.ai`/`developer.muso.ai`):

```
GET https://api2.muso.ai/api/v4/profile/<PROFILE_ID>/credits?limit=100&offset=0
  (no auth header; send Accept: application/json + Origin/Referer https://credits.muso.ai + X-App-Version: 4.0#131#web + a browser UA)
```
- Juan's PROFILE_ID = `50d6a7c0-2d7b-4557-94b6-c472fa949df2`. Returns `{result,code,data:{totalCount,limit,offset,hasMoreToLoad,items[]}}`. Paginate via `offset` until `hasMoreToLoad:false`.
- Per item: `credits[]` (roles, e.g. ["Mastering","Engineer"]), `track{id,title,spotifyId,previewUrl,popularity,rank}`, `album{id,title,avatarUrl,avatarUrl_640_640}`, `releaseDate` ("YYYY-MM-DD"), `artists[]{id,name,avatarUrl}`.
- **Real fixture committed:** plugin worktree `tests/fixtures/muso-credits.json` (60 tracks / 11 albums, 2007–2025). Build/TDD the parser against it.
- Caveat: undocumented internal endpoint (could change). Note in the design; the official x-api-key dev API remains the documented fallback if it ever breaks.

## REVISED ARCHITECTURE (supersedes spec §2/§3/§8)
- **Data source:** Muso public credits API (above) — **no credential**. Eliminates the gated Muso key entirely. The spec's "Muso↔Spotify matching risk" is GONE — Muso hands the `track.spotifyId` directly.
- **Spotify:** still useful but MINIMAL — resolve each album's **Spotify album id** from a track's `spotifyId` (GET `/v1/tracks/{id}` → `album.id`) so the play button embeds the ALBUM (`embed/album/`) and the schema `type='album'` (MusicAlbum) stays consistent. ~11 cached calls. Uses the user's working Spotify client-creds (confirmed). Artwork can come straight from Muso (`album.avatarUrl_640_640`).  **Credentials needed: Spotify only** (constant-lockable). Muso = none.
- **Modeling:** group the 60 track-credits **by `album.id`** → ~11 album entries. Per album: `title`=album.title, `artist`=artists[0].name (byArtist), `roles`=union of `credits` across that album's tracks, `year`=releaseDate year, `image`=album.avatarUrl_640_640, `type`='album', `spotify_id`=resolved album id, `spotify_url`=open.spotify.com/album/<id>, `muso_url`=credits.muso.ai/album/<album.id>.
- **Store / schema / display:** UNCHANGED — already built (the spine). They're source-agnostic by design, so this all just feeds them.

## What's BUILT + verified (in feature worktrees, unpushed)
Both off each repo's `origin/main`; branch `claude/music-identity` in each.
- **Plugin** (`…/signal-and-noise-tools/.claude/worktrees/music-identity`, off v4.12.0): `inc/discography-store.php` (T1, `277af4a`), `inc/seo-schema-music.php` (T5, `a21969a`), bootstrap (`1712b9b`), **XSS HIGH fix** (`4494ba0` — sanitize title/artist + drop JSON_UNESCAPED_SLASHES so `</script>` can't break the ld+json), fixtures (`8258e37`). Full suite 0-fail; phpcs falsified-clean.
- **Theme** (`…/signal-and-noise/.claude/worktrees/music-identity`, off v9.12.0): `inc/discography-render.php` `[sn_discography]` (T7, `a606d1f`), styles + lazy click-to-play JS (T8, `736568c`), **type-aware embed LOW fix** (`99e84e0` — render emits `data-type`, JS picks `embed/track|album/`). Full suite 0-fail; phpcs clean; JS syntax OK.
- Spine was built + adversarially verified by a 2-track Ultracode workflow; the 2 findings (1 HIGH, 1 LOW) were fixed inline with TDD and re-verified by hand.

## REMAINING (the populate + control layer + release)
1. **`inc/muso-api.php`** — `sn_muso_fetch_credits($profile_id)`: paginated GET of the public endpoint (wp_remote_get + the headers above), returns merged raw items; WP_Error/empty on failure. TDD against `tests/fixtures/muso-credits.json`.
2. **`inc/spotify-api.php`** — `sn_spotify_token()` (client-creds, cached) + `sn_spotify_album_for_track($track_spotify_id)` (GET /v1/tracks/{id} → album id/url). TDD against `tests/fixtures/spotify-search.json` (or a tracks fixture). Spotify creds: constant-lockable options (`SN_SPOTIFY_CLIENT_ID`/`SN_SPOTIFY_CLIENT_SECRET`).
3. **`inc/discography-sync.php`** — orchestrator: fetch Muso → group-by-album → resolve album ids via Spotify → `sn_discography_normalize_entry` → `sn_discography_set`; **last-good on failure**; register `add_filter('sn_discography_entries', fn → store entries)` (the theme already reads this); daily WP-Cron scheduled via admin_init+sentinel; bootstrap require. TDD.
4. **Admin (Task 6, simplified)** — a Monitoring → Music sub-tab: Spotify creds (masked, constant-lockable) + profile id (default Juan's) + status (last_synced/count/last_error) + "Sync now". Mirror `sn_handle_pl_save`/`sn_handle_cf_save`. **No Muso credential field.**
5. **`/music` placement** (manual): replace the hand-curated embeds block in the WP Page's content with `[sn_discography]` (keep header + Muso CTA).
6. **Paired release** (Task 9): full suites + falsified phpcs + lean adversarial verify; live smoke (Sync now → store populates → /music timeline + valid MusicAlbum JSON-LD via Rich Results Test); bump plugin → **v4.13.0** + theme → **v9.13.0** + CHANGELOGs + tags. Then remove the two `music-identity` worktrees.

## Credentials state
- **Spotify** client id/secret were pasted in chat earlier (user said they'd clear context). They WORK (token + search confirmed). Re-enter via the admin (or wp-config constants) at deploy. **No Muso key needed at all.**
- The Spotify capture also produced `tests/fixtures/spotify-search.json` (album-search shape).

## First thing on resume
Update the spec (§2 decisions, §3 architecture, §8 — delete the matching risk) + the plan (Tasks 2/3/6) to the REVISED ARCHITECTURE above, then build items 1–6. Everything is TDD-able against the committed real fixture. The store-shape contract (in the plan) is unchanged and locked by the spine's tests.
