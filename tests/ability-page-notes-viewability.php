<?php
/**
 * get-page-notes-pillars only surfaces a pillar's last_modified date when the
 * resolved post is publicly viewable — defense in depth so a draft/private
 * pillar never leaks its mtime over the read-gated /wp-abilities run-path.
 *
 * Run: php tests/ability-page-notes-viewability.php
 * @since theme v10.16.2
 */
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }
define( 'ABSPATH', '/' );
if ( ! defined( 'OBJECT' ) ) { define( 'OBJECT', 'OBJECT' ); }
if ( ! function_exists( 'add_action' ) ) { function add_action() {} }

function sn_theme_pillar_descriptors() { return array( array( 'slug' => 'p1', 'title' => 'P1', 'dek' => 'd', 'last_path' => 'prov-p1', 'date' => '2026-01-01 00:00:00', 'designation' => '1.00' ) ); }
function sn_notes_reading_time_for_slug( $s ) { return '5 min'; }
function home_url( $p = '' ) { return $p; }
$GLOBALS['__viewable'] = true;
function is_post_publicly_viewable( $post ) { return ! empty( $GLOBALS['__viewable'] ); }
function get_page_by_path( $path, $out = OBJECT, $type = 'post' ) { $p = new stdClass(); $p->post_modified = '2026-01-15 12:00:00'; return $p; }

require_once __DIR__ . '/../inc/abilities-content.php';

$pass = 0; $fail = 0;
function ok( $c, $m ) { global $pass, $fail; if ( $c ) { ++$pass; echo "PASS: $m\n"; } else { ++$fail; echo "FAIL: $m\n"; } }

echo "get-page-notes-pillars last_modified viewability gate\n\n";

$GLOBALS['__viewable'] = true;
$out = sn_theme_ability_page_notes_pillars();
ok( isset( $out['pillars'][0]['last_modified'] ) && '2026-01-15' === $out['pillars'][0]['last_modified'],
	'publicly-viewable pillar → last_modified emitted (YYYY-MM-DD)' );

$GLOBALS['__viewable'] = false;
$out = sn_theme_ability_page_notes_pillars();
ok( isset( $out['pillars'][0]['last_modified'] ) && '' === $out['pillars'][0]['last_modified'],
	'non-viewable (draft/private) pillar → last_modified withheld (empty string)' );

// v10.47.0: the editorial designation rides the ability output verbatim.
ok( isset( $out['pillars'][0]['designation'] ) && '1.00' === $out['pillars'][0]['designation'],
	'designation passes through the ability output' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
