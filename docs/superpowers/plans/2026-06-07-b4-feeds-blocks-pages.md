# B4 "Feeds, blocks & pages" (theme v9.11.0) — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking. **Each task is implemented with TDD** (write the failing CLI test → run it red → implement → run it green → commit). Read [the design spec](../specs/2026-06-07-b4-feeds-blocks-pages-design.md) first — it embeds the verified framework contracts (grounding `wf_923523e4-1bc`).

**Goal:** Ship six additive, reader/editor-facing capabilities — JSON Feed, RSS enrichment, two custom blocks, a Block Bindings source, a `/colophon` page, and a Notes-scoped reader command palette — as theme **v9.11.0**.

**Architecture:** Each capability is a self-contained `inc/*.php` module (≤150 lines, named functions, `require_once` from `functions.php`) plus its standalone CLI test fixture in `tests/`. No JS build step (buildless ES5). The theme never flushes rewrites. Cross-package (plugin) reads are `function_exists`-guarded.

**Tech Stack:** WordPress FSE (PHP 8.2), `theme.json` v3, vanilla ES5, WP block-bindings + dynamic-block APIs. Tests: standalone `php tests/<name>.php` fixtures that stub WP primitives.

**Conventions (every task):**
- Module top guard: `if ( ! defined( 'ABSPATH' ) ) { exit; }`.
- Hooked functions are **named** (never closures) so tests can `require` + call them.
- Tests start with the CLI/web guard `if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }` then `if ( ! defined( 'ABSPATH' ) ) define( 'ABSPATH', '/' );`.
- Run a test: `php tests/<name>.php` (exit 0 = pass; the harness prints `Result: N passed, 0 failed.`).
- Commit per task (the module + its test + its wiring). Co-author footer: `Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>`.
- After all tasks: Task 7 bumps `style.css` + CHANGELOG; Task 8 gates (full sweep + falsified phpcs).

**Task order (dependency-aware, sequential — all tasks touch `functions.php`):**
T1 JSON Feed → T2 RSS enrichment → T3 Blocks → T4 Block Bindings → T5 `/colophon` → T6 Command palette → T7 Release commit → T8 Gate.

---

### Task 1: JSON Feed 1.1

**Files:**
- Create: `inc/feed-json.php`
- Create: `tests/feed-json.php`
- Modify: `functions.php` (append `require_once`, update module-map docblock)

- [ ] **Step 1: Write the failing test** — `tests/feed-json.php`

```php
<?php
// SECURITY: CLI-only fixture.
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', '/' ); }
define( 'SN_FEED_JSON_TEST', true ); // suppress add_feed/add_filter wiring on require

$pass = 0; $fail = 0;
function ok( $cond, $msg ) { global $pass, $fail; if ( $cond ) { $pass++; echo "PASS: $msg\n"; } else { $fail++; echo "FAIL: $msg\n"; } }

// --- Stub WP primitives the builder touches ---
function add_feed( $n, $cb ) { $GLOBALS['__feeds'][ $n ] = $cb; return "do_feed_$n"; }
function add_action( $h, $cb, $p = 10, $a = 1 ) { return true; }
function add_filter( $h, $cb, $p = 10, $a = 1 ) { return true; }
function get_permalink( $p = null ) { return 'https://x.test/notes/n-' . ( is_object( $p ) ? $p->ID : $p ) . '/'; }
function get_the_title( $p = null ) { return 'Note "' . ( is_object( $p ) ? $p->ID : $p ) . '" & co'; }
function get_post_time( $f, $gmt, $p ) { return '2026-06-07T12:00:00+00:00'; }
function get_post_modified_time( $f, $gmt, $p ) { return '2026-06-07T13:00:00+00:00'; }
function get_the_category( $id ) { $c = new stdClass(); $c->name = 'analysis'; return array( $c ); }
function has_excerpt( $p ) { return false; }
function get_the_excerpt( $p ) { return ''; }
function apply_filters( $h, $v ) { return $v; }
function get_bloginfo( $k ) { return 'Signal & Noise'; }
function home_url( $p = '' ) { return 'https://x.test' . $p; }
function get_option( $k ) { return 'UTF-8'; }
if ( ! function_exists( 'wp_json_encode' ) ) { function wp_json_encode( $v, $flags = 0, $depth = 512 ) { return json_encode( $v, $flags, $depth ); } }

require __DIR__ . '/../inc/feed-json.php';

// --- Behavioral assertions on the pure builder ---
$post = (object) array( 'ID' => 7, 'post_content' => '<p>Body & stuff</p>' );
$item = sn_feed_json_build_item( $post );
ok( is_string( $item['id'] ) && $item['id'] !== '', 'item id is a non-empty string (stable permalink)' );
ok( isset( $item['content_html'] ) && $item['content_html'] !== '', 'content_html present + non-empty (required field)' );
ok( preg_match( '/^\d{4}-\d{2}-\d{2}T/', $item['date_published'] ) === 1, 'date_published is RFC 3339 shape' );
ok( in_array( 'analysis', $item['tags'], true ), 'tags carries category names' );

// Whole-feed shape + escaping discipline (JSON, not esc_html).
$feed = array(
	'version' => 'https://jsonfeed.org/version/1.1',
	'title'   => get_bloginfo( 'name' ),
	'items'   => array( $item ),
);
$json    = wp_json_encode( $feed, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
$decoded = json_decode( $json, true );
ok( $decoded['version'] === 'https://jsonfeed.org/version/1.1', 'feed round-trips with JSON Feed 1.1 version' );
ok( strpos( $json, '&amp;' ) === false, 'no HTML-entity mangling in JSON (title used esc-free path)' );
ok( strpos( $decoded['items'][0]['title'], '&' ) !== false, 'raw ampersand survives into decoded title' );

echo "Result: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/feed-json.php`
Expected: FAIL — `sn_feed_json_build_item()` undefined (fatal) until the module exists.

- [ ] **Step 3: Write minimal implementation** — `inc/feed-json.php` (named functions; the `init`/`add_filter` wiring is skipped under `SN_FEED_JSON_TEST`)

