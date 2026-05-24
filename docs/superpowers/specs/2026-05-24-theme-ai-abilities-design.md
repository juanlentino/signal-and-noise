# Theme AI Abilities — Design Spec

**Date:** 2026-05-24
**Status:** Approved (brainstorm complete; ready for plan-phase)
**Target releases:** plugin v3.8.0, then theme v9.1.0
**Phase context:** Post-v3.7.3 maintenance pass — net-new feature work that extends the established v8.4.0 cross-package contract surface

---

## 1. Goal

Make the Signal & Noise theme a "super theme" by exposing its design knowledge and theme-native generative capabilities to the WordPress 7.0 Abilities API. Today, every SN AI feature is **brand-blind** — it generates content (meta descriptions, OG titles, Insights, drift findings) without knowing the brand's actual visual identity or content structure.

After this work, any AI consumer (the plugin's own AI features, the WP 7.0 AI Copilot, WP-CLI AI agents, future integrations) can:

1. **Read** the theme's design tokens, patterns, template structure, and content metadata as context
2. **Generate** brand-voiced content (page-note summaries, pattern suggestions, brand-alignment reports) that uses the theme's actual identity rather than generic defaults

All operations are **non-destructive** — read-only or generative. No theme.json mutations, no pattern registration changes, no file writes.

---

## 2. Scope

### In scope (9 abilities, all non-destructive)

**Read abilities (6):**
1. `signal-noise/get-design-tokens`
2. `signal-noise/list-block-patterns`
3. `signal-noise/get-active-template-structure`
4. `signal-noise/get-theme-version`
5. `signal-noise/get-page-notes-pillars`
6. `signal-noise/get-reading-time-for-slug`

**Generative abilities (3):**
7. `signal-noise/ai-generate-page-note-summary`
8. `signal-noise/ai-suggest-block-pattern`
9. `signal-noise/ai-validate-brand-alignment`

### Out of scope

- Anything mutating theme.json, patterns, templates, or files
- Anything overlapping `ai/ai` plugin v1.0.0's editorial features (alt text generation, generic summarization, content classification, image generation, comment moderation)
- SSH or filesystem operations
- Abilities tied to specific posts requiring write capability (those stay in the plugin's existing `ai-generate-*` set)
- Multi-site / network-level abilities

### Out of scope for Phase 1, possible future phases

- Theme-mutating abilities (would need destructive-op guards)
- Per-pattern AI generation (e.g., "fill out this Hero pattern with content")
- FSE template synthesis ("create a new template part for X")
- Theme.json color-palette suggestions

---

## 3. Architecture — Approach 1 (Plugin-registered, theme-implemented)

The plugin remains the single registration surface for SN abilities. Theme provides filter handlers via the established cross-package contract pattern (matching `sn_purge_all_caches_result`, `sn_clear_template_overrides_result`, `sn_og_font_paths`).

```
┌──────────────────────────────────────────────────────────────────┐
│  signal-and-noise-tools (plugin)                                 │
│                                                                  │
│   inc/abilities-registration.php                                 │
│     wp_register_ability( 'signal-noise/get-design-tokens', [    │
│        'execute_callback' => function() {                        │
│          $tokens = apply_filters(                                │
│            'sn_theme_design_tokens', null );                     │
│          if ( null === $tokens ) {                               │
│            return new WP_Error( 'theme_handler_missing', ... );  │
│          }                                                       │
│          return $tokens;                                         │
│        }                                                         │
│     ]);                                                          │
│                                                                  │
└────────────────────────────┬─────────────────────────────────────┘
                             │ apply_filters
                             ▼
┌──────────────────────────────────────────────────────────────────┐
│  signal-and-noise (theme)                                        │
│                                                                  │
│   inc/theme-ability-handlers.php                                 │
│     add_filter( 'sn_theme_design_tokens', function( $value ) {  │
│       if ( null !== $value ) return $value; // chained          │
│       return sn_theme_read_design_tokens();                     │
│     });                                                          │
│                                                                  │
└──────────────────────────────────────────────────────────────────┘
```

### Why this architecture

Three rationales:

1. **Consistency with established v8.4.0 contract surface.** The theme/plugin pair already uses this exact pattern for three operations. Adding more uses doesn't require maintainers to learn a new pattern.

2. **Plugin owns the stable interface.** External consumers (WP 7.0 AI Copilot, future integrations) see one ability namespace, one set of schemas, one auth surface. The theme implementation can evolve internally without breaking that contract.

3. **The "theme without plugin" case doesn't exist on this site.** The supposed benefit of theme-self-registration (Approach 2) is moot — `signal-and-noise` theme always ships alongside `signal-and-noise-tools` plugin on this single-author install. Spending complexity on theoretical decoupling buys nothing.

### Failure modes

| Situation | Behavior |
|---|---|
| Plugin installed, theme not active | `apply_filters` returns null → ability returns `WP_Error( 'theme_handler_missing' )` with status 503 |
| Plugin installed, theme listener throws | Caught by plugin's try/catch (defense-in-depth) → returns `WP_Error( 'theme_handler_error' )` with status 500 |
| Plugin not installed | Theme listener registered but never invoked — harmless |
| Both installed, AI Client not active (WP < 7.0) | Plugin's `wp_register_ability` calls are no-ops; theme listeners idle |

---

## 4. Ability catalog

### 4.1 Read abilities

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

**Theme filter:** `sn_theme_design_tokens`
**Implementation note:** Theme handler reads `theme.json` via `wp_get_global_settings()` and shapes into the schema above.

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

**Theme filter:** `sn_theme_block_patterns`
**Implementation note:** Theme handler enumerates `WP_Block_Patterns_Registry::get_instance()->get_all_registered()` and the corresponding category registry. Filters by `category` input if provided.

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

**Theme filter:** `sn_theme_active_template_structure`
**Implementation note:** Theme handler resolves the template via `get_block_template()`, parses with `parse_blocks()`, and shapes a summary (does NOT recurse into innerBlocks beyond a count — keeps payload size bounded).

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

**Theme filter:** `sn_theme_version_info`
**Implementation note:** Theme handler reads `wp_get_theme()` + `wp_is_block_theme()` + `wp_get_wp_version()`.

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

**Theme filter:** `sn_theme_page_notes_pillars`
**Implementation note:** Theme handler enumerates the pillar essays defined in `inc/page-notes-render.php` and returns their metadata. (Pillar list is currently hardcoded in `sn_notes_query_posts()` — handler reads from that source.)

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

**Theme filter:** `sn_theme_reading_time_for_slug`
**Implementation note:** Theme handler calls `sn_notes_reading_time_for_slug( $slug )` (the existing PHP function in `inc/page-notes-render.php`). Returns 0 minutes if the slug doesn't resolve to a published post.

---

### 4.2 Generative abilities

All generative abilities use `snt_ai_generate_with_constraints()` from the plugin's `inc/ai-bootstrap.php`. They count against the AI token budget. Sonnet 4.6 is the default model (per v3.7.2 pin).

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

**Theme filter:** `sn_theme_page_note_summary_context`
**Implementation:**
1. Plugin's execute_callback fetches post via `snt_ai_extract_post_text()`
2. Plugin calls theme filter `sn_theme_page_note_summary_context` which returns a system-prompt fragment defining the "industrial catalog" voice (file path: `inc/page-notes-render.php` defines the aesthetic vocabulary; the filter handler returns a curated string version)
3. Plugin composes prompt: `[theme context] + [post excerpt]` → calls `snt_ai_generate_with_constraints` with system instruction
4. Returns trimmed summary + tokens used

**Net-new vs `ai/ai`:** `ai/ai` does generic content summarization that's voice-agnostic. This one's output is grounded in the theme's specific aesthetic vocabulary (brutalist white-first, NIN-influenced, blood-red accents, terminal-mono row layouts) — produces summaries that READ like SN, not like a generic blog.

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

**Theme filter:** Indirect — uses `signal-noise/list-block-patterns` internally to get the pattern catalog.

**Implementation:**
1. Plugin fetches pattern list via the read ability above
2. Composes prompt: `[pattern catalog as JSON] + [draft content] + [system: pick 1-3 patterns and explain]`
3. Calls `snt_ai_generate_with_constraints` with JSON-output system instruction
4. Parses response; validates each `pattern_name` exists in the catalog; drops invalid suggestions
5. Returns top suggestions

**Net-new vs `ai/ai`:** `ai/ai` doesn't know about SN's pattern library. This is genuinely net-new — pattern-AWARE drafting assistant.

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

**Theme filter:** `sn_theme_brand_voice_guide`

**Implementation:**
1. Plugin calls theme filter for the brand voice guide (a curated system-prompt fragment defining SN voice: brutalist, technical-precise, NIN-aesthetic-aware, asphalt/blood/bone palette vocabulary)
2. Plugin also calls `signal-noise/get-design-tokens` ability internally for the palette data
3. Composes prompt: `[voice guide] + [palette tokens] + [content] + [system: score 0-100 and flag findings as JSON]`
4. Calls `snt_ai_generate_with_constraints`
5. Parses response; validates verdicts against enum; returns

**Net-new vs `ai/ai`:** `ai/ai`'s Editorial Notes evaluate grammar / SEO / readability / a11y. None of those check brand fit. This is genuinely net-new — site-specific brand-identity validation.

---

## 5. Theme-side filter contract

Each ability has a corresponding theme-side filter. All filters live in the new file:

```
signal-and-noise/inc/theme-ability-handlers.php
```

The file structure follows the modular convention from `functions.php`:

```php
<?php
/**
 * Signal & Noise — Theme-side filter handlers for plugin's AI abilities.
 *
 * Listens on the filters defined by the plugin's inc/abilities-registration.php
 * (see WORDPRESS-REFERENCE.md §10.x for the full cross-package contract).
 *
 * @package SignalNoise
 * @since 9.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_filter( 'sn_theme_design_tokens', 'sn_theme_handle_design_tokens', 10, 1 );
add_filter( 'sn_theme_block_patterns', 'sn_theme_handle_block_patterns', 10, 2 );
add_filter( 'sn_theme_active_template_structure', 'sn_theme_handle_template_structure', 10, 2 );
add_filter( 'sn_theme_version_info', 'sn_theme_handle_version_info', 10, 1 );
add_filter( 'sn_theme_page_notes_pillars', 'sn_theme_handle_page_notes_pillars', 10, 1 );
add_filter( 'sn_theme_reading_time_for_slug', 'sn_theme_handle_reading_time', 10, 2 );
add_filter( 'sn_theme_page_note_summary_context', 'sn_theme_handle_page_note_context', 10, 1 );
add_filter( 'sn_theme_brand_voice_guide', 'sn_theme_handle_brand_voice_guide', 10, 1 );

// Handler functions below — each returns the schema-shaped value or null/default.
function sn_theme_handle_design_tokens( $value ) { /* ... */ }
function sn_theme_handle_block_patterns( $value, $category ) { /* ... */ }
// etc.
```

### Filter contract conventions

All filters follow the same shape:

- **First argument:** the running value (initially null from the plugin's `apply_filters`)
- **Subsequent arguments (where applicable):** input parameters from the ability invocation
- **Return value:** schema-shaped data (matches the ability's output_schema) OR null if the handler decides not to respond
- **Chain semantics:** if a prior handler already returned non-null, this handler should NO-OP and pass through:
  ```php
  function sn_theme_handle_design_tokens( $value ) {
      if ( null !== $value ) { return $value; } // chained handler ran first
      return sn_theme_read_design_tokens();
  }
  ```

This pattern matches `sn_purge_all_caches_result` and keeps the filter composable.

### Update functions.php module map

Add new module entry:

```
inc/theme-ability-handlers.php — Filter listeners for the plugin's AI abilities (8 filters since theme v9.1.0)
```

### Update WORDPRESS-REFERENCE.md §10.x

Document the 8 new filters in the cross-package contract section. Total contract surface goes from 3 filters (since v8.4.0) to 11 filters (since v9.1.0).

---

## 6. Error handling

### Plugin side (registration)

Each ability's `execute_callback` follows this defensive pattern:

```php
'execute_callback' => function( $input ) {
    try {
        $value = apply_filters( 'sn_theme_design_tokens', null );
    } catch ( \Throwable $e ) {
        error_log( 'SN theme ability handler threw: ' . $e->getMessage() );
        return new WP_Error(
            'theme_handler_error',
            sprintf( 'Theme handler error: %s', $e->getMessage() ),
            array( 'status' => 500 )
        );
    }

    if ( null === $value ) {
        return new WP_Error(
            'theme_handler_missing',
            'Theme listener not registered. Ensure signal-and-noise theme v9.1.0+ is active.',
            array( 'status' => 503 )
        );
    }

    return $value;
},
```

The v3.7.1 lesson directly applies: the `error_log` line is non-negotiable. Silent failures are exactly the class of bug v3.7.1 was caused by; instrumenting at the catch site means future failures surface in `debug.log` instead of being invisibly swallowed.

### Theme side (handlers)

Each handler is defensive about WP API availability (in case it's called before init, in tests, etc.):

```php
function sn_theme_handle_design_tokens( $value ) {
    if ( null !== $value ) { return $value; }
    if ( ! function_exists( 'wp_get_global_settings' ) ) { return null; }
    // ... read and shape
}
```

### Schema validation

WP's Abilities API validates input against `input_schema` BEFORE `permission_callback` fires. Invalid input produces a typed REST error automatically. We don't need to re-validate in the execute_callback.

For output: schemas are documentation, but the API also validates `output_schema` per recent WP versions. Test fixtures should match the declared shape exactly to avoid runtime validation errors.

---

## 7. Testing strategy

### Plugin-side unit tests

New file: `signal-and-noise-tools/tests/theme-abilities.php`

Standalone PHP harness matching the existing pattern (`tests/health-checks.php`, `tests/insights.php`).

**Scenarios per read ability:**
- Filter returns valid shape → ability returns that value
- Filter returns null → ability returns `WP_Error('theme_handler_missing')` with 503
- Filter throws → ability returns `WP_Error('theme_handler_error')` with 500 (verifies the v3.7.1 lesson: errors logged AND returned)
- Input schema validation (mock invalid input → ability returns 400)
- Permission check (logged-out → 401)

**Scenarios per generative ability:**
- AI gate false → returns `WP_Error('snt_ai_unavailable')` (test 8 lessons preserved)
- AI returns valid response → ability returns parsed output
- AI returns malformed JSON → ability gracefully degrades
- Model preference is `claude-sonnet-4-6` (v3.7.2 lock-in)

Estimate: ~30 new test blocks, ~80 assertions. Total project assertion count: 441 + ~80 = ~521.

### Theme-side smoke tests

The theme's PHP doesn't have a test harness today. Two options:

A. Add a minimal harness mirroring the plugin's style — `signal-and-noise/tests/theme-ability-handlers.php` — that stubs WP functions and verifies each handler returns schema-shaped data.

B. Defer theme-side tests; rely on plugin-side mocks to cover both halves.

**Recommendation: A.** The v3.7.1 lesson cuts both ways — if we only test the plugin half, the theme half can silently regress. A minimal harness (matching `tests/health-checks.php` style) costs ~1 hour to set up and catches future regressions.

### Manual smoke tests (per ability, post-deploy)

For each ability, verify via `wp eval` over SSH:

```bash
wp eval '$r = WP_Abilities_API::get_instance()->get_ability("signal-noise/get-design-tokens")->execute([]); echo wp_json_encode($r);'
```

Confirms:
- Ability is registered
- Theme handler fires
- Output matches schema

---

## 8. Release plan

### Plugin v3.8.0 ships first (minor bump — new user-visible capability)

**Scope:**
1. `inc/abilities-registration.php` — add 9 new `wp_register_ability` calls under the existing `diagnostics`, `content`, and `ai-generation` categories
2. `tests/theme-abilities.php` — new test file
3. CHANGELOG entry documenting all 9 abilities + the cross-package contract

**Behavior on day-of-ship:** all 9 abilities return `WP_Error('theme_handler_missing')` with status 503 because the theme hasn't shipped its v9.1.0 listeners yet. **This is intentional and safe** — typed errors with clear remediation messages, no silent failures.

**Patch cap status:** plugin currently at v3.7.3. Patch cap is 7 per minor; we have 4 more patches available before rolling to v3.8.0 naturally. Since this is net-new functionality (new abilities = new user-visible capability), it's a **minor bump regardless** — 3.7.3 → 3.8.0.

### Theme v9.1.0 ships second (minor bump — new module)

**Scope:**
1. `inc/theme-ability-handlers.php` — new file, 8 filter handler functions
2. `functions.php` — module map comment update (one line added)
3. `docs/WORDPRESS-REFERENCE.md` — §10.x cross-package contract update (8 new filters documented)
4. `tests/theme-ability-handlers.php` — new test file (per testing strategy option A)
5. CHANGELOG entry documenting the 8 filter handlers

**Behavior after both ship:** all 9 abilities return their intended data. AI consumers see the full surface.

**Minor cap status:** theme currently at v9.0.0. Minor cap is 5 per major; 9.0 → 9.1 is well within the cap.

### Why plugin first, not theme first

Per the user's selection (option 1 in the brainstorm):

- Plugin v3.8.0 ships → AI consumers see the 9 new abilities documented; calling them returns clean 503 errors with diagnostic messages
- Theme v9.1.0 ships → the 503s clear, real data flows
- Briefly broken state is acceptable BECAUSE the broken state has a typed error code with clear remediation, not a silent failure

The reverse order (theme first) would mean: theme filters exist but nothing calls them — harmless but useless. Either order works; the user's selection minimizes the window during which consumers see broken state without diagnostic clarity.

---

## 9. Self-review checklist

Performed inline against this spec:

- [x] **No placeholders** — every section concrete; no "TBD" or "TODO" markers
- [x] **Schemas defined for all 9 abilities** — input + output JSON Schema written
- [x] **Architecture decision justified** — three approaches compared; recommendation reasoned
- [x] **Failure modes enumerated** — both plugin-side and theme-side error paths
- [x] **Tests scoped** — both plugin-side and theme-side, with assertion estimates
- [x] **Release plan ordered** — plugin v3.8.0 → theme v9.1.0, version-cap math checked
- [x] **Out-of-scope explicit** — Phase 1 boundaries documented
- [x] **v3.7.x lessons applied** — `error_log` at catch sites; schemas grounded in actual WP API source; tests cover both sides
- [x] **Memory rules respected** — `ai/ai` v1.0.0 features check; no destructive ops; no dark mode (it's intentionally omitted)
- [x] **Cross-package contract surface updated** — 3 filters since v8.4.0 → 11 filters since v9.1.0; WORDPRESS-REFERENCE.md update flagged

---

## 10. Future phases (out of scope here, but worth recording)

**Phase 2 candidates (would need their own spec):**

- `signal-noise/ai-generate-pattern-content` — fill in a chosen pattern with content (input: pattern slug + topic; output: ready-to-paste block markup)
- `signal-noise/ai-rewrite-in-brand-voice` — transform external copy to SN voice (input: source text; output: rewritten text)
- `signal-noise/get-design-system-summary` — Markdown summary of design tokens optimized for AI-prompt embedding (saves tokens vs the full JSON)

**Phase 3 candidates (destructive ops — explicitly out of scope per user constraint):**

- `signal-noise/update-color-palette` — modify theme.json
- `signal-noise/register-pattern-from-blocks` — persist a new pattern

These remain out of scope until/unless the user revisits the "non-destructive only" constraint.

---

## 11. References

- WP 7.0 Abilities API source: [WordPress/abilities-api on trunk](https://github.com/WordPress/abilities-api) (verified 2026-05-17 in the plugin v2.0.4 audit)
- Plugin v3.7.x AI gate fix arc: `docs/superpowers/handoffs/2026-05-21-maintenance-pass-in-flight.md`
- Cross-package contract: `docs/WORDPRESS-REFERENCE.md` §10.0 (current 3-filter surface; this spec extends to 11)
- Existing ability registrations: `signal-and-noise-tools/inc/abilities-registration.php` (16 abilities across 5 categories)
- Theme structure: `signal-and-noise/functions.php` module map
- Memory: `feedback_skills_plugins_docs_always`, `feedback_read_framework_source`, `reference_ai_plugin_v1_features`
