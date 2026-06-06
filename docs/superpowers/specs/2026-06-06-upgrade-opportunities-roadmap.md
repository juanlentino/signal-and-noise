# Upgrade Opportunities — sequenced release roadmap (2026-06-06)

**Status:** Backlog + sequencing. Captured from two ultracode multi-agent sweeps (code-first + handbook-driven), each adversarially verified (refute-by-default) and filtered for the project's hard constraints + an explicit **token-frugal-AI** rule (no per-pageview/always-on AI; batch/on-demand/cached single-call only). The user's intent: **build all of these eventually, sequenced across patches → minors → majors.** Deep per-feature design still happens at each feature's own brainstorm.

**Provenance:** Sweep 1 (code-first, 8 lenses): 57 candidates → 46 kept / 11 dropped. Sweep 2 (handbook-driven, 8 lenses): 45 candidates → 12 kept / 9 dropped / **24 un-verdicted** (verifier glitch — recoverable via a follow-up pass; pattern suggests they skew low-leverage/dupes). After dedup ≈ **42 distinct opportunities**, below. Verification killed fabricated citations + false premises (see §Rejected).

**Execution order — LOCKED 2026-06-06:** **Track A → Track B → Track C** (patches/fixes first, then additive minors, then the majors). Confirmed by the user. Individual version numbers within each track are still assigned at build time (releases batch at session end per `[[feedback_batch_releases_not_per_fix]]`); theme + plugin version independently.

**Project state at capture:** theme **v9.9.0**, plugin **v4.8.0**.

---

## Track A — Patches & fixes (highest confidence, smallest, land first)

Fixes, perf, and data-only correctness. PATCH-class per the project SemVer (fix/perf/calibration), though the SEO batch could equally be one MINOR.

### A1 · Plugin patch — SEO/schema + a latent bug
| Item | Lev | Eff | Note |
|---|---|---|---|
| **Breeze HTML-cache rollover on deploy version-change** | 5 | S | **Latent bug** — documented gotcha #28 ("queued v9.0.0 enhancement"); stale inlined CSS until manual purge. Fire `sn_purge_all_caches_result` with `template_overrides=false`. |
| Article JSON-LD enrichment: `wordCount` + ISO-8601 `timeRequired` + `keywords` + `articleSection` | 4 | S | Reuse cached reading-time + tags. (Dedup of 3 finds.) |
| `Person`/publisher `ImageObject` on the KG entity node | 4 | S | Completes the knowledge-graph card. |
| `/notes` CollectionPage `mainEntity` ItemList of recent notes | 4 | S | |
| Fix stale `WebSite.potentialAction` SearchAction → `/notes/?s=` | 3 | S | + corrects a stale docblock. |
| `og:image:alt` + `twitter:image:alt` on social cards | 3 | S | Featured-image alt, Title→site fallback. |
| Trim WP-core sitemap index (drop users + empty taxonomies) | 3 | S | Every indexed URL = real content. |

### A2 · Theme patch — a11y + typography + perf
| Item | Lev | Eff | Note |
|---|---|---|---|
| Scroll-spy `aria-current` on the Identity-tab TOC | 3 | S | **Closes deferred WCAG 4.1.2-A gap PA-03.** |
| `styles.elements.caption` + `.cite` in theme.json | 2 | S | figcaptions/quote citations inherit brutalist type. |
| Unify the 3 hand-written `clamp()` heading sizes under the fluid engine (`{min,max}`) | 2 | S | ⚠ verify it doesn't alter the oversized brutalist display type (brand identity). |
| Conditional-GET (`Last-Modified` + 304) for `/feed/` + `/notes/feed/` | 3 | S | (OG-PNG half dropped — already static-served.) |
| Tune core **Speculation Rules** (prerender `/notes`→note, with exclusions) | 4 | S | Perf; core primitive. |

> **Verify-first:** "Block Bindings raw-token render bug" (dangling frontmatter shortcodes rendering raw tokens) — flagged by an agent as a *current bug*; confirm, then patch in whichever repo owns the token. If real, this is A-track.

---

## Track B — Minors (additive capabilities, bundled by theme)

