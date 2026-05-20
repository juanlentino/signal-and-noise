# Design — v2.5.0 Abilities-first refactor

**Date:** 2026-05-20
**Status:** Approved (in-session)
**Source:** Brainstormed via `superpowers:brainstorming` after the discovery that v2.3.0 + v2.4.0 had been tagged but never deployed to the live site, AND that WP 7.0's Armstrong release framing positions Abilities + Client-Side Abilities + Command Palette + AI Client as one coupled stack.

## Background

WP 7.0 ("Armstrong") shipped on 2026-05-20 with a coupled architecture:

- **AI Client** (`wp_ai_client_prompt()`) — provider-agnostic generative AI surface.
- **Connectors API** (`wp_register_connector`, `wp_get_connector`) — provider/credentials registry.
- **Abilities API** (`wp_register_ability`, `wp_register_ability_category`) — typed, schema-validated, permission-gated, annotation-tagged operations.
- **Client-Side Abilities** (`@wordpress/abilities` + `@wordpress/core-abilities`) — JS discovery + `executeAbility()` invocation with automatic REST routing per annotation (`readonly` → GET, `destructive+idempotent` → DELETE, else POST).
- **Command Palette** (`@wordpress/commands`) — JS-only registration via `wp.data.dispatch('core/commands').registerCommand()`. **Not** auto-populated from abilities.

The SN plugin's Phase 14 (v2.0.4) already registered 4 abilities. v2.3.0 + v2.4.0 (tagged but never installed on the live site) added Command Palette commands + AI generation features as **parallel surfaces** — manual `registerCommand` calls and one-off REST endpoints — bypassing the abilities layer that was already in place.

This refactor consolidates everything onto abilities.

### What v2.5.0 is NOT

- Not a user-visible feature change. The same surfaces (Cmd+K commands, AI buttons in the per-post meta box) work the same way.
- Not a theme change. Theme stays at v8.5.7.
- Not a breaking REST change. Legacy endpoints under `/signal-noise/v1/{cmd,ai}/*` continue to function — they're marked `@deprecated since 2.5.0` for internal hygiene but stay wired.

## Architecture

```
                       ┌─ wp-admin SN settings UI
                       │
[ 11 SN abilities ] ◀──┼─ ⌘K Command Palette (5 most-used)
   (Phase 14 + new)    │
                       └─ AI Client (Phase 16 chat — future)
```

Abilities are the **canonical action surface**. Three caller surfaces all converge on `executeAbility('signal-noise/X', input)`:

1. **wp-admin UI** — existing forms + meta-box buttons. Refactored from `wp.apiFetch({ path: '/signal-noise/v1/.../X' })` to `executeAbility('signal-noise/X', …)`.
2. **⌘K Command Palette** — 5 commands registered via `registerCommand`. Each command's `callback` calls `executeAbility(…)`.
3. **AI Client (future)** — once Phase 16's site-self-knowledge chat ships, the AI Client orchestrates abilities via natural language. No new code now; the ability registrations are the foundation.

## The 11 abilities

| Slug | Category | Permission | Annotations | Status |
|---|---|---|---|---|
| `signal-noise/purge-all-caches` | maintenance | `manage_options` | destructive, idempotent | existing (Phase 14) |
| `signal-noise/regenerate-og-card` | content | `edit_post` | idempotent | existing (Phase 14) |
| `signal-noise/get-deploy-status` | diagnostics | `manage_options` | readonly, idempotent | existing (Phase 14) |
| `signal-noise/clear-template-overrides` | maintenance | `manage_options` | destructive, idempotent | existing (Phase 14) |
| `signal-noise/list-template-overrides` | diagnostics | `manage_options` | readonly, idempotent | new — pairs with `clear-template-overrides` |
| `signal-noise/force-check-updates` | updates *(new category)* | `manage_options` | idempotent | new — replaces `POST /cmd/force-check` |
| `signal-noise/full-reset` | maintenance | `manage_options` | destructive, idempotent | new — replaces `POST /cmd/full-reset` |
| `signal-noise/get-rss-stats` | diagnostics | `manage_options` | readonly, idempotent | new — replaces `GET /cmd/rss-stats` |
| `signal-noise/ai-generate-meta-description` | ai-generation *(new category)* | `edit_post` | idempotent | new — replaces `POST /ai/generate-meta-description` |
| `signal-noise/ai-generate-og-card-title` | ai-generation | `edit_post` | idempotent | new — replaces `POST /ai/generate-og-card-title` |
| `signal-noise/ai-generate-excerpt` | ai-generation | `edit_post` | idempotent | new — replaces `POST /ai/generate-excerpt` |

**2 new categories:** `updates` (update-machinery operations) and `ai-generation` (AI Client-backed content generation).

### Annotation rationale

