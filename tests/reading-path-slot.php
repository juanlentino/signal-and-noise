<?php
/**
 * Tests for inc/reading-path-slot.php (v11.9.0): the [sn_reading_path] block
 * bridge, and the plugin-absent guard that earns it.
 *
 * The renderer itself lives in the PLUGIN (signal-and-noise-tools v11.3.0)
 * and is tested there; this suite pins the theme's half: the token resolves
 * inside core/shortcode block output, and with the plugin absent the slot is
 * EMPTY — never the literal token as prose, which is what a bare
 * do_shortcode() pass-through would print.
 *
 * Run: php tests/reading-path-slot.php
 */
if ( PHP_SAPI !== 'cli' ) { http_response_code( 404 ); exit; }

define( 'SN_READING_PATH_TEST', true );

error_reporting( E_ALL );
$GLOBALS['__php_errors'] = array();
set_error_handler( function ( $no, $str, $file, $line ) {
	$GLOBALS['__php_errors'][] = "$str @ $file:$line";
	return true;
} );

// Minimal registered-shortcode surface: shortcode_exists reads the real
// registry global, so registration is modelled the way WP models it.
$GLOBALS['shortcode_tags'] = array();
function shortcode_exists( $tag ) { return array_key_exists( $tag, $GLOBALS['shortcode_tags'] ); }
function do_shortcode( $content ) {
	foreach ( $GLOBALS['shortcode_tags'] as $tag => $cb ) {
		$content = str_replace( '[' . $tag . ']', (string) call_user_func( $cb ), $content );
	}
	return $content;
}
// shortcode_unautop stand-in: strips a <p> wrapper around a lone token, the
// one behaviour the bridge depends on (real fn verified in related-notes).
function shortcode_unautop( $text ) {
	return preg_replace( '#<p>\s*(\[sn_reading_path\])\s*</p>#', '$1', $text );
}

require __DIR__ . '/../inc/reading-path-slot.php';

$pass = 0; $fail = 0;
function ok( $cond, $label ) { global $pass, $fail; if ( $cond ) { ++$pass; echo "PASS: $label\n"; } else { ++$fail; echo "FAIL: $label\n"; } }

echo "Reading-path slot bridge (v11.9.0)\n\n";

$wrapped = "<p>[sn_reading_path]</p>";

// Plugin absent: the guard case that earns the bridge.
ok( '' === sn_reading_path_render_block_bridge( $wrapped, array() ),
	'PLUGIN ABSENT: the slot renders EMPTY — a reader never sees the literal token as prose' );

// Plugin present: the token resolves through unautop + do_shortcode.
$GLOBALS['shortcode_tags']['sn_reading_path'] = function () { return '<nav class="sn-reading-path">chain</nav>'; };
$out = sn_reading_path_render_block_bridge( $wrapped, array() );
ok( false !== strpos( $out, 'sn-reading-path' ) && false === strpos( $out, '[sn_reading_path]' ),
	'plugin present: the token resolves to the nav, unautop stripping the <p> first' );

// A self-gated empty render stays empty, not a stray wrapper.
$GLOBALS['shortcode_tags']['sn_reading_path'] = function () { return ''; };
$out2 = sn_reading_path_render_block_bridge( $wrapped, array() );
ok( false === strpos( $out2, '[sn_reading_path]' ), 'a self-gated (empty) render leaves no token behind' );

// Unrelated blocks pass through untouched.
$other = '<p>prose mentioning nothing relevant</p>';
ok( $other === sn_reading_path_render_block_bridge( $other, array() ), 'blocks without the token pass through byte-identical' );
$related = '<p>[sn_related_notes]</p>';
ok( $related === sn_reading_path_render_block_bridge( $related, array() ), "the SIBLING token is not this bridge's business — prefix gates stay disjoint" );

echo "\nGroup: no PHP notices\n";
ok( array() === $GLOBALS['__php_errors'], 'zero notices/warnings: ' . implode( ' | ', $GLOBALS['__php_errors'] ) );

echo "\n$pass passed, $fail failed\n";
exit( $fail > 0 ? 1 : 0 );