```php
<?php
/**
 * Signal & Noise — JSON Feed 1.1 for the Notes corpus.
 *
 * Registered via add_feed('json',…). ?feed=json resolves immediately (no flush —
 * 'feed' is a core public query var). The pretty /feed/json/ path needs a rewrite
 * rule that only materializes on the PLUGIN's next flush; the theme must NOT flush.
 *
 * @package SignalNoise
 * @since 9.11.0
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function sn_feed_json_register() {
	add_feed( 'json', 'sn_feed_json_render' );
}

function sn_feed_json_content_type( $type, $feed ) {
	return ( 'json' === $feed ) ? 'application/feed+json' : $type;
}

/**
 * do_feed_json callback. Core invokes it as ($is_comment_feed, $feed_name).
 */
function sn_feed_json_render( $is_comment_feed = false, $feed = 'json' ) {
	$q = new WP_Query( array(
		'post_type'           => 'post',
		'post_status'         => 'publish',
		'posts_per_page'      => 20,
		'orderby'             => 'date',
		'order'               => 'DESC',
		'no_found_rows'       => true,
		'ignore_sticky_posts' => true,
	) );
	$items = array();
	while ( $q->have_posts() ) {
		$q->the_post();
		$items[] = sn_feed_json_build_item( get_post() );
	}
	wp_reset_postdata();

	$doc = array(
		'version'       => 'https://jsonfeed.org/version/1.1',
		'title'         => get_bloginfo( 'name' ),
		'home_page_url' => home_url( '/notes/' ),
		'feed_url'      => home_url( '/feed/json/' ),
		'description'   => get_bloginfo( 'description' ),
		'language'      => get_bloginfo( 'language' ),
		'items'         => $items,
	);
	header( 'Content-Type: application/feed+json; charset=' . get_option( 'blog_charset' ) );
	echo wp_json_encode( $doc, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	exit;
}

/**
 * Build one JSON Feed item. Pure + testable. Raw values — wp_json_encode escapes.
 */
function sn_feed_json_build_item( $post ) {
	$tags = array();
	foreach ( (array) get_the_category( $post->ID ) as $cat ) {
		$tags[] = $cat->name;
	}
	$item = array(
		'id'             => (string) get_permalink( $post ),
		'url'            => get_permalink( $post ),
		'title'          => get_the_title( $post ),
		'content_html'   => (string) apply_filters( 'the_content', $post->post_content ),
		'date_published' => get_post_time( 'c', true, $post ),
		'date_modified'  => get_post_modified_time( 'c', true, $post ),
	);
	if ( $tags ) { $item['tags'] = $tags; }
	if ( has_excerpt( $post ) ) {
		$ex = get_the_excerpt( $post );
		if ( '' !== $ex ) { $item['summary'] = $ex; }
	}
	return $item;
}

if ( ! defined( 'SN_FEED_JSON_TEST' ) || ! SN_FEED_JSON_TEST ) {
	add_action( 'init', 'sn_feed_json_register' );
	add_filter( 'feed_content_type', 'sn_feed_json_content_type', 10, 2 );
}
```

- [ ] **Step 4: Run test to verify it passes** — `php tests/feed-json.php` → `Result: 7 passed, 0 failed.`

- [ ] **Step 5: Wire into `functions.php`** — append `require_once __DIR__ . '/inc/feed-json.php';` to the require list and add `inc/feed-json.php — JSON Feed 1.1 for the Notes corpus (v9.11.0)` to the module-map docblock.

- [ ] **Step 6: Commit**

```bash
git add inc/feed-json.php tests/feed-json.php functions.php
git commit -m "feat(feed): JSON Feed 1.1 endpoint for the Notes corpus (?feed=json)"
```

---

### Task 2: RSS item enrichment

**Files:**
- Create: `inc/feed-enrichment.php`
- Create: `tests/feed-enrichment.php`
- Modify: `functions.php`

**Contract:** `rss2_ns` declares the Media RSS namespace; `rss2_item` emits `<media:content>` (featured-image / plugin OG card) + a reading-time element. All plugin reads `function_exists`-guarded. Core RSS already emits `<category>` tags, so do not re-emit tags.

- [ ] **Step 1: Write the failing test** — `tests/feed-enrichment.php`

```php
<?php
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', '/' ); }
define( 'SN_FEED_ENRICH_TEST', true );
$pass = 0; $fail = 0;
function ok( $c, $m ) { global $pass, $fail; if ( $c ) { $pass++; echo "PASS: $m\n"; } else { $fail++; echo "FAIL: $m\n"; } }

function add_action( $h, $cb, $p = 10, $a = 1 ) { return true; }
function add_filter( $h, $cb, $p = 10, $a = 1 ) { return true; }
function esc_url( $u ) { return $u; }
function esc_html( $s ) { return $s; }
function esc_attr( $s ) { return $s; }
$GLOBALS['__thumb'] = 'https://x.test/og/7.png';
function get_post_thumbnail_id( $id ) { return $GLOBALS['__has_thumb'] ? 99 : 0; }
function wp_get_attachment_image_url( $aid, $size ) { return $GLOBALS['__thumb']; }
function get_the_ID() { return 7; }

require __DIR__ . '/../inc/feed-enrichment.php';

// Namespace declaration.
ob_start(); sn_rss_media_ns(); $ns = ob_get_clean();
ok( strpos( $ns, 'xmlns:media="http://search.yahoo.com/mrss/"' ) !== false, 'rss2_ns emits the Media RSS namespace' );

// Item enrichment WITH a featured image + plugin reading-time.
$GLOBALS['__has_thumb'] = true;
function sn_get_reading_time( $id = null ) { return 6; }
ob_start(); sn_rss_item_enrich(); $item = ob_get_clean();
ok( strpos( $item, '<media:content' ) !== false && strpos( $item, $GLOBALS['__thumb'] ) !== false, 'rss2_item emits media:content for the featured image' );
ok( strpos( $item, '6' ) !== false, 'rss2_item emits reading-time when the plugin is present' );

// Degrade: no featured image → no media:content (no fatal, no empty tag).
$GLOBALS['__has_thumb'] = false;
ob_start(); sn_rss_item_enrich(); $item2 = ob_get_clean();
ok( strpos( $item2, '<media:content' ) === false, 'no media:content when there is no featured image' );

echo "Result: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
```

- [ ] **Step 2: Run → FAIL** (`php tests/feed-enrichment.php`; functions undefined).

- [ ] **Step 3: Implement** — `inc/feed-enrichment.php`

```php
<?php
/**
 * Signal & Noise — RSS item enrichment.
 *
 * Adds the Media RSS namespace + <media:content> (featured image) and a
 * reading-time element to RSS2 items. Reading time is plugin-owned
 * (sn_get_reading_time) — function_exists-guarded so the feed degrades when
 * the plugin is absent. Core already emits <category> tags; we do not.
 *
 * @package SignalNoise
 * @since 9.11.0
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function sn_rss_media_ns() {
	echo 'xmlns:media="http://search.yahoo.com/mrss/"' . "\n";
}

function sn_rss_item_enrich() {
	$id    = get_the_ID();
	$thumb = get_post_thumbnail_id( $id );
	if ( $thumb ) {
		$url = wp_get_attachment_image_url( $thumb, 'full' );
		if ( $url ) {
			echo '<media:content url="' . esc_url( $url ) . '" medium="image" />' . "\n";
		}
	}
	if ( function_exists( 'sn_get_reading_time' ) ) {
		$mins = (int) sn_get_reading_time( $id );
		if ( $mins >= 1 ) {
			echo '<sn:readingTimeMinutes>' . esc_html( (string) $mins ) . '</sn:readingTimeMinutes>' . "\n";
		}
	}
}

if ( ! defined( 'SN_FEED_ENRICH_TEST' ) || ! SN_FEED_ENRICH_TEST ) {
	add_filter( 'rss2_ns', 'sn_rss_media_ns' );
	add_action( 'rss2_item', 'sn_rss_item_enrich' );
}
```

