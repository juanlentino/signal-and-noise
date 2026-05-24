# Theme AI Abilities — Design Spec

**Date:** 2026-05-24 (architecture re-evaluated in same session: theme-owned, not plugin-proxied)
**Status:** Approved (brainstorm complete; ready for plan-phase)
**Target releases:** theme v9.1.0 (primary), plugin v3.7.4 (Command Palette + JS render mode only)
**Phase context:** Post-v3.7.3 maintenance pass — net-new feature work that adds the theme as a first-class WP 7.0 Abilities API surface

---

## 1. Goal

Make the Signal & Noise theme a "super theme" by exposing its design knowledge and theme-native generative capabilities to the WordPress 7.0 Abilities API. Today, every SN AI feature is **brand-blind** — it generates content (meta descriptions, OG titles, Insights, drift findings) without knowing the brand's actual visual identity or content structure.

After this work, any AI consumer (the plugin's own AI features, the WP 7.0 AI Copilot, WP-CLI AI agents, future integrations) can:

1. **Read** the theme's design tokens, patterns, template structure, and content metadata as context
2. **Generate** brand-voiced content (page-note summaries, pattern suggestions, brand-alignment reports) that uses the theme's actual identity rather than generic defaults

All operations are **non-destructive** — read-only or generative. No theme.json mutations, no pattern registration changes, no file writes.

---

## 2. Scope

### In scope (12 abilities, all non-destructive, plus Command Palette + WP-CLI integration)

**Read abilities (7) — registered in theme:**
1. `signal-noise/get-design-tokens`
2. `signal-noise/list-block-patterns`
3. `signal-noise/get-active-template-structure`
4. `signal-noise/get-theme-version`
5. `signal-noise/get-page-notes-pillars`
6. `signal-noise/get-reading-time-for-slug`
7. `signal-noise/get-design-system-summary` *(formatter for #1, optimized for AI-prompt embedding)*

**Generative abilities (5) — registered in theme; call plugin's AI helper:**
8. `signal-noise/ai-generate-page-note-summary`
9. `signal-noise/ai-suggest-block-pattern`
10. `signal-noise/ai-validate-brand-alignment`
11. `signal-noise/ai-generate-pattern-content` *(fill a chosen pattern's content shell with topic-specific copy)*
12. `signal-noise/ai-rewrite-in-brand-voice` *(transform external copy → SN voice)*

**Additional entry-point integration (Section 11):**
- Command Palette (⌘K) registration for all 12 abilities — commands registered in plugin's `inc/desktop-mode-integration.php`, dispatch to theme-registered abilities
- WP-CLI access via `wp ability run signal-noise/*` (automatic; WP 7.0 abilities-api exposes any registered ability to wp-cli regardless of where it's registered)
- AI Copilot consumption via existing `aiCallable: true` flag on the ⌘K commands

### Out of scope

- Anything mutating theme.json, patterns, templates, or files (Phase 3 only)
- Anything overlapping `ai/ai` plugin v1.0.0's editorial features (alt text generation, generic summarization, content classification, image generation, comment moderation) — `ai-rewrite-in-brand-voice` is NOT overlap because `ai/ai`'s Editorial Notes do grammar/SEO/readability/a11y, not voice-transformation
- SSH or filesystem operations
- Multi-site / network-level abilities
- Streaming outputs for generative abilities (none of the 5 generative abilities have outputs long enough to warrant it today; revisit if average response time exceeds ~10s)

### Out of scope for v3.7.4/v9.1.0, possible future phases

- Theme-mutating abilities (would need destructive-op guards) — Phase 3
- FSE template synthesis ("create a new template part for X") — Phase 3
- Theme.json color-palette suggestions — Phase 3
- Streaming generative outputs — Phase 4 (when there's a real need)

---

## 3. Architecture — Theme-owned, plugin provides supporting infrastructure

The theme registers all 12 abilities directly via `wp_register_ability`. The plugin provides three supporting pieces: (a) the AI helper function `snt_ai_generate_with_constraints()` that generative abilities call, (b) the Command Palette commands that dispatch to the abilities, (c) the new `input-then-result` ⌘K render mode in `assets/desktop-mode.js`.

```
┌────────────────────────────────────────────────────────────────┐
│  signal-and-noise (theme) — owns ability registration          │
│                                                                │
│   inc/abilities-registration.php (NEW file)                    │
│     wp_register_ability( 'signal-noise/get-design-tokens', [   │
│        'execute_callback' => 'sn_theme_ability_design_tokens', │
│        ...                                                     │
│     ]);                                                        │
│     // ... + 11 more theme-owned ability registrations         │
│                                                                │
│     function sn_theme_ability_design_tokens() {                │
│        return wp_get_global_settings();  // direct, no filter  │
│     }                                                          │
│                                                                │
│     function sn_theme_ability_ai_page_note_summary( $input ) { │
│        if ( ! function_exists(                                 │
│             'snt_ai_generate_with_constraints' ) ) {           │
│           return new WP_Error( 'ai_helper_unavailable', ... ); │
│        }                                                       │
│        // ... compose prompt with theme voice guide ...        │
│        return snt_ai_generate_with_constraints(                │
│           $prompt, $system, $max_tokens );                     │
│     }                                                          │
│                                                                │
└────────────────────────────────────────────────────────────────┘
         ▲                                          │
         │ function_exists check                    │
         │ + direct function call                   │
         │                                          ▼
┌────────────────────────────────────────────────────────────────┐
│  signal-and-noise-tools (plugin) — supports + surfaces         │
│                                                                │
│   inc/ai-bootstrap.php (existing)                              │
│     snt_ai_generate_with_constraints()                         │
│     // Sonnet pinning, error handling, model-pref filter       │
│                                                                │
│   inc/desktop-mode-integration.php (extend)                    │
│     // 12 new ⌘K commands that dispatch to theme abilities     │
│     'ability' => 'signal-noise/get-design-tokens'              │
│                                                                │
│   assets/desktop-mode.js (extend)                              │
│     // New 'input-then-result' render mode for ⌘K commands     │
│                                                                │
└────────────────────────────────────────────────────────────────┘
```

### Why this architecture

Three rationales, each derived from the brainstorming re-evaluation:

1. **Lifecycle coupling.** Theme-domain knowledge (design tokens, patterns, /notes pillars) belongs in the theme. If a future maintainer swaps themes, the abilities should go with it — not stay registered in the plugin returning 503 forever. Registering in the theme makes the lifecycle natural.

2. **The existing cross-package filter pattern doesn't generalize to theme-domain reads.** The 3 existing filters (`sn_purge_all_caches_result`, `sn_clear_template_overrides_result`, `sn_og_font_paths`) are for cross-cutting operational concerns where work happens across multiple layers. The 12 new abilities are pure theme-domain reads + theme-aware generative calls — no cross-cutting concern. Applying the cross-cutting pattern here would create 10 filters that exist only to bridge a gap that shouldn't exist.

3. **The "503 theme handler missing" failure mode evaporates.** Under the original plugin-proxy architecture, plugin-installed-but-theme-not-yet-updated produced 503s on every ability call. Under theme-owned, abilities either exist (theme installed) or don't (theme not installed). WP's normal "ability not found" handling covers the latter — no custom error code needed.

### Category-reuse with defensive guard

Per source-verified behavior in [`class-wp-ability-categories-registry.php:57-67`](https://github.com/WordPress/abilities-api/blob/trunk/includes/abilities-api/class-wp-ability-categories-registry.php), calling `wp_register_ability_category()` with an already-registered slug fires `_doing_it_wrong` and returns null. Since the plugin already registers `diagnostics`, `content`, and `ai-generation`, the theme must check first:

```php
add_action( 'wp_abilities_api_categories_init', function() {
    if ( ! function_exists( 'wp_has_ability_category' ) ) { return; }

    // First-mover wins. Plugin typically fires first, but theme should
    // be defensive — handles plugin-not-installed and ordering changes.
    if ( ! wp_has_ability_category( 'diagnostics' ) ) {
        wp_register_ability_category( 'diagnostics', array(
            'label'       => 'Diagnostics',
            'description' => "Read-only inspection of the theme + plugin pair's state.",
        ) );
    }
    if ( ! wp_has_ability_category( 'content' ) ) {
        wp_register_ability_category( 'content', array(
            'label'       => 'Content',
            'description' => 'Per-post content artifacts (OG cards, schema, etc.).',
        ) );
    }
    if ( ! wp_has_ability_category( 'ai-generation' ) ) {
        wp_register_ability_category( 'ai-generation', array(
            'label'       => 'AI Generation',
            'description' => 'AI-generated content artifacts (meta descriptions, summaries, etc.).',
        ) );
    }
});
```

This pattern handles three install states:
- **Plugin only:** plugin registers categories; theme's abilities don't exist; works
- **Theme only:** theme registers categories; theme's read abilities work; generative abilities return `ai_helper_unavailable`
- **Both installed:** plugin registers first (lower hook priority by convention); theme's `is_registered` checks all skip; works

### Failure modes

| Situation | Behavior |
|---|---|
| Theme installed, plugin not | Read abilities work; generative abilities return `WP_Error('ai_helper_unavailable', 'snt_ai_generate_with_constraints not found — install signal-and-noise-tools plugin v3.7.x+')` with 503 |
| Plugin installed, theme not | None of the 12 abilities are registered. WP's standard "ability not found" handling — caller sees a 404-shaped error. Categories registered by plugin remain (harmless). |
| Both installed but AI Client not active (WP < 7.0) | Theme's `wp_register_ability` calls are no-ops (the function doesn't exist on WP < 7.0); same for the plugin. No errors. |
| Theme's ability execute_callback throws | Caught by theme's own try/catch; logged via `error_log()` per the v3.7.1 lesson; returns `WP_Error('theme_ability_error', $e->getMessage())` with 500 |
| Generative ability gets malformed AI response | Theme handler validates schema; logs the malformed payload; returns `WP_Error('ai_malformed_response')` with 502. The v3.6.0 Task 2 lesson applies: test fixtures must match real AI shapes, not idealized ones. |

---

## 4. Ability catalog

All 12 abilities are registered in the theme at `inc/abilities-registration.php`. Each has a direct `execute_callback` — no filter dispatch needed, no plugin-side proxy.

### 4.1 Read abilities (7)

#### `signal-noise/get-design-tokens`

**Category:** `diagnostics`
**Annotations:** `idempotent: true`, `open_world_hint: false`, `read_only: true`
**Permission:** `read` (any logged-in user — these are public design facts)

**Input schema:**
```json
{ "type": "object", "properties": {}, "additionalProperties": false }
```

**Output schema:**
```json
{
  "type": "object",
  "required": ["colors", "typography", "spacing", "version"],
  "properties": {
    "colors": {
      "type": "object",
      "description": "Named brand colors from theme.json color.palette",
      "additionalProperties": { "type": "string", "format": "color-hex" }
    },
    "typography": {
      "type": "object",
      "properties": {
        "fontFamilies": { "type": "array", "items": { "type": "object", "required": ["slug", "name", "fontFamily"] } },
        "fontSizes": { "type": "array", "items": { "type": "object", "required": ["slug", "size", "name"] } }
      }
    },
    "spacing": {
      "type": "object",
      "properties": {
        "spacingScale": { "type": "object" },
        "spacingSizes": { "type": "array" }
      }
    },
    "version": { "type": "string", "description": "Theme version that produced these tokens" }
  }
}
```

**Implementation:** `execute_callback` calls `wp_get_global_settings()` (WP 5.9+ core function), shapes the response per the output schema, returns the shaped data.

---

#### `signal-noise/list-block-patterns`

**Category:** `content`
**Annotations:** `idempotent: true`, `open_world_hint: false`, `read_only: true`
**Permission:** `read`

**Input schema:**
```json
{
  "type": "object",
  "properties": {
    "category": {
      "type": "string",
      "description": "Optional filter to a single pattern category slug"
    }
  },
  "additionalProperties": false
}
```

**Output schema:**
```json
{
  "type": "object",
  "required": ["patterns", "categories"],
  "properties": {
    "patterns": {
      "type": "array",
      "items": {
        "type": "object",
        "required": ["name", "title", "categories"],
        "properties": {
          "name": { "type": "string", "description": "namespaced pattern slug" },
          "title": { "type": "string" },
          "description": { "type": "string" },
          "categories": { "type": "array", "items": { "type": "string" } },
          "keywords": { "type": "array", "items": { "type": "string" } },
          "viewport_width": { "type": "integer" }
        }
      }
    },
    "categories": {
      "type": "array",
      "items": {
        "type": "object",
        "required": ["name", "label"],
        "properties": {
          "name": { "type": "string" },
          "label": { "type": "string" }
        }
      }
    }
  }
}
```

**Implementation:** `execute_callback` enumerates `WP_Block_Patterns_Registry::get_instance()->get_all_registered()` and `WP_Block_Pattern_Categories_Registry::get_instance()->get_all_registered()`. Filters by `category` input if provided.

---

#### `signal-noise/get-active-template-structure`

**Category:** `diagnostics`
**Annotations:** `idempotent: true`, `open_world_hint: false`, `read_only: true`
**Permission:** `read`

**Input schema:**
```json
{
  "type": "object",
  "properties": {
    "post_id": { "type": "integer", "minimum": 1 },
    "post_type": { "type": "string", "enum": ["post", "page"] },
    "slug": { "type": "string" }
  },
  "anyOf": [
    { "required": ["post_id"] },
    { "required": ["slug"] }
  ],
  "additionalProperties": false
}
```

**Output schema:**
```json
{
  "type": "object",
  "required": ["template_slug", "blocks"],
  "properties": {
    "template_slug": { "type": "string", "description": "e.g. 'single' or 'page' or 'page-notes'" },
    "template_part_slugs": { "type": "array", "items": { "type": "string" } },
    "blocks": {
      "type": "array",
      "items": {
        "type": "object",
        "required": ["blockName"],
        "properties": {
          "blockName": { "type": "string" },
          "attrs": { "type": "object" },
          "innerBlocksCount": { "type": "integer" }
        }
      }
    }
  }
}
```

**Implementation:** `execute_callback` resolves the template via `get_block_template()`, parses with `parse_blocks()`, shapes a summary (does NOT recurse into innerBlocks beyond a count — keeps payload size bounded).

---

#### `signal-noise/get-theme-version`

**Category:** `diagnostics`
**Annotations:** `idempotent: true`, `open_world_hint: false`, `read_only: true`
**Permission:** `read`

**Input schema:**
```json
{ "type": "object", "properties": {}, "additionalProperties": false }
```

**Output schema:**
```json
{
  "type": "object",
  "required": ["theme_version", "theme_name", "is_block_theme", "wp_version"],
  "properties": {
    "theme_version": { "type": "string" },
    "theme_name": { "type": "string" },
    "theme_template": { "type": "string", "description": "Parent theme slug or self" },
    "is_block_theme": { "type": "boolean" },
    "supports_fse": { "type": "boolean" },
    "wp_version": { "type": "string" }
  }
}
```

**Implementation:** `execute_callback` reads `wp_get_theme()` + `wp_is_block_theme()` + `wp_get_wp_version()`.

---

#### `signal-noise/get-page-notes-pillars`

**Category:** `content`
**Annotations:** `idempotent: true`, `open_world_hint: false`, `read_only: true`
**Permission:** `read`

**Input schema:**
```json
{ "type": "object", "properties": {}, "additionalProperties": false }
```

**Output schema:**
```json
{
  "type": "object",
  "required": ["pillars"],
  "properties": {
    "pillars": {
      "type": "array",
      "items": {
        "type": "object",
        "required": ["slug", "title", "url"],
        "properties": {
          "slug": { "type": "string" },
          "title": { "type": "string" },
          "url": { "type": "string", "format": "uri" },
          "summary": { "type": "string", "description": "Short editorial summary" },
          "reading_time_minutes": { "type": "integer" },
          "last_modified": { "type": "string", "format": "date" }
        }
      }
    }
  }
}
```

**Implementation:** `execute_callback` enumerates the pillar essays defined in `inc/page-notes-render.php`'s `sn_notes_query_posts()` and returns their metadata.

---

#### `signal-noise/get-reading-time-for-slug`

**Category:** `content`
**Annotations:** `idempotent: true`, `open_world_hint: false`, `read_only: true`
**Permission:** `read`

**Input schema:**
```json
{
  "type": "object",
  "required": ["slug"],
  "properties": {
    "slug": { "type": "string", "minLength": 1 }
  },
  "additionalProperties": false
}
```

**Output schema:**
```json
{
  "type": "object",
  "required": ["slug", "minutes"],
  "properties": {
    "slug": { "type": "string" },
    "minutes": { "type": "integer", "minimum": 0 },
    "wpm_basis": { "type": "integer", "description": "Words-per-minute basis used for the calc" }
  }
}
```

**Implementation:** `execute_callback` calls `sn_notes_reading_time_for_slug( $slug )` (the existing PHP function in `inc/page-notes-render.php`). Returns 0 minutes if the slug doesn't resolve to a published post.

---

#### `signal-noise/get-design-system-summary`

**Category:** `diagnostics`
**Annotations:** `idempotent: true`, `open_world_hint: false`, `read_only: true`
**Permission:** `read`

**Input schema:**
```json
{
  "type": "object",
  "properties": {
    "format": { "type": "string", "enum": ["markdown", "compact-text", "json"], "default": "markdown" }
  },
  "additionalProperties": false
}
```

**Output schema:**
```json
{
  "type": "object",
  "required": ["format", "summary", "token_estimate"],
  "properties": {
    "format": { "type": "string", "enum": ["markdown", "compact-text", "json"] },
    "summary": { "type": "string", "description": "Formatted design-system overview suitable for AI prompt embedding" },
    "token_estimate": { "type": "integer", "description": "Approximate token count (chars / 4 heuristic)" }
  }
}
```

**Implementation:** `execute_callback` calls `sn_theme_ability_design_tokens()` internally for the underlying data, then formats per the `format` input:
- `markdown`: structured headings (Colors, Typography, Spacing) with named entries
- `compact-text`: single-line `colors:void#fff,asphalt#f5f5f5,...; fonts:redaction,monogram; ...` for minimum-token embedding
- `json`: same as `get-design-tokens` (compatibility option)

**Net-new vs `get-design-tokens`:** Same underlying data, but pre-formatted for AI prompt embedding. The plugin's AI features (Insights, brand-alignment, etc.) can call this instead of stuffing full JSON in every prompt — typical 70-80% token reduction on the design-context fragment.

---

### 4.2 Generative abilities (5)

All generative abilities check `function_exists( 'snt_ai_generate_with_constraints' )` before doing anything. If the plugin is not installed, they return `WP_Error('ai_helper_unavailable')` with status 503 and a clear remediation message.

When the plugin IS available, they call `snt_ai_generate_with_constraints()` which already (as of plugin v3.7.2) pins `claude-sonnet-4-6` and provides the `snt_ai_model_preference` filter for per-feature overrides.

#### `signal-noise/ai-generate-page-note-summary`

**Category:** `ai-generation`
**Annotations:** `idempotent: false` (AI calls aren't strictly deterministic), `open_world_hint: false`, `read_only: false`
**Permission:** `edit_posts`

**Input schema:**
```json
{
  "type": "object",
  "required": ["post_id"],
  "properties": {
    "post_id": { "type": "integer", "minimum": 1 },
    "max_words": { "type": "integer", "minimum": 10, "maximum": 60, "default": 30 }
  },
  "additionalProperties": false
}
```

**Output schema:**
```json
{
  "type": "object",
  "required": ["summary", "post_id"],
  "properties": {
    "summary": { "type": "string", "description": "Brand-voiced summary in /notes catalog vocabulary" },
    "post_id": { "type": "integer" },
    "tokens_used": { "type": "integer" }
  }
}
```

**Implementation:**
1. Check `function_exists('snt_ai_generate_with_constraints')` → if false, return `WP_Error('ai_helper_unavailable')`
2. Check `function_exists('snt_ai_extract_post_text')` → if false, return same error
3. Fetch post via `snt_ai_extract_post_text( $input['post_id'], 1000 )`
4. Compose system instruction with the theme's brand voice guide (a curated string defined as a constant `SN_THEME_NOTES_VOICE_SYSTEM` near the top of `inc/abilities-registration.php` — captures the "industrial catalog" aesthetic: brutalist white-first, NIN-influenced, blood-red accents, terminal-mono row layouts)
5. Call `snt_ai_generate_with_constraints( $post_text, $system, $max_tokens )` where `$max_tokens` is roughly `2 * input['max_words']`
6. Return trimmed summary

**Net-new vs `ai/ai`:** `ai/ai` does generic content summarization that's voice-agnostic. This one's output is grounded in the theme's specific aesthetic vocabulary — produces summaries that READ like SN, not like a generic blog.

---

#### `signal-noise/ai-suggest-block-pattern`

**Category:** `ai-generation`
**Annotations:** `idempotent: false`, `open_world_hint: false`, `read_only: false`
**Permission:** `edit_posts`

**Input schema:**
```json
{
  "type": "object",
  "required": ["draft_content"],
  "properties": {
    "draft_content": { "type": "string", "minLength": 20, "maxLength": 4000 },
    "topic_hint": { "type": "string", "maxLength": 200 }
  },
  "additionalProperties": false
}
```

**Output schema:**
```json
{
  "type": "object",
  "required": ["suggestions"],
  "properties": {
    "suggestions": {
      "type": "array",
      "minItems": 1,
      "maxItems": 3,
      "items": {
        "type": "object",
        "required": ["pattern_name", "reasoning"],
        "properties": {
          "pattern_name": { "type": "string", "description": "Pattern slug from list-block-patterns" },
          "reasoning": { "type": "string", "description": "One-sentence why this pattern fits" },
          "confidence": { "type": "string", "enum": ["high", "medium", "low"] }
        }
      }
    },
    "tokens_used": { "type": "integer" }
  }
}
```

**Implementation:**
1. AI helper guard (as above)
2. Fetch pattern list via the same code that powers `signal-noise/list-block-patterns` (extract into shared helper `sn_theme_collect_block_patterns()`)
3. Compose prompt: `[pattern catalog as JSON] + [draft content] + [topic hint] + [system: pick 1-3 patterns; return JSON {suggestions: [{pattern_name, reasoning, confidence}]}]`
4. Call `snt_ai_generate_with_constraints`
5. Parse JSON response; validate each `pattern_name` exists in the catalog; drop invalid suggestions; cap at 3
6. Return top suggestions

**Net-new vs `ai/ai`:** `ai/ai` doesn't know about SN's pattern library. This is genuinely net-new — pattern-aware drafting assistant.

---

#### `signal-noise/ai-validate-brand-alignment`

**Category:** `ai-generation`
**Annotations:** `idempotent: false`, `open_world_hint: false`, `read_only: false`
**Permission:** `edit_posts`

**Input schema:**
```json
{
  "type": "object",
  "required": ["content"],
  "properties": {
    "content": { "type": "string", "minLength": 50, "maxLength": 8000 },
    "content_type": { "type": "string", "enum": ["copy", "title", "summary", "longform"], "default": "copy" }
  },
  "additionalProperties": false
}
```

**Output schema:**
```json
{
  "type": "object",
  "required": ["overall_score", "findings"],
  "properties": {
    "overall_score": { "type": "integer", "minimum": 0, "maximum": 100 },
    "findings": {
      "type": "array",
      "items": {
        "type": "object",
        "required": ["dimension", "verdict", "note"],
        "properties": {
          "dimension": { "type": "string", "enum": ["voice", "tone", "vocabulary", "palette_fit", "structure"] },
          "verdict": { "type": "string", "enum": ["aligned", "drift", "off-brand"] },
          "note": { "type": "string" }
        }
      }
    },
    "tokens_used": { "type": "integer" }
  }
}
```

**Implementation:**
1. AI helper guard
2. Get the brand voice guide constant `SN_THEME_BRAND_VOICE_SYSTEM` from `inc/abilities-registration.php` (this is shared between `ai-validate-brand-alignment` and `ai-rewrite-in-brand-voice` — same source of truth)
3. Also include the design tokens via `sn_theme_ability_design_tokens()` for palette context
4. Compose prompt: `[voice guide] + [palette tokens] + [content] + [system: score 0-100 and flag findings as JSON]`
5. Call `snt_ai_generate_with_constraints`
6. Parse response; validate verdicts against enum; return

**Net-new vs `ai/ai`:** `ai/ai`'s Editorial Notes evaluate grammar / SEO / readability / a11y. None of those check brand fit. This is site-specific brand-identity validation.

---

#### `signal-noise/ai-generate-pattern-content`

**Category:** `ai-generation`
**Annotations:** `idempotent: false`, `open_world_hint: false`, `read_only: false`
**Permission:** `edit_posts`

**Input schema:**
```json
{
  "type": "object",
  "required": ["pattern_name", "topic"],
  "properties": {
    "pattern_name": { "type": "string", "description": "Pattern slug from list-block-patterns; must exist in registry" },
    "topic": { "type": "string", "minLength": 5, "maxLength": 500 },
    "tone_hint": { "type": "string", "enum": ["technical", "narrative", "manifesto", "spec-sheet"], "default": "spec-sheet" }
  },
  "additionalProperties": false
}
```

**Output schema:**
```json
{
  "type": "object",
  "required": ["block_markup", "pattern_name"],
  "properties": {
    "block_markup": { "type": "string", "description": "Ready-to-paste serialized Gutenberg block markup with brand-voiced content filled in" },
    "pattern_name": { "type": "string" },
    "tokens_used": { "type": "integer" },
    "warnings": { "type": "array", "items": { "type": "string" }, "description": "Schema-validation warnings if any" }
  }
}
```

**Implementation:**
1. AI helper guard
2. Validate `pattern_name` exists via `WP_Block_Patterns_Registry::get_instance()->get_registered( $pattern_name )` — if missing, return `WP_Error('pattern_not_found')` with 404
3. Get the pattern's template content + the brand voice guide
4. Compose prompt: `[pattern template] + [topic] + [tone_hint] + [voice guide] + [system: fill in placeholders with brand-voiced content; return valid serialized Gutenberg blocks]`
5. Call `snt_ai_generate_with_constraints`
6. Validate output is parseable via `parse_blocks()` — any unparseable output triggers a `warnings` entry and returns the raw AI output anyway (caller decides what to do)
7. Return block_markup

**Net-new vs `ai/ai`:** `ai/ai` doesn't know about SN's pattern library. This is pattern-aware content drafting — fundamentally a theme-specific capability.

**Safety note:** This ability does NOT save content anywhere. It returns block markup the caller can choose to paste. No DB writes.

---

#### `signal-noise/ai-rewrite-in-brand-voice`

**Category:** `ai-generation`
**Annotations:** `idempotent: false`, `open_world_hint: false`, `read_only: false`
**Permission:** `edit_posts`

**Input schema:**
```json
{
  "type": "object",
  "required": ["source_text"],
  "properties": {
    "source_text": { "type": "string", "minLength": 20, "maxLength": 8000 },
    "preserve_links": { "type": "boolean", "default": true },
    "preserve_lists": { "type": "boolean", "default": true },
    "intensity": { "type": "string", "enum": ["light", "medium", "full"], "default": "medium", "description": "How aggressively to transform: light = vocabulary swaps; medium = sentence restructure + vocabulary; full = full rewrite" }
  },
  "additionalProperties": false
}
```

**Output schema:**
```json
{
  "type": "object",
  "required": ["rewritten_text", "summary_of_changes"],
  "properties": {
    "rewritten_text": { "type": "string" },
    "summary_of_changes": { "type": "string", "description": "Brief one-paragraph note on what changed and why" },
    "preserved_elements": {
      "type": "object",
      "properties": {
        "links_count": { "type": "integer" },
        "lists_count": { "type": "integer" }
      }
    },
    "tokens_used": { "type": "integer" }
  }
}
```

**Implementation:**
1. AI helper guard
2. Get the shared brand voice guide constant `SN_THEME_BRAND_VOICE_SYSTEM`
3. Compose prompt: `[voice guide] + [intensity directive] + [preserve_links/lists flags] + [source text] + [system: rewrite respecting flags; return JSON with rewritten_text + summary_of_changes]`
4. Call `snt_ai_generate_with_constraints` with JSON-output system instruction
5. Parse response; validate against output schema
6. Return shaped result

**Net-new vs `ai/ai`:** `ai/ai`'s Editorial Notes catch grammar/SEO/readability/a11y issues but do NOT transform voice. This is the only ability in the SN AI surface that converts external/generic copy into the SN voice register.

**Use case:** Pasting an LLM-generated draft from another tool, or a quote from a source, into the editor and asking SN to rewrite it in-voice before publishing.

---

## 5. Cross-package contract — unchanged

This spec does NOT extend the cross-package filter contract. Theme abilities run in-process within the theme; no theme→plugin or plugin→theme filter handoffs are needed for the 12 abilities.

The 3 existing cross-package filters from v8.4.0 remain unchanged:
- `sn_purge_all_caches_result`
- `sn_clear_template_overrides_result`
- `sn_og_font_paths`

The theme→plugin coupling for generative abilities is a function-call (`snt_ai_generate_with_constraints`) guarded by `function_exists()` — not a filter. This is a one-directional dependency: theme depends on plugin for AI helper availability, plugin does not depend on theme for ability registration.

**WORDPRESS-REFERENCE.md §10.x update:** Note that theme abilities introduce a new dependency category — "theme features that require plugin's AI helper" — but no new filter surface. The existing 3-filter contract count stays at 3.

---

## 6. Error handling

### Theme-side execute_callbacks

Each ability's `execute_callback` follows this defensive pattern (the v3.7.1 lesson directly applied):

```php
function sn_theme_ability_design_tokens() {
    try {
        if ( ! function_exists( 'wp_get_global_settings' ) ) {
            return new WP_Error(
                'theme_dependency_missing',
                'wp_get_global_settings() not available — requires WP 5.9+.',
                array( 'status' => 503 )
            );
        }
        // ... shape and return tokens ...
    } catch ( \Throwable $e ) {
        error_log( 'SN theme ability error in get-design-tokens: ' . $e->getMessage() );
        return new WP_Error(
            'theme_ability_error',
            sprintf( 'Theme ability failed: %s', $e->getMessage() ),
            array( 'status' => 500 )
        );
    }
}
```

**The `error_log` line is non-negotiable.** Silent failures are exactly the class of bug v3.7.1 was caused by; instrumenting at every catch site means future failures surface in `debug.log` instead of being invisibly swallowed.

### Generative ability dependency check

The 5 generative abilities all guard the plugin's AI helper:

```php
function sn_theme_ability_ai_page_note_summary( $input ) {
    if ( ! function_exists( 'snt_ai_generate_with_constraints' ) ) {
        return new WP_Error(
            'ai_helper_unavailable',
            'AI helper not available. Install or update signal-and-noise-tools plugin to v3.7.x+.',
            array( 'status' => 503 )
        );
    }
    // ... continue with AI call ...
}
```

### AI response validation

For abilities that expect JSON-shaped AI responses (`ai-suggest-block-pattern`, `ai-validate-brand-alignment`, `ai-rewrite-in-brand-voice`):

```php
$raw = snt_ai_generate_with_constraints( $prompt, $system, $max_tokens );
if ( is_wp_error( $raw ) ) {
    return $raw; // propagate
}
// Strip optional markdown fences (the v3.7.0 Task B / v3.7.0 fence-regex lesson)
$text = trim( preg_replace( '/^```(?:json)?\s*|\s*```$/i', '', $raw ) );
$parsed = json_decode( $text, true );
if ( ! is_array( $parsed ) ) {
    error_log( "SN ai-suggest-block-pattern: malformed JSON: " . substr( $text, 0, 200 ) );
    return new WP_Error(
        'ai_malformed_response',
        'AI returned malformed JSON.',
        array( 'status' => 502 )
    );
}
// validate against output_schema-aligned structure
```

### Schema validation

WP's Abilities API validates input against `input_schema` BEFORE `permission_callback` fires. Invalid input produces a typed REST error automatically — execute_callback doesn't need to re-validate input.

For output: schemas are documentation AND validation (per recent WP versions). Test fixtures must match the declared shape exactly.

---

## 7. Testing strategy

### Theme-side tests (primary)

New file: `signal-and-noise/tests/abilities-registration.php`

The theme doesn't have a test harness today, so this file establishes one — matching the standalone PHP pattern from the plugin's `tests/health-checks.php`. Self-contained, no PHPUnit/Composer dependency, runs via `php tests/abilities-registration.php`.

**Scenarios per read ability (~10 assertions each):**
- Happy path: returns schema-shaped data
- Invalid input (schema-rejected before execute_callback fires)
- Missing WP dependency: returns `WP_Error('theme_dependency_missing')` with 503
- Internal exception: returns `WP_Error('theme_ability_error')` with 500 + logs via error_log

**Scenarios per generative ability (~12 assertions each):**
- Plugin not installed (no `snt_ai_generate_with_constraints`): returns `WP_Error('ai_helper_unavailable')` with 503
- Happy path: composes prompt correctly, calls AI helper, returns shaped output
- AI helper returns WP_Error: propagates
- AI helper returns malformed JSON (for JSON-output abilities): returns `WP_Error('ai_malformed_response')` with 502
- Markdown-fence handling: strips ` ```json ` fences correctly (v3.7.0 Task B fence-regex lesson)
- Model preference defaults to `claude-sonnet-4-6` (v3.7.2 lock-in indirectly verified through helper mock)

**Estimate:** 7 read abilities × 10 + 5 generative × 12 = **~130 assertions** in new theme tests.

### Plugin-side tests (smaller scope)

New file: `signal-and-noise-tools/tests/theme-ability-commands.php`

Covers the Command Palette command registrations + the new `input-then-result` render mode logic (PHP side):
- Each of 12 commands registered with correct slug + ability mapping
- `aiCallable: true` flag set on all 12
- `input-then-result` commands have correct `input_fields` arrays
- Invalid ability slug → command registration fails cleanly (no fatal)

**Estimate:** ~20 assertions.

### Live smoke tests (post-deploy)

For each of 12 abilities, verify via `wp eval` over SSH:

```bash
wp eval '$r = wp_get_registered_abilities()["signal-noise/get-design-tokens"]->execute([]); echo wp_json_encode($r);'
```

Confirms:
- Ability is registered (theme installed correctly)
- Execute callback fires without throwing
- Output matches schema

For generative abilities, also confirm the AI helper guard works by temporarily renaming `snt_ai_generate_with_constraints` and verifying the ability returns `WP_Error('ai_helper_unavailable')`.

### Test count summary

| File | New | Existing |
|---|---|---|
| `signal-and-noise/tests/abilities-registration.php` (new) | ~130 | — |
| `signal-and-noise-tools/tests/theme-ability-commands.php` (new) | ~20 | — |
| Existing plugin suites | unchanged | 441 |
| **Total project assertions after this work** | **~150 new** | **~591 total** |

---

## 8. Release plan

### Theme v9.1.0 ships first (minor bump — primary delivery)

**Scope:**
1. `inc/abilities-registration.php` — NEW file. Registers categories defensively (`wp_has_ability_category` guard), registers all 12 abilities with execute_callbacks
2. `functions.php` — module map comment update (one line added for the new file)
3. `tests/abilities-registration.php` — NEW test file (~130 assertions; establishes theme test harness)
4. `style.css` — version bump 9.0.0 → 9.1.0
5. `CHANGELOG.md` — entry documenting the 12 abilities, the new file, and the function_exists guard pattern for plugin-AI-helper

**Behavior on day-of-ship:** if plugin v3.7.x is installed (current state), all 12 abilities work end-to-end. If plugin is somehow not installed, the 7 read abilities still work; the 5 generative abilities return clean `WP_Error('ai_helper_unavailable')` with diagnostic text.

**Minor cap status:** theme currently at v9.0.0. Minor cap is 5 per major; 9.0 → 9.1 is well within the cap.

### Plugin v3.7.4 ships second (patch bump — supporting infrastructure)

**Scope:**
1. `inc/desktop-mode-integration.php` — add 12 new Command Palette commands (one per new theme ability), all `aiCallable: true`
2. `assets/desktop-mode.js` — add `input-then-result` render mode (new ⌘K shape; the 15 existing commands all use the existing `result-panel` shape; the 12 new commands include 9 that need a form-then-result flow)
3. `tests/theme-ability-commands.php` — NEW test file (~20 assertions covering the command registrations)
4. `signal-and-noise-tools.php` — version bump 3.7.3 → 3.7.4
5. `CHANGELOG.md` — entry documenting the 12 ⌘K commands, the new render mode, and WP-CLI access (which works automatically once theme registers abilities)

**Why patch (3.7.4) not minor (3.8.0):** the plugin contributes no new abilities. It contributes ⌘K commands that dispatch to theme-registered abilities + a JS render mode. The user-visible primary feature (the abilities themselves) lives in the theme. Plugin's contribution is supporting infrastructure — fits the patch convention.

**Patch cap status:** plugin at v3.7.3. Patch cap is 7 per minor; this would be the 4th patch in v3.7.x (after v3.7.1, v3.7.2, v3.7.3). 3 more patches available before v3.7 rolls to v3.8.

**Why this order (theme first):** if theme ships before plugin, the 12 abilities are registered + functional (read abilities work; generative ones return helpful `ai_helper_unavailable` errors); plugin v3.7.4 lights up the ⌘K commands. Reverse order would mean plugin ships ⌘K commands that point at theme abilities that don't exist yet — registration would succeed but invocation would fail with "ability not found" until theme ships.

The brief window between theme v9.1.0 and plugin v3.7.4 is acceptable: WP-CLI access (`wp ability run signal-noise/*`) works immediately after theme ships, just not the ⌘K commands.

---

## 9. Self-review checklist

Performed inline against this spec (architecture re-evaluation pass):

- [x] **No placeholders** — every section concrete; no "TBD" or "TODO" markers
- [x] **Schemas defined for all 12 abilities** — input + output JSON Schema written
- [x] **Architecture decision justified** — original Approach 1 challenged; theme-owned chosen; rationales (lifecycle coupling, pattern misapplication, 503 elimination) documented
- [x] **Category-reuse pattern verified from source** — `class-wp-ability-categories-registry.php:57-67` confirms `_doing_it_wrong` on double-register; defensive `wp_has_ability_category` guard pattern shown
- [x] **Failure modes enumerated** — theme-only, plugin-only, both, WP < 7.0; all four cases handled
- [x] **Tests scoped** — theme-side (~130 assertions, establishes theme test harness) and plugin-side (~20 assertions)
- [x] **Release plan ordered** — theme v9.1.0 first, plugin v3.7.4 second; version-cap math checked; rationale for patch-vs-minor on plugin documented
- [x] **Out-of-scope explicit** — Phase 1 boundaries documented; Phase 2 absorbed; Phase 3+ deferred
- [x] **v3.7.x lessons applied** — `error_log` at catch sites; schemas grounded in actual WP API source (verified abilities-api source for category-reuse behavior); tests cover both sides; markdown-fence handling per v3.7.0 Task B lesson
- [x] **Memory rules respected** — `ai/ai` v1.0.0 features check; no destructive ops; no dark mode (intentionally omitted); WP-CLI access derived from upstream Abilities API source not assumed
- [x] **Cross-package contract surface UNCHANGED** — explicitly noted; 3-filter contract from v8.4.0 stays at 3; theme→plugin coupling is a function-call, not a new filter
- [x] **Command Palette integration scoped** — 12 new commands in plugin, render-mode shape documented, JS work flagged for plugin v3.7.4
- [x] **WP-CLI access surfaced** — confirmed automatic via WP 7.0 Abilities API; deliverable is documentation only
- [x] **AI Copilot consumption surfaced** — `ai_callable: true` on all 12 commands; labels + descriptions vetted as Copilot-facing
- [x] **Internal consistency** — ability counts match across §2 (12 in scope), §4 (12 cataloged), §8 (12 in release plan), §11 (12 commands); registration location consistent (all 12 in theme)

---

## 10. Future phases (out of scope here, but worth recording)

**Phase 2 — folded into v9.1.0 (no longer deferred):**

The 3 abilities originally proposed as Phase 2 are now part of this spec's 12-ability scope:
- `signal-noise/ai-generate-pattern-content` (in §4.2)
- `signal-noise/ai-rewrite-in-brand-voice` (in §4.2)
- `signal-noise/get-design-system-summary` (in §4.1)

**Phase 3 candidates (destructive ops — explicitly out of scope per user constraint):**

- `signal-noise/update-color-palette` — modify theme.json
- `signal-noise/register-pattern-from-blocks` — persist a new pattern
- `signal-noise/regenerate-template-part` — overwrite an FSE template part

These remain out of scope until/unless the user revisits the "non-destructive only" constraint.

**Phase 4 candidates (advanced AI features, none blocking):**

- Streaming outputs for generative abilities (revisit if any ability's median response time exceeds ~10s)
- Multi-turn conversation context for `ai-rewrite-in-brand-voice` (current shape is single-shot)
- Tool-use chaining (let one ability internally call another via the abilities API, without going through external orchestration)

**Phase 5 candidates (extensibility):**

- Filter hooks at strategic points in the theme abilities (e.g., `sn_theme_brand_voice_system` filter to override the voice guide string) — only add when there's a real consumer; YAGNI for now since this is a single-author site
- Sub-theme/child-theme support if/when SN ever supports child themes

---

## 11. Command Palette (⌘K) + other entry-point integration

All 12 abilities are reachable from the WP 7.0 Command Palette via the plugin's desktop-mode integration. They are also automatically WP-CLI-accessible via `wp ability run`. Both surfaces come from the theme's ability registration — no duplicate ability code.

### 11.1 Command Palette integration

**Where commands live:**

The plugin's `inc/desktop-mode-integration.php` already registers 15 commands. The new theme abilities map cleanly to additional commands in the same file — plugin stays the Command Palette interface layer; theme stays the ability implementation layer.

**Command shape:**

Each ability gets one ⌘K command. Read commands display the result in a side panel; generative commands (and read abilities with required inputs) show an input form, then the AI result.

```php
// In signal-and-noise-tools/inc/desktop-mode-integration.php
$theme_ability_commands = array(
    // Read abilities — most display result panel directly
    array(
        'slug'         => 'sn-cmd-get-design-tokens',
        'label'        => 'SN: Show design tokens',
        'description'  => 'Theme palette + typography + spacing scale.',
        'icon'         => 'dashicons-art',
        'ability'      => 'signal-noise/get-design-tokens',
        'render_mode'  => 'result-panel',
        'ai_callable'  => true,
    ),
    array(
        'slug'         => 'sn-cmd-list-block-patterns',
        'label'        => 'SN: List block patterns',
        'description'  => 'All registered patterns with category + keywords.',
        'icon'         => 'dashicons-screenoptions',
        'ability'      => 'signal-noise/list-block-patterns',
        'render_mode'  => 'result-panel',
        'ai_callable'  => true,
    ),
    array(
        'slug'         => 'sn-cmd-get-template-structure',
        'label'        => 'SN: Inspect active template',
        'description'  => 'FSE block tree for the current page.',
        'icon'         => 'dashicons-layout',
        'ability'      => 'signal-noise/get-active-template-structure',
        'render_mode'  => 'result-panel',
        'ai_callable'  => true,
    ),
    array(
        'slug'         => 'sn-cmd-theme-version',
        'label'        => 'SN: Theme version info',
        'description'  => 'Theme + WP version + block-theme flags.',
        'icon'         => 'dashicons-info-outline',
        'ability'      => 'signal-noise/get-theme-version',
        'render_mode'  => 'result-panel',
        'ai_callable'  => true,
    ),
    array(
        'slug'         => 'sn-cmd-page-notes-pillars',
        'label'        => 'SN: List /notes pillars',
        'description'  => 'Pillar essay metadata for the /notes catalog.',
        'icon'         => 'dashicons-book',
        'ability'      => 'signal-noise/get-page-notes-pillars',
        'render_mode'  => 'result-panel',
        'ai_callable'  => true,
    ),
    array(
        'slug'         => 'sn-cmd-reading-time',
        'label'        => 'SN: Reading time for slug',
        'description'  => 'Computed minutes for a given post slug.',
        'icon'         => 'dashicons-clock',
        'ability'      => 'signal-noise/get-reading-time-for-slug',
        'render_mode'  => 'input-then-result',
        'input_fields' => array( 'slug' ),
        'ai_callable'  => true,
    ),
    array(
        'slug'         => 'sn-cmd-design-summary',
        'label'        => 'SN: Design-system summary',
        'description'  => 'Formatted overview optimized for AI prompts.',
        'icon'         => 'dashicons-edit-page',
        'ability'      => 'signal-noise/get-design-system-summary',
        'render_mode'  => 'input-then-result',
        'input_fields' => array( 'format' ),
        'ai_callable'  => true,
    ),
    // Generative abilities — input form → AI call → result display
    array(
        'slug'         => 'sn-cmd-ai-page-note-summary',
        'label'        => 'SN: Generate page-note summary',
        'description'  => 'AI-summarize the current post in /notes catalog voice.',
        'icon'         => 'dashicons-text',
        'ability'      => 'signal-noise/ai-generate-page-note-summary',
        'render_mode'  => 'input-then-result',
        'input_fields' => array( 'post_id', 'max_words' ),
        'ai_callable'  => true,
    ),
    array(
        'slug'         => 'sn-cmd-ai-suggest-pattern',
        'label'        => 'SN: Suggest block pattern',
        'description'  => 'AI recommends patterns for a draft.',
        'icon'         => 'dashicons-screenoptions',
        'ability'      => 'signal-noise/ai-suggest-block-pattern',
        'render_mode'  => 'input-then-result',
        'input_fields' => array( 'draft_content', 'topic_hint' ),
        'ai_callable'  => true,
    ),
    array(
        'slug'         => 'sn-cmd-ai-brand-validate',
        'label'        => 'SN: Validate brand alignment',
        'description'  => 'AI checks if content fits SN voice + palette.',
        'icon'         => 'dashicons-yes-alt',
        'ability'      => 'signal-noise/ai-validate-brand-alignment',
        'render_mode'  => 'input-then-result',
        'input_fields' => array( 'content', 'content_type' ),
        'ai_callable'  => true,
    ),
    array(
        'slug'         => 'sn-cmd-ai-pattern-content',
        'label'        => 'SN: Generate pattern content',
        'description'  => 'Fill a pattern with brand-voiced copy.',
        'icon'         => 'dashicons-format-aside',
        'ability'      => 'signal-noise/ai-generate-pattern-content',
        'render_mode'  => 'input-then-result',
        'input_fields' => array( 'pattern_name', 'topic', 'tone_hint' ),
        'ai_callable'  => true,
    ),
    array(
        'slug'         => 'sn-cmd-ai-rewrite-voice',
        'label'        => 'SN: Rewrite in brand voice',
        'description'  => 'Transform external copy into SN voice.',
        'icon'         => 'dashicons-edit',
        'ability'      => 'signal-noise/ai-rewrite-in-brand-voice',
        'render_mode'  => 'input-then-result',
        'input_fields' => array( 'source_text', 'intensity' ),
        'ai_callable'  => true,
    ),
);
```

**Render modes:**

- `result-panel` — invoke ability with no input; display output in a side panel
- `input-then-result` — show an input form (populated from `input_fields`), then dispatch on submit and display result

The JS-side rendering lives in `assets/desktop-mode.js`. The current implementation handles `result-panel` for the 15 existing commands; `input-then-result` is the new render mode added in plugin v3.7.4.

**Command count after this work:** 15 existing + 12 new = **27 total commands**, all `aiCallable: true` for the new 12.

### 11.2 WP-CLI access (free / automatic)

The WP 7.0 Abilities API automatically exposes registered abilities via `wp-cli`:

```bash
# List all registered abilities
wp ability list

# Run a read ability
wp ability run signal-noise/get-design-tokens

# Run a generative ability with input
wp ability run signal-noise/ai-rewrite-in-brand-voice --input='{"source_text":"...","intensity":"medium"}'

# Filter by category
wp ability list --category=diagnostics
wp ability list --category=ai-generation
```

**No code required** — registration alone enables CLI access. The deliverable here is documentation:

1. CHANGELOG entry for theme v9.1.0 lists the 12 abilities and notes "all accessible via `wp ability run`"
2. `docs/WORDPRESS-REFERENCE.md` §10.x gains a one-paragraph note about WP-CLI access via the abilities-api
3. README.md in both repos can optionally show example invocations

### 11.3 AI Copilot consumption

All 12 commands set `ai_callable: true` in the registration shape above. Per the WP 7.0 AI Copilot architecture (see `reference_desktop_mode_ai_copilot` memory), this annotation makes them invokable by the AI Copilot as tools.

The Copilot decides which to call based on:
- The ability's category (`diagnostics` / `content` / `ai-generation`)
- The behavioral annotations (`idempotent`, `read_only`, `open_world_hint`)
- The input/output schemas (it can construct valid input)
- The label + description text

**Implication:** the labels + descriptions in §11.1 above are not just human-facing — they're also Copilot-facing. They should be terse, accurate, and use vocabulary the AI can match user intent against. The labels follow the existing "SN: <verb> <object>" pattern from the plugin's 15 existing commands.

---

## 12. References

- WP 7.0 Abilities API source: [WordPress/abilities-api on trunk](https://github.com/WordPress/abilities-api) (verified 2026-05-17 in the plugin v2.0.4 audit, re-verified 2026-05-24 for category-reuse behavior at `class-wp-ability-categories-registry.php:57-67`)
- WP 7.0 AI Client source: [WordPress/wp-ai-client](https://github.com/WordPress/wp-ai-client) (verified 2026-05-21 in the v3.7.1 root-cause investigation)
- Plugin v3.7.x AI gate fix arc: `docs/superpowers/handoffs/2026-05-21-maintenance-pass-in-flight.md`
- Cross-package contract: `docs/WORDPRESS-REFERENCE.md` §10.0 (current 3-filter surface; this spec does NOT extend it)
- Existing ability registrations: `signal-and-noise-tools/inc/abilities-registration.php` (16 abilities across 5 categories — this spec adds 12 in the THEME, keeping registration locations separate by domain)
- Existing Command Palette commands: `signal-and-noise-tools/inc/desktop-mode-integration.php` (15 commands — plugin v3.7.4 adds 12 more = 27 total)
- Theme structure: `signal-and-noise/functions.php` module map
- Memory entries:
  - `feedback_skills_plugins_docs_always` (the hard rule)
  - `feedback_read_framework_source` (verified twice during v3.7.x — applied again here for category-reuse + WP-CLI behavior)
  - `reference_ai_plugin_v1_features` (the duplicate-check that shapes §2's out-of-scope list)
  - `reference_desktop_mode_ai_copilot` (informs §11.3)
  - `reference_command_palette_js_only` (informs §11.1 — commands registered via JS in assets/desktop-mode.js)
