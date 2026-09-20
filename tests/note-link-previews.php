<?php
/**
 * Standalone fixture tests for internal note-link hover previews (v12.10.0).
 *
 * inc/note-link-previews.php: the the_content filter that stamps qualifying
 * internal /notes/<slug>/ anchors with data-sn-preview-* attributes. The
 * REAL filter and the REAL summary derivation run here (plus the real
 * sn_notes_render_date / sn_notes_render_reading_time from
 * inc/notes-index-helpers.php); only WP core is stubbed, and the one piece
 * of core the filter runs ON, the HTML API (#383), is loaded by
 * tests/lib/wp-html-api.php.
 *
 * @since theme v12.10.0
 */
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', '/' ); }
define( 'SN_NOTE_LINK_PREVIEWS_TEST', true );

$pass = 0; $fail = 0;
function ok( $c, $m ) { global $pass, $fail; if ( $c ) { $pass++; echo "PASS: $m\n"; } else { $fail++; echo "FAIL: $m\n"; } }

// ── WP stubs (environment only) ──
// Like core's, neither double-encodes an existing reference (#383 pins a
// texturized excerpt; a stub that doubled &#8217; would fail origin/main for
// a reason the live site never had).
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8', false ); }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8', false ); }
function wp_strip_all_tags( $s ) { return trim( strip_tags( (string) $s ) ); }
function wp_trim_words( $text, $num, $more ) {
	$words = preg_split( '/\s+/', trim( (string) $text ), -1, PREG_SPLIT_NO_EMPTY );
	if ( count( $words ) <= $num ) { return implode( ' ', $words ); }
	return implode( ' ', array_slice( $words, 0, $num ) ) . $more;
}
function wp_parse_url( $url, $component = -1 ) { return parse_url( (string) $url, $component ); }
function home_url( $path = '' ) { return 'https://x.test' . $path; }
function apply_filters( $tag, $value ) { return $value; }
function get_the_time( $fmt, $post ) { return 1787736000; }
function wp_date( $fmt, $ts ) { return gmdate( 'Y.m.d', (int) $ts ); }
function get_post_meta( $id, $key, $single ) { return $GLOBALS['__meta'][ (int) $id ][ $key ] ?? ''; }

$GLOBALS['__excerpts'] = array();
function has_excerpt( $post ) { return '' !== ( $GLOBALS['__excerpts'][ (int) $post->ID ] ?? '' ); }
function get_the_excerpt( $post ) { return $GLOBALS['__excerpts'][ (int) $post->ID ] ?? ''; }

$GLOBALS['__posts_by_name'] = array();
function mk_note( $id, $name, $title, $content, $excerpt = '', $mins = 3 ) {
	$p = new stdClass();
	$p->ID = $id; $p->post_name = $name; $p->post_title = $title; $p->post_content = $content;
	$GLOBALS['__posts_by_name'][ $name ] = $p;
	$GLOBALS['__excerpts'][ $id ] = $excerpt;
	$GLOBALS['__meta'][ $id ] = array( '_sn_reading_time_minutes' => $mins );
	return $p;
}
function get_posts( $args ) {
	$p = $GLOBALS['__posts_by_name'][ (string) ( $args['name'] ?? '' ) ] ?? null;
	return $p ? array( $p ) : array();
}
$GLOBALS['__is_single_post'] = true;
function is_singular( $type = '' ) { return $GLOBALS['__is_single_post'] && 'post' === $type; }
$GLOBALS['__queried_id'] = 100;
function get_queried_object_id() { return $GLOBALS['__queried_id']; }

require_once __DIR__ . '/lib/wp-html-api.php';
snt_require_wp_html_api();
require __DIR__ . '/../inc/notes-index-helpers.php';
require __DIR__ . '/../inc/note-link-previews.php';

mk_note( 7, 'two-kinds', 'Two kinds of provenance', 'Ignored body.', 'The opening, hand-set.' );
mk_note( 8, 'no-excerpt', 'A note with no excerpt', str_repeat( 'word ', 40 ) . '<em>tail</em>', '' );
mk_note( 9, 'hostile', 'A "quoted" <title>', 'Body.', 'Summary with "quotes" & ampersand.' );