> Note: `rss2_ns` is an action (core does `do_action('rss2_ns')`), not a filter that returns a string — but core registers via `add_action`. We hook it with `add_action`/echo. (If the harness flags the `add_filter` vs `add_action` choice, match core: `add_action('rss2_ns', …)`.) Use `add_action` for both.

- [ ] **Step 4: Run → PASS** (`Result: 4 passed, 0 failed.`). Fix the `rss2_ns` registration to `add_action` if needed.

- [ ] **Step 5: Wire** `require_once __DIR__ . '/inc/feed-enrichment.php';` + docblock line.

- [ ] **Step 6: Commit**

```bash
git add inc/feed-enrichment.php tests/feed-enrichment.php functions.php
git commit -m "feat(feed): RSS media:content + reading-time enrichment (plugin-guarded)"
```

---

### Task 3: Sidenote + pull-quote custom blocks

**Files:**
- Create: `blocks/editor.js`, `blocks/sidenote/block.json`, `blocks/sidenote/render.php`, `blocks/pull-quote/block.json`, `blocks/pull-quote/render.php`
- Create: `inc/blocks-register.php`
- Create: `tests/blocks-registry.php`
- Modify: `functions.php`; annotate `patterns/sidenote.php` + `patterns/pull-quote.php` (one-line "superseded by the block; retained as fallback" docblock)

**Load-bearing facts:** `editorScript` is a **registered handle string** (NOT `file:./editor.js` — that loads with empty deps → `wp is undefined`). Block categories use the `block_categories_all` filter (separate from pattern categories). `render.php` uses `get_block_wrapper_attributes(['class'=>'sn-sidenote'|'sn-pull-quote'])`. The pull-quote block emits `.sn-pull-quote` (the class `assets/css/critical.css` targets), not the pattern's `.sn-pattern-pull-quote`.

- [ ] **Step 1: Write the failing test** — `tests/blocks-registry.php`

```php
<?php
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', '/' ); }
define( 'SN_BLOCKS_TEST', true );
$pass = 0; $fail = 0;
function ok( $c, $m ) { global $pass, $fail; if ( $c ) { $pass++; echo "PASS: $m\n"; } else { $fail++; echo "FAIL: $m\n"; } }

$GLOBALS['__reg_blocks'] = array(); $GLOBALS['__reg_scripts'] = array();
function add_action( $h, $cb, $p = 10, $a = 1 ) { return true; }
function add_filter( $h, $cb, $p = 10, $a = 1 ) { return true; }
function register_block_type( $dir ) { $GLOBALS['__reg_blocks'][] = $dir; return true; }
function wp_register_script( $h, $src, $deps = array(), $v = false, $f = false ) { $GLOBALS['__reg_scripts'][ $h ] = $deps; return true; }
function get_theme_file_uri( $p = '' ) { return 'https://x.test/' . $p; }
function wp_get_theme() { return new class { public function get( $k ) { return '9.11.0'; } }; }
function get_block_wrapper_attributes( $a = array() ) { return 'class="' . ( $a['class'] ?? '' ) . '"'; }
function wp_kses_post( $s ) { return $s; }

$blocks_dir = __DIR__ . '/../blocks';

// block.json validity + fields.
foreach ( array( 'sidenote', 'pull-quote' ) as $slug ) {
	$json = json_decode( file_get_contents( "$blocks_dir/$slug/block.json" ), true );
	ok( is_array( $json ), "$slug/block.json parses as JSON" );
	ok( $json['name'] === "signal-noise/$slug", "$slug name is signal-noise/$slug" );
	ok( (int) $json['apiVersion'] === 3, "$slug apiVersion 3" );
	ok( $json['editorScript'] === 'signal-noise-blocks-editor', "$slug editorScript is the registered handle (not a file: path)" );
	ok( $json['render'] === 'file:./render.php', "$slug uses a dynamic render.php" );
	ok( $json['category'] === 'signal-noise', "$slug in the signal-noise block category" );
	ok( strpos( $json['editorScript'], 'file:' ) === false, "$slug editorScript is NOT a file: path (empty-deps trap)" );
}

// Behavioral render: sidenote.
$attributes = array( 'content' => 'A margin note' ); $content = ''; $block = null;
ob_start(); include "$blocks_dir/sidenote/render.php"; $out = ob_get_clean();
ok( strpos( $out, 'sn-sidenote' ) !== false && strpos( $out, 'A margin note' ) !== false, 'sidenote render emits .sn-sidenote with content' );

// Behavioral render: pull-quote with both fields, then empty fields.
$attributes = array( 'body' => 'Thesis', 'attribution' => 'Author' );
ob_start(); include "$blocks_dir/pull-quote/render.php"; $pq = ob_get_clean();
ok( strpos( $pq, 'sn-pull-quote__body' ) !== false && strpos( $pq, 'sn-pull-quote__attribution' ) !== false, 'pull-quote renders both slots when set' );
ok( strpos( $pq, 'sn-pull-quote' ) !== false && strpos( $pq, 'sn-pattern-pull-quote' ) === false, 'pull-quote block uses .sn-pull-quote (CSS-matching), not the pattern class' );
$attributes = array( 'body' => 'Only body', 'attribution' => '' );
ob_start(); include "$blocks_dir/pull-quote/render.php"; $pq2 = ob_get_clean();
ok( strpos( $pq2, 'sn-pull-quote__attribution' ) === false, 'pull-quote omits the attribution slot when empty' );

// Registration wiring.
require __DIR__ . '/../inc/blocks-register.php';
signal_noise_register_block_editor_script();
signal_noise_register_blocks();
ok( isset( $GLOBALS['__reg_scripts']['signal-noise-blocks-editor'] ), 'editor script handle registered' );
$deps = $GLOBALS['__reg_scripts']['signal-noise-blocks-editor'];
foreach ( array( 'wp-blocks', 'wp-element', 'wp-block-editor' ) as $d ) {
	ok( in_array( $d, $deps, true ), "editor script depends on $d" );
}
ok( count( $GLOBALS['__reg_blocks'] ) === 2, 'both block dirs registered' );
$cats = signal_noise_block_category( array() );
ok( ! empty( array_filter( $cats, fn( $c ) => ( $c['slug'] ?? '' ) === 'signal-noise' ) ), 'block_categories_all adds a signal-noise category' );

echo "Result: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
```

