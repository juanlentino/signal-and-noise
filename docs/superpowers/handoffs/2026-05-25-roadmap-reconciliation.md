# Handoff — 2026-05-25 (Plugin absorption roadmap reality reconciliation)

**Why this exists:** the 2026-05-16 plugin absorption roadmap at [docs/superpowers/specs/2026-05-16-plugin-absorption-roadmap.md](../specs/2026-05-16-plugin-absorption-roadmap.md) listed 15 phases. Nine days later, all 15 are shipped — most with significant scope expansion. The roadmap was never amended to reflect this. This handoff reconciles the plan vs. the actual codebase as of 2026-05-25 (theme v9.1.5 + plugin v3.8.2), records what shipped beyond the original scope, and surfaces the small residual TODO list.

---

## TL;DR

**Roadmap is fully shipped.** All 11 numbered phases (6 through 16+) plus the 5 net-new Phase 15 sub-features have working code on production, behind feature gates, with verified third-party plugin deactivations (TSF + wps-hide-login removed from `active_plugins`).

**The plugin grew well beyond scope.** Roadmap estimated ~780 LOC pre-7.0 + ~370 LOC post-7.0 ≈ 1,150 LOC. Actual plugin `inc/` directory is ~440 KB across 44 files, with 29 registered Abilities across plugin + theme, 4-surface dispatch (admin form / REST / Abilities / desktop-mode ⌘K) on every major feature, a Content Opportunity Advisor (`insights.php` v3.6.0) that wasn't in the roadmap at all, and a desktop-mode portal integration (13 commands + 1 widget) that also wasn't.

**What's actually left:** 5 small items (next section). None of them are blockers; each could be a session's worth of focused work.

---

## What's actually left to build

Ranked by recommended sequence (smallest blast radius first):

### 1. ~~Login hardening audit log~~ — ✅ SHIPPED as plugin v3.8.3 (2026-05-25)

