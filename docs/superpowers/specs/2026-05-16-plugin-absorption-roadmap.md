# Companion plugin absorption — strategic direction (Phase 6+ roadmap)

**Date:** 2026-05-16
**Status:** Strategic direction; brainstorm not yet held
**Author intent:** captured at end of the Phase 5 session, before clearing for a fresh session that will hold the actual brainstorms

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

## Suggested sequencing (high-confidence ordering)

If we go in order of low-risk-first:

1. **Absorb `plausible-analytics`** — already partially integrated, low surface, no security exposure. Good starter.
2. **Absorb `wps-hide-login`** — ~30 LOC, well-bounded. Quick win.
3. **Phase 6 click-to-update integration** — separately queued; can run in parallel or sequence.
4. **Absorb autodescription (SEO)** — biggest single project. Bigger brainstorm, plan it carefully. Multiple sub-features (meta tags, Open Graph, schema, sitemap).
5. **Absorb `limit-login-attempts-reloaded`** — most security-sensitive. Save for last, do with careful audit.

Each absorption becomes its own phase (Phase 7, 8, 9, etc.) with full brainstorm-spec-plan-execute cycle.

## Anti-goals

- **Don't** absorb features just because they're absorbable. Each one is real maintenance debt. The bar should be "I'll actively maintain this for years and it's part of my site's identity."
- **Don't** rebuild crypto / 2FA / form-submission XSS-protection from scratch. These features have years of attack-surface knowledge baked into them.
- **Don't** absorb features that WordPress core is about to absorb (performance-lab, speculation-rules trajectory).
- **Don't** create a "swiss-army-knife plugin." If absorption goes too far, the plugin becomes its own architectural problem.

## Brainstorm preparation checklist (next session)

When the user is ready to brainstorm absorption (probably starting with one candidate, not all):

1. Pick the candidate. Recommended starter: **Plausible** (low-stakes warmup).
2. Read the third-party plugin's source. Identify the 5-10 functions/hooks that actually fire on this site.
3. Check WPScan / GitHub Issues / CHANGELOG for security history.
4. Mock up the replacement module: file layout, hook surface, settings UI.
5. Plan the migration: how do you cut over without a gap?
6. Versioning: each absorption is at minimum a plugin minor bump (new admin UI / new behavior). Possibly major if dropping a long-standing dependency.

After the brainstorm produces a spec, the existing GSD/superpowers flow handles the rest (writing-plans → execution → release).

## Where to pick this up

- This file: `docs/superpowers/specs/2026-05-16-plugin-absorption-roadmap.md`
- Memory entry: `feedback_plugin_absorption_strategic_direction.md` (also added this session, points back here)
- Latest handoff: `docs/superpowers/handoffs/2026-05-16-end-of-phase-5-handoff.md`

A fresh session resuming this should:
1. Read the latest handoff for project state.
2. Read this file for the strategic direction.
3. Pick a candidate from "Strong absorption candidates" above.
4. Brainstorm that single candidate via `superpowers:brainstorming`.