- [ ] **Step 2: Run → FAIL** (`php tests/blocks-registry.php`; missing block.json files + functions).

- [ ] **Step 3: Implement the block files.**

`blocks/sidenote/block.json`:
```json
{
	"$schema": "https://schemas.wp.org/trunk/block.json",
	"apiVersion": 3,
	"name": "signal-noise/sidenote",
	"title": "Sidenote",
	"category": "signal-noise",
	"icon": "editor-ol",
	"description": "Tufte-style margin annotation. Floats right at >=1280px, inline hairline below at narrower.",
	"keywords": ["sidenote", "marginalia", "footnote", "tufte"],
	"textdomain": "signal-noise",
	"attributes": {
		"content": { "type": "string", "source": "html", "selector": ".sn-sidenote", "default": "" }
	},
	"supports": { "html": false, "reusable": false },
	"editorScript": "signal-noise-blocks-editor",
	"render": "file:./render.php"
}
```

`blocks/sidenote/render.php`:
```php
<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
$wrapper = get_block_wrapper_attributes( array( 'class' => 'sn-sidenote' ) );
printf( '<p %s>%s</p>', $wrapper, wp_kses_post( $attributes['content'] ?? '' ) );
```

`blocks/pull-quote/block.json`:
```json
{
	"$schema": "https://schemas.wp.org/trunk/block.json",
	"apiVersion": 3,
	"name": "signal-noise/pull-quote",
	"title": "Pull-quote",
	"category": "signal-noise",
	"icon": "format-quote",
	"description": "Brutalist pull-quote: black rules, serif italic body, mono uppercase attribution.",
	"keywords": ["thesis", "quote", "callout", "pull-quote"],
	"textdomain": "signal-noise",
	"attributes": {
		"body": { "type": "string", "source": "html", "selector": ".sn-pull-quote__body", "default": "" },
		"attribution": { "type": "string", "source": "html", "selector": ".sn-pull-quote__attribution", "default": "" }
	},
	"supports": { "html": false, "align": ["wide", "full"] },
	"editorScript": "signal-noise-blocks-editor",
	"render": "file:./render.php"
}
```

`blocks/pull-quote/render.php`:
```php
<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
$wrapper = get_block_wrapper_attributes( array( 'class' => 'sn-pull-quote' ) );
$body = wp_kses_post( $attributes['body'] ?? '' );
$attr = wp_kses_post( $attributes['attribution'] ?? '' );
echo '<aside ' . $wrapper . '>';
if ( '' !== $body ) { echo '<p class="sn-pull-quote__body">' . $body . '</p>'; }
if ( '' !== $attr ) { echo '<p class="sn-pull-quote__attribution">' . $attr . '</p>'; }
echo '</aside>';
```

`blocks/editor.js` (buildless ES5 — no JSX; registers both):
```javascript
( function ( blocks, element, blockEditor ) {
	'use strict';
	var el = element.createElement;
	var useBlockProps = blockEditor.useBlockProps;
	var RichText = blockEditor.RichText;

	blocks.registerBlockType( 'signal-noise/sidenote', {
		edit: function ( props ) {
			var bp = useBlockProps( { className: 'sn-sidenote' } );
			return el( RichText, Object.assign( {}, bp, {
				tagName: 'p',
				value: props.attributes.content,
				onChange: function ( v ) { props.setAttributes( { content: v } ); },
				placeholder: 'Margin note…'
			} ) );
		},
		save: function ( props ) {
			var bp = useBlockProps.save( { className: 'sn-sidenote' } );
			return el( RichText.Content, Object.assign( {}, bp, { tagName: 'p', value: props.attributes.content } ) );
		}
	} );

	blocks.registerBlockType( 'signal-noise/pull-quote', {
		edit: function ( props ) {
			var bp = useBlockProps( { className: 'sn-pull-quote' } );
			return el( 'aside', bp,
				el( RichText, { tagName: 'p', className: 'sn-pull-quote__body', value: props.attributes.body,
					onChange: function ( v ) { props.setAttributes( { body: v } ); }, placeholder: 'Thesis statement…' } ),
				el( RichText, { tagName: 'p', className: 'sn-pull-quote__attribution', value: props.attributes.attribution,
					onChange: function ( v ) { props.setAttributes( { attribution: v } ); }, placeholder: '— attribution' } )
			);
		},
		save: function ( props ) {
			var bp = useBlockProps.save( { className: 'sn-pull-quote' } );
			return el( 'aside', bp,
				el( RichText.Content, { tagName: 'p', className: 'sn-pull-quote__body', value: props.attributes.body } ),
				el( RichText.Content, { tagName: 'p', className: 'sn-pull-quote__attribution', value: props.attributes.attribution } )
			);
		}
	} );
} )( window.wp.blocks, window.wp.element, window.wp.blockEditor );
```

`inc/blocks-register.php`:
```php
<?php
/**
 * Signal & Noise — custom block registration (sidenote, pull-quote).
 *
 * Buildless dynamic blocks. editorScript is a manually-registered handle with
 * explicit deps — NOT a file: path (which would load with empty deps and throw
 * 'wp is undefined' because there is no .asset.php sidecar in a no-build theme).
 *
 * @package SignalNoise
 * @since 9.11.0
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function signal_noise_register_block_editor_script() {
	wp_register_script(
		'signal-noise-blocks-editor',
		get_theme_file_uri( 'blocks/editor.js' ),
		array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components' ),
		wp_get_theme()->get( 'Version' ),
		true
	);
}

function signal_noise_register_blocks() {
	register_block_type( __DIR__ . '/../blocks/sidenote' );
	register_block_type( __DIR__ . '/../blocks/pull-quote' );
}

function signal_noise_block_category( $categories ) {
	return array_merge( $categories, array( array(
		'slug'  => 'signal-noise',
		'title' => 'Signal & Noise',
	) ) );
}

if ( ! defined( 'SN_BLOCKS_TEST' ) || ! SN_BLOCKS_TEST ) {
	add_action( 'init', 'signal_noise_register_block_editor_script' );
	add_action( 'init', 'signal_noise_register_blocks' );
	add_filter( 'block_categories_all', 'signal_noise_block_category' );
}
```

- [ ] **Step 4: Run → PASS** (`php tests/blocks-registry.php`).

- [ ] **Step 5: Wire + annotate patterns.** Add `require_once __DIR__ . '/inc/blocks-register.php';` after the `inc/patterns.php` require + docblock line. Add to each of `patterns/sidenote.php` / `patterns/pull-quote.php` docblock: `Superseded by the signal-noise/sidenote (resp. /pull-quote) BLOCK as of v9.11.0; retained as a no-block fallback / scaffold.`

