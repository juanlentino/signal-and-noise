# WordPress 7.0 — Launch-Day Checklist

**Release date: May 20, 2026.** This document is the run-list for what to verify, what to ship, and what to evaluate for adoption when WP 7.0 actually drops on the live install. Pair it with the scheduled reminder set to fire on May 21 — that reminder loads this doc and walks through it.

If you're reading this *before* May 20, 2026: don't run the verification yet — the live install hasn't been upgraded. If you're reading this *after* May 20, 2026: skim the "What's already done" section first, then start at "Verification (Phase 2)."

---

## What's already done (Phase 1 — pre-launch)

The original WP 7.0 plan from earlier in this project (see [docs/WP-API-MAP.md](WP-API-MAP.md) for the full adoption matrix) called for two phases of work:

- **Phase 1 — pre-launch stabilization** (eliminate sync HTTP on admin render path, ship the REST surface, extract Block Patterns, harden security gaps). **All complete.** Versions v7.2.6 → v7.5.6 inclusive.
- **Phase 2 — launch-day verification** (this doc).
- **Phase 3 — opportunistic adoption** (post-stable, evaluated through the [docs/VOICE-GUIDE.md](VOICE-GUIDE.md) and [docs/WP-API-MAP.md](WP-API-MAP.md) anchors).

**Concretely shipped in Phase 1:**

| Concern | Solved by | Version |
|---|---|---|
| Plausible widgets blocking dashboard render | SWR via WP-Cron | v7.2.6 |
| Template self-heal blocking admin_init | SWR via WP-Cron | v7.2.7 |
| Updater + S&N options page blocking on GitHub | SWR via WP-Cron | v7.3.1 |
| Defense-in-depth security gaps (R1 audit) | Hardening pass | v7.3.0 |
| `signal-noise/v1` REST surface (8 endpoints) | New `inc/rest-api.php` | v7.4.0 |
| Block Patterns — first three extracted | New `patterns/` directory | v7.5.0 |
| IA confusion (Contact / Work With Me) | Renamed *Book a Call* | v7.5.1 |
| Editorial canonical-form propagation | Audit-driven cleanups | v7.5.2 |
| Apple-coded voice register codified | New voice guide | v7.5.5 + docs |
| Voice rewrites for Operations / Artist Dev / Resume strip | Audit §G closure | v7.5.6 |

So when 7.0 lands, the codebase is already at a known-good state. The verification is *"did we miss anything"*, not *"now we have to build the recovery."*

---

## Verification (Phase 2) — do this on May 21

### Pre-flight (before clicking Update)

