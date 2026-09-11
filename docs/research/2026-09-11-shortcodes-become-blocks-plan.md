# The shortcodes become blocks — implementation plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Every piece of template furniture that is a shortcode today becomes a real block or a bound paragraph, byte-identical on the page; the provenance chip and closing part are inserted by rule; `/notes/*` prerenders. Theme 13.1.0.

**Architecture:** Two bound paragraphs reuse the existing `signal-noise/post-field` source (the v9.11.0 markup, re-applied, plus a `slug` arg). Eight renderers become WordPress 7.0 PHP-only blocks (`supports.autoRegister`, `render_callback` wrapping the existing function). One `hooked_block_types` filter enforces chip + closing on `single`. Two template-part areas, one speculation-rules filter. Spec: `docs/research/2026-09-11-shortcodes-become-blocks-design.md`.

**Tech Stack:** WordPress 7.0+ block theme, PHP 8.3, standalone test sweep (`bash tests/run.sh`, no WP bootstrap — tests stub `register_block_type`, `add_filter`, etc. the way `tests/block-bindings.php` and `tests/editor-block-palette.php` do). Repo: `signal-and-noise` (theme only).

**Spec amendments decided while planning (applied in Task 0):**
- The spec's "render `parts/post-frontmatter.html` through `do_blocks()` with the real source" needs a WordPress bootstrap the sweep does not have. Replaced by `tests/block-bindings-templates.php`: a parse-level pin over every template/part — each `signal-noise/post-field` binding names a key the source implements (derived from the source file), its `<p>` inner is empty (bindings fill it), and no migrated shortcode string survives — plus the source exercised with a `$block_instance` context (already in `tests/block-bindings.php`). The v9.11.1 fatal class is a mistyped core call; PHPStan (required) and the stub-parity sweep own that.
- `[sn_reading_time]` is registered by the **plugin** (`inc/reading-time-*.php`); the theme's binding calls `sn_get_reading_time()` behind `function_exists`. Nothing changes in the plugin; the theme simply stops emitting the shortcode.
- The renderers read the **queried** post (`get_queried_object_id()`, `get_the_ID()`, `get_post()`). On `single` the queried id and the block context `postId` are the same post, so parity is exact by construction; the render callbacks still honour a differing context id (`setup_postdata`) so a future Query Loop placement is correct.

---

## File structure

| File | Responsibility |
|---|---|
| `inc/block-bindings.php` (modify) | `slug` arg resolves a post for `reading_time` |
| `inc/blocks-php-only.php` (create) | the eight `register_block_type()` calls + eight render callbacks + one context helper |
| `inc/block-hooks.php` (create) | `hooked_block_types` filter + the `hooked_block_core/template-part` slug filter |
| `inc/speculation.php` (create) | the two speculation-rules filters |
| `inc/template-part-areas.php` (create) | `default_wp_template_part_areas` → `article` |
| `inc/editor-block-palette.php` (modify) | eight names join `$theme_blocks` |
| `functions.php` (modify) | four requires |
| `theme.json` (modify) | two `templateParts` entries |
| `parts/post-frontmatter.html`, `parts/post-closing.html`, `parts/header.html`, `parts/footer.html`, `templates/single.html`, `templates/home.html`, `templates/page-notes.html` (modify) | shortcodes → blocks / bindings |
| `tests/block-bindings.php` (modify), `tests/block-bindings-templates.php`, `tests/blocks-php-only.php`, `tests/block-hooks.php`, `tests/template-part-areas.php`, `tests/speculation.php` (create), `tests/editor-block-palette.php` (modify) | pins |
| `CHANGELOG.md` | `[Unreleased]` bullets |

---

### Task 0: Capture the live baseline, amend the spec, verify the 7.1 Icon API

**Files:**
- Create: `docs/research/baselines/2026-09-11-single-note-frontmatter-closing.html` (capture; committed so the post-release diff has a fixed reference)
- Modify: `docs/research/2026-09-11-shortcodes-become-blocks-design.md`

- [ ] **Step 1: Capture the pre-release HTML of one signed note's furniture** (the parity reference for Task 9)

```bash
mkdir -p docs/research/baselines
curl -sL 'https://juanlentino.com/notes/the-signer-keeps-moving/' -A 'Mozilla/5.0 (baseline)' \
  | python3 -c "
import sys,re
h=sys.stdin.read()
for cls in ('sn-post-frontmatter','sn-post-closing','sn-related-notes','sn-cited-by'):
    m=re.search(r'(<[a-z]+ [^>]*class=\"[^\"]*'+cls+'[^\"]*\"[^>]*>.*?)(?=<(?:section|div|footer) [^>]*class=\"[^\"]*(?:sn-post-closing|sn-related-notes|sn-cited-by|sn-site-footer)|</main>)',h,re.S)
    print('<!-- '+cls+' -->'); print(m.group(1) if m else '<!-- not found -->')
" > docs/research/baselines/2026-09-11-single-note-frontmatter-closing.html
wc -c docs/research/baselines/2026-09-11-single-note-frontmatter-closing.html
```
Expected: a few KB, four `<!-- … -->` markers, none "not found". If a marker is not found, read the live HTML and fix the class names in the capture — the capture must hold the front-matter, the closing part, related notes and cited-by as served today.

- [ ] **Step 2: Amend the spec** — replace the "renders `parts/post-frontmatter.html` through `do_blocks()`" paragraph in §1 with the parse-level pin described in this plan's header, and add under §2: "`[sn_reading_time]` is the plugin's shortcode; the theme stops emitting it, the plugin keeps it."

- [ ] **Step 3: Verify the 7.1 Icon API on an installed core**

```bash
CORE="/Users/juanlentino/Library/Application Support/Studio/server-files/wordpress-versions/latest"
grep -rn "wp_register_icon\|register_icon_collection\|function wp_.*icon" "$CORE/wp-includes/"*.php "$CORE/wp-includes/blocks/icon/"* 2>/dev/null | head; ls "$CORE/wp-includes/blocks/icon" 2>/dev/null; grep -m1 'wp_version =' "$CORE/wp-includes/version.php"
```
Decision rule: if the installed core is 7.1+ AND a registration function exists AND `wp-includes/blocks/icon/block.json` shows the attribute that names an icon by slug, Task 8 runs; otherwise Task 8 is dropped and the spec §6 gets one line saying what was found. Record the finding in the spec either way.

- [ ] **Step 4: Commit**

```bash
git add docs/research
git commit -m "docs: baseline capture + spec amendments for the shortcodes-become-blocks arc"
```

---

### Task 1: Bindings — the `slug` arg

**Files:**
- Modify: `inc/block-bindings.php` (the `$post_id` resolution block, lines ~8–17)
- Test: `tests/block-bindings.php` (append before the Result line)

- [ ] **Step 1: Write the failing test**

Append before the final `Result:` echo in `tests/block-bindings.php` (the file stubs `get_post()` → id 7 and `sn_get_reading_time()` → `$GLOBALS['__rt']`; add the `get_page_by_path` stub next to the other stubs at the top):

