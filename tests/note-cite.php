<?php
/**
 * Standalone test: the cite line (inc/note-cite.php, blocks/note-cite/).
 *
 * One line in the closing footer: the APA citation as text, its DOI as a
 * link, and two buttons that copy the APA line and the BibTeX entry. The
 * citation is the companion plugin's (sn_cite_this_for), read the way the
 * print colophon reads the view-model: function_exists()-guarded, so without
 * the plugin, off a single note, or for a post with no citation, nothing
 * renders. The copy behavior is note-share.js's (tests/js/note-share.test.cjs).
 *
 * Run: php tests/note-cite.php
 */

if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }
define( 'ABSPATH', '/' );

$pass = 0; $fail = 0;
function ok( $c, $m ) { global $pass, $fail; if ( $c ) { ++$pass; echo "PASS: $m\n"; } else { ++$fail; echo "FAIL: $m\n"; } }

$GLOBALS['__singular'] = true;
function is_singular( $t = '' ) { return 'post' === $t && $GLOBALS['__singular']; }
function get_queried_object_id() { return 7; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $s ) { $s = (string) $s; return preg_match( '#^https?://#', $s ) ? str_replace( array( '"', '<', '>', '&' ), array( '%22', '%3C', '%3E', '&amp;' ), $s ) : ''; }
function esc_html__( $s, $d = null ) { return esc_html( $s ); }
function __( $s, $d = null ) { return $s; }

require_once __DIR__ . '/../inc/note-cite.php';

$root = dirname( __DIR__ );
$apa  = 'Lentino, J. (2026, October 17). The credit that has to stay private (Version 2). Signal & Noise. https://doi.org/10.5281/zenodo.23426657';
$bib  = "@online{lentino2026credit,\n  author = {Lentino, Juan},\n  title = {The credit that has to stay private},\n  organization = {Signal \\& Noise},\n  doi = {10.5281/zenodo.23426657}\n}";
$cite = array( 'apa' => $apa, 'bibtex' => $bib, 'doi' => '10.5281/zenodo.23426657', 'doi_url' => 'https://doi.org/10.5281/zenodo.23426657', 'url' => 'https://juanlentino.com/notes/the-credit-that-has-to-stay-private/' );

/** Every data-sn-copy-text value in $html, decoded the way getAttribute() decodes it. */
function copy_texts( $html ) {
	preg_match_all( '/data-sn-copy-text="([^"]*)"/', $html, $m );
	return array_map( static fn( $v ) => html_entity_decode( $v, ENT_QUOTES | ENT_HTML5, 'UTF-8' ), $m[1] );
}

echo "Group 1: degrades to nothing\n";
ok( ! function_exists( 'sn_cite_this_for' ) && '' === sn_note_cite_html(), 'companion plugin absent (no sn_cite_this_for, checked): renders nothing' );
$GLOBALS['__cite'] = null;
if ( ! function_exists( 'sn_cite_this_for' ) ) { // in a block: not hoisted, so the first call really runs without it
	function sn_cite_this_for( $id ) { return $GLOBALS['__cite']; }
}
ok( '' === sn_note_cite_html(), 'the plugin returns no citation (null): renders nothing' );
$GLOBALS['__cite'] = $cite; $GLOBALS['__singular'] = false;
ok( '' === sn_note_cite_html(), 'off a single note: renders nothing' );
$GLOBALS['__singular'] = true;

