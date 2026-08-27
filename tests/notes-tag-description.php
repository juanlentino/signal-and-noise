<?php
/**
 * Tests for sn_notes_tag_description() (theme v12.11.0).
 *
 * The tag-archive hero otherwise repeats one identical dek across all 23 tag
 * archives — the thin-content shape the contentless-page rule warns about. A
 * term that carries a description speaks for itself; one that does not keeps
 * the generic dek rather than getting an invented sentence.
 *
 * The plugin reads the SAME term description for the tag meta description
 * (plugin v13.14.0), so one written sentence lights up both surfaces.
 *
 * @since theme v12.11.0
 */
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', '/' ); }

$pass = 0; $fail = 0;
function ok( $c, $m ) { global $pass, $fail; if ( $c ) { $pass++; echo "PASS: $m\n"; } else { $fail++; echo "FAIL: $m\n"; } }

$GLOBALS['__is_tag'] = false;
$GLOBALS['__term']   = null;
function is_tag() { return (bool) $GLOBALS['__is_tag']; }
function get_queried_object() { return $GLOBALS['__term']; }
function wp_strip_all_tags( $s ) { return trim( strip_tags( (string) $s ) ); }
// Stubs for the rest of the helper file's load-time surface.
function apply_filters( $t, $v ) { return $v; }
function add_filter() {}
function add_action() {}

require __DIR__ . '/../inc/notes-index-helpers.php';

function mk_term( $desc ) {
	$t = new stdClass();
	$t->term_id = 7; $t->name = 'Provenance'; $t->description = $desc;
	return $t;
}

// ── Not a tag view: always empty, whatever is queried ──
$GLOBALS['__is_tag'] = false;
$GLOBALS['__term']   = mk_term( 'Should never surface off a tag view.' );
ok( '' === sn_notes_tag_description(), 'off a tag view the helper returns empty' );

// ── Tag view, description written ──
$GLOBALS['__is_tag'] = true;
$GLOBALS['__term']   = mk_term( '  Where a recording proves its own origin.  ' );
ok( 'Where a recording proves its own origin.' === sn_notes_tag_description(), 'a written description is returned, trimmed' );

// ── Tag view, markup in the description ──
$GLOBALS['__term'] = mk_term( '<p>Attribution, <em>verified</em>.</p>' );
ok( 'Attribution, verified.' === sn_notes_tag_description(), 'markup is stripped before it reaches the hero' );

// ── Tag view, nothing written: the generic dek must survive ──
$GLOBALS['__term'] = mk_term( '' );
ok( '' === sn_notes_tag_description(), 'an unwritten description returns empty so the corpus dek is kept' );
$GLOBALS['__term'] = mk_term( '   ' );
ok( '' === sn_notes_tag_description(), 'a whitespace-only description counts as unwritten, not as content' );

// ── Degenerate queried objects never fatal ──
$GLOBALS['__term'] = null;
ok( '' === sn_notes_tag_description(), 'no queried object → empty, not a fatal' );
$obj = new stdClass();
$obj->term_id = 7; // no ->description at all
$GLOBALS['__term'] = $obj;
ok( '' === sn_notes_tag_description(), 'a term object without a description property → empty' );

// ── The template actually consumes it, and keeps the fallback ──
$tpl = (string) file_get_contents( __DIR__ . '/../inc/page-notes-render.php' );
ok( false !== strpos( $tpl, 'sn_notes_tag_description()' ), 'the notes template calls the helper' );
ok( false !== strpos( $tpl, 'Working notes on music, AI' ), 'the generic corpus dek is still present as the fallback' );
ok( substr_count( $tpl, 'class="sn-notes-dek"' ) === 2, 'exactly two dek branches: the tag description and the fallback' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
