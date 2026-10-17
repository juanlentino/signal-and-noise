<?php
/**
 * Standalone test: the signal-noise/term dynamic block (blocks/term/).
 *
 * Pins the render callback: a <dl class="sn-term"> with one <dt> and one <dd>,
 * BOTH attributes passed through esc_html (never kses: the editor writes plain
 * text, so markup in an attribute is text to show, not HTML to keep), and the
 * <p> inside each cell that lets the public verifier's block-ending rule
 * (</p> -> </p>\n\n) see the same paragraph break the ledger signs.
 *
 * Run: php tests/term-block.php
 *
 * @since 2026.10.11
 */

if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }
define( 'ABSPATH', '/' );

$pass = 0; $fail = 0;
function ok( $c, $m ) { global $pass, $fail; if ( $c ) { ++$pass; echo "PASS: $m\n"; } else { ++$fail; echo "FAIL: $m\n"; } }

$GLOBALS['__esc_html'] = 0;
function esc_html( $s ) { ++$GLOBALS['__esc_html']; return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function wp_kses_post( $s ) { throw new RuntimeException( 'the term block must not route its attributes through kses' ); }
function get_block_wrapper_attributes( $extra = array() ) { return 'class="wp-block-signal-noise-term ' . ( $extra['class'] ?? '' ) . '"'; }

$dir = __DIR__ . '/../blocks/term';
function term_render( $attributes ) {
	global $dir;
	ob_start();
	( static function () use ( $dir, $attributes ) { include $dir . '/render.php'; } )();
	return (string) ob_get_clean();
}

echo "Group 1: block.json\n";
$bj = json_decode( (string) @file_get_contents( $dir . '/block.json' ), true );
ok( is_array( $bj ) && 'signal-noise/term' === $bj['name'] && 3 === (int) $bj['apiVersion'], 'block.json parses: signal-noise/term, apiVersion 3' );
ok( 'signal-noise-blocks-editor' === ( $bj['editorScript'] ?? '' ) && 'file:./render.php' === ( $bj['render'] ?? '' ) && 'file:./style.css' === ( $bj['style'] ?? '' ), 'sidenote shape: the shared editor handle, render.php, its own sheet' );
ok( array( 'term', 'definition' ) === array_keys( (array) ( $bj['attributes'] ?? array() ) ), 'attributes are term then definition, in that order (the ledger signs them in JSON order)' );
foreach ( array( 'term', 'definition' ) as $a ) {
	ok( 'string' === ( $bj['attributes'][ $a ]['type'] ?? '' ) && ! isset( $bj['attributes'][ $a ]['source'] ), "$a is a plain string attribute (lives in the delimiter, arrives in render.php)" );
}
ok( false === ( $bj['supports']['html'] ?? null ), 'supports.html is false' );

echo "\nGroup 2: render\n";
$out = term_render( array( 'term' => 'ISRC', 'definition' => 'The code for one recording.' ) );
ok( 1 === preg_match( '#^<dl class="wp-block-signal-noise-term sn-term"><dt><p>ISRC</p></dt><dd><p>The code for one recording\.</p></dd></dl>$#', $out ), 'renders <dl class="sn-term"><dt><p>term</p></dt><dd><p>definition</p></dd></dl> (' . $out . ')' );
$GLOBALS['__esc_html'] = 0;
$out = term_render( array( 'term' => '<script>alert(1)</script>', 'definition' => 'A "quoted" <b>tag</b> & ampersand.' ) );
ok( 2 === $GLOBALS['__esc_html'], 'esc_html runs once per attribute (' . $GLOBALS['__esc_html'] . ' calls)' );
ok( false === strpos( $out, '<script' ) && false !== strpos( $out, '&lt;script&gt;alert(1)&lt;/script&gt;' ), 'a <script> in the term renders as text, never as a tag' );
ok( false === strpos( $out, '<b>' ) && false !== strpos( $out, '&lt;b&gt;tag&lt;/b&gt; &amp; ampersand' ) && false !== strpos( $out, '&quot;quoted&quot;' ), 'markup, quotes and ampersands in the definition are escaped' );

echo "\nGroup 3: empty slots\n";
ok( '' === term_render( array( 'term' => '', 'definition' => '' ) ), 'both empty: renders nothing (no empty <dl>)' );
ok( '' === term_render( array() ), 'no attributes at all: renders nothing' );
$out = term_render( array( 'term' => 'ISRC', 'definition' => '' ) );
ok( false !== strpos( $out, '<dt><p>ISRC</p></dt>' ) && false === strpos( $out, '<dd' ), 'term only: the <dt> and no empty <dd>' );
$out = term_render( array( 'term' => '', 'definition' => 'Orphan definition.' ) );
ok( false === strpos( $out, '<dl' ) && false !== strpos( $out, '<p class="wp-block-signal-noise-term sn-term">Orphan definition.</p>' ), 'definition only: a plain paragraph, never a <dd> without its <dt> (invalid <dl>)' );
// The ledger's expansion signs STRING values only (sn_prov_expand_block_text),
// so a non-string slot must render nothing, or page and record disagree.
$out = term_render( array( 'term' => 42, 'definition' => 'Def.' ) );
ok( false === strpos( $out, '42' ) && false !== strpos( $out, 'Def.' ), 'a non-string term (tampered delimiter JSON) renders nothing for that slot: the ledger never signs it either' );
ok( '' === term_render( array( 'term' => array( 'x' ), 'definition' => null ) ), 'non-string in both slots: nothing at all, no warning' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
