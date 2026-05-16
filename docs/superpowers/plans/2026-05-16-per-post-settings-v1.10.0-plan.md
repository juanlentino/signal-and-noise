# Per-Post SEO Settings v1.10.0 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking. **Tasks 4, 5, 6 are independent** and can run in parallel subagents after Tasks 1-3 land.

**Goal:** Add a "Signal & Noise" meta box to the post editor (post + page) exposing 3 SEO overrides per post: noindex toggle, custom meta description, custom OG image URL. Storage via `register_post_meta` with REST exposure for future React-sidebar migration. Three existing reader files honor the new overrides.

**Architecture:** Hybrid PHP meta box + `register_post_meta(show_in_rest=true)` — Approach C from the spec research. New `inc/post-settings.php` owns registration + UI + save. Three existing modules (`inc/seo.php`, `inc/seo-schema.php`, `inc/og-card-generator.php`) each gain a pre-fallback check for the relevant new meta key.

**Tech Stack:** WordPress (PHP), `register_post_meta` for REST exposure, native HTML form (meta box auto-converts to block editor sidebar panel), zero build pipeline.

**Spec:** [docs/superpowers/specs/2026-05-16-per-post-settings-v1.10.0-design.md](../specs/2026-05-16-per-post-settings-v1.10.0-design.md) (commit `17d3f34`)

**Working directory:** `/Users/juanlentino/Projects/signal-and-noise-tools` (branch `main`; auto-deploys on tag push)

---

## File Structure

| File | Status | Responsibility | LOC |
|---|---|---|---|
| `inc/post-settings.php` | NEW | register_post_meta + add_meta_box + render + save + typed accessors | ~170 |
| `signal-and-noise-tools.php` | MODIFY | `require_once inc/post-settings.php` + version bump | +3/-2 |
| `assets/admin.css` | MODIFY | Width overrides for `.sn-field` inside narrow side meta box | +25 |
| `inc/seo.php` | MODIFY | `sn_seo_meta_for_current_view()` checks `_sn_meta_description` first | +5/-2 |
| `inc/seo-schema.php` | MODIFY | `sn_schema_article()` description gains same fallback | +4/-1 |
| `inc/og-card-generator.php` | MODIFY | Per-post `_sn_og_image_url` takes priority in resolution | +12/-2 |
| `CHANGELOG.md` | MODIFY | Prepend v1.10.0 entry | +30 |

Total: ~250 LOC. **8 atomic commits + tag.**

---

## Task 1: Create `inc/post-settings.php`

**Files:**
- Create: `/Users/juanlentino/Projects/signal-and-noise-tools/inc/post-settings.php`

- [ ] **Step 1: Write the full module file**

Create with this content:

```php
<?php
/**
 * Signal & Noise Tools — Per-post SEO settings.
 *
 * Three meta keys, written via the meta box on post + page edit screens:
 *   _sn_noindex            — robots noindex toggle (reader: inc/seo.php
 *                            since v1.6.0; write path added here)
 *   _sn_meta_description   — custom <meta name="description"> override
 *   _sn_og_image_url       — custom OG image URL override (highest priority)
 *
 * Architecture: classic add_meta_box() auto-converts to a block editor
 * sidebar panel via WP's legacy-meta-box bridge. Plus register_post_meta()
 * with show_in_rest=true future-proofs storage for a React sidebar later
 * (no migration). Same pattern Yoast uses at scale.
 *
 * Added in v1.10.0 (2026-05-16).
 *
 * @package SignalNoiseTools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const SN_POST_SETTINGS_NONCE       = 'sn_post_settings_save';
const SN_POST_SETTINGS_POST_TYPES  = array( 'post', 'page' );

/**
 * Register the 3 post meta keys with REST exposure.
 *
 * register_post_meta is per-post-type — loop over our supported types.
 * auth_callback enforces edit_posts for REST writes (without it, non-
 * admin users could bypass the save_post cap check via REST).
 */
function sn_post_settings_register_meta() {
	$auth_cb = function () {
		return current_user_can( 'edit_posts' );
	};

	$bool_args = array(
		'show_in_rest'      => true,
		'single'            => true,
		'type'              => 'boolean',
		'default'           => false,
		'auth_callback'     => $auth_cb,
		'sanitize_callback' => 'rest_sanitize_boolean',
	);

	$text_args = array(
		'show_in_rest'      => true,
		'single'            => true,
		'type'              => 'string',
		'default'           => '',
		'auth_callback'     => $auth_cb,
		'sanitize_callback' => 'sanitize_textarea_field',
	);

	$url_args = array(
		'show_in_rest'      => true,
		'single'            => true,
		'type'              => 'string',
		'default'           => '',
		'auth_callback'     => $auth_cb,
		'sanitize_callback' => 'esc_url_raw',
	);

	foreach ( SN_POST_SETTINGS_POST_TYPES as $post_type ) {
		register_post_meta( $post_type, '_sn_noindex',          $bool_args );
		register_post_meta( $post_type, '_sn_meta_description', $text_args );
		register_post_meta( $post_type, '_sn_og_image_url',     $url_args );
	}
}
add_action( 'init', 'sn_post_settings_register_meta' );

/**
 * Register the meta box on post + page edit screens.
 *
 * context='side' = right sidebar position (auto-converts to a block
 * editor sidebar panel). priority='high' = near the top so it's
 * discoverable.
 */
function sn_post_settings_register_meta_box() {
	foreach ( SN_POST_SETTINGS_POST_TYPES as $post_type ) {
		add_meta_box(
			'sn_post_settings',
			'Signal & Noise',
			'sn_post_settings_render',
			$post_type,
			'side',
			'high'
		);
	}
}
add_action( 'add_meta_boxes', 'sn_post_settings_register_meta_box' );

/**
 * Render the meta box. Uses .sn-fieldset / .sn-field design system
 * classes. The side meta box is narrower (~280px) than admin pages
 * (~820px); width overrides live in assets/admin.css.
 *
 * @param WP_Post $post Current post being edited.
 */
function sn_post_settings_render( $post ) {
	wp_nonce_field( SN_POST_SETTINGS_NONCE, 'sn_post_settings_nonce' );

	$noindex = (bool) get_post_meta( $post->ID, '_sn_noindex', true );
	$desc    = (string) get_post_meta( $post->ID, '_sn_meta_description', true );
	$og      = (string) get_post_meta( $post->ID, '_sn_og_image_url', true );

	echo '<div class="sn-post-settings">';

	// noindex toggle
	echo '<div class="sn-field">';
	echo '<label class="sn-field-label sn-field-label--inline">';
	echo '<input type="checkbox" name="sn_noindex" value="1"' . checked( $noindex, true, false ) . '> ';
	echo 'Hide from search engines (noindex)';
	echo '</label>';
	echo '<p class="sn-field-helper">Adds <code>noindex,nofollow</code> to the robots meta tag for this post only.</p>';
	echo '</div>';

	// Meta description
	echo '<div class="sn-field">';
	echo '<label class="sn-field-label" for="sn_meta_description">Meta description</label>';
	echo '<textarea id="sn_meta_description" name="sn_meta_description" rows="3">' . esc_textarea( $desc ) . '</textarea>';
	echo '<p class="sn-field-helper">Overrides the post excerpt for <code>&lt;meta name=&quot;description&quot;&gt;</code>, OG description, and JSON-LD. Empty falls back to excerpt.</p>';
	echo '</div>';

	// OG image URL
	echo '<div class="sn-field">';
	echo '<label class="sn-field-label" for="sn_og_image_url">OG image URL</label>';
	echo '<input type="url" id="sn_og_image_url" name="sn_og_image_url" value="' . esc_attr( $og ) . '" placeholder="https://...">';
	echo '<p class="sn-field-helper">Overrides the featured image / auto-generated card for OG and Twitter shares. Empty falls back to default resolution.</p>';
	echo '</div>';

	echo '</div>';
}

/**
 * Save handler. Hooked to save_post — runs on every save (including
 * autosaves + revisions, both guarded out).
 *
 * Empty values trigger delete_post_meta() rather than persisting
 * empty strings — keeps DB clean and means get_post_meta returns
 * the documented '' default for missing keys.
 *
 * @param int $post_id Post being saved.
 */
function sn_post_settings_save( $post_id ) {
	// Nonce absent — happens on REST writes and autosaves where our
	// meta box wasn't part of the form. Silent return.
	if ( ! isset( $_POST['sn_post_settings_nonce'] ) ) {
		return;
	}
	if ( ! wp_verify_nonce( wp_unslash( $_POST['sn_post_settings_nonce'] ), SN_POST_SETTINGS_NONCE ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( wp_is_post_revision( $post_id ) ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	// noindex — checkbox unchecked = absent from $_POST.
	if ( ! empty( $_POST['sn_noindex'] ) ) {
		update_post_meta( $post_id, '_sn_noindex', '1' );
	} else {
		delete_post_meta( $post_id, '_sn_noindex' );
	}

	// Meta description — wp_unslash before sanitize (WP stripslashes
	// hostile input on POST; we want the actual value to sanitize).
	$desc = isset( $_POST['sn_meta_description'] )
		? sanitize_textarea_field( wp_unslash( $_POST['sn_meta_description'] ) )
		: '';
	if ( '' !== $desc ) {
		update_post_meta( $post_id, '_sn_meta_description', $desc );
	} else {
		delete_post_meta( $post_id, '_sn_meta_description' );
	}

	// OG image URL — esc_url_raw strips invalid URLs to ''.
	$og = isset( $_POST['sn_og_image_url'] )
		? esc_url_raw( wp_unslash( $_POST['sn_og_image_url'] ) )
		: '';
	if ( '' !== $og ) {
		update_post_meta( $post_id, '_sn_og_image_url', $og );
	} else {
		delete_post_meta( $post_id, '_sn_og_image_url' );
	}
}
add_action( 'save_post', 'sn_post_settings_save' );

/**
 * Typed accessors — read meta with predictable types. Consumers
 * (seo.php / seo-schema.php / og-card-generator.php) call these
 * instead of get_post_meta directly so the type contract lives
 * in one place.
 */
function sn_post_settings_get_noindex( $post_id ) {
	return '1' === (string) get_post_meta( $post_id, '_sn_noindex', true );
}

function sn_post_settings_get_description( $post_id ) {
	return (string) get_post_meta( $post_id, '_sn_meta_description', true );
}

function sn_post_settings_get_og_image_url( $post_id ) {
	return (string) get_post_meta( $post_id, '_sn_og_image_url', true );
}
```

- [ ] **Step 2: Verify file shape**

```bash
cd /Users/juanlentino/Projects/signal-and-noise-tools && \
wc -l inc/post-settings.php && \
grep -c '^function ' inc/post-settings.php
```

