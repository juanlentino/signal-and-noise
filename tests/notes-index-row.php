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
function date_i18n( $f, $ts ) { return gmdate( $f, (int) $ts ); }
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
ok( 0 === substr_count( $out_2027, '<details class="sn-notes-year"' ), 'Jan 2027: a two-Note year does NOT collapse the 33-Note YEAR beneath it (month drawers inside it are fine)' );
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

echo "\nGroup: months bound growth INSIDE a year\n";
// The year is the wrong bounding unit on its own. At the observed cadence
// (22 Notes scheduled across ten weeks, ~114/yr) a single open year reaches
// ~4,900px by December — the exact wall this redesign removed. The year spine
// can only collapse whole years, so it does nothing until January.
$nov_2026 = array();
$m_counts = array( '10' => 9, '09' => 10, '08' => 9, '07' => 9, '06' => 5, '05' => 11, '04' => 2 );
$n = 0;
foreach ( $m_counts as $mm => $c ) {
	for ( $i = 0; $i < $c; $i++ ) {
		$nov_2026[] = mk( 1000 + $n, 'N' . $n, '2026-' . $mm . '-' . str_pad( (string) ( 28 - $i ), 2, '0', STR_PAD_LEFT ) . ' 10:00:00' );
		$n++;
	}
}
ok( 55 === count( $nov_2026 ), 'fixture models the KNOWN pipeline: 33 published + 22 scheduled = 55 by 1 Nov 2026' );
$out_nov = cap( function () use ( $nov_2026 ) { sn_notes_render_year_spine( $nov_2026 ); } );
ok( false === strpos( $out_nov, 'sn-notes-year-band' ), '55 Notes, still ONE year → no year spine' );
ok( 4 === substr_count( $out_nov, '<details class="sn-notes-month"' ), 'surplus months collapse (Oct+Sep+Aug = 28 >= 24, so Jul/Jun/May/Apr fold)' );
ok( 3 === substr_count( $out_nov, '<p class="sn-notes-month-band">' ) - 4, 'three months stay open' );
ok( false !== strpos( $out_nov, 'N0' ), 'the newest month is open' );
// Collapsed months keep their rows in the DOM — same contract as the excerpts.
ok( 55 === substr_count( $out_nov, 'sn-notes-row-title' ), 'ALL 55 rows remain in the DOM; collapsing is a CSS/details affordance, never a query limit' );

// The month that CROSSES the budget stays open, or the crossing month is the
// one that gets hidden — the same boundary bug the year rule had.
$boundary_m = array();
for ( $i = 0; $i < 10; $i++ ) { $boundary_m[] = mk( 2000 + $i, 'P' . $i, '2026-10-' . str_pad( (string) ( 28 - $i ), 2, '0', STR_PAD_LEFT ) . ' 10:00:00' ); }
for ( $i = 0; $i < 10; $i++ ) { $boundary_m[] = mk( 2100 + $i, 'Q' . $i, '2026-09-' . str_pad( (string) ( 28 - $i ), 2, '0', STR_PAD_LEFT ) . ' 10:00:00' ); }
for ( $i = 0; $i < 10; $i++ ) { $boundary_m[] = mk( 2200 + $i, 'R' . $i, '2026-08-' . str_pad( (string) ( 28 - $i ), 2, '0', STR_PAD_LEFT ) . ' 10:00:00' ); }
$out_bm = cap( function () use ( $boundary_m ) { sn_notes_render_year_spine( $boundary_m ); } );
ok( 0 === substr_count( $out_bm, '<details class="sn-notes-month"' ), '10+10+10: each month is tested BEFORE its own rows count, so 0<24, 10<24, 20<24 — all three open' );
// Add a fourth month and the budget is finally spent (30 >= 24), so it folds.
for ( $i = 0; $i < 10; $i++ ) { $boundary_m[] = mk( 2300 + $i, 'T' . $i, '2026-07-' . str_pad( (string) ( 28 - $i ), 2, '0', STR_PAD_LEFT ) . ' 10:00:00' ); }
$out_bm4 = cap( function () use ( $boundary_m ) { sn_notes_render_year_spine( $boundary_m ); } );
ok( 1 === substr_count( $out_bm4, '<details class="sn-notes-month"' ), 'the fourth month folds — the budget (24) is spent by then' );

