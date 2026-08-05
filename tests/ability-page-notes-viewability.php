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

$GLOBALS['__last_path'] = 'prov-p1';
function sn_theme_pillar_descriptors() { return array( array( 'slug' => 'p1', 'title' => 'P1', 'dek' => 'd', 'last_path' => $GLOBALS['__last_path'], 'date' => '2026-01-01 00:00:00', 'designation' => '1.00' ) ); }
function sn_notes_reading_time_for_slug( $s ) { return '5 min'; }
function home_url( $p = '' ) { return $p; }
$GLOBALS['__viewable'] = true;
function is_post_publicly_viewable( $post ) { return ! empty( $GLOBALS['__viewable'] ); }

// Pillars are PAGES (sn_theme_pillar_descriptors queries post_type => 'page').
// The fixture carries one page and one same-named post so a wrong-type lookup
// is observably different from a missing one.
$GLOBALS['__test_posts'] = array(
	array( 'post_name' => 'prov-p1', 'post_type' => 'page', 'post_modified' => '2026-01-15 12:00:00' ),
	array( 'post_name' => 'a-real-post', 'post_type' => 'post', 'post_modified' => '2026-02-02 09:00:00' ),
);
// Models core's REAL post_type filter: WP matches post_type IN ($post_type,
// 'attachment'), so a 'post' lookup can never return a page. The pre-v11.4.5
// stub ignored BOTH $path and $type and returned a modified post object
// unconditionally — which is why 3 assertions stayed green while the live
// ability returned last_modified:"" for every pillar. Same false-green class
// the v11.2.2 reading-time fix removed from the two other ability suites.
function get_page_by_path( $path, $out = OBJECT, $type = 'page' ) {
	foreach ( $GLOBALS['__test_posts'] as $p ) {
		if ( $p['post_name'] === $path
			&& in_array( $p['post_type'], array( $type, 'attachment' ), true ) ) {
			return (object) $p;
		}
	}
	return null;
}

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

// v11.4.5 regression: the resolution must ask for a PAGE. Before the fix the
// ability asked for 'post', so this next assertion is what was silently
// failing on production for every pillar.
$GLOBALS['__viewable']  = true;
$GLOBALS['__last_path'] = 'prov-p1';
$out = sn_theme_ability_page_notes_pillars();
ok( '2026-01-15' === $out['pillars'][0]['last_modified'],
	'pillar last_path resolves as a PAGE → live mtime emitted (regression: was "" on production)' );

// A path that exists only as a POST must NOT resolve — the descriptor set is
// page-only, so a same-named post is not the pillar and must not lend its mtime.
$GLOBALS['__last_path'] = 'a-real-post';
$out = sn_theme_ability_page_notes_pillars();
ok( '' === $out['pillars'][0]['last_modified'],
	'a same-named POST does not satisfy a pillar lookup → last_modified withheld' );

// Unknown path → the same uniform empty string as draft/private (existence
// oracle stays closed: a missing pillar is indistinguishable from a hidden one).
$GLOBALS['__last_path'] = 'no-such-path';
$out = sn_theme_ability_page_notes_pillars();
ok( '' === $out['pillars'][0]['last_modified'],
	'unknown last_path → last_modified withheld (uniform with the non-viewable case)' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
