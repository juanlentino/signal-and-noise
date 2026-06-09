# Security Back-Audit Runbook

How to run a periodic whole-codebase security audit of Signal & Noise (theme +
plugin), using Anthropic's [defending-code-reference-harness](https://github.com/anthropics/defending-code-reference-harness)
interactive skills. This is the methodology that produced the
[2026-06-09 back-audit](superpowers/audits/2026-06-09-security-back-audit.md)
(8 confirmed findings → theme v9.15.3/v9.15.4 + plugin v4.14.2/v4.14.3).

## Where the harness lives

A **dedicated persistent clone** at `~/Projects/defending-code-reference-harness`
(sibling to the S&N repos). It is **not vendored into either product repo** — on
purpose:

- The product repos already gitignore `.claude/`, so a vendored copy would be
  invisible anyway, and committing it would bloat every WP-update zipball with
  Anthropic's (unmaintained) reference code.
- The `triage` / `threat-model` / `patch` skills invoke
  `python3 .claude/skills/_lib/checkpoint.py` as a **literal relative path from
  cwd**. They only resolve when Claude Code runs **from the harness root**. A
  cherry-pick into `~/.claude/skills/` breaks `_lib`; a vendored copy would
  require the product repo to be cwd *and* carry `_lib`. The dedicated clone is
  the only layout that just works.
- The autonomous `harness/` pipeline (Docker + gVisor + ASAN) is **C/C++
  memory-safety only** and irrelevant to a PHP codebase. We use only the
  interactive, read/write-only skills (`/threat-model`, `/vuln-scan`,
  `/triage`, `/patch`) — safe to run unsandboxed with per-tool approval.

To re-create the clone if it's gone:
`git clone https://github.com/anthropics/defending-code-reference-harness ~/Projects/defending-code-reference-harness`

## ⚠️ Audit the SHIPPED code, not the stale local checkout

The bare plugin checkout at `~/Projects/signal-and-noise-tools` is **pinned at
~v4.7.0 HEAD** even though origin/main ships much later (see memory
`reference_stale_plugin_checkout_grep_trap`). Always audit a **fresh worktree at
the shipped tag**, never the stale working tree:

```bash
cd ~/Projects/signal-and-noise-tools
git fetch origin --tags
git worktree add --detach /tmp/snt-audit <latest-plugin-tag>   # e.g. v4.14.3
```

The theme repo's working tree tracks `main` and is usually at the latest tag, so
auditing it in place (`.`) is fine — but verify `git describe --tags` first.

## Runbook (interactive skills)

```bash
cd ~/Projects/defending-code-reference-harness
export CLAUDE_CODE_SUBAGENT_MODEL=<model-id>   # pin every subagent
claude

# 0. orient (first time only)
> /quickstart

# 1. (optional) build/refresh a threat model per target
> /threat-model bootstrap-then-interview ~/Projects/signal-and-noise
> /threat-model bootstrap-then-interview /tmp/snt-audit

# 2. static scan each target (read-only; fans out one subagent per focus area)
> /vuln-scan ~/Projects/signal-and-noise
> /vuln-scan /tmp/snt-audit

# 3. triage — N-vote verify, dedupe, re-rank, route. THIS is where false
#    positives get removed; /vuln-scan never drops a finding.
> /triage ~/Projects/signal-and-noise/VULN-FINDINGS.json --repo ~/Projects/signal-and-noise --votes 5
> /triage /tmp/snt-audit/VULN-FINDINGS.json --repo /tmp/snt-audit --votes 5

# 4. (optional) draft candidate fixes for confirmed findings (read/write only)
> /patch ./TRIAGE.json --repo ~/Projects/signal-and-noise
```

Findings land as `VULN-FINDINGS.{json,md}` and `TRIAGE.{json,md}` **inside the
target dir** — they are gitignored in the product repos (`.claude/` is, but
these aren't; delete them or copy the audit report into
`docs/superpowers/audits/` and discard the raw artifacts).

## Headless / focused alternative (Workflow)

For a **delta audit** (just the security-fix commits since the last release) or
any non-interactive run, the same methodology runs as a `Workflow` from the
theme repo without the skills being registered: review each fix cluster → 3-lens
adversarial verify per finding (exploitability / existing-mitigation /
reachability; crashed lens ≠ refutation; split verdict → human review) →
completeness critic. See the `security-delta-audit` workflow script under the
session's `workflows/scripts/`. Prefer this when re-auditing *unchanged* code
would be redundant (e.g. right after a full sweep) — point fresh effort at the
changed surface, where new risk actually lives.

## Cadence & scope discipline

- A **full sweep** (`/vuln-scan` both repos) is for periodic baselines or after a
  large feature wave. Right after a clean full sweep, a second full sweep is
  busywork — audit the **delta** instead.
- The harness's own guidance: once a finding is fixed the model can't re-find it,
  so each wave surfaces net-new, deeper issues. Record prior findings (the
  `audits/` dir) so a new run steers toward what's *not* yet known.
- Every confirmed finding follows the project's normal release discipline:
  batched into one patch release per repo, TDD where behavioral, CHANGELOG +
  tag. Never ship per-fix.
