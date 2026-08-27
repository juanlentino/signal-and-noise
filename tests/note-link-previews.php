<?php
/**
 * Standalone fixture tests for internal note-link hover previews (v12.10.0).
 *
 * inc/note-link-previews.php: the the_content filter that stamps qualifying
 * internal /notes/<slug>/ anchors with data-sn-preview-* attributes. The
 * REAL filter and the REAL summary derivation run here (plus the real
 * sn_notes_render_date / sn_notes_render_reading_time from
 * inc/notes-index-helpers.php); only WP core is stubbed.
 *
 * @since theme v12.10.0
 */
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', '/' ); }
define( 'SN_NOTE_LINK_PREVIEWS_TEST', true );

$pass = 0; $fail = 0;
function ok( $c, $m ) { global $pass, $fail; if ( $c ) { $pass++; echo "PASS: $m\n"; } else { $fail++; echo "FAIL: $m\n"; } }

// ── WP stubs (environment only) ──
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
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

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
