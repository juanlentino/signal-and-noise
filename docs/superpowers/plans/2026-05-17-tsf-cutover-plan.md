# Phase 13 TSF Cutover Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace The SEO Framework plugin with the Signal & Noise companion plugin's emission, ship theme v8.5.5 + plugin v2.0.0, deactivate TSF in wp-admin, and verify clean cutover.

**Architecture:** Theme adds `title-tag` support so WP-native `<title>` emission takes over. Plugin gains six new gated emitters (`document_title_parts` filter, WebPage/CollectionPage/BreadcrumbList JSON-LD, music-specific Person, sitemap 301 redirect, Last-Modified header) that stay dormant while TSF is active and activate the instant TSF is deactivated. Cutover happens in a single ~45min session.

**Tech Stack:** PHP 8.0+, WordPress 6.x core (7.0 ships in 3 days), no JS bundling, no build step. Live verification via curl + bash. Two git repos: theme worktree at `/Users/juanlentino/projects/signal-and-noise/.claude/worktrees/nice-goldstine-063551/`, plugin at `/Users/juanlentino/projects/signal-and-noise-tools/`.

**Verification model:** No PHPUnit harness in this codebase. Each plugin task uses `php -l` for syntax check; functional verification happens at deploy time via the curl-based verification script in [the spec](2026-05-17-tsf-cutover-design.md#verification-checklist) (Section 6). Intermediate commits are syntax-checked but not deployed individually; only the final tagged release auto-deploys.

---

## Task 1: Theme v8.5.5 — title-tag support

**Files:**
- Modify: `/Users/juanlentino/projects/signal-and-noise/.claude/worktrees/nice-goldstine-063551/inc/setup.php`
- Modify: `/Users/juanlentino/projects/signal-and-noise/.claude/worktrees/nice-goldstine-063551/style.css`
- Modify: `/Users/juanlentino/projects/signal-and-noise/.claude/worktrees/nice-goldstine-063551/CHANGELOG.md`

- [ ] **Step 1: Read inc/setup.php to find the existing `after_setup_theme` callback**

Run: `cat /Users/juanlentino/projects/signal-and-noise/.claude/worktrees/nice-goldstine-063551/inc/setup.php`

Look for the function registered to `after_setup_theme` (likely `sn_setup` or similar) and identify where to insert `add_theme_support('title-tag')`. If no `after_setup_theme` callback exists, the entire add is the addition.

- [ ] **Step 2: Add `add_theme_support('title-tag')` in the right place**

Use Edit tool to add this line inside the existing `after_setup_theme` callback:

```php
add_theme_support( 'title-tag' );
```

Adjacent to any other `add_theme_support()` calls already present. If none exist, create the callback:

```php
add_action( 'after_setup_theme', function() {
    add_theme_support( 'title-tag' );
} );
```

- [ ] **Step 3: PHP syntax check**

Run: `php -l /Users/juanlentino/projects/signal-and-noise/.claude/worktrees/nice-goldstine-063551/inc/setup.php`

Expected: `No syntax errors detected in ...`

- [ ] **Step 4: Bump theme version in style.css**

Use Edit tool on `/Users/juanlentino/projects/signal-and-noise/.claude/worktrees/nice-goldstine-063551/style.css`:

Replace `Version: 8.5.4` with `Version: 8.5.5`.

- [ ] **Step 5: Add CHANGELOG entry**

Use Edit tool to insert at the top (above `## [8.5.4]`) of `/Users/juanlentino/projects/signal-and-noise/.claude/worktrees/nice-goldstine-063551/CHANGELOG.md`:

```markdown
## [8.5.5] - 2026-05-17

### Added
- **`add_theme_support('title-tag')` in [inc/setup.php](inc/setup.php).** Block themes don't auto-declare title-tag support (verified against [WordPress/wp-includes/theme.php on trunk](https://raw.githubusercontent.com/WordPress/WordPress/master/wp-includes/theme.php) — no auto-declaration logic exists for block themes). Until now, The SEO Framework plugin was the only source of the `<title>` tag in `<head>`. With Phase 13 TSF cutover landing (plugin v2.0.0), WP core's `_wp_render_title_tag()` needs explicit theme support to take over title emission. Companion to plugin v2.0.0's `document_title_parts` filter which controls the title format.

### Why this matters
- After TSF is deactivated (companion plugin v2.0.0 cutover), the page would lose its `<title>` tag entirely without this support declaration. That's an SEO catastrophe — title is one of the most-weighted on-page signals.
- The plugin's `document_title_parts` filter cooperates with WP-native title emission rather than fighting it; both pieces together produce the same `<page name> — <site name>` format TSF was emitting before.

### Notes
- **PATCH bump within `8.5.x`.** From a user-visible perspective the page still has a `<title>` tag after this change — no new capability, no behavior shift. Pure infrastructure restoration of a capability TSF was previously providing externally. Cap headroom: 4/7 → 5/7 patches on 8.5.x.
- Companion plugin v2.0.0 ships in the same session.

```

- [ ] **Step 6: Commit theme v8.5.5**

Run from the theme worktree:

```bash
cd /Users/juanlentino/projects/signal-and-noise/.claude/worktrees/nice-goldstine-063551
git add inc/setup.php style.css CHANGELOG.md
git commit -m "$(cat <<'EOF'
v8.5.5: add_theme_support('title-tag') for Phase 13 TSF cutover

Block themes don't auto-declare title-tag support (verified against
wp-includes/theme.php on trunk — no auto-declaration for block themes).
Until now, TSF was the only source of <title> in <head>. Phase 13
deactivates TSF; WP core's _wp_render_title_tag() needs this support
declaration to take over.

PATCH bump: user-visible behavior unchanged (page still has <title>).
Pure infrastructure restoration. Companion to plugin v2.0.0 shipping
in the same session.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

- [ ] **Step 7: Push commit + tag**

```bash
git push origin HEAD:main
git tag -a v8.5.5 -m "v8.5.5 — add_theme_support('title-tag') for Phase 13 TSF cutover"
git push origin v8.5.5
```

- [ ] **Step 8: Wait for deploy + verify theme version**

Wait 45 seconds for auto-deploy. Then:

```bash
curl -sS "https://juanlentino.com/wp-content/themes/signal-and-noise/style.css" | head -20 | grep "^Version:"
```

Expected: `Version: 8.5.5`

If still 8.5.4 after 60s, check GHA: `gh run list --repo juanlentino/signal-and-noise --limit 3`.

---

## Task 2: Plugin — `document_title_parts` filter

**Files:**
- Modify: `/Users/juanlentino/projects/signal-and-noise-tools/inc/seo.php`

- [ ] **Step 1: Read current seo.php structure**

Run: `wc -l /Users/juanlentino/projects/signal-and-noise-tools/inc/seo.php` (expect ~291) and review the file structure. We're appending the filter after the existing emitters.

- [ ] **Step 2: Add the filter after the description emitter (line ~174)**

Use Edit tool to insert immediately after the existing `}, 2 );` line closing the meta description emitter (around line 174), BEFORE the OG/Twitter comment block:

```php
/**
 * Title tag format — WP-native emission via document_title_parts filter.
 *
 * Cooperates with WP core's _wp_render_title_tag() (active because the
 * theme declares add_theme_support('title-tag') as of theme v8.5.5).
 * Splits our pre-built "Page Name — Site Name" format from
 * sn_seo_meta_for_current_view() into the title/site parts WP expects.
 *
 * Gated on TSF: while TSF is active, TSF emits the <title> itself and
 * we let it. The instant TSF is deactivated, this filter takes over.
 *
 * Added in plugin v2.0.0 (Phase 13 TSF cutover).
 */
