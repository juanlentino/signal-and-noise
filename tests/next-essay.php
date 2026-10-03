<?php
/**
 * Tests: the next essay (14.9.0). Each pillar names the next in pillar order;
 * the last, the hub and any non-pillar page render nothing; the link carries
 * its goal.
 *
 * Run: php tests/next-essay.php
 */
if ( PHP_SAPI !== 'cli' ) { exit; }
define( 'ABSPATH', '/' );
$pass = 0; $fail = 0;
function ok( $c, $m ) { global $pass, $fail; if ( $c ) { $pass++; echo "PASS: $m\n"; } else { $fail++; echo "FAIL: $m\n"; } }

function esc_attr__( $s ) { return $s; }
function esc_html__( $s ) { return $s; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_url( $u ) { return $u; }
function home_url( $p ) { return 'https://x' . $p; }
function get_queried_object() { return $GLOBALS['__q']; }
function get_page_uri( $p ) { return $p->uri; }
// Deliberately out of order: the sort, not the input, decides.
function sn_theme_pillar_descriptors() {
	return array(
		array( 'slug' => 'provenance/without-institutions', 'title' => 'Provenance Without Institutions', 'last_path' => 'without-institutions', 'designation' => '1.03', 'date' => '3' ),
		array( 'slug' => 'provenance/over-detection', 'title' => 'Provenance Over Detection', 'last_path' => 'over-detection', 'designation' => '1.01', 'date' => '1' ),
		array( 'slug' => 'provenance/as-substrate', 'title' => 'Provenance as Substrate', 'last_path' => 'as-substrate', 'designation' => '1.02', 'date' => '2' ),
	);
}
function sn_theme_pillar_designation_parts( $d ) { return preg_match( '/^(\d+)\.(\d+)$/', (string) $d, $m ) ? array( (int) $m[1], (int) $m[2] ) : null; }
eval( '?>' . preg_replace( '/^.*?(function sn_theme_pillar_sort.*?\n}\n).*$/s', '<?php $1', (string) file_get_contents( __DIR__ . '/../inc/abilities-helpers.php' ) ) );
require __DIR__ . '/../inc/next-essay.php';

$page = static fn( $name, $type = 'page', $parent = 'provenance' ) => (object) array( 'post_name' => $name, 'post_type' => $type, 'uri' => '' === $parent ? $name : $parent . '/' . $name );

$GLOBALS['__q'] = $page( 'over-detection' );
$h = sn_next_essay_html();
ok( false !== strpos( $h, 'href="https://x/provenance/as-substrate/"' ) && false !== strpos( $h, 'Provenance as Substrate' ), 'pillar 1 names pillar 2' );
ok( false !== strpos( $h, 'data-sn-goal="next_essay"' ), 'the link fires the next_essay goal' );
$GLOBALS['__q'] = $page( 'as-substrate' );
ok( false !== strpos( sn_next_essay_html(), 'Provenance Without Institutions' ), 'pillar 2 names pillar 3' );
$GLOBALS['__q'] = $page( 'without-institutions' );
ok( '' === sn_next_essay_html(), 'the last pillar renders nothing' );
$GLOBALS['__q'] = $page( 'provenance', 'page', '' );
ok( '' === sn_next_essay_html(), 'the hub renders nothing' );
$GLOBALS['__q'] = $page( 'over-detection', 'post' );
ok( '' === sn_next_essay_html(), 'a post that shares a pillar slug renders nothing' );
$GLOBALS['__q'] = $page( 'over-detection', 'page', 'drafts' );
ok( '' === sn_next_essay_html(), '15.0.1: a page elsewhere that shares a pillar\'s last segment renders nothing (full path, not post_name)' );
$GLOBALS['__q'] = null;
ok( '' === sn_next_essay_html(), 'no queried object renders nothing' );

$tpl = (string) file_get_contents( __DIR__ . '/../templates/page-provenance.html' );
ok( strpos( $tpl, '<!-- wp:signal-noise/next-essay /-->' ) > strpos( $tpl, '<!-- wp:post-content' ), 'the template places it after the content, outside what is signed' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
