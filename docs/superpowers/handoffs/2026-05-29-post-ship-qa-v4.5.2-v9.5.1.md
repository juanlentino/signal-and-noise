# Handoff — 2026-05-29 — Post-ship QA audit → v4.5.2 + v9.5.1 patches shipped

**Why this exists:** before starting the v4.6.0 prep-minor cycle, a full-codebase QA/bug/UX-UI audit was run across BOTH repos (the handoff [`2026-05-27-paired-cycle-all-plans-locked.md`](2026-05-27-paired-cycle-all-plans-locked.md) §4 explicitly authorised this "conditional path": ship v4.5.2 / v9.5.1 patches FIRST if bugs surfaced). They did. This session audited, fixed, tested, and shipped both patches. The 4 locked plans (v4.6.0 + v9.6.0 + v5.0.0 + v10.0.0) are UNCHANGED — the sequencing chain now simply starts from a v4.5.2 / v9.5.1 baseline instead of v4.5.1 / v9.5.0.

---

## TL;DR

- **Method:** 7 parallel read-only review agents (correctness / security / UX-UI / hygiene × theme + plugin), each seeded with this project's recurring bug-classes. Findings synthesised, root-caused against live source, fixed with TDD where testable, verified, shipped.
- **Recurring bug-classes audited and found CLEAN** (the team's lessons held): `is_callable` vs `method_exists` AI-client trap, `document_title_parts`, `rel_canonical`, version-from-docblock, install-hook self-observation, Suggest+Apply md5 fingerprints, all 37 REST `permission_callback`s, custom-table SQL `prepare()` + ORDER BY allowlists, secret handling, `.git`-preservation cross-FS abort-before-destroy, contrast baseline 20/20.
- **Shipped:** **plugin `v4.5.2`** (commit `9bb3c4a`, tag pushed) + **theme `v9.5.1`** (commit `398fd7f`, tag pushed). Tag push does NOT auto-deploy — install via wp-admin → Updates.
- **Tests:** plugin **945 / 26 suites** (added `tests/settings-save-preserves-subtrees.php`), theme **355 / 7 suites**. 0 failed. All 8 changed PHP files lint clean.
- **Deferred → v4.6.0 cycle** (user decision): CHANGELOG Mimestream backfill, inline-style consolidation (22 instances), prefix-convention doc, pull-quote→blockquote, content-migrations retirement.
- **Spawned as its own task:** `admin-page.php` (1,463-line) split into handler + flash-data + form modules.

---

## What shipped

### Plugin v4.5.2 (`9bb3c4a`)
| Sev | Fix | File |
|---|---|---|
| CRITICAL | Dead pattern-adoption Suggest/Dismiss buttons on Health tab when no AI provider configured (JS enqueued under AI gate; AI-free section renders buttons unconditionally). Now enqueued unconditionally on Health tab. | `inc/admin-page.php` |
| HIGH | Identity save silently wiped `audit.retention_days` (whole-option replace omitted the `audit` subtree). Now preserved like `login.slug`. **Regression test added.** | `inc/settings.php` |
| HIGH | Audit-log sub-tab rendered unstyled via the "Security" sidebar deep-link (CSS guarded on wrong hook suffix). Now uses `sn_admin_page_hooks()`. | `inc/audit-log-admin.php` |
| MEDIUM | Webhook dispatch followed 3 redirects without re-validation (SSRF + exfil via admin-visible log). `redirection => 0`. | `inc/webhooks.php` |
| MEDIUM | Apply handlers accepted nameless freeform blocks from malformed markup (spliced raw HTML). Now require a named block. | `inc/pattern-adoption-apply.php`, `inc/block-migrations-apply.php` |
| MEDIUM | Reading-time "Apply" (irreversible bulk mutation) had no confirmation. Added the shared confirm modal. | `inc/reading-time.php` |

### Theme v9.5.1 (`398fd7f`)
| Sev | Fix | File |
|---|---|---|
| HIGH | `/notes` skip-link dangled (`#wp--skip-link--target` never stamped onto the custom renderer's `<main>`). Added the id. WCAG 2.4.1. | `inc/page-notes-render.php` |
| HIGH | Sub-11px type (`.sn-catalog-section-*` on services/music, `.sn-notes-section-*` on /notes) at 0.65rem → `max(0.7rem, 11px)`. | `assets/css/components.css`, `inc/page-notes-render.php` |
| MEDIUM | Duplicate "Skip to content" link (WP core's + theme's). Hide core's `#wp-skip-link` via ID-specificity rule; theme's brand link stays. | `assets/css/critical.css` |
| MEDIUM | Duplicate `<title>` on /notes (manual echo + `title-tag` support both emitted). Removed the manual echo. | `inc/page-notes-render.php` |
| MEDIUM | `scroll-behavior: smooth` not gated behind `prefers-reduced-motion`. Added override. | `assets/css/base.css` |

---

## Deferred to the v4.6.0 cycle (do at BC)

1. **CHANGELOG Mimestream backfill** — ~12 plugin + ~5 theme legacy `**Fixes:**`-blob entries → `### Fixed/Added/Changed` headers. Mechanical. (Note: the v4.5.2 / v9.5.1 entries are already compliant.)
2. **Inline-style consolidation (22 instances)** — `health-checks-admin.php`, `pattern-adoption-admin.php`, `block-migrations-admin.php`, `webhooks-admin.php` carry inline `style=` attrs (incl. a front-end `--wp--preset--color--blood` var leaking into admin at `webhooks-admin.php:70`). Promote to utility classes in `admin.css` (the 40/20/40 column triple is duplicated). Completes the v4.4.3 consolidation.
3. **Prefix-convention doc** — plugin splits `sn_` (177) vs `snt_` (185) with no documented rule. Don't mass-rename (breaks public surface); DOCUMENT the de-facto rule in WORDPRESS-REFERENCE / plugin header. (theme has 3 `signal_noise_` stragglers vs 36 `sn_` — trivial.)
4. **Pull-quote pattern → real `<blockquote>`** — `patterns/pull-quote.php` declares `core/quote` but renders `<aside><p>` (AT announces plain paragraphs). MEDIUM a11y/semantic gap; deferred because it changes a pattern's markup (verify no content depends on the current shape). Candidate for v9.6.0.
5. **`content-migrations.php` retirement** — 11 near-duplicate one-shot `sn_migrate_*()` fns (700 lines) loaded on every admin request. Audit prod sentinel-option state, factor the common skeleton, and retire completed migrations. Needs prod state visibility.

## Spawned as its own task (chip)
- **`admin-page.php` split** (1,463 lines → handler + shared flash-data + per-tab form modules). Pure refactor, behavior-preserving, → plugin v4.5.3. Self-contained brief lives in the spawned session.

## LOW-severity backlog (not yet ticketed — sweep opportunistically)
**Plugin:** webhook URL validation misses IPv6/`169.254.x` (config-time); `cron/run` REST has no hook allowlist; `login-hide` `/wp-admin` prefix check breaks on subdir installs; CF zone-ID autoloaded (token correctly isn't); no `register_deactivation_hook` to clear 4 recurring crons; cron-history dedup-flag comment inaccurate on the Run-now path; `ai-alt-inline` `image_src` uses `sanitize_text_field` (byte-match risk); inconsistent ability-input access style; insights "Settings" form posts to a legacy slug (extra 301); dismiss-error uses `window.alert` (blocked in desktop-mode portal).
**Theme:** `get-page-notes-pillars` `last_modified` likely never resolves (wrong post type/path); `ai-rewrite` ordered-list regex miscounts; view-transition style injection no-ops on single-quoted `style`; Spotify oEmbed uses raw `&`; `.font-display` class referenced but undefined; footnote markers can compute <11px; `/notes` pillar card lacks `:focus-within` parity; reduced-motion block misses 2 hover transitions.

---

## Next-session pickup
- Baseline is now **plugin v4.5.2 / theme v9.5.1** (both pushed + tagged, install via wp-admin Updates). UAT the 11 fixes if desired.
- The 4 locked plans are unchanged — proceed to **v4.6.0 BC** (plugin repo: `docs/superpowers/plans/2026-05-27-v4.6.0.md`) per the [2026-05-27 handoff](2026-05-27-paired-cycle-all-plans-locked.md) §4. Fold the 5 deferred items above into that cycle's scope at BC.
- Verify both suites still green before starting: plugin 945/26, theme 355/7.

## One-line summary
**Pre-v4.6.0 QA audit (7 parallel agents, both repos) found 1 CRITICAL + 6 HIGH + ~11 MEDIUM + ~18 LOW; the recurring bug-classes were all clean. Fixed + TDD-tested + shipped the high-value batch as plugin v4.5.2 + theme v9.5.1 (945/26 + 355/7 green); deferred 5 items to v4.6.0 and spawned the admin-page.php split as its own task.**
