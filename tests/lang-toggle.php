<?php
require_once __DIR__ . '/lib/components-source.php'; // the component layer, joined (2026-10-31 split)
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
function apply_filters( $t, $v ) { return 'sn_discography_entries' === $t ? ( $GLOBALS['__entries'] ?? $v ) : $v; }

require __DIR__ . '/../inc/lang-toggle.php';
require __DIR__ . '/../inc/discography-render.php';

function sn_discography_shortcode_bridge_probe() {
	$GLOBALS['__entries'] = array( array( 'id' => 'a', 'title' => 'T', 'year' => 2020, 'roles' => array( 'Mixing' ) ) );
	return sn_discography_shortcode();
}
$pair = array( 'en' => 'https://x.test/music/', 'es' => 'https://x.test/musica/' );

echo "Group: the switch\n";
ok( '' === sn_lang_toggle_html( array(), 'es' ), 'a Spanish page with no English counterpart: nothing, as before' );
ok( '' === sn_lang_toggle_html( array( 'es' => 'https://x.test/musica/' ), 'es' ), 'half a pair on a Spanish page: nothing' );
$en = sn_lang_toggle_html( $pair, 'en' );
ok( 0 === strpos( $en, '<nav class="sn-lang-switch" aria-label="Language">' ), 'an English page labels the switch in English' );
ok( false !== strpos( $en, '<span class="sn-lang-switch__seg is-current" aria-current="true">EN</span><a class="sn-lang-switch__seg" href="https://x.test/musica/" hreflang="es" lang="es-AR" aria-label="ES, Español">ES</a>' ), 'EN is the filled current segment, ES links to the translation; EN | ES order, no separator glyph' );
ok( 1 === substr_count( $en, '<a ' ), 'exactly one link (no link to the page you are on)' );
$es = sn_lang_toggle_html( $pair, 'es' );
ok( false !== strpos( $es, 'aria-label="Idioma"' ) && false !== strpos( $es, '<a class="sn-lang-switch__seg" href="https://x.test/music/" hreflang="en" lang="en" aria-label="EN, English">EN</a><span class="sn-lang-switch__seg is-current" aria-current="true">ES</span>' ), 'Spanish page: labelled Idioma, EN links back, ES is current' );

echo "\nGroup: an English page with no Spanish version (every note)\n";
// Owner, 2026-10-18: no more sending a Spanish reader to the Spanish home. The
// reader stays on the note; ES says it is only in English and points at the
// browser's translator.
$solo = sn_lang_toggle_html( array(), 'en' );
$half = sn_lang_toggle_html( array( 'en' => 'https://x.test/notes/x/' ), 'en' );
ok( $solo === $half, 'half a pair reads the same as none' );
ok( false === strpos( $solo, 'href' ) && false === strpos( $solo, '<a ' ), 'no link anywhere in the switch: nothing sends the reader away' );
ok( 1 === preg_match( '#<span class="sn-lang-switch__seg is-unavailable" role="link" aria-disabled="true" tabindex="0" lang="es-AR" aria-label="([^"]*)" data-hint="([^"]*)">ES</span>#', $solo, $u ), 'ES is a disabled link: role="link", aria-disabled, focusable, Spanish, visible text ES, hint in data-hint (' . $solo . ')' );
ok( 'ES, no disponible: solo en inglés.' === ( $u[1] ?? '' ), 'its name starts with the visible text and says it is unavailable, nothing more' );
// Owner, 2026-10-18 (live v2026.10.21): the translator line belongs under Notas,
// not on ES. ES keeps its state and a short hint.
ok( 'Solo en inglés.' === ( $u[2] ?? '' ), 'the hint is just "Solo en inglés."' );
ok( false === strpos( $solo, 'traductor' ), 'no translator sentence on ES' );
ok( false === strpos( $solo, 'title=' ), 'no title attribute: the browser would draw its own tooltip over the CSS hint' );
ok( false !== strpos( $solo, '<span class="sn-lang-switch__seg is-current" aria-current="true">EN</span>' ), 'EN stays the filled current segment' );

echo "\nGroup: WCAG 2.5.3, the name starts with the visible text in every state\n";
$states = 0;
foreach ( array( $en, $es, $solo ) as $html ) {
	preg_match_all( '#aria-label="([^"]*)"[^>]*>(EN|ES)</(?:a|span)>#', $html, $mm, PREG_SET_ORDER );
	foreach ( $mm as $x ) {
		++$states;
		ok( 0 === strpos( html_entity_decode( $x[1] ), $x[2] ), 'name "' . $x[1] . '" starts with "' . $x[2] . '"' );
	}
}
ok( 3 === $states, 'the scan saw a named segment in all three states (a silent zero is not a pass)' );
$css = (string) preg_replace( '#/\*.*?\*/#s', '', snt_components_source() );
ok( 1 === preg_match( '/\.sn-lang-switch__seg\.is-unavailable\s*\{[^}]*color:\s*var\(--wp--preset--color--rust\)/', $css ), 'the unavailable segment is muted (rust, the muted text token, in both palettes), not filled' );
ok( 1 === preg_match( '/\.sn-lang-switch__seg\.is-unavailable:focus-visible::after\s*\{[^}]*content:\s*attr\(data-hint\)/', $css ), 'the hint shows on keyboard focus (a title tooltip never reaches a keyboard)' );
ok( false === strpos( $css, 'attr(title)' ), 'no CSS hint reads title: a title draws a second, native tooltip on hover' );
ok( 1 === preg_match( '/@media \(hover: hover\)\s*\{[^@]*\.sn-lang-switch__seg\.is-unavailable:hover::after/', $css ), '...and on hover where the pointer hovers' );
ok( 0 === preg_match( '/\.sn-lang-switch__seg\.is-unavailable(?::focus-visible)?\s*\{[^}]*(padding|min-height|border|margin)\s*:/', $css ), 'no box change: same padding and height as the other segment, so no layout shift' );
ok( false === strpos( $en . $es . $solo, 'cookie' ) && false === strpos( $en . $es . $solo, 'localStorage' ), 'plain links: no storage' );
ok( '' === sn_lang_toggle_markup(), 'without the companion plugin, the block renders nothing' );
ok( false === sn_theme_is_spanish(), 'without the plugin, no page is Spanish' );

ok( false !== strpos( sn_discography_shortcode_bridge_probe(), 'Since 2022 alone, roughly 110 tracks' ), 'the bridge line is English on an English page' );

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
ok( false !== strpos( sn_discography_shortcode_bridge_probe(), 'Solo desde 2022, pasaron por mis manos unos 110 temas.' ), 'the bridge line is Spanish on a Spanish page' );
$card = sn_discography_render_card( array( 'title' => 'T', 'spotify_id' => 'abc', 'muso_url' => 'https://muso.ai/x' ) );
ok( false !== strpos( $card, 'aria-label="Reproducir T"' ) && false !== strpos( $card, 'Créditos' ), 'the card\'s play name and Credits link are Spanish' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail ? 1 : 0 );