// ── 1. Absolute same-site link gains the three attributes ──
$in  = '<p>See <a href="https://x.test/notes/two-kinds/">this note</a> for more.</p>';
$out = sn_note_link_previews_filter( $in );
ok( false !== strpos( $out, 'data-sn-preview-title="Two kinds of provenance"' ), 'absolute same-site link stamped with title' );
ok( false !== strpos( $out, 'data-sn-preview-summary="The opening, hand-set."' ), 'summary uses the hand-set excerpt (the opening)' );
ok( false !== strpos( $out, 'data-sn-preview-meta="2026.08.26 · 03 MIN"' ), 'meta joins the real date and reading-time helpers' );
ok( false !== strpos( $out, '>this note</a>' ), 'anchor text and close survive intact' );

// ── 2. No excerpt → trimmed opening words with ellipsis, tags stripped ──
$out2 = sn_note_link_previews_filter( '<a href="/notes/no-excerpt/">x</a>' );
ok( 1 === preg_match( '/data-sn-preview-summary="word(?: word){27}…"/u', $out2 ), 'derived summary is the first 28 words + ellipsis, tags stripped' );

// ── 3. What must pass through byte-identical ──
$self = '<a href="/notes/current-note/">me</a>';
mk_note( 100, 'current-note', 'The current note', 'Body.' );
ok( sn_note_link_previews_filter( $self ) === $self, 'a self-link is never stamped' );
$foreign = '<a href="https://example.com/notes/two-kinds/">foreign</a>';
ok( sn_note_link_previews_filter( $foreign ) === $foreign, 'a foreign-host /notes/ link is untouched' );
$missing = '<a href="/notes/never-published/">gone</a>';
ok( sn_note_link_previews_filter( $missing ) === $missing, 'an unresolvable slug is untouched' );
$plain = '<p>No note links here at all.</p>';
ok( sn_note_link_previews_filter( $plain ) === $plain, 'content without /notes/ takes the fast path unchanged' );
$GLOBALS['__is_single_post'] = false;
$in_off = '<a href="/notes/two-kinds/">x</a>';
ok( sn_note_link_previews_filter( $in_off ) === $in_off, 'filter is inert off single posts' );
$GLOBALS['__is_single_post'] = true;

// ── 4. Relative href + fragment both qualify ──
$out4 = sn_note_link_previews_filter( '<a href="/notes/two-kinds/#section">deep</a>' );
ok( false !== strpos( $out4, 'data-sn-preview-title=' ), 'site-relative href with fragment is stamped' );

// ── 5. Idempotence: running the filter twice stamps once ──
$twice = sn_note_link_previews_filter( $out );
ok( substr_count( $twice, 'data-sn-preview-title=' ) === 1, 'already-stamped anchors are not stamped again' );

// ── 6. Hostile title/summary land escaped, markup stays parseable ──
$out6 = sn_note_link_previews_filter( '<a href="/notes/hostile/">h</a>' );
ok( false !== strpos( $out6, 'data-sn-preview-title="A &quot;quoted&quot; &lt;title&gt;"' ), 'title with quotes and angle brackets is attribute-escaped' );
ok( false !== strpos( $out6, '&quot;quotes&quot; &amp; ampersand' ), 'summary is attribute-escaped too' );
ok( false === strpos( $out6, '<title>' ), 'no raw markup from post fields leaks into the page' );

// ── 7. Multiple links in one body each get their own stamp ──
$multi = sn_note_link_previews_filter( '<a href="/notes/two-kinds/">a</a> and <a href="/notes/no-excerpt/">b</a>' );
ok( 2 === substr_count( $multi, 'data-sn-preview-title=' ), 'two qualifying links → two stamps' );

