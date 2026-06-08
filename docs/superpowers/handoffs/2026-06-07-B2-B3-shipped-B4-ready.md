# Handoff — Track B2 + B3 SHIPPED; B4 grounded & ready (2026-06-07)

**Read this first.** Long execution session continuing the [upgrade-opportunities roadmap](../specs/2026-06-06-upgrade-opportunities-roadmap.md) (locked A→B→C, no majors this pass). Shipped **four releases**. B4 is grounded and ready to design+build.

## What SHIPPED this session (all tagged; none auto-deploy → wp-admin → Updates)
| Release | Repo | Tag | Notes |
|---|---|---|---|
| **plugin v4.10.0** | tools | `v4.10.0` (`2ec2018`) | Track B1 bundle 2: multi-event webhooks, `list-abilities`, audit CSV/JSON export, Privacy exporter/eraser + policy text, Speculation Rules |
| **plugin v4.10.1** | tools | `v4.10.1` (`26905b6`) | post-review fixes: webhook `post.deleted` single-fire (HIGH), CSV formula-injection (security), abilities test-fidelity, i18n domain |
| **plugin v4.11.0** | tools | `v4.11.0` (`3543a45` on tools/main) | Track B2 "Editor UX + frugal AI": insights body-grounding (0 new calls), pre-publish gate (reactive `useSelect` — review caught a stale-snapshot HIGH), ⌘K expansion, AI release-notes drafter (Tools sub-tab + ability), insights "Create draft" |
| **theme v9.10.0** | theme | `v9.10.0` (`489d586` on theme/main) | Track B3 "Reader experience I": Related Notes footer, print/PDF, "Updated" date, copy/Web-Share, Monolith+High-Contrast variations, Hairline+Signal block styles, curated palette, caption/cite. **Merged onto the user's v9.9.1** (see below). |

## ⚠ Pending installs
Plugin: **v4.6.0 → v4.11.0** (install v4.11.0 = everything). Theme: **v9.10.0** (includes the user's v9.9.1).

## ⭐ NEXT: build **B4** (theme "Feeds, blocks & pages" → theme v9.11.0)
- **Grounded** (workflow `wf_6664841d-bbb`, 2026-06-07). 5 solid specs + 1 to redo. Items + key findings:
  - **JSON Feed** (M): `add_feed('json')`; `?feed=json` works immediately (theme must NOT flush rewrites — the PLUGIN owns the permalink structure + the only flush); pretty `/feed/json/` needs a flush. Auto-tracked by the plugin's RSS-Plausible tracker (fires on any `is_feed()`) for free.
  - **RSS enrichment** (S): new `inc/feed-enrichment.php`; `rss2_ns` (media namespace) + `rss2_item` → `media:content` + reading-time/tags, all `function_exists`-guarded.
  - **Sidenote + pull-quote blocks** (M): ⚠ **roadmap wording wrong** — there is NO "WP 7.0 autoRegister" core feature; use `register_block_type_from_metadata` (block.json + render.php + buildless ES5 editor.js). The theme already has a pull-quote *pattern* → keep+annotate, the BLOCK supersedes it.
  - **Block Bindings** (M): one `signal-noise/post-field` source (reading-time/pillar/canonical/og-title) via `register_block_bindings_source`. Migrate ONLY `parts/post-frontmatter.html` now — explicitly NOT the v9.10.0 shortcodes (D4 fork: would collide). The eventual shortcode-replacement path.
  - **/colophon** (S): FSE `customTemplates` + an editable pattern, static factual content (anti-self-promotion brand line), quiet footer link; defer `/now`.
  - **Reader-facing ⌘K palette** (M): the grounding agent for this item FAILED (returned a placeholder) — **re-ground this one item** (vanilla-JS reader overlay, distinct from the plugin's wp-admin ⌘K; focus trap + Escape + aria). The full grounding output is at `/private/tmp/.../tasks/wjd7ilotj.output` (run `wf_6664841d-bbb`) — but it's session-temp; re-run the B4 grounding workflow (script saved) if gone.
- **Resume recipe** (the proven cycle, used 4× this session): new theme worktree off theme `main` (v9.10.0) → composer install → baseline sweep → brainstorm/present design (forks above) → plan → build-workflow (sequential TDD implementers + gate) → review-workflow (per-task spec+adversarial + refute-by-default ×3 + completeness critics) → fix confirmed findings → **gate the tag on the main push** → ship.

## 🔑 Key learnings this session (IMPORTANT)
1. **FSE shortcodes resolve via CORE, not bridges.** `get_the_block_template_html()` + `render_block_core_template_part()` both run `shortcode_unautop()`+`do_shortcode()` before `do_blocks()`. The theme's render_block bridges are belt-and-suspenders. This false premise (templates "don't resolve shortcodes") propagated through a B3 grounding→build→review and produced **two confirmed-but-WRONG findings** (a `[sn_post_pillar]` "raw render" HIGH + a "<p>-wrap" MED); the user's **live verification** + their v9.9.1 caught it. See `[[reference_fse_shortcodes_resolve_via_core]]`. **Lesson:** the adversarial review can be confidently wrong when the *grounding* premise is wrong — verify framework contracts at grounding, and trust live observation over source-reasoning chains.
2. **User interrupts kill running background workflows.** Two interrupts this session killed the B3 build (mid-T1) + the B4 grounding (all 6 agents). Recovery: per-task commits + re-launch via `scriptPath` (resume skips committed tasks). Don't fan out alongside a build you can't afford to lose.
3. **Gate the tag on the main push.** A v9.10.0 tag was pushed after a *failed* non-FF main push (origin/main had advanced to the user's v9.9.1). Fixed by deleting the tag, `git merge origin/main` into the worktree (adopt 9.9.1's canonical pillar bridge `--theirs`, de-dup my redundant FX1, stitch + correct the CHANGELOG), re-verify, then push main → tag only on success.
4. **phpcs `--parallel=8` shows BATCHES not files** ("8/8" ≠ 8 files). Falsify lint with an injection. `[[reference_phpcs_parallel_batches_not_files]]`.

## Track status (A→B→C locked)
- **Track A — DONE** (plugin v4.8.1).
- **Track B1 — DONE** (plugin v4.9.0 + v4.10.0 + v4.10.1).
- **Track B2 — DONE** (plugin v4.11.0).
- **Track B3 — DONE** (theme v9.10.0).
- **Track B4 — grounded, NEXT** (theme v9.11.0; see above). After B4: **B5 frontier minors** (404 recovery, in-article TOC, IndexNow — each its own brainstorm) + the **edge-cache perf lever** (strip empty Set-Cookie). Then **Track C majors** (plugin v5.0.0 + theme v10.0.0 cleanup chain).

## Docs reconciled this session
- upgrade-opportunities-roadmap: Track A/B1/B2/B3 marked shipped. master-execution-sequence shipped-log: B2/B3 to add (B1 done earlier). This handoff supersedes `2026-06-07-v4.10.0-v4.10.1-shipped.md`.
