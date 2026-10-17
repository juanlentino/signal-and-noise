<?php
/**
 * Standalone test: the pillar essays carry a signal path under their brow
 * (inc/essay-signal-path.php, 2026-10-17): the series in reading order, the
 * current essay marked, the others linked. Same order as Next essay.
 *
 * Run: php tests/essay-signal-path.php
 */
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }
define( 'ABSPATH', '/' );
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_url( $u ) { return (string) $u; }
function esc_attr__( $s, $d = null ) { return esc_attr( $s ); }
function home_url( $p = '' ) { return 'https://x.test' . $p; }
function get_post( $id ) { return $GLOBALS['__posts'][ (int) $id ] ?? null; }
function get_page_uri( $p ) { return $p->uri ?? ''; }
$GLOBALS['__pillars'] = array(
	array( 'slug' => 'provenance/as-substrate', 'title' => 'Provenance As Substrate', 'designation' => '2.00' ),
	array( 'slug' => 'provenance/over-detection', 'title' => 'Provenance <Over> Detection', 'designation' => '1.00' ),
	array( 'slug' => 'provenance/without-institutions', 'title' => 'Provenance Without Institutions', 'designation' => '3.00' ),
);
function sn_theme_pillar_descriptors() { return $GLOBALS['__pillars']; }
function sn_theme_pillar_sort( $d ) { usort( $d, static fn( $a, $b ) => strcmp( $a['designation'], $b['designation'] ) ); return $d; }
$GLOBALS['__posts'] = array( 7 => (object) array( 'ID' => 7, 'uri' => 'provenance/as-substrate' ), 8 => (object) array( 'ID' => 8, 'uri' => 'about' ) );
require dirname( __DIR__ ) . '/inc/essay-signal-path.php';

$pass = 0; $fail = 0;
function ok( $c, $m ) { global $pass, $fail; if ( $c ) { ++$pass; echo "PASS: $m\n"; } else { ++$fail; echo "FAIL: $m\n"; } }

$h = sn_essay_signal_path_html( 7 );
ok( 0 === strpos( $h, '<nav class="sn-signal-path" aria-label="Essay series"><ol>' ), 'a labelled nav with an ordered list' );
ok( 3 === substr_count( $h, '<li' ), 'one stop per pillar' );
ok( strpos( $h, '1.00' ) < strpos( $h, '2.00' ) && strpos( $h, '2.00' ) < strpos( $h, '3.00' ), 'in reading order, the same order as Next essay' );
ok( 1 === substr_count( $h, 'aria-current="page"' ) && 1 === preg_match( '#<li aria-current="page"><span class="sn-signal-path__no">2\.00</span>#', $h ), 'the current essay is marked, and is not a link to itself' );
ok( 2 === substr_count( $h, '<a ' ) && false !== strpos( $h, 'href="https://x.test/provenance/over-detection/"' ), 'the others link' );
ok( false !== strpos( $h, 'Provenance &lt;Over&gt; Detection' ), 'titles are escaped' );
ok( '' === sn_essay_signal_path_html( 8 ), 'a page that is not in the series gets no path' );
$GLOBALS['__pillars'] = array( $GLOBALS['__pillars'][0] );
ok( '' === sn_essay_signal_path_html( 7 ), 'a series of one is not a path' );
$GLOBALS['__pillars'] = array( array( 'slug' => 'provenance/as-substrate', 'title' => 'A', 'designation' => '' ), array( 'slug' => 'b', 'title' => 'B', 'designation' => '' ) );
ok( '' === sn_essay_signal_path_html( 7 ), 'undesignated pillars draw no numbered path' );
ok( false !== strpos( (string) file_get_contents( dirname( __DIR__ ) . '/inc/pillar-title-eyebrow.php' ), "sn_essay_signal_path_html( \$page_id )" ), 'the eyebrow filter emits it right after the brow, outside the signed content' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
