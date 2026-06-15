<?php
/**
 * Standalone fixture tests for the availability line (D5, theme v10.9.0).
 *
 * inc/availability.php exposes the [sn_availability] shortcode that surfaces the
 * owner-edited availability string (companion plugin's sn_settings option,
 * subtree identity.availability) in the /contact + /services heroes. Standalone-
 * safe: plugin/option absent or the string empty → the shortcode renders ''
 * (no empty box), mirroring inc/identity-rels.php's read-and-degrade pattern.
 *
 * @since theme v10.9.0
 */

if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', '/' ); }
define( 'SN_AVAILABILITY_TEST', true ); // suppress add_shortcode wiring on require

$pass = 0; $fail = 0;
function ok( $cond, $msg ) { global $pass, $fail; if ( $cond ) { $pass++; echo "PASS: $msg\n"; } else { $fail++; echo "FAIL: $msg\n"; } }

// --- Controllable WP stubs ---
$GLOBALS['__options'] = array();
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['__options'] ) ? $GLOBALS['__options'][ $k ] : $d; }
function add_shortcode() { return true; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }

require __DIR__ . '/../inc/availability.php';

ok( function_exists( 'sn_availability_text' ), 'sn_availability_text() is defined' );
ok( function_exists( 'sn_availability_shortcode' ), 'sn_availability_shortcode() is defined' );

// T1 — plugin/option ABSENT → empty text + empty shortcode (graceful degrade).
$GLOBALS['__options'] = array();
ok( sn_availability_text() === '', 'T1 degrade: empty text when sn_settings absent' );
ok( sn_availability_shortcode() === '', 'T1 degrade: shortcode renders nothing when absent' );

// T2 — populated option → text returned + rendered.
$GLOBALS['__options'] = array( 'sn_settings' => array( 'identity' => array( 'availability' => 'Available for select mixing work' ) ) );
ok( sn_availability_text() === 'Available for select mixing work', 'T2: returns the stored availability string' );
$out = sn_availability_shortcode();
ok( strpos( $out, 'class="sn-availability"' ) !== false, 'T2: shortcode emits the .sn-availability element' );
ok( strpos( $out, 'Available for select mixing work' ) !== false, 'T2: shortcode includes the text' );

// T3 — empty string stored → renders nothing (no empty box).
$GLOBALS['__options'] = array( 'sn_settings' => array( 'identity' => array( 'availability' => '' ) ) );
ok( sn_availability_shortcode() === '', 'T3: empty stored string → shortcode renders nothing' );

// T4 — whitespace-only stored → trimmed to empty → renders nothing.
$GLOBALS['__options'] = array( 'sn_settings' => array( 'identity' => array( 'availability' => "   \n  " ) ) );
ok( sn_availability_shortcode() === '', 'T4: whitespace-only → renders nothing' );

// T5 — escaping: a hostile stored value is esc_html()'d at output.
$GLOBALS['__options'] = array( 'sn_settings' => array( 'identity' => array( 'availability' => '<script>alert(1)</script>' ) ) );
$out = sn_availability_shortcode();
ok( strpos( $out, '<script>' ) === false, 'T5 escaping: raw <script> never rendered' );
ok( strpos( $out, '&lt;script&gt;' ) !== false, 'T5 escaping: value is HTML-escaped' );

// T6 — malformed subtree (not a string) → degrade, no fatal.
$GLOBALS['__options'] = array( 'sn_settings' => array( 'identity' => array( 'availability' => array( 'nope' ) ) ) );
ok( sn_availability_text() === '' && sn_availability_shortcode() === '', 'T6 robustness: non-string availability degrades to nothing' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
