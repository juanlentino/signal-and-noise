<?php
/**
 * Tests: every signal-noise/post-field and signal-noise/site-field binding in
 * the templates names a key its source implements, shapes its <p> the way the
 * source expects (post-field empty, for the source to fill; site-field carrying
 * the fallback line the canvas shows behind the binding lock), and no migrated
 * shortcode survives. Parse-level: the sweep has no WordPress to run
 * do_blocks(); each source's own behaviour is pinned in tests/block-bindings.php.
 * @since theme v13.1.0
 */
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }
$pass = 0; $fail = 0;
function ok( $c, $m ) { global $pass, $fail; if ( $c ) { $pass++; echo "PASS: $m\n"; } else { $fail++; echo "FAIL: $m\n"; } }
$root = dirname( __DIR__ );

// Keys each source implements, derived from its callback's body, never listed by hand.
$src  = (string) file_get_contents( $root . '/inc/block-bindings.php' );
$callbacks = array( 'signal-noise/post-field' => 'sn_post_field_binding_value', 'signal-noise/site-field' => 'sn_site_field_binding_value' );
$keys = array();
foreach ( $callbacks as $source => $fn ) {
	$at   = strpos( $src, "function $fn(" );
	$body = false === $at ? '' : substr( $src, $at );
	$end  = strpos( $body, "\n}\n" );
	$body = false === $end ? $body : substr( $body, 0, $end );
	preg_match_all( "/case '([a-z_]+)':/", $body, $km );
	$keys[ $source ] = array_unique( $km[1] );
}
ok( in_array( 'reading_time', $keys['signal-noise/post-field'], true ) && in_array( 'pillar', $keys['signal-noise/post-field'], true ), 'post-field implements reading_time and pillar (' . implode( ',', $keys['signal-noise/post-field'] ) . ')' );
ok( array( 'copyright' ) === array_values( $keys['signal-noise/site-field'] ), 'site-field implements copyright and nothing else (#389)' );

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
			$source = is_array( $b ) ? (string) ( $b['source'] ?? '' ) : '';
			if ( ! isset( $keys[ $source ] ) ) { $bad[] = "$rel: a bound paragraph does not use a signal-noise source ('$source')"; continue; }
			if ( ! in_array( $b['args']['key'] ?? '', $keys[ $source ], true ) ) { $bad[] = "$rel: key '" . ( $b['args']['key'] ?? '' ) . "' is not implemented by $source"; }
			// post-field is post-dependent and may return null (pillar): its <p> stays
			// empty so no static text shows when the source has nothing. site-field
			// never returns null for its keys, so its <p> carries the fallback line
			// the Site Editor canvas shows behind the binding lock (#389).
			if ( 'signal-noise/post-field' === $source && '' !== trim( $one[2] ) ) { $bad[] = "$rel: bound <p> is not empty (bindings fill it; static text would be shown when the source returns null)"; }
			if ( 'signal-noise/site-field' === $source && '' === trim( $one[2] ) ) { $bad[] = "$rel: a site-field <p> carries no fallback line (the canvas would show an empty paragraph)"; }
		}
	}
}
// Four slots: front-matter reading time, the home card, the notes card (post-field)
// and the footer's copyright line (site-field, #389).
// NOT bound: the pillar-card eyebrows keep the plugin's [sn_reading_time slug]
// (prefix text + figure in one <p>; a content binding replaces the prefix), and
// the pillar is a PHP-only block (a bound <p> would wrap the <a> — not parity).
ok( 4 === $bound, "four bound paragraphs across templates/parts (found $bound): front-matter reading time, the home card, the notes card, the footer line, and nothing else" );
ok( array() === $bad, 'every binding is well-formed' . ( $bad ? "\n  - " . implode( "\n  - ", $bad ) : '' ) );

$gone = array( '[sn_reading_time]', '[current_year]' ); // the bare reading-time form only: the eyebrows' [sn_reading_time slug="…"] is the plugin's and stays. [current_year] is the footer binding since #389.
$left = array();
foreach ( $files as $f ) { foreach ( $gone as $s ) { if ( false !== strpos( (string) file_get_contents( $f ), $s ) ) { $left[] = str_replace( $root . '/', '', $f ) . " still emits $s"; } } }
ok( array() === $left, 'no template or part emits a bare [sn_reading_time] or [current_year] any more' . ( $left ? "\n  - " . implode( "\n  - ", $left ) : '' ) );

// Negative control: a planted static inner is caught.
$probe = '<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"signal-noise/post-field","args":{"key":"reading_time"}}}}} --><p class="x">3 min read</p><!-- /wp:paragraph -->';
preg_match( '/<!-- wp:paragraph (\{.*?"bindings".*?\}) -->\s*<p[^>]*>(.*?)<\/p>/s', $probe, $pm );
ok( '' !== trim( $pm[2] ), 'control: the parser sees a non-empty inner when one is planted' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