Expected: ~170 LOC, 7 top-level functions (register_meta, register_meta_box, render, save, 3 accessors).

- [ ] **Step 3: Commit**

```bash
cd /Users/juanlentino/Projects/signal-and-noise-tools && \
git add inc/post-settings.php && \
git commit -m "post-settings: new meta box module (register + render + save)

NEW inc/post-settings.php (~170 LOC) — per-post SEO settings UI.

Three meta keys on 'post' + 'page' post types:
- _sn_noindex            (boolean, reader exists since v1.6.0)
- _sn_meta_description   (string, new)
- _sn_og_image_url       (string URL, new)

register_post_meta with show_in_rest=true future-proofs storage
for a React sidebar later (no migration). auth_callback enforces
edit_posts for REST writes.

Save handler on save_post with full guard chain (nonce → autosave
→ revision → cap → sanitize). Empty values trigger delete_post_meta
to keep DB clean.

Typed accessors (sn_post_settings_get_noindex/description/og_image_url)
expose the meta with predictable types for the reader integrations
in seo.php / seo-schema.php / og-card-generator.php (separate commits).

Module is NOT yet required in the bootstrap; next commit wires it in."
```

---

## Task 2: Wire into bootstrap

**Files:**
- Modify: `/Users/juanlentino/Projects/signal-and-noise-tools/signal-and-noise-tools.php`

- [ ] **Step 1: Add `require_once` line**

Find the existing require_once chain in `signal-and-noise-tools.php` (around line 119-126 — the Phase 3+ requires that include `seo-schema.php`, `login-hide.php`, etc.). Add `inc/post-settings.php` to the chain. Locate this block:

```php
require_once __DIR__ . '/inc/content-rendering-helpers.php';
require_once __DIR__ . '/inc/content-surfaces.php';
require_once __DIR__ . '/inc/content-migrations.php';
require_once __DIR__ . '/inc/og-card-generator.php';
require_once __DIR__ . '/inc/reading-time.php';
require_once __DIR__ . '/inc/wp-update-integration.php';
require_once __DIR__ . '/inc/login-hide.php';
require_once __DIR__ . '/inc/seo-schema.php';
```

Append this line immediately after `seo-schema.php`:

```php
require_once __DIR__ . '/inc/post-settings.php';
```