add_filter( 'document_title_parts', function( $parts ) {
	if ( function_exists( 'the_seo_framework' ) ) {
		return $parts;
	}

	list( $title, , ) = sn_seo_meta_for_current_view();
	if ( '' === $title ) {
		return $parts;
	}

	// sn_seo_meta_for_current_view returns "Page — Site"; split it.
	$segments = explode( ' — ', $title, 2 );
	$parts['title'] = $segments[0];
	if ( isset( $segments[1] ) ) {
		$parts['site'] = $segments[1];
	}

	return $parts;
}, 10, 1 );

```

- [ ] **Step 3: PHP syntax check**

Run: `php -l /Users/juanlentino/projects/signal-and-noise-tools/inc/seo.php`

Expected: `No syntax errors detected in ...`

- [ ] **Step 4: Commit (intermediate)**

```bash
cd /Users/juanlentino/projects/signal-and-noise-tools
git add inc/seo.php
git commit -m "Phase 13: add document_title_parts filter (TSF-gated)"
```

---

## Task 3: Plugin — JSON-LD WebPage builder

**Files:**
- Modify: `/Users/juanlentino/projects/signal-and-noise-tools/inc/seo-schema.php`

- [ ] **Step 1: Read seo-schema.php to confirm builder pattern**

Run: `cat /Users/juanlentino/projects/signal-and-noise-tools/inc/seo-schema.php | head -100`

Builders are pure functions returning associative arrays. Pattern is `sn_schema_<type>()` returning `array(...)` with `@type`, `@id`, etc.

- [ ] **Step 2: Add the WebPage builder before the emitter**

Use Edit tool to insert before line 158 (`add_action( 'wp_head', function() {`):

```php
/**
 * Build the WebPage schema for the current singular view.
 * Returns null if not on a singular (use CollectionPage or skip).
 *
 * Added in v2.0.0 (Phase 13 TSF cutover).
 */
function sn_schema_webpage() {
	if ( ! is_singular() ) {
		return null;
	}
	$post = get_queried_object();
	if ( ! $post ) {
		return null;
	}

	$permalink = get_permalink( $post );
	$name      = wp_strip_all_tags( get_the_title( $post ) );

	// Description: per-post _sn_meta_description override > excerpt > '' (omit).
	$description = sn_schema_article_description( $post );

	$webpage = array(
		'@type'      => 'WebPage',
		'@id'        => $permalink,
		'url'        => $permalink,
		'name'       => $name,
		'inLanguage' => str_replace( '_', '-', sn_setting( 'identity.locale', 'en_US' ) ),
		'isPartOf'   => array(
			'@id' => home_url( '/' ) . '#/schema/WebSite',
		),
		'breadcrumb' => array(
			'@id' => $permalink . '#breadcrumb',
		),
	);

	if ( '' !== $description ) {
		$webpage['description'] = $description;
	}

	return $webpage;
}

```

- [ ] **Step 3: PHP syntax check**

Run: `php -l /Users/juanlentino/projects/signal-and-noise-tools/inc/seo-schema.php`

Expected: `No syntax errors detected in ...`

- [ ] **Step 4: Commit (intermediate)**

```bash
git add inc/seo-schema.php
git commit -m "Phase 13: add sn_schema_webpage() builder"
```

---

## Task 4: Plugin — JSON-LD CollectionPage builder

**Files:**
- Modify: `/Users/juanlentino/projects/signal-and-noise-tools/inc/seo-schema.php`

- [ ] **Step 1: Add the CollectionPage builder after `sn_schema_webpage()`**

Use Edit tool to insert immediately after the WebPage builder block from Task 3:

```php
/**
 * Build the CollectionPage schema for /notes archive views.
 * Returns null if not on a CollectionPage-appropriate view.
 *
 * Added in v2.0.0 (Phase 13 TSF cutover).
 */
function sn_schema_collection_page() {
	$url  = '';
	$name = '';

	if ( is_home() || is_page( 'notes' ) ) {
		$url  = home_url( '/notes/' );
		$name = sn_setting( 'seo_copy.notes_title', 'Notes' );
	} else {
		return null;
	}

	return array(
		'@type'      => 'CollectionPage',
		'@id'        => $url,
		'url'        => $url,
		'name'       => $name,
		'inLanguage' => str_replace( '_', '-', sn_setting( 'identity.locale', 'en_US' ) ),
		'isPartOf'   => array(
			'@id' => home_url( '/' ) . '#/schema/WebSite',
		),
		'breadcrumb' => array(
			'@id' => $url . '#breadcrumb',
		),
	);
}

```

- [ ] **Step 2: PHP syntax check**

Run: `php -l /Users/juanlentino/projects/signal-and-noise-tools/inc/seo-schema.php`

Expected: `No syntax errors detected in ...`

- [ ] **Step 3: Commit (intermediate)**

```bash
git add inc/seo-schema.php
git commit -m "Phase 13: add sn_schema_collection_page() builder"
```

---

## Task 5: Plugin — JSON-LD BreadcrumbList builder

**Files:**
- Modify: `/Users/juanlentino/projects/signal-and-noise-tools/inc/seo-schema.php`

- [ ] **Step 1: Add the BreadcrumbList builder after `sn_schema_collection_page()`**

Use Edit tool to insert immediately after the CollectionPage builder block from Task 4:

```php
/**
 * Build the BreadcrumbList schema for the current view.
 * Returns null on the front page (no useful trail).
 *
 * Trail order: Home → (parent page chain if any) → current page.
 * For singular posts, post type archive is NOT in the trail (matches
 * WP's default breadcrumb conventions). Posts are: Home → Post Title.
 *
 * Added in v2.0.0 (Phase 13 TSF cutover). Will likely be removed in a
 * post-WP-7.0 refactor once the native Breadcrumbs block is added to
 * templates and emits its own BreadcrumbList structured data.
 */
function sn_schema_breadcrumb_list() {
	if ( is_front_page() ) {
		return null;
	}

	$home  = home_url( '/' );
	$items = array(
		array(
			'@type'    => 'ListItem',
			'position' => 1,
			'item'     => $home,
			'name'     => sn_setting( 'identity.site_name', get_bloginfo( 'name' ) ),
		),
	);

	$position = 2;
	$base_id  = '';

	if ( is_singular() ) {
		$post    = get_queried_object();
		$base_id = $post ? get_permalink( $post ) : '';

		// Walk parent chain (for hierarchical pages).
		if ( $post && $post->post_parent ) {
			$ancestors = array_reverse( get_post_ancestors( $post ) );
			foreach ( $ancestors as $ancestor_id ) {
				$items[] = array(
					'@type'    => 'ListItem',
					'position' => $position++,
					'item'     => get_permalink( $ancestor_id ),
					'name'     => wp_strip_all_tags( get_the_title( $ancestor_id ) ),
				);
			}
		}

		// Current page (no `item` on the final entry per schema.org best practice).
		if ( $post ) {
			$items[] = array(
				'@type'    => 'ListItem',
				'position' => $position,
				'name'     => wp_strip_all_tags( get_the_title( $post ) ),
			);
		}
	} elseif ( is_home() || is_page( 'notes' ) ) {
		$base_id = home_url( '/notes/' );
		$items[] = array(
			'@type'    => 'ListItem',
			'position' => $position,
			'name'     => sn_setting( 'seo_copy.notes_title', 'Notes' ),
		);
	} else {
		return null;
	}

	return array(
		'@type'           => 'BreadcrumbList',
		'@id'             => $base_id . '#breadcrumb',
		'itemListElement' => $items,
	);
}

```

- [ ] **Step 2: PHP syntax check**

Run: `php -l /Users/juanlentino/projects/signal-and-noise-tools/inc/seo-schema.php`

Expected: `No syntax errors detected in ...`

- [ ] **Step 3: Commit (intermediate)**

```bash
git add inc/seo-schema.php
git commit -m "Phase 13: add sn_schema_breadcrumb_list() builder"
```

---

## Task 6: Plugin — wire new JSON-LD into emitter with TSF gate

**Files:**
- Modify: `/Users/juanlentino/projects/signal-and-noise-tools/inc/seo-schema.php`

- [ ] **Step 1: Update the wp_head emitter to include the new schemas (gated)**

The existing emitter (around line 158 before our additions) currently builds `$graph = array( sn_schema_person(), sn_schema_website() )` and conditionally appends `sn_schema_article()`.

Use Edit tool to replace the existing emitter block. Find the existing block:

```php
add_action( 'wp_head', function() {
	// Only emit on front page, /notes, /provenance, and any singular content.
	if ( ! is_front_page() && ! is_home() && ! is_singular() ) {
		return;
	}

	$graph = array(
		sn_schema_person(),
		sn_schema_website(),
	);

	$article = sn_schema_article();
	if ( $article ) {
		$graph[] = $article;
	}

	$payload = array(
		'@context' => 'https://schema.org',
		'@graph'   => $graph,
	);

	echo '<script type="application/ld+json">' . wp_json_encode( $payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
}, 5 );
```

Replace with:

```php
add_action( 'wp_head', function() {
	// Only emit on front page, /notes, /provenance, and any singular content.
	if ( ! is_front_page() && ! is_home() && ! is_singular() ) {
		return;
	}

	$graph = array(
		sn_schema_person(),
		sn_schema_website(),
	);

	$article = sn_schema_article();
	if ( $article ) {
		$graph[] = $article;
	}

	// v2.0.0 (Phase 13 TSF cutover): when TSF is inactive, also emit
	// WebPage/CollectionPage and BreadcrumbList. These replace TSF's
	// equivalent schema emission. Gate keeps them dormant while TSF
	// is active to avoid duplicate JSON-LD entries on rollback.
	if ( ! function_exists( 'the_seo_framework' ) ) {
		$webpage = sn_schema_webpage();
		if ( $webpage ) {
			$graph[] = $webpage;
		}

		$collection = sn_schema_collection_page();
		if ( $collection ) {
			$graph[] = $collection;
		}

		$breadcrumb = sn_schema_breadcrumb_list();
		if ( $breadcrumb ) {
			$graph[] = $breadcrumb;
		}
	}

	$payload = array(
		'@context' => 'https://schema.org',
		'@graph'   => $graph,
	);

	echo '<script type="application/ld+json">' . wp_json_encode( $payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
}, 5 );
```

- [ ] **Step 2: PHP syntax check**

Run: `php -l /Users/juanlentino/projects/signal-and-noise-tools/inc/seo-schema.php`

Expected: `No syntax errors detected in ...`

- [ ] **Step 3: Commit (intermediate)**

```bash
git add inc/seo-schema.php
git commit -m "Phase 13: wire WebPage/CollectionPage/BreadcrumbList into @graph emitter (TSF-gated)"
```

---

## Task 7: Plugin — music-specific Person extensions

**Files:**
- Modify: `/Users/juanlentino/projects/signal-and-noise-tools/inc/seo-schema.php`

- [ ] **Step 1: Extend `sn_schema_person()` with `jobTitle` and `knowsAbout`**

Use Edit tool to replace the existing return array in `sn_schema_person()` (around line 46-52):

Find:

```php
	return array(
		'@type'  => 'Person',
		'@id'    => $home . '#/schema/Person',
		'name'   => $name,
		'url'    => $home,
		'sameAs' => array_values( $same_as ),
	);
```

Replace with:

```php
	return array(
		'@type'      => 'Person',
		'@id'        => $home . '#/schema/Person',
		'name'       => $name,
		'url'        => $home,
		'sameAs'     => array_values( $same_as ),
		'jobTitle'   => sn_setting( 'identity.job_title', 'Music Producer' ),
		'knowsAbout' => (array) sn_setting(
			'identity.knows_about',
			array(
				'Music Production',
				'Audio Engineering',
				'Provenance',
				'Music Industry',
			)
		),
	);
```

- [ ] **Step 2: PHP syntax check**

Run: `php -l /Users/juanlentino/projects/signal-and-noise-tools/inc/seo-schema.php`

Expected: `No syntax errors detected in ...`

- [ ] **Step 3: Commit (intermediate)**

```bash
git add inc/seo-schema.php
git commit -m "Phase 13: extend Person schema with jobTitle + knowsAbout (music-specific)"
```

---

## Task 8: Plugin — create sitemap-redirect.php

**Files:**
- Create: `/Users/juanlentino/projects/signal-and-noise-tools/inc/sitemap-redirect.php`

- [ ] **Step 1: Write the new file**

Use Write tool to create `/Users/juanlentino/projects/signal-and-noise-tools/inc/sitemap-redirect.php`:

```php
<?php
/**
 * Signal & Noise Tools — Sitemap URL redirect.
 *
 * After Phase 13 TSF cutover, the canonical sitemap moves from TSF's
 * /sitemap.xml route to WP core's /wp-sitemap.xml. Google Search
 * Console may have /sitemap.xml registered; the 301 here preserves
 * crawl continuity by redirecting old requests to the new location.
 *
 * Routes covered:
 *   /sitemap.xml          — TSF's main sitemap
 *   /sitemap_index.xml    — TSF's index variant
 *   /sitemap.xsl          — TSF's stylesheet (404s harmlessly without redirect, but explicit is cleaner)
 *
 * Gated on TSF: while TSF is active, its own routes serve directly and
 * this redirect doesn't fire. The instant TSF deactivates, this kicks in.
 *
 * Added in v2.0.0 (Phase 13 TSF cutover, 2026-05-17).
 *
 * @package SignalNoiseTools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', function() {
	// While TSF is active, let TSF serve its own sitemap routes.
	if ( function_exists( 'the_seo_framework' ) ) {
		return;
	}

	if ( ! isset( $_SERVER['REQUEST_URI'] ) ) {
		return;
	}

	$path = parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH );
	if ( ! is_string( $path ) ) {
		return;
	}

	$path = trim( $path, '/' );

	$legacy_routes = array(
		'sitemap.xml',
		'sitemap_index.xml',
		'sitemap.xsl',
	);

	if ( ! in_array( $path, $legacy_routes, true ) ) {
		return;
	}

	// 301 to WP core's sitemap index.
	wp_redirect( home_url( '/wp-sitemap.xml' ), 301 );
	exit;
}, 1 );
```

- [ ] **Step 2: PHP syntax check**

Run: `php -l /Users/juanlentino/projects/signal-and-noise-tools/inc/sitemap-redirect.php`

Expected: `No syntax errors detected in ...`

- [ ] **Step 3: Commit (intermediate)**

```bash
git add inc/sitemap-redirect.php
git commit -m "Phase 13: add inc/sitemap-redirect.php (301 /sitemap.xml → /wp-sitemap.xml, TSF-gated)"
```

---

## Task 9: Plugin — register sitemap-redirect.php in main plugin

**Files:**
- Modify: `/Users/juanlentino/projects/signal-and-noise-tools/signal-and-noise-tools.php`

- [ ] **Step 1: Read main plugin file to find require_once block**

Run: `grep -n "require_once" /Users/juanlentino/projects/signal-and-noise-tools/signal-and-noise-tools.php`

Identify the cluster of `require_once __DIR__ . '/inc/...'` lines. We add a new one for `sitemap-redirect.php`, adjacent to the existing `sitemap.php` require if present.

- [ ] **Step 2: Add the require_once line**

Use Edit tool. Find the line:

```php
require_once __DIR__ . '/inc/sitemap.php';
```

Replace with:

```php
require_once __DIR__ . '/inc/sitemap.php';
require_once __DIR__ . '/inc/sitemap-redirect.php';
```

If `sitemap.php` is not present, add `require_once __DIR__ . '/inc/sitemap-redirect.php';` at the bottom of the existing `require_once` cluster.

- [ ] **Step 3: PHP syntax check**

Run: `php -l /Users/juanlentino/projects/signal-and-noise-tools/signal-and-noise-tools.php`

Expected: `No syntax errors detected in ...`

- [ ] **Step 4: Commit (intermediate)**

```bash
git add signal-and-noise-tools.php
git commit -m "Phase 13: register sitemap-redirect.php in main plugin loader"
```

---

## Task 10: Plugin — Last-Modified header + If-Modified-Since 304

**Files:**
- Modify: `/Users/juanlentino/projects/signal-and-noise-tools/inc/seo.php`

- [ ] **Step 1: Add the `template_redirect` hook at the bottom of seo.php**

Use Edit tool to append BEFORE the closing `}` of the file (or at the very end, since PHP files don't need a closing tag):

```php

/**
 * Last-Modified header + If-Modified-Since 304 handling on singulars.
 *
 * TSF emits Last-Modified itself when active; gate keeps us dormant
 * until TSF deactivates. Returns 304 Not Modified when the request's
 * If-Modified-Since timestamp matches the post's modified time — saves
 * crawl budget for Google and friends.
 *
 * Hooked at template_redirect (after the query is set, before output
 * starts) so we can still send headers + exit.
 *
 * Added in v2.0.0 (Phase 13 TSF cutover).
 */
add_action( 'template_redirect', function() {
	if ( function_exists( 'the_seo_framework' ) ) {
		return;
	}

	if ( ! is_singular() ) {
		return;
	}

	$post = get_queried_object();
	if ( ! $post ) {
		return;
	}

	$modified_gmt   = get_post_modified_time( 'U', true, $post );
	if ( ! $modified_gmt ) {
		return;
	}

	$modified_http = gmdate( 'D, d M Y H:i:s', $modified_gmt ) . ' GMT';
	header( 'Last-Modified: ' . $modified_http );

	// 304 if client's If-Modified-Since is >= ours.
	if ( ! empty( $_SERVER['HTTP_IF_MODIFIED_SINCE'] ) ) {
		$client_since = strtotime( wp_unslash( $_SERVER['HTTP_IF_MODIFIED_SINCE'] ) );
		if ( $client_since && $client_since >= $modified_gmt ) {
			header( $_SERVER['SERVER_PROTOCOL'] . ' 304 Not Modified', true, 304 );
			exit;
		}
	}
}, 10 );
```

- [ ] **Step 2: PHP syntax check**

Run: `php -l /Users/juanlentino/projects/signal-and-noise-tools/inc/seo.php`

Expected: `No syntax errors detected in ...`

- [ ] **Step 3: Commit (intermediate)**

```bash
git add inc/seo.php
git commit -m "Phase 13: add Last-Modified header + If-Modified-Since 304 (TSF-gated)"
```

---

## Task 11: Plugin v2.0.0 — version bump + CHANGELOG + tag release

**Files:**
- Modify: `/Users/juanlentino/projects/signal-and-noise-tools/signal-and-noise-tools.php` (Version header + SNT_VERSION constant)
- Modify: `/Users/juanlentino/projects/signal-and-noise-tools/CHANGELOG.md`
- Modify: `/Users/juanlentino/projects/signal-and-noise-tools/README.md` (if exists, note TSF dropped)

- [ ] **Step 1: Check README.md exists and find current state**

Run: `ls -la /Users/juanlentino/projects/signal-and-noise-tools/README.md` and `head -30 /Users/juanlentino/projects/signal-and-noise-tools/README.md`. If TSF is mentioned anywhere as a dependency, we'll update that section. If README doesn't exist, skip README updates.

- [ ] **Step 2: Bump Version: header in main plugin file**

Use Edit tool on `/Users/juanlentino/projects/signal-and-noise-tools/signal-and-noise-tools.php`:

Replace `Version: 1.16.0` (or whatever current is — verify with `grep '^ \* Version:' signal-and-noise-tools.php`) with `Version: 2.0.0`.

- [ ] **Step 3: Bump SNT_VERSION constant**

In the same file, replace `define( 'SNT_VERSION', '1.16.0' );` with `define( 'SNT_VERSION', '2.0.0' );` (or use Edit tool on whatever exact form is present — verify with `grep "SNT_VERSION'" signal-and-noise-tools.php`).

- [ ] **Step 4: Add v2.0.0 CHANGELOG entry**

Use Edit tool. Insert at top of CHANGELOG.md (above `## [1.16.0]`):

```markdown
## [2.0.0] - 2026-05-17

### Major release — The SEO Framework dependency dropped

Phase 13 of the plugin absorption roadmap. The SEO Framework (`autodescription`) is no longer required for this site's SEO emission. All meta tags, JSON-LD structured data, sitemap routing, and `<title>` emission now come from this plugin (plus WP core's title-tag support via the companion theme release v8.5.5).

### Added — Six new gated emitters

All NEW emissions are gated on `function_exists('the_seo_framework')` — they stay dormant while TSF is active and activate the instant TSF is deactivated. Existing v1.6.0–v1.8.0 emissions (canonical, robots, description, OG, Twitter) stay unconditional.

1. **`document_title_parts` filter in [inc/seo.php](inc/seo.php)** — emits the page `<title>` via WP-native `_wp_render_title_tag()` (theme v8.5.5 declares `add_theme_support('title-tag')`). Format matches what TSF was emitting: `Page Name — Site Name`. Pulls from existing `sn_seo_meta_for_current_view()` so per-route titles (front page, /notes, /provenance) still come from settings copy.
2. **`sn_schema_webpage()` in [inc/seo-schema.php](inc/seo-schema.php)** — WebPage schema for every singular (Page or Post). Includes `breadcrumb` reference + `isPartOf` WebSite reference.
3. **`sn_schema_collection_page()`** — CollectionPage schema for /notes and home archive views.
4. **`sn_schema_breadcrumb_list()`** — manual breadcrumb trail until WP 7.0 native Breadcrumbs block lands in templates (then this becomes a small refactor in a follow-up release).
5. **`inc/sitemap-redirect.php`** — 301 redirect from TSF's legacy routes (`/sitemap.xml`, `/sitemap_index.xml`, `/sitemap.xsl`) to WP core's `/wp-sitemap.xml`. Preserves Google Search Console crawl continuity.
6. **Last-Modified header + If-Modified-Since 304 in [inc/seo.php](inc/seo.php)** — singular content gets `Last-Modified` header set to post's modified GMT. Honors `If-Modified-Since` request header by returning `304 Not Modified` when post is unchanged. Improves crawl budget efficiency.

### Added — Music-specific Person schema fields

`sn_schema_person()` now includes:
- `jobTitle` — defaults to "Music Producer"; settable via `sn_setting('identity.job_title')`.
- `knowsAbout` — defaults to `["Music Production", "Audio Engineering", "Provenance", "Music Industry"]`; settable via `sn_setting('identity.knows_about')`.

Both fields surfaced because this plugin uses richer domain context for the Person entity than TSF's generic schema generator can. Future v2.1.0+ may add a settings UI surface for these fields (no admin UI in this release; settings-array edits work via existing `sn_setting()` API).

### Why MAJOR (breaking change)

Per [CLAUDE.md](https://github.com/juanlentino/signal-and-noise/blob/main/CLAUDE.md) versioning rules: "removed/renamed public API, settings schema change without a migration, or a behavioural shift that requires user action." This release requires a user wp-admin action (TSF deactivation) to take full effect. The plugin's effective contract changes from "we cover SEO gaps TSF doesn't" to "we are the SEO surface." Resets minor count to 0 for v2.x.

### Cutover sequence (executed in this session)

1. Theme v8.5.5 deployed (declares `add_theme_support('title-tag')`).
2. This release (v2.0.0) deployed — new code live but gated dormant.
3. User deactivates TSF in wp-admin → Plugins.
4. Gates flip; new emissions activate; TSF stops emitting anything.
5. Verification via [the spec's checklist](../../signal-and-noise/blob/main/docs/superpowers/specs/2026-05-17-tsf-cutover-design.md#verification-checklist).
6. After 24-48h with no regressions: TSF plugin deleted from wp-admin.

### Rollback

Reactivate TSF in wp-admin (one click). All new emissions flip back to dormant automatically (gates re-fire). No code revert needed for rollback.

### Notes

- **Existing OG/Twitter suppression** (the `the_seo_framework_meta_generator_pools` filter from v1.4.1) stays in place permanently as defense-in-depth.
- **No data migration needed.** Plugin already reads from its own `_sn_*` post meta keys; no TSF data to import.
- Companion: theme v8.5.5 (PATCH) shipped in the same session.

```

- [ ] **Step 5: Update README.md if it exists (skip if not present)**

If README.md exists and mentions TSF as a dependency, use Edit tool to update that section to reflect TSF being optional/dropped as of v2.0.0. If README doesn't exist or doesn't mention TSF, skip this step.

- [ ] **Step 6: PHP syntax check on main plugin file**

Run: `php -l /Users/juanlentino/projects/signal-and-noise-tools/signal-and-noise-tools.php`

Expected: `No syntax errors detected in ...`

- [ ] **Step 7: Commit v2.0.0 release**

```bash
cd /Users/juanlentino/projects/signal-and-noise-tools
git add signal-and-noise-tools.php CHANGELOG.md
# Conditionally add README.md only if it was modified:
git diff --cached --name-only | grep -q '^README' || git add README.md 2>/dev/null
git commit -m "$(cat <<'EOF'
v2.0.0: Phase 13 TSF cutover — drop The SEO Framework dependency

MAJOR release. All SEO emission (meta tags, JSON-LD, sitemap routing,
title) now lives in this plugin. TSF is no longer required.

Six new gated emitters (all dormant while TSF active, activate
automatically when TSF deactivates):
- document_title_parts filter (cooperates with theme v8.5.5's
  add_theme_support('title-tag') for WP-native <title> emission)
- WebPage / CollectionPage / BreadcrumbList JSON-LD additions
- Music-specific Person extensions (jobTitle, knowsAbout)
- Sitemap 301 redirect (/sitemap.xml → /wp-sitemap.xml)
- Last-Modified header + If-Modified-Since 304

Cutover: ship → deactivate TSF in wp-admin → verify → 24-48h
window → delete TSF. Rollback is reactivate TSF (instant, no code
revert needed thanks to function_exists() gates).

Companion release: theme v8.5.5 (PATCH) shipped in same session.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

- [ ] **Step 8: Push commit + tag**

```bash
git push origin HEAD:main
git tag -a v2.0.0 -m "v2.0.0 — Phase 13 TSF cutover (drop The SEO Framework dependency)"
git push origin v2.0.0
```

- [ ] **Step 9: Wait for deploy + verify plugin version**

Wait 45 seconds. Then verify the deployed plugin version:

```bash
curl -sS "https://juanlentino.com/wp-content/plugins/signal-and-noise-tools/signal-and-noise-tools.php" | grep -E "^ \* Version:|SNT_VERSION'" | head -3
```

Expected: `Version: 2.0.0` and `define( 'SNT_VERSION', '2.0.0' );`

If still 1.16.0 after 60s, check GHA: `gh run list --repo juanlentino/signal-and-noise-tools --limit 3`.

- [ ] **Step 10: Pre-cutover verification — head should be unchanged**

Before deactivating TSF, verify that the new v2.0.0 emissions are DORMANT (gates active). Curl the homepage:

```bash
curl -sS "https://juanlentino.com/" | grep -E '"@type":"WebPage"|"@type":"BreadcrumbList"|"jobTitle"' | head -5
```

Expected: **only `"jobTitle":"Music Producer"`** appears (Person schema always emits, gates don't apply to it). WebPage and BreadcrumbList should NOT appear in the head because TSF is still active.

If WebPage or BreadcrumbList appear → gate is broken; investigate before proceeding.

---

## Task 12: Cutover — deactivate TSF + run verification

**Files:**
- Run: `/Users/juanlentino/projects/signal-and-noise/.claude/worktrees/nice-goldstine-063551/docs/superpowers/specs/2026-05-17-tsf-cutover-design.md` §"Verification checklist"

- [ ] **Step 1: User action — deactivate TSF**

User logs into wp-admin → Plugins → finds "The SEO Framework" → click **Deactivate**.

**Critical:** Do NOT click Delete yet. Deactivation is reversible (one click); deletion requires reinstall + reconfigure.

- [ ] **Step 2: Wait 30 seconds for any plugin teardown / caches**

- [ ] **Step 3: Run the verification script from the spec**

Save the verification block from [docs/superpowers/specs/2026-05-17-tsf-cutover-design.md](docs/superpowers/specs/2026-05-17-tsf-cutover-design.md#verification-checklist) §6 as a temporary script and run it:

```bash
cat > /tmp/verify-tsf-cutover.sh <<'VERIFY'
#!/usr/bin/env bash
set -u
BASE="https://juanlentino.com"

pass() { echo "  ✓ $1"; }
fail() { echo "  ✗ $1" >&2; FAIL=1; }
FAIL=0

home=$(curl -sS -A "Mozilla/5.0" "$BASE/")
notes=$(curl -sS -A "Mozilla/5.0" "$BASE/notes/")
pp=$(curl -sS -A "Mozilla/5.0" "$BASE/privacy-policy/")

echo "Check 1: <title> present on homepage"
if echo "$home" | grep -qoE '<title>[^<]+</title>'; then pass "title found"; else fail "no title tag"; fi

echo "Check 2: Single canonical on homepage"
n=$(echo "$home" | grep -o 'rel="canonical"' | wc -l | tr -d ' ')
if [ "$n" = "1" ]; then pass "1 canonical"; else fail "$n canonicals"; fi

echo "Check 3: Single description on homepage"
n=$(echo "$home" | grep -o 'name="description"' | wc -l | tr -d ' ')
if [ "$n" = "1" ]; then pass "1 description"; else fail "$n descriptions"; fi

echo "Check 4: Single robots on homepage"
n=$(echo "$home" | grep -o 'name="robots"' | wc -l | tr -d ' ')
if [ "$n" = "1" ]; then pass "1 robots"; else fail "$n robots"; fi

echo "Check 5: WebPage JSON-LD on /privacy-policy/"
if echo "$pp" | grep -qoE '"@type":"WebPage"'; then pass "WebPage present"; else fail "WebPage missing"; fi

echo "Check 6: BreadcrumbList on /notes/"
if echo "$notes" | grep -qoE '"@type":"BreadcrumbList"'; then pass "BreadcrumbList present"; else fail "BreadcrumbList missing"; fi

echo "Check 7: Sitemap 301"
code=$(curl -sSI -o /dev/null -w '%{http_code}' "$BASE/sitemap.xml")
loc=$(curl -sSI "$BASE/sitemap.xml" | grep -i '^location:' | tr -d '\r')
if [ "$code" = "301" ] && echo "$loc" | grep -q "wp-sitemap.xml"; then
    pass "301 → wp-sitemap.xml"
else
    fail "got $code, location: $loc"
fi

echo "Check 8: WP core sitemap live"
code=$(curl -sSI -o /dev/null -w '%{http_code}' "$BASE/wp-sitemap.xml")
if [ "$code" = "200" ]; then pass "wp-sitemap.xml 200"; else fail "wp-sitemap.xml $code"; fi

echo "Check 9: Last-Modified header on singular"
if curl -sSI "$BASE/privacy-policy/" | grep -qi '^last-modified:'; then
    pass "Last-Modified present"
else
    fail "Last-Modified missing"
fi

echo "Check 10: Music-specific Person fields"
if echo "$home" | grep -qoE '"jobTitle":"Music Producer"'; then
    pass "jobTitle present"
else
    fail "jobTitle missing"
fi

exit $FAIL
VERIFY
chmod +x /tmp/verify-tsf-cutover.sh
/tmp/verify-tsf-cutover.sh
```

Expected: All 10 checks pass.

- [ ] **Step 4: If any check fails → rollback decision**

Per the spec's rollback layers:
- **Layer 1 (instant):** Reactivate TSF in wp-admin. Gates re-fire, site returns to pre-cutover state.
- **Layer 2 (code broken):** `git revert <commit>` in plugin repo, tag v2.0.1, push tag → auto-deploy.
- **Layer 3 (theme issue):** Same pattern, revert theme commit, tag v8.5.6.

In practice Layer 1 is the only realistic action. Investigate the failing check before retrying.

- [ ] **Step 5: Submit /wp-sitemap.xml to Google Search Console**

User action: open [Google Search Console](https://search.google.com/search-console) → Sitemaps → Add a new sitemap → enter `wp-sitemap.xml` → Submit.

This is informational — the 301 from /sitemap.xml already handles crawl redirection — but explicitly submitting wp-sitemap.xml is good hygiene.

- [ ] **Step 6: Commit handoff doc**

Write a brief handoff capturing the cutover outcome. Will be done after Task 12 completion in the actual execution session.

---

## Task 13: Post-window cleanup — delete TSF plugin

**This task is a wait-then-act task — do NOT execute as part of the implementation session. It's documented here for completeness.**

- [ ] **Step 1: 24-48h verification window**

Monitor Google Search Console for crawl errors. Spot-check random URLs (homepage, /notes, individual posts, /privacy-policy).

- [ ] **Step 2: Delete TSF plugin**

Once verification window passes without regressions, in wp-admin → Plugins → The SEO Framework → Delete.

This frees ~30 MB of disk space and removes a now-unused dependency from the security surface (CVE monitoring scope reduces by 1 plugin).

- [ ] **Step 3: Write end-of-cutover handoff doc**

Append a note to the end-of-Phase-13 handoff in `docs/superpowers/handoffs/` (theme repo) confirming TSF is fully removed.

---

## Self-review

**Spec coverage check** (skim spec against tasks):
- Section 3a Title tag → Task 2 ✓
- Section 3b JSON-LD WebPage → Task 3 ✓
- Section 3b JSON-LD CollectionPage → Task 4 ✓
- Section 3b JSON-LD BreadcrumbList → Task 5 ✓
- Section 3b emitter wiring + TSF gate → Task 6 ✓
- Section 3c Music Person → Task 7 ✓
- Section 3d Sitemap redirect → Tasks 8 + 9 ✓
- Section 3e Last-Modified header → Task 10 ✓
- Section 3f TSF gating → Woven through Tasks 2, 6, 8, 10 ✓
- Theme title-tag support → Task 1 ✓
- Version bumps + CHANGELOG → Tasks 1 (theme) + 11 (plugin) ✓
- Cutover sequence (deactivate + verify) → Task 12 ✓
- Post-window delete → Task 13 (documented, not executed) ✓
- Rollback → Captured in Task 12 Step 4 ✓
- Verification checklist → Embedded in Task 12 Step 3 ✓

**No gaps.**

**Placeholder scan:** No "TBD", "TODO", or "implement later" remaining. All code blocks are complete. All commands are exact.

**Type consistency:** Function names match across tasks:
- `sn_schema_webpage()` (Tasks 3, 6) ✓
- `sn_schema_collection_page()` (Tasks 4, 6) ✓
- `sn_schema_breadcrumb_list()` (Tasks 5, 6) ✓
- `sn_schema_person()` (Task 7 extends existing) ✓
- `sn_seo_meta_for_current_view()` (Task 2 calls existing) ✓
- `sn_setting()` (used throughout — existing) ✓

**Scope check:** 11 substantive tasks + 1 wait-task. Single implementation plan size. No decomposition needed.

Plan is ready for execution.