echo "\nGroup 2: the line\n";
$out = sn_note_cite_html();
ok( 0 === strpos( $out, '<div class="sn-note-cite">' ), 'renders a <div class="sn-note-cite"> row, the share row\'s shape' );
ok( false !== strpos( $out, '<span class="sn-note-cite__label">Cite</span>' ), 'a "Cite" label in the share row\'s voice' );
ok( false !== strpos( $out, '(Version 2). Signal &amp; Noise. <a href="https://doi.org/10.5281/zenodo.23426657">https://doi.org/10.5281/zenodo.23426657</a></span>' ), 'the APA line as visible text, its DOI a link' );
ok( 2 === substr_count( $out, '<button type="button" class="sn-note-cite__copy"' ), 'two type="button" copy buttons' );
ok( false !== strpos( $out, '>COPY APA</button>' ) && false !== strpos( $out, '>COPY BIBTEX</button>' ), 'their accessible names are their visible labels, COPY APA and COPY BIBTEX' );
ok( array( $apa, $bib ) === copy_texts( $out ), 'the buttons carry the plugin\'s APA and BibTeX exactly, newlines and all, as getAttribute() reads them' );
ok( false !== strpos( $out, 'data-sn-copied="APA citation copied to clipboard"' ) && false !== strpos( $out, 'data-sn-copied="BibTeX copied to clipboard"' ), 'each button names what the live region announces' );
ok( false === strpos( $out, "\n" ), 'one line of markup, so the wpautop wrapper cannot insert <br> or <p> into it' );
ok( false === strpos( $out, '<details' ) && false === strpos( $out, "\u{2014}" ), 'no fold, no em dash' );

echo "\nGroup 3: without a DOI, and escaping\n";
$GLOBALS['__cite'] = array_merge( $cite, array( 'apa' => 'Lentino, J. (2026, October 17). A <b>note</b>. Signal & Noise. https://juanlentino.com/notes/a-note/', 'doi' => '', 'doi_url' => '', 'bibtex' => 'title = {"><script>x</script>}' ) );
$out = sn_note_cite_html();
ok( false === strpos( $out, '<a ' ) && false !== strpos( $out, 'Signal &amp; Noise. https://juanlentino.com/notes/a-note/</span>' ), 'no DOI: no link; the line ends with the permalink as text' );
ok( false === strpos( $out, '<b>' ) && false === strpos( $out, '<script' ), 'a title carrying markup renders as text, in the line and in the attributes' );

echo "\nGroup 4: placement, the block, the sheets\n";
$part = (string) file_get_contents( $root . '/parts/post-closing.html' );
ok( 1 === substr_count( $part, '<!-- wp:signal-noise/note-cite /-->' ), 'parts/post-closing.html carries the block once' );
ok( strpos( $part, '<!-- wp:signal-noise/note-share /-->' ) < (int) strpos( $part, '<!-- wp:signal-noise/note-cite /-->' ) && strpos( $part, '<!-- wp:signal-noise/note-cite /-->' ) < strpos( $part, '<!-- wp:signal-noise/note-reply /-->' ), '   ...right under the share row, above the reply row' );
$bj = json_decode( (string) file_get_contents( $root . '/blocks/note-cite/block.json' ), true );
ok( 'signal-noise/note-cite' === ( $bj['name'] ?? '' ) && ! isset( $bj['attributes'] ) && 'sn-note-share' === ( $bj['viewScript'] ?? '' ), 'block.json: no attributes, and the share row\'s script (one copy of the clipboard code)' );
$css = preg_replace( '~/\*.*?\*/~s', '', (string) file_get_contents( $root . '/assets/css/components-07-note-share-reply.css' ) );
ok( 1 === preg_match( '/\.sn-note-cite__copy[^{]*\{[^}]*min-height:\s*44px/s', $css ) && 1 === preg_match( '/\.sn-note-cite__copy\.is-copied/', $css ), 'the buttons share the share row\'s rules: the 44px target and the copied state' );
ok( 1 === preg_match( '/\.sn-note-cite__text\s*\{[^}]*font-size:\s*max\(0\.7rem, 11px\)/s', $css ), 'the citation text is never under 11px' );
$print = preg_replace( '~/\*.*?\*/~s', '', (string) file_get_contents( $root . '/assets/css/print.css' ) );
ok( 1 === preg_match( '/\.sn-note-cite__copy\s*[,{]/', $print ) && 0 === preg_match( '/\.sn-note-cite\s*[,{]/', $print ), 'print hides the buttons, keeps the citation (the colophon carries no citation of its own)' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