The position matters: `post-settings.php` defines accessor functions (`sn_post_settings_get_*`) that the upcoming `seo.php` / `seo-schema.php` / `og-card-generator.php` modifications will call. Loading post-settings.php BEFORE those consumers ensures the functions are defined when WP hooks fire. (`seo.php` is at line 54, BEFORE `post-settings.php` in this chain — but its callsites fire on `wp_head` priority 1, long after all requires complete, so order within the require chain doesn't matter for runtime.)

- [ ] **Step 2: Verify require chain**

```bash
cd /Users/juanlentino/Projects/signal-and-noise-tools && \
grep -n 'inc/post-settings.php\|inc/seo-schema.php' signal-and-noise-tools.php
```

Expected: 2 matches — `seo-schema.php` followed immediately by `post-settings.php` on the next line.

- [ ] **Step 3: Commit**

```bash
cd /Users/juanlentino/Projects/signal-and-noise-tools && \
git add signal-and-noise-tools.php && \
git commit -m "bootstrap: require inc/post-settings.php

Adds the v1.10.0 per-post settings module to the require chain,
positioned after inc/seo-schema.php (last entry in the Phase 3+
require block).

All consumer modules (seo.php / seo-schema.php / og-card-generator.php
get reader integration in subsequent commits) call the typed
accessors sn_post_settings_get_noindex/description/og_image_url —
require-chain order doesn't strictly matter since callsites fire
on wp_head priority 1 (long after all requires complete), but
loading post-settings.php in the same Phase 3+ block keeps the
content/SEO module group cohesive."
```

---

## Task 3: CSS overrides for the narrow side meta box

**Files:**
- Modify: `/Users/juanlentino/Projects/signal-and-noise-tools/assets/admin.css`

The `.sn-fieldset` / `.sn-field` / `.sn-field-w-*` rules in v1.9.6 assume the admin-page width (~820px max). Inside a side meta box on the post-edit screen, the panel is much narrower (~280px). Width caps need to be relative / removed inside the meta box.

- [ ] **Step 1: Append CSS rules to assets/admin.css**

Open `assets/admin.css` and append this block at the end of the file (after the v1.9.6 reduced-motion media query):

```css

/* ─── Post-edit screen meta box (v1.10.0) ───────────────────── */

/* The meta box renders inside #sn_post_settings .inside provided by
   WP. Fields inside the meta box ignore the absolute width caps
   from .sn-field-w-* because the surrounding panel is narrower
   than the desktop admin page. */
#sn_post_settings .sn-post-settings {
	padding: 0;
}

#sn_post_settings .sn-field {
	margin: 0 0 var(--sn-space-3);
}

#sn_post_settings .sn-field:last-child {
	margin-bottom: 0;
}

#sn_post_settings .sn-field-label {
	font-size: 0.85em;
	margin-bottom: 4px;
}

/* Inline label variant for checkbox + text on one line. */
.sn-field-label--inline {
	display: flex;
	align-items: center;
	gap: 6px;
	font-weight: 500;
	cursor: pointer;
}

#sn_post_settings .sn-field input[type="text"],
#sn_post_settings .sn-field input[type="url"],
#sn_post_settings .sn-field textarea {
	width: 100%;
	max-width: 100%;
	font-size: 0.88em;
}

#sn_post_settings .sn-field textarea {
	min-height: 60px;
	resize: vertical;
}

#sn_post_settings .sn-field-helper {
	font-size: 0.78em;
	margin-top: 4px;
	line-height: 1.4;
}
```

- [ ] **Step 2: Verify**

```bash
cd /Users/juanlentino/Projects/signal-and-noise-tools && \
wc -l assets/admin.css && \
grep -c '#sn_post_settings\|sn-field-label--inline' assets/admin.css
```

Expected: file grew by ~30 LOC. 8 selectors targeting `#sn_post_settings` + 1 for the inline-label variant.

- [ ] **Step 3: Commit**

```bash
cd /Users/juanlentino/Projects/signal-and-noise-tools && \
git add assets/admin.css && \
git commit -m "admin.css: side-meta-box width overrides for #sn_post_settings

The .sn-fieldset / .sn-field / .sn-field-w-* rules from v1.9.6
assume the admin-page width (~820px). Inside a side meta box on
the post-edit screen the panel is ~280px — the absolute caps
make fields look truncated.

Scope CSS to #sn_post_settings (the meta box id from
inc/post-settings.php). Reset margins, drop font sizes a notch
to match WP's native meta box typography, set width:100% on
inputs.

Also adds .sn-field-label--inline modifier — used for the
checkbox + label on one line ('Hide from search engines (noindex)').
Display:flex with align-items:center, font-weight 500, cursor
pointer so the whole label is clickable to toggle the checkbox."
```

---

## Task 4: `inc/seo.php` — meta description fallback

**Independent of Tasks 5 + 6 — dispatchable to a parallel subagent after Tasks 1-3 land.**

**Files:**
- Modify: `/Users/juanlentino/Projects/signal-and-noise-tools/inc/seo.php`

- [ ] **Step 1: Read the singular branch of sn_seo_meta_for_current_view()**

```bash
cd /Users/juanlentino/Projects/signal-and-noise-tools && \
grep -n 'function sn_seo_meta_for_current_view\|is_singular' inc/seo.php | head -5
```

The singular branch is at approximately line 72-79. It currently reads:

```php
} elseif ( is_singular() ) {
	$post  = get_queried_object();
	$title = $post ? wp_strip_all_tags( get_the_title( $post ) ) . ' — ' . sn_setting( 'identity.site_name', get_bloginfo( 'name' ) ) : '';
	if ( $post && ! empty( $post->post_excerpt ) ) {
		$description = wp_strip_all_tags( $post->post_excerpt );
	}
	$url = $post ? get_permalink( $post ) : '';
}
```

- [ ] **Step 2: Replace the description-setting block**

Find that exact block and replace the description-setting lines (the `if ( $post && ! empty( $post->post_excerpt ) )` block) with a fallback chain that prefers `_sn_meta_description`:

```php
} elseif ( is_singular() ) {
	$post  = get_queried_object();
	$title = $post ? wp_strip_all_tags( get_the_title( $post ) ) . ' — ' . sn_setting( 'identity.site_name', get_bloginfo( 'name' ) ) : '';
	if ( $post ) {
		// v1.10.0+: per-post _sn_meta_description override wins over
		// the excerpt. Empty override falls through to excerpt.
		$override = function_exists( 'sn_post_settings_get_description' )
			? sn_post_settings_get_description( $post->ID )
			: '';
		if ( '' !== $override ) {
			$description = $override;
		} elseif ( ! empty( $post->post_excerpt ) ) {
			$description = wp_strip_all_tags( $post->post_excerpt );
		}
	}
	$url = $post ? get_permalink( $post ) : '';
}
```

The `function_exists` guard is defensive — keeps `inc/seo.php` working if `inc/post-settings.php` were ever deactivated or absent. Same pattern used elsewhere in the plugin for cross-module function calls.

- [ ] **Step 3: Verify the change is in place**

```bash
cd /Users/juanlentino/Projects/signal-and-noise-tools && \
grep -n 'sn_post_settings_get_description' inc/seo.php
```

Expected: 1 match — the call inside the singular branch.

- [ ] **Step 4: Commit**

```bash
cd /Users/juanlentino/Projects/signal-and-noise-tools && \
git add inc/seo.php && \
git commit -m "seo: prefer _sn_meta_description override before post_excerpt

In sn_seo_meta_for_current_view(), the singular branch (singular
posts and pages) now reads the v1.10.0 _sn_meta_description meta
key first via sn_post_settings_get_description(). Empty override
falls through to the existing post_excerpt resolution.

function_exists guard on sn_post_settings_get_description keeps
this defensive — if inc/post-settings.php is somehow absent (e.g.
selective deactivation), seo.php continues to work with the v1.9.x
excerpt-only behaviour.

Propagates automatically to <meta name='description'>, og:description,
twitter:description (all use the same \$description returned by
this function). JSON-LD Article schema description is in
inc/seo-schema.php and gets the same fallback in a separate commit
(it reads \$post->post_excerpt directly, independent of this
function — Agent A flagged that distinction)."
```

---

## Task 5: `inc/seo-schema.php` — Article schema description fallback

**Independent of Tasks 4 + 6 — dispatchable to a parallel subagent after Tasks 1-3 land.**

**Files:**
- Modify: `/Users/juanlentino/Projects/signal-and-noise-tools/inc/seo-schema.php`

- [ ] **Step 1: Read the article-description section**

```bash
cd /Users/juanlentino/Projects/signal-and-noise-tools && \
grep -n 'function sn_schema_article\|post_excerpt' inc/seo-schema.php | head -5
```

The Article schema definition is in `sn_schema_article()` (around line 75-100). The description line is around line 91 and reads something like:

```php
'description' => $post->post_excerpt ? wp_strip_all_tags( $post->post_excerpt ) : '',
```

(or similar — may have minor variations).

- [ ] **Step 2: Replace with fallback chain**

Locate the `'description'` key in the `sn_schema_article()` array and replace its value expression. Find the surrounding context first to make the edit unique:

```bash
grep -B 1 -A 1 "'description'" inc/seo-schema.php
```

Then replace the description line in `sn_schema_article()` with this pattern (preserving surrounding array structure):

```php
'description' => sn_schema_article_description( $post ),
```

And add this helper function above `sn_schema_article()` (between the function declarations — find an existing function boundary like the closing `}` of the function above it):

```php
/**
 * Resolve the Article schema description with the v1.10.0 fallback
 * chain — per-post _sn_meta_description override wins over excerpt.
 *
 * Separate helper because seo-schema.php reads $post->post_excerpt
 * directly (independent of sn_seo_meta_for_current_view() in seo.php).
 * Both callsites need the same fallback logic.
 *
 * @param WP_Post $post Post being rendered.
 * @return string Description string (may be empty).
 */
function sn_schema_article_description( $post ) {
	$override = function_exists( 'sn_post_settings_get_description' )
		? sn_post_settings_get_description( $post->ID )
		: '';
	if ( '' !== $override ) {
		return $override;
	}
	if ( ! empty( $post->post_excerpt ) ) {
		return wp_strip_all_tags( $post->post_excerpt );
	}
	return '';
}
```

- [ ] **Step 3: Verify**

```bash
cd /Users/juanlentino/Projects/signal-and-noise-tools && \
grep -n 'sn_schema_article_description\|sn_post_settings_get_description' inc/seo-schema.php
```

Expected: 3 matches — the function declaration, the call from `sn_schema_article()`, and the call to `sn_post_settings_get_description` inside the helper.

- [ ] **Step 4: Commit**

```bash
cd /Users/juanlentino/Projects/signal-and-noise-tools && \
git add inc/seo-schema.php && \
git commit -m "seo-schema: Article description honors _sn_meta_description override

inc/seo-schema.php reads \$post->post_excerpt directly for the
JSON-LD Article schema description field — independent of
inc/seo.php's sn_seo_meta_for_current_view() resolution path.
Agent A flagged this as a separate consumer in the v1.10.0
research.

Adds sn_schema_article_description(\$post) helper with the same
fallback chain seo.php uses (override → excerpt → empty). Article
schema description now matches the HTML <meta name='description'>
behavior — both consult _sn_meta_description first.

function_exists guard on sn_post_settings_get_description keeps
this defensive against post-settings.php absence."
```

---

## Task 6: `inc/og-card-generator.php` — per-post OG image override

**Independent of Tasks 4 + 5 — dispatchable to a parallel subagent after Tasks 1-3 land.**

**Files:**
- Modify: `/Users/juanlentino/Projects/signal-and-noise-tools/inc/og-card-generator.php`

The current resolution chain (per Agent A's mapping):
1. Featured image (`has_post_thumbnail` → `get_the_post_thumbnail_url($post, 'large')`)
2. Generated PNG card at `wp-content/uploads/sn-og/post-{ID}.png`
3. Falls back to `null` → triggers the `og.default_image_url` site setting

v1.10.0 inserts a NEW step 0: explicit `_sn_og_image_url` per-post override beats everything.

- [ ] **Step 1: Locate the filter callback**

```bash
cd /Users/juanlentino/Projects/signal-and-noise-tools && \
grep -n "add_filter.*sn_og_image_url\|function.*sn_og_image\|has_post_thumbnail" inc/og-card-generator.php | head -10
```

The filter callback is at approximately line 360-380. Find the function that's attached to `apply_filters('sn_og_image_url', ...)`. It looks roughly like:

```php
add_filter( 'sn_og_image_url', function( $default_og ) {
	if ( ! is_singular() ) {
		return $default_og;
	}
	$post = get_queried_object();
	if ( has_post_thumbnail( $post ) ) {
		return get_the_post_thumbnail_url( $post, 'large' );
	}
	// ... auto-card fallback ...
} );
```

(May be slightly different — find the actual code first.)

- [ ] **Step 2: Insert the override check at the top of the filter**

Find the filter callback's body. Right after the `if ( ! is_singular() )` early-return and the `$post = get_queried_object();` line, insert the override check:

```php
	// v1.10.0+: per-post _sn_og_image_url wins over featured image
	// and auto-generated card. Explicit beats implicit.
	if ( function_exists( 'sn_post_settings_get_og_image_url' ) ) {
		$override = sn_post_settings_get_og_image_url( $post->ID );
		if ( '' !== $override ) {
			return $override;
		}
	}
```

Place this BEFORE the `has_post_thumbnail` check so the override takes priority.

- [ ] **Step 3: Verify the insert**

```bash
cd /Users/juanlentino/Projects/signal-and-noise-tools && \
grep -n 'sn_post_settings_get_og_image_url' inc/og-card-generator.php
```

Expected: 1 match — the call inside the `sn_og_image_url` filter callback.

Also verify the call sits BEFORE the featured-image check:

```bash
grep -nE 'sn_post_settings_get_og_image_url|has_post_thumbnail' inc/og-card-generator.php
```

Expected: line number for `sn_post_settings_get_og_image_url` is LESS THAN the line number for `has_post_thumbnail` (the override fires first).

- [ ] **Step 4: Commit**

```bash
cd /Users/juanlentino/Projects/signal-and-noise-tools && \
git add inc/og-card-generator.php && \
git commit -m "og-card-generator: per-post _sn_og_image_url wins over featured image

v1.10.0 lets authors override the OG image URL per-post. The
override takes priority over the existing chain (featured image
→ auto-generated card → site default).

Inserted as a step 0 inside the sn_og_image_url filter callback,
right after the is_singular() guard and \$post lookup. If
sn_post_settings_get_og_image_url returns a non-empty string,
it's the OG image — no further resolution.

function_exists guard keeps this defensive against post-settings.php
absence. Propagates to both <meta property='og:image'> emission
in inc/seo.php and the JSON-LD Article schema image field in
inc/seo-schema.php (both consume the sn_og_image_url filter)."
```

---

## Task 7: Version bump + CHANGELOG + commit + tag + push + deploy

**Files:**
- Modify: `/Users/juanlentino/Projects/signal-and-noise-tools/signal-and-noise-tools.php`
- Modify: `/Users/juanlentino/Projects/signal-and-noise-tools/CHANGELOG.md`

- [ ] **Step 1: Bump Version + SNT_VERSION**

```bash
cd /Users/juanlentino/Projects/signal-and-noise-tools && \
sed -i '' 's/^ \* Version:     1.9.6$/ * Version:     1.10.0/' signal-and-noise-tools.php && \
sed -i '' "s/define( 'SNT_VERSION', '1.9.6' );/define( 'SNT_VERSION', '1.10.0' );/" signal-and-noise-tools.php && \
grep "Version:\|SNT_VERSION" signal-and-noise-tools.php | head -2
```

Expected: both lines show `1.10.0`.

- [ ] **Step 2: Prepend CHANGELOG entry**

Insert this block in `CHANGELOG.md` above the existing `## [1.9.6]` heading:

```markdown
## [1.10.0] - 2026-05-16

### Added
- **Per-post SEO settings UI.** New "Signal & Noise" meta box on the post + page editor (auto-converts to a sidebar panel in the block editor) exposing three overrides:
  - **Noindex toggle** — when checked, adds `noindex,nofollow` to the robots meta tag for that post. Reader has existed since v1.6.0 via `_sn_noindex` post meta; v1.10.0 adds the write path.
  - **Custom meta description** — overrides the post excerpt for `<meta name="description">`, `og:description`, `twitter:description`, AND the JSON-LD Article schema description. Empty falls back to the excerpt.
  - **Custom OG image URL** — overrides the featured image / auto-generated card / site default. Highest priority in the OG image resolution chain. Explicit beats implicit.
- **REST API exposure for all three meta keys** via `register_post_meta()` with `show_in_rest=true`. `/wp-json/wp/v2/posts/{id}` (and pages endpoint) now include `meta._sn_noindex`, `meta._sn_meta_description`, `meta._sn_og_image_url`. `auth_callback` requires `edit_posts` for writes; reads are public (these are user-facing values).
- **`sn_post_settings_get_noindex/description/og_image_url($post_id)` typed accessors** — consumers call these instead of `get_post_meta()` directly so the type contract lives in one place.

### Changed
- **`inc/seo.php`** `sn_seo_meta_for_current_view()` singular branch now checks `_sn_meta_description` before falling back to `$post->post_excerpt`.
- **`inc/seo-schema.php`** Article schema `description` field follows the same fallback chain via new `sn_schema_article_description()` helper.
- **`inc/og-card-generator.php`** OG image filter chain checks `_sn_og_image_url` first, beating featured image / auto card / site default when set.

### Architecture
- **Hybrid PHP meta box + REST exposure** (Approach C from spec research). Zero build pipeline preserved. Same architectural pattern Yoast Free uses at scale. Future migration to a React block-editor sidebar is free thanks to REST exposure — meta keys and storage stay the same.
- Save handler on `save_post` with full guard chain (nonce → DOING_AUTOSAVE → wp_is_post_revision → cap → sanitize). Empty values trigger `delete_post_meta()` to keep the DB clean.
- All three reader integrations use `function_exists()` guards on `sn_post_settings_get_*` calls — defensive against `inc/post-settings.php` absence.
- Two affected post types: `post` + `page` (matches existing hook guards across `inc/reading-time.php`, `inc/og-card-generator.php`, `inc/cloudflare-purge.php`).

### Notes
- **MINOR bump despite minor cap.** Project cap is 5 minors per major; the plugin already exceeded that mid-Phase-1 (shipped 1.0 through 1.9 without rolling to 2.0). Continuing the existing pattern. A strict cap enforcement would require renumbering the entire 1.6-1.9 backlog as v2.x — not justified for a single-user plugin. See spec for detail.
- **Spec**: `docs/superpowers/specs/2026-05-16-per-post-settings-v1.10.0-design.md`. **Plan**: `docs/superpowers/plans/2026-05-16-per-post-settings-v1.10.0-plan.md`. Both grounded in two parallel research-agent reports (codebase mapping + UI architecture).
- **Out of scope** (deferred to v1.10.x or v1.11.0): React block-editor sidebar, custom robots directives beyond noindex (nofollow/noarchive), per-post Twitter card type, bulk-edit / quick-edit support, bulk import/export.

## [1.9.6] - 2026-05-16
```

- [ ] **Step 3: Commit + tag + push**

```bash
cd /Users/juanlentino/Projects/signal-and-noise-tools && \
git add signal-and-noise-tools.php CHANGELOG.md && \
git commit -m "v1.10.0: per-post SEO settings UI (noindex + description + OG image)

MINOR bump. Adds a 'Signal & Noise' meta box to the post + page
editor exposing three per-post overrides:

- noindex (boolean) — existing reader since v1.6.0, write path added
- meta description (string) — overrides post excerpt across <meta>,
  OG, Twitter, AND JSON-LD Article schema
- OG image URL (string) — wins over featured image, auto card,
  site default

Architecture: hybrid PHP meta box + register_post_meta(show_in_rest)
following Yoast's pattern. Zero build pipeline preserved. REST
endpoint /wp-json/wp/v2/posts/{id} now exposes meta._sn_noindex /
meta._sn_meta_description / meta._sn_og_image_url. Future React
sidebar migration is free — storage and meta keys stay the same.

Five file changes: inc/post-settings.php (NEW, ~170 LOC) for
registration + UI + save; inc/seo.php / inc/seo-schema.php /
inc/og-card-generator.php each gain a small _sn_*-override-first
check; assets/admin.css gets ~30 LOC of width overrides for the
narrow side meta box; signal-and-noise-tools.php wires the new
require + version bump.

Spec: docs/superpowers/specs/2026-05-16-per-post-settings-v1.10.0-
design.md (commit 17d3f34). Plan: docs/superpowers/plans/2026-
05-16-per-post-settings-v1.10.0-plan.md. Both grounded in parallel
research from feature-dev:code-explorer + general-purpose agents." && \
git push origin main && \
git tag -a v1.10.0 -m "v1.10.0 — per-post SEO settings UI" && \
git push origin v1.10.0
```

- [ ] **Step 4: Watch deploy + verify CSS asset**

```bash
sleep 12 && \
RUN_ID=$(gh run list --repo juanlentino/signal-and-noise-tools --workflow=deploy.yml --limit 1 --json databaseId -q '.[0].databaseId') && \
gh run watch "$RUN_ID" --repo juanlentino/signal-and-noise-tools --exit-status 2>&1 | tail -5 && \
echo "" && \
echo "=== admin.css reachable at v1.10.0 cache-buster ===" && \
curl -sSI "https://juanlentino.com/wp-content/plugins/signal-and-noise-tools/assets/admin.css?ver=1.10.0" | head -3
```

Expected: deploy success in ~12s. CSS HTTP 200 with `content-type: text/css`.

---

## Task 8: Post-deploy verification

**Files:** N/A (verification only)

- [ ] **Step 1: Byte-diff sanity check — no regression on existing posts (those without overrides)**

```bash
auth=$(printf '%s:%s' 'juanlentino' '<application-password>' | base64) && \
curl -sS -X POST 'https://juanlentino.com/wp-json/signal-noise/v1/purge-cache' \
  -H "Authorization: Basic $auth" -H 'Content-Length: 0' -o /dev/null -w 'purge HTTP=%{http_code}\n' && \
sleep 3 && \
mkdir -p /tmp/sn-v110-post && \
for slug in pricing-in-dollars-from-argentina musics-billion-dollar-metadata-problem; do
  curl -sS "https://juanlentino.com/notes/${slug}/" -o "/tmp/sn-v110-post/${slug}.html"
done && \
for slug in pricing-in-dollars-from-argentina musics-billion-dollar-metadata-problem; do
  echo "--- Diff <head> for $slug (v1.8.0 baseline vs v1.10.0) ---"
  diff \
    <(sed -n '/<head>/,/<\/head>/p' /tmp/sn-v18-baseline/${slug}.html) \
    <(sed -n '/<head>/,/<\/head>/p' /tmp/sn-v110-post/${slug}.html) || true
done
```

Expected: zero diff on both pages. Confirms v1.10.0 is additive-only — posts without the new meta set behave exactly as v1.9.x. (Replace `<application-password>` with the actual value — see security flag below.)

- [ ] **Step 2: REST API exposure check**

```bash
auth=$(printf '%s:%s' 'juanlentino' '<application-password>' | base64) && \
echo "=== /wp-json/wp/v2/posts query — does meta._sn_* appear? ===" && \
curl -sS "https://juanlentino.com/wp-json/wp/v2/posts?per_page=1" \
  -H "Authorization: Basic $auth" | python3 -m json.tool | grep -E '"_sn_|"meta":'
```

Expected: lines showing `"meta":` and three `"_sn_…"` keys inside it. If grep returns nothing, `show_in_rest=true` registration failed — investigate Task 1's `register_post_meta` call.

- [ ] **Step 3: User-side smoke test (manual — `wps-hide-login` blocks automated admin checks)**

Walk through in a logged-in browser:

1. Open any existing post in the block editor.
2. In the right sidebar, look for a "Signal & Noise" panel (likely auto-expanded since priority='high').
3. Three fields visible: noindex checkbox, meta description textarea, OG image URL input.
4. Fill all three:
   - Check noindex
   - Type "Test meta description override" in the textarea
   - Paste a known image URL (e.g., the site logo URL) in the OG image field
5. Click Update to save.
6. View source on the published post — verify:
   - `<meta name="robots">` content includes `noindex,nofollow`
   - `<meta name="description" content="Test meta description override">`
   - `<meta property="og:description" content="Test meta description override">`
   - `<meta property="og:image" content="<the URL you pasted>">`
   - JSON-LD `<script type="application/ld+json">` Article `description` field matches the override
7. Return to the editor; clear all three fields; Update.
8. View source again — all three reverted to defaults (excerpt for description; featured image / auto card for OG; permissive robots).
9. Open `/wp-json/wp/v2/posts/{that post id}` (authenticated curl from Step 2 above); confirm `meta._sn_*` keys are present and reflect the cleared state.

- [ ] **Step 4: Verify the meta box appears on `page` post type too**

Open any existing page (not a post) in the editor. Verify the "Signal & Noise" panel appears in the same sidebar location with the same three fields. Click Update on a page without changes — no errors.

- [ ] **Step 5: Final acceptance criteria checklist**

Walk through the spec's 9 acceptance criteria. Tick each that passed:

- [ ] AC1: Meta box appears on `post` + `page` editor
- [ ] AC2: 3 fields render with current values pre-populated
- [ ] AC3: Save persists to `_sn_*` post meta; empty values trigger delete_post_meta (verify via WP-CLI if SSH available, otherwise via REST API meta object)
- [ ] AC4: `<meta name="description">` honors override (smoke test step 6)
- [ ] AC5: JSON-LD Article description honors override (smoke test step 6)
- [ ] AC6: `_sn_og_image_url` wins over featured / auto / default (smoke test step 6)
- [ ] AC7: noindex toggle changes robots meta content (smoke test step 6)
- [ ] AC8: REST `/wp-json/wp/v2/posts/{id}` exposes all 3 meta keys (Step 2)
- [ ] AC9: No regression for posts WITHOUT overrides (Step 1 byte-diff)

---

## Rollback paths

**If the meta box doesn't render on the post editor:**
- Cause #1: `add_meta_boxes` hook didn't fire — verify `inc/post-settings.php` is loaded (`grep require_once signal-and-noise-tools.php | grep post-settings`).
- Cause #2: Block editor hid it (some themes / plugins remove side meta boxes). Check Settings → Preferences → Panels in the block editor.
- Fix: enable the panel in the block editor's preferences, OR add `__back_compat_meta_box => false` to the `add_meta_box()` args (but this is the default and shouldn't be needed).

**If saving doesn't persist:**
- Cause #1: nonce mismatch — verify `wp_nonce_field( SN_POST_SETTINGS_NONCE, 'sn_post_settings_nonce' )` is rendering in the meta box (view source).
- Cause #2: cap check failing — verify the user is an editor or admin (`edit_post` cap).
- Cause #3: `DOING_AUTOSAVE` always true — happens on classic editor with frequent autosaves; manually click Update to bypass autosave.
- Fix: check the WP debug log for any silent returns.

**If REST endpoint doesn't expose meta:**
- Cause: `register_post_meta()` failed (likely registered too late). Verify Task 1's `init` hook ran.
- Diagnostic: `curl /wp-json/wp/v2/types/post` and look for the meta schema; if absent, `register_post_meta` didn't take.

**If you want to fully revert v1.10.0:**
- `git revert <Task 7 commit SHA>` in the plugin repo.
- Push the revert; deploy fires; old v1.9.6 behaviour restored.
- Per-post meta values stored in `wp_postmeta` are NOT deleted by revert — they just stop having a UI. Either ignore (harmless) or `wp postmeta delete --all-instances _sn_noindex _sn_meta_description _sn_og_image_url` if you want them gone.

---

## Out of scope (deferred to v1.10.x or v1.11.0)

- React block-editor sidebar (Approach B from spec research). Premature; classic meta box auto-conversion works. Migration is free thanks to REST exposure when we want it.
- Custom robots directives beyond noindex (`nofollow`, `noarchive`, `noimageindex`). Would need a richer UI than a single checkbox.
- Per-post Twitter card type override (`summary` vs `summary_large_image`).
- Bulk-edit / quick-edit support on the posts list screen.
- Bulk import/export of per-post settings.
- Custom OG image upload via WP media library (instead of pasting a URL). Would need a media-picker JS handler.

---

## Spec self-review (run by plan author)

- ✅ **Spec coverage:** all 9 acceptance criteria from spec covered. AC1 → Tasks 1-3. AC2 → Task 1 Step 1. AC3 → Task 1 (save handler). AC4 → Task 4. AC5 → Task 5. AC6 → Task 6. AC7 → Task 1 (write path; existing reader unchanged). AC8 → Task 1 (register_post_meta with show_in_rest); verified in Task 8 Step 2. AC9 → Task 8 Step 1 (byte-diff).
- ✅ **No placeholders:** every code block is concrete. The one `<application-password>` reference in Task 8 is intentionally generic with a security note pointing to credential rotation.
- ✅ **Type consistency:** function names (`sn_post_settings_register_meta`, `sn_post_settings_render`, `sn_post_settings_save`, `sn_post_settings_get_*`) used consistently across Tasks 1, 4, 5, 6. Constants (`SN_POST_SETTINGS_NONCE`, `SN_POST_SETTINGS_POST_TYPES`) defined in Task 1 Step 1 and referenced consistently.
- ✅ **Scope:** focused on per-post SEO settings — 5 files, 8 tasks, single coherent feature. Doesn't touch admin tabs, sidebar nav, or anything outside the post-edit screen.
- ✅ **Parallelism call-out:** Tasks 4, 5, 6 explicitly flagged as independent — ready for `subagent-driven-development` dispatch.
