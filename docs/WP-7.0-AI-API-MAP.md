# Signal & Noise — WordPress 7.0 AI Client API map + Phase 7/12/14 plan

**Date:** 2026-05-17
**WP 7.0 release:** 2026-05-20 (3 days out)
**Status:** Pre-staging — plugin v1.16.0 ships function_exists-gated scaffolding that activates the moment 7.0 lands

This doc is the **execution reference** for the AI-features arc. It supersedes the "Skip AI Client entirely" stance in `docs/WP-API-MAP.md` lines 22 + 53-55 (those were written when the theme was a single-author surface with no admin-side workflows — the v8.5 + plugin v1.10+ work created exactly the admin workflows that benefit from AI assistance).

Verified against the **actual source** of every primitive used here, not handbook prose:
- [WordPress/ai](https://github.com/WordPress/ai) — meta-plugin (alt text, title gen, etc.)
- [WordPress/php-ai-client/src/AiClient.php](https://github.com/WordPress/php-ai-client/blob/main/src/AiClient.php) — fluent SDK
- [WordPress/wp-ai-client/autoload.php](https://github.com/WordPress/wp-ai-client/blob/main/autoload.php) — WP-side wrapper + 7.0 detection
- [WordPress/ai-provider-for-anthropic](https://github.com/WordPress/ai-provider-for-anthropic) — Anthropic connector
- [WordPress/abilities-api](https://github.com/WordPress/abilities-api) — Phase 14 target
- WP make blog: [merge proposal](https://make.wordpress.org/core/2026/02/03/proposal-for-merging-wp-ai-client-into-wordpress-7-0/), [intro post](https://make.wordpress.org/core/2026/03/24/introducing-the-ai-client-in-wordpress-7-0/), [7.0 Field Guide](https://make.wordpress.org/core/2026/05/14/wordpress-7-0-field-guide/)

## What's in 7.0 core (no install needed)

| Surface | Provided by core 7.0 | Notes |
|---|---|---|
| `wp_ai_client_prompt( $text )` | The `wordpress/wp-ai-client` Composer package, bundled in core with scoped PSR deps | Returns `Prompt_Builder_With_WP_Error` — catches SDK exceptions, returns `WP_Error` on failure |
| `AiClient::prompt( $text )` | Bundled `WordPress\AiClient` namespace from `wordpress/php-ai-client` | Fluent API; throws exceptions (use the WP wrapper above for WP-idiomatic error handling) |
| `wp_has_ai_client()` | Bundled in `wp-ai-client/functions.php` | **THE canonical compatibility check** — returns `true` on 7.0+ (or 6.x with the wp-ai-client plugin active) |
| `Settings > Connectors` admin UI | Core 7.0 | Where users configure provider API keys |
| Connectors framework | Core 7.0 | Provider plugins register here |
| Abilities API (PHP + JS) | Core 7.0 | Server-side ability registration + client-side discovery/invocation |
| `WP_AI_Client_Prompt_Builder` class | Core 7.0 | The class returned by the procedural wrapper |

## What's NOT in 7.0 core (install separately)

| Component | Install via | When |
|---|---|---|
| **Anthropic provider** (`WordPress/ai-provider-for-anthropic`) | wp-admin → Plugins → Add New → search "ai provider anthropic" | **Phase 7 (May 21+)** — required for SN's AI features |
| OpenAI / Google / Ollama providers | Same | Optional alternates — we standardize on Anthropic per [feedback memory](https://github.com/juanlentino/signal-and-noise/blob/main/...) |
| `WordPress/ai` feature plugin | Optional install | Provides generic features (alt text, title gen) — see "Hybrid split" below |

## Fluent API surface (verified from `src/AiClient.php`)

```php
$result = wp_ai_client_prompt( 'Generate something' )
    ->using_provider( 'anthropic' )       // optional — pick provider explicitly
    ->using_model( $model )                // optional — specific model instance
    ->using_system_instruction( $sys )     // optional — system prompt
    ->using_max_tokens( 150 )              // optional — output cap
    ->generate_text();                     // string | WP_Error

// Also: ->using_model_config( $config ), ->using_temperature( $temp )
// Also: ->generate_text_result() returns full result object
// Also: ->generate_image() / ->generate_image_result() for image gen
```

**Naming convention:**
- WP-idiomatic procedural path (`wp_ai_client_prompt()`) returns the WP wrapper, methods are `snake_case` (`using_system_instruction()`)
- OO fluent path (`AiClient::prompt()`) uses `camelCase` (`usingSystemInstruction()`)
- Pick one and stay with it per file. SN code uses the WP-idiomatic path.

## Provider-agnostic by design — DO NOT hardcode model/sampling

Per the [Anthropic claude-api skill](https://docs.anthropic.com) reading: `temperature`, `top_p`, `top_k` are **removed from Claude Opus 4.7** and will 400. They're permitted on older models but the rule generalizes — **don't set sampling parameters** for provider-agnostic code:

- Tomorrow the user might swap to OpenAI or Google
- The WP AI Client routes the request through whatever provider is configured in `Settings > Connectors`
- Each provider has different sampling defaults + different parameter-acceptance rules
- Setting `temperature` in our code = locking the user to a specific provider's parameter shape

**What to use instead:**
- `using_system_instruction()` for tone/format constraints (works on every provider)
- `using_max_tokens()` for output bounds (universal)
- Prompt engineering for everything else

This is the same discipline as the v1.14.0 admin redesign: **work with the platform's abstraction, don't fight it**.

## SN-specific use cases (Phase 12)

Three concrete features, ranked by clarity-of-need:

### 1. Meta description generation — `_sn_meta_description`

The most obvious gap. Per-post meta box (since v1.10.0) has a textarea for `_sn_meta_description` that overrides excerpt for `<meta>`, OG, Twitter, JSON-LD. Adding a "Generate with AI" button fills that field from post content.

**Prompt shape:**

```
SYSTEM: Generate a meta description for SEO. Output 140–160 characters.
Active voice. No marketing fluff (avoid: amazing, powerful, ultimate, best,
revolutionary). Capture the single most useful thing a search-result reader
would want to know about this content. Output ONLY the description text —
no quotes, no preamble, no labels.

USER: <post content, truncated to ~1000 words>
```

**Why this works:** SEO meta descriptions are short, structured, low-creativity. Every provider does this well. Output is always ~150 chars regardless of provider. Truncation at 1000 words = ~1200-1400 tokens input = pennies per generation across all providers.

**Implementation:** see `inc/ai-meta-description.php` in plugin v1.16.0+.

### 2. OG card title generation (deferred — v1.17.0+)

OG cards currently use `get_the_title()` verbatim. Sometimes a punchier card-friendly title (60-90 chars, less formal) shares better than the article's headline. Adds an `apply_filters('sn_og_card_title', $default, $post_id)` callback that uses AI to rewrite if no override is set.

**Why deferred:** lower urgency than meta description. The OG card system already works fine with post_title. Ship after meta description proves the pattern + after WP 7.0 launches.

### 3. Per-post "tag suggestions" (v1.18.0+, speculative)

The /notes archive could benefit from auto-tagging if the tag taxonomy ever grows. Currently low priority because the site has few tags + small notes volume.

## SN x WP/ai split (hybrid model)

Per feedback (this session, hybrid recommendation approved): install **`WordPress/ai`** alongside SN for generic features, **SN's plugin** owns SN-specific features:

| Feature | Owner | Why |
|---|---|---|
| Alt text on image upload | `WordPress/ai` | Maintained by WP core team, generic enough that we have no special opinion |
| Title suggestion | `WordPress/ai` | Same |
| Comment moderation | N/A — site doesn't allow comments | — |
| **Meta description gen → `_sn_meta_description`** | SN plugin | SN-specific field; needs to fill our per-post meta, not WP's excerpt |
| **OG card title gen** | SN plugin (future) | Bound to our OG card generator, not WP-native |
| Abilities API registration | SN plugin (Phase 14) | We register our own actions (`regenerate_og_card`, `purge_caches`) |

WP/ai is marked experimental ("features may change, move, or break"). Hybrid model means our SN code doesn't depend on WP/ai — if it breaks we lose the generic features but our SN-specific ones keep working.

## Phase 7 — WP 7.0 upgrade run-book (May 21)

In sequence, on the live site:

1. **Pre-flight** (per [`docs/WP-7.0-CHECKLIST.md`](WP-7.0-CHECKLIST.md)): check release status, snapshot Cloudways, verify SN cache state.
2. **Update WP core** — wp-admin → Updates → click Update. Wait for ~7.0 or 7.0.1.
3. **Install Anthropic provider:**
   - wp-admin → Plugins → Add New → search "AI Provider for Anthropic" → Install + Activate
   - Or via WP-CLI: `wp plugin install ai-provider-for-anthropic --activate`
4. **Configure Anthropic key:**
   - Settings → Connectors → Anthropic → paste API key
   - Get an Anthropic API key from [console.anthropic.com](https://console.anthropic.com) → Settings → API Keys → Create Key. Save to a password manager.
5. **(Optional) Install WordPress/ai feature plugin** for the generic features (alt text, title gen). wp-admin → Plugins → Add New → search "AI for WordPress" → Install + Activate.
6. **Verify** by hitting the SN plugin's Dashboard tab — the "External APIs" line should show `Anthropic: configured` (or similar — the API rate monitor will catch the new host after the first outgoing request).
7. **Test the Phase 12 button:** edit any post → SN meta box → "Generate with AI" button next to the meta description textarea → expect a 140-160 char description in ~3-5 seconds.

If step 7 fails: check `wp-content/debug.log` for errors. Most common: API key wrong / not saved (Settings → Connectors → Anthropic shows red), or rate limit hit (the api-rate-monitor email warning would have fired).

## Phase 12 — AI-assisted features (already pre-staged in plugin v1.16.0)

Plugin v1.16.0 ships with the meta description generation feature **dormant** (gated behind `wp_has_ai_client()` which returns false on 6.9). It activates the instant WP 7.0 is installed + Anthropic provider is configured.

**Files (plugin v1.16.0):**
- `inc/ai-bootstrap.php` — shared `wp_has_ai_client()` gate + helper `snt_ai_generate_with_constraints()`
- `inc/ai-meta-description.php` — REST endpoint + meta-box button injection
- `assets/ai-meta-description.js` — button click → `wp.apiFetch` → fill textarea

## Phase 14 — Abilities API registration (post-Phase 12)

WP 7.0's Abilities API lets plugins register named typed actions that AI assistants can discover + invoke. Per the [abilities-api README](https://github.com/WordPress/abilities-api): "discovery, permissioning, and execution metadata only — actual business logic stays inside the registering component."

SN has obvious candidates (already implemented as pure functions or filter hooks):
- `sn_regenerate_og_card( post_id )` — current OG card generator
- `sn_purge_all_caches()` — existing filter (`sn_purge_all_caches_result`)
- `sn_clear_template_overrides()` — existing filter
- `sn_get_deploy_status()` — already in `inc/admin-tab-dashboard.php`

Phase 14 = thin registration glue. Estimated ~80 LOC. Ships as plugin v1.17.0 or v1.18.0 depending on what else lands first.

## Versioning impact

| Repo | Pre-WP-7.0 | Post-WP-7.0 |
|---|---|---|
| Theme | v8.5.4 — no AI code; theme stays presentation-only per architecture | unchanged |
| Plugin | **v1.16.0** — Phase 12 meta-desc scaffold (dormant on 6.9) | v1.17.0+ for next AI feature |
| WP core | 6.9 | 7.0 (or 7.0.1 if first-weekend patch ships) |
| WP plugins installed | — | + `ai-provider-for-anthropic` (required) + `ai` (recommended) |

## Open questions for May 21

These don't block the scaffolding but should be decided post-launch:

1. **WP/ai install** — confirm install once the plugin's 7.0-compatibility is verified. If it errors or conflicts, defer until they patch.
2. **Anthropic model choice** — the plugin doesn't pin a model; provider plugin defaults apply. If we want a specific Claude model, we'd add `->using_model( $model_id )`. Recommend NOT pinning — let Anthropic provider pick the default, swap if defaults change.
3. **Token budget caps** — Anthropic 5000/h authenticated bucket (separate from GitHub bucket) is more than enough for personal-site usage. If we ever hit it, add a rate-limit reading to the api-rate-monitor.
4. **AI-assisted alt text** — install WP/ai first, evaluate quality, then decide if our existing `_sn_og_image_url` workflow needs anything more.

## Skipped (with reason — don't re-evaluate without these specific triggers)

- **Programmatic Tool Calling / Tool Use** — SN doesn't have an agentic workflow on the WP side. Reconsider when there's a use case for an autonomous loop (e.g., automated content tagging at scale).
- **Streaming responses** — meta description is 150 tokens, fits in a single response well under timeout. No streaming needed.
- **Prompt caching** — the per-post-content input changes every request, no cacheable prefix. The system instruction is small (<200 tokens) — under the cache minimum.
- **Multimodal (image input)** — alt text generation needs this, but that's WP/ai's job per the hybrid split.
- **Tool definitions / function calling on our side** — Abilities API (Phase 14) is the WP-native way to expose actions to AI; we don't need to roll custom tool schemas.

This doc lives in the theme repo (alongside the canonical reference docs `WP-API-MAP.md`, `WP-7.0-CHECKLIST.md`, `WORDPRESS-REFERENCE.md`). Update after Phase 7 lands with actual measurement (which features shipped, what broke, what was easier than expected).
