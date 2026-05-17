# Settings Layer v1.8.0 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Lift all site-identity-specific string literals out of v1.5.0-v1.7.0 PHP files into a structured `wp_options['sn_settings']` row, add an Identity tab to the existing admin page for editing, and ship a one-time hostname-gated activation migration so the live JL site's output stays byte-identical post-upgrade.

**Architecture:** Single `wp_option` row stores all settings as a nested array (5 categories: identity, social, og, login, seo_copy). New `inc/settings.php` exposes a static-cached `sn_setting('cat.field', $fallback)` accessor + `sn_settings_save($raw)` write handler with per-field sanitization. Activation hook (or lazy `admin_init` fallback for SSH deploys) seeds JL legacy values into `wp_options` once, hostname-gated to `juanlentino.com`. Existing `apply_filters()` hooks are preserved as override stack on top of stored settings. Admin UI is a single `<form>` rendered in `inc/admin-page.php` under the new `?tab=identity` URL.

**Tech Stack:** WordPress (PHP), WP options API, native HTML form (no JS framework), WP-CLI for post-deploy verification over SSH.

**Spec:** [docs/superpowers/specs/2026-05-16-settings-layer-v1.8.0-design.md](../specs/2026-05-16-settings-layer-v1.8.0-design.md) (commit `8a71ca6`)

**Working directory:** `/Users/juanlentino/Projects/signal-and-noise-tools` (branch `main`; auto-deploys on tag push)

**Pre-deploy baseline (captured 2026-05-16):** `/tmp/sn-v18-baseline/pricing-in-dollars-from-argentina.html` (102162 bytes), `/tmp/sn-v18-baseline/musics-billion-dollar-metadata-problem.html` (104346 bytes). Used in Task 8 for byte-identical `<head>` diff verification.

---

## File Structure

| File | Status | Responsibility | LOC |
|---|---|---|---|
| `inc/settings.php` | NEW | Schema + defaults + accessor + save handler + activation migration + lazy fallback | ~180 |
| `signal-and-noise-tools.php` | MODIFY | Add `require_once` for settings.php (must be FIRST); register activation + admin_init hooks; bump Version + SNT_VERSION; tag v1.8.0 | +5/-2 |
| `inc/seo.php` | MODIFY | Replace 8 hardcoded JL-specific values with `sn_setting()` calls | ±30 |
| `inc/seo-schema.php` | MODIFY | Replace 4 hardcoded JL-specific values with `sn_setting()` calls | ±15 |
| `inc/login-hide.php` | MODIFY | `sn_login_get_slug()` reads from settings with constant override | ±5 |
| `inc/admin-page.php` | MODIFY | Add 'identity' tab: $valid_tabs/$tab_labels entries, save_identity action handler, tab-render block with form | +160 |
| `CHANGELOG.md` | MODIFY | Prepend v1.8.0 entry | +25 |

Total new code: ~370 LOC. Eight atomic commits + tag.

---

## Task 1: NEW `inc/settings.php`

**Files:**
- Create: `/Users/juanlentino/Projects/signal-and-noise-tools/inc/settings.php`

- [ ] **Step 1: Write the full settings module**

Create `inc/settings.php` with this content:

