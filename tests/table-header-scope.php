<?php
/**
 * Standalone test: the comparison table's header cells get scope="col" on
 * render (inc/table-header-scope.php).
 *
 * Measured 2026-10-16 on WordPress 7.1: a table built in the block editor
 * saves <th> with no scope (core's table state never sets it and the UI has
 * no control for it), so a convention that required scope="col" in stored
 * markup was one its author could not meet. The house style now guarantees
 * it at render, through core's HTML API, and only for is-style-sn-table.
 *
 * Run: php tests/table-header-scope.php
 */

if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }
define( 'ABSPATH', '/' );
$GLOBALS['__filters'] = array();
function add_filter( $h, $c, $p = 10, $a = 1 ) { $GLOBALS['__filters'][ $h ][] = $c; }
require_once __DIR__ . '/lib/wp-html-api.php';
snt_require_wp_html_api();
require_once __DIR__ . '/../inc/table-header-scope.php';

$pass = 0; $fail = 0;
function ok( $c, $m ) { global $pass, $fail; if ( $c ) { ++$pass; echo "PASS: $m\n"; } else { ++$fail; echo "FAIL: $m\n"; } }

$editor_made = '<figure class="wp-block-table is-style-sn-table"><table class="has-fixed-layout"><thead><tr><th>Identifier</th><th>What it names</th></tr></thead><tbody><tr><th>ISRC</th><td>One recording</td></tr></tbody></table></figure>';
$styled      = array( 'blockName' => 'core/table', 'attrs' => array( 'className' => 'is-style-sn-table' ) );

ok( in_array( 'sn_table_header_scope', $GLOBALS['__filters']['render_block_core/table'] ?? array(), true ), 'hooked on render_block_core/table' );
$out = sn_table_header_scope( $editor_made, $styled );
ok( 2 === substr_count( $out, '<th scope="col">' ), 'both header cells in <thead> gain scope="col" (' . $out . ')' );
ok( false !== strpos( $out, '<tbody><tr><th>ISRC</th>' ), 'a <th> in <tbody> (a row header) is left alone: it is not a column header' );
ok( strip_tags( $out ) === strip_tags( $editor_made ), 'the text is untouched (nothing the ledger signs changes)' );
$kept = sn_table_header_scope( str_replace( '<th>Identifier</th>', '<th scope="colgroup">Identifier</th>', $editor_made ), $styled );
ok( false !== strpos( $kept, 'scope="colgroup"' ) && 1 === substr_count( $kept, 'scope="col"' ), 'an author-set scope is kept, never overwritten' );
ok( $editor_made === sn_table_header_scope( $editor_made, array( 'blockName' => 'core/table', 'attrs' => array() ) ), 'an unstyled table is left exactly as stored' );
ok( $editor_made === sn_table_header_scope( $editor_made, array( 'blockName' => 'core/table', 'attrs' => array( 'className' => 'is-style-sn-table-x' ) ) ), 'a class that merely starts with the style name is not the style' );
$no_head = '<figure class="wp-block-table is-style-sn-table"><table><tbody><tr><td>a</td></tr></tbody></table></figure>';
ok( $no_head === sn_table_header_scope( $no_head, $styled ), 'no <thead>: nothing to do, returned unchanged' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
