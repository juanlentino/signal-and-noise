# Handoff — 2026-05-26 — Session close — v5/v10 brainstorm spec ready

**Why this exists:** long session that covered the deferred audits C + D, three theme patches (v9.4.4–v9.4.6), two plugin patches (v4.4.4–v4.4.5), three docs surfaces (audit findings + accessibility baseline + handoffs), and culminated in the v5.0.0 + v10.0.0 paired-major brainstorm. Context was at 58% when the brainstorm reached the spec-write step. This handoff persists the strategic state so the next session can pick up clean.

---

## TL;DR

- **Brainstorm complete.** Paired design for Plugin v4.5.0 + Theme v9.5.0 locked, written, committed: [`docs/superpowers/specs/2026-05-26-v4.5.0-and-v9.5.0-paired-design.md`](../specs/2026-05-26-v4.5.0-and-v9.5.0-paired-design.md). 545 lines, design-verified against source.
- **Next session's first task:** user reviews the spec, requests changes if any, approves. Then transition to `superpowers:writing-plans` to draft one plan per repo.
- **5 versions shipped this session:** Plugin v4.4.4 + v4.4.5, Theme v9.4.4 + v9.4.5 + v9.4.6. All green: plugin 888 / theme 303 assertions verified pre-tag (post v4.4.4 untested-test-failure lesson).
- **3 docs surfaces added/refreshed:** `docs/ACCESSIBILITY.md` (contrast baseline + watch thresholds), 4 audit/synthesis specs (Audits C + D findings + cycle synthesis + paired design), 3 handoffs (cycle-shipped, tier-b-shipped, this one).
- **3 memory entries refreshed:** architecture regenerated, dashboard widgets rule scoped to "new", brutalist-in-admin-UI entry indexed.
- **PA-01 content sweep still outstanding** — user ran the SQL but it matched 0 rows (extra attrs in block JSON). Diagnostic SELECT recipe in §6 below. v9.4.6 visual fix landed regardless of WCAG state.

---

## Section 1: What shipped this session

### Plugin (`signal-and-noise-tools`)

| Version | Commit | What it does |
|---|---|---|
| **v4.4.4** | `a29a221` | Audit C + D fixes: admin tabs `aria-current` (PA-03, WCAG 4.1.2), `Tested up to: 7.0` header (HYG-03), abilities docblock 28→30 (HYG-06), `@deprecated 4.2.0`→`@internal` framing (HYG-08 opt 1) |
| **v4.4.5** | `e1d8939` | PA-10 social-input `aria-label` parity + test catch-up after v4.4.4 docblock re-framing broke `tests/legacy-url-redirect.php` Test 5. Test now forward-compatible (accepts `@internal` OR `@deprecated`) |

**Plugin state:** v4.4.5, 888 assertions / 21 suites — all green and verified.

### Theme (`signal-and-noise`)

| Version | Commit | What it does |
|---|---|---|
| **v9.4.4** | `802e59d` | Audit D fixes: Turnstile strip via `script_loader_tag` (closes /notes/ leak, PA-07), reduced-motion gate on hover transforms (PA-12), `Tested up to: 7.0` header (OBS-HYG-02) |
| **v9.4.5** | `358c16d` + docs follow-up `0ebac9d` | Tier B fixes: static template `<img>` lazy/async (PA-08, 7 images), footnote popover keyboard parity (PA-11) |
| **v9.4.6** | `cd63ab2` | Body heading scale for single-post context — `<h2>` / `<h3>` / `<h4>` no longer compete with `.sn-note-title` `<h1>`. User-caught regression after the v9.3.0 long-form post layout shrank h1 but body heading scale wasn't re-tuned |

**Theme state:** v9.4.6, 303 assertions / 5 suites — all green.

### Docs

