# Companion plugin absorption — strategic direction (Phase 6+ roadmap)

**Date:** 2026-05-16 (revised same day with WP 7.0 audit gate)
**Status:** Strategic direction; brainstorm not yet held — **gated on WP 7.0 release audit (May 20, 2026)**
**Author intent:** captured at end of the Phase 5 session, before clearing for a fresh session that will hold the actual brainstorms

## ⚠️ Mandatory prerequisite: WP 7.0 audit (May 20, 2026)

**Do not start any absorption phase or net-new-feature phase until WP 7.0 has shipped and its feature set has been audited against this roadmap.** WordPress 7.0 ships on May 20, 2026 (four days after this doc's creation). Building a feature WordPress core absorbs natively shortly after is wasted work.

The audit consists of reading three sources and re-evaluating the candidate columns below:

1. **WP 7.0 Field Guide** at [make.wordpress.org/core](https://make.wordpress.org/core) — the canonical developer-facing changes summary. Published 2-3 weeks before each WP major release. Read the entire Field Guide; it lists every developer-facing API addition, deprecation, and behavioral change in the release.

2. **Performance Lab module graduations** — which Performance Lab modules merged into core. Check `wp-content/plugins/performance-lab/` against new core capabilities; you may be able to deactivate Performance Lab entirely if its modules all graduated. The WP Performance Team usually publishes a "Modules Graduating to Core in WP X.Y" post around the time of each release.

3. **Cron / Site Health changelogs** — specifically check for cron dashboard or content-health expansions, since those overlap two of our proposed net-new features (cron job dashboard, content health checks). These two candidates are tagged ⚠️ Medium / Medium-high risk in the table below; the audit determines whether they survive as part of the absorption roadmap.

If the audit reveals that WP 7.0 absorbs functionality we'd otherwise have built, the affected candidates are removed from this roadmap and the remaining ones are sequenced as written.

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

## Suggested sequencing (revised 2026-05-16 with WP 7.0 gate)

**Phase 6: WP 7.0 audit** (May 20+, ~1 hour) — read the three sources in the prerequisite section. Update this roadmap doc to remove any candidates that 7.0 absorbs. Output: a revised candidate matrix.

**Phase 7: Verify Phase 3 OG cards under TSF** (30 min diagnostic) — confirm whether our `wpseo_*` filter hooks actually fire under TSF. If they don't, our OG cards have been invisible since Phase 3 — which makes the SEO absorption more urgent. Quick: visit a post, view-source, check `og:image` URL. If it's the site icon (not `/sn-og/<slug>.png`), the bug is confirmed.

**Phase 8: wps-hide-login absorption** (warmup, ~80 LOC). Smallest candidate; good to validate the absorption playbook before tackling SEO. Ships as plugin v1.5.0 (minor).

**Phase 9: Deploy status widget** (~150 LOC). Satisfies the Phase 6 click-to-update want from earlier (visibility + deep-link to manual deploy trigger) without rewiring the deploy architecture. Ships as plugin v1.6.0 (minor).

**Phase 10: SEO absorption — foundation** (~300 LOC). Meta title + description + Open Graph + Twitter Card + canonical + robots. Wires directly to existing OG card generator. Migration: soft overlap with TSF for a week, then deactivate. Ships as plugin v1.7.0 (minor).

**Phase 11: SEO absorption — schema + admin UI** (~250 LOC). JSON-LD (Article, WebSite, Person, BreadcrumbList) + per-post meta box for title/description overrides. Ships as plugin v1.8.0 (minor) OR v2.0.0 (major if it deactivates TSF as the final cutover step).

**Phase 12+: Net-new features survivors** — sequence Outgoing API rate-limit monitor, Personal automation webhooks, and any of the ⚠️ items that survived the WP 7.0 audit. Each its own phase.

That's a ~6-phase roadmap (Phase 6-11 confirmed; 12+ contingent on audit). At one phase every week-or-two, this is a 2-3 month arc.

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
