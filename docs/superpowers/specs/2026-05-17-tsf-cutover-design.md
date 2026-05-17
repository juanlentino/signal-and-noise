# Signal & Noise — TSF cutover (Phase 13) — design

**Date:** 2026-05-17
**Target releases:** theme v8.5.5 (PATCH) + plugin v2.0.0 (MAJOR)
**Status:** Approved — proceeding to implementation plan
**Roadmap reference:** [Phase 13 in plugin-absorption-roadmap.md](2026-05-16-plugin-absorption-roadmap.md) §"Post-7.0 track"
**Strategic memory:** [feedback_plugin_absorption_strategic_direction.md](/Users/juanlentino/.claude/projects/-Users-juanlentino-Projects-signal-and-noise/memory/feedback_plugin_absorption_strategic_direction.md)

## Goal

Deactivate and remove The SEO Framework (`autodescription`), shifting all SEO emission to the Signal & Noise companion plugin. Where TSF has gaps (no AI assistance, no per-post overrides, no domain-specific schema), our implementation supersedes it.

Not just parity — **parity++**: the live site after cutover emits a strictly richer signal than TSF does today.

## Why now (pre-WP-7.0)

1. **Live site currently emits duplicates** — `meta description`, `link rel=canonical`, `meta robots` all appear twice in `<head>` because TSF and our plugin both emit. Google's behavior on duplicates is undefined; treat as actively harmful.
2. **Variable isolation** — WP 7.0 ships May 20 (3 days). Doing the SEO cutover BEFORE the core upgrade means each change is independently observable.
3. **Code is ready** — Phases 10/11 (v1.7.0–v1.8.0) shipped the parallel SEO emission ~36hr ago. The "1-2 week A/B observation" the roadmap called for is already partially in-flight; another 1-2 weeks of duplicate emissions buys nothing.

## Releases

### Theme v8.5.5 (PATCH)

**Single change:** `add_theme_support('title-tag')` inside the existing `after_setup_theme` callback in [inc/setup.php](../../inc/setup.php).

**Reasoning for PATCH (not MINOR):** From the user's perspective, the page still has a `<title>` tag after this change — no new user-visible capability, no behavior shift. Pure infrastructure restoration of a capability TSF was previously providing externally. Cap headroom: 4/7 patches on 8.5.x → 5/7 after this.

