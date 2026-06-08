# Handoff — /music redesign + featured player + gallery dedup

**Date:** 2026-06-08
**Status:** ✅ All shipped + tagged on `main`. Two owner-only steps remain (install + featured placement).

This continues the same-day Music Identity arc (see `2026-06-08-music-identity-shipped.md`). After Music Identity went live, the `/music` page was redesigned and two follow-ups landed.

## What shipped (this segment)

- **Theme v9.14.0** (`d3f5c7a`, tag `v9.14.0`) — **/music cover-grid redesign.** `[sn_discography]` went from a vertical row list to an album-cover gallery: a sticky controls rail (live release count + filter-by-role chips), year-grouped cover cards, whole-cover click-to-play, per-card Muso `Credits ↗`. Theme-side only (render + CSS + JS); the `sn_discography_entries` store contract is unchanged. Design contract: `docs/superpowers/specs/2026-06-08-music-redesign-design.md` + the approved mockup `docs/superpowers/mockups/2026-06-08-music-redesign.html`.
- **Plugin v4.14.0** (`7cb5f72`, tag `v4.14.0`) — **featured-release setting + gallery dedup.**
  - *Featured release:* `inc/music-featured.php` parses a pasted Spotify URL/URI (`sn_music_featured_parse` handles `/intl-xx/`, `?si=`, `spotify:` URI) → stored → exposed over the standalone-safe `sn_music_featured` filter. Field added to Monitoring → Music (handled within `music_save`; dispatch map unchanged at 29).
  - *Dedup fix:* `sn_muso_dedupe_albums()` collapses same-`title+artist` releases into the fuller one (most credited tracks, union of roles, earliest date) after the by-`album.id` grouping. Fixed the duplicate "Fin del Mundo" cards (a 1-track 2008 single + a 10-track 2007 album). 60 credits → **10 distinct releases** (was 11). Cleans gallery + count + `MusicAlbum` schema.
- **Theme v9.15.0** (`61baa84`, tag `v9.15.0`) — **`[sn_music_featured]` shortcode.** Renders the single "press play" Spotify embed at the top of `/music` from the `sn_music_featured` filter, in a brutalist "Featured · Press play" card (type-adaptive height; the one eager iframe — grid stays click-to-play). Standalone-safe; cross-package **Contract 6** added.
- **Also landed:** plugin **v4.13.1** (`fb142e3`) — the spawned masked-token fix (pl_save/cf_save `substr` → `strpos`). The Cloudflare CSP `img-src` gained `https://i.scdn.co` earlier so album art loads (see `[[reference_csp_is_edge_set_not_in_repo]]`).

## Architecture notes (for the next session)

- **Two cross-package filters now**: `sn_discography_entries` (grid) + `sn_music_featured` (hero). Same contract shape: plugin owns data + `add_filter` (guarded by a `*_TEST` constant); theme reads `apply_filters(..., array())` and degrades to `''`. Theme is standalone-safe.
- **Dedup is data-layer** (the Muso grouper), so it fixes everything downstream at once. The store entry `id` is the *fuller* release's Muso album id.
- **Verification pattern that paid off:** every change was confirmed by rendering the REAL code in a localhost preview (`preview_start` + `/tmp/render_preview.php` rendering the real shortcodes against the live catalog) and exercising it (filter clicks, DOM eval) — not just green CLI tests. Caught a `git checkout`-clobbered-uncommitted-render and a phantom phpcs (fresh worktree had no `vendor/` → silent no-op). **`composer install` in every fresh worktree before trusting phpcs; never `git checkout` an uncommitted file.**

## ⚠️ Owner-only steps remaining

1. **Install both updates** — Dashboard → Updates → *Signal & Noise* v9.15.0 + *Signal & Noise Tools* v4.14.0. The dedup applies on the next sync (or **Sync now**).
2. **Featured via settings (optional):** edit the Music page, replace the curated featured Spotify embed block with a Shortcode block holding `[sn_music_featured]`, then set the release in Monitoring → Music. No CSP change needed (`frame-src` already allows `open.spotify.com`).

## Worktrees
Both feature worktrees (`signal-and-noise/.claude/worktrees/music-redesign` + `signal-and-noise-tools/.claude/worktrees/music-featured`) removed after release.