- [ ] **Step 6: Commit**

```bash
git add blocks/ inc/blocks-register.php tests/blocks-registry.php functions.php patterns/sidenote.php patterns/pull-quote.php
git commit -m "feat(blocks): buildless sidenote + pull-quote dynamic blocks (supersede patterns)"
```

---

### Task 4: Block Bindings source `signal-noise/post-field`

**Files:**
- Create: `inc/block-bindings.php`
- Create: `tests/block-bindings.php`
- Modify: `parts/post-frontmatter.html` (migrate the reading-time + pillar slots), `functions.php`

**Load-bearing facts:** `register_block_bindings_source` on `init`; props are exactly `label`/`get_value_callback`/`uses_context`. Callback returns a string to override or **`null` to keep the block's fallback** (avoids an empty `<p>`). Reading time is the real function `sn_get_reading_time()`; pillar reuses `sn_post_pillar_shortcode()`; canonical/og_title via `sn_post_settings_get_canonical_url()` / `sn_post_settings_get_og_card_title()` — all `function_exists`-guarded. Only `reading_time` + `pillar` are bound in the part; `canonical`/`og_title` are resolvable but NOT bound (would dupe the plugin `<head>`).

- [ ] **Step 1: Write the failing test** — `tests/block-bindings.php`

```php
<?php
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', '/' ); }
define( 'SN_BINDINGS_TEST', true );
$pass = 0; $fail = 0;
function ok( $c, $m ) { global $pass, $fail; if ( $c ) { $pass++; echo "PASS: $m\n"; } else { $fail++; echo "FAIL: $m\n"; } }

$GLOBALS['__sources'] = array();
function register_block_bindings_source( $name, $props ) { $GLOBALS['__sources'][ $name ] = $props; return true; }
function add_action( $h, $cb, $p = 10, $a = 1 ) { return true; }
function __( $s, $d = null ) { return $s; }
function esc_html( $s ) { return $s; }
function esc_url( $s ) { return $s; }
function get_post() { return (object) array( 'ID' => 7 ); }
// Plugin-side stubs (toggle-able via globals).
function sn_get_reading_time( $id = null ) { return $GLOBALS['__rt']; }
function sn_post_pillar_shortcode() { return $GLOBALS['__pillar']; }
function sn_post_settings_get_canonical_url( $id ) { return $GLOBALS['__canon']; }
function sn_post_settings_get_og_card_title( $id ) { return $GLOBALS['__ogt']; }

require __DIR__ . '/../inc/block-bindings.php';

// Registration capture.
sn_register_post_field_binding();
$src = $GLOBALS['__sources']['signal-noise/post-field'] ?? null;
ok( is_array( $src ), 'signal-noise/post-field registered' );
ok( $src['get_value_callback'] === 'sn_post_field_binding_value', 'callback wired' );
ok( in_array( 'postId', $src['uses_context'], true ), 'uses_context includes postId' );

// reading_time.
$GLOBALS['__rt'] = 5;
ok( sn_post_field_binding_value( array( 'key' => 'reading_time' ) ) === '5 min read', 'reading_time formats' );
$GLOBALS['__rt'] = 0;
ok( sn_post_field_binding_value( array( 'key' => 'reading_time' ) ) === '1 min read', 'reading_time min-1 floor' );

// pillar: anchor passes through; empty → null (keep fallback).
$GLOBALS['__pillar'] = '<a class="sn-post-frontmatter__pillar" href="/provenance/x/">PROVENANCE</a>';
ok( strpos( (string) sn_post_field_binding_value( array( 'key' => 'pillar' ) ), 'sn-post-frontmatter__pillar' ) !== false, 'pillar returns the anchor' );
$GLOBALS['__pillar'] = '';
ok( sn_post_field_binding_value( array( 'key' => 'pillar' ) ) === null, 'pillar returns null when no pillar (keep fallback)' );

// canonical / og_title.
$GLOBALS['__canon'] = 'https://x.test/canon/';
ok( sn_post_field_binding_value( array( 'key' => 'canonical' ) ) === 'https://x.test/canon/', 'canonical resolves' );
$GLOBALS['__canon'] = '';
ok( sn_post_field_binding_value( array( 'key' => 'canonical' ) ) === null, 'canonical null when empty' );
$GLOBALS['__ogt'] = 'OG Title';
ok( sn_post_field_binding_value( array( 'key' => 'og_title' ) ) === 'OG Title', 'og_title resolves' );

// Edge cases.
ok( sn_post_field_binding_value( array( 'key' => 'bogus' ) ) === null, 'unknown key → null' );
ok( sn_post_field_binding_value( array() ) === null, 'missing key → null' );

// postId-context precedence (get_post returns null here, context supplies id).
$blk = (object) array( 'context' => array( 'postId' => 42 ) );
$GLOBALS['__rt'] = 9;
ok( sn_post_field_binding_value( array( 'key' => 'reading_time' ), $blk ) === '9 min read', 'uses block context postId' );

echo "Result: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
```

- [ ] **Step 2: Run → FAIL.**

- [ ] **Step 3: Implement** — `inc/block-bindings.php`

```php
<?php
/**
 * Signal & Noise — Block Bindings source: signal-noise/post-field.
 *
 * Read-only source resolving reading_time|pillar|canonical|og_title for the
 * current post. PHP-only registration → read-only in the editor (acceptable).
 * All plugin reads function_exists-guarded; returns null to keep the block's
 * fallback markup when a value is genuinely absent.
 *
 * @package SignalNoise
 * @since 9.11.0
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function sn_post_field_binding_value( $source_args, $block_instance = null, $attribute_name = '' ) {
	$key = isset( $source_args['key'] ) ? (string) $source_args['key'] : '';
	if ( '' === $key ) { return null; }

	$post_id = 0;
	if ( $block_instance && ! empty( $block_instance->context['postId'] ) ) {
		$post_id = (int) $block_instance->context['postId'];
	} else {
		$p = get_post();
		if ( $p ) { $post_id = (int) $p->ID; }
	}
	if ( ! $post_id ) { return null; }

	switch ( $key ) {
		case 'reading_time':
			if ( ! function_exists( 'sn_get_reading_time' ) ) { return null; }
			return esc_html( sprintf( '%d min read', max( 1, (int) sn_get_reading_time( $post_id ) ) ) );
		case 'pillar':
			if ( ! function_exists( 'sn_post_pillar_shortcode' ) ) { return null; }
			$html = sn_post_pillar_shortcode();
			return '' !== $html ? $html : null;
		case 'canonical':
			if ( ! function_exists( 'sn_post_settings_get_canonical_url' ) ) { return null; }
			$v = sn_post_settings_get_canonical_url( $post_id );
			return '' !== $v ? esc_url( $v ) : null;
		case 'og_title':
			if ( ! function_exists( 'sn_post_settings_get_og_card_title' ) ) { return null; }
			$v = sn_post_settings_get_og_card_title( $post_id );
			return '' !== $v ? esc_html( $v ) : null;
	}
	return null;
}

function sn_register_post_field_binding() {
	register_block_bindings_source( 'signal-noise/post-field', array(
		'label'              => __( 'Signal & Noise: Post Field', 'signal-noise' ),
		'get_value_callback' => 'sn_post_field_binding_value',
		'uses_context'       => array( 'postId', 'postType' ),
	) );
}

if ( ! defined( 'SN_BINDINGS_TEST' ) || ! SN_BINDINGS_TEST ) {
	add_action( 'init', 'sn_register_post_field_binding' );
}
```