- [ ] **Confirm release status.** Open https://wordpress.org/news/category/releases/ and confirm WP 7.0 is officially shipped. If a `7.0.1` patch already shipped within the first 24 hours, wait for it — that's the "first weekend bug fix" release WP almost always issues. Click Update only after the dust has settled.
- [ ] **Check error log on the live install** via Cloudways → Server Management → Logs. Note any *current* `_doing_it_wrong` notices or PHP warnings so you can tell which are pre-existing vs. introduced by the upgrade.
- [ ] **Take a Cloudways application backup** before the WP core upgrade. WP core upgrades are usually safe but a 2-minute snapshot is cheap insurance for a major version bump.
- [ ] **Verify SN cache state** by hitting [`/wp-admin/themes.php?page=sn-theme-options`](https://juanlentino.com/wp-admin/themes.php?page=sn-theme-options) — the *Latest on GitHub* row should show a real SHA, not *"refreshing in background"*. If it's still cold, click *Check Now* and reload to populate the SWR cache.

### Update WP core

- [ ] **Click Update on Dashboard → Updates** for WP core. Let it run. The upgrade should complete in under 60 seconds — Cloudways has SSD-backed storage and the PHP 8 runtime is fast.
- [ ] **Verify the version number** in `Dashboard → Updates` reads `WordPress 7.0` (or `7.0.1` if a patch landed first).

### Smoke test surfaces — every route once

For each route below, navigate to it and verify it renders correctly. The **acceptance criterion is "renders without errors"**, not "looks identical" — minor visual shifts are expected when block-editor internals change.

- [ ] **Dashboard widgets** at [`/wp-admin/index.php`](https://juanlentino.com/wp-admin/index.php). The four Plausible widgets should render instantly with their data (or *"refreshing in background"* on a cold cache). No PHP fatals. No empty cards.
- [ ] **Site Editor** at `/wp-admin/site-editor.php`. Navigate into Templates → Page (About). Confirm the FSE block markup loads, edits save, and no migration warnings appear.
- [ ] **Block inserter — patterns**. In the Site Editor, open the inserter and search for *Signal & Noise* under Patterns. Confirm the three patterns (`hero-dossier`, `cta-closing`, `section-constrained`) appear and insert correctly.
- [ ] **Front-page**. Visit https://juanlentino.com/ and confirm the hero, the navigation (with new *Book a Call* label), the footer template part all render.
- [ ] **Navigation overlay (mobile)**. Resize browser below ~768px or use device emulation. The `<!-- wp:navigation overlayMenu:"mobile" -->` from `parts/header.html` should now use 7.0's stable navigation overlay implementation (it was experimental in 6.x). Verify the hamburger toggle works and the overlay opens/closes.
- [ ] **Each page template** — visit each in turn and confirm rendering: `/about`, `/services`, `/music`, `/resume`, `/notes`, `/contact`, `/work-with-me`, `/provenance`. Pay attention to:
  - Pillar essay cards on `/notes`
  - Catalog block on `/music`
  - PDF viewer on `/resume`
  - Cal.com booking widget on `/work-with-me`
- [ ] **404 page** — visit a deliberately-broken URL like https://juanlentino.com/this-does-not-exist and confirm the brand 404 renders.
- [ ] **S&N admin options page** at `/wp-admin/themes.php?page=sn-theme-options`. Run each maintenance action once: *Purge All Caches*, *Heal Templates Now*, *Check for Updates*. Confirm all three succeed and surface the expected notice.
- [ ] **REST endpoints**. With your Application Password, curl one read endpoint and one write endpoint:
  ```bash
  # Read — should return cached Plausible data
  curl -u 'username:app-password' https://juanlentino.com/wp-json/signal-noise/v1/plausible/realtime

  # Write — should return ok=true and clear caches
  curl -u 'username:app-password' -X POST https://juanlentino.com/wp-json/signal-noise/v1/purge-cache
  ```

### Post-update sanity checks

- [ ] **Re-check error log** on Cloudways. Compare against pre-flight notes. New errors? Investigate. New `_doing_it_wrong` notices? Identify which deprecated function fired and queue a fix as v7.5.7 (or v8.0.0 if it's structural).
- [ ] **Check the WP Site Health screen** at `/wp-admin/site-health.php`. Confirm no critical issues. Note any new recommendations introduced by 7.0.
- [ ] **Bump `Tested up to: 7.0`** in [style.css:9](../style.css). One-line change. Documentation only — the project's [CLAUDE.md](../CLAUDE.md) versioning rules say documentation-only changes don't bump version on their own, so this lands as part of the next change that does (or as a `docs:` commit).
- [ ] **Check the Plausible API status** by clicking *Run Test* on Appearance → S&N → Plausible. The widgets depend on Plausible's API being reachable; a confirmation here proves the SWR layer is working post-upgrade.

### If anything broke

- [ ] **Front-end visual regression?** Likely a block-CSS change in 7.0. Check [docs/WP-API-MAP.md](WP-API-MAP.md) row "Roster of design tools per block (7.0)" — that dev-note documents which blocks gained/lost design controls. Spot-fix the affected template, ship as a patch.
- [ ] **PHP fatal or `_doing_it_wrong` notice?** Triage by file. Most likely candidate: any `inc/` module touching the Site Editor, REST API serializers, or transient internals. The SWR architecture should be insulated (it uses only stable core APIs).
- [ ] **Plugin conflict?** Disable each plugin in turn (Plausible WP plugin, Breeze, etc.) to bisect. None of our `inc/` modules depend on plugin internals; the only interlocks are the Plausible plugin's stored settings (read-only on our side) and Breeze's cache-purge hooks.
- [ ] **Roll back via Cloudways application backup** if the situation requires it. Keep the WP 6.9 snapshot for at least a week post-upgrade.

---

## Adoption opportunities (Phase 3) — evaluate, don't auto-adopt

These are the WP 7.0 features the [docs/WP-API-MAP.md](WP-API-MAP.md) audit scored as adoption candidates. **None are required.** Some explicitly recommended *skip* — listed here for completeness so they're not accidentally re-evaluated next cycle.

### Should evaluate (post-stable)

| Feature | Status | Decision needed |
|---|---|---|
| **Block Bindings** for `[sn_reading_time]` and `[current_year]` shortcodes | Top R2 recommendation, skipped in Phase 1 | Replace shortcodes with bindings sources? Surface affected: any post that uses `[sn_reading_time]`. Editor-visible values are a real upgrade. |
| **Style Variations** under `theme/styles/` | XS effort, low risk | Ship `monolith.json` (drop accent red) and/or `inverted.json` (white-on-black)? Trivially cheap; visible in Site Editor → Styles browser. |
| **Custom template-part areas** via `default_wp_template_part_areas` filter | XS effort, low risk | Add `provenance-strip` or `brand-mark` area? Currently only default header/footer used. |
| **Block Visibility (viewport-based)** | Editor-only feature | Document for the maintainer that this exists; useful for brutalist mobile/desktop differences without writing CSS. No theme code needed. |
| **WP-CLI custom commands** wrapping the existing REST endpoints | S effort, low risk | Worth shipping `wp signal-noise purge-cache | heal-templates | refresh-plausible | check-updates`? Same callbacks as REST routes, scriptable from the command line on Cloudways SSH. |

### Should skip (already evaluated, reasoning preserved)

| Feature | Skip reason |
|---|---|
| **Abilities API** (PHP + JS) | Designed for distributed plugins exposing capabilities to external agents. Single-author personal site has no agents and nothing to expose. Revisit only if a personal AI workflow needs to drive the site programmatically. |
| **AI Client API** | `wp_ai_client_prompt()` is for content/feature plugins. The theme has no UI surface that calls AI. Auto-tagging Notes could be a future fit but isn't now. |
| **Connectors API** | Site-admin surface for AI-provider authentication, not a theme one. The Cloudflare/Plausible/GitHub integrations live in `inc/` because they're theme behaviour, not user-configurable connections. |
| **Interactivity API** | Forces a JS build pipeline (`wp-scripts` + `node_modules`). The no-build invariant is load-bearing for the GitHub-driven self-updater. Skip until a feature genuinely requires reactive UI. |
| **Custom sync providers** (real-time collaboration) | Single author. Multi-user collab is a non-goal. |
| **Pattern Overrides for custom blocks** | Theme has no custom blocks. Revisit if v8 introduces any. |

### Should later (queued for separate cycle)

- **Bulk `__()` / `esc_html__()` wrapping of admin-facing strings** ([R1 audit](WP-STANDARDS-AUDIT.md) M8 + L1). Mechanical pass touching 7 files. Not blocked by 7.0; just hasn't been prioritized yet.
- **Resume cred-strip vs prose duplication** — closed in v7.5.6 by replacing strip with discipline framing.

---

## References (consult during verification, not before)

- **WP 7.0 release notes** — https://wordpress.org/news/2026/05/wordpress-7-0-released/ *(URL pattern; will exist after May 20)*
- **Field Guide** — https://make.wordpress.org/core/wordpress-7-0-field-guide/ *(release-day URL pattern)*
- **Dev-notes index** — https://make.wordpress.org/core/tag/dev-notes+7-0/
- **`docs/WP-API-MAP.md`** — full adoption matrix with per-API scoring
- **`docs/VOICE-GUIDE.md`** — canonical voice anchor for any content edits introduced during the upgrade
- **`docs/WP-STANDARDS-AUDIT.md`** — R1 audit findings still relevant for re-checking against any 7.0 deprecations

---

## Refresh cadence

- **After May 21, 2026 verification completes**: rename or archive this doc. Don't leave it as-is — a stale "Launch-Day Checklist" for a release that already happened becomes confusing.
- **Suggested archive name**: `docs/archive/WP-7.0-RUNDOWN.md` with the verification results filled in (what passed, what failed, what was deferred).
- **For WP 7.1 / 7.2 / etc.**: copy this doc to `docs/WP-7.X-CHECKLIST.md` and adapt — the structure is reusable.