**Block-theme footnote:** WordPress block themes do NOT auto-declare title-tag support. Verified against [WordPress/wp-includes/theme.php on trunk](https://raw.githubusercontent.com/WordPress/WordPress/master/wp-includes/theme.php) — no auto-declaration logic for block themes; explicit `add_theme_support()` required. The "TSF emits the title tag" reality is what's been masking this gap.

### Plugin v2.0.0 (MAJOR)

**Breaking change rationale per [CLAUDE.md](../../CLAUDE.md):** "removed/renamed public API, settings schema change without a migration, or a behavioural shift that requires user action." Deactivating TSF requires user wp-admin action; the plugin's effective contract changes (was: "we cover SEO gaps TSF doesn't"; now: "we are the SEO surface").

Six functional additions, ~250–300 LOC.

## Component design

### 3a. Title tag format — `document_title_parts` filter

**File:** new section in [`inc/seo.php`](https://github.com/juanlentino/signal-and-noise-tools/blob/main/inc/seo.php) (existing module).

**Hook:** `document_title_parts` (10, 1). Standard WP-native filter consumed by `wp_get_document_title()` → `_wp_render_title_tag()`.

**Logic:** Reuse `sn_seo_meta_for_current_view()` to resolve the page-specific title. Format: `<page title> — <site name>`. On front page, use the curated `seo_copy.home_title` setting rather than the auto-built title. Site name comes from `sn_setting('identity.site_name', get_bloginfo('name'))`.

```php
add_filter( 'document_title_parts', function( $parts ) {
    if ( function_exists( 'the_seo_framework' ) ) {
        return $parts; // TSF active — let TSF handle title
    }
    list( $title, , ) = sn_seo_meta_for_current_view();
    if ( $title ) {
        // Split our pre-built "X — Y" into title + site parts.
        $segments = explode( ' — ', $title, 2 );
        $parts['title'] = $segments[0];
        if ( isset( $segments[1] ) ) {
            $parts['site'] = $segments[1];
        }
    }
    return $parts;
}, 10, 1 );
```

### 3b. JSON-LD parity additions — extend [`inc/seo-schema.php`](https://github.com/juanlentino/signal-and-noise-tools/blob/main/inc/seo-schema.php)

Three new schema builders + extension of the `wp_head` emitter:

**`sn_schema_webpage()`** — emitted on every singular (not just post). ~30 LOC. Fields: `@type`, `@id`, `url`, `name`, `description`, `inLanguage`, `isPartOf` (WebSite ref), `breadcrumb` (BreadcrumbList ref), `potentialAction` (ReadAction).

**`sn_schema_collection_page()`** — emitted on `/notes` and home archive. ~20 LOC. Same shape as WebPage but `@type: CollectionPage`.

**`sn_schema_breadcrumb_list()`** — generated from current view's URL hierarchy. ~40 LOC. Fields: `@type`, `@id`, `itemListElement` array with `position`/`item`/`name`. Order: Home → (parent if any) → current page.

**Front-page emission rule:** Skip BreadcrumbList on front page (no useful trail).

**TSF gate on all three:** wrap the `wp_head` emitter's expanded payload in `function_exists('the_seo_framework')` check. While TSF is active, only emit Person + WebSite + Article (current behavior). When TSF is inactive, emit full payload.

**Post-WP-7.0 follow-up:** Once 7.0 ships and we add the native Breadcrumbs block to templates, remove `sn_schema_breadcrumb_list()` and rely on the native block to provide the structured data (it emits BreadcrumbList itself). This is a v2.1.0+ refactor, NOT blocking the cutover.

### 3c. Music-specific Person extensions — modify `sn_schema_person()` in [`inc/seo-schema.php`](https://github.com/juanlentino/signal-and-noise-tools/blob/main/inc/seo-schema.php#L41)

Add to the returned array:

```php
'jobTitle'   => sn_setting( 'identity.job_title', 'Music Producer' ),
'knowsAbout' => (array) sn_setting( 'identity.knows_about', array(
    'Music Production',
    'Audio Engineering',
    'Provenance',
    'Music Industry',
) ),
```

Both fields configurable via existing settings layer (no new admin UI surface needed; `identity` tab gets two new fields in a follow-up if user wants UI control).

**Skipped:** `MusicGroup`, `MusicRecording`, `MusicAlbum`. These require dedicated post types (or post meta) with releaseDate/duration/recordedAt fields. Out of scope for v2.0.0; revisit if/when individual track/album content exists.

### 3d. Sitemap 301 redirect — new file `inc/sitemap-redirect.php`

~30 LOC. Hook `init` priority 1, check `$_SERVER['REQUEST_URI']` against:
- `/sitemap.xml`
- `/sitemap_index.xml`
- `/sitemap.xsl`

Send HTTP 301 to `/wp-sitemap.xml`. Gated on `! function_exists('the_seo_framework')` so TSF's own routes serve while TSF is active.

**Preserves Google Search Console crawl continuity** — `/sitemap.xml` stays in GSC indefinitely; the 301 hands crawlers to the new location automatically.

### 3e. Last-Modified HTTP header — extend [`inc/seo.php`](https://github.com/juanlentino/signal-and-noise-tools/blob/main/inc/seo.php)

~15 LOC. Hook `template_redirect` (after `wp` query is set). On singulars:

```php
$modified = get_post_modified_time( 'D, d M Y H:i:s', true, $post );
header( 'Last-Modified: ' . $modified . ' GMT' );
```

Then honor `If-Modified-Since` header — if request includes one and the post hasn't been modified since, send `HTTP/1.1 304 Not Modified` and `exit`.

**TSF gate:** `if ( function_exists('the_seo_framework') ) return;` — TSF emits its own Last-Modified; don't duplicate.

**Crawler benefit:** Google honors `If-Modified-Since`. On posts that haven't changed, return 304 instead of full HTML — saves crawl budget. TSF does this; we should too.

### 3f. TSF gating on all NEW emissions

Pattern applied to 3a, 3b, 3d, 3e:

```php
add_action( 'wp_head', function() {
    if ( function_exists( 'the_seo_framework' ) ) {
        return;
    }
    // ... new emission logic
}, 5 );
```

**Defense-in-depth rationale:** If TSF is ever reactivated (intentionally or by accident), gates prevent our new code from re-introducing duplicates. The existing v1.4.1 `the_seo_framework_meta_generator_pools` filter stays in place permanently as belt-and-suspenders against TSF's OG/Twitter/Facebook emission.

**Existing emissions** (canonical, robots, description, OG, Twitter from v1.6.0–v1.8.0) stay **unconditional** — they're already running today, the duplicates issue is that TSF emits ALONGSIDE them. Removing TSF removes the duplication on those surfaces.

## Cutover sequence

| # | Step | Duration | What flips |
|---|---|---|---|
| 1 | Pre-flight: baseline curl + GSC note | 5 min | Nothing (data capture) |
| 2 | Ship theme v8.5.5 | 5 min | title-tag support live (TSF still emits title) |
| 3 | Ship plugin v2.0.0 | 35 min | New code live but DORMANT (gated) |
| 4 | Deactivate TSF in wp-admin | 30 sec | New code activates, TSF stops emitting |
| 5 | Submit `/wp-sitemap.xml` to GSC | 10 min | Crawl path updated |
| 6 | 24–48h verification window | passive | — |
| 7 | Delete TSF plugin | 1 min | TSF files removed |

**Critical sequence rule:** Steps 2 and 3 must complete and auto-deploy BEFORE step 4. If step 4 runs before our gated emissions are deployed, the site will be missing `<title>`, JSON-LD WebPage/BreadcrumbList, etc. during the gap.

## Rollback

**Layer 1 (instant, expected only need):** Reactivate TSF in wp-admin → Plugins. Our gates flip back on automatically. Site reverts to pre-cutover state (with the duplicate-emissions problem, but functional). Recovery: ~30 seconds.

**Layer 2 (plugin code broken):** `git revert <commit>` in plugin repo, tag `v2.0.1` with the revert, push tag, auto-deploy lands ~30s later. Plugin returns to known-good v1.16.0 emission set.

**Layer 3 (theme title-tag conflict):** Same pattern, revert theme commit, tag `v8.5.6`. Removes title-tag support; site relies on TSF (which would need to be reactivated via Layer 1) for `<title>`.

In practice Layer 1 is the only realistic rollback path.

## Verification checklist

Runnable post-cutover. Save as `scripts/verify-tsf-cutover.sh` or run inline:

```bash
#!/usr/bin/env bash
set -u
BASE="https://juanlentino.com"

pass() { echo "  ✓ $1"; }
fail() { echo "  ✗ $1" >&2; FAIL=1; }
FAIL=0

# Pull fresh HTML for each test URL
home=$(curl -sS -A "Mozilla/5.0" "$BASE/")
notes=$(curl -sS -A "Mozilla/5.0" "$BASE/notes/")
pp=$(curl -sS -A "Mozilla/5.0" "$BASE/privacy-policy/")

echo "Check 1: <title> present on homepage"
if echo "$home" | grep -qoE '<title>[^<]+</title>'; then pass "title found"; else fail "no title tag"; fi

echo "Check 2: Single canonical on homepage"
n=$(echo "$home" | grep -o 'rel="canonical"' | wc -l | tr -d ' ')
if [ "$n" = "1" ]; then pass "1 canonical"; else fail "$n canonicals"; fi

echo "Check 3: Single description on homepage"
n=$(echo "$home" | grep -o 'name="description"' | wc -l | tr -d ' ')
if [ "$n" = "1" ]; then pass "1 description"; else fail "$n descriptions"; fi

echo "Check 4: Single robots on homepage"
n=$(echo "$home" | grep -o 'name="robots"' | wc -l | tr -d ' ')
if [ "$n" = "1" ]; then pass "1 robots"; else fail "$n robots"; fi

echo "Check 5: WebPage JSON-LD on /privacy-policy/"
if echo "$pp" | grep -qoE '"@type":"WebPage"'; then pass "WebPage present"; else fail "WebPage missing"; fi

echo "Check 6: BreadcrumbList on /notes/"
if echo "$notes" | grep -qoE '"@type":"BreadcrumbList"'; then pass "BreadcrumbList present"; else fail "BreadcrumbList missing"; fi

echo "Check 7: Sitemap 301"
code=$(curl -sSI -o /dev/null -w '%{http_code}' "$BASE/sitemap.xml")
loc=$(curl -sSI "$BASE/sitemap.xml" | grep -i '^location:' | tr -d '\r')
if [ "$code" = "301" ] && echo "$loc" | grep -q "wp-sitemap.xml"; then
    pass "301 → wp-sitemap.xml"
else
    fail "got $code, location: $loc"
fi

echo "Check 8: WP core sitemap live"
code=$(curl -sSI -o /dev/null -w '%{http_code}' "$BASE/wp-sitemap.xml")
if [ "$code" = "200" ]; then pass "wp-sitemap.xml 200"; else fail "wp-sitemap.xml $code"; fi

echo "Check 9: Last-Modified header on singular"
if curl -sSI "$BASE/privacy-policy/" | grep -qi '^last-modified:'; then
    pass "Last-Modified present"
else
    fail "Last-Modified missing"
fi

echo "Check 10: Music-specific Person fields"
if echo "$home" | grep -qoE '"jobTitle":"Music Producer"'; then
    pass "jobTitle present"
else
    fail "jobTitle missing"
fi

exit $FAIL
```

**Failure modes:** Each check's hint:
- 1: title-tag support not active → check theme setup.php, reactivate TSF
- 2-4: TSF still active → deactivate TSF
- 5-6: plugin v2.0.0 emissions not running → check version + reactivation issues
- 7: rewrite rules not flushed OR sitemap-redirect.php not loaded → wp-admin Settings → Permalinks → Save (flushes rules)
- 8: WP core sitemap disabled somewhere (Cloudways WAF? .htaccess?) → investigate
- 9: Last-Modified hook not firing → check plugin activation
- 10: seo-schema.php Person not updated → check version

## Non-goals (explicit)

- ❌ Per-post-type indexability toggle UI (admin surface — separate v2.1.0 effort if needed)
- ❌ `MusicGroup` / `MusicRecording` / `MusicAlbum` schema (needs domain post types)
- ❌ AI-assisted alt-text (Phase 12 followup, post-WP-7.0)
- ❌ TSF data migration (we already read from our own meta keys; no TSF data to migrate)
- ❌ Search Console URL inspection automation (manual user action)
- ❌ A/B observation window via dual emission (already had ~36hr; current state is duplicates which is worse than either side alone)

## Versioning summary

| Repo | Bump | Reasoning |
|---|---|---|
| Theme | v8.5.4 → **v8.5.5** (PATCH) | Infrastructure addition; no user-visible behavior change (page still has `<title>`). Patch headroom: 4/7 → 5/7. |
| Plugin | v1.16.0 → **v2.0.0** (MAJOR) | TSF dependency dropped; user wp-admin action required (deactivation). Resets minor count. |

## Expected diff sizes

| File | LOC delta |
|---|---|
| Theme `inc/setup.php` | +1 |
| Theme `style.css` | +1 (version) |
| Theme `CHANGELOG.md` | +~20 |
| Plugin `inc/seo.php` | +~80 (title filter + Last-Modified + maintain existing) |
| Plugin `inc/seo-schema.php` | +~120 (WebPage, CollectionPage, BreadcrumbList, music Person) |
| Plugin `inc/sitemap-redirect.php` | +~30 (new file) |
| Plugin `signal-and-noise-tools.php` | +1 (require_once new file) +1 (version) |
| Plugin `CHANGELOG.md` | +~50 |
| Plugin `README.md` | +~10 (note TSF dropped) |
| **Total** | **~315 LOC** |

## Open questions

None. All design decisions captured above.
