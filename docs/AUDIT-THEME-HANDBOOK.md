# Signal & Noise theme — WP handbook conformance audit

**Date:** 2026-05-16
**Theme version at audit:** v8.5.3
**Auditor:** AI session pass against WordPress Plugin Handbook + Theme Handbook + WP core source
**Scope:** PHP code in `inc/`, `functions.php`, `style.css` header, `theme.json`, FSE templates/parts, `assets/`
**Out of scope:** Public-facing block markup conventions (covered by the `gutenberg-block-authoring` skill on-demand), the companion plugin (separately audited and rebuilt in plugin v1.14.0)

## TL;DR

**The theme is in excellent shape. No version bump warranted from this audit.** Plugin v1.14.0's comprehensive cleanup pass surfaced ~25 inline-style violations across plugin admin PHP; the equivalent sweep across theme PHP turned up **zero**. Theme follows the modern block-theme conventions correctly (no obsolete `add_theme_support` calls, no classic-theme primitives, no PHP rendering of what should be FSE/theme.json).

Two cosmetic-only findings warrant inline fixes (no version bump per CLAUDE.md):
1. `functions.php` docblock says `@version 8.4.0` — stale by 3 versions (theme is at 8.5.3).
2. `inc/patterns.php` docblock says `@since 7.5.0` — accurate but pre-Phase 3 (when patterns.php existed earlier in the theme's life). Leave as historical reference.

## Audit framework

The audit checks the theme against four reference sources per CLAUDE.md's "read framework source first" rule:

| Reference | Where checked |
|---|---|
| **WP Theme Handbook** — Block Themes chapter (developer.wordpress.org/themes/block-themes/) | theme.json structure, template part conventions, pattern registration |
| **WP Plugin Handbook** — wp_enqueue_* + nonce + capability + escaping (already verified for plugin v1.14.0) | assets-frontend.php enqueue pattern |
| **WP CSS Coding Standards** | All `.sn-*` class names use lowercase + hyphens (zero camelCase/underscores) |
| **WP core source** — `wp-includes/class-wp-http.php`, `wp-admin/includes/class-wp-upgrader.php`, `wp-admin/css/{common,forms}.css` | Update integration filters (already verified in plugin v1.10.x–v1.13.x work) |

## Findings by dimension

### 1. Theme bootstrap (`functions.php`)

| Item | Result |
|---|---|
| File size | 43 LOC — clean composition-only bootstrap. |
| `if ( ! defined( 'ABSPATH' ) ) exit;` | ✓ Present (line 30). |
| `require_once` pattern | ✓ Used for all module imports. No `include` or `require` lookup-side-effects. |
| `add_action`/`add_filter` direct calls | ✓ None (correctly delegated to module files). |
| Stale docblock `@version 8.4.0` | 🟡 **Minor fix:** update to `@version 8.5.3` (or remove version from docblock — it duplicates `style.css` Version header). |

**Recommendation:** Drop the `@version` line from `functions.php` docblock entirely. The `style.css` header is the canonical version source for WP-admin display + this audit + future-me. Duplicating it in a docblock invites exactly this drift.

### 2. Theme setup (`inc/setup.php`)

| Item | Result |
|---|---|
| `add_theme_support()` calls | ✓ **Zero** — modern block themes auto-enable `post-thumbnails`, `responsive-embeds`, `editor-styles`, `html5`, `automatic-feed-links` since WP 5.9. Theme correctly doesn't redeclare them. |
| `add_editor_style()` | ✓ Used with the 5-stylesheet array matching the public cascade — canonical for block themes that want unified editor + public rendering. |
| i18n bootstrap | ✓ Intentionally absent (documented in docblock — single-author surface, no translation files). |
| Shortcodes | `[current_year]` registered via `add_shortcode` on `init` — canonical. |
| `render_block` filter for shortcode resolution inside block templates | ✓ Used — handbook-recommended pattern for shortcode-in-FSE compatibility. |

No issues.

### 3. Asset enqueuing (`inc/assets-frontend.php`)

| Item | Result |
|---|---|
| `wp_enqueue_style` / `wp_enqueue_script` usage | ✓ Canonical signatures with `($handle, $src, $deps, $ver, $in_footer)`. |
| Dependency chain | ✓ Explicit: `sn-base` → `sn-layout` → `sn-components` → `sn-forms` → `sn-responsive`. No unordered enqueues. |
| Cache-busting versions | ✓ `sn_asset_ver()` helper uses `filemtime()` — auto-busts on deploy. |
| `get_theme_file_uri()` | ✓ Used (correct for child-theme overridability if ever added). |
| `wp_localize_script` / `wp_add_inline_script` | Not used (no JS that needs PHP-injected data). Correct. |
| `add_action('wp_enqueue_scripts', ...)` | ✓ Hooked on the right action (not `init` or `wp_head`). |
| Inline `<style>` / `<script>` in PHP output | ✓ **Zero** — all styles in `assets/css/*.css`, all JS in `assets/js/*.js`. |

No issues.

### 4. Frontend filters (`inc/frontend-filters.php`)

| Item | Result |
|---|---|
| Output buffer for `<meta name="generator">` stripping | ✓ Standard hardening pattern; doesn't break WP core's emit since it runs in `wp_head` after-priority. |
| oEmbed cleanup | Reasonable — removes unused discovery links to trim `<head>`. |
| Skip link normalization | ✓ Accessibility-positive. |

No issues.

### 5. OG fonts (`inc/og-fonts.php`)

| Item | Result |
|---|---|
| `sn_og_font_paths` filter | ✓ Returns array of absolute paths via `get_theme_file_path()` — canonical for asset paths in theme PHP. |
| Cross-package contract | ✓ Documented in WP-REFERENCE §10.0 (3 hooks). Plugin's `og-card-generator.php` applies this filter. |

No issues.

### 6. WP-native update integration (`inc/wp-update-integration.php` + `inc/wp-update-git-preservation.php`)

Both files audited extensively across v8.5.0–v8.5.3:
- Filter signatures verified against `wp-admin/includes/class-wp-upgrader.php` (WP-REFERENCE §10.5).
- `accept_args` correctness: 4 for `upgrader_source_selection`, 2 for `pre_install`, 3 for `post_install`.
- `WP_Error` semantics: aborts install when returned from `pre_install`; honored by `upgrader_source_selection`.
- `.git` preservation: atomic same-FS `rename()`, backup under `wp-content/upgrade/`, admin_init self-recovery.

No additional issues found in this pass.

### 7. Template overrides (`inc/page-notes-template.php` + `inc/page-notes-render.php`)

| Item | Result |
|---|---|
| `template_include` hook | ✓ Documented edge case (WP-REFERENCE §10.4). Defensive design with three-layer fallback. |
| PHP-authoritative rendering | ✓ Intentional — eliminates FSE block-template resolution as a failure surface for `/notes`. |
| Inline `<style>` in render PHP | The `inc/page-notes-render.php` file emits inline styles in the rendered HTML — that's CONTENT styling for the `/notes` page, equivalent to a block pattern's inline block-supports markup. Same exception class as `inc/seed-content/*.html` and `inc/content-rendering-helpers.php`: rendered HTML, not admin PHP. **Correct as-is.** |

No issues.

### 8. Block pattern registration (`inc/patterns.php`)

| Item | Result |
|---|---|
| Category registration only | ✓ Patterns themselves auto-register from `patterns/` directory per WP 5.9+ convention. |
| Hooked on `init` | ✓ Canonical for global state. |
| Single category strategy | ✓ Documented expansion threshold (~10 items → split). |

No issues.

### 9. Template maintenance (`inc/template-maintenance.php`)

| Item | Result |
|---|---|
| Cross-package filter listeners | ✓ Hooks `sn_purge_all_caches_result` + `sn_clear_template_overrides_result` per the documented 3-hook contract surface (WP-REFERENCE §10.0). |
| Capability gates | All admin-side actions gated on `manage_options`. |

No issues.

### 10. `theme.json` — the modern block theme manifest

| Item | Current value | Recommendation |
|---|---|---|
| `$schema` | `https://schemas.wp.org/wp/6.9/theme.json` | 🟢 Current (`6.9` is latest). After WP 7.0 ships on 2026-05-20, update to `wp/7.0/theme.json` for the new schema features (none required, but tooling auto-completion benefits). Not blocking. |
| `version` | `3` | ✓ The only valid value per the schema's `"const": 3`. |
| `settings.appearanceTools` | `true` | ✓ Canonical for modern themes — unlocks background, border, color, dimensions, position, spacing, typography UI controls without per-block declarations. |
| `settings.color.defaultPalette` | `false` | ✓ Theme provides its own brutalist palette; correctly disables WP defaults. |
| `settings.color.defaultGradients` | `false` | ✓ Brutalist theme doesn't use gradients; correctly disables defaults. |
| `settings.color.palette` | 7 named colors (void/asphalt/concrete/rust/bone/blood/signal) | ✓ Slugs follow handbook convention (lowercase + dashes if multi-word). Names are brand-appropriate. |
| `settings.typography.fluid` | `true` | ✓ Modern fluid typography (WP 6.1+). |
| `settings.typography.fontFamilies` | Bebas Neue + DM Mono declared | ✓ Self-hosted via `assets/fonts/` — no Google Fonts CDN dependency (privacy + perf positive). |
| `settings.layout.contentSize` / `wideSize` | `720px` / `1200px` | ✓ Canonical layout sizes for FSE alignment. |
| `settings.useRootPaddingAwareAlignments` | NOT SET | 🟢 **Optional consideration:** setting this to `true` makes `.alignfull` blocks honor root padding (canonical for full-bleed blocks that still respect site gutters). Currently absent. Could improve full-bleed behavior on hero blocks. Test before adopting. |
| `styles` | `blocks`, `color`, `elements`, `spacing`, `typography` populated | ✓ Standard structure. |
| `customTemplates` | Defined for custom page templates | ✓ Correct usage. |
| `templateParts` | Defined for `header` + `footer` parts | ✓ Matches actual files in `parts/`. |

**Recommendation:** Consider adding `"useRootPaddingAwareAlignments": true` to `settings`. **Test in a feature branch first** — it changes how `.alignfull` blocks render (root padding is applied to the contents, not the block itself), which can subtly affect any existing full-width section. Not a fix; a deliberate behavior shift if you want full-bleed-with-margins behavior.

### 11. FSE templates (`templates/*.html` + `parts/*.html`)

| Item | Result |
|---|---|
| File presence | ✓ All expected templates present: `404.html`, `front-page.html`, `home.html`, `index.html`, `single.html`, `page.html`, `page-{about,contact,music,notes,provenance,resume,services}.html`. |
| Template parts | ✓ `header.html` + `footer.html` in `parts/`. |
| Inline `style="..."` on blocks | Present and **correct** — that's how FSE/Gutenberg serializes block UI choices (alignment, color, spacing) into the saved markup. The block editor produces this; it's not hand-authored CSS-in-HTML. Same applies to `<!-- wp:* -->` block delimiters. |
| Block-recovery risk | Not assessed in this audit. The `gutenberg-block-authoring` skill should be invoked for any template edits. |

No issues from a handbook-conformance perspective. Future template edits should use the `gutenberg-block-authoring` skill to avoid block-recovery.

### 12. CSS architecture (`assets/css/*.css`)

| File | LOC | Role |
|---|---|---|
| `base.css` | 163 | Reset, root variables, base typography |
| `critical.css` | 504 | Above-fold critical CSS (separate enqueue path?) |
| `layout.css` | 312 | Grid + container + spacing scales |
| `components.css` | 558 | Component-level styles (cards, navs, forms) |
| `forms.css` | 205 | Form elements |
| `responsive.css` | 410 | Media-query overrides |

**Cascade ordering:** `base → layout → components → forms → responsive` — enforced via the `wp_enqueue_style` `$deps` chain in `inc/assets-frontend.php`. ✓ Canonical.

**Critical CSS file (`critical.css`)** — at 504 LOC it's larger than typical "above-fold" critical CSS (usually ≤14 KB inlined). Not necessarily a problem — could be deliberately scoped. **Not audited deeply this pass**; flagging as "worth reviewing" if perf optimization is ever a goal.

### 13. JavaScript (`assets/js/sticky-header.js`)

27 LOC. Single concern: add `.is-scrolled` class to header on scroll.

| Item | Result |
|---|---|
| IIFE wrapping | ✓ No globals leaked. |
| Defensive null check | ✓ `if ( ! header ) return;` before listener registration. |
| `requestAnimationFrame` throttling | ✓ Canonical perf pattern for scroll handlers. |
| `{ passive: true }` listener option | ✓ Browsers skip the `preventDefault` check, eliminating jank on scroll. |
| `wp_enqueue_script` registration | ✓ Enqueued in `inc/assets-frontend.php` with footer load + cache-bust. |

No issues.

### 14. Accessibility (sampled — not exhaustive)

This audit is NOT a full WCAG conformance review. Sampled items:

| Item | Result |
|---|---|
| Skip link | ✓ Normalized in `inc/frontend-filters.php` (default WP skip link enabled). |
| Editor styles match public styles | ✓ `add_editor_style()` includes the same 5-file cascade — editor accurately previews public output. |
| Color contrast | Not verified here. Brutalist palette uses high-contrast black/white/red — likely passes AA on text, but full review needed before claiming AAA. |
| Keyboard navigation | Not verified here. JS-driven sticky-header doesn't block keyboard scroll. |
| Reduced-motion preferences | Not verified here. Worth a sweep if any CSS uses `transition` / `animation` heavily. |

**Recommendation:** A dedicated WCAG audit pass (separate from this handbook-conformance audit) could be its own deliverable. Out of scope for this session.

### 15. Security (sampled — not exhaustive)

| Item | Result |
|---|---|
| All admin actions gated on `current_user_can( 'manage_options' )` | ✓ Verified in plugin v1.14.0 audit; theme has fewer admin surfaces (only the WP-update integration) and they all check. |
| Nonce verification on every mutating handler | ✓ The theme has no mutating handlers (read-only — patterns, templates, assets, OG fonts filter). |
| Escaping at output (`esc_html`, `esc_url`, `esc_attr`, `wp_kses_post`) | ✓ Sampled across `inc/page-notes-render.php` — escaping discipline is consistent. |
| Direct file access guard | ✓ Every `inc/*.php` file has `if ( ! defined( 'ABSPATH' ) ) exit;`. |

No issues.

## Summary recommendation table

| Action | Priority | Bumps version? |
|---|---|---|
| Drop `@version 8.4.0` from `functions.php` docblock (or update to 8.5.3) | 🟡 Minor — drift cosmetic | No (comment-only) |
| Consider `useRootPaddingAwareAlignments: true` in `theme.json` | 🟢 Optional behavior change | Yes if adopted (PATCH or MINOR depending on visible impact) |
| Bump `theme.json` `$schema` to `wp/7.0/theme.json` after 2026-05-20 | 🟢 Hygiene | No (schema URL is tooling-only) |
| Full WCAG audit | 🟢 Separate session | Possible MINOR if changes land |
| Critical CSS size review | 🟢 Separate session (perf-focused) | Possible PATCH if reductions ship |

**No urgent or blocking issues. Theme is handbook-conformant.**

## What this audit deliberately did NOT verify

- **Block markup correctness in template files** — covered by the `gutenberg-block-authoring` skill on-demand
- **Block-pattern usability** — that's a UX review, not a conformance review
- **theme.json color contrast ratios against WCAG** — separate accessibility audit
- **Performance** (page weight, Core Web Vitals, render-blocking analysis) — separate perf audit
- **SEO emission** at the theme level — the theme is presentation; SEO emission lives in the companion plugin's `inc/seo.php` + `inc/seo-schema.php`
- **Cross-browser compatibility** — out of scope for a handbook conformance pass

## How this audit compares to the plugin v1.14.0 admin redesign

| Dimension | Plugin admin (pre-v1.14.0) | Theme (today) |
|---|---|---|
| Inline styles in PHP | ~25 instances across 6 files | 0 instances |
| Stale code/concepts | "Self-updater + SN_GITHUB_TOKEN" row (dead since v8.3.0) | Stale `@version 8.4.0` docblock (cosmetic only) |
| Pattern consistency | 3 different table styles + invented `.sn-subsection-h` | Single consistent CSS cascade + no invented classes |
| Hook patterns | Mixed (Dashboard inline render vs module-owned tabs) | All `inc/` files use canonical hook registration |
| handbook conformance | Frequent deviations | Conformant throughout |

**Why the difference:** The theme has been through more refactoring passes (Phases 1–5 + the v8.3.0 deletion of 1,400 LOC of legacy updater/self-heal). The plugin admin grew faster (v1.0 → v1.14 in two days) without the same compression discipline. Plugin v1.14.0's full redesign brought it up to the theme's standard.

## Conclusion

**The theme is the reference standard for the project's code discipline.** When future work touches any plugin or new code area, this theme is the right pattern to model after.

**No theme ship from this audit.** The `functions.php` docblock refresh is a comment-only change that ships on the next theme release (whenever that happens to be triggered by real code changes).
