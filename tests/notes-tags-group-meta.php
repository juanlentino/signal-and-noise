<?php
/**
 * Tests: inc/notes-tags-group-meta.php, the Group field on Posts › Tags (13.4.0).
 * Run: php tests/notes-tags-group-meta.php
 */
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', '/' ); }
$pass = 0; $fail = 0;
function ok( $c, $m ) { global $pass, $fail; if ( $c ) { $pass++; } else { $fail++; echo "FAIL: $m\n"; } }

$GLOBALS['K'] = array( 'meta' => array(), 'actions' => array(), 'filters' => array(), 'registered' => array(), 'terms' => array() );
function add_action( $t, $c, $p = 10, $a = 1 ) { $GLOBALS['K']['actions'][ $t ][] = $c; return true; }
function add_filter( $t, $c, $p = 10, $a = 1 ) { $GLOBALS['K']['filters'][ $t ][] = $c; return true; }
function register_term_meta( $tax, $key, $args ) { $GLOBALS['K']['registered'][ $key ] = $args; return true; }
function get_term_meta( $id, $key = '', $single = false ) { return $GLOBALS['K']['meta'][ (int) $id ][ $key ] ?? ''; }
function update_term_meta( $id, $key, $v ) { $GLOBALS['K']['meta'][ (int) $id ][ $key ] = $v; return true; }
function delete_term_meta( $id, $key ) { unset( $GLOBALS['K']['meta'][ (int) $id ][ $key ] ); return true; }
function get_term( $id, $tax = '' ) { return $GLOBALS['K']['terms'][ (int) $id ] ?? null; }
function get_terms( $a = array() ) { return array_values( $GLOBALS['K']['terms'] ); }
function is_wp_error( $t ) { return false; }
$GLOBALS['K']['can'] = true; $GLOBALS['K']['nonce_ok'] = true;
function current_user_can( $c, $id = 0 ) { $GLOBALS['K']['cap_asked'] = array( $c, $id ); return $GLOBALS['K']['can']; }
function wp_verify_nonce( $n, $a ) { $GLOBALS['K']['nonce_asked'][] = $a; return $GLOBALS['K']['nonce_ok'] && 'ok' === $n; }
function sanitize_text_field( $s ) { return is_string( $s ) ? trim( $s ) : ''; }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_html__( $s, $d = null ) { return esc_html( $s ); }
function __( $s, $d = null ) { return $s; }
function selected( $a, $b, $echo = true ) { return (string) $a === (string) $b ? ' selected="selected"' : ''; }
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ); }
function wp_unslash( $s ) { return $s; }
function mk_term( $id, $slug ) { $t = new stdClass(); $t->term_id = $id; $t->slug = $slug; $t->name = ucfirst( $slug ); $GLOBALS['K']['terms'][ $id ] = $t; return $t; }

require __DIR__ . '/../inc/notes-tags-data.php';
require __DIR__ . '/../inc/notes-tags-group-meta.php';

// A: hooks and the registered meta.
foreach ( array( 'init', 'post_tag_add_form_fields', 'post_tag_edit_form_fields', 'created_post_tag', 'edited_post_tag' ) as $h ) { ok( ! empty( $GLOBALS['K']['actions'][ $h ] ), "A1 hooks $h" ); }
ok( ! empty( $GLOBALS['K']['filters']['manage_edit-post_tag_columns'] ) && ! empty( $GLOBALS['K']['filters']['manage_post_tag_custom_column'] ), 'A2 the list column filters' );
sn_tag_group_register_meta();
$reg = $GLOBALS['K']['registered'][ SN_TAG_GROUP_META ] ?? null;
ok( is_array( $reg ) && 'string' === $reg['type'] && true === $reg['single'] && true === $reg['show_in_rest'] && 'sn_tag_group_sanitize' === $reg['sanitize_callback'], 'A3 registered meta: string, single, in REST, sanitized' );

// B: sanitize.
ok( 'built' === sn_tag_group_sanitize( 'built' ) && '' === sn_tag_group_sanitize( 'nonsense' ) && '' === sn_tag_group_sanitize( array( 'built' ) ) && '' === sn_tag_group_sanitize( 'BUILT' ), 'B1 a known id passes; anything else is unfiled' );

