<?php
/**
 * Standalone fixture tests for [sn_note_reply] (v12.9.0).
 *
 * inc/note-reply.php: the reply-by-correspondence row in the single-note
 * closing footer, built on the REAL sn_email_markup() from
 * inc/contact-email.php (both files loaded; no stub of either producer, per
 * the stub-drift rule). Asserts the scraper posture holds on notes exactly as
 * on /contact: no contiguous address, no "@", no "mailto:" in served markup —
 * and that the subject rides base64 in data-esj for contact-aliases.js.
 *
 * @since theme v12.9.0
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
function shortcode_unautop( $s ) { return $s; }
function shortcode_atts( $defaults, $atts, $tag = '' ) { return array_merge( $defaults, is_array( $atts ) ? $atts : array() ); }

$GLOBALS['__titles'] = array();
function get_the_title( $id ) { return $GLOBALS['__titles'][ (int) $id ] ?? ''; }
$GLOBALS['__queried_id'] = 0;
function get_queried_object_id() { return $GLOBALS['__queried_id']; }
$GLOBALS['__is_single_post'] = false;
function is_singular( $type = '' ) { return $GLOBALS['__is_single_post'] && 'post' === $type; }

require __DIR__ . '/../inc/contact-email.php';
require __DIR__ . '/../inc/note-reply.php';

/** Pull one data-* attribute value out of markup, or '' when absent. */
function attr_of( $html, $attr ) {
	return preg_match( '/' . preg_quote( $attr, '/' ) . '="([^"]*)"/', $html, $m ) ? $m[1] : '';
}

// ── 1. The row for a titled note ──
$GLOBALS['__titles'][7] = 'Two kinds of provenance';
$row = sn_note_reply_markup( 7 );

ok( '' !== $row, 'titled note renders a row' );
ok( false !== strpos( $row, 'class="sn-note-reply"' ), 'row carries the .sn-note-reply hook' );
ok( false !== strpos( $row, 'Reply</span>' ), 'label reads Reply' );
ok( 'Re: Two kinds of provenance' === base64_decode( attr_of( $row, 'data-esj' ) ), 'data-esj decodes to "Re: <title>"' );
ok( 'reply-note' === attr_of( $row, 'data-sn-goal' ), 'conversion goal is reply-note, not contact-research' );
ok( 'research' === base64_decode( attr_of( $row, 'data-eu' ) ), 'data-eu decodes to the research alias' );
ok( false !== strpos( $row, 'research [at] juanlentino [dot] com' ), 'no-JS fallback stays readable' );

// ── 2. The scraper posture holds on notes ──
ok( false === strpos( $row, 'research@juanlentino.com' ), 'no contiguous address in served markup' );
ok( false === strpos( $row, '@' ), 'no bare @ anywhere in served markup' );
ok( false === stripos( $row, 'mailto' ), 'no mailto: in served markup' );

// ── 3. Subject with markup-significant characters survives the b64 round trip ──
$GLOBALS['__titles'][8] = 'Signal & Noise — a "quote" <test>';
$row8 = sn_note_reply_markup( 8 );
ok( 'Re: Signal & Noise — a "quote" <test>' === base64_decode( attr_of( $row8, 'data-esj' ) ), 'hostile title round-trips intact through base64' );
ok( false === strpos( $row8, '<test>' ), 'raw title markup never lands unescaped in the HTML' );

// ── 4. Untitled note: row still renders, subject simply absent ──
$GLOBALS['__titles'][9] = '';
$row9 = sn_note_reply_markup( 9 );
ok( '' !== $row9, 'untitled note still gets a reply row' );
ok( false === strpos( $row9, 'data-esj' ), 'no data-esj when there is no title to Re:' );

// ── 5. Shortcode gating ──
$GLOBALS['__is_single_post'] = false;
$GLOBALS['__queried_id']     = 7;
ok( '' === sn_note_reply_shortcode(), 'shortcode is inert off single posts' );
$GLOBALS['__is_single_post'] = true;
ok( false !== strpos( sn_note_reply_shortcode(), 'sn-note-reply' ), 'shortcode renders on a single post' );
ok( '' === sn_note_reply_markup( 0 ), 'id 0 renders nothing rather than a broken row' );

// ── 6. Back-compat: the two-arg /contact call emits the pre-12.9.0 shape ──
$press = sn_email_markup( 'press', 'juanlentino.com' );
ok( false === strpos( $press, 'data-esj' ), 'two-arg call ships no data-esj (contact aliases byte-stable)' );
ok( 'contact-press' === attr_of( $press, 'data-sn-goal' ), 'two-arg call keeps the contact-<user> goal' );

// ── 7. Goal override is slugged like the default path ──
$odd = sn_email_markup( 'press', 'juanlentino.com', array( 'goal' => 'Reply Note!!' ) );
ok( 'reply-note' === attr_of( $odd, 'data-sn-goal' ), 'goal override is slugged, not emitted raw' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
