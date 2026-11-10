<?php
/**
 * Tests: reading time is core's Time to Read block, showing the plugin's
 * figure (inc/post-time-to-read.php, 2026-11-10).
 *
 * The front matter and the two note-card loops place core/post-time-to-read
 * with the classes, colors and spacing the bound paragraphs had. A filter
 * writes "N min read" from sn_get_reading_time(), the figure the schema and
 * the byline count, so the text matches what was shown before. Without the
 * plugin, core's own "N minutes" stays. Real core HTML API.
 *
 * Run: php tests/post-time-to-read.php
 */
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', '/' ); }

$pass = 0; $fail = 0;
function ok( $c, $m ) { global $pass, $fail; if ( $c ) { $pass++; echo "PASS: $m\n"; } else { $fail++; echo "FAIL: $m\n"; } }

$GLOBALS['__f'] = array();
function add_filter( $h, $cb, $p = 10, $n = 1 ) { $GLOBALS['__f'][ $h ] = $cb; return true; }
require __DIR__ . '/lib/wp-html-api.php';
snt_require_wp_html_api();
require __DIR__ . '/../inc/post-time-to-read.php';
$root = dirname( __DIR__ );

$cb   = $GLOBALS['__f']['render_block_core/post-time-to-read'] ?? null;
ok( is_callable( $cb ), 'a render_block_core/post-time-to-read filter is registered' );
// What core 7.1 renders for the front-matter block.
$core = '<div class="wp-block-post-time-to-read sn-post-frontmatter__rt has-blood-color has-text-color has-body-font-family" style="margin-top:0;margin-bottom:0">7 minutes</div>';
$inst = (object) array( 'context' => array( 'postId' => 42 ) );
$blk  = array( 'attrs' => array( 'className' => 'sn-post-frontmatter__rt' ) );

ok( $core === $cb( $core, $blk, $inst ), "without the plugin, core's text stays" );

if ( true ) { function sn_get_reading_time( $id ) { $GLOBALS["__rt_id"] = $id; return $GLOBALS["__rt"]; } } // declared at run time, after the no-plugin case
$GLOBALS['__rt'] = 5;
$out = $cb( $core, $blk, $inst );
ok( '<div class="wp-block-post-time-to-read sn-post-frontmatter__rt has-blood-color has-text-color has-body-font-family" style="margin-top:0;margin-bottom:0">5 min read</div>' === $out, 'with the plugin: the same element, the plugin figure, the old wording: ' . $out );
ok( 42 === $GLOBALS['__rt_id'], "the figure is the block context's post (the Query Loop's card, not the page)" );
$GLOBALS['__rt'] = 0;
ok( false !== strpos( $cb( $core, $blk, $inst ), '>1 min read<' ), 'floored at 1, as the binding was' );
$GLOBALS['__rt'] = 5;
ok( $core === $cb( $core, array( 'attrs' => array( 'displayMode' => 'words' ) ), $inst ), 'a word-count block is left alone' );
ok( $core === $cb( $core, array( 'attrs' => array( 'displayAsRange' => true ) ), $inst ), 'a range block is left alone' );

$want = array( 'parts/post-frontmatter.html' => 'sn-post-frontmatter__rt', 'templates/home.html' => 'sn-note-card-reading-time', 'templates/page-notes.html' => 'sn-note-card-reading-time' );
foreach ( $want as $rel => $class ) {
	$h = (string) file_get_contents( "$root/$rel" );
	ok( 1 === preg_match( '/<!-- wp:post-time-to-read \{[^}]*"className":"' . preg_quote( $class, '/' ) . '"/', $h ), "$rel places core/post-time-to-read with class $class" );
	ok( false === strpos( $h, '"key":"reading_time"' ), "$rel no longer binds a paragraph to reading_time" );
}
ok( false !== strpos( (string) file_get_contents( "$root/templates/page-notes.html" ), '[sn_reading_time slug="provenance/over-detection"]' ), 'the pillar-card eyebrows keep their shortcode: core cannot target a post by slug outside a query' );

echo "Result: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
