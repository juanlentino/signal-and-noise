# Music Identity — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A zero-touch on-site discography: a daily cron mirrors Juan's verified Muso.AI producer credits, enriches each release with Spotify media, caches it, emits `MusicAlbum`/`MusicRecording` schema, and renders a `/music` release timeline.

**Architecture:** Plugin owns sync + data + schema; theme owns display. The plugin polls Muso.AI (credits/roles) + Spotify (media) on WP-Cron, normalizes into one cached option (the **store**), and exposes it via the standalone-safe filter `sn_discography_entries`. The theme's `[sn_discography]` shortcode reads that filter and renders the timeline; the plugin's schema module reads the store and emits JSON-LD. No request-time API calls.

**Tech Stack:** PHP, WordPress (FSE theme + companion plugin), WP-Cron, Muso.AI REST API (`x-api-key`), Spotify Web API (client-credentials), standalone CLI fixture tests (`tests/*.php`, no PHPUnit), `phpcs` (WPCS).

**Spec:** `docs/superpowers/specs/2026-06-08-music-identity-design.md`

**Repos & versions:** plugin `signal-and-noise-tools` → **v4.13.0** (off `origin/main` = v4.12.0); theme `signal-and-noise` → **v9.13.0** (off `main` = v9.12.0). Paired minor.

---

## The normalized store shape (the contract everything downstream reads)

This is defined and owned by us — the API parsers map *into* it; the schema emitter, theme display, admin status, and all tests read *from* it. Stable across both API sources.

```php
// One discography entry:
array(
	'id'            => '',   // string — stable id: prefer ISRC/UPC, else Muso id, else slug(title-artist)
	'title'         => '',   // string — release title
	'artist'        => '',   // string — primary artist (schema byArtist)
	'roles'         => array(), // string[] — Juan's credited roles, e.g. ['Producer','Mixing']
	'year'          => 0,    // int — release year (schema datePublished)
	'type'          => '',   // string — 'album' | 'single' | 'track'
	'image'         => '',   // string — https Spotify artwork URL ('' if unmatched)
	'spotify_id'    => '',   // string — Spotify album/track id for the lazy embed ('' if unmatched)
	'spotify_url'   => '',   // string — open.spotify.com/... ('' if unmatched)
	'muso_url'      => '',   // string — credits.muso.ai deep link
	'isrc'          => '',   // string — optional
	'upc'           => '',   // string — optional
);
// Store option value:
array(
	'entries'      => array(/* the entries above, sorted year desc */),
	'last_synced'  => 0,     // int unix ts (0 = never)
	'count'        => 0,     // int
	'last_error'   => '',    // string ('' = ok)
);
```

Store option key: `SN_DISCOGRAPHY_OPTION = 'sn_discography'` (non-autoloaded). Credential option keys: `SN_MUSO_TOKEN_OPT = 'sn_muso_token'`, `SN_SPOTIFY_ID_OPT = 'sn_spotify_client_id'`, `SN_SPOTIFY_SECRET_OPT = 'sn_spotify_client_secret'` (all non-autoloaded; constant-lockable via `SN_MUSO_API_KEY` / `SN_SPOTIFY_CLIENT_ID` / `SN_SPOTIFY_CLIENT_SECRET`).

---

## File structure