```php
<?php
/**
 * Signal & Noise Tools — settings storage + accessors.
 *
 * Single wp_option ('sn_settings') stores all site-identity
 * configuration as a structured array across 5 categories:
 * identity, social, og, login, seo_copy. Code throughout the
 * plugin reads via `sn_setting('cat.field')` with dot-paths.
 *
 * Defaults are generic — pulled from WP built-ins (get_bloginfo)
 * where possible — keeping the plugin portable for new installs.
 * The current production site (juanlentino.com) gets its specific
 * legacy values seeded on first v1.8.0 activation via a hostname-
 * gated migration. A lazy admin_init fallback covers SSH-based
 * upgrades where register_activation_hook doesn't fire.
 *
 * Added in v1.8.0 (2026-05-16, Phase 11.5).
 *
 * @package SignalNoiseTools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const SN_SETTINGS_OPTION       = 'sn_settings';
const SN_SETTINGS_MIGRATED_FLAG = 'sn_settings_migrated_v1';
const SN_LEGACY_HOST            = 'juanlentino.com';

/**
 * Full settings schema with generic defaults.
 *
 * Defaults are intentionally NOT site-specific. The juanlentino.com
 * legacy values are seeded via sn_settings_seed_legacy_values()
 * exactly once per environment, hostname-gated.
 *
 * @return array<string,array<string,mixed>>
 */
function sn_settings_defaults() {
	return array(
		'identity' => array(
			'site_name'        => get_bloginfo( 'name' ),
			'site_description' => get_bloginfo( 'description' ),
			'person_name'      => get_bloginfo( 'name' ),
			'locale'           => 'en_US',
		),
		'social' => array(
			'twitter_handle' => '',
			'same_as'        => array(),
		),
		'og' => array(
			'default_image_url' => '',
			'card_width'        => 1200,
			'card_height'       => 630,
		),
		'login' => array(
			'slug' => 'sn-login',
		),
		'seo_copy' => array(
			'home_title'             => '',
			'home_description'       => '',
			'notes_title'            => '',
			'notes_description'      => '',
			'provenance_title'       => '',
			'provenance_description' => '',
		),
	);
}

/**
 * Read a setting by dot-delimited path, deep-merged with defaults.
 *
 * Static-cached per request — one get_option() call regardless of
 * how many sn_setting() invocations.
 *
 * @param string $path    Dot-delimited path (e.g. 'identity.site_name').
 * @param mixed  $default Fallback if path doesn't resolve.
 * @return mixed
 */
function sn_setting( $path, $default = null ) {
	static $merged = null;
	if ( null === $merged ) {
		$stored = get_option( SN_SETTINGS_OPTION, array() );
		$merged = array_replace_recursive(
			sn_settings_defaults(),
			is_array( $stored ) ? $stored : array()
		);
	}
	$value = $merged;
	foreach ( explode( '.', $path ) as $key ) {
		if ( ! is_array( $value ) || ! array_key_exists( $key, $value ) ) {
			return $default;
		}
		$value = $value[ $key ];
	}
	return $value;
}

/**
 * Sanitize + persist settings from a $_POST submission.
 *
 * @param array $raw Raw $_POST data from the Identity tab form.
 * @return bool True on update_option success.
 */
function sn_settings_save( $raw ) {
	$sanitized = array();

	$sanitized['identity'] = array(
		'site_name'        => sanitize_text_field( (string) ( $raw['identity_site_name'] ?? '' ) ),
		'site_description' => sanitize_text_field( (string) ( $raw['identity_site_description'] ?? '' ) ),
		'person_name'      => sanitize_text_field( (string) ( $raw['identity_person_name'] ?? '' ) ),
		'locale'           => sanitize_text_field( (string) ( $raw['identity_locale'] ?? 'en_US' ) ),
	);

	$same_as_raw   = (array) ( $raw['social_same_as'] ?? array() );
	$same_as_clean = array();
	foreach ( $same_as_raw as $url ) {
		$url = esc_url_raw( trim( (string) $url ) );
		if ( $url ) {
			$same_as_clean[] = $url;
		}
	}
	$sanitized['social'] = array(
		'twitter_handle' => sanitize_text_field( (string) ( $raw['social_twitter_handle'] ?? '' ) ),
		'same_as'        => array_values( $same_as_clean ),
	);

	$sanitized['og'] = array(
		'default_image_url' => esc_url_raw( (string) ( $raw['og_default_image_url'] ?? '' ) ),
		'card_width'        => max( 1, (int) ( $raw['og_card_width'] ?? 1200 ) ),
		'card_height'       => max( 1, (int) ( $raw['og_card_height'] ?? 630 ) ),
	);

	$sanitized['login'] = array(
		'slug' => sanitize_title( (string) ( $raw['login_slug'] ?? 'sn-login' ) ),
	);

	$sanitized['seo_copy'] = array(
		'home_title'             => sanitize_text_field( (string) ( $raw['seo_home_title'] ?? '' ) ),
		'home_description'       => sanitize_textarea_field( (string) ( $raw['seo_home_description'] ?? '' ) ),
		'notes_title'            => sanitize_text_field( (string) ( $raw['seo_notes_title'] ?? '' ) ),
		'notes_description'      => sanitize_textarea_field( (string) ( $raw['seo_notes_description'] ?? '' ) ),
		'provenance_title'       => sanitize_text_field( (string) ( $raw['seo_provenance_title'] ?? '' ) ),
		'provenance_description' => sanitize_textarea_field( (string) ( $raw['seo_provenance_description'] ?? '' ) ),
	);

	return (bool) update_option( SN_SETTINGS_OPTION, $sanitized );
}

/**
 * One-time seed of the JL-specific legacy values into wp_options.
 *
 * Hostname-gated: only seeds when home_url() host is juanlentino.com.
 * On any other host, sets only the migrated flag so the migration
 * doesn't re-attempt and generic defaults from sn_settings_defaults()
 * take over.
 *
 * Idempotent — guarded by SN_SETTINGS_MIGRATED_FLAG.
 */
function sn_settings_seed_legacy_values() {
	if ( get_option( SN_SETTINGS_MIGRATED_FLAG ) ) {
		return;
	}

	$host = parse_url( home_url(), PHP_URL_HOST );
	if ( $host === SN_LEGACY_HOST ) {
		update_option( SN_SETTINGS_OPTION, array(
			'identity' => array(
				'site_name'        => 'Juan Lentino',
				'site_description' => 'Music Production & Creative Strategy',
				'person_name'      => 'Juan Lentino',
				'locale'           => 'en_US',
			),
			'social' => array(
				'twitter_handle' => '@juan_lentino',
				'same_as'        => array(
					'https://x.com/juan_lentino',
					'https://instagram.com/juan_lentino',
					'https://linkedin.com/in/juanlentino',
				),
			),
			'og' => array(
				'default_image_url' => home_url( '/wp-content/uploads/2026/02/cropped-jl_logo-min-300x300.png' ),
				'card_width'        => 1200,
				'card_height'       => 630,
			),
			'login' => array(
				'slug' => 'sn-login',
			),
			'seo_copy' => array(
				'home_title'             => 'Juan Lentino — Music producer & creative strategist',
				'home_description'       => 'Music producer, mix engineer, and creative strategist based in Buenos Aires. Founder of Panacea recording studio.',
				'notes_title'            => 'Notes — Juan Lentino',
				'notes_description'      => 'Working notes on music, AI, and the infrastructure underneath. Written when there\'s something worth writing.',
				'provenance_title'       => 'Music has a verification problem. Detection isn\'t the answer.',
				'provenance_description' => "A short read on why the industry needs to prove what's human, not chase what isn't.",
			),
		) );
	}

	update_option( SN_SETTINGS_MIGRATED_FLAG, '1' );
}

/**
 * Lazy fallback handler for SSH-based upgrades.
 *
 * register_activation_hook() doesn't fire when the plugin is upgraded
 * via git checkout (Phase 2c auto-deploy uses SSH + git checkout).
 * Run the migration check on admin_init so it self-heals on the next
 * admin pageview.
 */
function sn_settings_lazy_migration_check() {
	if ( ! get_option( SN_SETTINGS_MIGRATED_FLAG ) ) {
		sn_settings_seed_legacy_values();
	}
}
```

- [ ] **Step 2: Verify file syntax + LOC**

```bash
cd /Users/juanlentino/Projects/signal-and-noise-tools && \
wc -l inc/settings.php && \
php -l inc/settings.php 2>&1 || echo "(php unavailable; live server will catch syntax)"
```

Expected: ~180 LOC. `No syntax errors detected` (or fallback message if php not on PATH).

- [ ] **Step 3: Commit**

```bash
cd /Users/juanlentino/Projects/signal-and-noise-tools && \
git add inc/settings.php && \
git commit -m "settings: introduce sn_settings option + accessor + sanitizing save

NEW inc/settings.php (~180 LOC) — single source of truth for site-
identity config across 5 categories (identity, social, og, login,
seo_copy). Backing store is one wp_option row (sn_settings).

Public API:
- sn_setting(\$path, \$default)  — dot-path read, static-cached
- sn_settings_save(\$raw)        — sanitize + persist
- sn_settings_defaults()        — generic WP-built-in defaults
- sn_settings_seed_legacy_values() — hostname-gated activation
                                     migration (juanlentino.com only)
- sn_settings_lazy_migration_check() — admin_init self-heal for
                                       SSH-based upgrades

Hook registration lives in the bootstrap (next commit) so the
activation hook gets the correct plugin file path."
```

---

## Task 2: Bootstrap wiring

**Files:**
- Modify: `/Users/juanlentino/Projects/signal-and-noise-tools/signal-and-noise-tools.php`

- [ ] **Step 1: Add `require_once` line for settings.php FIRST in the require chain**

In `signal-and-noise-tools.php`, find the first `require_once` line (currently `require_once SNT_PATH . 'inc/seo.php';` at line 53). Insert immediately above it:

```php
require_once SNT_PATH . 'inc/settings.php';
```