```php
// v13.1.0: a slug arg names the post explicitly (the pillar cards on /notes).
if ( ! function_exists( 'get_page_by_path' ) ) {
	function get_page_by_path( $slug, $output = OBJECT, $type = 'page' ) {
		return isset( $GLOBALS['__by_slug'][ $slug ] ) ? (object) array( 'ID' => $GLOBALS['__by_slug'][ $slug ] ) : null;
	}
}
if ( ! defined( 'OBJECT' ) ) { define( 'OBJECT', 'OBJECT' ); }
```

and, at the end:

```php
echo "\nGroup: slug arg (v13.1.0)\n";
$GLOBALS['__by_slug'] = array( 'cheap-option' => 42 );
$GLOBALS['__rt_by_id'] = array( 42 => 9, 7 => 3 );
// Make the reading-time stub id-aware for this group only.
$GLOBALS['__rt'] = 3;
ok( '3 min read' === sn_post_field_binding_value( array( 'key' => 'reading_time' ) ), 'control: no slug → the global post (7)' );
$GLOBALS['__rt'] = 9;
ok( '9 min read' === sn_post_field_binding_value( array( 'key' => 'reading_time', 'slug' => 'cheap-option' ) ), 'slug resolves to that post and its reading time' );
ok( null === sn_post_field_binding_value( array( 'key' => 'reading_time', 'slug' => 'no-such-note' ) ), 'an unknown slug is null (the paragraph renders empty), never the global post' );
$ctx = (object) array( 'context' => array( 'postId' => 7 ) );
ok( '9 min read' === sn_post_field_binding_value( array( 'key' => 'reading_time', 'slug' => 'cheap-option' ), $ctx ), 'slug outranks block context — the card names the note, the loop does not' );
```

If `sn_get_reading_time` in this file ignores its id argument, the "9 min read" pins pass for the wrong reason. Make the stub id-aware: `function sn_get_reading_time( $id = null ) { return $GLOBALS['__rt_by_id'][ (int) $id ] ?? $GLOBALS['__rt']; }` — edit the existing stub, and confirm the pre-existing pins still pass (they set `$GLOBALS['__rt']` and use id 7, which is not in `__rt_by_id` unless you add it; set `__rt_by_id` to `array( 42 => 9 )` only, so 7 falls through to `__rt`).

- [ ] **Step 2: Run it to verify it fails**

Run: `php tests/block-bindings.php | grep -E "FAIL|Result"`
Expected: FAIL on "slug resolves", "unknown slug", "slug outranks"; `Result: … 3 failed`.

- [ ] **Step 3: Implement**

In `inc/block-bindings.php`, replace the `$post_id` resolution:

```php
	$post_id = 0;
	// v13.1.0: an explicit slug names the post (the pillar cards on /notes
	// address a note by slug, outside any loop). It outranks block context:
	// the card names the note, the surrounding loop does not. Unknown slug →
	// null, so the paragraph renders empty — never the global post by accident.
	if ( isset( $source_args['slug'] ) && '' !== (string) $source_args['slug'] ) {
		$by_slug = function_exists( 'get_page_by_path' ) ? get_page_by_path( sanitize_title( (string) $source_args['slug'] ), OBJECT, 'post' ) : null;
		if ( ! $by_slug ) {
			return null;
		}
		$post_id = (int) $by_slug->ID;
	} elseif ( $block_instance && ! empty( $block_instance->context['postId'] ) ) {
		$post_id = (int) $block_instance->context['postId'];
	} else {
		$p = get_post();
		if ( $p ) {
			$post_id = (int) $p->ID;
		}
	}
```

Add `sanitize_title` to the test's stubs if missing: `function sanitize_title( $s ) { return strtolower( preg_replace( '/[^a-z0-9-]+/i', '-', (string) $s ) ); }`.

- [ ] **Step 4: Run to verify it passes** — `php tests/block-bindings.php | tail -1` → `0 failed`. phpcs: `vendor/bin/phpcs --standard=phpcs.xml.dist inc/block-bindings.php`.

- [ ] **Step 5: Commit**

```bash
git add inc/block-bindings.php tests/block-bindings.php
git commit -m "feat(bindings): a slug arg names the post for signal-noise/post-field"
```

---

### Task 2: Bindings — the templates, and the parse-level pin

**Files:**
- Modify: `parts/post-frontmatter.html:15`, `templates/home.html:70`, `templates/page-notes.html:38,60,95`
- Create: `tests/block-bindings-templates.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
/**
 * Tests: every signal-noise/post-field binding in the templates names a key the
 * source implements, leaves its <p> empty for the source to fill, and no
 * migrated shortcode survives. Parse-level: the sweep has no WordPress to run
 * do_blocks(); the source's own behaviour is pinned in tests/block-bindings.php.
 * @since theme v13.1.0
 */
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }
$pass = 0; $fail = 0;
function ok( $c, $m ) { global $pass, $fail; if ( $c ) { $pass++; echo "PASS: $m\n"; } else { $fail++; echo "FAIL: $m\n"; } }
$root = dirname( __DIR__ );

// Keys the source implements — derived from the source, never listed by hand.
$src  = (string) file_get_contents( $root . '/inc/block-bindings.php' );
preg_match_all( "/case '([a-z_]+)':/", $src, $km );
$keys = array_unique( $km[1] );
ok( in_array( 'reading_time', $keys, true ) && in_array( 'pillar', $keys, true ), 'the source implements reading_time and pillar (' . implode( ',', $keys ) . ')' );

$files = array_merge( glob( $root . '/templates/*.html' ), glob( $root . '/parts/*.html' ) );
$bound = 0; $bad = array();
foreach ( $files as $f ) {
	$h = (string) file_get_contents( $f );
	$rel = str_replace( $root . '/', '', $f );
	// Every bound paragraph: the block comment JSON + the <p …>…</p> that follows.
	if ( preg_match_all( '/<!-- wp:paragraph (\{.*?"bindings".*?\}) -->\s*<p[^>]*>(.*?)<\/p>/s', $h, $m, PREG_SET_ORDER ) ) {
		foreach ( $m as $one ) {
			$bound++;
			$json = json_decode( $one[1], true );
			$b    = $json['metadata']['bindings']['content'] ?? null;
			if ( ! is_array( $b ) || 'signal-noise/post-field' !== ( $b['source'] ?? '' ) ) { $bad[] = "$rel: a bound paragraph does not use signal-noise/post-field"; continue; }
			if ( ! in_array( $b['args']['key'] ?? '', $keys, true ) ) { $bad[] = "$rel: key '" . ( $b['args']['key'] ?? '' ) . "' is not implemented by the source"; }
			if ( '' !== trim( $one[2] ) ) { $bad[] = "$rel: bound <p> is not empty (bindings fill it; static text would be overwritten or, worse, shown when the source returns null)"; }
		}
	}
}
ok( 7 === $bound, "seven bound paragraphs across templates/parts (found $bound): front-matter rt + pillar, home card rt, notes card rt, two pillar cards, and nothing else" );
ok( array() === $bad, 'every binding is well-formed' . ( $bad ? "\n  - " . implode( "\n  - ", $bad ) : '' ) );

$gone = array( '[sn_reading_time', '[sn_post_pillar' );
$left = array();
foreach ( $files as $f ) { foreach ( $gone as $s ) { if ( false !== strpos( (string) file_get_contents( $f ), $s ) ) { $left[] = str_replace( $root . '/', '', $f ) . " still emits $s"; } } }
ok( array() === $left, 'no template or part emits [sn_reading_time] or [sn_post_pillar] any more' . ( $left ? "\n  - " . implode( "\n  - ", $left ) : '' ) );

// Negative control: a planted static inner is caught.
$probe = '<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"signal-noise/post-field","args":{"key":"reading_time"}}}}} --><p class="x">3 min read</p><!-- /wp:paragraph -->';
preg_match( '/<!-- wp:paragraph (\{.*?"bindings".*?\}) -->\s*<p[^>]*>(.*?)<\/p>/s', $probe, $pm );
ok( '' !== trim( $pm[2] ), 'control: the parser sees a non-empty inner when one is planted' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
```

