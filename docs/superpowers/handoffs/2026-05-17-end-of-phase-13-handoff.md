# Handoff — End of Phase 13 (TSF cutover complete)

**Date:** 2026-05-17
**Session arc:** WP 7.0 audit → Phase 13 TSF cutover → post-cutover QA → hotfixes → verification

**Last shipped this turn:**
- **Theme v8.5.5** ([commit 2860957](https://github.com/juanlentino/signal-and-noise/commit/2860957), tag `v8.5.5`) — `add_theme_support('title-tag')`
- **Plugin v2.0.0** ([commit 9965ac6](https://github.com/juanlentino/signal-and-noise-tools/commit/9965ac6), tag `v2.0.0`) — Phase 13 cutover; TSF dependency dropped
- **Plugin v2.0.1** ([commit 5c538ce](https://github.com/juanlentino/signal-and-noise-tools/commit/5c538ce), tag `v2.0.1`) — Identity UI for new Person fields + dock duplication fix + RSS dashboard restored
- **Plugin v2.0.2** ([commit 5a0c724](https://github.com/juanlentino/signal-and-noise-tools/commit/5a0c724), tag `v2.0.2`) — title format hotfix + canonical de-duplication
- **Plugin v2.0.3** ([commit 0718748](https://github.com/juanlentino/signal-and-noise-tools/commit/0718748), tag `v2.0.3`) — deploy workflow hardening (same code as v2.0.2 + `git reset --hard` before checkout)

All live on origin + on the live site (juanlentino.com). All 10 verification checks passed against the live site.

## Session narrative

The user opened with a question: does the theme need WP 7.0 scaffolding like the plugin (v1.16.0 had just shipped). Audit found it didn't (theme has no AI surface). User then escalated scope:

1. **"Should I delete TSF?"** → audit showed live site was emitting **duplicate** description/canonical/robots tags (TSF + our plugin both firing) since v1.7.0+v1.8.0's parallel emission code shipped.
2. **"Our plugin should do all TSF does."** → confirmed feature-parity assumption.
3. **"If we can make what TSF does better, let's do it."** → parity++ scope: cutover essentials + music-specific Person extensions + Last-Modified header + WebPage/BreadcrumbList JSON-LD that TSF was emitting and we were skipping.
4. **"Go. Do what you must."** → executed in single session.

Then post-cutover the user surfaced two visible bugs (RSS dashboard missing, dock duplicated), triggering a comprehensive QA pass that became v2.0.1. Verification after TSF deletion caught two more (title appending tagline, canonical dupe), shipped as v2.0.2. Manual deploy of v2.0.2 then failed on a server git-dirty-tree issue, requiring v2.0.3 to ship a hardened deploy workflow. Final 10-check verification: all green.

Five plugin releases + one theme release in one session. Notable for being orderly — each release had a specific surface, atomic commits, full CHANGELOG entries.

## What's running now (post-cutover)

**The SEO Framework is GONE.** The user deleted (not just deactivated) the plugin during the cutover step.

**SN plugin v2.0.3 owns the entire SEO surface:**
- `<title>` via `document_title_parts` filter cooperating with WP-native title-tag support (theme v8.5.5)
- `<link rel="canonical">` (with explicit `remove_action( 'wp_head', 'rel_canonical' )` to suppress WP core's default)
- `<meta name="description">` with per-post `_sn_meta_description` override fallback chain
- `<meta name="robots">` with per-post noindex / noarchive / noimageindex flags
- Full OG + Twitter meta (article:published_time/modified_time, og:image:width/height, twitter:site/creator)
- JSON-LD @graph with Person (music-specific `jobTitle` + `knowsAbout`) + WebSite + Article (posts) + WebPage (singulars) + CollectionPage (/notes) + BreadcrumbList
- `/sitemap.xml` → 301 → `/wp-sitemap.xml` (WP core sitemap)
- Last-Modified header + If-Modified-Since 304 on singulars

All NEW emissions in v2.0.0 gated on `! function_exists( 'the_seo_framework' )` — defense-in-depth for rollback path.

**Theme v8.5.5** has one functional change vs v8.5.4: `add_theme_support('title-tag')`. Plus the WP 7.0 schema URL bump from earlier this session.

## Verification

Final 10-check pass against live site:

| # | Check | Result |
|---|---|---|
| 1 | `<title>` present | `Juan Lentino — Music producer & creative strategist` |
| 2 | Single canonical | 1 |
| 3 | Single description | 1 |
| 4 | Single robots | 1 |
| 5 | WebPage JSON-LD on /privacy-policy/ | present |
| 6 | BreadcrumbList on /notes/ | present |
| 7 | `/sitemap.xml` → 301 → `/wp-sitemap.xml` | passing |
| 8 | `/wp-sitemap.xml` HTTP 200 | passing |
| 9 | Last-Modified header on singular | present |
| 10 | Music-specific Person fields | `"jobTitle":"Music Producer"` |

## Versioning state

| Repo | Current | Cap headroom |
|---|---|---|
| Theme (`signal-and-noise`) | **v8.5.5** | Patch 5/7; minor 5/5 — **next minor rolls to v9.0.0** |
| Plugin (`signal-and-noise-tools`) | **v2.0.3** | Patch 3/7 on 2.0.x; minor 0/5 |

## Outstanding items

### 🟡 Operational hygiene (non-blocking)

1. **Replace `WP_DEPLOY_APP_PASSWORD` GHA secret.** The Cloudflare purge step at the end of the plugin's manual deploy workflow uses this secret. The currently-stored value is the same Application Password the user revoked earlier in the session. Future manual deploys will git-checkout successfully but 401 at the CF purge step (visible in the v2.0.3 deploy log).

   **Fix:**
   ```
   wp-admin → Users → Edit Juan Lentino → Application Passwords → Add New
                                                              → Name: "gha-deploy"
                                                              → Copy generated password
   gh secret set WP_DEPLOY_APP_PASSWORD --repo juanlentino/signal-and-noise-tools
   # Paste the generated password at the prompt (input hidden)
   ```

   Not blocking because WP-UI Updates is the canonical path and doesn't use this secret. Only matters for `gh workflow run deploy.yml` manual deploys.

2. **Submit `/wp-sitemap.xml` to Google Search Console.** The `/sitemap.xml` 301 preserves crawl continuity, but explicitly registering the new URL is good hygiene. wp-admin action.

### 🟢 Post-WP-7.0 follow-ups (May 20+)

3. **Phase 7 launch-day run-book** — upgrade WP core to 7.0, install `ai-provider-for-anthropic` plugin, configure API key. Per [docs/WP-7.0-AI-API-MAP.md](../../WP-7.0-AI-API-MAP.md).

4. **Verify v1.16.0 AI button** appears on per-post SN meta box after Phase 7.

5. **Replace `sn_schema_breadcrumb_list()` with native Breadcrumbs block** in theme templates. WP 7.0 ships a native Breadcrumbs block that emits BreadcrumbList structured data itself, making our manual emission redundant.

6. **Phase 14: Abilities API registration** — once Phase 7 verified, register `sn_regenerate_og_card`, `sn_purge_all_caches`, `sn_get_deploy_status`, `sn_clear_template_overrides` as Abilities. Plugin v2.1.0 target.

### 🟢 Independent quality work (separate sessions)

7. WCAG audit (theme; possible minor that rolls to v9.0.0).
8. `critical.css` size review (504 LOC).
9. `useRootPaddingAwareAlignments` evaluation (theme.json).
10. Phase 15b/c — webhooks, cron dashboard, content health.

## Key learnings captured this turn (new + updated memories)

| Memory | What it captures |
|---|---|
| [`project_architecture.md`](.../memory/project_architecture.md) | **Major rewrite** — reflects post-cutover state (TSF gone, plugin owns SEO surface, theme v8.5.5/plugin v2.0.3) |
| [`feedback_plugin_deploy_is_manual.md`](.../memory/feedback_plugin_deploy_is_manual.md) | **Updated** — added note about v2.0.3 workflow hardening |
| [`feedback_document_title_parts_replaces_array.md`](.../memory/feedback_document_title_parts_replaces_array.md) | **New** — WP joins all non-empty $parts keys; whole-array replacement vs augmentation |
| [`feedback_remove_rel_canonical_when_owning_canonical.md`](.../memory/feedback_remove_rel_canonical_when_owning_canonical.md) | **New** — WP core rel_canonical fires on singular views including front page; must explicitly remove when plugin owns canonical |
| [`feedback_never_inline_live_secrets_in_plan_docs.md`](.../memory/feedback_never_inline_live_secrets_in_plan_docs.md) | (from earlier this session) Plan docs use placeholders, not literal tokens |

## How this session validates the workflow

A few patterns proved themselves:

1. **Spec → plan → execute discipline scaled.** The session went brainstorm → spec → writing-plans → executing-plans across 5 releases. The pattern held even when scope expanded mid-execution (QA pass for v2.0.1; hotfix for v2.0.2; workflow fix for v2.0.3).

2. **Verification scripts catch what manual review misses.** The 10-check verification surfaced the title-tagline-append bug AND the duplicate canonical — both invisible to "read the code" review because they emerged from WP-core defaults that TSF was previously suppressing. Without the script, those would have shipped silently.

3. **Defense-in-depth gating is cheap and saves rollback work.** All v2.0.0 emissions gate on `! function_exists( 'the_seo_framework' )`. Rollback became a single click (reactivate TSF) instead of a code revert.

4. **CLAUDE.md drift bites.** The project's CLAUDE.md still said "plugin auto-deploys on tag push" — false since v1.10.1. Caught mid-session when the manual deploy failed. Updated in this turn.

## Outstanding security item

✅ **CLOSED 2026-05-17 (earlier this session):** The leaked WP Application Password from `docs/superpowers/plans/2026-05-16-settings-layer-v1.8.0-plan.md` was revoked by the user and redacted from the plan doc.

The new outstanding security-adjacent item is the dead `WP_DEPLOY_APP_PASSWORD` GHA secret (item 1 above) — but it's a non-functional credential, not a leak. It just needs to be replaced with a fresh, valid password.
