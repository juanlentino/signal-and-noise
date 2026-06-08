# Music Identity — Design Spec

**Date:** 2026-06-08
**Status:** Design approved (brainstorm complete). Awaiting user spec review → writing-plans.
**Type:** Paired plugin + theme feature (net-new). Flagship of the "Batch B" cycle.
**Repos:** plugin `signal-and-noise-tools` (sync + data + schema) · theme `signal-and-noise` (`/music` display).

## 1. Problem

juanlentino.com is a music producer's site that models **zero music**. The `/music` page is a hand-curated selection of Spotify embeds in the Page's `post_content` plus an external link to Juan's verified **Muso.AI** credits profile — no structured data, no on-site discography model, no schema describing Juan as a music professional. This is the site's single biggest content/SEO blind spot.

**Goal (chosen "full flagship"):** model the discography once as a single source of truth, emit Music schema from it (SEO / Knowledge-Graph), AND render it as an enhanced `/music` showcase — all **zero-touch** for the owner once configured.

## 2. Decisions made during brainstorm

| Decision | Choice | Why |
|---|---|---|
| Scope | Full flagship (data model + schema + showcase) | User intent |
| Data sources | **Muso.AI** (verified producer credits + roles) + **Spotify** (media: artwork, embeds, metadata) | Complementary: Muso has credits, Spotify has playable media |
| Integration | **Muso.AI API auto-sync** (cron) enriched with Spotify | User wants "best + easiest, never touch it once it's there" |
| Muso.AI API access | **Confirmed available** (`x-api-key`) | Unlocks true zero-touch — the *only* auto-updating source of producer credits |
| Ownership | Plugin owns sync + data + schema; theme owns display | Schema already lives plugin-side (`inc/seo-schema.php`); display is brand/theme domain |
| Version | **Paired minor** — plugin v4.13.0 + theme v9.13.0 | Net-new ≠ breaking change. Project rule: majors gate on actual breaking changes; the v10.0.0-scope audit found zero breaking driver. v10/v5 stay reserved for the WP-floor-raise + REST-removal cleanup. |