// Below the divider threshold a year is one clean run — no bands at all.
$short = array();
for ( $i = 0; $i < 8; $i++ ) { $short[] = mk( 3000 + $i, 'S' . $i, '2026-0' . ( 1 + ( $i % 3 ) ) . '-05 00:00:00' ); }
$out_s = cap( function () use ( $short ) { sn_notes_render_year_spine( $short ); } );
ok( false === strpos( $out_s, 'sn-notes-month-band' ), 'a short year gets NO month dividers — a divider on four rows is texture, not structure' );

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

// ── THE BUG CLASS IS NOW UNREPRESENTABLE (v11.13.0) ─────────────────────────
// HISTORY. v11.12.2 and v11.12.3 were two releases spent on one bug: the row's
// `grid-template-areas` lived inside `@media (min-width: 720px)` while its four
// children carried `grid-area: spec|title|meta|excerpt` UNCONDITIONALLY. Below
// 720px the names resolved to nothing and all four children stacked into one
// cell. v11.12.2's fix (`grid-area: auto` under max-width: 719px) was written
// above the declarations it had to beat, so at equal specificity it lost on
// source order and shipped as a tagged no-op.
//
// The v11.12.3 repair was a test asserting the OVERRIDE'S POSITION. That guards
// the workaround; it does not remove the thing being worked around. A rule whose
// correctness depends on which line it sits on is a rule that will be moved.
//
// v11.13.0 removes the split instead. The 720px switch is an `@container` query
// on the list, and every `grid-area` name now lives INSIDE the same block that
// declares `grid-template-areas`. A name can no longer outlive the template that
// defines it, because they are the same block — so there is no override to
// position, and no source order to get wrong.
echo "\nGroup: the row template and its area names are one block\n";
// v12.4.1: the /notes CSS moved out of the renderer into its own route-scoped
// stylesheet. Same rules, new home.
$css_src = (string) file_get_contents( __DIR__ . '/../assets/css/notes.css' );

ok( strpos( $css_src, 'container-type: inline-size' ) !== false,
	'.sn-notes-index-list establishes a size container' );
ok( preg_match( '/\.sn-notes-index-list\s*\{[^}]*container-type:\s*inline-size/s', $css_src ) === 1,
	'the container is the LIST — the row cannot be its own query subject' );
ok( strpos( $css_src, 'container-name: sn-notes-list' ) !== false,
	'the container is named, so the query cannot bind to some future ancestor container' );

// The switch is driven by the LIST's width, not the viewport's.
ok( strpos( $css_src, '@container sn-notes-list (min-width: 720px)' ) !== false,
	'the 720px switch is an @container query' );
ok( preg_match( '/@media\s*\(\s*min-width:\s*720px\s*\)\s*\{\s*\.sn-notes-row\s*\{/', $css_src ) !== 1,
	'the old viewport @media switch for .sn-notes-row is GONE' );

// Brace-match the @container block and prove containment.
$at = strpos( $css_src, '@container sn-notes-list (min-width: 720px)' );
$open = strpos( $css_src, '{', $at );
$depth = 0; $close = $open;
for ( $i = $open; $i < strlen( $css_src ); $i++ ) {
	if ( '{' === $css_src[ $i ] ) { $depth++; }
	if ( '}' === $css_src[ $i ] ) { $depth--; if ( 0 === $depth ) { $close = $i; break; } }
}
$block = substr( $css_src, $open, $close - $open );
ok( $close > $open, 'the @container block brace-matches' );

ok( strpos( $block, 'grid-template-areas:' ) !== false,
	'grid-template-areas is declared inside the @container block' );
foreach ( array( 'grid-area: spec;', 'grid-area: title;', 'grid-area: meta;', 'grid-area: excerpt;' ) as $decl ) {
	ok( strpos( $block, $decl ) !== false, "INSIDE the block that defines the areas: $decl" );
	ok( substr_count( $css_src, $decl ) === 1,
		"declared exactly once, so no copy of it survives outside the block: $decl" );
}

// The workaround is gone, not merely repositioned. Comments are stripped first:
// the history of WHY it is gone is written in a comment right there in the file,
// and a substring search that cannot tell prose from a declaration would read
// that explanation as the thing it is warning about.
$decls_only = (string) preg_replace( '#/\*.*?\*/#s', '', $css_src );
ok( strpos( $decls_only, 'grid-area: auto' ) === false,
	'THE `grid-area: auto` WORKAROUND IS GONE — there is no longer a broken state for it to undo' );
ok( preg_match( '/@media\s*\(\s*max-width:\s*719px\s*\)/', $css_src ) !== 1,
	'no max-width: 719px counter-rules remain for the row' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