This ensures `sn_setting()` is defined before any consumer file (seo.php, seo-schema.php, login-hide.php) loads.

- [ ] **Step 2: Register the activation hook + admin_init lazy fallback**

After the last `require_once` line (around line 125), append:

```php
// Settings migration: seed legacy values once per environment.
// register_activation_hook fires only on WP-upgrader-driven activations;
// the admin_init handler covers SSH-based git-checkout deploys.
register_activation_hook( __FILE__, 'sn_settings_seed_legacy_values' );
add_action( 'admin_init', 'sn_settings_lazy_migration_check' );
```

- [ ] **Step 3: Verify changes**

```bash
cd /Users/juanlentino/Projects/signal-and-noise-tools && \
grep -n 'inc/settings.php\|sn_settings_seed_legacy_values\|sn_settings_lazy_migration_check' signal-and-noise-tools.php
```

Expected: 3 matches — the require_once line, the register_activation_hook call, and the add_action call.

- [ ] **Step 4: Commit**

```bash
cd /Users/juanlentino/Projects/signal-and-noise-tools && \
git add signal-and-noise-tools.php && \
git commit -m "bootstrap: require settings.php first; register migration hooks

inc/settings.php loads before any consumer (seo.php, seo-schema.php,
login-hide.php) so sn_setting() is available when those modules
register their wp_head emission callbacks.

Two hooks register the migration:
- register_activation_hook for WP-upgrader-based activations
- admin_init lazy fallback for SSH-based git-checkout upgrades
  (Phase 2c auto-deploy doesn't trigger activation hooks)

Both call sn_settings_seed_legacy_values() which is idempotent
and hostname-gated."
```

---

## Task 3: Refactor `inc/seo.php` — replace 8 hardcoded values

**Files:**
- Modify: `/Users/juanlentino/Projects/signal-and-noise-tools/inc/seo.php`

- [ ] **Step 1: Refactor `sn_seo_meta_for_current_view()` — hardcoded strings → settings**

Find the function `sn_seo_meta_for_current_view()` (around line 32). Replace its `is_front_page()`, `is_page('notes')`, and `is_page('provenance')` branches:

```php
function sn_seo_meta_for_current_view() {
	$title       = '';
	$description = '';
	$url         = '';

	if ( is_front_page() ) {
		$title       = sn_setting( 'seo_copy.home_title', '' );
		$description = sn_setting( 'seo_copy.home_description', '' );
		$url         = home_url( '/' );
	} elseif ( is_page( 'notes' ) || is_home() ) {
		$title       = sn_setting( 'seo_copy.notes_title', '' );
		$description = sn_setting( 'seo_copy.notes_description', '' );
		$url         = home_url( '/notes/' );
	} elseif ( is_page( 'provenance' ) ) {
		$title       = sn_setting( 'seo_copy.provenance_title', '' );
		$description = sn_setting( 'seo_copy.provenance_description', '' );
		$url         = home_url( '/provenance/' );
	} elseif ( is_singular() ) {
		$post  = get_queried_object();
		$title = $post ? wp_strip_all_tags( get_the_title( $post ) ) . ' — ' . sn_setting( 'identity.site_name', get_bloginfo( 'name' ) ) : '';
		if ( $post && ! empty( $post->post_excerpt ) ) {
			$description = wp_strip_all_tags( $post->post_excerpt );
		}
		$url = $post ? get_permalink( $post ) : '';
	}

	return array( $title, $description, $url );
}
```

Note: the singular-post title pattern (`$title . ' — Juan Lentino'`) is also a hardcoded reference — replaced with `sn_setting( 'identity.site_name', get_bloginfo( 'name' ) )`.

- [ ] **Step 2: Refactor the wp_head OG/Twitter block — 6 hardcoded values**

Find the `add_action( 'wp_head', ..., 3 )` block that emits og:type, og:locale, og:title, og:description, og:url, og:site_name, og:image, twitter:card, twitter:image, twitter:site, twitter:creator, article:published_time, article:modified_time.

Replace the hardcoded segments:

```php
// REPLACE this hardcoded site_name:
// echo '<meta property="og:site_name" content="Juan Lentino">' . "\n";
$site_name = sn_setting( 'identity.site_name', get_bloginfo( 'name' ) );
echo '<meta property="og:site_name" content="' . esc_attr( $site_name ) . '">' . "\n";

// REPLACE this hardcoded og:locale:
// echo '<meta property="og:locale" content="en_US">' . "\n";
$locale = sn_setting( 'identity.locale', 'en_US' );
echo '<meta property="og:locale" content="' . esc_attr( $locale ) . '">' . "\n";

// REPLACE this hardcoded fallback URL:
// $default_og = home_url( '/wp-content/uploads/2026/02/cropped-jl_logo-min-300x300.png' );
$default_og = sn_setting( 'og.default_image_url', '' );

// REPLACE the hardcoded image dimensions:
// $dims = (array) apply_filters( 'sn_og_image_dimensions', array( 1200, 630 ), $og_image );
$dims = (array) apply_filters(
	'sn_og_image_dimensions',
	array(
		sn_setting( 'og.card_width', 1200 ),
		sn_setting( 'og.card_height', 630 ),
	),
	$og_image
);

// REPLACE the hardcoded twitter handle:
// $twitter_handle = (string) apply_filters( 'sn_twitter_handle', '@juan_lentino' );
$twitter_handle = (string) apply_filters(
	'sn_twitter_handle',
	sn_setting( 'social.twitter_handle', '' )
);
```

All other lines in the block stay unchanged. The filter compat pattern: `apply_filters('sn_<name>', sn_setting('<path>', $fallback))` — settings are the value, filters are the override stack.

- [ ] **Step 3: Verify no JL-identity strings remain**

```bash
cd /Users/juanlentino/Projects/signal-and-noise-tools && \
grep -nE 'Juan Lentino|@juan_lentino|Music producer|Music Production|Working notes|verification problem' inc/seo.php
```

Expected: zero matches. (The Breeze exclude filters and gtag.js block at the bottom of the file stay untouched.)

- [ ] **Step 4: Commit**

```bash
cd /Users/juanlentino/Projects/signal-and-noise-tools && \
git add inc/seo.php && \
git commit -m "seo: replace hardcoded JL strings with sn_setting() calls

Refactors sn_seo_meta_for_current_view() and the wp_head OG/Twitter
emission block to read from wp_option('sn_settings') via sn_setting().

Replaces 8 hardcoded values:
- Front page title + description
- /notes title + description
- /provenance title + description
- Singular-post title suffix (' — Juan Lentino' → ' — <site_name>')
- og:site_name
- og:locale
- og:image fallback URL
- og:image:width / og:image:height
- twitter:site / twitter:creator handle

Existing apply_filters() hooks preserved as override stack:
  apply_filters('sn_twitter_handle', sn_setting('social.twitter_handle', ''))
  apply_filters('sn_og_image_dimensions', [w_from_setting, h_from_setting], \$url)

Live-site output stays byte-identical because the activation
migration seeds the JL legacy values into wp_options['sn_settings']."
```

