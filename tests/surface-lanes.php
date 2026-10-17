<?php
/**
 * Standalone test: the search marker lane and the résumé arrangement lanes
 * (2026-10-17). Both are pure helpers plus a little markup.
 *
 *   - sn_notes_term_positions(): where a search term occurs across a text, as
 *     fractions of its length, capped, so a result row can draw a tick at each.
 *   - sn_resume_lane_span(): a role's date line ("Sep 2018 - Present",
 *     "ene. 2016 - actualidad") as a start and end year with fractions.
 *
 * Run: php tests/surface-lanes.php
 */
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }
define( 'ABSPATH', '/' );
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function add_filter() {}
require dirname( __DIR__ ) . '/inc/search-marker-lane.php';
require dirname( __DIR__ ) . '/inc/resume-lanes.php';

$pass = 0; $fail = 0;
function ok( $c, $m ) { global $pass, $fail; if ( $c ) { ++$pass; echo "PASS: $m\n"; } else { ++$fail; echo "FAIL: $m\n"; } }

echo "Search marker lane\n";
$t = str_repeat( 'x', 10 ) . 'Metadata' . str_repeat( 'x', 80 ) . 'metadata' . str_repeat( 'y', 2 );
$pos = sn_notes_term_positions( $t, 'metadata' );
ok( 2 === count( $pos ) && abs( $pos[0] - 10 / 108 ) < 0.001 && abs( $pos[1] - 98 / 108 ) < 0.001, 'each occurrence, case-insensitive, as a fraction of the text\'s length' );
ok( 40 === count( sn_notes_term_positions( str_repeat( 'ab ', 200 ), 'ab' ) ), 'capped at 40 ticks' );
ok( array() === sn_notes_term_positions( 'nothing here', 'metadata' ) && array() === sn_notes_term_positions( 'abc', '' ), 'no match or no term, no ticks' );
ok( 2 === count( sn_notes_term_positions( 'Ñandú y ñandú', 'ÑANDÚ' ) ), 'multibyte text is searched by character, case-insensitively' );
$lane = sn_notes_row_lane_html( array( 0.1, 0.5 ) );
ok( false !== strpos( $lane, '<div class="sn-notes-row-lane" aria-hidden="true"><i style="--x:10.00%"></i><i style="--x:50.00%"></i></div>' ), 'a decorative hairline with a tick at each position' );
ok( false !== strpos( $lane, '<span class="sn-notes-row-lane-n">2 matches</span>' ) && false !== strpos( sn_notes_row_lane_html( array( 0.3 ) ), '1 match<' ), 'the count is real text, singular when one' );
ok( '' === sn_notes_row_lane_html( array() ), 'no positions, no lane' );
ok( false !== strpos( (string) file_get_contents( dirname( __DIR__ ) . '/inc/notes-index-row.php' ), 'sn_notes_row_lane_html(' ), 'search rows draw it' );

echo "\nRésumé arrangement lanes\n";
$s = sn_resume_lane_span( 'Sep 2018 - Present' );
ok( is_array( $s ) && abs( $s['start'] - ( 2018 + 8 / 12 ) ) < 0.001 && null === $s['end'], 'Sep 2018 - Present: starts September 2018, still open' );
$s = sn_resume_lane_span( 'Jan 2016 - Mar 2019' );
ok( is_array( $s ) && abs( $s['end'] - ( 2019 + 2 / 12 ) ) < 0.001, 'a closed role ends where it ended' );
$s = sn_resume_lane_span( 'ene. 2016 - actualidad' );
ok( is_array( $s ) && abs( $s['start'] - 2016 ) < 0.001 && null === $s['end'], 'Spanish: ene. 2016 - actualidad' );
ok( is_array( sn_resume_lane_span( 'sep. 2018 – presente' ) ), 'Spanish presente, en dash' );
ok( null === sn_resume_lane_span( 'Orlando, FL' ) && null === sn_resume_lane_span( 'Sometime - Later' ), 'anything else is skipped, never guessed' );
$html = sn_resume_lane_html( array( 'start' => 2018.5, 'end' => null ), 2016.0, 2026.0 );
ok( false !== strpos( $html, '<div class="sn-resume-lane" aria-hidden="true"><i class="is-open" style="--s:25.00%;--w:75.00%"></i></div>' ), 'an open role runs from its start to now on the shared axis' );
ok( false !== strpos( sn_resume_lane_html( array( 'start' => 2016.0, 'end' => 2021.0 ), 2016.0, 2026.0 ), '<i style="--s:0.00%;--w:50.00%">' ), 'a closed role spans only its own years' );
ok( '' === sn_resume_lane_html( array( 'start' => 2016.0, 'end' => null ), 2016.0, 2016.0 ), 'a zero-length axis draws nothing' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
