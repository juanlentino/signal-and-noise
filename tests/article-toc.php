<?php
/**
 * Standalone fixture tests for the in-article TOC builder.
 *
 * Stubs the WP primitives inc/article-toc.php touches (sanitize_title,
 * escaping, is_singular/in_the_loop/is_main_query) so the pure helpers run
 * without a WordPress load; the one piece of core they run ON, the HTML API
 * (#383), is loaded by tests/lib/wp-html-api.php. Mirrors tests/post-share.php.
 * The JS layer (assets/js/article-toc.js) is progressive enhancement, verified
 * by manual UAT — these tests cover the server-rendered markup contract only.
 *
 * @since theme (TOC feature)
 */

// SECURITY: Prevent web access. Test fixture, not a runtime module.
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) {
	http_response_code( 404 );
	exit;
}

// Controllable stub state for the filter guard.
$GLOBALS['__is_singular_post'] = false;
$GLOBALS['__in_the_loop']      = false;
$GLOBALS['__is_main_query']    = false;

if ( ! function_exists( 'is_singular' ) ) {
	function is_singular( $types = '' ) { return (bool) $GLOBALS['__is_singular_post']; }
}
if ( ! function_exists( 'in_the_loop' ) ) {
	function in_the_loop() { return (bool) $GLOBALS['__in_the_loop']; }
}
if ( ! function_exists( 'is_main_query' ) ) {
	function is_main_query() { return (bool) $GLOBALS['__is_main_query']; }
}
if ( ! function_exists( 'sanitize_title' ) ) {
	// Core's sanitize_title_with_dashes in miniature: strip_tags() first,
	// character references killed whole (`&.+?;`), then lowercase and
	// non-alphanumerics to a single hyphen, trimmed. The first two steps are
	// what make a decoded "<meta>" label slug differently from its escaped
	// form; without them the stub cannot see that class.
	function sanitize_title( $t ) {
		$t = strip_tags( (string) $t );
		$t = preg_replace( '/&.+?;/', '', $t );
		$t = strtolower( $t );
		$t = preg_replace( '/[^a-z0-9]+/', '-', $t );
		return trim( (string) $t, '-' );
	}
}
if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( $s ) {
		return trim( (string) preg_replace( '/<[^>]*>/', '', (string) $s ) );
	}
}
// Like core's _wp_specialchars, these do not double-encode an existing
// reference, so an escaped label reads the same whether it arrives raw
// (origin/main's regex) or decoded (the processor).
if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8', false ); }
}
if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8', false ); }
}
if ( ! function_exists( 'esc_attr__' ) ) {
	function esc_attr__( $s, $d = 'default' ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
}
if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( $s, $d = 'default' ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
}

define( 'SN_ARTICLE_TOC_TEST', true );
require_once __DIR__ . '/lib/wp-html-api.php';
snt_require_wp_html_api();
require __DIR__ . '/../inc/article-toc.php';

$pass = 0; $fail = 0;
function ok( $cond, $label ) {
	global $pass, $fail;
	if ( $cond ) { $pass++; echo "PASS: $label\n"; }
	else { $fail++; echo "FAIL: $label\n"; }
}

// Helpers to build rendered-content fixtures.
function h2( $text, $attrs = ' class="wp-block-heading"' ) { return "<h2$attrs>$text</h2>"; }
$p = '<p>Body text.</p>';

// ── Below threshold (2 H2s) → unchanged ───────────────────────────────
$two = h2( 'One' ) . $p . h2( 'Two' ) . $p;
ok( sn_article_toc_apply( $two ) === $two, '<3 H2s: content returned unchanged' );

// ── 3 H2s → TOC prepended, ids injected, links match ──────────────────
$three = h2( 'Intro' ) . $p . h2( 'Method' ) . $p . h2( 'Result' ) . $p;
$out = sn_article_toc_apply( $three );
ok( strpos( $out, '<nav class="sn-article-toc"' ) === 0, 'TOC nav is prepended at the very start' );
ok( strpos( $out, 'aria-label="Table of contents"' ) !== false, 'nav has the aria-label' );
ok( strpos( $out, '<ol class="sn-article-toc__list">' ) !== false, 'ordered list present' );
ok( substr_count( $out, '<li>' ) === 3, 'three TOC items' );
ok( strpos( $out, '<a href="#intro">Intro</a>' ) !== false, 'links to #intro' );
ok( strpos( $out, '<a href="#method">Method</a>' ) !== false, 'links to #method' );
// #383: set_attribute() places a new attribute right after the tag name, so
// the id now leads the class where the regex splice used to trail it. The
// DOM is the same; the shape pin at the end of this file proves it against
// the bytes origin/main's regex produced for this very fixture.
ok( strpos( $out, '<h2 id="intro" class="wp-block-heading">Intro</h2>' ) !== false, 'id injected into the Intro heading (after the tag name, the HTML API\'s placement)' );
ok( strpos( $out, '<h2 id="result" class="wp-block-heading">Result</h2>' ) !== false, 'id injected into the Result heading' );

// ── Duplicate heading text → unique ids (-2 suffix) ───────────────────
$dupe = h2( 'Notes' ) . $p . h2( 'Notes' ) . $p . h2( 'Notes' ) . $p;
$dout = sn_article_toc_apply( $dupe );
ok( strpos( $dout, 'id="notes"' ) !== false, 'first duplicate keeps the bare slug' );
ok( strpos( $dout, 'id="notes-2"' ) !== false, 'second duplicate gets -2' );
ok( strpos( $dout, 'id="notes-3"' ) !== false, 'third duplicate gets -3' );
ok( strpos( $dout, '<a href="#notes-2">Notes</a>' ) !== false, 'TOC links the deduped id' );

// ── Author-set id is preserved, not re-slugged ────────────────────────
$auth = '<h2 id="custom-anchor">Alpha</h2>' . $p . h2( 'Beta' ) . $p . h2( 'Gamma' ) . $p;
$aout = sn_article_toc_apply( $auth );
ok( strpos( $aout, '<h2 id="custom-anchor">Alpha</h2>' ) !== false, 'author id left untouched on the heading' );
ok( strpos( $aout, '<a href="#custom-anchor">Alpha</a>' ) !== false, 'TOC links the author id' );
ok( strpos( $aout, 'id="custom-anchor" id=' ) === false, 'no second id injected onto an already-anchored heading' );

// ── An author-set id must be recorded, or a later generated heading can
// duplicate it (theme #330): <h2 id="setup"> then a heading literally
// titled "Setup" both slug to "setup" — two TOC links would jump to the
// SAME (first) heading.
$collide = '<h2 id="setup">Getting started</h2>' . $p . h2( 'Intro' ) . $p . h2( 'Setup' ) . $p;
$cout    = sn_article_toc_apply( $collide );
ok( 1 === substr_count( $cout, 'id="setup"' ), 'the author id is recorded, so the generated "Setup" heading does NOT reuse it' );
ok( strpos( $cout, 'id="setup-2"' ) !== false, 'the generated heading gets the next free suffix instead' );
ok( strpos( $cout, '<a href="#setup-2">Setup</a>' ) !== false, 'the TOC links the deduped id, not the author id, for the generated heading' );

// ── Inline markup in a heading → clean label + slug ───────────────────
$inline = '<h2 class="wp-block-heading"><em>Hello</em> <code>World</code></h2>' . $p . h2( 'Two' ) . $p . h2( 'Three' ) . $p;
$iout = sn_article_toc_apply( $inline );
ok( strpos( $iout, 'id="hello-world"' ) !== false, 'slug derived from stripped heading text' );
ok( strpos( $iout, '<a href="#hello-world">Hello World</a>' ) !== false, 'TOC label is the stripped text' );

// ── Escaped markup in a heading keeps its word in the id (#383 review) ──
// The block editor stores a typed "<meta>" as &lt;meta&gt;. The processor
// hands the label back decoded, and core's sanitize_title_with_dashes()
// strip_tags()es a decoded "<meta>" clean away: #the-tag where origin/main
// gave #the-meta-tag. The label is re-escaped before it is slugged.
$escaped = h2( 'The &lt;meta&gt; tag' ) . $p . h2( '&lt;b&gt;bold&lt;/b&gt;' ) . $p . h2( 'Tom &amp; Jerry' ) . $p;
$eout    = sn_article_toc_apply( $escaped );
ok( strpos( $eout, 'id="the-meta-tag"' ) !== false, '#383: an escaped <meta> in the heading text keeps "meta" in the id, as origin/main did' );
ok( strpos( $eout, 'id="bbold-b"' ) !== false, '#383: escaped <b> tags slug the way origin/main slugged them (core kills the references, the b and /b letters stay)' );
ok( strpos( $eout, 'id="tom-jerry"' ) !== false, '#383: an escaped ampersand still drops out of the slug' );
ok( strpos( $eout, '<a href="#the-meta-tag">The &lt;meta&gt; tag</a>' ) !== false, '#383: the TOC label shows the literal <meta>, escaped once' );

// ── Empty heading is skipped; remaining count still gates ─────────────
$empty = h2( 'One' ) . $p . h2( '' ) . $p . h2( 'Two' ) . $p;
ok( sn_article_toc_apply( $empty ) === $empty, 'two non-empty + one empty H2 → below threshold, unchanged' );

// ── Filter guard: secondary / non-post content is never touched ───────
$body = h2( 'A' ) . $p . h2( 'B' ) . $p . h2( 'C' ) . $p;

$GLOBALS['__is_singular_post'] = false; $GLOBALS['__in_the_loop'] = true; $GLOBALS['__is_main_query'] = true;
ok( sn_article_toc_the_content( $body ) === $body, 'guard: not is_singular(post) → unchanged' );

$GLOBALS['__is_singular_post'] = true; $GLOBALS['__in_the_loop'] = false; $GLOBALS['__is_main_query'] = true;
ok( sn_article_toc_the_content( $body ) === $body, 'guard: outside the loop (e.g. excerpt) → unchanged' );

$GLOBALS['__is_singular_post'] = true; $GLOBALS['__in_the_loop'] = true; $GLOBALS['__is_main_query'] = false;
ok( sn_article_toc_the_content( $body ) === $body, 'guard: secondary query → unchanged' );

$GLOBALS['__is_singular_post'] = true; $GLOBALS['__in_the_loop'] = true; $GLOBALS['__is_main_query'] = true;
ok( strpos( sn_article_toc_the_content( $body ), '<nav class="sn-article-toc"' ) === 0, 'guard: main single-post query → TOC applied' );

// ── #383: the port paints the same DOM as the regex it replaced ────────
// Each expected string is the byte-for-byte output of origin/main's
// preg_replace_callback (captured 2026-09-20 with these stubs) for the same
// fixture. The bytes differ only in attribute order (id now leads); the shape
// (tags, decoded attributes, text) is identical, which is what a browser sees.
$captured = array(
	'three'   => array( $three,   '<nav class="sn-article-toc" aria-label="Table of contents"><p class="sn-article-toc__label">Contents</p><ol class="sn-article-toc__list"><li><a href="#intro">Intro</a></li><li><a href="#method">Method</a></li><li><a href="#result">Result</a></li></ol></nav><h2 class="wp-block-heading" id="intro">Intro</h2><p>Body text.</p><h2 class="wp-block-heading" id="method">Method</h2><p>Body text.</p><h2 class="wp-block-heading" id="result">Result</h2><p>Body text.</p>' ),
	'dupe'    => array( $dupe,    '<nav class="sn-article-toc" aria-label="Table of contents"><p class="sn-article-toc__label">Contents</p><ol class="sn-article-toc__list"><li><a href="#notes">Notes</a></li><li><a href="#notes-2">Notes</a></li><li><a href="#notes-3">Notes</a></li></ol></nav><h2 class="wp-block-heading" id="notes">Notes</h2><p>Body text.</p><h2 class="wp-block-heading" id="notes-2">Notes</h2><p>Body text.</p><h2 class="wp-block-heading" id="notes-3">Notes</h2><p>Body text.</p>' ),
	'auth'    => array( $auth,    '<nav class="sn-article-toc" aria-label="Table of contents"><p class="sn-article-toc__label">Contents</p><ol class="sn-article-toc__list"><li><a href="#custom-anchor">Alpha</a></li><li><a href="#beta">Beta</a></li><li><a href="#gamma">Gamma</a></li></ol></nav><h2 id="custom-anchor">Alpha</h2><p>Body text.</p><h2 class="wp-block-heading" id="beta">Beta</h2><p>Body text.</p><h2 class="wp-block-heading" id="gamma">Gamma</h2><p>Body text.</p>' ),
	'collide' => array( $collide, '<nav class="sn-article-toc" aria-label="Table of contents"><p class="sn-article-toc__label">Contents</p><ol class="sn-article-toc__list"><li><a href="#setup">Getting started</a></li><li><a href="#intro">Intro</a></li><li><a href="#setup-2">Setup</a></li></ol></nav><h2 id="setup">Getting started</h2><p>Body text.</p><h2 class="wp-block-heading" id="intro">Intro</h2><p>Body text.</p><h2 class="wp-block-heading" id="setup-2">Setup</h2><p>Body text.</p>' ),
	'escaped' => array( $escaped, '<nav class="sn-article-toc" aria-label="Table of contents"><p class="sn-article-toc__label">Contents</p><ol class="sn-article-toc__list"><li><a href="#the-meta-tag">The &lt;meta&gt; tag</a></li><li><a href="#bbold-b">&lt;b&gt;bold&lt;/b&gt;</a></li><li><a href="#tom-jerry">Tom &amp; Jerry</a></li></ol></nav><h2 class="wp-block-heading" id="the-meta-tag">The &lt;meta&gt; tag</h2><p>Body text.</p><h2 class="wp-block-heading" id="bbold-b">&lt;b&gt;bold&lt;/b&gt;</h2><p>Body text.</p><h2 class="wp-block-heading" id="tom-jerry">Tom &amp; Jerry</h2><p>Body text.</p>' ),
	'inline'  => array( $inline,  '<nav class="sn-article-toc" aria-label="Table of contents"><p class="sn-article-toc__label">Contents</p><ol class="sn-article-toc__list"><li><a href="#hello-world">Hello World</a></li><li><a href="#two">Two</a></li><li><a href="#three">Three</a></li></ol></nav><h2 class="wp-block-heading" id="hello-world"><em>Hello</em> <code>World</code></h2><p>Body text.</p><h2 class="wp-block-heading" id="two">Two</h2><p>Body text.</p><h2 class="wp-block-heading" id="three">Three</h2><p>Body text.</p>' ),
);
foreach ( $captured as $name => $pair ) {
	ok( snt_html_shape( $pair[1] ) === snt_html_shape( sn_article_toc_apply( $pair[0] ) ), "#383 $name: the port paints the DOM origin/main's regex painted (same tags, ids, text)" );
}
// The module rewrites through the HTML API and nothing else: no regex, no
// byte splice, and no pre-gate probe either (the walk is the count).
$toc_src = (string) file_get_contents( __DIR__ . '/../inc/article-toc.php' );
ok( 0 === preg_match( '/preg_(?:replace|replace_callback|match|match_all)\s*\(|substr_replace\s*\(/', $toc_src ), '#383: inc/article-toc.php carries no preg_* call and no substr_replace; the H2s are read and written through WP_HTML_Tag_Processor' );
ok( false !== strpos( $toc_src, 'new WP_HTML_Tag_Processor(' ) && false !== strpos( $toc_src, "set_attribute( 'id'" ), '#383: the id lands through set_attribute() on the processor' );

// ── JS SOURCE: the progress bar MEASURES the header, it does not assume it ──
// #322 added a `transitionend` listener here because the header shrank on
// scroll over 300ms and `bar.style.top` was only re-read on scroll/resize, so
// one wheel notch left ~33px of content showing under the bar. The header is
// one height now and does not transition, so that event can never fire and the
// listener is gone. What must survive is the reason it was needed: the top is
// read from the live element every frame, never hardcoded, because the header
// still changes height at two breakpoints and with the reader's font size.
$toc_js = (string) @file_get_contents( realpath( __DIR__ . '/..' ) . '/assets/js/article-toc.js' );
ok( '' !== $toc_js, 'article-toc.js is readable' );
ok( strpos( $toc_js, "addEventListener('transitionend'" ) === false, 'the transitionend listener is gone with the scroll-shrink it waited on' );
ok( strpos( $toc_js, "header.getBoundingClientRect().bottom" ) !== false, '#322 still holds: the bar reads the header\'s live bottom edge rather than a literal' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