---

## Task 4: Refactor `inc/seo-schema.php` — replace 4 hardcoded values

**Files:**
- Modify: `/Users/juanlentino/Projects/signal-and-noise-tools/inc/seo-schema.php`

- [ ] **Step 1: Refactor `sn_schema_default_same_as()`**

Replace the function body (around line 33):

```php
function sn_schema_default_same_as() {
	return (array) sn_setting( 'social.same_as', array() );
}
```

Note: the function name is now slightly misleading (it returns stored values, not "defaults"), but renaming is out of scope — kept stable to avoid breaking the `sn_schema_same_as` filter signature in `sn_schema_person()`.

- [ ] **Step 2: Refactor `sn_schema_person()`**

Replace the function body (around line 43):

```php
function sn_schema_person() {
	$home    = home_url( '/' );
	$same_as = (array) apply_filters( 'sn_schema_same_as', sn_schema_default_same_as() );
	$name    = sn_setting( 'identity.person_name', get_bloginfo( 'name' ) );

	return array(
		'@type'  => 'Person',
		'@id'    => $home . '#/schema/Person',
		'name'   => $name,
		'url'    => $home,
		'sameAs' => array_values( $same_as ),
	);
}
```

- [ ] **Step 3: Refactor `sn_schema_website()`**

Replace the function body (around line 58):

```php
function sn_schema_website() {
	$home   = home_url( '/' );
	$locale = sn_setting( 'identity.locale', 'en_US' );

	return array(
		'@type'       => 'WebSite',
		'@id'         => $home . '#/schema/WebSite',
		'url'         => $home,
		'name'        => sn_setting( 'identity.site_name', get_bloginfo( 'name' ) ),
		'description' => sn_setting( 'identity.site_description', get_bloginfo( 'description' ) ),
		'inLanguage'  => str_replace( '_', '-', $locale ),
		'publisher'   => array(
			'@id' => $home . '#/schema/Person',
		),
	);
}
```

Note: `inLanguage` follows BCP-47 (`en-US`) while the WP locale uses `en_US` underscore — `str_replace` handles the conversion.

- [ ] **Step 4: Verify no JL-identity strings remain**

```bash
cd /Users/juanlentino/Projects/signal-and-noise-tools && \
grep -nE 'Juan Lentino|@juan_lentino|Music Production|x\.com/juan_lentino|instagram\.com/juan_lentino|linkedin\.com/in/juanlentino' inc/seo-schema.php
```

Expected: zero matches.

- [ ] **Step 5: Commit**

```bash
cd /Users/juanlentino/Projects/signal-and-noise-tools && \
git add inc/seo-schema.php && \
git commit -m "seo-schema: replace hardcoded JL strings with sn_setting() calls

Refactors sn_schema_default_same_as(), sn_schema_person(), and
sn_schema_website() to read from wp_option('sn_settings').

Replaces 4 hardcoded values:
- The 3 sameAs profile URLs (x.com, instagram.com, linkedin.com)
- Person name ('Juan Lentino')
- WebSite name + description (site identity)
- WebSite inLanguage (derived from identity.locale setting)

The sn_schema_same_as filter still applies as an override on top
of the stored sameAs array, preserving backward compat for any
code that hooks it."
```

---

## Task 5: Refactor `inc/login-hide.php` — slug from settings

**Files:**
- Modify: `/Users/juanlentino/Projects/signal-and-noise-tools/inc/login-hide.php`

- [ ] **Step 1: Refactor `sn_login_get_slug()`**

Replace the function (around line 36):

```php
function sn_login_get_slug() {
	// Constant override has highest priority — for wp-config.php-based
	// emergency unlocks and per-environment overrides.
	if ( defined( 'SN_LOGIN_SLUG' ) && SN_LOGIN_SLUG ) {
		return trim( (string) SN_LOGIN_SLUG, '/' );
	}
	// Otherwise the configured setting (defaults to 'sn-login').
	$slug = sn_setting( 'login.slug', 'sn-login' );
	return $slug ? $slug : 'sn-login';
}
```

The function signature and behaviour are unchanged for callers; only the resolution order changes (was: constant → hardcoded 'sn-login'; now: constant → setting → hardcoded 'sn-login').

- [ ] **Step 2: Verify**

```bash
cd /Users/juanlentino/Projects/signal-and-noise-tools && \
grep -nE "sn_setting|SN_LOGIN_SLUG|return.*'sn-login'" inc/login-hide.php
```

Expected: 3 matches — the constant check, the sn_setting call, and the hardcoded final fallback.

- [ ] **Step 3: Commit**

```bash
cd /Users/juanlentino/Projects/signal-and-noise-tools && \
git add inc/login-hide.php && \
git commit -m "login-hide: read slug from sn_setting with constant override

sn_login_get_slug() now resolves the slug in priority order:
  1. SN_LOGIN_SLUG constant (wp-config.php emergency override)
  2. sn_setting('login.slug') — user-editable wp_option
  3. Hardcoded fallback 'sn-login' (final safety net)

Function signature unchanged; existing callers in the same file
don't need changes."
```

---

## Task 6: Admin UI — add Identity tab to `inc/admin-page.php`

**Files:**
- Modify: `/Users/juanlentino/Projects/signal-and-noise-tools/inc/admin-page.php`

- [ ] **Step 1: Add `'identity'` to `$valid_tabs`**

Find the `$valid_tabs` array (around line 55):

```php
// REPLACE:
$valid_tabs = array( 'dashboard', 'cloudflare', 'plausible', 'rss', 'reading-time', 'links' );
// WITH:
$valid_tabs = array( 'dashboard', 'identity', 'cloudflare', 'plausible', 'rss', 'reading-time', 'links' );
```

Placement after `'dashboard'` makes it the second tab — high-visibility for the new feature.

- [ ] **Step 2: Add `'identity' => 'Identity'` to `$tab_labels`**

Find the `$tab_labels` array (around line 111):

