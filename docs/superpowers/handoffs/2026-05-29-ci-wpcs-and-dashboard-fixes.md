# Handoff — 2026-05-29 — CI + WPCS install + dashboard fix + the false-0/0 catch

**Why this exists:** a long single-day session that started as "QA both codebases" and chained through several follow-ups the user requested one at a time ("do whatever you have to do", "fix everything"). It produced 3 plugin releases, a real WPCS toolchain, CI on both repos, and — most importantly — exposed and fixed a PHPCS false-0/0 that had been silently hiding theme findings. This handoff captures the full arc so the next session starts from truth, not from the stale "v4.5.3 / split pending" state two earlier handoffs recorded.

---

## TL;DR — current state (all verified green)

- **Theme: v9.5.1** (unchanged this session except dev-tooling + the readme fix below). 355 tests / 7 suites. PHPCS 0/0 (falsification-verified). CI green. Synced, clean.
- **Plugin: v4.5.5.** 1,046 tests / 30 suites. PHPCS 0/0. CI green. Synced, clean.
- **Both repos now have real CI** (`.github/workflows/ci.yml`): lint + WPCS + tests + PR-gated CHANGELOG check. Verified green per-job on push.
- **WPCS is genuinely installed** (composer via Homebrew + per-repo `vendor/`); `composer run lint` works in both.
- **Combined: 1,401 assertions / 37 suites / 0 failures.**

---

## What shipped this session (chronological)

1. **Plugin v4.5.2 + theme v9.5.1** — pre-v4.6.0 QA audit (7 parallel agents). Plugin: dead pattern-adoption buttons, Identity-save audit-retention data loss (TDD-fixed), unstyled audit tab, webhook SSRF, nameless-block rejection, reading-time confirm. Theme: /notes skip-link WCAG, core skip-link de-dupe, duplicate `<title>`, sub-11px floor, reduced-motion scroll. *(These shipped changelog-less due to an em-dash `old_string` no-op — backfilled later this session.)*
2. **Plugin v4.5.3 + theme dev-tooling** — WP-handbook PHPCS+WPCS pass. Plugin: 6 unslash + 1 `$pagenow`-ignore + 1 esc_url. Committed `phpcs.xml.dist` + `composer require-dev` to both. **NOTE: the theme's "0/0" here was a FALSE GREEN — see the catch below.**
3. **Plugin v4.5.4** — admin-page.php split (1,468 lines → 8 modules + action→callback dispatcher + shared flash registry, +88 tests). *Shipped by a spawned task in a separate worktree; my main checkout was 14 commits behind until I fast-forwarded — caught via the live dashboard showing 4.5.4.*
4. **Plugin v4.5.5** — Dashboard External-APIs line shows only APIs that report (Cloudflare uses non-standard `Ratelimit` headers, Plausible emits none — both verified vs live docs). Permanent `—` → omitted, self-healing. TDD'd (13 assertions).
5. **WPCS install + CI** — `brew install composer`; `composer install` per repo; `composer.lock` removed + gitignored (distributed packages, dev-only deps); `ci.yml` added to both; plugin's redundant `lint.yml` removed; theme `composer.json` PHP floor `8.1`→`8.0` (matched the other 3 sources).
6. **The false-0/0 fix (both repos)** — see below.
7. **readme.txt Stable tag** `9.5.0` → `9.5.1` (this commit) — the v9.5.1 ship bumped style.css + CHANGELOG but missed the readme; same partial-update class as the empty-changelog bug.

---

## THE KEY CATCH: PHPCS false-0/0 (read this before trusting any lint result)

**Symptom:** theme CI failed on first run even though local `composer run lint` said 0/0.

**Root cause:** PHPCS matches `<exclude-pattern>` as **regex against the full ABSOLUTE path**. The theme's pattern was `*/.claude/*`, and the theme is developed in a git worktree at `…/.claude/worktrees/nice-goldstine-063551/` — so the pattern matched the worktree's own ancestor path and **silently excluded every file**. Local = false 0/0; CI (clean `/home/runner` path) = real findings.

**Fix (both repos now identical + worktree-safe):**
```xml
<exclude-pattern type="relative">^\.claude/</exclude-pattern>
```
`type="relative"` anchors the match to `basepath`, so it only matches a `.claude/` at the repo ROOT — never an ancestor. This also correctly excludes the plugin's *nested* `.claude/worktrees/quirky-hoover-cdc582/` (a full second copy of `inc/*.php`). `.claude/` is now gitignored in both repos.

**Real theme findings that had been hidden** (all fixed): `$_SERVER['REQUEST_URI']` unslash (page-notes-template), `force-check` unslash (wp-update-integration), trusted `file_get_contents`/`do_blocks`/esc'd-helper annotations (assets-frontend + page-notes-render ×5), I18n `MissingTranslatorsComment` excluded.

**THE DISCIPLINE (now a memory rule):** *Always falsification-test a 0/0 — inject a known violation and confirm the linter reports it — before trusting it. `exit 0` can mean "scanned nothing."* Both repos verified this way: canary → errors (scanner ALIVE), then real 0/0.

---

## How to run the linter (committed, persistent)

```bash
cd <repo> && composer install   # one-time; vendor/ is gitignored
composer run lint               # → 0/0
composer run lint:fix           # phpcbf (whitespace-safe only)
```
`composer` is now installed globally via Homebrew. CI runs the same `composer run lint`.

---

## Is the theme "behind"? (the question that opened this step)

No — not in any actionable sense. Theme is synced (behind=0 ahead=0 dirty=0), all green, no unshipped work. The **version gap (theme 9.5.1 vs plugin 4.5.5) is expected** — independent cadences; the plugin absorbed 15 tooling phases and ships more often. Both scope audits agree there's no driver to force lockstep.

The theme's next *planned* step is **v9.6.0** (prep-minor; plan written at `docs/superpowers/plans/2026-05-27-v9.6.0.md`) — but per the sequencing chain, **v4.6.0 (plugin prep-minor) comes FIRST**, then v9.6.0, then the v5.0.0+v10.0.0 paired major. Recommend letting this session's large batch settle before opening v4.6.0 (batch-releases + audit-before-UAT discipline).

---

## Next-session pickup

- Baseline: **plugin v4.5.5 / theme v9.5.1**, both green + CI-enforced. Install plugin v4.5.5 (and, if not yet, the theme readme bump rides in the same install — no version change) via wp-admin → Updates. Tag push does NOT auto-deploy.
- 4 locked plans unchanged. Next execution target: **v4.6.0 BC** (plugin repo: `docs/superpowers/plans/2026-05-27-v4.6.0.md`).
- Deferred to v4.6.0 BC: 5 QA items (inline-style sweep, prefix doc, pull-quote→blockquote, content-migrations retirement, CHANGELOG Mimestream backfill) + wiring the now-working `composer run lint` deeper into the dev loop.
- The CHANGELOG-presence CI gate is LIVE (PR-gated) — it'll catch the empty-changelog class going forward.

## One-line summary
**Big multi-thread session: shipped plugin v4.5.2→v4.5.5 (QA fixes, handbook conformance, admin-page split, dashboard API-counter fix) + theme v9.5.1 QA fixes; installed WPCS for real + added CI (lint/phpcs/tests/changelog-gate) to both repos; and caught+fixed a PHPCS false-0/0 (worktree path matched `*/.claude/*`) that had been silently hiding theme findings — both rulesets now `type="relative"` anchored + falsification-verified. Theme is synced + green at v9.5.1; next planned step is v4.6.0 (plugin) then v9.6.0.**
