# Handoff — 2026-05-24 (AI-readiness arc COMPLETE; items D + E start fresh)

**Hand off because:** The longest single session in project history. Started "execute plugin v3.7.4 ⌘K command plan" → ended "v9.1.3 theme + v3.7.6 plugin shipped, abilities AI-tool-ready, upstream PR pending, plugin v3.7.x cap exhausted." Items D (theme deploy migration) + E (Phase 8 wps-hide-login absorption) are explicitly queued for a clean session start.

---

## TL;DR — production state

| Surface | Latest tag | Cap status | Notes |
|---|---|---|---|
| **Theme** | **v9.1.3** | 4/7 patches used in v9.1.x; 3 remain | Item D will bump to v9.1.4 |
| **Plugin** | **v3.7.6** | **7/7 patches used — CAP HIT** | Next code change MUST roll to v3.8.0 |
| **Theme docs** | `dc7a123` post-v9.1.3 | n/a | WORDPRESS-REFERENCE.md +3 gotchas (docs-only, no bump) |
| **Plugin docs** | `67c8a66` post-v3.7.5 | n/a | upstream-monitoring.md playbook (docs-only) |

**29 abilities are AI-tool-harvester-ready** (12 theme `signal-and-noise/*` + 17 plugin `signal-noise/*`). When WordPress/desktop-mode PR #240's step 3 (Abilities-as-tools bridge) ships, our registrations are auto-promoted. When a Core or upstream Anthropic provider story crystallizes, the abilities surface to the Copilot.

---

## What landed today (4 release tags + 2 doc commits)

