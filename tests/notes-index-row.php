<?php
/**
 * Standalone fixture tests for inc/notes-index-row.php (v11.10.0) and the
 * index helpers it leans on.
 *
 * The redesign turns /notes from a feed into a MANIFEST, and three of its
 * decisions are the kind that quietly regress because nothing renders an
 * obvious error when they do:
 *
 *   1. The excerpt stays IN THE DOM and is hidden with CSS only. Owner
 *      decision: crawlers and AEO lose nothing. A future "optimisation" that
 *      stops emitting it would look identical in a browser.
 *   2. Rows carry EDITORIAL signals only — reading time, tags, provenance
 *      version. Never traffic or decay: publishing per-note performance would
 *      cut against the ML kernel's refusals.
 *   3. The year spine renders only when it discriminates. With a single-year
 *      corpus (true today: every Note is 2026) it must fall back to a flat
 *      list rather than draw one band restating the section header's count.
 *
 * Run: php tests/notes-index-row.php
 */
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }

define( 'ABSPATH', '/' );

$GLOBALS['__meta'] = array();
$GLOBALS['__tags'] = array();

function get_post_meta( $id, $key, $single = false ) { $v = $GLOBALS['__meta'][ (int) $id ][ $key ] ?? ''; return $single ? $v : ( '' === $v ? array() : array( $v ) ); }
function get_the_tags( $id ) { return $GLOBALS['__tags'][ (int) $id ] ?? false; }
function get_term_link( $t ) { return 'https://x.test/tag/' . strtolower( str_replace( ' ', '-', $t->name ) ) . '/'; }
function get_the_excerpt( $p ) { return $p->excerpt ?? ''; }
function get_permalink( $p ) { return 'https://x.test/notes/' . ( $p->slug ?? 'x' ) . '/'; }
function get_the_title( $p ) { return $p->title ?? ''; }
function get_the_date( $fmt, $p ) { return gmdate( $fmt, strtotime( $p->post_date ) ); }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $s ) { return esc_html( $s ); }
function esc_url( $s ) { return $s; }
function esc_html__( $s, $d = null ) { return esc_html( $s ); }
function esc_attr__( $s, $d = null ) { return esc_html( $s ); }
function wp_strip_all_tags( $s ) { return strip_tags( (string) $s ); }
function number_format_i18n( $n ) { return (string) $n; }
// NOTE: sn_notes_render_date(), sn_notes_render_reading_time() and
// sn_notes_result_type_label() are REAL helpers in notes-index-helpers.php and
// are deliberately NOT stubbed — a stub that shadows the code under test is how
// a fixture ends up asserting its own behaviour. They only need these:
function get_the_time( $fmt, $p ) { return gmdate( $fmt, strtotime( $p->post_date ) ); }
function wp_date( $fmt, $ts ) { return gmdate( $fmt, (int) $ts ); }
function get_post_type( $p ) { return 'post'; }

require_once __DIR__ . '/../inc/notes-index-helpers.php';
require_once __DIR__ . '/../inc/notes-index-row.php';

$pass = 0; $fail = 0;
function ok( $c, $l ) { global $pass, $fail; if ( $c ) { $pass++; echo "  ok   $l\n"; } else { $fail++; echo "  FAIL $l\n"; } }
function mk( $id, $title, $date, $excerpt = 'An argument in brief.', $slug = 'n' ) {
	return (object) array( 'ID' => $id, 'title' => $title, 'post_date' => $date, 'excerpt' => $excerpt, 'slug' => $slug );
}
function cap( callable $fn ) { ob_start(); $fn(); return ob_get_clean(); }

echo "\nGroup: provenance version (the only revision signal on a row)\n";
$GLOBALS['__meta'][5]['_sn_prov_chain'] = array( array( 'version' => 0 ), array( 'version' => 3 ), array( 'version' => 1 ) );
ok( 3 === sn_notes_prov_version( 5 ), 'highest version in the chain wins, not the last entry' );
ok( 0 === sn_notes_prov_version( 999 ), 'no chain → 0 (plugin absent or Note never committed)' );
$GLOBALS['__meta'][6]['_sn_prov_chain'] = 'not-an-array';
ok( 0 === sn_notes_prov_version( 6 ), 'a malformed chain → 0, never a fatal' );
ok( ! sn_notes_prov_version_is_notable( 1 ), 'v1 is NOT notable — true of nearly every Note, so it says nothing' );
ok( sn_notes_prov_version_is_notable( 2 ), 'v2 is notable — the argument was revisited AND signed' );