- [ ] **Step 4: Run → PASS.**

- [ ] **Step 5: Migrate `parts/post-frontmatter.html`.** Replace ONLY the reading-time paragraph (lines 14-16) and the pillar `wp:shortcode` block (lines 24-26). Leave `wp:post-date`, `[sn_updated_date]`, the two `·` dividers, and `wp:post-terms` byte-identical.

Reading-time paragraph becomes (binding fills content; empty fallback):
```html
<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"signal-noise/post-field","args":{"key":"reading_time"}}}},"className":"sn-post-frontmatter__rt","style":{"typography":{"fontSize":"0.75rem","letterSpacing":"0.15em","textTransform":"uppercase"},"spacing":{"margin":{"top":"0","bottom":"0"}}},"textColor":"blood","fontFamily":"body"} -->
<p class="sn-post-frontmatter__rt has-blood-color has-text-color has-body-font-family" style="margin-top:0;margin-bottom:0;font-size:0.75rem;letter-spacing:0.15em;text-transform:uppercase"></p>
<!-- /wp:paragraph -->
```

Pillar `wp:shortcode` block becomes a bound paragraph (own slot class):
```html
<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"signal-noise/post-field","args":{"key":"pillar"}}}},"className":"sn-post-frontmatter__pillar-slot","style":{"typography":{"fontSize":"0.75rem","letterSpacing":"0.15em","textTransform":"uppercase"},"spacing":{"margin":{"top":"0","bottom":"0"}}},"textColor":"rust","fontFamily":"body"} -->
<p class="sn-post-frontmatter__pillar-slot has-rust-color has-text-color has-body-font-family" style="margin-top:0;margin-bottom:0;font-size:0.75rem;letter-spacing:0.15em;text-transform:uppercase"></p>
<!-- /wp:paragraph -->
```

- [ ] **Step 6: Wire** `require_once __DIR__ . '/inc/block-bindings.php';` after the `inc/post-frontmatter.php` require + docblock line.

- [ ] **Step 7: Commit**

```bash
git add inc/block-bindings.php tests/block-bindings.php parts/post-frontmatter.html functions.php
git commit -m "feat(bindings): signal-noise/post-field source; migrate post-frontmatter reading-time + pillar"
```

---

### Task 5: `/colophon` page template

**Files:**
- Create: `templates/page-colophon.html`, `patterns/colophon.php`
- Create: `tests/colophon-template.php`
- Modify: `theme.json` (`customTemplates`), `parts/footer.html`

- [ ] **Step 1: Write the failing test** — `tests/colophon-template.php`

```php
<?php
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', '/' ); }
$pass = 0; $fail = 0;
function ok( $c, $m ) { global $pass, $fail; if ( $c ) { $pass++; echo "PASS: $m\n"; } else { $fail++; echo "FAIL: $m\n"; } }

$root = __DIR__ . '/..';
$theme = json_decode( file_get_contents( "$root/theme.json" ), true );
$ct = $theme['customTemplates'] ?? array();
$colophon = array_values( array_filter( $ct, fn( $t ) => ( $t['name'] ?? '' ) === 'page-colophon' ) );
ok( count( $colophon ) === 1, 'theme.json customTemplates has a page-colophon entry' );
ok( ( $colophon[0]['title'] ?? '' ) === 'Colophon', 'colophon template title is "Colophon"' );
ok( in_array( 'page', $colophon[0]['postTypes'] ?? array(), true ), 'colophon template applies to pages' );

ok( file_exists( "$root/templates/page-colophon.html" ), 'page-colophon.html template exists' );
ok( file_exists( "$root/patterns/colophon.php" ), 'colophon pattern exists' );

$tpl = file_get_contents( "$root/templates/page-colophon.html" );
ok( strpos( $tpl, 'wp:template-part' ) !== false && strpos( $tpl, 'header' ) !== false, 'template pulls the header part' );
ok( strpos( $tpl, 'signal-noise/colophon' ) !== false || strpos( $tpl, 'wp:pattern' ) !== false, 'template references the colophon pattern' );

$footer = file_get_contents( "$root/parts/footer.html" );
ok( strpos( $footer, '/colophon' ) !== false, 'footer links to /colophon' );

$pat = file_get_contents( "$root/patterns/colophon.php" );
ok( strpos( $pat, 'Slug: signal-noise/colophon' ) !== false, 'colophon pattern declares its slug' );

echo "Result: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
```

- [ ] **Step 2: Run → FAIL.**

- [ ] **Step 3: Implement.**

`patterns/colophon.php` (factual credits — anti-self-promotion; brutalist; edit copy to match the real stack):
```php
<?php
/**
 * Title: Colophon
 * Slug: signal-noise/colophon
 * Categories: signal-noise
 * Description: Factual colophon — stack, type, tooling, build. Anti-self-promotion by design.
 *
 * Added in theme v9.11.0 (B4). Static, editable in the Site Editor.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
?>
<!-- wp:group {"tagName":"section","className":"sn-colophon","layout":{"type":"constrained"}} -->
<section class="wp-block-group sn-colophon">
	<!-- wp:heading {"level":1,"className":"sn-colophon__title"} -->
	<h1 class="wp-block-heading sn-colophon__title">Colophon</h1>
	<!-- /wp:heading -->
	<!-- wp:paragraph -->
	<p>Signal &amp; Noise is a custom WordPress block theme. Type is set in Bebas Neue and DM Mono. Built and maintained in the open.</p>
	<!-- /wp:paragraph -->
	<!-- wp:list -->
	<ul class="wp-block-list">
		<!-- wp:list-item --><li>Platform — WordPress Full Site Editing</li><!-- /wp:list-item -->
		<!-- wp:list-item --><li>Type — Bebas Neue, DM Mono</li><!-- /wp:list-item -->
		<!-- wp:list-item --><li>Hosting — Cloudways, Cloudflare CDN</li><!-- /wp:list-item -->
	</ul>
	<!-- /wp:list -->
</section>
<!-- /wp:group -->
```

