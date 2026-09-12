<?php
/**
 * Tests: single's Block Hooks rule — core/template-part (post-closing, area
 * article) hooks after core/post-content. Scoped to the single WP_Template,
 * never a part, pattern, or other template. No chip rule: the chip lives
 * inside parts/post-frontmatter.html, before the title, which the Block
 * Hooks API cannot express (only sibling-anchor hooking within the same
 * block list) — explicit placement only.
 *
 * Because core applies block hooks to FILE-based templates too (read time,
 * not just on db save), templates/single.html must declare
 * `metadata.ignoredHookedBlocks: ["core/template-part"]` on its
 * core/post-content block by hand, or the shipped template would render the
 * closing part TWICE. That declaration is pinned directly against the
 * shipped file, not a stand-in.
 * @since theme v13.1.0
 */
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', '/' ); }
$pass = 0; $fail = 0;
function ok( $c, $m ) { global $pass, $fail; if ( $c ) { $pass++; echo "PASS: $m\n"; } else { $fail++; echo "FAIL: $m\n"; } }

// ── WP stubs ──
$GLOBALS['__filters'] = array(); // hook => array of [cb, priority, accepted_args]
function add_filter( $hook, $cb, $priority = 10, $accepted_args = 1 ) {
	$GLOBALS['__filters'][ $hook ][] = array( $cb, $priority, $accepted_args );
	return true;
}
function get_stylesheet() { return 'signal-and-noise'; }

class WP_Block_Template {
	public $type;
	public $slug;
	public $theme;
	public $area;
}

require_once __DIR__ . '/../inc/block-hooks.php';

function sn_test_filter_entry( $hook ) {
	return $GLOBALS['__filters'][ $hook ][0] ?? null;
}

function sn_test_template( $slug, $type = 'wp_template' ) {
	$t       = new WP_Block_Template();
	$t->type = $type;
	$t->slug = $slug;
	return $t;
}

echo "Group: registration\n";
$types_entry = sn_test_filter_entry( 'hooked_block_types' );
ok( is_array( $types_entry ) && is_callable( $types_entry[0] ) && 4 === $types_entry[2], 'hooked_block_types filtered, callback exists, registered with 4 accepted args' );

$block_entry = sn_test_filter_entry( 'hooked_block_core/template-part' );
ok( is_array( $block_entry ) && is_callable( $block_entry[0] ) && 5 === $block_entry[2], 'hooked_block_core/template-part filtered, registered with 5 accepted args (real signature)' );

$types_cb = $types_entry[0];
$block_cb = $block_entry[0];

echo "\nGroup: hooked_block_types on single\n";
$single = sn_test_template( 'single' );
ok( array( 'core/template-part' ) === $types_cb( array(), 'after', 'core/post-content', $single ), 'single, after, core/post-content → appends core/template-part' );
ok( array() === $types_cb( array(), 'before', 'core/post-content', $single ), 'not before post-content' );
ok( array() === $types_cb( array(), 'after', 'core/post-title', $single ), 'not after core/post-title — the chip is not a hook' );
ok( array() === $types_cb( array(), 'after', 'core/post-date', $single ), 'not after some other anchor' );

echo "\nGroup: scope — only the single wp_template\n";
foreach ( array( 'home', 'page', 'index', 'page-notes' ) as $slug ) {
	$t = sn_test_template( $slug );
	ok( array() === $types_cb( array(), 'after', 'core/post-content', $t ), "$slug template: nothing hooks after post-content" );
}

$part = sn_test_template( 'post-frontmatter', 'wp_template_part' );
ok( array() === $types_cb( array(), 'after', 'core/post-content', $part ), 'a template PART (post-frontmatter) → nothing' );

ok( array() === $types_cb( array(), 'after', 'core/post-content', null ), 'null context (post content) → nothing' );
ok( array() === $types_cb( array(), 'after', 'core/post-content', array( 'foo' => 'bar' ) ), 'array context (a pattern) → nothing' );

echo "\nGroup: appends to, never replaces\n";
ok( array( 'x/y', 'core/template-part' ) === $types_cb( array( 'x/y' ), 'after', 'core/post-content', $single ), "['x/y'] in → ['x/y','core/template-part']" );