| Doc | Commit | Purpose |
|---|---|---|
| `docs/ACCESSIBILITY.md` | `549ae1e` | WCAG 2.1 AA contrast baseline + watch thresholds. Closes Audit D PA-16 optional rec. Will be turned into machine-enforced test in v9.5.0 |
| 3 audit specs in `docs/superpowers/specs/` | `f5e4f73` | Audits C + D findings + unified synthesis |
| 2 handoffs (cycle-shipped + tier-b-shipped) | `0f581c7` + `be2afec` | Session-state continuity for the prior arcs |
| **Paired design spec** (this brainstorm) | `070c6c2` | v4.5.0 + v9.5.0 design — the artifact the next session picks up from |
| Theme `CLAUDE.md` + `readme.txt` doc-hygiene | `ba75f49` | HYG-01 + HYG-02 fixes (audit doc-drift surfaces) |

### Memory (out-of-repo, in `~/.claude/projects/.../memory/`)

| File | Change |
|---|---|
| `project_architecture.md` | Full regeneration — v9.4.6 + v4.4.5 state, cap-drop framing, Audits A/B/E/C/D cycle context |
| `feedback_no_dashboard_widgets.md` | Rule rescoped to "new" surfaces; Plausible widgets + admin bar dropdown grandfathered |
| `MEMORY.md` | Refreshed architecture entry line; rephrased dashboard-widgets line; added new line for `feedback_no_brutalist_in_admin_ui.md` (was orphan) |

---

## Section 2: The v5/v10 brainstorm outcome

**Strategic framing the user settled on:** *"It makes sense that isn't 5/10 now, but everything approaches to it... Sequenced and all that..."*

Read: v5.0.0 + v10.0.0 happen *organically* (per cap-drop) only when actual breaking changes accumulate. Every minor between now and them deliberately contributes — but doesn't force them.

**Approach selected:** B — refinement-led minors with ONE net-new tool each side, both solving real problems hit THIS session.

**Plugin v4.5.0 scope (3 workstreams):**

1. `_deprecated_function()` annotations on 4 legacy `@deprecated 2.5.0` REST handlers — signal-only, gives observability for "safe to remove in v5" decision
2. JS-client flip: ability-first, REST-fallback for the 3 deprecated-route JS clients (command-palette.js stays REST-first; external consumer)
3. **NET-NEW: Block Migration tool** — `parse_blocks`/`serialize_block` infrastructure with Suggest+Apply UX, mirrors v4.3.0 pattern-adoption. First migration: heading-hierarchy-skip (directly addresses the PA-01 SQL-miss this session). ~600 LOC + 120 LOC tests.

**Theme v9.5.0 scope (3 workstreams):**

1. Cross-package listener tests (theme side of all 4 contracts) — mirrors plugin's `tests/contracts-stub.php`
2. Theme API audit → produces `<date>-v10.0.0-scope.md` — mirrors plugin's `v5.0.0-scope.md` shape
3. **NET-NEW: Build-time contrast verification** — turns `docs/ACCESSIBILITY.md` watch thresholds into machine-enforced WCAG luminance assertions. ~120 LOC + 25 assertions.

**Coordination:** v9.5.0 sync point relaxes from v5.0.x gate → v4.5.x gate. Roadmap doc §3 anticipates this; the brainstorm exercised the escape hatch.

**Full design + UAT criteria + risks + verification grounding:** [`docs/superpowers/specs/2026-05-26-v4.5.0-and-v9.5.0-paired-design.md`](../specs/2026-05-26-v4.5.0-and-v9.5.0-paired-design.md)

---

## Section 3: Next-session pickup recipe