`templates/page-colophon.html`:
```html
<!-- wp:template-part {"slug":"header","tagName":"header"} /-->

<!-- wp:group {"tagName":"main","className":"sn-colophon-main","layout":{"type":"constrained"}} -->
<main class="wp-block-group sn-colophon-main">
	<!-- wp:pattern {"slug":"signal-noise/colophon"} /-->
</main>
<!-- /wp:group -->

<!-- wp:template-part {"slug":"footer","tagName":"footer"} /-->
```

`theme.json` — add to the `customTemplates` array (after the existing entries):
```json
{ "name": "page-colophon", "title": "Colophon", "postTypes": ["page"] }
```

`parts/footer.html` — add a quiet colophon link in the existing footer nav/links area (match the surrounding markup):
```html
<!-- wp:paragraph {"className":"sn-footer__colophon"} -->
<p class="sn-footer__colophon"><a href="/colophon/">Colophon</a></p>
<!-- /wp:paragraph -->
```

- [ ] **Step 4: Run → PASS** (`php tests/colophon-template.php`).

- [ ] **Step 5: Commit**

```bash
git add templates/page-colophon.html patterns/colophon.php tests/colophon-template.php theme.json parts/footer.html
git commit -m "feat(pages): /colophon FSE template + editable pattern + footer link"
```

> **Release-note reminder:** a published Page at `/colophon` must be created in wp-admin for the route + footer link to resolve (one-time manual step; note it in the CHANGELOG + handoff).

---

### Task 6: Reader-facing Notes-scoped command palette

**Files:**
- Create: `inc/command-palette.php`, `assets/js/command-palette.js`, `assets/css/command-palette.css`
- Create: `tests/command-palette.php`
- Modify: `functions.php`

**Load-bearing facts:** data island via `wp_add_inline_script(handle, …, 'before')` using **`wp_json_encode($data, JSON_HEX_TAG | JSON_UNESCAPED_SLASHES)`** (bare encode is not `</script>`-safe). Enqueue **site-wide** (no `is_singular`). Search = navigation to `/notes/?s=` (no REST). APG dialog+combobox a11y (focus trap, `aria-activedescendant`, Escape restores focus). Pillars from `sn_theme_pillar_descriptors()` (guarded); recent notes via bounded `WP_Query(posts_per_page=8)`.

- [ ] **Step 1: Write the failing test** — `tests/command-palette.php`

```php
<?php
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', '/' ); }
define( 'SN_CMDK_TEST', true );
$pass = 0; $fail = 0;
function ok( $c, $m ) { global $pass, $fail; if ( $c ) { $pass++; echo "PASS: $m\n"; } else { $fail++; echo "FAIL: $m\n"; } }

function add_action( $h, $cb, $p = 10, $a = 1 ) { return true; }
function home_url( $p = '' ) { return 'https://x.test' . $p; }
function get_permalink( $p = null ) { return 'https://x.test/notes/n' . ( is_object( $p ) ? $p->ID : $p ) . '/'; }
function get_the_title( $p = null ) { return 'A &amp; B'; }
function sn_theme_pillar_descriptors() { return array(
	array( 'slug' => 'provenance/over-detection', 'title' => 'Provenance Over Detection' ),
	array( 'slug' => 'provenance/as-substrate', 'title' => 'Provenance As Substrate' ),
); }
if ( ! function_exists( 'wp_json_encode' ) ) { function wp_json_encode( $v, $f = 0, $d = 512 ) { return json_encode( $v, $f, $d ); } }
$GLOBALS['__qargs'] = null;
class WP_Query {
	public $posts;
	public function __construct( $args ) { $GLOBALS['__qargs'] = $args; $this->posts = array( (object) array( 'ID' => 1 ), (object) array( 'ID' => 2 ) ); }
}
function wp_reset_postdata() {}

require __DIR__ . '/../inc/command-palette.php';

$data = sn_cmdk_build_data();
ok( isset( $data['notesUrl'], $data['recent'], $data['pillars'] ), 'data island has notesUrl/recent/pillars' );
ok( $data['notesUrl'] === 'https://x.test/notes/', 'notesUrl is /notes/' );
ok( count( $data['pillars'] ) === 2, 'pillars from descriptors' );
ok( $data['pillars'][0]['u'] === 'https://x.test/provenance/over-detection/', 'pillar slug → home_url(/slug/)' );
ok( $data['recent'][0]['t'] === 'A & B', 'recent titles HTML-decoded' );
ok( $GLOBALS['__qargs']['posts_per_page'] === 8, 'recent query bounded to 8' );
ok( $GLOBALS['__qargs']['no_found_rows'] === true, 'recent query uses no_found_rows' );
ok( $GLOBALS['__qargs']['post_status'] === 'publish', 'recent query is publish-only' );

// XSS contract: JSON_HEX_TAG neutralizes a </script> in a title.
$enc = wp_json_encode( array( 't' => '</script>' ), JSON_HEX_TAG | JSON_UNESCAPED_SLASHES );
ok( strpos( $enc, '</script>' ) === false, 'JSON_HEX_TAG escapes a closing script tag' );

echo "Result: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
```

- [ ] **Step 2: Run → FAIL.**

- [ ] **Step 3: Implement** — `inc/command-palette.php`