echo "\nGroup: the row's contract\n";
$GLOBALS['__meta'][10]['_sn_prov_chain'] = array( array( 'version' => 3 ) );
$GLOBALS['__meta'][10]['_sn_reading_time_minutes'] = 7;
$GLOBALS['__tags'][10] = array( (object) array( 'name' => 'Provenance' ), (object) array( 'name' => 'Music Rights' ), (object) array( 'name' => 'Third' ) );
$row = cap( function () { sn_notes_render_row( mk( 10, 'The master never moves', '2026-08-14 14:46:00', 'A master is delivered once.' ) ); } );

// THE DECISION MOST LIKELY TO REGRESS SILENTLY.
ok( false !== strpos( $row, 'A master is delivered once.' ), 'THE EXCERPT IS IN THE DOM — collapsed by CSS, never dropped from the markup' );
ok( false !== strpos( $row, 'sn-notes-row-excerpt-wrap' ), 'excerpt sits in the collapsible wrapper' );
ok( false !== strpos( $row, 'The master never moves' ), 'title present' );
ok( false !== strpos( $row, '07 MIN' ), 'reading time present, from the REAL helper (zero-padded)' );
ok( false !== strpos( $row, 'Provenance' ) && false !== strpos( $row, 'Music Rights' ), 'up to two tags present' );
ok( false === strpos( $row, 'Third' ), 'the third tag is dropped — the stamp is a signal, not a tag cloud' );
ok( false !== strpos( $row, '>v3<' ), 'provenance version renders at v3' );

// The refusal, asserted rather than assumed.
foreach ( array( 'views', 'pageviews', 'decay', 'spike', 'cooling', 'sustained', 'traffic', 'popular' ) as $forbidden ) {
	ok( false === stripos( $row, $forbidden ), "no traffic/decay signal on a row: '$forbidden' absent" );
}

$row_v1 = cap( function () { sn_notes_render_row( mk( 11, 'A note', '2026-08-01 00:00:00' ) ); } );
ok( false === strpos( $row_v1, 'sn-notes-row-prov' ), 'no provenance badge without a notable version' );

$row_pin = cap( function () { sn_notes_render_row( mk( 12, 'Start Here', '2026-04-01 00:00:00' ), array( 'pinned' => true ) ); } );
ok( false !== strpos( $row_pin, 'is-pinned' ) && false !== strpos( $row_pin, 'Start here' ), 'pinned row keeps its blood flag' );
ok( false !== strpos( $row_pin, 'sn-notes-row-title' ), 'pinned row uses the SAME row markup (it had drifted before)' );

echo "\nGroup: the year spine renders only when it discriminates\n";
$one_year = array( mk( 1, 'A', '2026-08-14 00:00:00' ), mk( 2, 'B', '2026-05-02 00:00:00' ) );
$out1 = cap( function () use ( $one_year ) { sn_notes_render_year_spine( $one_year ); } );
ok( false === strpos( $out1, '<details' ), 'single-year corpus → NO details drawer' );
ok( false === strpos( $out1, 'sn-notes-year-band' ), 'single-year corpus → NO year band (it would restate the section count)' );
ok( 1 === substr_count( $out1, '<ol class="sn-notes-index-list">' ), 'single-year corpus → one flat list' );

