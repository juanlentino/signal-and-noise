<?php
/**
 * Tests: the reply-by-email subject survives entities and non-ASCII titles
 * (theme issue #328).
 *
 * inc/note-reply.php base64-encodes get_the_title() as-is. WordPress stores
 * a title's "&" as the entity "&#038;", so get_the_title() returns entities,
 * not the literal character — a title "Signal & Noise" produces the subject
 * "Re: Signal &#038; Noise" verbatim in the mail client. Separately,
 * inc/contact-email.php base64-encodes the raw UTF-8 subject bytes, and
 * assets/js/contact-aliases.js decodes with plain atob() + encodeURIComponent()
 * with no UTF-8 awareness — atob() yields a JS string of raw bytes-as-code-units,
 * and encodeURIComponent() on THAT mis-encodes any multibyte character
 * (mojibake) once it reaches ?subject=.
 *
 * Fix: wp_specialchars_decode() the title before it becomes the subject, and
 * carry the subject as base64(rawurlencode()) so the JS side can reverse it
 * exactly with decodeURIComponent(atob()) — a UTF-8-safe, byte-exact channel.
 *
 * Run: php tests/note-reply-subject-encoding.php
 * @since 12.19.0
 */
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', '/' ); }
define( 'SN_CONTACT_EMAIL_TEST', true );
define( 'SN_NOTE_REPLY_TEST', true );

$pass = 0; $fail = 0;
function ok( $c, $m ) { global $pass, $fail; if ( $c ) { $pass++; echo "PASS: $m\n"; } else { $fail++; echo "FAIL: $m\n"; } }

// ── WP stubs (environment only — both producers under test are the real files) ──
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_html__( $s, $d = null ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function do_shortcode( $s ) { return $s; }
function apply_filters( $tag, $value ) { return $value; }
function shortcode_unautop( $s ) { return $s; }
function shortcode_atts( $defaults, $atts, $tag = '' ) { return array_merge( $defaults, is_array( $atts ) ? $atts : array() ); }
// wp_specialchars_decode: real WP decodes &amp; &#038; &lt; &gt; &quot; &#039;
// back to their characters (ENT_QUOTES-equivalent default). A tiny stand-in
// is enough here — the fix under test is THAT it is called, not WP's own logic.
function wp_specialchars_decode( $text, $quote_style = ENT_QUOTES ) {
	return htmlspecialchars_decode( (string) $text, $quote_style );
}
$GLOBALS['__titles'] = array();
function get_the_title( $id ) { return $GLOBALS['__titles'][ (int) $id ] ?? ''; }
$GLOBALS['__queried_id'] = 0;
function get_queried_object_id() { return $GLOBALS['__queried_id']; }
$GLOBALS['__is_single_post'] = false;
function is_singular( $type = '' ) { return $GLOBALS['__is_single_post'] && 'post' === $type; }

require __DIR__ . '/../inc/contact-email.php';
require __DIR__ . '/../inc/note-reply.php';

function attr_of( $html, $attr ) {
	return preg_match( '/' . preg_quote( $attr, '/' ) . '="([^"]*)"/', $html, $m ) ? $m[1] : '';
}

echo "note-reply-subject-encoding — theme #328\n\nGroup 1: entities in the stored title decode before becoming the subject\n";

// This is what get_the_title() ACTUALLY returns for a post titled "Signal & Noise" —
// WordPress stores/renders the ampersand as an entity.
$GLOBALS['__titles'][20] = 'Signal &#038; Noise';
$row20  = sn_note_reply_markup( 20 );
$esj20  = attr_of( $row20, 'data-esj' );
$subject20 = rawurldecode( (string) base64_decode( $esj20 ) );
ok( 'Re: Signal & Noise' === $subject20,
	"the mail subject reads the literal '&', not the entity — got: \"$subject20\"" );

echo "\nGroup 2: non-ASCII survives base64(rawurlencode()) byte-exact\n";

$GLOBALS['__titles'][21] = 'Two kinds of provenance — a follow-up';
$row21     = sn_note_reply_markup( 21 );
$esj21     = attr_of( $row21, 'data-esj' );
$subject21 = rawurldecode( (string) base64_decode( $esj21 ) );
ok( 'Re: Two kinds of provenance — a follow-up' === $subject21,
	"the em dash round-trips byte-exact through base64(rawurlencode()) — got: \"$subject21\"" );
ok( false !== strpos( (string) base64_decode( $esj21 ), '%E2%80%94' ),
	'the base64 payload itself is percent-encoded UTF-8 (rawurlencode), not raw bytes' );

echo "\nGroup 3: contact-aliases.js reverses it with decodeURIComponent(atob()), UTF-8-safe\n";

$js = (string) file_get_contents( dirname( __DIR__ ) . '/assets/js/contact-aliases.js' );
ok( false !== strpos( $js, 'decodeURIComponent' ),
	'the JS decodes the subject with decodeURIComponent — plain atob() mis-encodes multibyte text once re-escaped' );

// Prove the ACTUAL algorithm the JS now uses is byte-exact for a multibyte
// subject, by running it under node against the very payload PHP just built.
$node = 'node -e ' . escapeshellarg(
	'const b64 = process.argv[1];'
	. 'let s = Buffer.from(b64, "base64").toString("binary");'
	. 'try { s = decodeURIComponent(s); } catch (e) { s = ""; }'
	. 'process.stdout.write(s);'
) . ' ' . escapeshellarg( $esj21 );
$node_out = shell_exec( $node );
ok( 'Re: Two kinds of provenance — a follow-up' === $node_out,
	"decodeURIComponent(atob()) in JS reproduces the exact subject — got: " . var_export( $node_out, true ) );

echo "\nGroup 4: user/domain parts are untouched by the new decode step\n";

ok( 'research' === base64_decode( attr_of( $row21, 'data-eu' ) ), 'data-eu is still a plain base64(user), no rawurlencode layer' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