```php
<?php
/**
 * Signal & Noise — reader-facing Notes-scoped command palette.
 *
 * ⌘/Ctrl-K or "/" opens an accessible overlay: search notes (→ /notes/?s=),
 * jump to recent notes, jump to pillar pages. Front-end only (distinct from the
 * plugin's wp-admin @wordpress/commands palette). The data island uses
 * JSON_HEX_TAG so a note titled "</script>" can't break out of the inline tag.
 *
 * @package SignalNoise
 * @since 9.11.0
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function sn_cmdk_build_data() {
	$recent = array();
	$q = new WP_Query( array(
		'post_type'           => 'post',
		'post_status'         => 'publish',
		'posts_per_page'      => 8,
		'orderby'             => 'date',
		'order'               => 'DESC',
		'no_found_rows'       => true,
		'ignore_sticky_posts' => true,
	) );
	foreach ( $q->posts as $p ) {
		$recent[] = array( 't' => html_entity_decode( get_the_title( $p ), ENT_QUOTES ), 'u' => get_permalink( $p ) );
	}
	wp_reset_postdata();

	$pillars = array();
	if ( function_exists( 'sn_theme_pillar_descriptors' ) ) {
		foreach ( sn_theme_pillar_descriptors() as $d ) {
			$pillars[] = array( 't' => $d['title'], 'u' => home_url( '/' . $d['slug'] . '/' ) );
		}
	}
	return array(
		'notesUrl' => home_url( '/notes/' ),
		'recent'   => $recent,
		'pillars'  => $pillars,
	);
}

function sn_cmdk_enqueue() {
	wp_enqueue_style( 'sn-command-palette', get_theme_file_uri( 'assets/css/command-palette.css' ), array( 'sn-components' ), sn_asset_ver( 'assets/css/command-palette.css' ) );
	wp_enqueue_script( 'sn-command-palette', get_theme_file_uri( 'assets/js/command-palette.js' ), array(), sn_asset_ver( 'assets/js/command-palette.js' ), array( 'in_footer' => true, 'strategy' => 'defer' ) );
	$json = wp_json_encode( sn_cmdk_build_data(), JSON_HEX_TAG | JSON_UNESCAPED_SLASHES );
	wp_add_inline_script( 'sn-command-palette', 'window.SN_CMDK=' . $json . ';', 'before' );
}

function sn_cmdk_print_trigger() {
	echo '<button type="button" class="sn-cmdk-trigger" aria-haspopup="dialog" aria-controls="sn-cmdk" aria-keyshortcuts="Control+K Meta+K /">'
		. '<span class="sn-cmdk-trigger-label">Search</span>'
		. '<kbd class="sn-cmdk-hint" aria-hidden="true">⌘K</kbd></button>' . "\n";
}

if ( ! defined( 'SN_CMDK_TEST' ) || ! SN_CMDK_TEST ) {
	add_action( 'wp_enqueue_scripts', 'sn_cmdk_enqueue', 30 );
	add_action( 'wp_footer', 'sn_cmdk_print_trigger', 5 );
}
```

`assets/js/command-palette.js` — buildless ES5 IIFE implementing the APG dialog + combobox pattern (full structure per the spec/grounding: `open(trigger)` stores last focus + traps; `/` ignored in form fields; ⌘/Ctrl-K global with `preventDefault`; `aria-activedescendant` tracking with DOM focus staying on the input; Arrow/Enter/Escape; `activate` → `location.assign(notesUrl+'?s='+encodeURIComponent(q))` for the synthetic search row, else `item.u`; `textContent` only). The injected DOM:
```html
<div id="sn-cmdk" class="sn-cmdk" role="dialog" aria-modal="true" aria-label="Search and navigate" hidden>
	<div class="sn-cmdk-backdrop" data-close></div>
	<div class="sn-cmdk-panel" role="search">
		<input class="sn-cmdk-input" type="text" role="combobox" aria-expanded="true" aria-controls="sn-cmdk-list" aria-autocomplete="list" aria-activedescendant="" aria-label="Search notes" placeholder="Search notes, jump to a page…" autocomplete="off">
		<ul id="sn-cmdk-list" class="sn-cmdk-list" role="listbox" aria-label="Results"></ul>
	</div>
</div>
```

`assets/css/command-palette.css` — preset tokens only; `.sn-cmdk{position:fixed;inset:0;z-index:100002}` + `[hidden]{display:none}`; backdrop `color-mix(in srgb, var(--wp--preset--color--void) 82%, transparent)`; panel `var(--wp--preset--color--bone)` field; `[role=option].is-active{background:var(--wp--preset--color--blood);color:var(--wp--preset--color--bone)}`; `@media (prefers-reduced-motion: no-preference)` for the open animation only; `@media (pointer: coarse){.sn-cmdk-hint{display:none}}`; `.sn-cmdk-hint` ≥ 11px; trigger uses Bebas (`--font-family--heading`), uppercase.

- [ ] **Step 4: Run → PASS** (`php tests/command-palette.php`).

- [ ] **Step 5: Wire** `require_once __DIR__ . '/inc/command-palette.php';` after the `inc/assets-frontend.php` require + docblock line.

- [ ] **Step 6: Commit**

```bash
git add inc/command-palette.php assets/js/command-palette.js assets/css/command-palette.css tests/command-palette.php functions.php
git commit -m "feat(palette): reader-facing Notes-scoped command palette (⌘K / '/', accessible)"
```

---

### Task 7: Release commit (version + CHANGELOG)

**Files:** `style.css` (Version), `CHANGELOG.md`

- [ ] **Step 1: Bump `style.css`** `Version: 9.10.0` → `Version: 9.11.0`.
- [ ] **Step 2: Add the CHANGELOG entry** at the top, Mimestream format (mirror the v9.10.0 entry): `## [9.11.0] - 2026-06-07 — Feeds, blocks & pages` with a Headline + `### Added` (the six features, each with its `(file)` ref), `### Improvements` (the post-frontmatter Block Bindings migration), `### Tests` (six new fixtures + the updated total). Note the `/colophon` Page is a one-time manual creation step.
- [ ] **Step 3: Commit**

```bash
git add style.css CHANGELOG.md
git commit -m "v9.11.0: Feeds, blocks & pages — JSON Feed, RSS enrichment, sidenote/pull-quote blocks, post-field bindings, /colophon, reader command palette"
```

---

### Task 8: Gate (full sweep + falsified lint)

- [ ] **Step 1: Full test sweep** — run every `tests/*.php` (exclude `contracts-smoke.php`); confirm 26 suites, 0 failures (20 baseline + 6 new).

```bash
for t in tests/*.php; do [ "$(basename "$t")" = "contracts-smoke.php" ] && continue; php "$t" >/dev/null 2>&1 && echo "ok $t" || echo "FAIL $t"; done
```

- [ ] **Step 2: phpcs + FALSIFY** — `composer run lint` must be 0 errors; then inject a deliberate violation into one new file, re-run, confirm it's reported (proves coverage isn't a `.claude`-path exclude false-green), then revert.

```bash
composer run lint
```

- [ ] **Step 3:** If any failures, fix (TDD: red → green) and re-gate. Do NOT proceed to tag until green + falsified.

---

## Self-review (run after writing)

- **Spec coverage:** all 6 spec items → Tasks 1–6; versioning/CHANGELOG → Task 7; testing strategy → per-task tests + Task 8. ✓
- **Placeholders:** the palette JS/CSS bodies (Task 6 step 3) are specified structurally + by the grounded skeleton in the spec rather than reproduced line-for-line — the executor builds them from the spec's full skeleton (`wf_923523e4-1bc`). All PHP + tests are complete. Acceptable: the JS is buildless ES5 with an exact DOM + behavior spec.
- **Name consistency:** `sn_feed_json_*`, `sn_rss_media_ns`/`sn_rss_item_enrich`, `signal_noise_register_block*`/`signal_noise_block_category`, `sn_post_field_binding_value`/`sn_register_post_field_binding`, `sn_cmdk_build_data`/`sn_cmdk_enqueue`/`sn_cmdk_print_trigger` — consistent across tasks + tests. ✓