```php
// REPLACE:
$tab_labels = array(
    'dashboard'    => 'Dashboard',
    'cloudflare'   => 'Cloudflare',
    'plausible'    => 'Plausible',
    'rss'          => 'RSS',
    'reading-time' => 'Reading Time',
    'links'        => 'Links',
);
// WITH:
$tab_labels = array(
    'dashboard'    => 'Dashboard',
    'identity'     => 'Identity',
    'cloudflare'   => 'Cloudflare',
    'plausible'    => 'Plausible',
    'rss'          => 'RSS',
    'reading-time' => 'Reading Time',
    'links'        => 'Links',
);
```

- [ ] **Step 3: Add `save_identity` action handler**

Find the `$_POST['sn_action']` switch block (around lines 62-91). After the last `if ( 'full_reset' === $action ) { ... }` block, append:

```php
if ( 'save_identity' === $action ) {
	$saved = sn_settings_save( $_POST );
	if ( $saved ) {
		$notices[] = array( 'success', 'Identity settings saved.' );
	} else {
		$notices[] = array( 'info', 'No changes to save.' );
	}
}
```

`update_option()` returns false when the new value equals the existing one, so distinguish "no change" from "failed" via the `info` notice.

- [ ] **Step 4: Add the Identity tab render block**

After the existing tab-render chain (find the last `elseif` block — likely for `'links'`), append a new `elseif` block. This is the largest single addition. Insert this entire block:

```php
} elseif ( 'identity' === $active_tab ) {

	echo '<form method="post">';
	wp_nonce_field( 'sn_theme_options_nonce' );
	echo '<input type="hidden" name="sn_action" value="save_identity">';

	echo '<p style="color:#666;max-width:700px;">Site-identity values used by OG/Twitter meta, JSON-LD schema, the custom login URL, and per-route SEO copy. Empty fields fall back to WordPress built-in defaults (site name, tagline). The <code>SN_LOGIN_SLUG</code> constant in wp-config.php overrides the Login Slug field below.</p>';

	// ── IDENTITY ──
	echo '<h2 style="font-size:1.1em;margin:1.5em 0 0.6em;">Identity</h2>';
	echo '<table class="form-table"><tbody>';

	echo '<tr><th><label for="sn_identity_site_name">Site name</label></th>';
	echo '<td><input type="text" id="sn_identity_site_name" name="identity_site_name" value="' . esc_attr( sn_setting( 'identity.site_name', '' ) ) . '" class="regular-text"></td></tr>';

	echo '<tr><th><label for="sn_identity_site_description">Site description</label></th>';
	echo '<td><textarea id="sn_identity_site_description" name="identity_site_description" rows="2" class="large-text">' . esc_textarea( (string) sn_setting( 'identity.site_description', '' ) ) . '</textarea></td></tr>';

	echo '<tr><th><label for="sn_identity_person_name">Person name (schema author)</label></th>';
	echo '<td><input type="text" id="sn_identity_person_name" name="identity_person_name" value="' . esc_attr( sn_setting( 'identity.person_name', '' ) ) . '" class="regular-text"></td></tr>';

	echo '<tr><th><label for="sn_identity_locale">Locale</label></th>';
	echo '<td><input type="text" id="sn_identity_locale" name="identity_locale" value="' . esc_attr( sn_setting( 'identity.locale', 'en_US' ) ) . '" class="regular-text" placeholder="en_US"><p class="description">WP locale code (e.g. <code>en_US</code>). Used for og:locale and schema inLanguage.</p></td></tr>';

	echo '</tbody></table>';

	// ── SOCIAL ──
	echo '<h2 style="font-size:1.1em;margin:1.5em 0 0.6em;">Social</h2>';
	echo '<table class="form-table"><tbody>';

	echo '<tr><th><label for="sn_social_twitter_handle">Twitter / X handle</label></th>';
	echo '<td><input type="text" id="sn_social_twitter_handle" name="social_twitter_handle" value="' . esc_attr( sn_setting( 'social.twitter_handle', '' ) ) . '" class="regular-text" placeholder="@username"><p class="description">Used as twitter:site and twitter:creator. Include the @ prefix.</p></td></tr>';

	$same_as = (array) sn_setting( 'social.same_as', array() );
	// Render existing + one trailing blank row.
	$rows    = array_merge( $same_as, array( '' ) );
	echo '<tr><th><label>Profile URLs (sameAs)</label></th><td>';
	foreach ( $rows as $url ) {
		echo '<input type="url" name="social_same_as[]" value="' . esc_attr( (string) $url ) . '" class="regular-text" style="margin-bottom:4px;display:block;"placeholder="https://x.com/...">';
	}
	echo '<p class="description">Emitted as the Person schema sameAs array. Leave a row empty to remove it on save.</p></td></tr>';

	echo '</tbody></table>';

	// ── OG ──
	echo '<h2 style="font-size:1.1em;margin:1.5em 0 0.6em;">Open Graph</h2>';
	echo '<table class="form-table"><tbody>';

	echo '<tr><th><label for="sn_og_default_image_url">Default OG image URL</label></th>';
	echo '<td><input type="url" id="sn_og_default_image_url" name="og_default_image_url" value="' . esc_attr( (string) sn_setting( 'og.default_image_url', '' ) ) . '" class="large-text"><p class="description">Fallback image used when no per-post OG card exists.</p></td></tr>';

	echo '<tr><th><label for="sn_og_card_width">Card width (px)</label></th>';
	echo '<td><input type="number" min="1" id="sn_og_card_width" name="og_card_width" value="' . esc_attr( (string) sn_setting( 'og.card_width', 1200 ) ) . '" class="small-text"></td></tr>';

	echo '<tr><th><label for="sn_og_card_height">Card height (px)</label></th>';
	echo '<td><input type="number" min="1" id="sn_og_card_height" name="og_card_height" value="' . esc_attr( (string) sn_setting( 'og.card_height', 630 ) ) . '" class="small-text"></td></tr>';

	echo '</tbody></table>';

	// ── LOGIN ──
	echo '<h2 style="font-size:1.1em;margin:1.5em 0 0.6em;">Login</h2>';
	echo '<table class="form-table"><tbody>';

	echo '<tr><th><label for="sn_login_slug">Custom login slug</label></th>';
	echo '<td><input type="text" id="sn_login_slug" name="login_slug" value="' . esc_attr( (string) sn_setting( 'login.slug', 'sn-login' ) ) . '" class="regular-text" placeholder="sn-login"><p class="description">Replaces <code>/wp-login.php</code>. The <code>SN_LOGIN_SLUG</code> constant in wp-config.php overrides this field.</p></td></tr>';

	echo '</tbody></table>';

	// ── SEO COPY ──
	echo '<h2 style="font-size:1.1em;margin:1.5em 0 0.6em;">SEO Copy (per-route)</h2>';
	echo '<table class="form-table"><tbody>';

	echo '<tr><th><label for="sn_seo_home_title">Home title</label></th>';
	echo '<td><input type="text" id="sn_seo_home_title" name="seo_home_title" value="' . esc_attr( (string) sn_setting( 'seo_copy.home_title', '' ) ) . '" class="large-text"></td></tr>';

	echo '<tr><th><label for="sn_seo_home_description">Home description</label></th>';
	echo '<td><textarea id="sn_seo_home_description" name="seo_home_description" rows="2" class="large-text">' . esc_textarea( (string) sn_setting( 'seo_copy.home_description', '' ) ) . '</textarea></td></tr>';

	echo '<tr><th><label for="sn_seo_notes_title">/notes title</label></th>';
	echo '<td><input type="text" id="sn_seo_notes_title" name="seo_notes_title" value="' . esc_attr( (string) sn_setting( 'seo_copy.notes_title', '' ) ) . '" class="large-text"></td></tr>';

	echo '<tr><th><label for="sn_seo_notes_description">/notes description</label></th>';
	echo '<td><textarea id="sn_seo_notes_description" name="seo_notes_description" rows="2" class="large-text">' . esc_textarea( (string) sn_setting( 'seo_copy.notes_description', '' ) ) . '</textarea></td></tr>';

	echo '<tr><th><label for="sn_seo_provenance_title">/provenance title</label></th>';
	echo '<td><input type="text" id="sn_seo_provenance_title" name="seo_provenance_title" value="' . esc_attr( (string) sn_setting( 'seo_copy.provenance_title', '' ) ) . '" class="large-text"></td></tr>';

	echo '<tr><th><label for="sn_seo_provenance_description">/provenance description</label></th>';
	echo '<td><textarea id="sn_seo_provenance_description" name="seo_provenance_description" rows="2" class="large-text">' . esc_textarea( (string) sn_setting( 'seo_copy.provenance_description', '' ) ) . '</textarea></td></tr>';

	echo '</tbody></table>';

	echo '<p style="margin-top:1.5em;"><button type="submit" class="button button-primary">Save Identity Settings</button></p>';
	echo '</form>';
```

