<?php
/**
 * Signal & Noise: the EN | ES switch in the header.
 *
 * The companion plugin owns the pairing (inc/bilingual.php there: a Spanish
 * page names its English page in `_sn_en_counterpart`) and answers two
 * questions, sn_lang_pair_urls() and sn_lang_current(). This file only draws
 * the link. It renders nothing when the plugin is absent or the page has no
 * PUBLISHED counterpart, so it can sit in the shared header. No cookie, no
 * storage, no redirect by browser language: a plain link to the other page.
 *
 * @package SignalNoise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The EN | ES switch. Pure, for the test.
 *
 * One bordered, two-part control in the header on every page (owner,
 * 2026-10-15: one switch, one place; the footer link is gone). The current
 * language is filled and marked aria-current; the other is a plain link
 * carrying hreflang and lang, so a screen reader announces it in its own
 * language. Every accessible name starts with the visible "EN"/"ES" (WCAG
 * 2.5.3, axe label-content-name-mismatch).
 *
 * An English page with no Spanish version (every note) does not send the
 * reader anywhere (owner, 2026-10-18: Spanish readers stay on the note, a
 * translator covers it). ES is then a disabled link: role="link",
 * aria-disabled, focusable so its hint reaches a keyboard, and a data-hint
 * the CSS shows on hover and focus ("Solo en inglés."). Not a title: the
 * browser would draw its own tooltip over the box. The translator line sits
 * on the Spanish header's Notas link (inc/lang-chrome.php), the link that
 * actually leads to English notes (owner, 2026-10-18). A Spanish page with no English version
 * renders nothing, as before. No cookie, no storage, no redirect.
 *
 * @param array{en?:string,es?:string} $pair    This page's pair, or [].
 * @param string                       $current 'en' or 'es'.
 * @return string HTML, or '' when there is nowhere to switch to.
 */
function sn_lang_toggle_html( $pair, $current ) {
	$paired = ! empty( $pair['en'] ) && ! empty( $pair['es'] );
	if ( ! $paired && 'en' !== $current ) {
		return '';
	}
	$langs = array(
		// Visible text, accessible name, lang (the Spanish pages are es-AR).
		'en' => array( 'EN', 'EN, English', 'en' ),
		'es' => array( 'ES', 'ES, Español', 'es-AR' ),
	);
	$items = array();
	foreach ( $langs as $code => $text ) {
		if ( $code === $current ) {
			$items[] = '<span class="sn-lang-switch__seg is-current" aria-current="true">' . esc_html( $text[0] ) . '</span>';
		} elseif ( ! $paired ) {
			$items[] = '<span class="sn-lang-switch__seg is-unavailable" role="link" aria-disabled="true" tabindex="0" lang="es-AR" aria-label="' . esc_attr( 'ES, no disponible: solo en inglés.' ) . '" data-hint="' . esc_attr( 'Solo en inglés.' ) . '">ES</span>';
		} else {
			$items[] = '<a class="sn-lang-switch__seg" href="' . esc_url( $pair[ $code ] ) . '" hreflang="' . esc_attr( $code ) . '" lang="' . esc_attr( $text[2] ) . '" aria-label="' . esc_attr( $text[1] ) . '">' . esc_html( $text[0] ) . '</a>';
		}
	}
	return '<nav class="sn-lang-switch" aria-label="' . esc_attr( 'es' === $current ? 'Idioma' : 'Language' ) . '">' . implode( '', $items ) . '</nav>';
}

/**
 * The switch for the page being viewed.
 *
 * @return string
 */
function sn_lang_toggle_markup() {
	if ( ! function_exists( 'sn_lang_pair_urls' ) || ! function_exists( 'sn_lang_current' ) ) {
		return '';
	}
	return sn_lang_toggle_html( sn_lang_pair_urls(), sn_lang_current() );
}

/**
 * Is the page being viewed Spanish? The theme's one question for its own
 * strings (the discography labels).
 *
 * @return bool
 */
function sn_theme_is_spanish() {
	return function_exists( 'sn_lang_current' ) && 'es' === sn_lang_current();
}