```bash
# 1. Navigate to theme worktree
cd /Users/juanlentino/projects/signal-and-noise/.claude/worktrees/nice-goldstine-063551

# 2. Read THIS handoff (you are here)
cat docs/superpowers/handoffs/2026-05-26-session-close-v5-v10-brainstorm-spec-ready.md

# 3. Read the paired design spec (the canonical brainstorm output)
cat docs/superpowers/specs/2026-05-26-v4.5.0-and-v9.5.0-paired-design.md

# 4. Verify versions are still stable
cd /Users/juanlentino/Projects/signal-and-noise-tools
git log --oneline -3   # Expect: v4.4.5, v4.4.4, v4.4.3 (top 3)
grep "^ \* Version:" signal-and-noise-tools.php   # Expect: 4.4.5

cd /Users/juanlentino/projects/signal-and-noise/.claude/worktrees/nice-goldstine-063551
git log --oneline -7   # Expect: spec commit, v9.4.6, ACCESSIBILITY.md commit, etc.
grep "^Version:" style.css   # Expect: 9.4.6

# 5. Confirm tests still green
cd /Users/juanlentino/Projects/signal-and-noise-tools
for f in tests/*.php; do echo "=== $f ==="; php "$f" 2>&1 | tail -1; done
# Expect: 888 assertions, 0 failed (legacy-url-redirect now 6 passed since v4.4.5)

cd /Users/juanlentino/projects/signal-and-noise/.claude/worktrees/nice-goldstine-063551
for f in tests/*.php; do echo "=== $f ==="; php "$f" 2>&1 | tail -1; done
# Expect: 303 assertions, 0 failed
```

**Then, the brainstorm workflow continues from where it paused:**

### Step 1 (NEXT SESSION FIRST ACTION): Spec user review

Present the spec to the user (or have them read it directly). Ask:

> "I have the v4.5.0 + v9.5.0 paired design spec ready at `docs/superpowers/specs/2026-05-26-v4.5.0-and-v9.5.0-paired-design.md`. Please review and let me know if you want any changes before we transition to writing the implementation plans."

Wait for response. If changes requested → revise spec + re-review. If approved → Step 2.

### Step 2 (after spec approval): Invoke `superpowers:writing-plans`

The skill takes a locked design spec and produces phased implementation plans with task breakdowns + checkpoints. Per the spec §11, this brainstorm calls for **one plan per repo** (decoupled scopes):

- `docs/superpowers/plans/<ship-date>-v4.5.0.md` (plugin repo)
- `docs/superpowers/plans/<ship-date>-v9.5.0.md` (theme repo)

Plan files are created via the writing-plans skill, NOT manually. The skill provides the structure.

### Step 3 (after plans approved): Invoke `superpowers:executing-plans`

Execute the plans sequentially per spec §5. Plugin first, then theme. Cycle template applies (QA → bugfix → UI/UX → gate).

---

## Section 4: User actions owed

Carried over (still outstanding from prior handoffs):

- ⏳ **Install Plugin v4.4.5** (replaces v4.4.4) via wp-admin → Updates. Verify identity → social settings `aria-label` works with VoiceOver/NVDA.
- ⏳ **Install Theme v9.4.6** (replaces v9.4.5, v9.4.4) via wp-admin → Updates. Verify `<h2>` body section headings now clearly subordinate to `.sn-note-title` `<h1>`. Run a Purge All Caches after install to clear Breeze + Cloudflare.
- ⏳ **PA-01 content sweep (heading hierarchy)** — see §6 below for the corrected SQL diagnostic + recipe.
- ⏳ Aikido Security check for prior exploitation of `tests/contracts-smoke.php` during the v4.4.0 → v4.4.2 exposure window (carried over from earlier handoff).

---

## Section 5: Strategic state

| Repo | Current | Next concrete step |
|---|---|---|
| Plugin (`signal-and-noise-tools`) | **v4.4.5** | After v4.5.0 spec approval → writing-plans → ship v4.5.0 |
| Theme (`signal-and-noise`) | **v9.4.6** | After v4.5.x gate → v9.5.0 BC convenes (this spec becomes its input) → ship v9.5.0 |

Caps remain dropped (since 2026-05-26). v5.0.0 + v10.0.0 stay optional. The audit-to-ship loop now has 4 consecutive cycles documented: v4.4.x post-ship audit, Tier B follow-on, typography fix, paired major brainstorm. Pattern is stable.

---

## Section 6: PA-01 SQL diagnostic + retry recipe