**Theme** (one minor bump v9.1.0 → v9.1.1, then 3 patches):
- `v9.1.1` ([`f07d673`](https://github.com/juanlentino/signal-and-noise/commit/f07d673)) — rename theme abilities to `signal-and-noise/*` for WP 7.0 namespace classifier
- `v9.1.2` ([`0b5998f`](https://github.com/juanlentino/signal-and-noise/commit/0b5998f)) — AI-readiness pass: security/permission tightening + items schemas on 7 array outputs + `read_only`→`readonly` annotation rename
- `v9.1.3` ([`7f3b294`](https://github.com/juanlentino/signal-and-noise/commit/7f3b294)) — 85 integration test assertions + JSON Schema examples on 11 input properties
- `dc7a123` (docs) — WORDPRESS-REFERENCE.md entries #32 (method_exists vs is_callable), #33 (eliminate credentials), #34 (desktop-mode field-strip)

**Plugin** (3 patches in v3.7.x line):
- `v3.7.4` ([`acfe231`](https://github.com/juanlentino/signal-and-noise-tools/commit/acfe231)) — AI-readiness pass: get-rss-stats schema backfill + `additionalProperties:false` on 17 schemas + 3 description hints + v3.8.0 cancellation cleanup
- `v3.7.5` ([`ab82d7c`](https://github.com/juanlentino/signal-and-noise-tools/commit/ab82d7c)) — 108 integration test assertions + 10-closure named-function refactor + JSON Schema examples on 8 properties + 1384-line abilities catalog + 3 agent guideline templates
- `v3.7.6` ([`5b3d808`](https://github.com/juanlentino/signal-and-noise-tools/commit/5b3d808)) — security audit (0 HIGH, 2 MEDIUM fixed): `error_log` on `inc/cron-dashboard.php:269` + `inc/ai-bootstrap.php:84`
- `67c8a66` (docs) — upstream-monitoring.md playbook + GH repo subscription to WordPress/desktop-mode

**Test count totals (cumulative across the arc):**
- Theme: **239 assertions** (was 154 — +85 integration)
- Plugin: **658 assertions across 10 suites** (was 550 — +108 integration)

---

## The cancelled v3.8.0 arc (preserved as historical record)

`docs/superpowers/specs/2026-05-24-plugin-v3.8.0-anthropic-provider-design.md` + `docs/superpowers/plans/2026-05-24-plugin-v3.8.0-anthropic-provider.md` both annotated CANCELLED.

**Why cancelled** (read in this order):
1. [WordPress/desktop-mode PR #240](https://github.com/WordPress/desktop-mode/pull/240) — Agents framework MOCK. Step 3 = Abilities-as-tools bridge that will auto-harvest our `wp_register_ability()` registrations. The 26 manual `desktop_mode_register_ai_tool()` wrappers we planned in v3.8.0 would have been obsoleted before shipping.
2. [WordPress/desktop-mode#271](https://github.com/WordPress/desktop-mode/issues/271#issuecomment-4530436691) — Collaborator AllTerrainDeveloper explicitly: *"We prefer to wait, around the AI provider, as those feel more from CORE."* Not the architecture we'd planned to ship upstream.
3. [WordPress/desktop-mode `commands.php:148-155`](https://github.com/WordPress/desktop-mode/blob/trunk/includes/commands.php) — `desktop_mode_register_command()` silently strips fields outside its 6-key whitelist. The original v3.7.4 plan assumed extra fields would propagate; they don't. Even the manual ⌘K command surface was wrongly architected.

**What's preserved in git history** (in case we ever port it):
- Plugin commits `d3d89cc`, `92e39cc`, `a1275b2`: ~600 LOC Anthropic provider (HTTP wire layer + 3 callbacks + tool translation) with 71 test assertions. Reverted in `efc9459` but recoverable via `git show <sha>`.

**Upstream contribution status:**
- Fork at `juanlentino/desktop-mode` cloned to `/Users/juanlentino/Projects/desktop-mode/` (branch `feat/anthropic-provider` exists, empty, at upstream/trunk HEAD)
- Comment posted + DELETED from issue #271 after seeing maintainer's "wait for CORE" comment
- **NO PR opened** — the maintainer signal is clear; don't push code they've signaled against
- Re-evaluate when upstream signals change (per `docs/upstream-monitoring.md` playbook)

---

## Items D + E — your next session's queue

### Item D: Theme deploy.yml SSH+wp-eval migration

**Goal:** Eliminate `WP_DEPLOY_USER` + `WP_DEPLOY_APP_PASSWORD` from the theme repo by mirroring plugin v3.7.3's architectural fix. After this lands, NO rotatable credentials exist anywhere in the SN stack.

**Reference pattern:** plugin v3.7.3 (commit `4e5addd`) — see `signal-and-noise-tools/.github/workflows/deploy.yml:80-115` for the "Purge caches via WP-CLI in-process (no App Password required)" step.

**Current theme state:**
- Theme `.github/workflows/deploy.yml` still uses HTTP Basic Auth for the Cloudflare purge step (per security audit B4)
- `WP_DEPLOY_USER` + `WP_DEPLOY_APP_PASSWORD` GH secrets likely still exist on `juanlentino/signal-and-noise` (need to verify with `gh secret list --repo juanlentino/signal-and-noise`)
- Theme `.github/workflows/deploy.yml` is `workflow_dispatch:` only since v8.5.1 (no auto-deploy on tag push)
- Cache purge logic lives in the theme's `sn_purge_all_caches_result` filter at `inc/template-maintenance.php`

**Migration steps** (mirrors plugin v3.7.3 plan):
1. Read plugin's v3.7.3 deploy.yml as the reference
2. Verify SSH_HOST, SSH_KNOWN_HOSTS, SSH_PRIVATE_KEY, SSH_USER secrets exist on theme repo (`gh secret list`) — they're needed for SSH access
3. Replace the HTTP-based purge step with SSH+wp-eval: `ssh sn-theme@cloudways "cd /apps/.../public_html && wp eval 'do_action(\"sn_purge_all_caches\");'"`
4. Add `continue-on-error: true` per plugin v3.7.2 pattern (a missed purge is a latency artifact, not deploy failure)
5. Verify by triggering `gh workflow run deploy.yml --ref vX.Y.Z`
6. After successful deploy: `gh secret delete WP_DEPLOY_USER --repo juanlentino/signal-and-noise` + `gh secret delete WP_DEPLOY_APP_PASSWORD --repo juanlentino/signal-and-noise`
7. Revoke the corresponding App Password in wp-admin → Users → Profile (this is a separate WP-side cleanup)

**Estimated effort:** 30-60 min (mostly verification + testing; the code change is small)

**Versioning:** theme patch v9.1.3 → v9.1.4 (4/7 patches used in v9.1.x; 3 remain)

**Skill discipline reminder:** invoke `superpowers:verification-before-completion` before claiming success. The `gh workflow run` + log inspection IS the verification command — don't claim it works until you've watched the run complete + the post-deploy cache state changes.

### Item E: Phase 8 — wps-hide-login absorption

**Goal:** Replace the wps-hide-login community plugin with native SN-tools functionality. The only formally unstarted phase from the 15-phase plugin absorption roadmap.

**Reference materials:**
- Plugin absorption roadmap: `/Users/juanlentino/Projects/signal-and-noise/.claude/worktrees/nice-goldstine-063551/docs/superpowers/specs/2026-05-16-plugin-absorption-roadmap.md` — Phase 8 section, user-narrowed scope: *"smallest absorption candidate (~80 LOC including admin UI)"*
- Existing dormancy-detection: plugin's `inc/login-hide.php:53` already has `is_plugin_active( $wps_basename ) && file_exists( $wps_file )` pattern (verified clean in v3.7.6 security audit C2)
- Memory entry: `feedback_plugin_absorption_strategic_direction.md` — strategic context

**Likely scope** (verify against the absorption roadmap doc):
- Custom login URL via rewrite rule
- Redirect default wp-login.php to 404 (or configurable)
- Admin UI in the SN Identity tab (`inc/identity-admin.php` likely)
- Migration: detect wps-hide-login settings on activation, copy URL, then deactivate
- Setting stored in `sn_settings.identity.hidden_login_url` (or similar — match existing schema)

**Pre-implementation discipline (per hard rule):**
1. Invoke `superpowers:brainstorming` skill — Phase 8 is net-new feature, deserves a design pass before code
2. Read the wps-hide-login plugin source on the production install (SSH in, `cat wp-content/plugins/wps-hide-login/...`) to verify our spec matches the actual feature surface we're absorbing
3. Read existing `inc/login-hide.php` to understand current dormancy-detection
4. Read `inc/identity-admin.php` (if exists) for the SN admin UI pattern
5. Then `superpowers:writing-plans` for the implementation plan
6. Then execute via `superpowers:subagent-driven-development` or inline depending on scope

**Estimated effort:** brainstorm (~30 min) + spec (~30 min) + plan (~30 min) + execute (~1-2 hr) + ship = 3-4 hours total. Worth its own session.

**Versioning:** plugin **MUST be v3.8.0** (cap hit at v3.7.6). This is the right kind of feature for a minor bump — new user-visible capability surface.

---

## Other outstanding items (queued, not in scope for D/E session)

From the [survey done this session](docs/superpowers/handoffs/2026-05-24-ai-readiness-arc-complete.md), in priority order:

| Item | Repo | Status |
|---|---|---|
| Install all theme + plugin updates on production via wp-admin Updates | Both | Probably done (user installed during session) |
| Cleanup deprecated REST handlers for v4.0.0 cut | Plugin | Queued for v4.0.0 cycle |
| WORDPRESS-REFERENCE.md updates with newer lessons | Theme | Ongoing, +3 entries shipped this session |
| Native Breadcrumbs visual adoption in theme templates | Theme | Cosmetic, deferred |
| Submit `/wp-sitemap.xml` to Google Search Console | Ops | User action |

**NOT queued** (explicitly deferred by maintainer signal):
- Upstream Anthropic provider PR to WordPress/desktop-mode — DO NOT OPEN until upstream signals change. Watch `docs/upstream-monitoring.md` playbook.

---

## Session-level lessons captured

Three discipline lessons surfaced today, all worth preserving:

### 1. The "small change exception" is the failure mode the hard rule warns against

I almost shipped a `snt_ai_is_available()` caching patch (v3.7.6 first draft) on the unverified assumption that the function fires HTTP. Reading [php-ai-client `PromptBuilder.php:821` + ProviderRegistry.php:271-293](https://github.com/WordPress/php-ai-client) revealed it's deterministic + local — caching would have addressed CPU perf but NOT the user's stated concern (API requests). The patch would have shipped under false pretenses. **The hard rule** ([`feedback_skills_plugins_docs_always.md`](../../../.claude/projects/-Users-juanlentino-Projects-signal-and-noise/memory/feedback_skills_plugins_docs_always.md)) requires source reading even for "small" changes. The user enforced this twice this session ("USE SKILLS, PLUGINS AND HANDBOOKS") — both times caught a misguided patch in flight.

### 2. Verification evidence in audit reports is load-bearing

The v3.7.6 security audit's Part D ("clean results") is what makes "0 HIGH severity" credible. Without it, "0 findings" reads as "I didn't look hard enough." Per the `superpowers:verification-before-completion` skill: every claim cited with command + output + file:line. The full audit covered 9 dimensions; each one has a paragraph in Part D enumerating what was checked.

### 3. Upstream engagement requires reading the room first

I posted a thoughtful comment on WordPress/desktop-mode#271 proposing the Anthropic provider PR — without reading the existing comment thread. The collaborator had already commented "we prefer to wait, around the AI provider, as those feel more from CORE." My comment proposed exactly what they said they don't want. Comment got deleted by the user after they spotted the mismatch. **Lesson:** before commenting on someone else's project, read EVERY existing comment on the issue. Engagement etiquette > engagement speed.

---

## Where to pick up next session

### Recommended sequence

1. **Read this handoff** (you're doing it)
2. **Verify production state**: `gh release view v9.1.3 --repo juanlentino/signal-and-noise` + `gh release view v3.7.6 --repo juanlentino/signal-and-noise-tools` (both should be tagged). Check `gh secret list --repo juanlentino/signal-and-noise` for which credentials still exist.
3. **Pick D OR E first** (probably D since it unblocks E by demonstrating the SSH pattern works):
   - **D**: read plugin v3.7.3 deploy.yml as reference, mirror in theme, ship as theme v9.1.4
   - **E**: invoke `superpowers:brainstorming` first, then spec, then plan, then execute as plugin v3.8.0
4. **Re-read upstream-monitoring playbook** at `signal-and-noise-tools/docs/upstream-monitoring.md` before considering any AI-Copilot-related work
5. **Don't open the upstream PR** until [WordPress/desktop-mode#271](https://github.com/WordPress/desktop-mode/issues/271) shows a maintainer signal change

### Key file locations

| What | Path |
|---|---|
| Plugin v3.7.3 deploy.yml (Item D reference) | `signal-and-noise-tools/.github/workflows/deploy.yml:80-115` |
| Theme deploy.yml (Item D target) | `signal-and-noise/.github/workflows/deploy.yml` |
| Plugin absorption roadmap (Item E source spec) | `signal-and-noise/docs/superpowers/specs/2026-05-16-plugin-absorption-roadmap.md` |
| Existing login-hide dormancy code | `signal-and-noise-tools/inc/login-hide.php` |
| Existing identity admin UI (Item E surface) | `signal-and-noise-tools/inc/identity-admin.php` (if exists) |
| Upstream monitoring playbook | `signal-and-noise-tools/docs/upstream-monitoring.md` |
| AI abilities catalog (any future ability work) | `signal-and-noise-tools/docs/ai-abilities-catalog.md` |
| Agent guideline templates (speculative) | `signal-and-noise-tools/docs/agent-guidelines/` |
| WORDPRESS-REFERENCE (gotcha lookup) | `signal-and-noise/docs/WORDPRESS-REFERENCE.md` (34 entries) |
| v3.8.0 spec (CANCELLED, preserved) | `signal-and-noise-tools/docs/superpowers/specs/2026-05-24-plugin-v3.8.0-anthropic-provider-design.md` |
| Anthropic provider implementation in git history | Plugin commits `d3d89cc`, `92e39cc`, `a1275b2` (reverted in `efc9459`) |

### Patch-cap watch

**Plugin v3.7.x cap is HIT (7/7).** Next code-bearing release MUST be v3.8.0. Item E (Phase 8) is the natural v3.8.0 candidate — it's a new capability surface that justifies a minor bump.

Theme v9.1.x cap is 4/7. Item D bumps to v9.1.4 (cleanly within cap).

### Process discipline summary

- Today's session shipped 4 release tags + 2 doc commits across 2 repos
- Used `superpowers:brainstorming`, `superpowers:writing-plans`, `superpowers:writing-skills`, `superpowers:verification-before-completion`, `superpowers:subagent-driven-development`, `gsd-docs-update`, `claude-api` skills explicitly
- 6 subagent dispatches (audit, conventions research, catalog, integration tests, agent guidelines, security audit, source survey)
- 0 force pushes, 0 amends, 0 `--no-verify` usage
- Every claim of "shipped" or "tests pass" verified with fresh command output per `superpowers:verification-before-completion`

---

## One-line summary

**29 SN abilities are AI-tool-harvester-ready (theme v9.1.3 + plugin v3.7.6 + 1384-line catalog + 3 agent templates + monitoring playbook). v3.8.0 plan cancelled in favor of waiting for upstream signals. Items D (theme SSH+wp-eval migration) + E (Phase 8 wps-hide-login absorption) are queued for the next session — D first (mirrors plugin v3.7.3 pattern; ships as theme v9.1.4); E next (needs brainstorm → spec → plan → execute; ships as plugin v3.8.0 since v3.7.x cap is hit).**