Place this `elseif` block in the existing tab-render chain (after the last existing `elseif`, before the closing `}` of the function).

- [ ] **Step 2: Verify the form renders correctly**

After the admin page is reachable (post-deploy), navigate to `wp-admin/themes.php?page=sn-theme-options&tab=identity`. Verify all field labels appear, all input values match seeded data, the Save button is at the bottom. Verified in Task 8.

- [ ] **Step 3: Commit**

```bash
cd /Users/juanlentino/Projects/signal-and-noise-tools && \
git add inc/admin-page.php && \
git commit -m "admin-page: add Identity tab for site-identity settings

Adds 'identity' to the existing tab chain in Appearance → Signal & Noise.
Single <form> with 5 grouped sections (Identity, Social, OG, Login,
SEO Copy) corresponding to the sn_settings categories.

Form posts back with sn_action=save_identity; handler calls
sn_settings_save() with check_admin_referer() guarding. Renders
a success notice on update, info notice on no-change.

Profile URLs (sameAs) render as a list of <input type=url> rows
plus one trailing empty row to add a new URL. Empty rows are
filtered out by sn_settings_save() on submission.

Field IDs are namespaced (sn_<category>_<field>) to avoid collision
with other plugins' admin form elements."
```

---

## Task 7: Version bump + CHANGELOG + tag

**Files:**
- Modify: `signal-and-noise-tools.php` (Version field + SNT_VERSION constant)
- Modify: `CHANGELOG.md` (prepend new entry)

- [ ] **Step 1: Bump Version header**

In `signal-and-noise-tools.php`, line ~6:

```php
// REPLACE:
 * Version:     1.7.0
// WITH:
 * Version:     1.8.0
```

- [ ] **Step 2: Bump SNT_VERSION constant**

In `signal-and-noise-tools.php`, line ~21:

```php
// REPLACE:
define( 'SNT_VERSION', '1.7.0' );
// WITH:
define( 'SNT_VERSION', '1.8.0' );
```

- [ ] **Step 3: Prepend CHANGELOG entry**

In `CHANGELOG.md`, immediately above the existing `## [1.7.0] - 2026-05-16` heading, insert:

```markdown
## [1.8.0] - 2026-05-16

### Added
- `inc/settings.php` — single source of truth for site-identity config (~180 LOC). Stores all settings in one `wp_options['sn_settings']` row across 5 categories: identity, social, og, login, seo_copy.
- **Identity tab in admin page** (`Appearance → Signal & Noise → Identity`) — single form with grouped fields for: site name + description + person name + locale; Twitter handle + sameAs profile URLs; default OG image URL + card dimensions; custom login slug; per-route SEO titles + descriptions.
- **Activation migration** (`sn_settings_seed_legacy_values`) — hostname-gated to `juanlentino.com`; seeds existing JL values into `wp_options` exactly once per environment. Subsequent activations no-op via `sn_settings_migrated_v1` flag. Lazy admin_init fallback covers SSH-based deploys where `register_activation_hook` doesn't fire.
- **`sn_setting('cat.field', $fallback)` accessor** — static-cached, dot-path read with deep-merge over defaults. Used throughout seo.php / seo-schema.php / login-hide.php in place of hardcoded literals.

### Changed
- `inc/seo.php`, `inc/seo-schema.php`, `inc/login-hide.php` refactored to read all site-identity values from `sn_setting()` instead of PHP literals. 12 hardcoded JL-specific values removed across the three files.
- Filter compat layer preserved: existing `apply_filters()` hooks (`sn_twitter_handle`, `sn_schema_same_as`, `sn_og_image_dimensions`) continue to work as override stack on top of stored settings. Pattern: `apply_filters('sn_X', sn_setting('path', $fallback))`.

### Notes
- **Live site output is byte-identical post-upgrade.** The activation migration seeds the JL-specific values into `wp_options['sn_settings']` so emitted meta tags match v1.7.0 exactly. Verifiable: diff a page's `<head>` pre/post-upgrade returns empty.
- **Generic defaults for fresh installs.** On any non-juanlentino.com host, the migration sets only the `sn_settings_migrated_v1` flag without seeding values. `sn_settings_defaults()` provides generic fallbacks pulled from `get_bloginfo()`.
- **Out of scope:** per-post settings UI (noindex toggle, custom meta description override per post), security toggles UI (xmlrpc, rest user lockdown, etc.), JS-driven add/remove for the sameAs list. Each becomes its own future phase.
- **Prereq for Phase 13 cutover** (v2.0.0, deactivates TSF + wps-hide-login). After v1.8.0, the plugin owns all site-identity emission with configurable values.
```