**Plugin (`signal-and-noise-tools`):**
- Create `inc/discography-store.php` — store accessors (`sn_discography_get()`, `sn_discography_set()`, entry-normalize helper, meta accessors).
- Create `inc/muso-api.php` — Muso client (`sn_muso_config()`, `sn_muso_profile_releases()`), modeled on `inc/plausible-api.php`.
- Create `inc/spotify-api.php` — Spotify client (`sn_spotify_token()`, `sn_spotify_match_release()`).
- Create `inc/discography-sync.php` — `sn_discography_run_sync()` (orchestrator + cron hook + scheduler) + `sn_discography_entries_filter()` (the `add_filter` callback).
- Create `inc/seo-schema-music.php` — `sn_music_schema_jsonld()` (emits per-release JSON-LD on `/music`).
- Create `inc/admin-forms/music.php` — `sn_admin_render_music_section()` (credentials + status + Sync-now form).
- Modify `inc/admin-post-actions.php` — `sn_handle_music_save()` + `sn_handle_music_sync()`.
- Modify `inc/admin-post-handler.php` — register the two actions.
- Modify `inc/admin-flash-messages.php` — flash strings.
- Modify `inc/admin-tabs-data.php` — add `music` sub-tab under Monitoring.
- Modify `inc/admin-page.php` — dispatch the `music` sub-tab.
- Modify `signal-and-noise-tools.php` — require the 5 new `inc/*.php` + the admin-form.
- Create `tests/discography-store.php`, `tests/muso-api.php`, `tests/spotify-api.php`, `tests/discography-sync.php`, `tests/music-schema.php`.
- Create `tests/fixtures/muso-profile.json`, `tests/fixtures/spotify-search.json` (captured in Phase 0).

**Theme (`signal-and-noise`):**
- Create `inc/discography-render.php` — `[sn_discography]` shortcode → reads `apply_filters('sn_discography_entries', array())` → timeline HTML.
- Modify `functions.php` — require it.
- Modify `assets/css/components.css` — `.sn-discography*` brutalist styles.
- Create `assets/js/discography.js` — click-to-play lazy Spotify embed.
- Modify the `/music` WP Page content — place `[sn_discography]` (manual, documented step).
- Create `tests/discography-render.php`; modify `tests/cross-package-listeners.php` (add the `sn_discography_entries` contract).

---

## Task 0: Worktrees + Phase-0 gate

**Files:** none (setup).

- [ ] **Step 1: Create fresh worktrees off each repo's current main**

```bash
# Plugin
cd /Users/juanlentino/Projects/signal-and-noise-tools
git fetch origin --quiet
git worktree add .claude/worktrees/music-identity origin/main -b claude/music-identity
cd .claude/worktrees/music-identity && grep -m1 'Version:' signal-and-noise-tools.php   # expect 4.12.0
# Theme: work in the current theme worktree (already on main, v9.12.0) OR a fresh one if isolation is needed.
```
Let `PLUGINWT` = the plugin worktree path; `THEMEWT` = the theme worktree path.

- [ ] **Step 2: Install plugin dev deps for phpcs**

```bash
cd "$PLUGINWT" && composer install --no-interaction --quiet && ls vendor/bin/phpcs
```

---

## Phase 0 — API research spike (GATING — Phases 1+ depend on its fixtures)

**Goal:** capture REAL Muso + Spotify responses with live credentials, save as test fixtures, and confirm the release-matching strategy (ISRC/UPC vs fuzzy). **No implementation parses these blind — the fixtures are the contract.**

**Files:** Create `tests/fixtures/muso-profile.json`, `tests/fixtures/spotify-search.json`; append a "Confirmed API shapes" section to the spec.

- [ ] **Step 1: Obtain credentials** — user provides the Muso.AI `x-api-key`; register a Spotify app at developer.spotify.com → client id + secret. Export locally (never commit):

```bash
export MUSO_KEY='<paste>'  SPOTIFY_ID='<paste>'  SPOTIFY_SECRET='<paste>'
```

- [ ] **Step 2: Capture the Muso profile + a release's credits**

Juan's profile id is `50d6a7c0-2d7b-4557-94b6-c472fa949df2` (from the live `/music` page CTA link). Hit the Profile endpoint and save the raw response. Confirm the exact base URL + path against developer.muso.ai once authenticated (the docs are bot-blocked to anonymous fetches; an authenticated browser/curl works):

```bash
curl -sS -H "x-api-key: $MUSO_KEY" \
  "https://developer.muso.ai/api/v3/profile/50d6a7c0-2d7b-4557-94b6-c472fa949df2" \
  | tee tests/fixtures/muso-profile.json | python3 -m json.tool | head -60
# If v3 path differs, try the album/credits endpoints per the live docs; capture whichever returns the credit list + roles.
```