// ── 8. #383: the port paints the same DOM as the regex it replaced ──
// Each expected string is the byte-for-byte output of origin/main's
// preg_replace_callback (captured 2026-09-20 with these stubs) for the same
// fixture. The bytes differ in attribute order (set_attribute() places the
// stamp right after the tag name, the splice appended it before '>'); the
// shape (tags, decoded attributes, text) is identical, which is what a reader
// and the preview script see.
$captured = array(
	'absolute' => array( $in, '<p>See <a href="https://x.test/notes/two-kinds/" data-sn-preview-title="Two kinds of provenance" data-sn-preview-summary="The opening, hand-set." data-sn-preview-meta="2026.08.26 · 03 MIN">this note</a> for more.</p>' ),
	'derived'  => array( '<a href="/notes/no-excerpt/">x</a>', '<a href="/notes/no-excerpt/" data-sn-preview-title="A note with no excerpt" data-sn-preview-summary="word word word word word word word word word word word word word word word word word word word word word word word word word word word word…" data-sn-preview-meta="2026.08.26 · 03 MIN">x</a>' ),
	'fragment' => array( '<a href="/notes/two-kinds/#section">deep</a>', '<a href="/notes/two-kinds/#section" data-sn-preview-title="Two kinds of provenance" data-sn-preview-summary="The opening, hand-set." data-sn-preview-meta="2026.08.26 · 03 MIN">deep</a>' ),
	'hostile'  => array( '<a href="/notes/hostile/">h</a>', '<a href="/notes/hostile/" data-sn-preview-title="A &quot;quoted&quot; &lt;title&gt;" data-sn-preview-summary="Summary with &quot;quotes&quot; &amp; ampersand." data-sn-preview-meta="2026.08.26 · 03 MIN">h</a>' ),
	'two'      => array( '<a href="/notes/two-kinds/">a</a> and <a href="/notes/no-excerpt/">b</a>', '<a href="/notes/two-kinds/" data-sn-preview-title="Two kinds of provenance" data-sn-preview-summary="The opening, hand-set." data-sn-preview-meta="2026.08.26 · 03 MIN">a</a> and <a href="/notes/no-excerpt/" data-sn-preview-title="A note with no excerpt" data-sn-preview-summary="word word word word word word word word word word word word word word word word word word word word word word word word word word word word…" data-sn-preview-meta="2026.08.26 · 03 MIN">b</a>' ),
	'attrs'    => array( '<a class="x" href="/notes/two-kinds/" rel="bookmark">c</a>', '<a class="x" href="/notes/two-kinds/" rel="bookmark" data-sn-preview-title="Two kinds of provenance" data-sn-preview-summary="The opening, hand-set." data-sn-preview-meta="2026.08.26 · 03 MIN">c</a>' ),
);
foreach ( $captured as $name => $pair ) {
	ok( snt_html_shape( $pair[1] ) === snt_html_shape( sn_note_link_previews_filter( $pair[0] ) ), "#383 $name: the port paints the DOM origin/main's regex painted (same anchor, same three stamps, same text)" );
}
// Where the stamp sits: right after the tag name, the HTML API's placement.
// Several attributes added at one point serialise in byte order of their
// text (the processor's tie-break), so meta, summary, title.
ok( 0 === strpos( sn_note_link_previews_filter( '<a href="/notes/two-kinds/">a</a>' ), '<a data-sn-preview-meta="2026.08.26 · 03 MIN" data-sn-preview-summary="The opening, hand-set." data-sn-preview-title="Two kinds of provenance" href="/notes/two-kinds/">' ), '#383: the three stamps land after the tag name, before href, in the processor\'s order (meta, summary, title)' );
// A texturized excerpt (&#8217;, &amp;) and an apostrophe in a title reach
// the reader as their characters: decoded once (sn_link_preview_text),
// encoded once (set_attribute), never doubled. The excerpt is what
// get_the_excerpt() hands over on a live site, so this is the live case.
mk_note( 10, 'texturized', "Detection doesn't scale", 'Body.', 'It&#8217;s the reader&#8217;s call &amp; nobody else&#8217;s.' );
$tex = new WP_HTML_Tag_Processor( sn_note_link_previews_filter( '<a href="/notes/texturized/">t</a>' ) );
$tex->next_tag( 'A' );
ok( "Detection doesn't scale" === $tex->get_attribute( 'data-sn-preview-title' ), '#383: a title with an apostrophe reads back as typed' );
ok( 'It’s the reader’s call & nobody else’s.' === $tex->get_attribute( 'data-sn-preview-summary' ), '#383: a texturized excerpt reads back as its characters, never as a double-encoded reference' );
// The module rewrites through the HTML API and nothing else: the one regex
// left runs on the decoded href VALUE, not on markup.
$lp_src = (string) file_get_contents( __DIR__ . '/../inc/note-link-previews.php' );
ok( 0 === preg_match( '/preg_replace(?:_callback)?\s*\(|substr_replace\s*\(|substr\s*\(\s*\$whole/', $lp_src ), '#383: inc/note-link-previews.php carries no preg_replace, no preg_replace_callback and no byte splice; the anchors are read and stamped through WP_HTML_Tag_Processor' );
ok( false !== strpos( $lp_src, 'new WP_HTML_Tag_Processor(' ) && 3 === substr_count( $lp_src, '$tags->set_attribute( \'data-sn-preview-' ), '#383: the three stamps land through set_attribute() on the processor' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