// THE DISTRIBUTION THAT WILL ACTUALLY HAPPEN. This corpus began 2026-04, so
// there is no 2025 and never will be — the spine's first real activation is
// January 2027: a new year holding a couple of Notes above a year holding
// thirty-plus. A "newest year open, everything else closed" rule shows the
// reader two rows and folds the whole argument behind a closed line, on the
// exact day it switches on. These fixtures are built that way round on purpose;
// backdating instead puts the mass in the open year and hides the bug.
$jan_2027 = array( mk( 1, 'New', '2027-01-08 00:00:00' ), mk( 2, 'Newer', '2027-01-03 00:00:00' ) );
for ( $i = 0; $i < 33; $i++ ) {
	$jan_2027[] = mk( 100 + $i, 'Note ' . $i, '2026-' . str_pad( (string) ( 1 + ( $i % 12 ) ), 2, '0', STR_PAD_LEFT ) . '-05 00:00:00' );
}
$out_2027 = cap( function () use ( $jan_2027 ) { sn_notes_render_year_spine( $jan_2027 ); } );
ok( false === strpos( $out_2027, '<details' ), 'Jan 2027: a two-Note year does NOT collapse the 33-Note year beneath it' );
ok( 2 === substr_count( $out_2027, '<section class="sn-notes-year">' ), 'Jan 2027: both years open — the page must carry substance' );
ok( false !== strpos( $out_2027, 'Note 0' ), 'Jan 2027: the 2026 corpus is still readable in sequence' );

// Once the newest year alone carries the page, surplus years DO collapse.
$fat_year = array();
for ( $i = 0; $i < 30; $i++ ) { $fat_year[] = mk( 200 + $i, 'Fat ' . $i, '2029-06-05 00:00:00' ); }
for ( $i = 0; $i < 20; $i++ ) { $fat_year[] = mk( 300 + $i, 'Old ' . $i, '2028-06-05 00:00:00' ); }
for ( $i = 0; $i < 15; $i++ ) { $fat_year[] = mk( 400 + $i, 'Older ' . $i, '2027-06-05 00:00:00' ); }
$out_fat = cap( function () use ( $fat_year ) { sn_notes_render_year_spine( $fat_year ); } );
ok( 1 === substr_count( $out_fat, '<section class="sn-notes-year">' ), 'a year that alone exceeds the threshold is the only one open' );
ok( 2 === substr_count( $out_fat, '<details' ), 'the two surplus years collapse' );
ok( strpos( $out_fat, '2029' ) < strpos( $out_fat, '2028' ), 'newest year first' );

// The threshold boundary: the year that CROSSES it is itself open, never a
// drawer — otherwise the crossing year is the one that gets hidden.
$boundary = array();
for ( $i = 0; $i < 20; $i++ ) { $boundary[] = mk( 500 + $i, 'A' . $i, '2030-06-05 00:00:00' ); }
for ( $i = 0; $i < 20; $i++ ) { $boundary[] = mk( 600 + $i, 'B' . $i, '2029-06-05 00:00:00' ); }
for ( $i = 0; $i < 20; $i++ ) { $boundary[] = mk( 700 + $i, 'C' . $i, '2028-06-05 00:00:00' ); }
$out_b = cap( function () use ( $boundary ) { sn_notes_render_year_spine( $boundary ); } );
ok( 2 === substr_count( $out_b, '<section class="sn-notes-year">' ), 'the year that crosses the threshold stays OPEN (20 < 24, so the second year opens too)' );
ok( 1 === substr_count( $out_b, '<details' ), 'only the genuinely surplus third year collapses' );

$two_years = array( mk( 1, 'A', '2027-08-14 00:00:00' ), mk( 2, 'B', '2026-11-02 00:00:00' ), mk( 3, 'C', '2026-01-02 00:00:00' ) );
$out2 = cap( function () use ( $two_years ) { sn_notes_render_year_spine( $two_years ); } );
ok( false !== strpos( $out2, 'sn-notes-year-band' ), 'multi-year corpus → the spine appears' );
ok( strpos( $out2, '2027' ) < strpos( $out2, '2026' ), 'newest year first' );

echo "\nGroup: grouping is pure\n";
$g = sn_notes_group_by_year( $two_years );
// PHP coerces numeric-string array keys to INTEGERS, so the keys come back as
// ints even though sn_notes_group_by_year() builds them from a 'Y' string. The
// renderer casts with (string) before esc_html(), which is why the band is
// correct either way — asserted here so the coercion is documented rather than
// rediscovered.
ok( array( 2027, 2026 ) === array_keys( $g ), 'groups are year-keyed (ints, per PHP key coercion), newest first' );
ok( 2 === count( $g[2026] ), 'posts land in the right year' );
ok( ! sn_notes_year_spine_is_useful( sn_notes_group_by_year( $one_year ) ), 'one year → spine not useful' );
ok( sn_notes_year_spine_is_useful( $g ), 'two years → spine useful' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