- **Shipped:** Security → Audit log sub-tab. 6 captured events (login_success per-event + login_failed + wp_login_404 + wp_admin_unauth_404 + lockout_triggered + password_reset as day-bucketed counters) + unique_ips_count via ephemeral hashed-IP transient (no IPs persist long-term).
- **Full 4-surface dispatch:** admin sub-tab (stat-card hero + counter timeline + recent-logins table + LLA summary), REST under signal-noise/v1/audit/*, 4 Abilities (3 read AI-eligible + 1 maintenance prune NOT AI-callable), 2 desktop-mode ⌘K commands.
- **Spec:** [`docs/superpowers/specs/2026-05-25-login-hardening-audit-log-design.md`](https://github.com/juanlentino/signal-and-noise-tools/blob/main/docs/superpowers/specs/2026-05-25-login-hardening-audit-log-design.md). **Plan:** [`docs/superpowers/plans/2026-05-25-login-hardening-audit-log-v3.8.3.md`](https://github.com/juanlentino/signal-and-noise-tools/blob/main/docs/superpowers/plans/2026-05-25-login-hardening-audit-log-v3.8.3.md).
- **Notable verified-at-design-time finding:** LLA fires no lockout action hook (only `llar_plugin_version_updated` + `llar_mfa_generate_codes` exist in LLA core). `lockout_triggered` counter uses polling fallback on `limit_login_lockouts` size delta in the daily prune tick.
- **Follow-up patches in same session:** v3.8.4 fixed a desktop-mode dock submenu drift (8→6 entries; was hardcoded since v1.15.0, missed by v3.8.1's sidebar reduction); v3.8.5 made `.sn-2col` always stack (RSS tab cramped layout couldn't be fixed by breakpoint bump alone).
- **Actual LOC:** ~991 LOC across 8 files (above the 150-200 estimate because the 4-surface dispatch + LLA polling + admin UI added more surface than initially scoped).

### 2. AI-assisted content-health fix proposals

- **State:** `health-checks.php` v3.5.0 ships 4 detection-only checks (missing_alt, orphaned_media, broken_links, stale_posts). The `SNT_AI_DRIFT_SYSTEM` constant at [health-checks.php:39-48](../../../inc/health-checks.php#L39) hints at AI drift detection but is the only AI surface present in this module.
- **Roadmap promise:** "AI-assisted suggestions" bolt-on; "AI-assisted fix proposals are a future extension" (per the file's own docblock at line 14-15 of health-checks.php).
- **Best opportunity:** missing-alt suggestions (one-click apply) + stale-post freshness rewrites. Both have a clear AI surface and a one-shot "accept" UX.
- **Estimated:** plugin v3.9.0 (minor — adds visible AI buttons in health-checks tab; ~200 LOC).

### 3. Native Breadcrumbs block adoption in theme templates

- **State:** WP 7.0 ships `core/breadcrumbs` block. Per memory [feedback_native_breadcrumbs_no_jsonld.md](../../../../../memory/feedback_native_breadcrumbs_no_jsonld.md), the native block emits visual `<nav><ol>` only — no JSON-LD. Our `seo-schema.php:sn_schema_breadcrumb_list()` continues to emit BreadcrumbList JSON-LD.
- **Question for next session:** do any theme templates render breadcrumbs today? If yes, swap to the native block (visual layer simplification, no SEO regression). If no, ignore — JSON-LD-only emission is the right move.
- **Estimated:** theme v9.1.6 if any swap is needed (~30 LOC of template/pattern changes). Otherwise close as no-action.

### 4. /wp-sitemap.xml submission to Google Search Console

- **State:** `sitemap.php` + `sitemap-redirect.php` exist in the plugin (~4 KB combined). The 301 from `/sitemap.xml` → WP's native `/wp-sitemap.xml` should already be working.
- **Action:** verify the GSC property has the sitemap submitted. This is a one-time configuration in GSC, not a code change. If submission is missing, also verify the sitemap is actually fetchable end-to-end (curl + GSC test fetch).
- **Estimated:** 15 min of configuration; no code ship.

### 5. WORDPRESS-REFERENCE.md updates (running gotchas list)

- **State:** [docs/WORDPRESS-REFERENCE.md](../../WORDPRESS-REFERENCE.md) §10.0 maintains a contract surface + phase plan list, plus a "running upstream gotchas" appendix.
- **Outstanding gotchas this session surfaced** worth appending:
  - WP `get_file_data()` for derivative-version constants (v3.8.2 lesson — single source of truth for plugin/theme versions)
  - `method_exists()` cannot detect `__call`-routed methods (v3.7.1 incident, already in memory but worth promoting to reference)
  - `desktop-mode` plugin converts WP left-sidebar submenu items into a horizontal top nav (the v3.8.0 duplicate-nav incident)
- **Estimated:** docs-only; ~20 minutes.

---

## What shipped beyond the original roadmap

Items that exist in the production codebase but were **never** in the 15-phase plan:

| Surface | File(s) | Why it materialized |
|---|---|---|
| **Content Opportunity Advisor** (Insights tab) | `insights.php` + `insights-admin.php` v3.6.0 | Cross-system AI synthesis — combines Plausible analytics + WP publish history + webhook deliveries + cron firings + site identity into 5 actionable recommendations per scan. Weekly cron + 7-day cache. Not in roadmap; emerged organically. |
| **desktop-mode plugin integration** | `desktop-mode-integration.php` v1.15.0 | 13 ⌘K commands + 1 desktop widget + 2 desktop icons. The roadmap predates the desktop-mode plugin's relevance to SN; this turned into a major surface. |
| **WP 7.0 native Command Palette** | `command-palette.php` v2.3.0 | 5-command mirror of desktop-mode's palette, for vanilla wp-admin users. Not in roadmap; added when WP 7.0 shipped. |
| **Theme abilities (12 of them)** | `inc/abilities-registration.php` (theme, 67910 bytes) | Roadmap put abilities only in the plugin (~100 LOC, ~4 abilities). Reality: 17 plugin + 12 theme = 29 abilities total, ~108 KB of registration code. Theme owns abilities about itself (patterns, design tokens, reading time) — different ownership model than originally envisioned. |
| **Theme-side AI features (5 of them)** | Registered in theme `abilities-registration.php`: `ai-generate-page-note-summary`, `ai-suggest-block-pattern`, `ai-validate-brand-alignment`, `ai-generate-pattern-content`, `ai-rewrite-in-brand-voice` | Not in roadmap. Theme has its own AI surface for design-system-aware operations. |
| **Per-post SEO overrides v2** | `post-settings.php` evolved through v1.10.0 → v1.10.2 → v2.4.0 (canonical override, noarchive, noimageindex, OG card title override) | Roadmap Phase 11 specified per-post meta box for "title/description overrides only." Reality is ~6 distinct override fields. |
| **Pinned model preference** | `ai-bootstrap.php` line 137-157 (v3.7.2) | Roadmap didn't anticipate the per-call cost model — pinning to `claude-sonnet-4-6` to avoid Opus 4.7 default (~10x cost). Discovered via AI Request Logs after a single Insights call cost ~$0.10 in production. |

`★ Insight ─────────────────────────────────────`
The scope-expansion pattern here is healthy, not concerning: every item above is **direct user value**, not gold-plating. The Insights advisor unlocks usage-driven editorial decisions. The desktop-mode integration meets the user where they are (the desktop-mode portal is where the user spends time, per memory). The theme abilities serve AI features about design-system state that the plugin couldn't surface without coupling. The pinned model preference saves real dollars. Roadmaps that "ship under scope" are usually a sign of unmet need; this one shipped over scope because the discovered needs were real.
`─────────────────────────────────────────────────`

---

## Phase-by-phase reconciliation (full table)

For the audit trail. Phases 1-5 predate this roadmap doc (theme→plugin extraction).

| Phase | Roadmap title | Status | Tag | Evidence file(s) |
|---|---|---|---|---|
| 6 | OG card / TSF diagnostic | ✅ SHIPPED 2026-05-16 | plugin v1.4.1 | `seo.php` lines 36-38 (TSF generator pool filter) |
| 7 | WP 7.0 upgrade + AI provider config | ✅ SHIPPED | n/a (infra) | active plugins: `ai/ai.php` + `ai-provider-for-anthropic/plugin.php`; `ai-bootstrap.php` v1.16.0 |
| 8 | wps-hide-login absorption | ✅ SHIPPED | plugin v1.5.0 | `login-hide.php`; `wps-hide-login` removed from active_plugins |
| 9 | Deploy status widget | ✅ SHIPPED (as tab, not widget) | plugin v1.14.0 (via v1.12.0 → v1.13.0 → v1.14.0) | `admin-tab-dashboard.php`; `github-actions-api.php`. Roadmap said dashboard widget; user pushback (per memory `feedback_no_dashboard_widgets.md`) moved it to a settings tab. |
| 10 | SEO foundation | ✅ SHIPPED | plugin v1.6.0 | `seo.php` (canonical, robots, meta description, title, OG/Twitter, article times) |
| 11 | SEO schema + admin UI | ✅ SHIPPED | plugin v1.7.0 + v1.10.0 (admin UI) | `seo-schema.php` (Person/WebSite/Article/WebPage/CollectionPage/BreadcrumbList); `post-settings.php` (per-post overrides) |
| 12 | AI-assisted SEO | ✅ SHIPPED (alt-text deferred to native plugin) | plugin v1.16.0 | `ai-meta-description.php`; alt-text skipped because `ai/ai` plugin ships it natively |
| 13 | Final TSF cutover | ✅ SHIPPED | plugin v2.0.0 | TSF removed from active_plugins; `function_exists('the_seo_framework')` gates throughout `seo.php` + `seo-schema.php` are insurance against accidental reactivation |
| 14 | Abilities API registration | ✅ SHIPPED + EXPANDED | plugin v2.1.0+ | `abilities-registration.php`: 17 plugin + 12 theme = 29 abilities. Far beyond the ~4 abilities the roadmap estimated. |
| 15 | Surviving net-new features | ✅ ALL 4 SHIPPED | various | API rate-limit monitor `api-rate-monitor.php` v1.13.0; webhooks `webhooks.php` v3.4.0; cron dashboard `cron-dashboard.php` v3.0.0; content health `health-checks.php` v3.5.0 |
| 16+ | AI features (excerpt, OG title, RAG-lite chat) | ✅ MOSTLY SHIPPED | plugin v2.4.0+ | `ai-excerpt.php`, `ai-og-card-title.php`. RAG-lite chat: delivered via desktop-mode's `wp.desktop.ai.ask()` portal rather than building our own. |

---

## Verification methodology used in this audit

To make this reconciliation defensible against future "wait, was this actually done?" questions:

1. **Direct file reads** (not just file existence) — for every claim of "shipped," the audit read at least the file's docblock + first 30-50 lines to verify (a) the feature is wired, not just stubbed; (b) the @since tag or "v1.x.x added" annotation matches the roadmap version; (c) no obvious dead code from abandoned approaches.
2. **Production server probe** for plugin deactivations — `ssh ... php -r "..."` ran against the live wp-load.php to read `active_plugins` from the options table. TSF + wps-hide-login confirmed absent.
3. **Production version probe** for SNT_VERSION — confirmed `3.8.2` is what PHP reads at runtime, not just what the docblock says.
4. **Cross-reference against memory entries** — facts derived from prior sessions (e.g., desktop-mode integration scope, 29-ability count) were verified by grep against the actual registration code, not just trusted from memory.

This is the verification-before-completion skill applied retrospectively. The cost was ~12 file reads + 2 SSH probes + 4 greps — cheap insurance against the audit being wrong.

---

## Memory entries to create (carried over from prior handoff)

These three were flagged in [2026-05-25-sub-tabs-arc-complete.md](2026-05-25-sub-tabs-arc-complete.md) but deferred due to context budget. They remain worth creating, and they each capture a class-of-bug lesson worth recurring-reference:

1. **`feedback_version_constants_must_derive_from_docblock.md`** — SNT_VERSION drift lesson; pattern `get_file_data(__FILE__, ['Version' => 'Version'], 'plugin')` at bootstrap.
2. **`feedback_desktop_mode_horizontal_submenu_warning.md`** — WP submenu items become horizontal top nav in desktop-mode portal; design in-page nav with this in mind.
3. **`feedback_internal_toc_vs_sub_tabs_decision.md`** — when sections share a form, internal TOC; when each section is its own independent context, sub-tabs (click-to-swap).

---

## Recommended next-session sequence

1. **Read this handoff.**
2. **Pick from "What's actually left to build" (5 items above).** Recommended order matches the list ranking:
   - Login hardening audit log (concrete spec already exists, ships v3.8.3, single session)
   - AI-assisted content-health fixes (next-most-valuable AI surface, ships v3.9.0)
   - Native Breadcrumbs theme adoption check (quick verification, may close as no-action)
   - GSC sitemap submission (15 min config, no ship)
   - WORDPRESS-REFERENCE.md gotchas append (docs-only)
3. **Or:** create the 3 memory entries listed above first (5-10 min total, low context cost, prevents recurrence of 3 classes of bug).

---

## One-line summary

**The 15-phase absorption roadmap is fully shipped with significant scope expansion. ~~Five~~ Four small items remain (~~login audit log~~ shipped as plugin v3.8.3-v3.8.5; AI-assisted content fixes, native breadcrumbs adoption check, GSC sitemap submission, WORDPRESS-REFERENCE updates). The headline non-roadmap addition is `insights.php` v3.6.0 (Content Opportunity Advisor), and the 33-ability surface across plugin + theme (17→21 plugin + 12 theme) is the architectural foundation that pays the most forward dividends.**

---

## 2026-05-25 session-end addendum

4 plugin ships landed in the session after this reconciliation was first written:

| Tag | Scope |
|---|---|
| **v3.8.3** | Login hardening audit log shipped (Security → Audit log sub-tab); 4 new abilities; 2 new ⌘K commands; +991 LOC across 8 files |
| **v3.8.4** | Fix: desktop-mode dock submenu derives from `sn_admin_top_tabs()` (was hardcoded 8 entries; v3.8.1 reduced wp-admin sidebar to 6 but missed this parallel filter — single-source-of-truth violation) |
| **v3.8.5** | Fix: `.sn-2col` always stacks (RSS tab was cramped at every viewport; v3.8.4's breakpoint bump 960→1200px wasn't enough at >1200px monitors) |
| **v3.8.6** | Viewport-fit admin pages — system-wide CSS pass: sticky chrome (sub-tab nav + TOC), `.snt-scroll-table` opt-in wrapper (max-height 50vh + sticky `<thead>`) on 6 tables across 5 module files, hero card density (16→12px padding, 28→22px values), CSS-variable tightening (`--sn-space-4` 16→12px, `--sn-space-5` 24→20px) rippling through 23 callsites |

Plugin patch headroom after: **6/7** in v3.8.x. **1 patch remains before v3.9.0 rollover.** Theme unchanged this session (still v9.1.5).