- [ ] **Step 2: Run it to verify it fails** — `php tests/block-bindings-templates.php | grep -E "FAIL|Result"` → FAIL on "seven bound paragraphs (found 0)" and "still emits".

- [ ] **Step 3: Migrate the seven slots**

`parts/post-frontmatter.html` line 15 — replace the paragraph with the reading-time shortcode by the v9.11.0 shape, KEEPING today's className/style/textColor/fontFamily attributes exactly:

```html
	<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"signal-noise/post-field","args":{"key":"reading_time"}}}},"className":"sn-post-frontmatter__rt","style":{"spacing":{"margin":{"top":"0","bottom":"0"}}},"textColor":"blood","fontFamily":"body"} -->
	<p class="sn-post-frontmatter__rt has-blood-color has-text-color has-body-font-family" style="margin-top:0;margin-bottom:0"></p>
	<!-- /wp:paragraph -->
```

`parts/post-frontmatter.html` line 25 (`[sn_post_pillar]`): the same treatment with `"args":{"key":"pillar"}` and the pillar paragraph's own className/style (read them from the line above the shortcode; `git show a683390 -- parts/post-frontmatter.html` has the exact v9.11.0 form — reuse it, it was correct; only the source had the typo).

`templates/home.html:70` and `templates/page-notes.html:95` (the card loops inside `wp:post-template`): same shape, key `reading_time`, no `slug` — the loop context supplies `postId`. Keep each paragraph's own className/style.

`templates/page-notes.html:38` and `:60` (the two pillar cards, `[sn_reading_time slug="…"]`): same shape with `"args":{"key":"reading_time","slug":"<the slug from the shortcode>"}`.

In every case the `<p>` inner becomes empty.

- [ ] **Step 4: Run to verify it passes** — `php tests/block-bindings-templates.php | tail -1` → `0 failed`. Then `php tests/notes-index-row.php | tail -1`, `php tests/page-notes-*.php | tail -1` (whatever page-notes suites exist — `ls tests | grep notes`), all green.

- [ ] **Step 5: Commit**

```bash
git add parts/post-frontmatter.html templates/home.html templates/page-notes.html tests/block-bindings-templates.php
git commit -m "feat(templates): reading time and pillar are bound paragraphs — the v9.11.0 migration, retried with its pins"
```

---

### Task 3: PHP-only blocks — the module and the parity pins

**Files:**
- Create: `inc/blocks-php-only.php`
- Create: `tests/blocks-php-only.php`
- Modify: `functions.php` (require after `inc/blocks-register.php`)

- [ ] **Step 1: Write the failing test**

```php
<?php
/**
 * Tests: the eight PHP-only blocks (WordPress 7.0 supports.autoRegister) —
 * registered as declared, each render === its shortcode's output on the same
 * post (THE PARITY PIN), a differing block-context id is honoured, and the
 * palette lists them all.
 * @since theme v13.1.0
 */
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', '/' ); }
$pass = 0; $fail = 0;
function ok( $c, $m ) { global $pass, $fail; if ( $c ) { $pass++; echo "PASS: $m\n"; } else { $fail++; echo "FAIL: $m\n"; } }

// ── WP stubs ──
$GLOBALS['__blocks'] = array();
function register_block_type( $name, $args = array() ) { $GLOBALS['__blocks'][ $name ] = $args; return (object) $args; }
function add_action( $h, $cb, $p = 10, $a = 1 ) { if ( 'init' === $h ) { $GLOBALS['__init'][] = $cb; } return true; }
function add_filter( $h, $cb, $p = 10, $a = 1 ) { return true; }
function __( $s, $d = null ) { return $s; }
$GLOBALS['__the_id'] = 7; $GLOBALS['__queried'] = 7; $GLOBALS['__setup'] = array();
function get_the_ID() { return $GLOBALS['__the_id']; }
function get_queried_object_id() { return $GLOBALS['__queried']; }
function get_post( $id = null ) { return (object) array( 'ID' => null === $id ? $GLOBALS['__the_id'] : (int) $id ); }
function setup_postdata( $p ) { $GLOBALS['__setup'][] = 'setup:' . $p->ID; $GLOBALS['__the_id'] = (int) $p->ID; return true; }
function wp_reset_postdata() { $GLOBALS['__setup'][] = 'reset'; $GLOBALS['__the_id'] = 7; }
// The renderers, stubbed to echo which post they saw — parity is about the WRAPPER.
function sn_prov_chip_shortcode()      { return '<chip:' . get_the_ID() . '>'; }
function sn_prov_panel_shortcode()     { return '<panel:' . get_the_ID() . '>'; }
function sn_related_notes_shortcode()  { return '<related:' . get_queried_object_id() . '>'; }
function sn_cited_by_shortcode()       { return '<cited:' . get_queried_object_id() . '>'; }
function sn_note_share_shortcode()     { return '<share:' . get_queried_object_id() . '>'; }
function sn_note_reply_shortcode()     { return '<reply:' . get_queried_object_id() . '>'; }
function sn_updated_date_shortcode()   { return '<updated:' . get_post()->ID . '>'; }
function sn_dark_mode_toggle_markup( $atts = array() ) { return '<toggle:' . ( $atts['placement'] ?? 'footer' ) . '>'; }

require_once __DIR__ . '/../inc/blocks-php-only.php';
foreach ( $GLOBALS['__init'] ?? array() as $cb ) { $cb(); }

$expected = array( 'prov-chip', 'prov-panel', 'related-notes', 'cited-by', 'note-share', 'note-reply', 'updated-date', 'theme-toggle' );
echo "Group: registration\n";
foreach ( $expected as $slug ) {
	$b = $GLOBALS['__blocks'][ 'signal-noise/' . $slug ] ?? null;
	ok( is_array( $b ) && true === ( $b['supports']['autoRegister'] ?? null ) && is_callable( $b['render_callback'] ?? null ) && 3 === ( $b['api_version'] ?? 0 ), "signal-noise/$slug registered PHP-only (autoRegister, render_callback, api_version 3)" );
}
ok( 8 === count( array_filter( array_keys( $GLOBALS['__blocks'] ), static fn( $n ) => str_starts_with( $n, 'signal-noise/' ) ) ), 'exactly eight blocks registered by this module' );
foreach ( array( 'prov-chip', 'prov-panel', 'related-notes', 'cited-by', 'note-share', 'note-reply' ) as $slug ) {
	ok( false === ( $GLOBALS['__blocks'][ 'signal-noise/' . $slug ]['supports']['multiple'] ?? true ), "$slug is single-instance (multiple => false)" );
}
ok( ! isset( $GLOBALS['__blocks']['signal-noise/theme-toggle']['supports']['multiple'] ), 'theme-toggle allows two instances (header and footer)' );
ok( array( 'placement' ) === array_keys( $GLOBALS['__blocks']['signal-noise/theme-toggle']['attributes'] ?? array() ) && array( 'header', 'footer' ) === ( $GLOBALS['__blocks']['signal-noise/theme-toggle']['attributes']['placement']['enum'] ?? array() ), 'theme-toggle has one attribute, placement enum header|footer (auto inspector control)' );

echo "\nGroup: THE PARITY PIN — block output === shortcode output on the same post\n";
$blk = static function ( $slug, $ctx = array(), $attrs = array() ) { return ( $GLOBALS['__blocks'][ 'signal-noise/' . $slug ]['render_callback'] )( $attrs, '', (object) array( 'context' => $ctx ) ); };
$pairs = array( 'prov-chip' => 'sn_prov_chip_shortcode', 'prov-panel' => 'sn_prov_panel_shortcode', 'related-notes' => 'sn_related_notes_shortcode', 'cited-by' => 'sn_cited_by_shortcode', 'note-share' => 'sn_note_share_shortcode', 'note-reply' => 'sn_note_reply_shortcode', 'updated-date' => 'sn_updated_date_shortcode' );
foreach ( $pairs as $slug => $fn ) {
	ok( $blk( $slug, array( 'postId' => 7 ) ) === $fn(), "$slug === {$fn}() when context is the queried post" );
	ok( $blk( $slug ) === $fn(), "$slug === {$fn}() with no context at all (a template outside any loop)" );
}
ok( $blk( 'theme-toggle', array(), array( 'placement' => 'header' ) ) === sn_dark_mode_toggle_markup( array( 'placement' => 'header' ) ), 'theme-toggle passes placement through' );
ok( $blk( 'theme-toggle' ) === sn_dark_mode_toggle_markup(), 'theme-toggle with no attribute renders the footer form' );

echo "\nGroup: a differing context id is honoured (a future Query Loop placement)\n";
$GLOBALS['__setup'] = array();
$out = $blk( 'prov-chip', array( 'postId' => 42 ) );
ok( '<chip:42>' === $out, 'the chip rendered for the CONTEXT post, not the global' );
ok( array( 'setup:42', 'reset' ) === $GLOBALS['__setup'], 'setup_postdata/wp_reset_postdata bracketed the render' );
ok( 7 === get_the_ID(), 'and the global is restored afterwards' );
$GLOBALS['__setup'] = array();
$blk( 'prov-chip', array( 'postId' => 7 ) );
ok( array() === $GLOBALS['__setup'], 'a context equal to the global does NOT re-setup (no churn on single)' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
```