The user ran the SQL from the earlier handoff but it matched 0 rows. Root cause: literal-string `REPLACE` patterns fail when block JSON has extra attrs (`{"level":3,"className":"foo"}` doesn't match `{"level":3}` prefix because the closing `}` differs).

### Step A — Diagnostic (run this first to see the actual format)

```sql
SELECT
    ID, post_title,
    SUBSTRING(post_content, LOCATE('wp:heading', post_content), 100) AS heading_block_start
FROM wp_posts
WHERE post_type='post' AND post_status='publish'
    AND post_content LIKE '%wp:heading%level":3%'
LIMIT 5;
```

Share the output — that tells us the actual stored format.

### Step B — Two paths forward

**Path 1 — Corrected SQL (after seeing the diagnostic output):**

Once we know the actual format, write a `REGEXP_REPLACE` (MySQL 8+) that handles the variations. Will provide based on the diagnostic.

**Path 2 — Wait for the Block Migration tool in v4.5.0:**

The v4.5.0 Block Migration tool uses `parse_blocks` / `serialize_block` (proper block-aware) which sidesteps the SQL pattern-match problem entirely. If you'd rather wait ~1 cycle, the tool will handle this and all future content migrations cleanly.

**Recommended:** Path 2 (wait for the tool). The current v9.4.6 typography fix means the visual issue is already resolved — the WCAG 1.3.1 issue is the only remaining concern, and it's a known-deferred user-action item.

---

## Section 7: Task list state at session close

Tasks 1–17, 18 (project context read pass), 24 (read pass for spec) all **COMPLETED**.

Tasks 19–21 (brainstorm questions + approach proposal + design sections) all **COMPLETED**.

Task 22 (write spec + self-review + user review) is **IN_PROGRESS** — write + self-review done; user review pending (Step 1 of next session per §3 above).

Task 23 (transition to writing-plans) is **PENDING** — Step 2 of next session per §3 above.

---

## Section 8: Lessons captured this session

These are worth carrying forward (some already memory-bound):

1. **Verify-before-tag is non-negotiable.** v4.4.4 shipped a test failure that wasn't caught because plugin tests weren't re-run between edits and `git tag`. v4.4.5 fixed it + I owned the lapse in the CHANGELOG.

2. **Read sources, don't reason from memory.** Section 2 of the brainstorm was design-by-vibes until the user called it out. The rigorous read pass (gutenberg-block-authoring skill + 5 plugin source files + WP source for `serialize_block` + WP theme.json docs) produced Section 2 V2 which is dramatically more grounded. The discipline trail is preserved in spec §10 Verification grounding.

3. **String-mismatch in chained Edit + Bash sequences silently breaks the batch.** v9.4.5 had its CHANGELOG entry dropped from the tagged commit because the Edit failed (single missed word in old_string) but the downstream Bash proceeded with 4 files instead of 5. Recovery: follow-on CHANGELOG-only commit per "never re-tag" rule.

4. **Cache layers compound.** PA-01 SQL ran successfully (0 rows affected) but the visual issue persisted because Cloudflare + Breeze still served stale HTML. Always pair SQL writes with cache purge.

5. **Body heading scale needs re-tuning when post title scale changes.** v9.3.0 shrank `.sn-note-title` h1 but body h2/h3 (theme.json defaults) stayed at the catalog-hero scale. Caught + fixed in v9.4.6.

6. **Approach B (refinement + ONE net-new each) is the right shape for "minors approaching majors".** Pure refinement is sterile. Pure net-new burns prep time. One careful tool that solves a real problem encountered this session = best of both.

---

## One-line summary

**Session shipped 5 versions (Plugin v4.4.4 + v4.4.5; Theme v9.4.4 + v9.4.5 + v9.4.6) plus accessibility baseline doc plus full v5/v10 brainstorm culminating in the paired Plugin v4.5.0 + Theme v9.5.0 design spec — design-verified against source per HARD RULE discipline. Next session: user reviews the spec, then transitions to superpowers:writing-plans to produce one implementation plan per repo. Plugin ships first (v4.5.0 → v4.5.x cycle → gate), then theme (v9.5.0 BC convenes on plugin gate, ships, cycle, gate). v5.0.0 / v10.0.0 stay cap-dropped optional — convene only on actual breaking changes.**