- [ ] **Step 3: Document the confirmed shape** — in the fixture or a spec addendum, record: the field that holds the release list, the per-release **title / primary-artist / year / role(s)** field names, and crucially **whether ISRC and/or UPC are present** (decides the Spotify-match strategy). If neither identifier is present, note that the sync uses fuzzy title+artist matching + a manual override.

- [ ] **Step 4: Capture a Spotify client-credentials token + a search match**

```bash
TOKEN=$(curl -sS -X POST https://accounts.spotify.com/api/token \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "grant_type=client_credentials&client_id=$SPOTIFY_ID&client_secret=$SPOTIFY_SECRET" \
  | python3 -c 'import sys,json;print(json.load(sys.stdin)["access_token"])')
# Match a known release: by ISRC if available (track search), else by album+artist name.
curl -sS -H "Authorization: Bearer $TOKEN" \
  "https://api.spotify.com/v1/search?type=album&limit=1&q=$(python3 -c 'import urllib.parse;print(urllib.parse.quote("album:<TITLE> artist:<ARTIST>"))')" \
  | tee tests/fixtures/spotify-search.json | python3 -m json.tool | head -40
```
Confirm the response carries `images[].url` (artwork), `id` (embed), `external_urls.spotify` (link), `release_date`.

- [ ] **Step 5: Commit the fixtures** (NO credentials in them — they're public catalog/credit data)

```bash
cd "$PLUGINWT" && git add tests/fixtures/muso-profile.json tests/fixtures/spotify-search.json
git commit -m "test(fixtures): capture real Muso profile + Spotify search responses (Phase 0)"
```

> **GATE:** do not start Phase 1 until both fixtures exist and the identifier strategy (Step 3) is documented. The downstream parser tests load these fixtures verbatim.

---

# PHASE 1 — PLUGIN DATA ENGINE (PLUGINWT, ships v4.13.0)

## Task 1: Discography store + accessors

**Files:** Create `inc/discography-store.php`; Test `tests/discography-store.php`.

- [ ] **Step 1: Write the failing test**

Create `tests/discography-store.php` (CLI guard + in-memory `$GLOBALS['__options']` + `get_option`/`update_option` stubs, mirroring `tests/settings-theme.php`):

```php
require __DIR__ . '/../inc/discography-store.php';
// Empty store → safe defaults.
$s = sn_discography_get();
ok( is_array( $s['entries'] ) && $s['entries'] === array(), 'store: empty entries default' );
ok( $s['last_synced'] === 0 && $s['count'] === 0 && $s['last_error'] === '', 'store: empty meta defaults' );

// Normalize coerces types + fills missing keys.
$e = sn_discography_normalize_entry( array( 'title' => ' Hit ', 'artist' => 'X', 'year' => '2019', 'roles' => 'Producer' ) );
ok( $e['title'] === 'Hit' && $e['artist'] === 'X' && $e['year'] === 2019, 'normalize: trims + casts' );
ok( $e['roles'] === array( 'Producer' ), 'normalize: scalar role → array' );
ok( $e['id'] !== '' , 'normalize: derives a stable id when none given' );

// Set sorts by year desc + recomputes meta.
sn_discography_set( array(
	sn_discography_normalize_entry( array( 'title' => 'Old', 'artist' => 'A', 'year' => 2005 ) ),
	sn_discography_normalize_entry( array( 'title' => 'New', 'artist' => 'B', 'year' => 2024 ) ),
), 1700000000, '' );
$s = sn_discography_get();
ok( $s['entries'][0]['title'] === 'New' && $s['count'] === 2, 'set: sorts year desc + counts' );
ok( $s['last_synced'] === 1700000000, 'set: records sync ts' );
```

- [ ] **Step 2: Run → FAIL** — `php tests/discography-store.php` → undefined `sn_discography_get`.

- [ ] **Step 3: Implement** `inc/discography-store.php`:

```php
<?php
/**
 * Signal & Noise Tools — discography store (the normalized, source-agnostic
 * release cache the schema emitter + theme display + admin status all read).
 * Cron is the sole writer (see inc/discography-sync.php). Non-autoloaded.
 *
 * @package SignalNoiseTools
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

const SN_DISCOGRAPHY_OPTION = 'sn_discography';

function sn_discography_defaults() {
	return array( 'entries' => array(), 'last_synced' => 0, 'count' => 0, 'last_error' => '' );
}

function sn_discography_get() {
	$stored = get_option( SN_DISCOGRAPHY_OPTION, array() );
	return array_merge( sn_discography_defaults(), is_array( $stored ) ? $stored : array() );
}

function sn_discography_entry_defaults() {
	return array(
		'id' => '', 'title' => '', 'artist' => '', 'roles' => array(), 'year' => 0,
		'type' => 'album', 'image' => '', 'spotify_id' => '', 'spotify_url' => '',
		'muso_url' => '', 'isrc' => '', 'upc' => '',
	);
}

function sn_discography_normalize_entry( $raw ) {
	$e          = array_merge( sn_discography_entry_defaults(), is_array( $raw ) ? $raw : array() );
	$e['title'] = trim( (string) $e['title'] );
	$e['artist']= trim( (string) $e['artist'] );
	$e['year']  = (int) $e['year'];
	$e['roles'] = array_values( array_filter( array_map( 'trim', (array) $e['roles'] ) ) );
	foreach ( array( 'image', 'spotify_url', 'muso_url' ) as $u ) {
		$e[ $u ] = esc_url_raw( (string) $e[ $u ] );
	}
	foreach ( array( 'id', 'spotify_id', 'isrc', 'upc', 'type' ) as $k ) {
		$e[ $k ] = sanitize_text_field( (string) $e[ $k ] );
	}
	if ( '' === $e['id'] ) {
		$e['id'] = '' !== $e['isrc'] ? $e['isrc'] : ( '' !== $e['upc'] ? $e['upc'] : sanitize_title( $e['title'] . '-' . $e['artist'] ) );
	}
	return $e;
}

function sn_discography_set( array $entries, $synced_ts, $last_error ) {
	usort( $entries, function ( $a, $b ) { return (int) $b['year'] <=> (int) $a['year']; } );
	update_option( SN_DISCOGRAPHY_OPTION, array(
		'entries'     => array_values( $entries ),
		'last_synced' => (int) $synced_ts,
		'count'       => count( $entries ),
		'last_error'  => (string) $last_error,
	), false );
}
```

Add identity stubs for `esc_url_raw`/`sanitize_text_field`/`sanitize_title` to the test harness if undefined (mirror `tests/settings-theme.php`).

- [ ] **Step 4: Run → PASS.** **Step 5: Commit** `feat(discography): normalized store + accessors`.

## Task 2: Muso.AI client

**Files:** Create `inc/muso-api.php`; Test `tests/muso-api.php` (loads `tests/fixtures/muso-profile.json`).

- [ ] **Step 1: Write the failing test** — stub `wp_remote_*`/`is_wp_error` to return the captured fixture; assert `sn_muso_profile_releases()` returns an array of `{title, artist, year, roles[], muso_url, isrc?, upc?}` mapped from the REAL fixture fields (use the exact field names confirmed in Phase 0 Step 3). Assert config returns null when no key set (feature dormant).

- [ ] **Step 2: Run → FAIL.**

- [ ] **Step 3: Implement** `inc/muso-api.php` modeled on `inc/plausible-api.php`: `sn_muso_config()` (reads `SN_MUSO_API_KEY` constant else `sn_muso_token` option; returns null if absent), `sn_muso_api( $path )` (adds `x-api-key`, `wp_remote_get`, decodes, records last-error transient), `sn_muso_profile_releases()` (calls the profile endpoint, maps the confirmed fixture fields → the partial store-entry shape). **Map exactly the fields the Phase-0 fixture exposes; do not invent fields.**

- [ ] **Step 4: Run → PASS.** **Step 5: Commit** `feat(discography): Muso.AI profile client`.

## Task 3: Spotify client

**Files:** Create `inc/spotify-api.php`; Test `tests/spotify-api.php` (loads `tests/fixtures/spotify-search.json`).

- [ ] **Step 1: Write the failing test** — stub the token POST + search GET with fixtures; assert `sn_spotify_token()` caches the bearer token (one POST across repeated calls) and `sn_spotify_match_release( $title, $artist, $isrc )` returns `{spotify_id, spotify_url, image, year}` from the fixture, '' image when no match.

- [ ] **Step 2: Run → FAIL.**

- [ ] **Step 3: Implement** `inc/spotify-api.php`: `sn_spotify_config()` (constant-lockable id/secret), `sn_spotify_token()` (client-credentials POST → token cached in a transient until `expires_in`), `sn_spotify_match_release()` (search by ISRC if present else `album:<title> artist:<artist>`; pick top hit; pull `images[0].url`, `id`, `external_urls.spotify`, parse `release_date` year).

- [ ] **Step 4: Run → PASS.** **Step 5: Commit** `feat(discography): Spotify match client`.

## Task 4: Sync orchestrator + standalone filter

**Files:** Create `inc/discography-sync.php`; Test `tests/discography-sync.php`.

- [ ] **Step 1: Write the failing test** — stub `sn_muso_profile_releases()` (2 releases) + `sn_spotify_match_release()` (one matches, one returns no media). Run `sn_discography_run_sync()`; assert the store now has 2 normalized entries, the matched one has an `image`, the unmatched one has `image === ''` (still kept), `last_error === ''`. Then make `sn_muso_profile_releases()` return a `WP_Error`/empty and assert the store **keeps the prior 2 entries** + sets `last_error` (last-good preserved). Assert `sn_discography_entries_filter( array() )` returns the store's entries (the cross-package contract).

- [ ] **Step 2: Run → FAIL.**

- [ ] **Step 3: Implement** `inc/discography-sync.php`:
  - `sn_discography_run_sync()`: pull Muso releases; if empty/error → set `last_error`, DO NOT overwrite entries (re-read current, re-set with new ts + error); else for each release call `sn_spotify_match_release()`, merge media, `sn_discography_normalize_entry()`, then `sn_discography_set()`.
  - `sn_discography_entries_filter( $entries )`: return `sn_discography_get()['entries']` (the `add_filter('sn_discography_entries', …)` callback). Register with a `SN_DISCOGRAPHY_TEST` guard like `inc/theme-filters.php`.
  - Cron: `add_action('sn_discography_cron', 'sn_discography_run_sync')`; `sn_discography_maybe_schedule()` (daily; scheduled on `admin_init` via a version-sentinel option, NOT activation — install hooks can't self-observe).

- [ ] **Step 4: Run → PASS.** **Step 5: Commit** `feat(discography): cron sync (last-good on failure) + sn_discography_entries filter`.

## Task 5: Music schema emitter

**Files:** Create `inc/seo-schema-music.php`; Test `tests/music-schema.php`.

- [ ] **Step 1: Write the failing test** — seed the store with 1 entry; call `sn_music_schema_jsonld()`; `json_decode` the output; assert `@type === 'MusicAlbum'`, `producer['@id']` equals the canonical Person `@id` (from `seo-schema.php`), `byArtist.name` === the primary artist, `image`/`sameAs` populated from the entry, `datePublished` === the year. Assert empty store → emits nothing.

- [ ] **Step 2: Run → FAIL.**

- [ ] **Step 3: Implement** `inc/seo-schema-music.php`: `sn_music_schema_jsonld()` builds an `@graph` (or array) of per-entry `MusicAlbum`/`MusicRecording` nodes per the spec §4 shape, `producer` referencing the existing Person `@id` (reuse `seo-schema.php`'s id helper), `sameAs` = array_filter([spotify_url, muso_url]); hook to `wp_head` ONLY on the `/music` page (mirror how `seo-schema.php` gates per-route). `wp_json_encode`, escaped.

- [ ] **Step 4: Run → PASS.** **Step 5: Commit** `feat(discography): MusicAlbum/MusicRecording JSON-LD (Person as producer)`.

## Task 6: Admin Monitoring sub-tab (credentials + status + Sync-now)

**Files:** Create `inc/admin-forms/music.php`; Modify `inc/admin-post-actions.php`, `inc/admin-post-handler.php`, `inc/admin-flash-messages.php`, `inc/admin-tabs-data.php`, `inc/admin-page.php`, `signal-and-noise-tools.php`.

- [ ] **Step 1: Write the failing test** (append to a settings test): `sn_handle_music_save()` stores the Muso token + Spotify id/secret (skipping the masked-placeholder value, like `sn_handle_pl_save`), returns `music_saved`; `sn_handle_music_sync()` calls `sn_discography_run_sync()` and returns `music_synced`. Constant-locked token → short-circuit `music_locked`.

- [ ] **Step 2: Run → FAIL.**

- [ ] **Step 3: Implement** the handlers (mirror `sn_handle_pl_save` / `sn_handle_cf_save` for the masked-credential + constant-lock pattern + `delete_option`-on-"clear"); register `'music_save'`/`'music_sync'` in `sn_admin_post_handlers()`; add flash strings (`music_saved`/`music_synced`/`music_locked`/`music_sync_failed`); add `'music' => array( 'label' => 'Music' )` to the Monitoring `sub_tabs`; dispatch it in `admin-page.php` (`'monitoring' === $active_tab` arm) to `sn_admin_render_music_section`; require the new files in the bootstrap.

- [ ] **Step 4: Create `inc/admin-forms/music.php`** — `sn_admin_render_music_section()`: a `<form>` (nonce `sn_theme_options_nonce` + `sn_action=music_save`) with masked credential fields (show `••••` when set, like Plausible), a read-only status block (last_synced as human-diff, count, last_error) from `sn_discography_get()`, and a second `<form>` with `sn_action=music_sync` ("Sync now"). Native WP styling, `.sn-field` classes, escaped.

- [ ] **Step 5: Run tests → PASS; manual smoke** (Monitoring → Music renders, saves, "Sync now" populates the store). **Step 6: Commit** `feat(discography): Monitoring → Music admin (credentials + status + sync-now)`.

---

# PHASE 2 — THEME DISPLAY (THEMEWT, ships v9.13.0)

## Task 7: `[sn_discography]` shortcode + render

**Files:** Create `inc/discography-render.php`; Modify `functions.php`; Test `tests/discography-render.php`; modify `tests/cross-package-listeners.php`.

- [ ] **Step 1: Write the failing test** — set `$GLOBALS['__filters']['sn_discography_entries']` to 2 fixture entries (different years); call `sn_discography_shortcode()`; assert output groups by year desc, contains each title/artist/role/year, the artwork `<img>`, a lazy play trigger carrying the `spotify_id` (NOT an eager `<iframe>`), and the Muso link. Then set the filter to `array()` and assert the shortcode returns a graceful empty string/state (standalone-safe: plugin absent). Add a `cross-package-listeners.php` assertion that the theme reads `sn_discography_entries`.

- [ ] **Step 2: Run → FAIL.**

- [ ] **Step 3: Implement** `inc/discography-render.php`: register `add_shortcode('sn_discography', 'sn_discography_shortcode')`; the callback reads `apply_filters('sn_discography_entries', array())`, returns `''` when empty, else builds the brutalist timeline (year-grouped `<section>`s; each entry: `<img loading="lazy">` artwork, title, artist, role(s), a `<button class="sn-disco-play" data-spotify="<id>">` for click-to-play, Muso link). Escape everything (`esc_html`/`esc_url`/`esc_attr`).

- [ ] **Step 4: Run → PASS.** **Step 5: Commit** `feat(music): [sn_discography] timeline shortcode (standalone-safe)`.

## Task 8: Styles + lazy embed + page placement

**Files:** Modify `assets/css/components.css`; Create `assets/js/discography.js`; Modify `functions.php` (enqueue js on `/music`); manual: `/music` Page content.

- [ ] **Step 1: Add `.sn-discography` brutalist styles** (year headers as tracked mono labels, entry rows, artwork sizing, hover) to `components.css` — match existing `.sn-catalog-*` tokens already on the page.

- [ ] **Step 2: Create `assets/js/discography.js`** — on `.sn-disco-play` click, replace the button with the Spotify iframe (`https://open.spotify.com/embed/album/<id>`), so iframes mount only on demand. Enqueue only on the `/music` page.

- [ ] **Step 3: Manual placement** — edit the `/music` WP Page: replace the hand-curated embeds block in `post-content` with `[sn_discography]` (keep the header + Muso CTA sections). Document in the release notes that this is a one-time placement.

- [ ] **Step 4: Commit** `feat(music): discography timeline styles + lazy embeds + /music placement`.

---

# PHASE 3 — INTEGRATION + PAIRED RELEASE

## Task 9: Full verification + adversarial verify + release

- [ ] **Step 1: Full suites + falsified phpcs** in BOTH repos (`for f in tests/*.php; do php "$f"; done` sweep; `composer run lint`; inject one violation to confirm phpcs reports it, then revert — per `feedback_falsification_test_before_trusting_clean`).

- [ ] **Step 2: Lean Ultracode adversarial-verify workflow** on each repo's diff (mirror the v4.12.0 verify): lenses = standalone-safety/filter contract, schema correctness (Person-as-producer, valid JSON-LD), credential security + no request-time API calls, last-good-on-failure data integrity, escaping/XSS on rendered external data. Fix any real findings; re-verify.

- [ ] **Step 3: Live smoke with real credentials** — enter credentials in Monitoring → Music, "Sync now", confirm the store populates, `/music` renders the timeline, `view-source` shows valid `MusicAlbum` JSON-LD, and the [Rich Results Test](https://search.google.com/test/rich-results) parses it. Toggle: with the plugin deactivated, `/music` still renders (empty timeline, no fatal).

- [ ] **Step 4: Paired release.** Plugin: bump `signal-and-noise-tools.php` Version → `4.13.0` + CHANGELOG (`### New` Music Identity); commit `v4.13.0: …`, push `origin HEAD:main`, tag `v4.13.0`. Theme: bump `style.css` Version + `readme.txt` Stable tag → `9.13.0` + CHANGELOG; commit `v9.13.0: …`, push, tag `v9.13.0`. Order irrelevant (standalone-safe).

- [ ] **Step 5: Clean up worktrees** (`git worktree remove .claude/worktrees/music-identity`) + write the completion handoff.

---

## Self-review (coverage)

- Spec §3 components → Tasks 1–8 (store/muso/spotify/sync/schema/admin/render). ✓
- §4 schema (Person-as-producer) → Task 5. ✓ §5 display (timeline, no eager iframes) → Tasks 7–8. ✓
- §6 standalone-safe filter → Task 4 (plugin add_filter) + Task 7 (theme read + fallback) + cross-package-listeners test. ✓
- §7 cron/failure/security/testing → Task 4 (last-good + cron-via-admin_init) + Task 6 (constant-lockable masked creds) + every task's TDD. ✓
- §8 Muso↔Spotify matching risk → Phase 0 (gating research) + Task 3 (ISRC-else-fuzzy) + Task 6 (manual re-sync). ✓
- §10 versioning → Task 9 Step 4 (paired minor). ✓
- **Fixture dependency is explicit:** only Tasks 2 & 3 parse external shapes, and both are TDD against Phase-0 fixtures — no blind parsing.