### Verified external-service constraints (researched 2026-06-08)
- **Spotify Web API** — client-credentials flow gives public catalog data + artwork **without** user login. But producer/songwriter credits are **NOT** exposed (limitation open since 2018, [issue #779](https://github.com/spotify/web-api/issues/779)). → Spotify = media only, never the credit source.
- **Muso.AI API** — `x-api-key` auth; Profile / Album / Charts endpoints; 50M+ verified tracks ([developer.muso.ai](https://developer.muso.ai/)). The only machine-readable source of Juan's producer roles. Access is gated (owner has confirmed access).
- **schema.org** — a producer is a `Person` referenced via the **`producer`** property on `MusicRecording`/`MusicAlbum` (canonical example: George Martin). `byArtist` is the *primary* artist (someone else). The handoff's "MusicGroup" framing is **wrong** for a producer — Juan stays a `Person`.

## 3. Architecture

```
WP-Cron (daily)
  └─ inc/discography-sync.php  (orchestrator)
       ├─ inc/muso-api.php     → Juan's Muso profile: releases + his role(s) per release  [+ ISRC/UPC if available]
       ├─ inc/spotify-api.php  → per release: artwork, album/track metadata, Spotify id/embed
       └─ normalize + write ──→ inc/discography-store.php  (one non-autoloaded option; cron is sole writer)
                                   │
        ┌──────────────────────────┴───────────────────────────┐
        ▼ (plugin reads store)                                  ▼ (theme reads store via filter)
  inc/seo-schema-music.php                              [sn_discography] shortcode (theme)
   → MusicAlbum/MusicRecording JSON-LD on /music         → branded release timeline on /music
```

**No request-time API calls.** Pages serve entirely from the cached store; the cron owns refresh. APIs are touched only by the cron + the manual "Sync now" button.

### Components (each a small, single-purpose unit)

**Plugin:**
1. `inc/muso-api.php` — thin Muso.AI client (`x-api-key`, Profile + Album endpoints; cached; error-tracked; constant-lockable credential). Mirrors `inc/plausible-api.php`.
2. `inc/spotify-api.php` — thin Spotify client (client-credentials token → metadata + artwork; token cached until expiry). Same pattern.
3. `inc/discography-sync.php` — cron orchestrator: pull Muso → enrich with Spotify → normalize → write store. Idempotent full-rebuild; **keeps last-good store on any API failure** (page never blanks). Records last-run status/error/count.
4. `inc/discography-store.php` — canonical normalized release list + accessors. A dedicated **non-autoloaded option** (survives API outages; cron is sole writer). Read once per request on `/music` + schema emit.
5. `inc/seo-schema-music.php` — extends `seo-schema.php`: emits per-release JSON-LD from the store, only on `/music` (or wherever the discography surfaces).
6. Admin sub-tab — a new **Monitoring** sub-tab (alongside Plausible; both are external-API integrations with credentials + sync status): the two credentials (masked, constant-lockable) + sync status (last run, count, last error) + "Sync now" button. Reuses the existing credential-field / flash / dispatcher patterns (`sn_handle_*` + `sn_admin_flash_messages` + the admin-post handler map) and the `sn_admin_top_tabs()` sub-tab data.

**Theme:**
7. `[sn_discography]` shortcode (+ render module, e.g. `inc/discography-render.php`) — reads the store via the standalone-safe filter and renders the branded timeline. Placed once in the `/music` Page content (replacing the manual embeds block).

## 4. Schema model

Juan = the existing canonical `Person` `@id` (already emitted by the plugin), referenced as **`producer`** on each release. Per-release JSON-LD shape:

```json
{
  "@context": "https://schema.org",
  "@type": "MusicAlbum",
  "name": "<release title>",
  "byArtist": { "@type": "MusicGroup", "name": "<primary artist>" },
  "producer": { "@id": "<canonical Juan Person @id>" },
  "datePublished": "<year>",
  "image": "<Spotify artwork URL>",
  "sameAs": ["<Spotify URL>", "<Muso.AI credit URL>"]
}
```

(`MusicRecording` for single tracks, with `inAlbum`.) Roles beyond producer (mixer/engineer) map to `producer` where schema.org has no dedicated property, with the precise role surfaced in the visible display.

**Honest SEO expectation:** this strengthens Juan's entity in the Knowledge Graph and helps search engines comprehend his catalog — it is **not** a guaranteed visual rich result (Google has no dedicated producer rich card). The value is entity/catalog comprehension + eligibility, not a snippet.

## 5. `/music` display

Keep the existing brutalist header (eyebrow "Catalog · Discography · 2005 → 2026", "MUSIC" title, intro) and the "Verified Credits → Muso.AI" CTA. Replace the hand-curated embeds with a **data-driven release timeline**, grouped by year descending, rendered from the store. Each entry is one component with fixed fields: artwork (Spotify), title, `byArtist` (primary artist), **role(s)** (from Muso), year, links (Spotify + Muso).

**Performance decision (load-bearing):** N live Spotify iframes would wreck the page. The timeline renders as a **lightweight list — artwork + metadata, zero iframes by default** — with click-to-play (lazy-mount the embed on demand) or a Spotify link per release, plus optionally **one** featured embed up top. Keeps `/music` fast, consistent with the brutalist speed-first ethos.

## 6. Standalone-safety contract (cross-package)

Mirrors the v9.12.0 filter pattern. The theme renders from `apply_filters( 'sn_discography_entries', array() )`; the plugin registers `add_filter( 'sn_discography_entries', … )` returning the cached store. **Plugin absent → empty array → `/music` falls back to its existing static content** (graceful; no fatal, no blank). The new filter joins the cross-package contract set locked by the theme's `tests/cross-package-listeners.php` and the plugin's contract stubs.

## 7. Sync robustness, security, testing

- **Scheduling:** daily WP-Cron, reusing the plugin's cron infra. Scheduled via an `admin_init` version-check + sentinel option (NOT activation alone — install hooks can't self-observe). Manual "Sync now" for on-demand refresh.
- **Failure modes:** idempotent full-rebuild; either API failing → keep last-good store; surface the error in the admin status panel. Respect rate limits (Spotify token cached; minimal Muso calls; batch + cache).
- **Security:** Muso `x-api-key` + Spotify client id/secret stored as **non-autoloaded, constant-lockable options** (`SN_MUSO_API_KEY`, `SN_SPOTIFY_CLIENT_ID`/`SN_SPOTIFY_CLIENT_SECRET` in wp-config), masked in the field, never logged — mirroring the Plausible/Cloudflare token pattern. All calls server-side. External data (Muso/Spotify) treated as untrusted: sanitized on store-write, escaped on render.
- **Testing:** Plugin — normalizer (Muso+Spotify → store shape), schema emitter (correct `producer`/`byArtist`/`sameAs`), external-data sanitize, failure-keeps-last-good, the cross-package filter — against stubbed API fixtures. Theme — renderer reads the filter, renders the timeline, standalone-safe + escaped. phpcs + suites falsification-verified, per project discipline.

## 8. Key technical risk (resolved in the plan's research phase, not a design gap)

**Matching a Muso release to its Spotify counterpart** — no shared id. Hinges on whether Muso's Profile/Album response includes **ISRC/UPC** identifiers, which Spotify can look up reliably. If present → solid matching. If absent → fuzzy title+artist match with a manual per-release override field. The implementation research pins down the exact Muso.AI Profile response shape (Juan's profile id `50d6a7c0-2d7b-4557-94b6-c472fa949df2`, role representation, pagination, identifier fields) + the Spotify lookup path **first**, before any build.

## 9. Non-goals (YAGNI)

- **No CPT / per-release pages.** The store is a cached mirror, not materialized posts — avoids thin-page bloat + sync→CPT reconciliation. (A CPT is a later option only if individual release URLs are ever wanted.)
- **No eager Spotify embeds.** Lazy/click-to-play only.
- **No Discogs / other sources.** Muso + Spotify only.
- **No v10.0.0 / v5.0.0 major.** Ships as a paired minor; the majors stay for breaking cleanup.
- **No user-facing editing of synced data.** It mirrors Muso; the only manual surface is credentials + "Sync now" + (if matching needs it) per-release Spotify-id overrides.

## 10. Versioning

Current: theme v9.12.0, plugin v4.12.0. This lands as **theme v9.13.0** + **plugin v4.13.0** (paired minor). Both are net-new MINORs per SemVer + the project's "majors gate on actual breaking changes" rule. Order is irrelevant (standalone-safe defaults); plugin carries the bulk.

## 11. Cross-references

- Corrects the handoff framing "theme v10.0.0 = Music identity (MusicGroup/MusicRecording)" — it's a **paired minor**, Juan is a **`Person` producer**, not a MusicGroup. See `docs/superpowers/handoffs/2026-06-08-settings-hygiene-batch-a-complete.md`.
- v10.0.0 surface audit (no breaking driver): `docs/superpowers/specs/2026-05-27-v10.0.0-scope.md`.
- Credential + cron patterns to mirror: plugin `inc/plausible-api.php`, `inc/plausible-admin.php`, `inc/cron-dashboard.php`, `sn_handle_pl_save`/`sn_handle_cf_save`.
- Schema home: plugin `inc/seo-schema.php`.
- Standalone-safe filter precedent: v9.12.0 (`inc/theme-filters.php` ↔ theme `apply_filters` sites).
