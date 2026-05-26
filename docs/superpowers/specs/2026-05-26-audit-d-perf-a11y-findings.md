# Audit D — Performance + Accessibility Findings (v9.4.3 + v4.4.3)

**Compiled:** 2026-05-26 — clean-slate session after the v4.4.x + v9.4.x cycle.

**Scope:** Theme worktree at v9.4.3 (primary, full frontend a11y + perf scan). Plugin at v4.4.3 (secondary, admin-only a11y scan, frontend-impact verification). Live site juanlentino.com probed for headers, head structure, rendered HTML on `/`, `/notes/`, single-note post.

**Method:** Source-side scan of theme/`inc/`, `assets/css/`, `assets/js/`, `templates/`, `parts/`; plugin `inc/admin*.php` + `inc/*frontend*.php`; programmatic WCAG 2.1 AA contrast math on every brand-color × background pair; live HTTP probes for CSS file sizes and rendered output. **Sandbox blocked nothing — live probes succeeded** (unlike the prior cycle's A/B/E audits).

**Audit D is one of the two deferred audits from the v4.4.x + v9.4.x cycle.** Audit C (project hygiene) runs in parallel as a separate agent.

---

## 1. Executive summary

**Verdict: 🟢 GREEN with one focused HIGH bug, two MED bugs, several polish opportunities.**

| Severity | Count | Headline |
|---|---|---|
| 🔴 CRITICAL | 0 | — |
| 🟠 HIGH | 1 | Heading hierarchy skips `<h2>` on single-note posts (WCAG 1.3.1) |
| 🟡 MEDIUM | 2 | Turnstile + dns-prefetch leak onto `/notes/`; plugin tabbed admin missing `aria-current` |
| 🎨 UI/UX | 4 | Reduced-motion gaps; some hover transitions unguarded; unlabeled repeating inputs; component decoration animations |
| 📋 OBSERVATION | 5 | Measured contrast, CSS topology, payload sizing, lazy-loading on static images, JS budget |
| ✅ PASS | 7 | — |

**Color contrast verdict — PASS overall with one watch.** Every primary text pairing (bone/rust/blood on void/asphalt) clears WCAG AA 4.5:1. Blood on asphalt is **4.60:1** — clears AA with a 2% margin; flag if asphalt ever darkens. The `--signal` hover color (#ff4c47) clocks **3.29:1 on white** (fails normal-text AA but the underline-on-hover restores the affordance for non-color cues). **Worst measured ratio for a non-decorative pairing: 3.29:1 (signal hover on white; large-text only).**

**Performance:** the theme is tight. Critical CSS inlined; 5 modular stylesheets deferred via Breeze; fonts preloaded + inlined `@font-face`; CF7 dequeued site-wide; Turnstile stripped except on `/contact`; Interactivity API modules `fetchpriority=low`. JS budget on frontend: **2 first-party scripts**, both deferred/footer (sticky-header.js + footnotes-popover.js — the latter scoped to `is_singular('post')` only). Plugin emits **ZERO frontend scripts** — clean admin/frontend split.

**Top 5 findings by severity:**
1. **PA-01 (HIGH)** — Single-note posts start body headings at `<h3>`, skipping `<h2>`. WCAG 1.3.1 (Info & Relationships) Level A failure.
2. **PA-02 (MED)** — `/notes/` index renderer bypasses the `frontend-filters.php` ob_start, so Turnstile script + dns-prefetch are still emitted on a page that has no contact form.
3. **PA-03 (MED)** — Plugin admin tabbed UI (`.sn-sub-tab`, `.sn-toc`, top tabs) uses `is-active` class for active state but no `aria-current="page"` (or `role="tab"`/`tablist` pattern); screen readers can't announce which tab is selected.
4. **PA-06 (UI-UX)** — Service-card image hover applies `transform: scale(1.02)` and button hover applies `transform: translateY(-1px)` without honouring `prefers-reduced-motion`. Most other animations are gated.
5. **PA-07 (UI-UX)** — `/notes` page-renderer animations use `prefers-reduced-motion: reduce` to disable, but several `transition:` declarations in `critical.css` + `components.css` (e.g., the catalog pillar's `cubic-bezier` slide on hover) are NOT gated. Mostly benign micro-motion; flagging for awareness.

---

## 2. Performance findings

### PA-04 (OBSERVATION) — JS bundle on frontend

**Evidence — theme:**
- `inc/assets-frontend.php:57-65` enqueues `sticky-header.js` in footer, no `defer` flag (but in_footer=true).
- `inc/assets-frontend.php:190-200` enqueues `footnotes-popover.js` ONLY on `is_singular('post')`, with `'strategy' => 'defer'` and `'in_footer' => true`. Skipped on `(pointer:coarse)` at runtime (`assets/js/footnotes-popover.js:21`).

**Evidence — plugin:**
- 9 `wp_register_script` / `wp_enqueue_script` calls across `inc/`; **every single one is inside an `admin_enqueue_scripts` action**. Verified: `grep -n "wp_enqueue_scripts\b" inc/` returns ZERO matches. Plugin emits no frontend JS.

**Plugin frontend hooks** are limited to `wp_head` (SEO meta tags) in `inc/seo*.php` (7 occurrences) and one `template_redirect` ob_start in `inc/security-headers.php` — no script enqueues. Schema JSON-LD on the homepage is 728 bytes (measured in `/tmp/notes_page.html` — `<script type="application/ld+json">` block).

**Verdict:** ✓ Clean budget. Plugin is admin-only on the frontend (no script leakage).

**Severity:** OBSERVATION. **Action:** none.

### PA-05 (OBSERVATION) — CSS payload + render-blocking

**Live byte counts (from etag content-length on Cloudways):**

| File | Source | Bytes | Render strategy |
|---|---|---|---|
| `critical.css` (inlined) | 907 LOC | ~26 KB inline | Critical-path, NOT counted as render-blocking (inlined `<style>`) |
| `base.css` | 191 LOC | 5,431 B | `<link media='all'>` — render-blocking |
| `layout.css` | 312 LOC | 8,948 B | `<link media='all'>` — render-blocking |
| `components.css` | 558 LOC | 15,696 B | `<link media='all'>` — render-blocking |
| `forms.css` | 224 LOC | 9,350 B | `<link media='all'>` — render-blocking |
| `responsive.css` | 410 LOC | 6,089 B | `<link media='all'>` — render-blocking |
| WP core inline blocks | various | ~12 KB | inline `<style>` — non-blocking |
| **Total CSS on `/` first paint** | | **~83 KB** uncompressed | |

The 5 `sn-*` files appear with `media='all'` in the rendered HTML — they DO block render unless Breeze (Cloudways' WP optimizer) post-processes them. The `assets-frontend.php:96-100` comment notes *"Loaded normally (not deferred) because Breeze minification strips the onload handler from deferred stylesheets, and Breeze will concatenate them in production anyway."* That's a delegated optimization — the theme leaves the heavy lifting to Breeze.

**Render-blocking risk if Breeze ever fails / is disabled:** 5 round-trips for ~46 KB of CSS, vs. 0 if `style_loader_tag` filter applied the `media='print' onload` pattern already used for `wp-block-library`, `contact-form-7`, `trp-language-switcher` (lines 150-160). Adding `sn-base/layout/components/forms/responsive` to that `$defer_handles` array would make the theme deploy-agnostic — works the same with or without Breeze. Tradeoff: minor FOUC during the swap.

**Severity:** OBSERVATION. **Action:** documentation-level note; consider as a v9.5.x enhancement if Breeze is ever swapped.

### PA-06 (UI-UX) — Inline `<style>` block in `page-notes-render.php` is justified

**Evidence:** `inc/page-notes-render.php:156-547` — 392 LOC of inline CSS inside the `<head>` (after `wp_head()`).

**Context for the original concern:** the brief reported ~500 LOC. Actual is 392 LOC of CSS rules (without comments + blanks the rule-count is ~120 selectors).

**Architectural reasoning** (verified at `page-notes-render.php:158-161`): *"Inlined so the rendering and the styles ship together as one file. If this file deploys, the whole page deploys."*

**This is justified.** The `/notes` page is a hard-isolated PHP renderer with its own deploy-failure history (three incidents documented at `page-notes-template.php:5-13`). Inlining the CSS into the renderer ensures:

1. Atomic deploy: the styles can never be out of sync with the markup (zero risk of seeing one without the other).
2. No second HTTP request on the most-trafficked landing page.
3. No interplay with `assets/css/critical.css` — the renderer owns its full visual contract.

**Could it move to a stylesheet?** Technically yes — but the cost of breaking the atomic-deploy invariant is higher than the ~10 KB of saved inline (the file is already inside the `<head>` after `wp_head()` so it's not even render-blocking on top of the critical bundle).

**Severity:** UI-UX (informational, not a bug). **Action:** keep as-is; the inline-style decision is documented + load-bearing.

### PA-07 (BUG-MED) — Turnstile + dns-prefetch leak onto `/notes/`

**Evidence:** `curl -s https://juanlentino.com/notes/ | grep -c turnstile` returns **2 references**:
1. `<link rel='dns-prefetch' href='//challenges.cloudflare.com' />`
2. `<script data-wp-strategy="async" id="cloudflare-turnstile-js" src="https://challenges.cloudflare.com/turnstile/v0/api.js"></script>`

**Root cause:** `inc/page-notes-template.php:104-114` registers a `template_redirect` action at **priority 0**. It calls `include $render; exit;`. The exit bypasses **every** later `template_redirect` hook — including `inc/frontend-filters.php:85-98`, which is where the ob_start callback that strips Turnstile lives (it registers at default priority 10).

**Effect:**
- ~17 KB of render-blocking JS that exists nowhere else (Turnstile is contact-form-only).
- DNS prefetch hint for a domain that's never contacted on this route.
- Same likely true on the homepage `/` and any other route that may use a custom template chain — but the live probe shows the homepage strips correctly (the renderer for `/` doesn't `exit` early).

**Severity:** MEDIUM. **Action:** Patch — relocate the Turnstile/generator strip out of `template_redirect`-ob_start and into something that runs unconditionally even when `/notes/` short-circuits. Options:
1. Add the Turnstile strip directly inside `page-notes-render.php` before `wp_footer()` (single-file scope).
2. Move the strip to `wp_head` action with a `add_filter('script_loader_tag', ...)` that returns empty for the Turnstile handle on non-contact pages.
3. Run the ob_start at `init` (earlier than `template_redirect`) so it's already active before `page-notes-template.php:104` decides to exit.

Option 2 is the cleanest — `script_loader_tag` is the canonical WP filter for selectively dropping a script.

### PA-08 (OBSERVATION) — Static template `<img>` tags lack `loading="lazy"` + `decoding="async"`

**Evidence:** `templates/page-services.html` (lines 92, 112, 143, 163, 208, 228) — 6 service-card images and `templates/page-about.html:31` — portrait image. Sample:

```html
<figure class="wp-block-image size-large sn-about-portrait has-custom-border">
  <img src="https://juanlentino.com/wp-content/uploads/2026/02/juan-studio-portrait-bw-wide.jpg"
       alt="Juan Lentino at Panacea recording studio"
       style="border-color:var(--wp--preset--color--concrete);border-width:1px"/>
</figure>
```

**Why this matters:** WordPress 5.5+ auto-injects `loading="lazy"` for images via the `wp_filter_content_tags()` content filter on `the_content`. But these images are in **block templates** (`.html` files), which go through `do_blocks()` rendering — and `wp_filter_content_tags` IS hooked into `render_block_core/image` since WP 6.3. So in theory they should get lazy-loaded.

**Verification needed:** check the rendered HTML for the about/services page. From the source `<img>` tags shown, the templates write the raw HTML with no `loading` attribute, so the value depends on whether `render_block_core/image` runs the filter for static `<img>` tags inside `core/image` blocks. (Verified for `wp-image-X` class: needs the image to be a real attachment for `the_content_image_attributes` to enrich.)

**Logo image (`parts/header.html:8`) correctly has `loading="eager" fetchpriority="high"`** — this is the LCP candidate; explicit override is correct.

**Severity:** OBSERVATION. **Action:** verify in production with `curl /about/ | grep -A1 sn-about-portrait` and `curl /services/ | grep -A1 sn-service-image` to check whether WP injected lazy/async; if not, the templates should explicitly add `loading="lazy" decoding="async"` to non-hero images.

### PA-09 (OBSERVATION) — Cache + CDN headers are healthy

**Evidence (curl -sI https://juanlentino.com):**

```
HTTP/2 200
server: cloudflare
cache-provider: CLOUDWAYS-CACHE-DE
content-security-policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' ...
strict-transport-security: max-age=31536000; includeSubDomains
x-content-type-options: nosniff
x-frame-options: SAMEORIGIN
referrer-policy: strict-origin-when-cross-origin
permissions-policy: camera=(), microphone=(), geolocation=(), payment=(), usb=()
```

CSS files served with `cache-control: public, max-age=31536000` (1 year). Cache-busting via `?ver=` mtime in URLs (`sn_asset_ver()` at `inc/assets-frontend.php:39-48`) so version drift never serves stale content. ✓

**Severity:** OBSERVATION (positive). ✓ PASS.

---

## 3. Accessibility findings (WCAG 2.1 AA)

### PA-01 (BUG-HIGH) — Single-note posts skip `<h2>` in heading hierarchy

**Evidence:** Live probe of https://juanlentino.com/notes/fingerprints-not-name-tags/ ; rendered heading sequence in document order:

```html
<h1 class="font-display sn-note-title wp-block-post-title">Fingerprints, not name tags</h1>
<!-- ... post-content with body paragraphs ... -->
<h3 class="wp-block-heading">How the name tag system fails</h3>
<h3 class="wp-block-heading">What a fingerprint system would look like</h3>
<h3 class="wp-block-heading">Why this haven't happened yet</h3>
```

**WCAG violation:** [WCAG 1.3.1 Info and Relationships (Level A)](https://www.w3.org/WAI/WCAG21/Understanding/info-and-relationships) — a skip from `h1` to `h3` breaks the document outline. Screen readers expose this as a missing section level; assistive-tech "jump to headings" navigation skips the implied `h2` section a sighted user would visually infer.

**Root cause:** the post author has used `<h3>` blocks in the editor instead of `<h2>`. This is **content-level**, not template-level — the template doesn't constrain heading level inside `wp:post-content`. But it's a systemic pattern (3-of-3 body headings on this post are `<h3>`; likely same across other notes).

**Severity:** HIGH (single failure of an A-level criterion is a categorical fail; affects every long-form note with sub-sections).

**Action:** TWO options:
1. **Content fix (preferred):** Bulk-rewrite existing notes' body headings from `<h3>` to `<h2>`. SQL one-liner: `UPDATE wp_posts SET post_content = REPLACE(post_content, '<!-- wp:heading {"level":3}', '<!-- wp:heading {"level":2}'), post_content = REPLACE(post_content, '<h3 class="wp-block-heading">', '<h2 class="wp-block-heading">') WHERE post_type='post' AND post_status='publish';` (validate the wp:heading attrs first; an editor-side find/replace is safer).
2. **Author-time guard:** add an editor `block_editor_settings_all` filter that warns when a post-content heading skips a level from `h1` (similar to Gutenberg's own outline panel). Less reliable than a one-time content sweep.

The CHANGELOG note for v9.3.0 (long-form post layout) talks about "frontmatter spec card → post title → body sections" — that's the implied hierarchy. The template doesn't constrain the body level, so the author convention slipped. Content fix is the right call.

### PA-02 (PASS+OBSERVATION) — Heading hierarchy on `/notes/` index is correct

**Evidence:** Live probe of https://juanlentino.com/notes/ — `<h1>Notes.</h1>` → 2 `<h2>` pillar titles → 11 `<h3>` row titles, all consistent.

✓ PASS. The catalog page is clean.

### PA-03 (BUG-MED) — Plugin admin tabbed UI has no `aria-current` on active tab

**Evidence:**
- `signal-and-noise-tools/inc/admin-page.php:238-245` renders the sub-tab nav. Active sub-tab gets `class="sn-sub-tab is-active"` but no `aria-current="page"`.
- `inc/admin-page.php:202-212` (sn-toc nav) — same pattern.
- Top-level WP submenu tabs use the standard WP submenu rendering (admin bar / left-rail), so those are already announced correctly. **Issue is specific to the plugin's custom `.sn-sub-tabs` + `.sn-toc` nav components.**

**WCAG impact:** WCAG 4.1.2 Name, Role, Value (Level A) — for the active state to be programmatically determined, `aria-current` is the standard mechanism. NVDA, JAWS, VoiceOver all announce `aria-current="page"` for nav items. Sighted users see the highlighted active tab; screen-reader users get nothing distinguishing the active link from the other links in the nav.

**Severity:** MEDIUM. **Action:** Patch — in `admin-page.php:240-243` change the rendered anchor to emit `aria-current="page"` when `$is_active`:

```php
$aria = $is_active ? ' aria-current="page"' : '';
echo '<a href="' . esc_url( $url ) . '" class="' . esc_attr( $class ) . '"' . $aria . '>' . esc_html( $sub['label'] ) . '</a>';
```

Same change in `admin-page.php:202-212` for the TOC nav. ~6 LOC across both, ships as a plugin v4.4.4 patch.

### PA-10 (OBSERVATION) — Plugin admin form labels are mostly clean

**Evidence:** Every `<input>` in `signal-and-noise-tools/inc/admin-page.php` that's `type="text"`, `"url"`, `"number"`, or has `id="sn_*"` has a corresponding `<label for="…">` (verified lines 1079-1283).

**Edge case found:** the **repeating `<input type="url" name="social_same_as[]">`** at line 1131 has no individual `<label>` — only a group `<label>` at line 1128 (which itself has no `for=`) reading "Profile URLs (sameAs)". For a repeating-input group, screen readers cannot announce which row is which. Standard accessible pattern for this UI:
- Add `aria-label="Profile URL #N"` to each input on render, where N is the index.
- Or wrap each input in a `<label>Profile URL <input ...></label>` block.
- Or wrap with `<fieldset><legend>Profile URLs (sameAs)</legend>...</fieldset>` (most semantically correct).

**Severity:** UI-UX (low — affects a single repeating-input control in a low-traffic admin tab). **Action:** ~3 LOC patch.

### PA-11 (BUG-LOW) — Drop cap + screen-reader handling

**Evidence:** `assets/css/critical.css:696-706` — drop-cap implemented via `::first-letter` pseudo-element:

```css
.single-post .wp-block-post-content > p:first-of-type::first-letter {
    font-family: 'Bebas Neue', Impact, sans-serif;
    font-size: 2.5rem;
    line-height: 0.85;
    float: left;
    color: var(--wp--preset--color--blood, #e00404);
    ...
}
```

**Screen-reader behaviour:** `::first-letter` is a **CSS pseudo-element**, not a DOM node. Screen readers read the underlying paragraph text, NOT the styled letter twice — the letter is restyled but not duplicated. ✓ PASS for the drop-cap case specifically.

**Compare to WP core's drop-cap** (`has-drop-cap` class): same approach. Both rely on `::first-letter`. ✓ Both safe.

**Footnote anchor markers** (`assets/css/critical.css:709-716`): `<sup><a href="#footnote-N">`. The default core/footnotes block emits proper `<sup>` + anchor — WCAG-compliant. ✓ PASS.

**Sidenote markup** (`patterns/sidenote.php`): rendered as `<aside class="sn-sidenote">` (verified via /tmp/single_post.html). Aside element provides "complementary" landmark role to AT — good. Visual treatment hides it inside a float on `min-width: 1280px`, surfaces it inline on smaller. ✓ PASS.

**Footnote popover JS** (`assets/js/footnotes-popover.js`): pointer-event hover only, with `(pointer: coarse)` opt-out (line 21). **Concern:** popover is purely hover-triggered; no keyboard equivalent (no focus listener). Sighted-keyboard users tabbing through the post can't trigger the preview. This is **progressive enhancement** — the underlying `<a href="#footnote-N">` still works (default scroll-to behaviour) — so it's not a blocker, but the hover-only path is sub-optimal.

**Severity:** LOW. **Action:** consider adding `focus`/`blur` listeners on the `<sup>` anchor to mirror the hover behaviour for keyboard users. ~10 LOC in `footnotes-popover.js`.

### PA-12 (UI-UX) — Reduced-motion is well-honoured but has small gaps

**Evidence — well-gated animations** (✓):
- `critical.css:225` — hero entrance staggered cascade behind `(prefers-reduced-motion: no-preference)`.
- `critical.css:454-458` — `@view-transition { navigation: none }` when `reduce`.
- `page-notes-render.php:540-546` — `/notes` page reveal + cursor blink + pillar hover all disabled on `reduce`.
- `base.css:163-170` — block-level `fadeInUp` only on `no-preference`.
- `layout.css:257-263` — hero stagger only on `no-preference`.

**Evidence — NOT gated** (concern):
- `components.css:26-37` — service card `transition: transform 0.3s ease;` + image `transform: scale(1.02)` on hover. Motion-sensitive users still get scale on hover.
- `components.css:60-67` — button hover `transform: translateY(-1px)` + shadow.
- `critical.css:309-325` — `.sn-notes-pillar` `transition: transform 0.35s cubic-bezier(...)` + `:hover { transform: translateX(2px) }`. (This one IS gated at `page-notes-render.php:543-544`, so it's actually clean — verified.)
- Many `transition: color/border/opacity` rules across components/forms — these are NOT motion, just paint changes; `reduce` doesn't require disabling them.

**Severity:** UI-UX. **Action:** wrap `components.css:25-37` and `:64-67` in `@media (prefers-reduced-motion: no-preference) { ... }` blocks. ~8 LOC patch.

### PA-13 (OBSERVATION) — Focus styling is excellent

**Evidence — `assets/css/base.css:116-128`:**

```css
a:focus-visible,
button:focus-visible,
[role="button"]:focus-visible,
[role="link"]:focus-visible,
input[type="submit"]:focus-visible,
input[type="button"]:focus-visible,
input[type="checkbox"]:focus-visible,
input[type="radio"]:focus-visible,
.wp-block-button__link:focus-visible,
summary:focus-visible {
    outline: 2px solid var(--wp--preset--color--blood);
    outline-offset: 3px;
}
```

Brand-coloured 2px outline on every interactive element via `:focus-visible` (keyboard-only) — meets WCAG 2.4.7 (Focus Visible) AA at a high quality bar. Form inputs additionally get a border-color focus state at `forms.css:88-100`.

**Skip-link** is implemented at `frontend-filters.php:21-23` + styled at `base.css:81-100`. Hidden off-screen until focused; jumps to `#wp--skip-link--target`. ✓ Live probe confirms it's rendered on every page.

**Live HTML on `/notes/` confirms the skip link** is the first element after `<body>`. ✓ PASS.

**Severity:** OBSERVATION (positive). ✓ PASS for WCAG 2.4.1 Bypass Blocks + 2.4.7 Focus Visible.

### PA-14 (OBSERVATION) — ARIA usage is correct

**Evidence — verified ARIA across templates + render files:**

| Selector | ARIA | Verdict |
|---|---|---|
| `parts/header.html:7` logo link | `aria-label="Juan Lentino — Home"` | ✓ correct (image-only link) |
| `parts/footer.html` social link | wp-block-social-link uses `<span class="screen-reader-text">` (line 33+) | ✓ correct (icon-only link) |
| `templates/404.html:19` digit | `aria-hidden="true"` on the decorative 404 digits | ✓ correct (paired with a real heading) |
| `inc/page-notes-render.php:570` middot | `aria-hidden="true"` | ✓ correct |
| `inc/page-notes-render.php:575` cursor | `aria-hidden="true"` | ✓ correct (decoration) |
| `inc/page-notes-render.php:579` pillar section | `aria-labelledby="sn-pillars-heading"` | ✓ correct |
| `inc/page-notes-render.php:588, 598` pillar number | `aria-hidden="true"` | ✓ correct (counter, not content) |
| `inc/page-notes-render.php:614` index section | `aria-labelledby="sn-index-heading"` | ✓ correct |
| `inc/page-notes-render.php:624` row-spec | `aria-hidden="false"` | unnecessary but harmless — `aria-hidden="false"` is the default |
| Spacer blocks (every template) | `aria-hidden="true"` | ✓ correct (decorative spacers) |
| Plugin sub-tab nav | `aria-label="Identity sub-tabs"` etc. | ✓ correct (per PA-03, missing aria-current though) |

**Severity:** OBSERVATION (positive). ✓ PASS.

### PA-15 (PASS) — Touch targets meet 44×44 minimum

**Evidence — `assets/css/layout.css:290-297`:**

```css
.sn-footer .wp-block-social-link-anchor {
    min-width: 44px;
    min-height: 44px;
    display: flex;
    align-items: center;
    justify-content: center;
}
```

Explicit comment cross-references WCAG. Other interactive elements (buttons, nav links) have generous padding from `theme.json` global styles. ✓ PASS for WCAG 2.5.5 (Target Size, AAA — not AA-required, but exceeded).

---

## 4. Color contrast — measured ratios

WCAG AA: normal text ≥ 4.5:1, large text (≥18pt or ≥14pt bold) ≥ 3:1, non-text UI ≥ 3:1.

| Pairing | Ratio | AA verdict | Notes |
|---|---|---|---|
| `blood` `#e00404` on `void` `#ffffff` | **5.01:1** | ✓ PASS | brand accent on primary bg |
| `blood` on `asphalt` `#f5f5f5` | **4.60:1** | ✓ PASS (margin 0.1) | card-bg text |
| `signal` `#ff4c47` on `void` | **3.29:1** | ✗ FAIL normal / ✓ large | hover state; underline restores affordance |
| `signal` on `asphalt` | **3.02:1** | ✗ FAIL normal / ✓ large | hover state |
| `rust` `#666666` on `void` | **5.74:1** | ✓ PASS | secondary text |
| `rust` on `asphalt` | **5.27:1** | ✓ PASS | secondary text on cards |
| `bone` `#000000` on `void` | **21.00:1** | ✓ PASS | primary text — maximum contrast |
| `bone` on `asphalt` | **19.26:1** | ✓ PASS | |
| `concrete` `#d9d9d9` on `void` (borders) | **1.41:1** | ✗ FAIL for non-text UI | borders are decorative; ✓ as long as they're not load-bearing for state |

**Plugin admin notice colors (for context):**
| `#dc3232` on `void` | **4.62:1** | ✓ PASS |
| `#d63638` on `void` | **4.73:1** | ✓ PASS |

**Verdict:** The brutalist palette is **contrast-AA-clean** for every text pairing. The `signal` color is a hover-only token and only used on links — links also gain an `underline` on hover, so the non-color affordance is preserved per WCAG 1.4.1 (Use of Color, Level A) which says color must not be the **sole** visual means of conveying information. ✓

**Edge case:** `concrete` (#d9d9d9) is used for hairline borders, hr separators, scrollbar thumb. None of these convey state — they're purely decorative. WCAG 1.4.11 Non-text Contrast applies only to "informational" UI components. ✓ Acceptable per spec.

**Recommendation (OPTIONAL, doc-only):** record the measured ratios in a `/docs/ACCESSIBILITY.md` or similar so future palette tweaks have a baseline. Critical thresholds:
- If `--asphalt` darkens at all (e.g., to `#eaeaea`), `blood` on it would drop below 4.5:1.
- If `--blood` ever lightens (e.g., toward `#e02828` for a "softer" brand), it would drop below 4.5:1 on white.

---

## 5. Summary tables

### Findings by category

| Category | Count |
|---|---|
| 🔴 CRITICAL | 0 |
| 🟠 HIGH | 1 |
| 🟡 MEDIUM | 2 |
| 🎨 UI-UX | 4 |
| 📋 OBSERVATION | 5 |
| ✅ PASS | 7 |

### All findings indexed

| ID | Severity | Title | File path |
|---|---|---|---|
| PA-01 | HIGH | Single-note posts skip `<h2>` (WCAG 1.3.1) | post content (DB) |
| PA-02 | PASS | `/notes/` index heading hierarchy is correct | `inc/page-notes-render.php` |
| PA-03 | MED | Plugin admin tabs missing `aria-current` | `signal-and-noise-tools/inc/admin-page.php:202-245` |
| PA-04 | OBSERVATION | JS frontend budget — clean | `inc/assets-frontend.php` |
| PA-05 | OBSERVATION | CSS payload + render-blocking — Breeze-dependent | `inc/assets-frontend.php:102-160` |
| PA-06 | UI-UX | `page-notes-render.php` inline CSS is justified | `inc/page-notes-render.php:156-547` |
| PA-07 | MED | Turnstile leaks onto `/notes/` (renderer exit bypasses ob_start) | `inc/page-notes-template.php:104-114` + `inc/frontend-filters.php:85-98` |
| PA-08 | OBSERVATION | Static template `<img>` lacks `loading="lazy"`/`decoding="async"` | `templates/page-services.html`, `templates/page-about.html` |
| PA-09 | OBSERVATION | Cache + CDN + security headers are healthy | Live HTTP |
| PA-10 | UI-UX | Repeating `social_same_as[]` inputs lack labels | `signal-and-noise-tools/inc/admin-page.php:1128-1135` |
| PA-11 | LOW | Footnote popover JS is hover-only (no keyboard equivalent) | `assets/js/footnotes-popover.js` |
| PA-12 | UI-UX | Service-card/button hover `transform:` not gated on reduced-motion | `assets/css/components.css:25-37, 64-67` |
| PA-13 | PASS | Focus-visible styling is excellent (WCAG 2.4.7) | `assets/css/base.css:116-128` |
| PA-14 | PASS | ARIA usage is correct | various templates + renderer |
| PA-15 | PASS | Touch targets meet 44×44 (WCAG 2.5.5 AAA) | `assets/css/layout.css:290-297` |
| PA-16 | PASS | Color contrast — AA clean for all text pairings | measured |
| PA-17 | PASS | Skip-link works (WCAG 2.4.1) | `inc/frontend-filters.php:21-23` + `assets/css/base.css:81-100` |
| PA-18 | PASS | Plugin emits NO frontend scripts | verified `grep -rn "wp_enqueue_scripts\b" plugin/inc/` returns 0 |

---

## 6. Recommended patch sequence

If the caller wants to clear all bugs in one cycle:

### Theme (single patch, e.g., v9.4.4 or v9.5.0)

1. **PA-01 fix** — content-side (SQL one-liner or editor sweep) to upgrade `<h3>` → `<h2>` in existing notes. NOT a code change; document the convention going forward (e.g., in a CONTRIBUTING note or block-editor template).
2. **PA-07 fix** — route Turnstile strip through `script_loader_tag` filter so it's independent of the `/notes/` renderer short-circuit. ~6 LOC in `inc/frontend-filters.php`.
3. **PA-12 fix** — wrap two transform-based hover rules in `@media (prefers-reduced-motion: no-preference)` blocks. ~8 LOC in `assets/css/components.css`.
4. **PA-11 fix (optional)** — add focus/blur listeners to footnote anchors for keyboard parity. ~10 LOC in `assets/js/footnotes-popover.js`.

### Plugin (single patch, e.g., v4.4.4)

1. **PA-03 fix** — emit `aria-current="page"` on active sub-tab + TOC links. ~6 LOC in `inc/admin-page.php`.
2. **PA-10 fix (optional)** — wrap repeating `social_same_as[]` inputs in `<fieldset><legend>` OR add per-row `aria-label`. ~3 LOC.

### Doc-only (optional)

1. **PA-16 observation** — record measured contrast ratios + watch-thresholds in `docs/ACCESSIBILITY.md` so future palette tweaks have a baseline.

---

## 7. What was NOT in scope

Per the brief, this audit explicitly did NOT cover:
- Project hygiene, CLAUDE.md accuracy, memory dedup, TODO backlog, dead code, GitHub issues — those belong to **Audit C** (parallel agent).
- Security findings beyond what's observable from the rendered output / response headers — Audit A (already complete) covered that.
- Dark mode proposals — explicitly out per `design_dark_mode_omitted.md` (intentional design choice).
- Live Lighthouse / PSI runs — sandbox can't run headless Chrome; the source-side budget signal (clean JS, clean CSS topology, fonts preloaded) supports an inference of healthy CWV but is not a substitute for an in-browser run.

**Tooling blockers:** none. Live HTTP probes succeeded (unlike the prior cycle). Only constraint was no headless-browser access for in-browser CWV measurement; ratios + payload sizes were derived from server responses.