- [ ] **Step 2: Run it to verify it fails** — fatal on the missing module.

- [ ] **Step 3: Implement**

Create `inc/blocks-php-only.php`:

```php
<?php
/**
 * Signal & Noise — the template furniture as PHP-only blocks (WordPress 7.0).
 *
 * Eight renderers that were [shortcodes] inside block templates become real
 * blocks: visible by name in the site editor, movable, styleable — with NO
 * JavaScript build. WordPress 7.0's `supports.autoRegister` exposes a
 * server-rendered block to the editor from its PHP registration alone.
 *
 * THE CONTRACT IS PARITY. Every render_callback returns the SAME string the
 * shortcode returns for the same post — tests/blocks-php-only.php pins it
 * per block. The shortcodes stay registered through 13.1.x for anything
 * typed into post content; 13.2.0 retires them.
 *
 * Context: the renderers read the queried post. On `single` that is the
 * block's `postId` context, so parity is exact. A differing context id (a
 * future Query Loop placement) is honoured by bracketing the render in
 * setup_postdata()/wp_reset_postdata() — only when it differs, so single
 * pays nothing.
 *
 * @since 13.1.0
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Run a renderer for the block's context post: set the global post to the
 * context id only when it differs from the current one, restore after.
 *
 * @param object   $block    WP_Block (or a stand-in exposing ->context).
 * @param callable $renderer The shortcode renderer.
 * @param array    $args     Arguments for the renderer.
 * @return string
 */
function sn_php_block_render_for_context( $block, callable $renderer, array $args = array() ) {
	$ctx_id = ( is_object( $block ) && ! empty( $block->context['postId'] ) ) ? (int) $block->context['postId'] : 0;
	if ( $ctx_id > 0 && $ctx_id !== (int) get_the_ID() ) {
		$post = get_post( $ctx_id );
		if ( $post ) {
			setup_postdata( $post );
			$out = (string) $renderer( ...$args );
			wp_reset_postdata();
			return $out;
		}
	}
	return (string) $renderer( ...$args );
}

function sn_block_render_prov_chip( $attributes, $content, $block )     { return sn_php_block_render_for_context( $block, 'sn_prov_chip_shortcode' ); }
function sn_block_render_prov_panel( $attributes, $content, $block )    { return sn_php_block_render_for_context( $block, 'sn_prov_panel_shortcode' ); }
function sn_block_render_related_notes( $attributes, $content, $block ) { return sn_php_block_render_for_context( $block, 'sn_related_notes_shortcode' ); }
function sn_block_render_cited_by( $attributes, $content, $block )      { return sn_php_block_render_for_context( $block, 'sn_cited_by_shortcode' ); }
function sn_block_render_note_share( $attributes, $content, $block )    { return sn_php_block_render_for_context( $block, 'sn_note_share_shortcode' ); }
function sn_block_render_note_reply( $attributes, $content, $block )    { return sn_php_block_render_for_context( $block, 'sn_note_reply_shortcode' ); }
function sn_block_render_updated_date( $attributes, $content, $block )  { return sn_php_block_render_for_context( $block, 'sn_updated_date_shortcode' ); }
function sn_block_render_theme_toggle( $attributes, $content, $block ) {
	$placement = ( isset( $attributes['placement'] ) && 'header' === $attributes['placement'] ) ? 'header' : 'footer';
	return (string) sn_dark_mode_toggle_markup( array( 'placement' => $placement ) );
}

/**
 * Register the eight blocks. api_version 3, category signal-noise (the
 * custom blocks' category), autoRegister on, html off. multiple => false
 * where a second instance is meaningless; the toggle is placed twice.
 */
function sn_register_php_only_blocks() {
	if ( ! function_exists( 'register_block_type' ) ) { return; }
	$common = array( 'api_version' => 3, 'category' => 'signal-noise', 'supports' => array( 'autoRegister' => true, 'html' => false, 'multiple' => false ) );
	$blocks = array(
		'prov-chip'     => array( 'title' => __( 'Provenance chip', 'signal-and-noise' ),    'icon' => 'shield',            'description' => __( 'The byline verification chip of a signed note.', 'signal-and-noise' ),                 'render_callback' => 'sn_block_render_prov_chip' ),
		'prov-panel'    => array( 'title' => __( 'Provenance record', 'signal-and-noise' ),  'icon' => 'shield-alt',        'description' => __( 'The expandable provenance record of a signed note.', 'signal-and-noise' ),              'render_callback' => 'sn_block_render_prov_panel' ),
		'related-notes' => array( 'title' => __( 'Related notes', 'signal-and-noise' ),      'icon' => 'networking',        'description' => __( 'Notes the kernel ranks closest to this one.', 'signal-and-noise' ),                     'render_callback' => 'sn_block_render_related_notes' ),
		'cited-by'      => array( 'title' => __( 'Cited by', 'signal-and-noise' ),           'icon' => 'admin-links',       'description' => __( 'Verified webmentions that cite this note.', 'signal-and-noise' ),                        'render_callback' => 'sn_block_render_cited_by' ),
		'note-share'    => array( 'title' => __( 'Share this note', 'signal-and-noise' ),    'icon' => 'share',             'description' => __( 'The share row for a note.', 'signal-and-noise' ),                                       'render_callback' => 'sn_block_render_note_share' ),
		'note-reply'    => array( 'title' => __( 'Reply by email', 'signal-and-noise' ),     'icon' => 'email',             'description' => __( 'The reply-by-email line for a note.', 'signal-and-noise' ),                             'render_callback' => 'sn_block_render_note_reply' ),
		'updated-date'  => array( 'title' => __( 'Updated date', 'signal-and-noise' ),       'icon' => 'clock',             'description' => __( 'The last-updated line, shown only when a note was amended.', 'signal-and-noise' ),      'render_callback' => 'sn_block_render_updated_date' ),
	);
	foreach ( $blocks as $slug => $args ) {
		register_block_type( 'signal-noise/' . $slug, array_merge( $common, $args, array( 'uses_context' => array( 'postId' ) ) ) );
	}
	// The toggle: placed in both header and footer, so `multiple` stays open,
	// and its one attribute gets an auto-generated inspector control (7.0).
	register_block_type( 'signal-noise/theme-toggle', array(
		'api_version'     => 3,
		'category'        => 'signal-noise',
		'title'           => __( 'High-contrast toggle', 'signal-and-noise' ),
		'icon'            => 'visibility',
		'description'     => __( 'The high-contrast palette switch.', 'signal-and-noise' ),
		'attributes'      => array( 'placement' => array( 'type' => 'string', 'enum' => array( 'header', 'footer' ), 'default' => 'footer' ) ),
		'supports'        => array( 'autoRegister' => true, 'html' => false ),
		'render_callback' => 'sn_block_render_theme_toggle',
	) );
}
add_action( 'init', 'sn_register_php_only_blocks', 11 ); // after inc/blocks-register.php's own init (priority 10) so the category exists.
```