echo "\nGroup: hooked_block_core/template-part on single\n";
$anchor = array( 'blockName' => 'core/post-content', 'attrs' => array() );
$parsed = array( 'blockName' => 'core/template-part', 'attrs' => array(), 'innerBlocks' => array(), 'innerContent' => array() );
$result = $block_cb( $parsed, 'core/template-part', 'after', $anchor, $single );
ok( 'post-closing' === ( $result['attrs']['slug'] ?? null ), 'attrs.slug set to post-closing' );
ok( 'article' === ( $result['attrs']['area'] ?? null ), 'attrs.area set to article' );
ok( 'footer' === ( $result['attrs']['tagName'] ?? null ), 'attrs.tagName set to footer — mirrors templates/single.html\'s explicit {"slug":"post-closing","tagName":"footer"} placement' );

$other_anchor = array( 'blockName' => 'core/post-title', 'attrs' => array() );
$unchanged    = $block_cb( $parsed, 'core/template-part', 'after', $other_anchor, $single );
ok( $unchanged === $parsed, 'an unrelated anchor → returned unchanged' );

ok( null === $block_cb( null, 'core/template-part', 'after', $anchor, $single ), 'a null parsed block → returned unchanged (null)' );

echo "\nGroup: negative control\n";
$home        = sn_test_template( 'home' );
$home_result = $block_cb( $parsed, 'core/template-part', 'after', $anchor, $home );
ok( $home_result === $parsed, 'the same per-block callback on a home template → unchanged' );

echo "\nGroup: the shipped single.html declares ignoredHookedBlocks — without it the closing part renders TWICE\n";
$root       = dirname( __DIR__ );
$single_src = (string) file_get_contents( $root . '/templates/single.html' );
ok( 1 === preg_match( '/<!-- wp:post-content (\{.*?\}) \/-->/', $single_src, $m ), 'templates/single.html has a post-content self-closing block comment to parse' );
$post_content_attrs = isset( $m[1] ) ? json_decode( $m[1], true ) : null;
ok( is_array( $post_content_attrs ), 'post-content attrs JSON decodes' );
ok(
	array( 'core/template-part' ) === ( $post_content_attrs['metadata']['ignoredHookedBlocks'] ?? null ),
	"single.html's post-content anchor ignores the hooked template part — without this the shipped template would render the closing part TWICE"
);

// Negative control: the same JSON with the metadata key stripped must NOT satisfy the pin.
$stripped = $post_content_attrs;
unset( $stripped['metadata'] );
ok(
	array( 'core/template-part' ) !== ( $stripped['metadata']['ignoredHookedBlocks'] ?? null ),
	'negative control: post-content attrs with metadata removed do NOT satisfy the ignoredHookedBlocks pin'
);

echo "\nGroup: only single needs the ignore — no other template declares it\n";
$other_declares = array();
foreach ( glob( $root . '/templates/*.html' ) as $f ) {
	if ( 'single.html' === basename( $f ) ) {
		continue;
	}
	$src = (string) file_get_contents( $f );
	if ( preg_match_all( '/<!-- wp:\S+ (\{[^)]*?"ignoredHookedBlocks":\s*\[[^\]]*"core\/template-part"[^\]]*\][^)]*?\})/', $src ) ) {
		$other_declares[] = basename( $f );
	}
}
ok( array() === $other_declares, 'no template other than single.html declares ignoredHookedBlocks for core/template-part' . ( $other_declares ? ' — FOUND: ' . implode( ', ', $other_declares ) : '' ) );

echo "\nGroup: the closing part still sits after post-content in the shipped template\n";
$content_pos = strpos( $single_src, '<!-- wp:post-content' );
$closing_pos = strpos( $single_src, '<!-- wp:template-part {"slug":"post-closing"' );
ok( false !== $content_pos && false !== $closing_pos && $closing_pos > $content_pos, 'templates/single.html still places the post-closing part after post-content' );

$total = $pass + $fail;
echo "\n$pass passed, $fail failed\n";
