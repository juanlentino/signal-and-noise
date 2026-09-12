<?php
/**
 * Tests: every signal-noise/post-field binding in the templates names a key the
 * source implements, leaves its <p> empty for the source to fill, and no
 * migrated shortcode survives. Parse-level: the sweep has no WordPress to run
 * do_blocks(); the source's own behaviour is pinned in tests/block-bindings.php.
 * @since theme v13.1.0
 */
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }
$pass = 0; $fail = 0;
function ok( $c, $m ) { global $pass, $fail; if ( $c ) { $pass++; echo "PASS: $m\n"; } else { $fail++; echo "FAIL: $m\n"; } }
$root = dirname( __DIR__ );

// Keys the source implements — derived from the source, never listed by hand.
$src  = (string) file_get_contents( $root . '/inc/block-bindings.php' );
preg_match_all( "/case '([a-z_]+)':/", $src, $km );
$keys = array_unique( $km[1] );
ok( in_array( 'reading_time', $keys, true ) && in_array( 'pillar', $keys, true ), 'the source implements reading_time and pillar (' . implode( ',', $keys ) . ')' );

$files = array_merge( glob( $root . '/templates/*.html' ), glob( $root . '/parts/*.html' ) );
$bound = 0; $bad = array();
foreach ( $files as $f ) {
	$h = (string) file_get_contents( $f );
	$rel = str_replace( $root . '/', '', $f );
	// JSON attrs are single-line in this codebase; match up to " -->" at end of
	// line rather than a lazy "}" which truncates on the JSON's own nested braces.
	if ( preg_match_all( '/<!-- wp:paragraph (\{[^\n]*"bindings"[^\n]*\}) -->\s*<p[^>]*>(.*?)<\/p>/s', $h, $m, PREG_SET_ORDER ) ) {
		foreach ( $m as $one ) {
			$bound++;
			$json = json_decode( $one[1], true );
			$b    = $json['metadata']['bindings']['content'] ?? null;
			if ( ! is_array( $b ) || 'signal-noise/post-field' !== ( $b['source'] ?? '' ) ) { $bad[] = "$rel: a bound paragraph does not use signal-noise/post-field"; continue; }
			if ( ! in_array( $b['args']['key'] ?? '', $keys, true ) ) { $bad[] = "$rel: key '" . ( $b['args']['key'] ?? '' ) . "' is not implemented by the source"; }
			if ( '' !== trim( $one[2] ) ) { $bad[] = "$rel: bound <p> is not empty (bindings fill it; static text would be shown when the source returns null)"; }
		}
	}
}
// Three slots: front-matter reading time, the home card, the notes card.
// NOT bound: the pillar-card eyebrows keep the plugin's [sn_reading_time slug]
// (prefix text + figure in one <p>; a content binding replaces the prefix), and
// the pillar is a PHP-only block (a bound <p> would wrap the <a> — not parity).
ok( 3 === $bound, "three bound paragraphs across templates/parts (found $bound): front-matter reading time, the home card, the notes card, and nothing else" );
ok( array() === $bad, 'every binding is well-formed' . ( $bad ? "\n  - " . implode( "\n  - ", $bad ) : '' ) );

$gone = array( '[sn_reading_time]' ); // the bare form only: the eyebrows' [sn_reading_time slug="…"] is the plugin's and stays.
$left = array();
foreach ( $files as $f ) { foreach ( $gone as $s ) { if ( false !== strpos( (string) file_get_contents( $f ), $s ) ) { $left[] = str_replace( $root . '/', '', $f ) . " still emits $s"; } } }
ok( array() === $left, 'no template or part emits a bare [sn_reading_time] any more' . ( $left ? "\n  - " . implode( "\n  - ", $left ) : '' ) );

// Negative control: a planted static inner is caught.
$probe = '<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"signal-noise/post-field","args":{"key":"reading_time"}}}}} --><p class="x">3 min read</p><!-- /wp:paragraph -->';
preg_match( '/<!-- wp:paragraph (\{.*?"bindings".*?\}) -->\s*<p[^>]*>(.*?)<\/p>/s', $probe, $pm );
ok( '' !== trim( $pm[2] ), 'control: the parser sees a non-empty inner when one is planted' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
