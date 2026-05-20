# Phase 16 — opt SN commands into desktop-mode AI Copilot

**Date:** 2026-05-20
**Status:** Approved
**Brainstormed via:** `superpowers:brainstorming` after the user pushed back on a chatbot framing + pointed out that `WordPress/desktop-mode` (already installed) ships its own AI Copilot.

## Why this exists in 10 LOC instead of 600

The original Phase 16 design was a custom chat UI + AI Client wrapper + 6 new read-only abilities + tool-use loop + history persistence — ~600 LOC. **All of it is already provided by `WordPress/desktop-mode`:**

- `wp.desktop.ai.ask(query, opts)` — programmatic AI access (since 0.17.0)
- ⌘K spotlight overlay — natural-language input → command dispatch
- `/ai/search` server endpoint — AI provider integration owned by desktop-mode
- Built-in tools: `search_posts`, `search_pages`, `search_comments` — handles "what posts did I publish last month" out of the box
- `aiCallable: true` per-command opt-in — the only flag we need to set
- `desktop_mode_ai_command_allowed` PHP filter — per-user gating (we don't need it; all SN commands are admin-only)

Reference: [desktop-mode docs/javascript-reference.md `wp.desktop.ai.ask`](https://github.com/WordPress/desktop-mode/blob/trunk/docs/javascript-reference.md) — verified 2026-05-20.

## Scope

10 of our 13 desktop-mode-registered SN commands opt into AI invocation. 3 stay manual-only because they're destructive enough that "typing the command explicitly" IS the safety check.

| Command | aiCallable | Reason |
|---|:---:|---|
| `sn-cmd-force-check` | ✅ | Idempotent; only clears transients. |
| `sn-cmd-purge-caches` | ❌ | Destructive (wipes Breeze/Varnish/CF caches). |
| `sn-cmd-clear-overrides` | ❌ | Deletes DB rows. |
| `sn-cmd-full-reset` | ❌ | Combination of both above. |
| `sn-cmd-nav-dashboard` through `sn-cmd-nav-reading-time` (7 nav commands) | ✅ | Navigation only — no state change. |
| `sn-cmd-version-theme` | ✅ | Read-only info toast. |
| `sn-cmd-version-plugin` | ✅ | Read-only info toast. |

## Files

| File | Change | LOC |
|---|---|---|
| `assets/desktop-mode.js` | Add `aiCallable: true` to 10 of 13 `wp.desktop.registerCommand()` calls | +10 |
| `signal-and-noise-tools.php` | Bump version | 2 |
| `CHANGELOG.md` | New entry | ~30 |

Total: ~10 LOC of behavior change. No PHP changes. No new files.

## Versioning

Plugin **v2.5.5** (patch — 5 of 7 in the v2.5.x patch budget). Stays in v2.5.x. Theme unchanged.

## Verification (per `verification-before-completion`)

After install:
1. Press ⌘K → desktop-mode AI overlay opens
2. Type "force-check for updates" → expect AI invokes `sn-cmd-force-check`, transients clear, toast appears
3. Type "show me the plugin version" → expect version toast with v2.5.5
4. Type "go to the RSS tab" → expect navigation to wp-admin → S&N → RSS
5. Type "purge all caches" → expect AI says it can't (or routes to chat-style answer suggesting manual ⌘K) since command isn't aiCallable

If any fail, the diagnostic is on desktop-mode's `ai.ask()` side; check browser console for `[desktop-mode]` log entries.

## Out of scope (queued for future sessions)

- Per-command toggle UI in SN admin (let user opt destructive commands in/out)
- Custom system prompt override giving the AI SN context (music producer site, brutalist theme)
- Server-side abilities orchestration via `desktop_mode_register_ai_tool()` (richer than the JS-side command path; lets AI invoke the 11 abilities directly without going through commands)

## Process

Followed `superpowers:brainstorming` → confirmed correct via reading FULL source of:
- `WordPress/desktop-mode/docs/javascript-reference.md` (the `wp.desktop.ai.ask` API)
- `WordPress/desktop-mode/docs/hooks-reference.md` (the `aiCallable` flag + `desktop_mode_ai_command_allowed` filter)
- `WordPress/desktop-mode/includes/ai-copilot/` (the server-side AI integration directory exists)
- `WordPress/desktop-mode/src/ai-assistant/` (the overlay UI)

Per the project memory hard rule [`feedback_skills_plugins_docs_always`](../../.claude/projects/-Users-juanlentino-Projects-signal-and-noise/memory/feedback_skills_plugins_docs_always.md).