- [ ] **Step 4: Commit + tag + push**

```bash
cd /Users/juanlentino/Projects/signal-and-noise-tools && \
git add signal-and-noise-tools.php CHANGELOG.md && \
git commit -m "v1.8.0: settings layer + Identity admin UI (Phase 11.5)

Lifts all site-identity values out of v1.5.0-v1.7.0 PHP literals
into wp_options['sn_settings'] (single row, 5 categories). Adds an
Identity tab to Appearance → Signal & Noise with grouped form
fields for all settings. Existing apply_filters() hooks preserved
as override stack.

One-time activation migration seeds JL legacy values into wp_options
on first v1.8.0 activation, hostname-gated to juanlentino.com. Lazy
admin_init fallback covers SSH-based deploys. After migration,
wp_options is the source of truth — values are user-editable via
the Identity tab.

Live site output stays byte-identical post-upgrade. Fresh installs
on other hosts get generic WP-built-in defaults until the form is
filled.

Prereq for Phase 13 cutover (v2.0.0)." && \
git push origin main && \
git tag -a v1.8.0 -m "v1.8.0 — settings layer + Identity admin UI (Phase 11.5)" && \
git push origin v1.8.0
```

- [ ] **Step 5: Watch the deploy fire**

```bash
sleep 10 && \
gh run list --repo juanlentino/signal-and-noise-tools --workflow=deploy.yml --limit 1
```

Expected: a run in `queued` or `in_progress` triggered by tag `v1.8.0`.

---

## Task 8: Post-deploy verification (automatic via WP-CLI + curl diff)

**Files:** N/A (verification only)

- [ ] **Step 1: Watch deploy to completion**

```bash
RUN_ID=$(gh run list --repo juanlentino/signal-and-noise-tools --workflow=deploy.yml --limit 1 --json databaseId -q '.[0].databaseId') && \
gh run watch "$RUN_ID" --repo juanlentino/signal-and-noise-tools --exit-status 2>&1 | tail -10
```

Expected: workflow `success`. Three steps green (Configure SSH, SSH git checkout, Purge Cloudflare cache).

- [ ] **Step 2: Regenerate SSH key (if needed for verification)**

Phase 2c handoff says local copies were wiped after the previous session. Regenerate the deploy key for use this session:

```bash
ls /tmp/sn-tools-deploy_ed25519 2>/dev/null || {
  ssh-keygen -t ed25519 -f /tmp/sn-tools-deploy_ed25519 -N "" -C "sn-tools-verify-v1.8.0" -q
  echo "Generated new keypair. To re-use, add the public key to the sn-plugin user on Cloudways via the dashboard, then re-run this task. For one-off verification, ask the user to paste the existing GHA-stored private key here, OR use the WP REST API path in Step 4 instead (no SSH needed)."
}
```

If SSH is unavailable for this session, Step 4 (WP REST) is sufficient on its own — skip Steps 3 & 5.

- [ ] **Step 3: Verify wp_options sn_settings exists with JL values (SSH+WP-CLI path)**

```bash
ssh -i /tmp/sn-tools-deploy_ed25519 -o UserKnownHostsFile=/tmp/sn-tools-known_hosts sn-plugin@157.245.116.64 \
  'cd /home/master/applications/nffqxsrgxz/public_html && wp option get sn_settings --format=json | python3 -m json.tool' \
  2>&1 | grep -v 'WARNING\|store now\|post-quantum\|See https'
```

Expected: pretty-printed JSON matching the JL legacy values (twitter_handle = `@juan_lentino`, sameAs has 3 URLs, etc.).

Also verify the migrated flag is set:

```bash
ssh -i /tmp/sn-tools-deploy_ed25519 -o UserKnownHostsFile=/tmp/sn-tools-known_hosts sn-plugin@157.245.116.64 \
  'cd /home/master/applications/nffqxsrgxz/public_html && wp option get sn_settings_migrated_v1' \
  2>&1 | grep -v 'WARNING\|store now\|post-quantum\|See https'
```

Expected: `1`.

- [ ] **Step 4: Verify byte-identical <head> diff (WP REST path — no SSH needed)**

Force a cache purge first so we get fresh HTML:

```bash
auth=$(printf '%s:%s' 'juanlentino' 'REDACTED-REVOKED-2026-05-17' | base64)
curl -sS -X POST 'https://juanlentino.com/wp-json/signal-noise/v1/purge-cache' \
  -H "Authorization: Basic $auth" -H 'Content-Length: 0' -o /dev/null -w '%{http_code}\n'
sleep 3
```

Then capture fresh post-deploy snapshots + diff:

```bash
mkdir -p /tmp/sn-v18-post
for slug in pricing-in-dollars-from-argentina musics-billion-dollar-metadata-problem; do
  curl -sS "https://juanlentino.com/notes/${slug}/" 2>/dev/null > "/tmp/sn-v18-post/${slug}.html"
done

# Extract just the <head> sections + diff
for slug in pricing-in-dollars-from-argentina musics-billion-dollar-metadata-problem; do
  echo "=== Diff <head> for $slug ==="
  diff \
    <(sed -n '/<head>/,/<\/head>/p' /tmp/sn-v18-baseline/${slug}.html) \
    <(sed -n '/<head>/,/<\/head>/p' /tmp/sn-v18-post/${slug}.html)
done
```

Expected: zero diff output (empty diff → byte-identical `<head>`).

If there's diff output, INSPECT it: most acceptable differences would be timestamps in the `?v=` cache-buster on the OG card URL. Anything else (missing meta tags, changed values) is a real regression — STOP and diagnose before continuing.

- [ ] **Step 5: Test editing-and-saving a field via WP-CLI (proves persistence)**

```bash
ssh -i /tmp/sn-tools-deploy_ed25519 -o UserKnownHostsFile=/tmp/sn-tools-known_hosts sn-plugin@157.245.116.64 'bash -s' 2>&1 <<'EOF' | grep -v 'WARNING\|store now\|post-quantum\|See https'
set -euo pipefail
cd /home/master/applications/nffqxsrgxz/public_html

echo "=== BEFORE: twitter handle setting ==="
wp option get sn_settings --format=json | python3 -c "import sys,json; print(json.load(sys.stdin)['social']['twitter_handle'])"

echo "=== UPDATE: set twitter handle to @verify-test temporarily ==="
wp eval '$s = get_option("sn_settings"); $s["social"]["twitter_handle"] = "@verify-test"; update_option("sn_settings", $s);'

echo "=== Verify HTML emission picks up the change ==="
curl -sS "https://juanlentino.com/notes/pricing-in-dollars-from-argentina/?cb=$(date +%s)" 2>/dev/null | grep -oE '<meta name="twitter:site"[^>]*content="[^"]+"'

echo "=== RESTORE: set twitter handle back ==="
wp eval '$s = get_option("sn_settings"); $s["social"]["twitter_handle"] = "@juan_lentino"; update_option("sn_settings", $s);'

echo "=== AFTER: twitter handle setting ==="
wp option get sn_settings --format=json | python3 -c "import sys,json; print(json.load(sys.stdin)['social']['twitter_handle'])"
EOF
```

