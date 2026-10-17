<?php
/**
 * Standalone test: the pillar essays carry a tracklist under their signal path
 * (inc/essay-tracklist.php, 2026-10-17): each "Section N" with its heading, a
 * link to it, and its length at the byline's reading rate. Wrapped sections and
 * loose ones (a label and heading straight in the content) both count.
 *
 * Run: php tests/essay-tracklist.php
 */
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }
define( 'ABSPATH', '/' );
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_attr__( $s, $d = null ) { return esc_attr( $s ); }
function wp_strip_all_tags( $s ) { return trim( strip_tags( (string) $s ) ); }
function apply_filters( $h, $v ) { return 'sn_reading_time_wpm' === $h && isset( $GLOBALS['__wpm'] ) ? $GLOBALS['__wpm'] : $v; }
function get_post( $id ) { return $GLOBALS['__posts'][ (int) $id ] ?? null; }
function sn_get_reading_time( $p ) { return 5; }
require dirname( __DIR__ ) . '/inc/essay-tracklist.php';

$pass = 0; $fail = 0;
function ok( $c, $m ) { global $pass, $fail; if ( $c ) { ++$pass; echo "PASS: $m\n"; } else { ++$fail; echo "FAIL: $m\n"; } }
$w = static fn( $n ) => implode( ' ', array_fill( 0, $n, 'word' ) );

$content = '<!-- wp:paragraph --><p class="hero">Intro words that sit before any section.</p><!-- /wp:paragraph -->'
	. '<!-- wp:group {"tagName":"section"} --><section class="wp-block-group sn-provenance-section" id="setup"><!-- wp:paragraph --><p class="sn-provenance-section-eyebrow has-blood-color">Section 1</p><!-- /wp:paragraph -->'
	. '<!-- wp:heading --><h2 class="wp-block-heading font-display">The <em>setup</em> &amp; more</h2><!-- /wp:heading --><!-- wp:paragraph --><p>' . $w( 450 ) . '</p><!-- /wp:paragraph --></section><!-- /wp:group -->'
	. '<!-- wp:group {"tagName":"section"} --><section id="analogy" class="wp-block-group sn-provenance-section"><p class="sn-provenance-section-eyebrow">Section 2</p><h2 class="wp-block-heading">The analogy</h2><p>' . $w( 225 ) . '</p></section><!-- /wp:group -->'
	. "<!-- wp:paragraph -->\n<p class=\"has-blood-color has-text-color\">Section 3</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:heading -->\n<h2 class=\"wp-block-heading\" id=\"the-economics\">The economics</h2>\n<!-- /wp:heading -->\n<p>" . $w( 100 ) . '</p>';
$GLOBALS['__posts'] = array(
	7 => (object) array( 'ID' => 7, 'post_content' => $content ),
	8 => (object) array( 'ID' => 8, 'post_content' => '<p>Section 1</p><h2 id="a">Only one</h2><p>x</p>' ),
	9 => (object) array( 'ID' => 9, 'post_content' => '<h2 id="a">A</h2><p>no labels here</p><h2 id="b">B</h2>' ),
);

$t = sn_essay_tracks( $GLOBALS['__posts'][7]->post_content, 225 );
ok( 3 === count( $t ), 'wrapped and loose sections both count, and the intro before Section 1 does not' );
ok( 'The setup & more' === $t[0]['title'], 'titles are plain text: inline markup stripped, entities decoded' );
ok( 'setup' === $t[0]['id'] && 'analogy' === $t[1]['id'] && 'the-economics' === $t[2]['id'], 'each links to its section id, or to its heading id when it is loose' );
ok( 120 === $t[0]['seconds'] && 60 === $t[1]['seconds'], 'length is words at the byline rate: 450 words at 225 wpm is 2:00' );
ok( $t[2]['seconds'] > 0 && $t[2]['seconds'] < 30, 'a loose section ends where the content ends' );

$h = sn_essay_tracklist_html( 7 );
ok( 0 === strpos( $h, '<nav class="sn-essay-tracklist" aria-label="Sections">' ), 'a labelled nav' );
ok( false !== strpos( $h, '3 sections' ) && false !== strpos( $h, '<span>5 min</span>' ), 'the head counts the sections and gives the byline\'s own total, without repeating "read"' );
ok( 3 === substr_count( $h, '<li>' ) && false !== strpos( $h, 'href="#the-economics"' ), 'one linked row per section' );
ok( false !== strpos( $h, '<time datetime="PT2M0S">2:00</time>' ) && false !== strpos( $h, '<time datetime="PT1M0S">1:00</time>' ), 'lengths are m:ss with a machine-readable duration' );
ok( false !== strpos( $h, 'The setup &amp; more' ) && false === strpos( $h, '<em>' ), 'titles are escaped on output' );
ok( false !== strpos( $h, '>A1<' ) && false !== strpos( $h, '>A3<' ), 'rows are numbered like a side' );
$GLOBALS['__wpm'] = 450;
ok( false !== strpos( sn_essay_tracklist_html( 7 ), '<time datetime="PT1M0S">1:00</time>' ), 'a filtered reading rate moves the lengths with the byline' );
unset( $GLOBALS['__wpm'] );
ok( '' === sn_essay_tracklist_html( 8 ), 'a single section is not a tracklist' );
ok( '' === sn_essay_tracklist_html( 9 ), 'headings without Section labels are not tracks' );
ok( '' === sn_essay_tracklist_html( 404 ), 'no post, no tracklist' );
ok( false !== strpos( (string) file_get_contents( dirname( __DIR__ ) . '/inc/pillar-title-eyebrow.php' ), 'sn_essay_tracklist_html( $page_id )' ), 'the eyebrow filter emits it after the signal path, outside the signed content' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