`functions.php`: after the `require_once __DIR__ . '/inc/blocks-register.php';` line add
`require_once __DIR__ . '/inc/blocks-php-only.php'; // v13.1.0: the template furniture as PHP-only blocks (WP 7.0 autoRegister)` and a line in the header comment's file list.

Check how `inc/blocks-register.php` registers the `signal-noise` block category (`block_categories_all`) — if it does so on a different hook, mirror the priority so the category exists before these register; if `wp_block_type_supports` / stub-parity complains about `autoRegister` being unknown to the pinned stubs, it is a `supports` array key, not a function — no stub involved.

- [ ] **Step 4: Run to verify it passes** — `php tests/blocks-php-only.php | tail -1` → `0 failed` (expected 8 + 1 + 6 + 2 + 14 + 2 + 4 = 37). phpcs the module. `php tests/stub-parity*.php` or `php tools/stub-parity.php` if the theme has it → green.

- [ ] **Step 5: Commit**

```bash
git add inc/blocks-php-only.php tests/blocks-php-only.php functions.php
git commit -m "feat(blocks): eight PHP-only blocks wrap the template furniture renderers — parity pinned per block"
```

---

### Task 4: Swap the shortcodes in the templates; curate the palette

**Files:**
- Modify: `parts/post-frontmatter.html:7,29`, `parts/post-closing.html:11,39,43`, `templates/single.html:44,51`, `parts/header.html:27`, `parts/footer.html:63`
- Modify: `inc/editor-block-palette.php:181-184`
- Modify: `tests/editor-block-palette.php`, `tests/blocks-php-only.php` (append)

- [ ] **Step 1: Write the failing pins** — append to `tests/blocks-php-only.php` before the Result line:

```php
echo "\nGroup: the templates place the blocks, not the shortcodes\n";
$root = dirname( __DIR__ );
$files = array_merge( glob( $root . '/templates/*.html' ), glob( $root . '/parts/*.html' ) );
$migrated = array( 'sn_prov_chip', 'sn_prov_panel', 'sn_related_notes', 'sn_cited_by', 'sn_note_share', 'sn_note_reply', 'sn_updated_date', 'sn_theme_toggle' );
$left = array(); $placed = array();
foreach ( $files as $f ) {
	$h = (string) file_get_contents( $f ); $rel = str_replace( $root . '/', '', $f );
	foreach ( $migrated as $s ) { if ( false !== strpos( $h, '[' . $s ) ) { $left[] = "$rel [$s"; } }
	if ( preg_match_all( '/<!-- wp:signal-noise\/([a-z-]+)( \{[^}]*\})? \/-->/', $h, $m ) ) { foreach ( $m[1] as $n ) { $placed[ $n ] = ( $placed[ $n ] ?? 0 ) + 1; } }
}
ok( array() === $left, 'no template or part emits a migrated shortcode' . ( $left ? ' — LEFT: ' . implode( ', ', $left ) : '' ) );
ok( 1 === ( $placed['prov-chip'] ?? 0 ) && 1 === ( $placed['prov-panel'] ?? 0 ) && 1 === ( $placed['related-notes'] ?? 0 ) && 1 === ( $placed['cited-by'] ?? 0 ) && 1 === ( $placed['note-share'] ?? 0 ) && 1 === ( $placed['note-reply'] ?? 0 ) && 1 === ( $placed['updated-date'] ?? 0 ) && 2 === ( $placed['theme-toggle'] ?? 0 ), 'each block is placed exactly where its shortcode was (toggle twice): ' . json_encode( $placed ) );
$hdr = (string) file_get_contents( $root . '/parts/header.html' );
ok( false !== strpos( $hdr, '<!-- wp:signal-noise/theme-toggle {"placement":"header"} /-->' ), 'the header toggle carries placement:header' );
ok( false !== strpos( (string) file_get_contents( $root . '/templates/single.html' ), '[sn_reading_path]' ), 'the PLUGIN\'s [sn_reading_path] stays a shortcode — not this arc\'s' );
```

And in `tests/editor-block-palette.php`, find the pin that lists `signal-noise/sidenote` etc. in the allowed set and add the eight names to what it expects (read the suite; extend the expected list in place).

- [ ] **Step 2: Run to verify they fail** — the "no template emits" and "placed" pins red.

