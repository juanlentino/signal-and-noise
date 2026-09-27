<?php
/**
 * Tests the /notes document <title> single-owner: the theme's
 * pre_get_document_title filter appends "— Page N" for N>1 (and nothing for
 * page 1). The paged read is inlined (no render-file dependency).
 *
 * NOTE: SN_NOTES_OVERRIDE_BUILD is NOT pre-defined here because the file uses
 * a bare `const` (unguarded), so pre-defining it would cause a fatal
 * "Constant already defined" error. The constant is not used by
 * sn_notes_index_title(), so the test doesn't need it.
 */
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) {
	http_response_code( 404 );
	exit;
}
define( 'ABSPATH', '/' );

$GLOBALS['__qv'] = array();
$GLOBALS['__setting'] = ''; // sn_setting() below is hoisted; the early cases read a blank setting.
function get_query_var( $k, $d = '' ) { return $GLOBALS['__qv'][ $k ] ?? $d; }
function get_bloginfo( $w ) { return 'name' === $w ? 'Juan Lentino' : ''; }
function add_action() {}
function add_filter() {}
function home_url( $p = '' ) { return 'https://example.com' . $p; }
function esc_html( $s ) { return (string) $s; }

$pass = 0; $fail = 0;
function ok( $c, $m ) { global $pass, $fail; if ( $c ) { $pass++; echo "PASS: $m\n"; } else { $fail++; echo "FAIL: $m\n"; } }

require __DIR__ . '/../inc/page-notes-template.php';

// Page 1 → no suffix.
$GLOBALS['__qv'] = array(); unset( $_GET['paged'] );
ok( 'Notes — Juan Lentino' === sn_notes_index_title(), 'title: page 1 has no page suffix' );

// Page 2 (query var) → suffix.
$GLOBALS['__qv']['paged'] = 2;
ok( 'Notes — Juan Lentino — Page 2' === sn_notes_index_title(), 'title: page 2 appends "— Page 2"' );

// Page 3 via $_GET fallback.
$GLOBALS['__qv'] = array(); $_GET['paged'] = '3';
ok( 'Notes — Juan Lentino — Page 3' === sn_notes_index_title(), 'title: $_GET paged fallback appends "— Page 3"' );
unset( $_GET['paged'] );

// Starts with "Notes".
$GLOBALS['__qv'] = array();
ok( strpos( sn_notes_index_title(), 'Notes' ) === 0, 'title: starts with "Notes"' );

// 14.4.2: the owner's "/notes title" setting wins, and keeps the page suffix.
$GLOBALS['__setting'] = 'Music provenance, AI and rights: notes by Juan Lentino';
function sn_setting( $k, $d = '' ) { return 'seo_copy.notes_title' === $k ? $GLOBALS['__setting'] : $d; }
$GLOBALS['__qv'] = array();
ok( 'Music provenance, AI and rights: notes by Juan Lentino' === sn_notes_index_title(), 'title: the /notes title setting is the <title>' );
$GLOBALS['__qv']['paged'] = 2;
ok( 'Music provenance, AI and rights: notes by Juan Lentino — Page 2' === sn_notes_index_title(), 'title: the setting keeps the page suffix' );
$GLOBALS['__qv'] = array(); $GLOBALS['__setting'] = '  ';
ok( 'Notes — Juan Lentino' === sn_notes_index_title(), 'title: a blank setting falls back to the default' );

echo "Result: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