### B1 · Plugin minor — "Operational hardening" (mostly native-WP-citizen wins)
| Item | Lev | Eff | AI | Note |
|---|---|---|---|---|
| **Cloudflare security-header drift health check** (cached `wp_remote_head(home_url())`) | 5 | S | — | Whole CSP/HSTS posture is delegated to CF with **zero drift detection**. |
| **Native Site Health async test** for the SN cron pipeline (overdue + `DISABLE_WP_CRON`) | 4 | M | — | Plugin registers *no* native Site Health tests today; lifts the most fragile subsystem into Tools→Site Health. |
| Multi-event webhooks (`post.updated`/`unpublished`/`deleted`) over the existing sign+cron+retry+log pipeline | 4 | M | — | |
| Native Site Health **`debug_information`** (surface SN operational state) | 3 | S | — | Support-discoverable. |
| Audit-log CSV/JSON export before the daily prune (admin + read-only ability) | 4 | S | — | |
| Uptime Kuma WP-Cron liveness heartbeat | 4 | S | — | |
| Heartbeat-driven live refresh for the Webhooks log + Cron last-fired | 3 | M | — | |
| `list-abilities` read-only meta-ability (self-enumeration across both namespaces) | 3 | S | — | |
| Privacy **exporter + eraser** for audit-log usernames + suggested **Privacy Policy text** | 2 | S | — | WP-citizen hygiene (thin PII surface). |

### B2 · Plugin minor — "Editor UX + frugal AI"
| Item | Lev | Eff | AI | Note |
|---|---|---|---|---|
| **Body-ground the Insights advisor** (bounded top-25 excerpts into the *existing* weekly call) | 5 | S | ✅ frugal | Zero new calls, ~$0.42/yr; far sharper recs. |
| **Pre-publish mistake gate** (`PluginPrePublishPanel`: noindex-left-on, missing meta-desc, no tags) | 4 | M | — | Pure client-side heuristic, no AI/network; catches mistakes at publish. |
| Expand the WP 7.0 ⌘K palette: New-Note + recent Notes + tab-jumps + 5 ability commands | 4 | S | — | (Dedup of 2 finds.) |
| AI release-notes drafter (one-call CHANGELOG-delta → Mimestream draft, on-demand) | 4 | S | ✅ frugal | Dev tooling. |
| "Create draft" button on `write_about` insights cards (seed from cached rec) | 2 | M | — | No new AI call. |

### B3 · Theme minor — "Reader experience I"
| Item | Lev | Eff | Note |
|---|---|---|---|
| **Related Notes footer** (shared-tag/pillar **heuristic** "More on this") | 5 | M | Single-note footer has no related surface; no AI. (AI relatedness map = flagship, Track C.) |
| Print / save-as-PDF stylesheet (`is_singular` `@media print`) | 4 | S | |
| Reader-visible "Updated YYYY.MM.DD" on materially-revised notes (threshold-gated) | 4 | M | |
| Copy-permalink + native Web Share affordance (progressive enhancement) | 3 | S | |
| Theme **Style Variations** (`/styles/*.json`) — alternate brutalist global looks | 3 | S | Site Editor Styles browser. |
| Brutalist **block-style variations** (`register_block_style`: Hairline separator, Signal quote) | 3 | S | One-click house styling. |
| Curate the editor to the brutalist block palette (`allowed_block_types_all`) | 3 | S | |

### B4 · Theme minor — "Feeds, blocks & pages"
| Item | Lev | Eff | Note |
|---|---|---|---|
| JSON Feed 1.1 endpoint for the Notes corpus | 3 | M | Indie-web sibling to RSS. |
| RSS item enrichment (`media:content` OG image + reading-time/tags) | 4 | S | |
| PHP-registered **sidenote + pull-quote** blocks (WP 7.0 `autoRegister`, no JS build) | 4 | M | |
| Block Bindings + `Block Bindings source` for reading-time/pillar **and** protected `_sn_*` SEO meta | 4 | M | Replaces dangling shortcodes; surfaces canonical/OG-title in body content. |
| Reader-facing front-end ⌘K palette (no-build vanilla JS) | 3 | M | |
| `/colophon` (+ optional `/now`) FSE page template | 3 | S | ⚠ keep within the "anti-self-promotion" brand line (a committed decision). |

### B5 · Already-committed frontier minors (interleave — each has its own brainstorm)
404 recovery (both repos) · in-article TOC + progress rail (theme) · IndexNow (plugin). See master-execution-sequence §Frontier.

### B-track, bigger perf lever (M, schedule deliberately)
**Strip empty `Set-Cookie` (anon-only) so Cloudflare can edge-cache HTML** — plugin `send_headers` + a CF-tab cacheability status. Potentially the single biggest perf win; today origin cookies likely block edge HTML caching entirely. Verify CF cache-rule interaction first.