- [ ] **Step 3: Swap** — each `[shortcode]` line becomes a self-closing block on its own line, inside whatever wrapper the shortcode had. Where the shortcode sat inside a `wp:shortcode` block, replace the whole `<!-- wp:shortcode -->…<!-- /wp:shortcode -->` with the self-closing block; where it sat as bare text inside a `wp:paragraph` (updated-date does — see `inc/post-updated-date.php`'s render_block bridge), replace the paragraph with the block:

```html
<!-- wp:signal-noise/prov-chip /-->
<!-- wp:signal-noise/prov-panel /-->
<!-- wp:signal-noise/related-notes /-->
<!-- wp:signal-noise/cited-by /-->
<!-- wp:signal-noise/note-share /-->
<!-- wp:signal-noise/note-reply /-->
<!-- wp:signal-noise/updated-date /-->
<!-- wp:signal-noise/theme-toggle {"placement":"header"} /-->   (header)
<!-- wp:signal-noise/theme-toggle /-->                            (footer)
```

Keep every surrounding wrapper (`wp:group`, classes, spacing) exactly as it is — the parity diff in Task 9 compares the rendered furniture to the baseline.

`inc/editor-block-palette.php`: add the eight `signal-noise/<slug>` names to `$theme_blocks`.

- [ ] **Step 4: Run to verify** — `php tests/blocks-php-only.php | tail -1`, `php tests/editor-block-palette.php | tail -1`, `bash tests/run.sh | tail -1` all green.

- [ ] **Step 5: Commit**

```bash
git add parts templates inc/editor-block-palette.php tests/blocks-php-only.php tests/editor-block-palette.php
git commit -m "feat(templates): the template furniture is placed as blocks; the palette lists them"
```

---

### Task 5: Block Hooks — chip and closing by rule on `single`

**Files:**
- Create: `inc/block-hooks.php`, `tests/block-hooks.php`
- Modify: `functions.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
/**
 * Tests: hooked_block_types inserts the provenance chip after core/post-title
 * and the post-closing part after core/post-content — on the `single`
 * template only. The shipped template places both explicitly (WordPress
 * records ignoredHookedBlocks), so the hook is the ENFORCEMENT for a single
 * template that lacks them, never a duplicate.
 * @since theme v13.1.0
 */
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', '/' ); }
$pass = 0; $fail = 0;
function ok( $c, $m ) { global $pass, $fail; if ( $c ) { $pass++; echo "PASS: $m\n"; } else { $fail++; echo "FAIL: $m\n"; } }
$GLOBALS['__filters'] = array();
function add_filter( $h, $cb, $p = 10, $a = 1 ) { $GLOBALS['__filters'][ $h ][] = $cb; return true; }
class WP_Block_Template { public $slug; public $area = ''; public function __construct( $s ) { $this->slug = $s; } }
class WP_Block_Template_Part extends WP_Block_Template {}
require_once __DIR__ . '/../inc/block-hooks.php';
$hook = $GLOBALS['__filters']['hooked_block_types'][0] ?? null;
ok( is_callable( $hook ), 'hooked_block_types is filtered' );
$single = new WP_Block_Template( 'single' );
ok( array( 'signal-noise/prov-chip' ) === $hook( array(), 'after', 'core/post-title', $single ), 'single: the chip hooks after core/post-title' );
ok( array( 'core/template-part' ) === $hook( array(), 'after', 'core/post-content', $single ), 'single: a template part hooks after core/post-content' );
ok( array() === $hook( array(), 'before', 'core/post-title', $single ), 'not before the title' );
ok( array() === $hook( array(), 'after', 'core/post-date', $single ), 'not after some other anchor' );
foreach ( array( 'home', 'page', 'index', 'page-notes' ) as $s ) {
	ok( array() === $hook( array(), 'after', 'core/post-title', new WP_Block_Template( $s ) ), "$s: nothing hooks" );
}
ok( array() === $hook( array(), 'after', 'core/post-title', new WP_Block_Template_Part( 'post-frontmatter' ) ), 'a template PART is not single' );
ok( array() === $hook( array(), 'after', 'core/post-title', null ), 'post content (null context) is never hooked' );
ok( array( 'x/y', 'signal-noise/prov-chip' ) === $hook( array( 'x/y' ), 'after', 'core/post-title', $single ), 'appends, never replaces, what others hooked' );
// The part slug for the hooked core/template-part.
$slugger = $GLOBALS['__filters']['hooked_block_core/template-part'][0] ?? null;
ok( is_callable( $slugger ), 'hooked_block_core/template-part is filtered to name the part' );
$parsed = array( 'blockName' => 'core/template-part', 'attrs' => array(), 'innerBlocks' => array(), 'innerHTML' => '', 'innerContent' => array() );
$out = $slugger( $parsed, 'after', 'core/post-content', $single );
ok( 'post-closing' === ( $out['attrs']['slug'] ?? '' ) && 'article' === ( $out['attrs']['area'] ?? '' ), 'the hooked part is post-closing in the article area' );
$other = $slugger( $parsed, 'after', 'core/post-date', $single );
ok( $other === $parsed, 'an unrelated anchor leaves a hooked part untouched' );
echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
```

- [ ] **Step 2: Run to verify it fails** — fatal on the missing module.

- [ ] **Step 3: Implement**

```php
<?php
/**
 * Signal & Noise — Block Hooks: the provenance chip and the closing part are
 * inserted into the `single` template BY RULE.
 *
 * The shipped templates/single.html places both explicitly, and WordPress
 * records `ignoredHookedBlocks` on a template the moment it is saved, so this
 * never duplicates. It fires on a single template that LACKS them — a future
 * template, a reset, a fork — which is the whole point: "every signed note
 * carries its chip" stops being hoped-for and becomes enforced.
 *
 * Scope: WP_Block_Template with slug `single` only. Parts, patterns and post
 * content are never hooked (a chip inside a note's own body is not a chip).
 *
 * @since 13.1.0
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * @param string[]                        $hooked   Block names already hooked here.
 * @param string                          $position before|after|first_child|last_child.
 * @param string                          $anchor   The anchor block's name.
 * @param WP_Block_Template|array|null    $context  The template/part/pattern being rendered; null for post content.
 * @return string[]
 */
function sn_hooked_block_types( $hooked, $position, $anchor, $context ) {
	if ( ! ( $context instanceof WP_Block_Template ) || ( $context instanceof WP_Block_Template_Part ) || 'single' !== (string) $context->slug ) {
		return $hooked;
	}
	if ( 'after' !== $position ) {
		return $hooked;
	}
	if ( 'core/post-title' === $anchor ) {
		$hooked[] = 'signal-noise/prov-chip';
	} elseif ( 'core/post-content' === $anchor ) {
		$hooked[] = 'core/template-part';
	}
	return $hooked;
}
add_filter( 'hooked_block_types', 'sn_hooked_block_types', 10, 4 );

/**
 * A hooked core/template-part carries no slug of its own; name it. Only for
 * the anchor this file hooks it after.
 *
 * @param array|null  $parsed_hooked_block
 * @param string      $position
 * @param string      $anchor
 * @param mixed       $context
 * @return array|null
 */
function sn_hooked_block_post_closing( $parsed_hooked_block, $position, $anchor, $context ) {
	if ( ! is_array( $parsed_hooked_block ) || 'after' !== $position || 'core/post-content' !== $anchor ) {
		return $parsed_hooked_block;
	}
	if ( ! ( $context instanceof WP_Block_Template ) || 'single' !== (string) $context->slug ) {
		return $parsed_hooked_block;
	}
	$parsed_hooked_block['attrs']['slug'] = 'post-closing';
	$parsed_hooked_block['attrs']['area'] = 'article';
	return $parsed_hooked_block;
}
add_filter( 'hooked_block_core/template-part', 'sn_hooked_block_post_closing', 10, 4 );
```

`functions.php`: `require_once __DIR__ . '/inc/block-hooks.php'; // v13.1.0: chip + closing part by rule on single (Block Hooks)`.

Note `WP_Block_Template_Part` — confirm the class exists in the pinned stubs (`vendor/php-stubs/wordpress-stubs`); if core has no such class (parts are `WP_Block_Template` with `area` set), replace the `instanceof WP_Block_Template_Part` test with `! empty( $context->area )` and adjust the test's stand-in accordingly. Say which in the commit.

- [ ] **Step 4: Run to verify** — `php tests/block-hooks.php | tail -1` → `0 failed`; phpcs; phpstan on the module (`vendor/bin/phpstan analyse inc/block-hooks.php`).

- [ ] **Step 5: Commit**

```bash
git add inc/block-hooks.php tests/block-hooks.php functions.php
git commit -m "feat(hooks): the provenance chip and the closing part are hooked into single by rule"
```

---

### Task 6: Template-part areas

**Files:**
- Create: `inc/template-part-areas.php`, `tests/template-part-areas.php`
- Modify: `theme.json` (`templateParts`), `functions.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
/** Tests: post-frontmatter and post-closing are declared template parts in the `article` area, and the area is registered. @since theme v13.1.0 */
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', '/' ); }
$pass = 0; $fail = 0;
function ok( $c, $m ) { global $pass, $fail; if ( $c ) { $pass++; echo "PASS: $m\n"; } else { $fail++; echo "FAIL: $m\n"; } }
$GLOBALS['__filters'] = array();
function add_filter( $h, $cb, $p = 10, $a = 1 ) { $GLOBALS['__filters'][ $h ][] = $cb; return true; }
function __( $s, $d = null ) { return $s; }
$t = json_decode( (string) file_get_contents( dirname( __DIR__ ) . '/theme.json' ), true );
$parts = array_column( $t['templateParts'], null, 'name' );
ok( isset( $parts['post-frontmatter'] ) && 'article' === $parts['post-frontmatter']['area'] && '' !== ( $parts['post-frontmatter']['title'] ?? '' ), 'post-frontmatter declared in the article area with a title' );
ok( isset( $parts['post-closing'] ) && 'article' === $parts['post-closing']['area'] && '' !== ( $parts['post-closing']['title'] ?? '' ), 'post-closing declared in the article area with a title' );
ok( 4 === count( $t['templateParts'] ), 'four declared parts (header, footer, post-frontmatter, post-closing)' );
$on_disk = array_map( static fn( $f ) => basename( $f, '.html' ), glob( dirname( __DIR__ ) . '/parts/*.html' ) );
sort( $on_disk ); $declared = array_keys( $parts ); sort( $declared );
ok( $on_disk === $declared, 'every part on disk is declared, and nothing declared is missing on disk' );
require_once __DIR__ . '/../inc/template-part-areas.php';
$cb = $GLOBALS['__filters']['default_wp_template_part_areas'][0] ?? null;
ok( is_callable( $cb ), 'default_wp_template_part_areas is filtered' );
$areas = $cb( array( array( 'area' => 'header' ), array( 'area' => 'footer' ) ) );
$article = array_values( array_filter( $areas, static fn( $a ) => 'article' === ( $a['area'] ?? '' ) ) );
ok( 1 === count( $article ) && isset( $article[0]['label'], $article[0]['description'], $article[0]['icon'], $article[0]['area_tag'] ), 'the article area is appended with label, description, icon, area_tag' );
ok( 3 === count( $areas ), 'existing areas are kept' );
echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
```

- [ ] **Step 2: Run to verify it fails.**

- [ ] **Step 3: Implement**

`theme.json` `templateParts`:
```json
	"templateParts": [
		{ "name": "header", "title": "Header", "area": "header" },
		{ "name": "footer", "title": "Footer", "area": "footer" },
		{ "name": "post-frontmatter", "title": "Note front matter", "area": "article" },
		{ "name": "post-closing", "title": "Note closing", "area": "article" }
	],
```

`inc/template-part-areas.php`:
```php
<?php
/**
 * Signal & Noise — the `article` template-part area: the two parts that
 * frame a note's body (front matter, closing) were "General" in the site
 * editor because theme.json never declared them. Declared + a named area.
 * @since 13.1.0
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
function sn_template_part_areas( $areas ) {
	$areas[] = array(
		'area'        => 'article',
		'label'       => __( 'Article', 'signal-and-noise' ),
		'description' => __( 'Before and after a note\'s body: the front matter and the closing.', 'signal-and-noise' ),
		'icon'        => 'layout',
		'area_tag'    => 'div',
	);
	return $areas;
}
add_filter( 'default_wp_template_part_areas', 'sn_template_part_areas' );
```
`functions.php`: `require_once __DIR__ . '/inc/template-part-areas.php'; // v13.1.0`.

- [ ] **Step 4: Verify** — `php tests/template-part-areas.php | tail -1`; `php tests/theme-json-*.php | tail -1` for each existing theme.json suite (`ls tests | grep theme-json`) still green; `python3 -c "import json;json.load(open('theme.json'))"`.

- [ ] **Step 5: Commit**

```bash
git add theme.json inc/template-part-areas.php tests/template-part-areas.php functions.php
git commit -m "feat(theme.json): post-frontmatter and post-closing are declared parts in an article area"
```

---

### Task 7: Speculative loading

**Files:**
- Create: `inc/speculation.php`, `tests/speculation.php`
- Modify: `functions.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
/** Tests: speculation rules — prerender/moderate; search and paginated notes excluded; a null (feature off) config is respected. @since theme v13.1.0 */
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', '/' ); }
$pass = 0; $fail = 0;
function ok( $c, $m ) { global $pass, $fail; if ( $c ) { $pass++; echo "PASS: $m\n"; } else { $fail++; echo "FAIL: $m\n"; } }
$GLOBALS['__filters'] = array();
function add_filter( $h, $cb, $p = 10, $a = 1 ) { $GLOBALS['__filters'][ $h ][] = $cb; return true; }
require_once __DIR__ . '/../inc/speculation.php';
$cfg = $GLOBALS['__filters']['wp_speculation_rules_configuration'][0] ?? null;
$exc = $GLOBALS['__filters']['wp_speculation_rules_href_exclude_paths'][0] ?? null;
ok( is_callable( $cfg ) && is_callable( $exc ), 'both speculation filters are registered' );
ok( array( 'mode' => 'prerender', 'eagerness' => 'moderate' ) === $cfg( array( 'mode' => 'prefetch', 'eagerness' => 'conservative' ) ), 'core\'s conservative prefetch becomes prerender/moderate' );
ok( null === $cfg( null ), 'a null config (feature disabled) is respected, never re-enabled' );
$paths = $exc( array( '/wp-admin/*' ) );
ok( in_array( '/notes/?s=*', $paths, true ) && in_array( '/notes/page/*', $paths, true ), 'search results and paginated notes are excluded' );
ok( in_array( '/wp-admin/*', $paths, true ), 'existing exclusions are kept' );
echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
```

- [ ] **Step 2: Run to verify it fails.**

- [ ] **Step 3: Implement**

```php
<?php
/**
 * Signal & Noise — speculative loading (WordPress 6.8, on by default at
 * "conservative prefetch"). Raised to prerender/moderate: a reader hovering
 * a note on the index gets the note rendered before the click. Search and
 * paginated results are excluded — each is a query, not a page. Core's null
 * (the feature disabled by a plugin or filter) is respected as-is.
 * @since 13.1.0
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
function sn_speculation_config( $config ) {
	if ( ! is_array( $config ) ) { return $config; }
	return array( 'mode' => 'prerender', 'eagerness' => 'moderate' );
}
add_filter( 'wp_speculation_rules_configuration', 'sn_speculation_config' );
function sn_speculation_exclude( $paths ) {
	$paths   = is_array( $paths ) ? $paths : array();
	$paths[] = '/notes/?s=*';
	$paths[] = '/notes/page/*';
	return $paths;
}
add_filter( 'wp_speculation_rules_href_exclude_paths', 'sn_speculation_exclude' );
```
`functions.php`: `require_once __DIR__ . '/inc/speculation.php'; // v13.1.0`.

- [ ] **Step 4: Verify** — `php tests/speculation.php | tail -1`; phpcs; stub-parity (`wp_speculation_rules_*` are filter NAMES, not functions — nothing to stub).

- [ ] **Step 5: Commit**

```bash
git add inc/speculation.php tests/speculation.php functions.php
git commit -m "feat(perf): speculative loading raised to prerender/moderate; search and pagination excluded"
```

---

### Task 8: Icons (only if Task 0 step 3 said yes)

**Files:**
- Create: `inc/icons.php`, `tests/icons.php`; Modify: `parts/footer.html`, `functions.php`

- [ ] **Step 1:** Read the five `<svg>` blocks in `parts/footer.html` (each inside a `wp:html`) and the registration function Task 0 found. Write `tests/icons.php`: the collection registers five icons whose names match the footer links' purposes; each SVG string in the collection === the SVG that was in the footer (parity); the footer places five `wp:icon` blocks and zero `wp:html` icons.
- [ ] **Step 2:** red. **Step 3:** register the collection with the exact API Task 0 verified; swap the five `wp:html` blocks for the Icon block markup the installed core's `blocks/icon/block.json` expects. **Step 4:** green + phpcs. **Step 5:** commit `feat(footer): the five icons through the 7.1 Icon API`.

If Task 0 said no: skip, and the CHANGELOG bullet in Task 9 says why.

---

### Task 9: CHANGELOG, sweep, PR, cut 13.1.0, live parity

- [ ] **Step 1: CHANGELOG** under `## [Unreleased]`:

```markdown
### Added
- **The template furniture is blocks.** Eight renderers that were `[shortcodes]` inside block templates — provenance chip and record, related notes, cited-by, share, reply, updated date, the high-contrast toggle — are WordPress 7.0 PHP-only blocks (`supports.autoRegister`, no JavaScript build): visible by name in the site editor, movable, styleable. Byte-identical on the page — `tests/blocks-php-only.php` pins block === shortcode per block. The shortcodes stay registered through 13.1.x; 13.2.0 retires them.
- **Reading time and pillar are bound paragraphs** through the `signal-noise/post-field` source the theme has carried since v9.11.0 — the migration v9.11.1 reverted, retried now that PHPStan is a required check and `tests/block-bindings-templates.php` pins every binding's key and empty inner. The source gains a `slug` arg for the pillar cards.
- **The provenance chip and the closing part are hooked into `single` by rule** (`hooked_block_types`): a single template that lacks them gets them; the shipped template, which places them, is untouched (`ignoredHookedBlocks`).
- `post-frontmatter` and `post-closing` are declared template parts in a new **Article** area.
- **Speculative loading** raised to prerender/moderate; `/notes/?s=` and paginated notes excluded.
- (if built) The five footer icons through the 7.1 Icon API. / (if not) The 7.1 Icon API was checked on core X.Y: <what was found>; the footer keeps its inline SVGs.
Survey and spec: `docs/research/2026-09-11-*.md`.
```

- [ ] **Step 2:** `bash tests/run.sh | tail -1` → 0 failed. phpcs on every touched `inc/*.php`; phpstan on the four new modules.

- [ ] **Step 3:** PR from the arc branch; wait for green (fresh re-read); squash-merge.

- [ ] **Step 4:** Cut: `git checkout -b release/v13.1.0 origin/main && tools/cut-release.sh release "the template furniture is blocks"` → commit → PR → green → squash → tag the squash commit → `gh release create v13.1.0 --verify-tag …`. Install via wp-admin.

- [ ] **Step 5: Live parity** — re-run the Task 0 capture against the same note into `/tmp/after.html` and `diff docs/research/baselines/2026-09-11-single-note-frontmatter-closing.html /tmp/after.html`. Expected: empty diff, or only whitespace/`class="wp-block-signal-noise-…"` wrapper attributes that `register_block_type` adds — anything else is a parity break and a fix release. Then: the site editor → Patterns → Template Parts shows an **Article** area with two parts; the inserter lists the eight blocks under Signal & Noise; `view-source:` of `/notes/` shows `<script type="speculationrules">` with `"prerender"`. Purge caches first.

---

## Self-review

**Spec coverage:** §1 bindings → Tasks 1–2 (slug arg, seven slots, parse pin); §2 PHP-only blocks → Task 3 (module, parity, context) + Task 4 (templates, palette); §3 hooks → Task 5; §4 areas → Task 6; §5 speculation → Task 7; §6 icons → Task 0 verification + Task 8; tests → each task; live parity → Task 0 capture + Task 9 diff. The spec's `do_blocks()` test replaced by the parse-level pin (Task 0 amends the spec). Plugin: nothing, as the spec says.

**Placeholders:** Task 8 is deliberately conditional on Task 0's verification of an API this plan has not seen — its steps name the checks, not the code; that is the honest form for an unverified API, and it is the last task so nothing depends on it.

**Type consistency:** `sn_php_block_render_for_context( $block, callable $renderer, array $args )`; render callbacks `( $attributes, $content, $block )`; `sn_hooked_block_types( $hooked, $position, $anchor, $context )` with 4 args and priority 10; `sn_hooked_block_post_closing` likewise; the block names `signal-noise/{prov-chip,prov-panel,related-notes,cited-by,note-share,note-reply,updated-date,theme-toggle}` are identical in Task 3's registration, Task 4's templates and pins, Task 5's hook, and the palette.