- `force-check-updates` is `idempotent` only — clearing the `update_themes` / `update_plugins` / `sn_gh_latest_*` transients forces the next page-load to refetch, but no user data is deleted. Calling twice is harmless.
- `full-reset` is `destructive + idempotent` — combines `purge-all-caches` (destructive — wipes caches) with `clear-template-overrides` (destructive — deletes DB rows). Both bits propagate.
- AI generation abilities are `idempotent` in the WP-Abilities sense ("safe to retry"). LLM output isn't deterministic but the *contract* is safe-to-retry — same input twice can't corrupt anything.
- `readonly + idempotent` for the three diagnostics — `executeAbility()` will route these to GET per annotation.

## PHP changes

### Modified files

**[inc/ai-bootstrap.php](https://github.com/juanlentino/signal-and-noise-tools/blob/main/inc/ai-bootstrap.php)** — fix the gate function per the official AI Client dev note (2026-03-24).

- Add `snt_ai_can_text_generate()` — builds a no-cost prompt with `using_temperature(0.7)` and calls `is_supported_for_text_generation()`. Returns `bool`. This is the canonical "is the AI Client wired up with at least one text-capable provider configured?" check.
- Keep `snt_ai_is_available()` for back-compat, but have it delegate to `snt_ai_can_text_generate()` so all existing call sites get the corrected behavior automatically.
- Update the docblock to reflect the dev note's explicit guidance ("`is_supported_for_text_generation()`, … — **not** `wp_has_ai_client()`").

**[inc/abilities-registration.php](https://github.com/juanlentino/signal-and-noise-tools/blob/main/inc/abilities-registration.php)** — add 7 new abilities + 2 new categories. Existing 4 abilities + 3 categories unchanged.

- Register `updates` category in `wp_abilities_api_categories_init`.
- Register `ai-generation` category in `wp_abilities_api_categories_init`.
- Register 7 new abilities in `wp_abilities_api_init`, each citing the appropriate category + annotation set + I/O schemas.
- Each execute callback delegates to an existing function (the cmd-handler functions already exist for the 3 cmd/* operations; the 3 AI generation operations get their existing implementation pulled out of the REST handler into a private impl function).

**[inc/desktop-mode-integration.php](https://github.com/juanlentino/signal-and-noise-tools/blob/main/inc/desktop-mode-integration.php)** — extract the cmd-handler bodies (force-check, full-reset, rss-stats sections of `snt_desktop_cmd_handler`) into private callable functions so the new ability execute callbacks can reuse them. One implementation, two callers (legacy REST + new ability). No behavior change to the REST endpoints.

**[inc/ai-meta-description.php](https://github.com/juanlentino/signal-and-noise-tools/blob/main/inc/ai-meta-description.php), [inc/ai-og-card-title.php](https://github.com/juanlentino/signal-and-noise-tools/blob/main/inc/ai-og-card-title.php), [inc/ai-excerpt.php](https://github.com/juanlentino/signal-and-noise-tools/blob/main/inc/ai-excerpt.php)** — each gets a private `snt_ai_*_impl()` function that wraps the existing generation logic. The REST handler becomes a 3-line delegator that calls `snt_ai_*_impl()` then returns the result. The ability execute callback also calls `snt_ai_*_impl()`. One generator, two callers. Add `@deprecated since 2.5.0` to the REST endpoint docblock.

### Net file impact

| File | LOC delta | Nature |
|---|---|---|
| `inc/ai-bootstrap.php` | +20 | Gate function correction + new helper |
| `inc/abilities-registration.php` | +180 | 7 new abilities + 2 new categories |
| `inc/desktop-mode-integration.php` | +60, -30 | Extract impl functions; REST handler shrinks |
| `inc/ai-meta-description.php` | +20, -10 | Impl extraction; REST handler shrinks |
| `inc/ai-og-card-title.php` | +20, -10 | Same |
| `inc/ai-excerpt.php` | +20, -10 | Same |
| **Total PHP** | **~+260 LOC** | |

## JS changes

### Modified files

**[assets/command-palette.js](https://github.com/juanlentino/signal-and-noise-tools/blob/main/assets/command-palette.js)** — each command callback swaps `wp.apiFetch({ path: '/signal-noise/v1/cmd/X', method: 'POST' })` for `executeAbility('signal-noise/X', input)`.

- Add `await initialize()` from `@wordpress/core-abilities` on first command invocation (per the package README's "Call initialize() from the feature that needs abilities — for example, when the workflow palette opens" guidance).
- The `runRest()` helper becomes `runAbility()` — same shape, different transport.
- Snackbar feedback via `core/notices` stays unchanged.

**[assets/ai-meta-description.js](https://github.com/juanlentino/signal-and-noise-tools/blob/main/assets/ai-meta-description.js), [assets/ai-og-card-title.js](https://github.com/juanlentino/signal-and-noise-tools/blob/main/assets/ai-og-card-title.js), [assets/ai-excerpt.js](https://github.com/juanlentino/signal-and-noise-tools/blob/main/assets/ai-excerpt.js)** — same swap. Each file's `wp.apiFetch` block becomes `executeAbility('signal-noise/ai-generate-...')`. DOM injection + button UI + status-row pattern unchanged.

### Dependency chain

Each affected JS file adds `wp-abilities` + `wp-core-abilities` to its `wp_register_script()` dep array. WP 7.0 ships both as registered handles; the dep is silent no-op on WP 6.x (defensive bail in JS still required).

### Net JS impact

| File | LOC delta | Nature |
|---|---|---|
| `assets/command-palette.js` | +40, -30 | Net +10; runRest → runAbility |
| `assets/ai-meta-description.js` | +15, -10 | Net +5 |
| `assets/ai-og-card-title.js` | +15, -10 | Net +5 |
| `assets/ai-excerpt.js` | +15, -10 | Net +5 |
| **Total JS** | **~+25 LOC** | |

## What's NOT changing

- No theme changes (theme stays at v8.5.7).
- No CSS changes.
- No new DOM markup in `inc/post-settings.php` (already correctly extended in v2.3.0/v2.4.0 commits).
- No removal of `/signal-noise/v1/cmd/*` or `/signal-noise/v1/ai/*` REST endpoints — they still work, just marked `@deprecated`.
- No change to the `_sn_og_card_title` post meta or the `sn_og_card_title` filter — v2.3.0's substrate stays.
- No change to existing Phase 14 ability registrations.

## Versioning

**Plugin v2.5.0** — fills the last v2.x minor slot. The 5/major cap is exactly hit, so the next minor after v2.5.0 rolls to v3.0.0.

The v2.3.0 + v2.4.0 tags remain on origin as historical stepping stones — they were tagged but never installed; CHANGELOG documents this. The v2.5.0 CHANGELOG entry consolidates: the abilities-first refactor, the gate-function correction, the new categories, the deprecation note for legacy REST endpoints.

## Verification plan

**This is the gate against repeating the v2.3.0/v2.4.0 "shipped to origin, never installed" failure.** No completion claims without these checks passing.

After git push + tag push + user installs via wp-admin → Updates UI:

```bash
# 1. v2.5.0 actually on disk:
curl -sI "https://juanlentino.com/wp-content/plugins/signal-and-noise-tools/assets/command-palette.js" | head -1
#    expect: HTTP/2 200

# 2. All 11 abilities registered + visible via REST:
curl -s "https://juanlentino.com/wp-json/wp-abilities/v1/abilities" \
  | python3 -c "import sys,json; print('\n'.join(sorted(a['name'] for a in json.load(sys.stdin) if a['name'].startswith('signal-noise'))))"
#    expect: 11 lines, all starting with signal-noise/

# 3. AI gate function returns correct value on prod:
#    Smoke-test: edit any post → SN meta box → "Generate with AI"
#    button next to Meta description. If button renders + click produces
#    a 140-160 char description in ~3-5s, the gate is correct + provider
#    is wired.

# 4. Command Palette command runs end-to-end via executeAbility path:
#    Press ⌘K → run "SN: Show deploy status" → snackbar shows current versions.
#    (Verifies executeAbility round-trip + core-abilities initialize() worked.)

# 5. Legacy REST endpoint still 200s (back-compat):
curl -sI "https://juanlentino.com/wp-json/signal-noise/v1/cmd/status"
#    expect: HTTP/2 401 (auth required — correct; means endpoint exists)
```

If any check fails, the install needs investigation BEFORE another tag.

## Risks + mitigations

| Risk | Mitigation |
|---|---|
| `@wordpress/core-abilities` `initialize()` race — multiple AI buttons all call it on click before the first resolves | Per the README, `initialize()` is idempotent: "Repeated calls return the same in-flight or resolved promise." No mitigation needed. |
| Ability execute callbacks duplicate input validation already done by `input_schema` | Don't re-validate. The abilities API validates input against `input_schema` BEFORE calling the permission_callback or execute_callback. Trust the framework. |
| Legacy REST endpoints + new abilities expose the same operation twice — risk of one being "fixed" while the other isn't | Single-source-of-truth: each operation has ONE impl function; both surfaces call it. Defense lives in the impl. |
| `@wordpress/core-abilities` script handle name may differ from `wp-core-abilities` | Verify the actual registered handle via `wp_scripts()` debug or by inspecting `wp-includes/script-loader.php` source. Pin the correct handle in `wp_register_script` deps. |
| Plugin still won't install via WP-UI Updates if previously failed | Manual install path: `gh workflow run deploy.yml --repo juanlentino/signal-and-noise-tools --ref v2.5.0`. Workflow now does `git reset --hard` before checkout (v2.0.3+) to survive dirty trees. |

## Implementation sequence (high-level — full plan in writing-plans phase)

1. Verify the actual registered handle for `@wordpress/core-abilities` against WP 7.0 source.
2. Extract impl functions from REST handlers (no behavior change yet).
3. Register the 2 new categories.
4. Register the 7 new abilities.
5. Refactor the 4 JS files to use `executeAbility`.
6. Bump version, CHANGELOG, commit, tag, push.
7. **Wait for user-driven install via wp-admin → Updates UI.**
8. Run verification curls.
9. Update memory + handoff.

The writing-plans skill will turn this into atomic commits + sequenced tasks.