---

## Track C — Majors (v5.0.0 plugin + v10.0.0 theme)

### C1 · Cleanup chain (already scoped)
WP 7.0 hard-raise · remove 10 deprecated REST routes · JS→Ability flip · orphan-option cleanup. (The real SemVer-breaking work.)

### C2 · Frugal-AI content-intelligence flagship (the marquee the cleanup-major lacks)
All **batch / single-call / cached / human-reviewed** — token-disciplined by design. Pairs with the **Plausible content-intelligence seed** (`signal-and-noise-tools/docs/superpowers/specs/2026-06-06-plausible-content-intelligence-seed.md`, incl. the v1→v2 Stats API substrate).
| Item | Lev | Eff | Note |
|---|---|---|---|
| Missing internal-link finder (batch AI → Suggest+Apply splices the anchor) | 4 | L | |
| Related Notes via AI relatedness map (+ `relatedLink` schema) | 4 | L | Upgrades B3's heuristic. |
| AI topic-cluster map for `/notes` (one-call taxonomy + reader hub strip) | 4 | L | |
| Ask-My-Site — grounded, cited Q&A over the corpus (single-call ability + Insights panel) | 4 | L | |
| Traffic-grounded stale-post triage (one batch call ranks refresh-worthiness) | 4 | M | |
| Factual/figure-drift detection (stale versions/pricing/tool names — sibling Health check) | 4 | M | Reuses drift Suggest+Apply rails. |
| Plausible layer D1–D5 + v1→v2 refactor | — | — | See the seed. |

### C-strategic (flag, low priority)
ActivityPub / fediverse presence (adopt the official plugin; verify SEO/canonical coexistence). Strategic, not urgent.

### C-marginal (fold in, don't standalone)
TOC-via-Interactivity-API (decide *inside* the committed TOC brainstorm) · OG-card layout variants (mild tension with one-treatment brand discipline).

---

## Rejected (verified false/duplicate — do NOT re-propose)
The verification pass earned its keep by killing these:
- **Sitemap `lastmod`** — WP core already emits it since 6.5 (agent fabricated a "PA-09 audit" to justify it).
- **OG-PNG cache headers** — fabricated `31536000` citation + wrong layer (CF owns static headers).
- **Conditional-GET on the Plausible fetch** — Plausible's Stats API emits no ETag/Last-Modified (read the Plausible server source); pure dead code.
- **Client-side `/notes` filter** — corpus is server-paginated; would break PE equivalence.
- **GitHub rate-monitor per-caller split** — GitHub's limit is account-wide, not per-endpoint (incoherent).
- **Per-tag feeds** — the "pillars" are WordPress *pages*, not tags.
- **Dedicated `search.html`** — a `template_redirect` (v9.8.0) intercepts all `?s=` → `/notes/?s=`; the file would be unreachable.
- **`shadows on cards`** — `.sn-note-card` is an intentional hairline-divider list, not elevated cards (brutalist-flat by design).
- **Move CSS into theme.json `styles.css`** — `add_editor_style()` already syncs the cascade; would breach the ≤150-line rule.
- **Block Hooks footer / Query-Loop variation / Section styles / pillar binding** — dupes of kept items or infeasible-as-written (void `core/post-content` can't host child hooks).
- **`/colophon` strategic version** — conflicts with the committed "anti-self-promotion" brand decision (the *page template* B4 item stays, scoped to credits not self-promotion).

## Open / recoverable
- **24 un-verdicted sweep-2 candidates** (verifier glitch) — recoverable via a resume pass if a fuller inventory is wanted; pattern suggests mostly dupes/low-leverage.
- Theme-vs-plugin ownership of the "raw-token render bug" — confirm during A2.

## Cross-references
- Plausible flagship seed: plugin `docs/superpowers/specs/2026-06-06-plausible-content-intelligence-seed.md`.
- Canonical roadmap: `docs/superpowers/specs/2026-06-05-master-execution-sequence.md` (this doc feeds its Frontier/parked sections).
- Major chain: `docs/superpowers/specs/2026-05-27-v5-and-v10-paired-cycle-design.md`.
- Constraints applied: `[[feedback_no_dashboard_widgets]]`, `[[reference_ai_plugin_v1_features]]`, `[[feedback_no_brutalist_in_admin_ui]]`, `[[design_dark_mode_omitted]]`, `[[feedback_batch_releases_not_per_fix]]`.
