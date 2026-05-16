# Companion plugin absorption — strategic direction (Phase 6+ roadmap)

**Date:** 2026-05-16 (revised same day with WP 7.0 audit gate)
**Status:** Strategic direction; brainstorm not yet held — **gated on WP 7.0 release audit (May 20, 2026)**
**Author intent:** captured at end of the Phase 5 session, before clearing for a fresh session that will hold the actual brainstorms

## ✅ WP 7.0 audit — COMPLETED 2026-05-16

WordPress 7.0 ships on May 20, 2026. The audit was conducted on 2026-05-16 against the following sources:

- [WP 7.0 Field Guide (make.wordpress.org/core, 2026-05-14)](https://make.wordpress.org/core/2026/05/14/wordpress-7-0-field-guide/)
- [Introducing the AI Client in WordPress 7.0 (make.wordpress.org/core, 2026-03-24)](https://make.wordpress.org/core/2026/03/24/introducing-the-ai-client-in-wordpress-7-0/)
- [Proposal for merging WP AI Client into WordPress 7.0 (make.wordpress.org/core, 2026-02-03)](https://make.wordpress.org/core/2026/02/03/proposal-for-merging-wp-ai-client-into-wordpress-7-0/)
- Search results synthesized 2026-05-16 (DreamHost, GreenGeeks, Kinsta, ByteIota)

### Audit verdict per candidate

| Candidate | WP 7.0 status | Action |
|---|---|---|
| **SEO absorption** | Not addressed — WP remains hands-off | ✅ Proceed as planned; ADD AI-assisted features (see below) |
| **wps-hide-login absorption** | Not in core | ✅ Proceed as planned |
| **Deploy status widget** | Not in core (no GHA integration in WP 7.0) | ✅ Proceed; consider integrating with new Command Palette (⌘K) |
| **Outgoing API rate-limit monitor** | Not in core | ✅ Proceed as planned |
| **Personal automation webhooks** | Not in core | ✅ Proceed; consider registering as **Abilities** (see below) |
| **Cron job dashboard** | Not added in 7.0 (was ⚠️ Medium risk) | ✅ Survives the audit; proceed |
| **Content health checks** | Site Health did NOT expand into content (was ⚠️ Medium-high risk) | ✅ Survives the audit; consider AI-assisted suggestions |
| **Breadcrumbs** (was a planned sub-feature of SEO absorption) | **Native Breadcrumbs block in 7.0** | ❌ DROP from scope. Use the native block. |

### Things WP 7.0 SHIPS that reshape our strategy

The single most consequential addition is the **AI Building Blocks** subsystem:

- **`wp_ai_client_prompt('text')`** — fluent builder, returns `WP_AI_Client_Prompt_Builder`. Supports text/image/speech/video output, JSON-schema-validated responses, model preferences, system instructions, temperature/top_p/top_k/max_tokens.
- **Connectors API** — site admin configures API keys ONCE in `Settings > Connectors`. WP ships 3 official provider plugins (OpenAI, Anthropic, Google) — installed separately, not bundled in core. Plugin developers never handle credentials.
- **Abilities API** — server + client-side. Plugins register named typed actions; the AI Client can discover and invoke them. Effectively "LSP for AI." `@wordpress/core-abilities` package on the client.
- **Capability gating** — `wp_ai_client_prevent_prompt` filter controls which prompts a user can dispatch.

**Other 7.0 features worth knowing about** (not strictly affecting our scope but adoption-worthy):
- **Command Palette** (⌘K / Ctrl+K) in admin bar — extensible; plugins can register commands.
- **DataViews / DataForms** — Posts/Pages/Media list screens rebuilt. Use this pattern when building list UIs (deploy history, cron events).
- **Iframed editor**, **Custom CSS per block**, **Viewport-based block visibility**, **View Transitions**, **Modern color scheme** — editor/admin polish; no direct action needed.
- New blocks: **Headings, Breadcrumbs, Icons, Playlist**, Navigation Overlay Close — Breadcrumbs and Icons are now native; useful in templates.
- **Iframed editor** — doesn't affect our theme (we have no editor JS).
- **PHP minimum bumped to 7.4** — we already require 8.0; no action.
- **OPCache in Site Health > Info** — diagnostic-only addition.
- **`plugins_list_status_text` filter, Block Hooks moved to REST controller** — small API additions.

**Things WP 7.0 does NOT do** (audit confirmations):
- No native SEO meta tag emission, OG/Twitter cards, or schema.org JSON-LD
- No Site Health expansion into content quality
- No cron dashboard
- No login URL hiding
- No Performance Lab module graduations called out in the Field Guide
- No changes to plugin/theme update transients or upgrader_pre_install (our Phase 5 integration stays compatible)

### NEW candidates unlocked by WP 7.0 AI primitives

These are features that would have required us to build full OpenAI/Anthropic integrations ourselves before WP 7.0. With the AI Client in core, they become ~30-150 LOC each:

| New candidate | Description | Scope (LOC) |
|---|---|---|
| **AI-assisted meta descriptions** (bolts onto SEO module) | When editing a post, button to "Generate meta description with AI" → calls `wp_ai_client_prompt()` with post content as context, populates the meta_description field | ~50 |
| **AI-assisted alt-text** | On image upload, optionally auto-generate alt-text via AI (image-input → text-output, supported by 7.0's multimodal API) | ~40 |
| **AI-assisted excerpt generation** | Generate WP excerpt from post content via AI | ~30 |
| **AI-assisted OG card text** | Phase 3's OG generator gets an enhancement: use AI to generate a punchier card-friendly title rather than reusing post_title verbatim | ~50 |
| **Abilities API integration** | Register our existing actions as Abilities: `regenerate_og_card`, `purge_caches`, `get_deploy_status`, `clear_overrides`. AI Client can orchestrate them via chat-style UX | ~100 |
| **Site self-knowledge chat (RAG-lite)** | Ask the site "what posts did I publish last month?" or "regenerate the OG card for [post]" — AI Client routes via registered Abilities | ~150 |
| **AI-assisted content health** (bolts onto content health checks) | Instead of just listing "this image has no alt text," generate a suggested alt-text inline with one-click apply | ~80 |

These all DEPEND on WP 7.0 being installed AND the user having configured at least one AI Connector (Anthropic API key, OpenAI API key, etc.). Plugins gate AI features with feature detection: `is_supported_for_text_generation()`.

## Context

The Signal & Noise theme/plugin split (Phases 1-5) consolidated **theme-internal** concerns into the companion plugin. The next architectural evolution the user wants to consider: consolidate **third-party-plugin** concerns into the same companion plugin.

Goal: make `signal-and-noise-tools` the canonical home for everything except vendor-mandatory infrastructure. Fewer moving parts. Code we own end-to-end. No third-party plugin updates breaking the stack.

This is the same architectural move as Phase 1 (theme→plugin), one layer further out (third-party→plugin).

## Candidate taxonomy (live plugin inventory, 2026-05-16)

The 16 plugins currently active on `juanlentino.com`, categorized by absorption viability:

### Strong absorption candidates

These are feature-bounded, well-understood, and a leaner reimplementation has high payoff:

| Plugin | Surface | Why absorb | Risk |
|---|---|---|---|
| **autodescription** (The SEO Framework) | Meta tags, Open Graph, Twitter Card, schema.org JSON-LD, XML sitemap, breadcrumbs | Mature plugin (~30k LOC) where we use maybe 10% of the feature surface. Build a focused SEO module (~500-1000 LOC) that does what we need + nothing else. Already partially overlapping (our plugin's `og-card-generator.php` already integrates via Yoast filters — we'd switch those to our own filters). | Medium. SEO regressions = ranking impact. Test carefully against current emitted tags. |
| **wps-hide-login** | One feature: rename `/wp-login.php` to a custom URL | Tiny feature, ~30 LOC. Easy code ownership win. | Low. Worst case: locked out of login URL during transition — preventable with overlap window. |
| **plausible-analytics** | Injects Plausible tracking script in `<head>`, optional dashboard widget, optional event tracking | Already partially integrated: `inc/plausible-api.php` (stats fetching), `inc/plausible-admin.php` (admin dashboard), `inc/plausible-widget.php` (widget). The third-party plugin handles the script injection — we could absorb that ~50 LOC + the script consent UX, then remove the plugin. | Low. Plausible's tracking script is documented + stable. |

### Medium absorption candidates

Real CVE history to consider; rebuild needs careful audit:

| Plugin | Surface | Why absorb | Why hesitate |
|---|---|---|---|
| **limit-login-attempts-reloaded** | Failed-login tracking, IP-based throttling, optional lockout UI, optional GDPR mode | ~200-300 LOC to replicate. Code ownership win. Performance: our reimplementation can use object-cache-pro's atomic counters (we have that license) instead of the plugin's per-attempt DB writes. | Years of audit history in the original. Bypass attempts are a real attack vector. Build carefully + cross-reference WPScan vulnerability DB for the original plugin's CVE list to ensure we don't reintroduce known issues. |

### Weak absorption candidates / keep

These have specialized maintenance, security audits, or upstream commitments that make rebuilding net-negative:

| Plugin | Why keep |
|---|---|
| **two-factor** | TOTP/U2F crypto. Lockout-on-bug risk is real. Maintained by WordPress core contributors. Keep. |
| **contact-form-7** + **flamingo** | Mature form handling + submission storage. Years of XSS/CSRF audit. Rebuilding is high-effort for low gain. Keep. |
| **performance-lab**, **speculation-rules**, **webp-uploads**, **performant-translations** | Official WordPress.org Performance Team plugins. Landing in WP core eventually. Track upstream; don't fork. |
| **breeze** | Cloudways infrastructure. Removing breaks their stack assumptions. Keep. |
| **object-cache-pro** | Licensed vendor product (Redis-backed object cache). Replacement is downgrade to free Redis Object Cache. Keep. |
| **cloudways-site-manager**, **cloudways-wp-manager** | Cloudways vendor management. Keep. |

## Brainstorm framework

When the actual brainstorm happens (next session), evaluate each absorption candidate against:

1. **Surface area honesty.** What percentage of the existing plugin's features do we actually use? If <30%, absorption is favorable.
2. **CVE history.** Check WPScan for the original plugin. List the CVE patterns. Plan our reimplementation to avoid those classes of bugs.
3. **Migration path.** Three patterns to choose from:
   - **Hard cutover**: deactivate old plugin, activate ours, in one tagged release. Risk: regression visible during deploy window.
   - **Soft overlap**: ship ours, leave theirs active, A/B compare for a week, then deactivate. Cleaner.
   - **Side-by-side feature parity**: ship ours, gate it behind a setting, user opts in. Lowest risk, longest timeline.
4. **Maintenance budget.** Each absorbed feature is now our maintenance burden. Are we willing to chase WP-core changes and CVE patches for it?
5. **Configuration migration.** Existing plugin has user settings stored in `wp_options`. Plan: read those options on first run of our replacement, migrate, mark as migrated. Or: re-collect from user.

## User-narrowed scope (2026-05-16 revision)

The user has narrowed the absorption scope from "all strong candidates" to **two specific picks** + **net-new features that aren't currently plugins or WP-native**:

### Absorption targets (user's pick)

- **SEO** (replace `autodescription` / The SEO Framework) — strong candidate, the user uses ~10% of TSF's feature surface. Also resolves a latent bug surfaced during Phase 5 strategy review: our OG card generator hooks `wpseo_*` filters (Yoast's namespace) but the site runs TSF (`the_seo_framework_*` namespace) — meaning our Phase 3 OG cards may not actually be making it into TSF's emitted meta. Absorbing SEO unifies OG card → OG meta emission under code we own.
- **wps-hide-login** — smallest absorption candidate (~80 LOC including admin UI). Renames `/wp-login.php` to a custom URL. Easy code-ownership win.

**Explicitly DEFERRED** from the original strong-candidate list:
- `plausible-analytics` — keep the plugin. The integration overlap is small enough that absorption isn't a priority.
- `limit-login-attempts-reloaded` — CVE-history risk doesn't justify the rebuild. Keep.

### Net-new feature candidates (anything not covered by an existing plugin or WP core)

Ranked by value-to-effort, with WP 7.0 overlap risk:

| Feature | Value | Scope | WP 7.0 risk |
|---|---|---|---|
| **Deploy status widget** — admin dashboard widget showing current theme/plugin SHA, last deploy time, last 5 GHA workflow runs with status. Polls GitHub Actions API. | High (also satisfies Phase 6 click-to-update want) | ~150 LOC | Very low |
| **Outgoing API rate-limit monitor** — tracks usage against GitHub, Plausible, Cloudflare API limits. Warns when approaching. | Medium | ~100 LOC | Very low |
| **Personal automation webhooks** — custom REST endpoints fire on publish_post / update_post etc., POSTing to user-configured URLs (Discord, Slack, email, custom). | Medium-high (dev-tool catnip) | ~150 LOC | Very low |
| **Cron job dashboard** — surfaces WP-cron health. Per-event next-run + last-fired + "run now" button. | Medium | ~120 LOC | ⚠️ Medium — re-evaluate after WP 7.0 audit |
| **Content health checks** — orphaned media, missing alt text, broken internal links, posts older than N years with no recent edit. | Medium | ~200 LOC | ⚠️ Medium-high — WP Site Health may expand in 7.0; re-evaluate after audit |

## Sequencing (post-audit, revised 2026-05-16)

Audit complete; sequencing below is the post-audit confirmed plan. Phase numbers continue from the existing roadmap (Phases 1-5 shipped).

**Phase 6: Verify Phase 3 OG cards under TSF** (30 min diagnostic, no version bump)
- Visit `https://juanlentino.com/notes/<some-post>/` → view-source → check `<meta property="og:image">` URL
- If it's `https://juanlentino.com/wp-content/uploads/sn-og/<slug>.png` → no bug, Phase 3's integration is working
- If it's the site icon (`cropped-jl_logo-min.png`) → **bug confirmed**: our `wpseo_*` filter hooks aren't firing under TSF. Note for Phase 10.

**Phase 7: WP 7.0 upgrade** (after May 20, 2026)
- Upgrade WP core from current 6.x to 7.0.x
- Install + configure ONE AI provider plugin (recommend Anthropic — already used as the deploy automation source)
- Configure API key in Settings > Connectors
- Verify our Phase 5 update integration still works (it should — the filters didn't change)
- Verify our Phase 3 OG card generator still works
- No code changes to our packages required by 7.0 upgrade

**Phase 8: wps-hide-login absorption** (warmup, ~80 LOC). Smallest candidate. Validate the absorption playbook. Ships as plugin v1.5.0 (minor).

**Phase 9: Deploy status widget** (~150 LOC). Satisfies the Phase 6-from-earlier click-to-update visibility want. Adopt WP 7.0's Command Palette pattern for deploy-trigger shortcut. Ships as plugin v1.6.0 (minor).

**Phase 10: SEO absorption — foundation** (~300 LOC). Meta title + description + Open Graph + Twitter Card + canonical + robots. Wires directly to existing OG card generator (fixing the latent bug from Phase 6). Soft-overlap migration with TSF for a week, then deactivate. Ships as plugin v1.7.0 (minor).

**Phase 11: SEO absorption — schema + admin UI** (~250 LOC). JSON-LD (Article, WebSite, Person — drop BreadcrumbList since WP 7.0 ships a native Breadcrumbs block) + per-post meta box for title/description overrides. Ships as plugin v1.8.0.

**Phase 12: AI-assisted SEO additions** (~120 LOC). Add "Generate with AI" button to per-post meta description field. Add automatic alt-text generation on image upload. Both use `wp_ai_client_prompt()`. Ships as plugin v1.9.0.

**Phase 13: Final TSF cutover** (no new code, just deactivation). After 1-2 weeks of A/B observation with both plugins active. Deactivate TSF. Ships as plugin v2.0.0 (major — TSF dependency dropped).

**Phase 14: Abilities API registration** (~100 LOC). Register existing operations (regenerate_og_card, purge_caches, get_deploy_status, clear_overrides) as Abilities. Enables AI Client to orchestrate them. Ships as plugin v2.1.0 (minor).

**Phase 15+: Surviving net-new features** — Outgoing API rate-limit monitor, Personal automation webhooks, Cron job dashboard, Content health checks (consider AI-assisted version). Each its own phase. Order by user priority at the time.

**Phase 16+: AI features powered by WP 7.0** — AI excerpt generator, AI OG card text generator, Site self-knowledge chat (RAG-lite via Abilities). All depend on Phases 12+14 being in place.

That's a 10-phase roadmap (Phase 6-15) with possible extension via 16+. At one phase per week or two, ~3-5 month arc. Phases 6+7 are prerequisites that take <2 hours total of actual work.

Each phase is its own brainstorm-spec-plan-execute cycle.

## Anti-goals

- **Don't** absorb features just because they're absorbable. Each one is real maintenance debt. The bar should be "I'll actively maintain this for years and it's part of my site's identity."
- **Don't** rebuild crypto / 2FA / form-submission XSS-protection from scratch. These features have years of attack-surface knowledge baked into them.
- **Don't** absorb features that WordPress core is about to absorb (performance-lab, speculation-rules trajectory).
- **Don't** create a "swiss-army-knife plugin." If absorption goes too far, the plugin becomes its own architectural problem.

## Brainstorm preparation checklist (next session)

When the user is ready to brainstorm a specific phase:

1. **Confirm WP 7.0 audit (Phase 6) is complete.** If it isn't, do that first. The audit determines whether any of this doc's planned phases need removal.
2. **Pick the next phase** from the sequencing list above. The list is ordered by dependency + risk; deviate only with reason.
3. **For absorption phases (SEO, wps-hide-login):**
   - Read the third-party plugin's source. Identify the 5-10 functions/hooks that actually fire on this site.
   - Check WPScan / GitHub Issues / CHANGELOG for security history.
   - Mock up the replacement module: file layout, hook surface, settings UI.
   - Plan the migration: how do you cut over without a gap?
4. **For net-new feature phases (Deploy status widget, etc.):**
   - Survey existing WordPress patterns for similar UI (dashboard widgets, admin pages, settings APIs).
   - Identify external dependencies (GitHub API, Plausible API, etc.) and confirm rate-limit headroom.
   - Plan the admin UX: where in the WP admin does this surface? What's the interaction model?
5. **Versioning:** each phase is at minimum a plugin minor bump. SEO absorption final-cutover phase could be major (drops TSF as a dependency = real behavioral change).
6. After the brainstorm produces a spec, the existing GSD/superpowers flow handles the rest (writing-plans → execution → release).

## Where to pick this up

- This file: `docs/superpowers/specs/2026-05-16-plugin-absorption-roadmap.md`
- Memory entry: `feedback_plugin_absorption_strategic_direction.md` (also added this session, points back here)
- Latest handoff: `docs/superpowers/handoffs/2026-05-16-end-of-phase-5-handoff.md`

A fresh session resuming this should:
1. Read the latest handoff for project state.
2. Read this file for the strategic direction.
3. **First action:** check whether WP 7.0 has shipped + whether the audit (Phase 6) has been completed. If 7.0 is out and audit not done → run the audit, update this roadmap, then proceed. If audit done → pick the next sequenced phase.
4. Brainstorm that phase via `superpowers:brainstorming`.