// C: the select shows the effective group.
$fair = mk_term( 7, 'brand-new' ); $c2pa = mk_term( 8, 'c2pa' ); // brand-new is in no seed list
ok( str_contains( sn_tag_group_select( '' ), 'value="" selected="selected"' ) && substr_count( sn_tag_group_select( '' ), '<option' ) === 5, 'C1 five options, unfiled selected by default' );
ob_start(); sn_tag_group_edit_field( $c2pa ); $h = ob_get_clean();
ok( str_contains( $h, 'value="record" selected="selected"' ) && str_contains( $h, 'name="sn_tag_group"' ), 'C2 a seeded tag with no meta shows its seed group selected' );
ob_start(); sn_tag_group_edit_field( $fair ); $h = ob_get_clean();
ok( str_contains( $h, 'value="" selected="selected"' ), 'C3 an unfiled tag shows Not yet filed' );
ok( str_contains( $h, 'Why it’t built' ) === false && str_contains( $h, 'built' ), 'C4 the group title is decoded from its entity, not printed raw' );

// D: save.
$_POST['_wpnonce'] = 'ok';
$_POST[ SN_TAG_GROUP_META ] = 'built';
sn_tag_group_save( 7 );
ok( 'built' === ( $GLOBALS['K']['meta'][7][ SN_TAG_GROUP_META ] ?? null ), 'D1 the posted group is stored' );
ok( array( 'edit_term', 7 ) === $GLOBALS['K']['cap_asked'] && in_array( 'update-tag_7', $GLOBALS['K']['nonce_asked'], true ), 'D1b the handler asks edit_term on the tag and verifies the tag form nonce itself' );
// The review's case: a Contributor minting a tag through REST or tax_input with their $_POST in scope.
$GLOBALS['K']['can'] = false; $_POST[ SN_TAG_GROUP_META ] = 'record';
sn_tag_group_save( 7 );
ok( 'built' === ( $GLOBALS['K']['meta'][7][ SN_TAG_GROUP_META ] ?? null ), 'D1c without edit_term nothing is written' );
$GLOBALS['K']['can'] = true; $_POST['_wpnonce'] = 'forged';
sn_tag_group_save( 7 );
ok( 'built' === ( $GLOBALS['K']['meta'][7][ SN_TAG_GROUP_META ] ?? null ), 'D1d without the tag form nonce nothing is written' );
unset( $_POST['_wpnonce'] ); $_POST['_wpnonce_add-tag'] = 'ok';
sn_tag_group_save( 7 );
ok( 'record' === ( $GLOBALS['K']['meta'][7][ SN_TAG_GROUP_META ] ?? null ) && in_array( 'add-tag', $GLOBALS['K']['nonce_asked'], true ), 'D1e the add form nonce is accepted too' );
unset( $_POST['_wpnonce_add-tag'] ); $_POST['_wpnonce'] = 'ok';
$_POST[ SN_TAG_GROUP_META ] = 'nonsense';
sn_tag_group_save( 7 );
ok( ! isset( $GLOBALS['K']['meta'][7][ SN_TAG_GROUP_META ] ), 'D2 an unknown value deletes the meta (unfiled), never stores junk' );
$_POST[ SN_TAG_GROUP_META ] = 'record';
sn_tag_group_save( 7 );
unset( $_POST[ SN_TAG_GROUP_META ] );
sn_tag_group_save( 7 );
ok( 'record' === ( $GLOBALS['K']['meta'][7][ SN_TAG_GROUP_META ] ?? null ), 'D3 a save with no field posted leaves the meta alone' );
ok( 'record' === sn_notes_tag_group_effective( $fair ), 'D4 the page reads the filing' );

// E: the column.
$cols = sn_tag_group_column( array( 'name' => 'Name', 'description' => 'Description', 'slug' => 'Slug', 'posts' => 'Count' ) );
ok( array( 'name', 'description', 'sn_tag_group', 'slug', 'posts' ) === array_keys( $cols ), 'E1 Group sits after Description' );
ok( 'The record' === sn_tag_group_column_value( '', 'sn_tag_group', 7 ) && str_contains( sn_tag_group_column_value( '', 'sn_tag_group', 9 ), 'Not yet filed' ) === false, 'E2 the column names the group; an unknown term id leaves the cell as given' );
mk_term( 9, 'orphan' );
ok( str_contains( sn_tag_group_column_value( '', 'sn_tag_group', 9 ), 'Not yet filed' ) && 'x' === sn_tag_group_column_value( 'x', 'other', 9 ), 'E3 an unfiled tag reads Not yet filed; other columns pass through' );

echo "Result: $pass passed, $fail failed.\n";
exit( $fail ? 1 : 0 );
