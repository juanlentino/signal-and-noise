<?php
/**
 * Signal & Noise: the EN / ES toggle on the bilingual storefront pages.
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
 * The toggle for a pair. Pure, for the test.
 *
 * The current language is plain text marked aria-current; the other is a link
 * carrying hreflang and lang, so a screen reader announces it in its own
 * language. Its accessible name keeps the visible "ES"/"EN" (WCAG 2.5.3).
 *
 * @param array{en?:string,es?:string} $pair    Both URLs, or [].
 * @param string                       $current 'en' or 'es'.
 * @return string HTML, or '' when there is no pair.
 */
function sn_lang_toggle_html( $pair, $current ) {
	if ( empty( $pair['en'] ) || empty( $pair['es'] ) ) {
		return '';
	}
	$langs = array(
		'en' => array( 'EN', 'English (EN)' ),
		'es' => array( 'ES', 'Español (ES)' ),
	);
	$items = array();
	foreach ( $langs as $code => $text ) {
		$items[] = $code === $current
			? '<span class="sn-lang-toggle__current" aria-current="true">' . esc_html( $text[0] ) . '</span>'
			: '<a class="sn-lang-toggle__link" href="' . esc_url( $pair[ $code ] ) . '" hreflang="' . esc_attr( $code ) . '" lang="' . esc_attr( $code ) . '" aria-label="' . esc_attr( $text[1] ) . '">' . esc_html( $text[0] ) . '</a>';
	}
	return '<nav class="sn-lang-toggle" aria-label="' . esc_attr( 'es' === $current ? 'Idioma' : 'Language' ) . '">'
		. implode( '<span class="sn-lang-toggle__sep" aria-hidden="true">/</span>', $items )
		. '</nav>';
}

/**
 * The toggle for the page being viewed.
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
