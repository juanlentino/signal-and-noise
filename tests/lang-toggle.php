<?php
/**
 * Tests: the EN / ES toggle (inc/lang-toggle.php) and the Spanish discography
 * strings and release note (inc/discography-render.php).
 */
if ( PHP_SAPI !== 'cli' ) { http_response_code( 404 ); exit; }
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', '/' ); }

$pass = 0; $fail = 0;
function ok( $cond, $label ) {
	global $pass, $fail;
	if ( $cond ) { $pass++; echo "PASS: $label\n"; } else { $fail++; echo "FAIL: $label\n"; }
}
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_url( $s ) { return (string) $s; }
function __( $s ) { return $s; }
function _n( $a, $b, $n ) { return 1 === $n ? $a : $b; }
function esc_html__( $s ) { return esc_html( $s ); }
function add_shortcode() {}
function apply_filters( $t, $v ) { return $v; }

require __DIR__ . '/../inc/lang-toggle.php';
require __DIR__ . '/../inc/discography-render.php';

$pair = array( 'en' => 'https://x.test/music/', 'es' => 'https://x.test/musica/' );

echo "Group: the toggle\n";
ok( '' === sn_lang_toggle_html( array(), 'en' ), 'no pair: renders nothing (an unpaired page, or a draft translation)' );
ok( '' === sn_lang_toggle_html( array( 'en' => 'https://x.test/music/' ), 'en' ), 'half a pair: renders nothing' );
$en = sn_lang_toggle_html( $pair, 'en' );
ok( false !== strpos( $en, '<nav class="sn-lang-toggle" aria-label="Language">' ), 'an English page labels the nav in English' );
ok( false !== strpos( $en, 'href="https://x.test/musica/" hreflang="es" lang="es" aria-label="Español (ES)">ES</a>' ), 'English page: ES is a link to the Spanish page, hreflang + lang es, name keeps the visible ES' );
ok( false !== strpos( $en, '<span class="sn-lang-toggle__current" aria-current="true">EN</span>' ), 'English page: EN is plain text marked current' );
ok( 1 === substr_count( $en, '<a ' ), 'exactly one link (no link to the page you are on)' );
$es = sn_lang_toggle_html( $pair, 'es' );
ok( false !== strpos( $es, 'aria-label="Idioma"' ) && false !== strpos( $es, 'href="https://x.test/music/" hreflang="en" lang="en" aria-label="English (EN)">EN</a>' ), 'Spanish page: nav labelled Idioma, EN links back to English' );
ok( false === strpos( $en . $es, 'cookie' ) && false === strpos( $en . $es, 'localStorage' ), 'a plain link: no storage' );
ok( '' === sn_lang_toggle_markup(), 'without the companion plugin, the block renders nothing' );
ok( false === sn_theme_is_spanish(), 'without the plugin, no page is Spanish' );

echo "\nGroup: the release note on a card\n";
ok( '' === sn_discography_render_note( array() ), 'no note: nothing' );
ok( '<p class="sn-disco-note">Stage manager (listed on Muso.AI as Manager).</p>' === sn_discography_render_note( array( 'note' => 'Stage manager (listed on Muso.AI as Manager).' ) ), 'an English note renders on an English page' );
$card = sn_discography_render_card( array( 'title' => 'Fin del Mundo', 'artist' => 'Richter', 'roles' => array( 'Manager' ), 'note' => 'Stage manager (listed on Muso.AI as Manager).' ) );
ok( strpos( $card, 'sn-disco-roles' ) < strpos( $card, 'sn-disco-note' ), 'the card prints the note right after the roles line' );
ok( false !== strpos( sn_discography_render_note( array( 'note' => '<b>x</b>' ) ), '&lt;b&gt;' ), 'the note is escaped' );

echo "\nGroup: Spanish strings (a page the plugin calls Spanish)\n";
// Declared inside a block so PHP does not hoist them: the groups above run
// with no plugin, these below with one.
if ( true ) {
	function sn_lang_current() { return 'es'; }
	function sn_lang_pair_urls() { return $GLOBALS['pair']; }
}
$GLOBALS['pair'] = $pair;
ok( true === sn_theme_is_spanish(), 'with the plugin answering es, the page is Spanish' );
ok( false !== strpos( sn_lang_toggle_markup(), 'aria-label="Idioma"' ), 'the block reads the plugin\'s pair and language' );
ok( 'lanzamientos' === sn_disco_t( 'releases', 'lanzamientos' ), 'a label switches to Spanish' );
ok( '<p class="sn-disco-note">Jefe de escenario.</p>' === sn_discography_render_note( array( 'note' => 'Stage manager.', 'note_es' => 'Jefe de escenario.' ) ), 'a Spanish note wins on a Spanish page' );
ok( '<p class="sn-disco-note" lang="en">Stage manager.</p>' === sn_discography_render_note( array( 'note' => 'Stage manager.', 'note_es' => '' ) ), 'an empty Spanish note falls back to English, marked lang="en"' );
$card = sn_discography_render_card( array( 'title' => 'T', 'spotify_id' => 'abc', 'muso_url' => 'https://muso.ai/x' ) );
ok( false !== strpos( $card, 'aria-label="Reproducir T"' ) && false !== strpos( $card, 'Créditos' ), 'the card\'s play name and Credits link are Spanish' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail ? 1 : 0 );