Expected:
- BEFORE: `@juan_lentino`
- AFTER UPDATE: HTML grep returns `<meta name="twitter:site" content="@verify-test"`
- AFTER: `@juan_lentino` (restored)

This proves both the read path (sn_setting reads stored value) and write path (update_option persists; next request reflects change).

- [ ] **Step 6: Test filter override still works**

The filter compat layer should still let code override stored settings. The cleanest test path: temporarily drop a filter into a must-use plugin, hit a page, verify, remove.

If SSH is available:

```bash
ssh -i /tmp/sn-tools-deploy_ed25519 -o UserKnownHostsFile=/tmp/sn-tools-known_hosts sn-plugin@157.245.116.64 'bash -s' 2>&1 <<'EOF' | grep -v 'WARNING\|store now\|post-quantum\|See https'
set -euo pipefail
WP_DIR=/home/master/applications/nffqxsrgxz/public_html

echo "=== INSTALL test mu-plugin filter override ==="
cat > "$WP_DIR/wp-content/mu-plugins/sn-test-filter-override.php" <<'MUPLUGIN'
<?php
add_filter( 'sn_twitter_handle', function( $value ) {
	return '@filter-override-test';
} );
MUPLUGIN

echo "=== Verify HTML emission uses filter value, not stored value ==="
curl -sS "https://juanlentino.com/notes/pricing-in-dollars-from-argentina/?cb=$(date +%s)" 2>/dev/null | grep -oE '<meta name="twitter:site"[^>]*content="[^"]+"'

echo "=== REMOVE test mu-plugin ==="
rm "$WP_DIR/wp-content/mu-plugins/sn-test-filter-override.php"

echo "=== Verify HTML emission reverts to stored value ==="
sleep 2
curl -sS "https://juanlentino.com/notes/pricing-in-dollars-from-argentina/?cb=$(date +%s)" 2>/dev/null | grep -oE '<meta name="twitter:site"[^>]*content="[^"]+"'
EOF
```

Expected:
- With filter installed: `<meta name="twitter:site" content="@filter-override-test"`
- After filter removed: `<meta name="twitter:site" content="@juan_lentino"`

If SSH is unavailable for this session, mark Step 6 as deferred + log it as a manual verification task. The filter compat path is exercised continuously in production (it's the same `apply_filters` call site for both filter and no-filter paths) so the risk of regression without explicit test is low.

- [ ] **Step 7: Verify Identity tab renders + form submission works**

Open `https://juanlentino.com/wp-admin/themes.php?page=sn-theme-options&tab=identity` in a browser. Verify:
- All 5 sections appear (Identity, Social, OG, Login, SEO Copy)
- All fields pre-populated with the JL legacy values
- Twitter handle field shows `@juan_lentino`
- sameAs section shows 3 URL rows + 1 empty trailing row
- The Save button at the bottom is clickable

Click Save (no changes). Verify the "Identity settings saved." notice appears (or "No changes to save." — either is correct outcome).

Refresh. Verify all fields still show the JL legacy values.

- [ ] **Step 8: Final acceptance criteria check**

Walk through the spec's 8 acceptance criteria. Tick each that passed:

- [ ] AC1: Plugin v1.8.0 activates without errors. `wp_options['sn_settings']` exists with JL values. `sn_settings_migrated_v1` flag set to `'1'`. (Verified Steps 3-5)
- [ ] AC2: Page `<head>` byte-identical to pre-deploy. (Verified Step 4)
- [ ] AC3: Identity tab renders with all fields pre-populated. (Verified Step 7)
- [ ] AC4: Editing → saving → reloading persists. (Verified Step 5)
- [ ] AC5: Sanitization works. (Implicit — sn_settings_save sanitizes each field; can manually test via curl if desired)
- [ ] AC6: Filter overrides still work. (Verified Step 6 if SSH available)
- [ ] AC7: No site-identity string literals remain. Verify via grep:
  ```bash
  cd /Users/juanlentino/Projects/signal-and-noise-tools && \
  grep -rnE 'Juan Lentino|@juan_lentino|Music Production|Working notes|verification problem|x\.com/juan_lentino|instagram\.com/juan_lentino|linkedin\.com/in/juanlentino' inc/seo.php inc/seo-schema.php inc/login-hide.php
  ```
  Expected: zero matches.
- [ ] AC8: Fresh-install simulation — skip live verification (requires a non-juanlentino.com host); covered by hostname-guard logic review.

---

## Rollback paths

**If v1.8.0 activates but the live site renders with empty/wrong OG/Twitter values:**
- Most likely cause: the activation migration didn't fire (SSH deploy bypassed `register_activation_hook`) AND the `admin_init` lazy fallback hasn't run yet (no admin pageview).
- Fix: visit `wp-admin/` once. The `admin_init` handler runs, calls `sn_settings_lazy_migration_check`, seeds the JL legacy values.
- If still wrong: SSH in and manually run the migration:
  ```bash
  wp eval 'sn_settings_seed_legacy_values();'
  ```

**If a regression appears in `<head>` output (Step 4 diff has unexpected content):**
- The hardcoded → setting refactor of `inc/seo.php` / `inc/seo-schema.php` introduced a bug.
- Roll back: `git revert <Task 7 commit SHA>` + `git revert <Task 3 commit SHA>` etc. Or: push a tag `v1.8.1` that reverts to v1.7.0 file contents but bumps the version.
- More pragmatic: edit `wp_options['sn_settings']` directly to fix bad values without redeploy.

**If the Identity tab doesn't render (PHP error on the admin page):**
- Most likely: a missing close-tag or syntax error in the new tab-render block (Task 6).
- Fix: SSH in, view error log:
  ```bash
  tail /home/master/applications/nffqxsrgxz/private_html/error.log
  ```
- Hotfix: push `v1.8.1` with the fix.

---

## Out of scope (per spec)

- Per-post settings UI (noindex toggle, custom meta description override)
- Security toggles UI (sn_security_*)
- JS-driven add/remove for sameAs list
- Multi-locale support
- Settings export/import
- Network admin / multi-site support
