<?php
/**
 * Standalone fixture tests for inc/note-uid.php (v10.49.0).
 *
 * sn_theme_note_uid() is the single canonical read of the plugin-owned
 * `_sn_prov_uid` post meta. Before v10.49.0 the lowercase+trim
 * normalization was inlined in THREE modules (content-json-document,
 * feed-json, feed-enrichment) and had already drifted: the .json twin
 * lacked the trim, so a uid stored with stray whitespace would republish
 * whitespace into the machine surface the /verify docket matches against.
 * This fixture pins the normalization — INCLUDING the trim — in one place.
 *
 * @since theme v10.49.0
 */

// SECURITY: CLI-only fixture.
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', '/' ); }

$pass = 0; $fail = 0;
function ok( $cond, $msg ) { global $pass, $fail; if ( $cond ) { $pass++; echo "PASS: $msg\n"; } else { $fail++; echo "FAIL: $msg\n"; } }

// Meta store stub.
$GLOBALS['__meta'] = array();
function get_post_meta( $post_id, $key, $single = false ) {
	return $GLOBALS['__meta'][ (int) $post_id ][ $key ] ?? '';
}

require __DIR__ . '/../inc/note-uid.php';

ok( function_exists( 'sn_theme_note_uid' ), 'sn_theme_note_uid() exists' );

// Normalization: lowercase.
$GLOBALS['__meta'][1] = array( '_sn_prov_uid' => 'DEADBEEF-Dead-4Eef-8eef-DEADBEEFdead' );
ok( 'deadbeef-dead-4eef-8eef-deadbeefdead' === sn_theme_note_uid( 1 ), 'uid is lowercased' );

// Normalization: trim — the drift the shared helper exists to close.
$GLOBALS['__meta'][2] = array( '_sn_prov_uid' => "  AB12cd34\n" );
ok( 'ab12cd34' === sn_theme_note_uid( 2 ), 'leading/trailing whitespace (incl. newline) is trimmed' );
$GLOBALS['__meta'][3] = array( '_sn_prov_uid' => "\tab12cd34 " );
ok( 'ab12cd34' === sn_theme_note_uid( 3 ), 'tab + trailing space trimmed' );

// Absent meta → ''.
ok( '' === sn_theme_note_uid( 99 ), 'absent meta yields an empty string' );

// Whitespace-only meta → '' (never a fabricated value).
$GLOBALS['__meta'][4] = array( '_sn_prov_uid' => "   " );
ok( '' === sn_theme_note_uid( 4 ), 'whitespace-only meta normalizes to empty' );

// Non-string meta defensively cast.
$GLOBALS['__meta'][5] = array( '_sn_prov_uid' => 12345 );
ok( '12345' === sn_theme_note_uid( 5 ), 'non-string meta is cast to string' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
