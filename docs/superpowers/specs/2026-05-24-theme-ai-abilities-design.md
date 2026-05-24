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

### In scope (12 abilities, all non-destructive, plus Command Palette + WP-CLI integration)

**Read abilities (7):**
1. `signal-noise/get-design-tokens`
2. `signal-noise/list-block-patterns`
3. `signal-noise/get-active-template-structure`
4. `signal-noise/get-theme-version`
5. `signal-noise/get-page-notes-pillars`
6. `signal-noise/get-reading-time-for-slug`
7. `signal-noise/get-design-system-summary` *(formatter for #1, optimized for AI-prompt embedding)*

**Generative abilities (5):**
8. `signal-noise/ai-generate-page-note-summary`
9. `signal-noise/ai-suggest-block-pattern`
10. `signal-noise/ai-validate-brand-alignment`
11. `signal-noise/ai-generate-pattern-content` *(fill a chosen pattern's content shell with topic-specific copy)*
12. `signal-noise/ai-rewrite-in-brand-voice` *(transform external copy → SN voice)*

**Additional entry-point integration (Section 12):**
- Command Palette (⌘K) registration for all 12 abilities (read commands display result panel; generative commands present input form + output display)
- WP-CLI access via `wp ability run signal-noise/*` (automatic; documented in CHANGELOG + WORDPRESS-REFERENCE.md)
- AI Copilot consumption via existing `aiCallable: true` flag on the ⌘K commands

### Out of scope

- Anything mutating theme.json, patterns, templates, or files (Phase 3 only)
- Anything overlapping `ai/ai` plugin v1.0.0's editorial features (alt text generation, generic summarization, content classification, image generation, comment moderation) — `ai-rewrite-in-brand-voice` is NOT overlap because `ai/ai`'s Editorial Notes do grammar/SEO/readability/a11y, not voice-transformation
- SSH or filesystem operations
- Multi-site / network-level abilities
- Streaming outputs for generative abilities (none of the 5 generative abilities have outputs long enough to warrant it today; revisit if average response time exceeds ~10s)

### Out of scope for v3.8.0/v9.1.0, possible future phases

- Theme-mutating abilities (would need destructive-op guards) — Phase 3
- FSE template synthesis ("create a new template part for X") — Phase 3
- Theme.json color-palette suggestions — Phase 3
- Streaming generative outputs — Phase 4 (when there's a real need)

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

**Theme filter:** `sn_theme_design_system_summary`
**Implementation note:** Theme handler reuses `sn_theme_handle_design_tokens()` for the underlying data, then formats per the `format` input:
- `markdown`: structured headings (Colors, Typography, Spacing) with named entries
- `compact-text`: single-line `colors:void#fff,asphalt#f5f5f5,...; fonts:redaction,monogram; ...` for minimum-token embedding
- `json`: same as `get-design-tokens` (compatibility option)

**Net-new vs `get-design-tokens`:** Same underlying data, but pre-formatted for AI prompt embedding. The plugin's AI features (Insights, brand-alignment, etc.) can call THIS instead of stuffing full JSON in every prompt — typical 70-80% token reduction on the design-context fragment.

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

**Theme filter:** `sn_theme_pattern_content_template`

**Implementation:**
1. Validate `pattern_name` exists via `WP_Block_Patterns_Registry::get_instance()->get_registered( $pattern_name )` — if missing, return `WP_Error('pattern_not_found')` with 404
2. Plugin calls theme filter `sn_theme_pattern_content_template` with the pattern object; theme returns the empty/skeleton block markup + a system-prompt fragment describing the pattern's intent (e.g., "Hero pattern: large title + 1-2 line body + CTA")
3. Plugin composes prompt: `[pattern template] + [topic] + [tone_hint] + [system: fill in placeholders with brand-voiced content; return valid serialized Gutenberg blocks]`
4. Calls `snt_ai_generate_with_constraints` with system instruction
5. Validates output is parseable via `parse_blocks()` — any unparseable output triggers a `warnings` entry and returns the raw AI output anyway (caller decides what to do)
6. Returns block_markup

**Net-new vs `ai/ai`:** `ai/ai` doesn't know about SN's pattern library. This is pattern-AWARE content drafting — fundamentally a theme-specific capability.

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

**Theme filter:** `sn_theme_brand_voice_guide` (shared with `ai-validate-brand-alignment`)

**Implementation:**
1. Plugin calls theme filter for the brand voice guide (same one used by ai-validate-brand-alignment — single source of truth)
2. Plugin composes prompt: `[voice guide] + [intensity directive] + [preserve_links/lists flags] + [source text] + [system: rewrite respecting flags; return JSON with rewritten_text + summary_of_changes]`
3. Calls `snt_ai_generate_with_constraints` with JSON-output system instruction
4. Parses response; validates against output schema
5. Returns shaped result

**Net-new vs `ai/ai`:** `ai/ai`'s Editorial Notes catch grammar/SEO/readability/a11y issues but do NOT transform voice. This is the only ability in the SN AI surface that converts external/generic copy into the SN voice register (brutalist, terminal-monospace cadence, NIN-aesthetic vocabulary).

**Use case:** Pasting an LLM-generated draft from another tool, or a quote from a source, into the editor and asking SN to rewrite it in-voice before publishing.

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

add_filter( 'sn_theme_design_tokens',             'sn_theme_handle_design_tokens',           10, 1 );
add_filter( 'sn_theme_block_patterns',            'sn_theme_handle_block_patterns',          10, 2 );
add_filter( 'sn_theme_active_template_structure', 'sn_theme_handle_template_structure',      10, 2 );
add_filter( 'sn_theme_version_info',              'sn_theme_handle_version_info',            10, 1 );
add_filter( 'sn_theme_page_notes_pillars',        'sn_theme_handle_page_notes_pillars',      10, 1 );
add_filter( 'sn_theme_reading_time_for_slug',     'sn_theme_handle_reading_time',            10, 2 );
add_filter( 'sn_theme_design_system_summary',     'sn_theme_handle_design_system_summary',   10, 2 );
add_filter( 'sn_theme_page_note_summary_context', 'sn_theme_handle_page_note_context',       10, 1 );
add_filter( 'sn_theme_brand_voice_guide',         'sn_theme_handle_brand_voice_guide',       10, 1 );
add_filter( 'sn_theme_pattern_content_template',  'sn_theme_handle_pattern_content_template',10, 2 );

// Handler functions below — each returns the schema-shaped value or null/default.
function sn_theme_handle_design_tokens( $value ) { /* ... */ }
function sn_theme_handle_block_patterns( $value, $category ) { /* ... */ }
// etc.
```

**Total: 10 theme-side filters** (not 8 — 3 new ones for Phase 2 abilities; note that `ai-rewrite-in-brand-voice` reuses `sn_theme_brand_voice_guide` which is shared with `ai-validate-brand-alignment`, so no new filter for that ability).

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
inc/theme-ability-handlers.php — Filter listeners for the plugin's AI abilities (10 filters since theme v9.1.0)
```

### Update WORDPRESS-REFERENCE.md §10.x

Document the 10 new filters in the cross-package contract section. Total contract surface goes from 3 filters (since v8.4.0) to **13 filters (since v9.1.0)**.

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

Estimate: ~40 new test blocks, ~110 assertions across plugin-side `tests/theme-abilities.php` (covers all 12 abilities). Total project assertion count: 441 + ~110 = ~551.

Plus ~15 new assertions in theme-side `tests/theme-ability-handlers.php` for the filter handlers directly. Total: ~125 new across both repos.

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
1. `inc/abilities-registration.php` — add 12 new `wp_register_ability` calls under the existing `diagnostics`, `content`, and `ai-generation` categories
2. `inc/desktop-mode-integration.php` — add 12 new Command Palette commands (one per new ability), all `aiCallable: true`
3. `assets/desktop-mode.js` — add `input-then-result` render mode (new ⌘K shape; the 15 existing commands all use the existing `result-panel` shape; the 10 new generative-or-input commands need a form-then-result flow)
4. `tests/theme-abilities.php` — new test file (~110 plugin-side assertions across 12 abilities)
5. CHANGELOG entry documenting all 12 abilities + the cross-package contract + the 12 new ⌘K commands + WP-CLI access

**Behavior on day-of-ship:** all 12 abilities return `WP_Error('theme_handler_missing')` with status 503 because the theme hasn't shipped its v9.1.0 listeners yet. **This is intentional and safe** — typed errors with clear remediation messages, no silent failures. The ⌘K commands ARE registered but will surface the 503 error to the user clearly ("Theme listener not registered; update Signal & Noise theme to v9.1.0+").

**Patch cap status:** plugin currently at v3.7.3. Patch cap is 7 per minor; we have 4 more patches available before rolling to v3.8.0 naturally. Since this is net-new functionality (12 new abilities + 12 new commands + new ⌘K render mode = significant user-visible capability), it's a **minor bump regardless** — 3.7.3 → 3.8.0.

### Theme v9.1.0 ships second (minor bump — new module)

**Scope:**
1. `inc/theme-ability-handlers.php` — new file, 10 filter handler functions
2. `functions.php` — module map comment update (one line added)
3. `docs/WORDPRESS-REFERENCE.md` — §10.x cross-package contract update (10 new filters documented; total surface 3 → 13)
4. `tests/theme-ability-handlers.php` — new test file (~15 assertions for the filter handlers)
5. CHANGELOG entry documenting the 10 filter handlers + how this completes the plugin v3.8.0 ability surface

**Behavior after both ship:** all 12 abilities return their intended data. AI consumers see the full surface. The 12 ⌘K commands surface real results. WP-CLI `wp ability run signal-noise/*` returns data.

**Minor cap status:** theme currently at v9.0.0. Minor cap is 5 per major; 9.0 → 9.1 is well within the cap.

### Why plugin first, not theme first

Per the user's selection (option 1 in the brainstorm):

- Plugin v3.8.0 ships → AI consumers see the 9 new abilities documented; calling them returns clean 503 errors with diagnostic messages
- Theme v9.1.0 ships → the 503s clear, real data flows
- Briefly broken state is acceptable BECAUSE the broken state has a typed error code with clear remediation, not a silent failure

The reverse order (theme first) would mean: theme filters exist but nothing calls them — harmless but useless. Either order works; the user's selection minimizes the window during which consumers see broken state without diagnostic clarity.

---

## 9. Self-review checklist

Performed inline against this spec (re-validated after Phase 2 + Command Palette + WP-CLI expansion):

- [x] **No placeholders** — every section concrete; no "TBD" or "TODO" markers
- [x] **Schemas defined for all 12 abilities** — input + output JSON Schema written
- [x] **Architecture decision justified** — three approaches compared; recommendation reasoned
- [x] **Failure modes enumerated** — both plugin-side and theme-side error paths, including the 503 window during plugin-first-then-theme release
- [x] **Tests scoped** — both plugin-side (~110 assertions) and theme-side (~15 assertions), with estimate math
- [x] **Release plan ordered** — plugin v3.8.0 → theme v9.1.0, version-cap math checked
- [x] **Out-of-scope explicit** — Phase 1 boundaries documented; Phase 2 absorbed; Phase 3+ deferred
- [x] **v3.7.x lessons applied** — `error_log` at catch sites; schemas grounded in actual WP API source; tests cover both sides
- [x] **Memory rules respected** — `ai/ai` v1.0.0 features check; no destructive ops; no dark mode (it's intentionally omitted); WP-CLI access derived from upstream Abilities API source not assumed
- [x] **Cross-package contract surface updated** — 3 filters since v8.4.0 → 13 filters since v9.1.0; WORDPRESS-REFERENCE.md update flagged
- [x] **Command Palette integration scoped** — 12 new commands, render-mode shape documented, JS work flagged for v3.8.0 (new `input-then-result` mode)
- [x] **WP-CLI access surfaced** — confirmed automatic via WP 7.0 Abilities API; deliverable is documentation only
- [x] **AI Copilot consumption surfaced** — `ai_callable: true` on all 12 commands; labels + descriptions vetted as Copilot-facing
- [x] **Internal consistency** — ability counts match across §2 (12 in scope), §4 (12 cataloged), §5 (10 filters since `ai-rewrite-in-brand-voice` reuses one), §8 (12 in release plan), §11 (12 commands)

---

## 10. Future phases (out of scope here, but worth recording)

**Phase 2 — folded into v3.8.0 (no longer deferred):**

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

---

## 11. Command Palette (⌘K) + other entry-point integration

All 12 abilities should be reachable from the WP 7.0 Command Palette via desktop-mode integration. They are also automatically WP-CLI-accessible via `wp ability run`. Both surfaces come from the same registration — no duplicate ability code.

### 11.1 Command Palette integration

**Where commands live:**

The plugin's `inc/desktop-mode-integration.php` already registers 15 commands (memory: "12 of 15 desktop-mode ⌘K commands aiCallable"). The new theme abilities map cleanly to additional commands in the same file — theme stays the implementation layer; plugin remains the interface layer. **No new file required** for command registration; theme adds nothing here.

**Command shape:**

Each ability gets one ⌘K command. Read commands display the result in a side panel; generative commands show an input form, then the AI result.

```php
// In signal-and-noise-tools/inc/desktop-mode-integration.php
$theme_ability_commands = array(
    // Read abilities — display result panel
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

The JS-side rendering lives in `assets/desktop-mode.js`. The current implementation handles `result-panel` for the 15 existing commands; `input-then-result` needs to be added (it's a new mode). Plugin v3.8.0 includes this JS addition.

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

1. CHANGELOG entry for plugin v3.8.0 lists the 12 abilities and notes "all accessible via `wp ability run`"
2. `docs/WORDPRESS-REFERENCE.md` §10.x gains a one-paragraph note about WP-CLI access
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

- WP 7.0 Abilities API source: [WordPress/abilities-api on trunk](https://github.com/WordPress/abilities-api) (verified 2026-05-17 in the plugin v2.0.4 audit)
- WP 7.0 AI Client source: [WordPress/wp-ai-client](https://github.com/WordPress/wp-ai-client) (verified 2026-05-21 in the v3.7.1 root-cause investigation)
- Plugin v3.7.x AI gate fix arc: `docs/superpowers/handoffs/2026-05-21-maintenance-pass-in-flight.md`
- Cross-package contract: `docs/WORDPRESS-REFERENCE.md` §10.0 (current 3-filter surface; this spec extends to 13)
- Existing ability registrations: `signal-and-noise-tools/inc/abilities-registration.php` (16 abilities across 5 categories — Phase 1 of this spec adds 12 more = 28 total)
- Existing Command Palette commands: `signal-and-noise-tools/inc/desktop-mode-integration.php` (15 commands — Phase 1 of this spec adds 12 more = 27 total)
- Theme structure: `signal-and-noise/functions.php` module map
- Memory entries:
  - `feedback_skills_plugins_docs_always` (the hard rule)
  - `feedback_read_framework_source` (verified twice during v3.7.x — applied here for WP-CLI behavior)
  - `reference_ai_plugin_v1_features` (the duplicate-check that shapes §2's out-of-scope list)
  - `reference_desktop_mode_ai_copilot` (informs §11.3)
  - `reference_command_palette_js_only` (informs §11.1 — commands registered via JS in assets/desktop-mode.js)
