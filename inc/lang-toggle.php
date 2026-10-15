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
 * language, and its accessible name keeps the visible "ES"/"EN" (WCAG 2.5.3).
 * On an English page with no translation, ES goes to the Spanish home and its
 * name says so. No cookie, no storage, no redirect by browser language.
 *
 * @param array{en?:string,es?:string} $pair    This page's pair, or [].
 * @param string                       $current 'en' or 'es'.
 * @param string                       $es_home The published Spanish home, or ''.
 * @return string HTML, or '' when there is nowhere to switch to.
 */
function sn_lang_toggle_html( $pair, $current, $es_home = '' ) {
	$to_home = false;
	if ( empty( $pair['en'] ) || empty( $pair['es'] ) ) {
		if ( 'en' !== $current || '' === $es_home ) {
			return '';
		}
		$pair    = array( 'en' => '', 'es' => $es_home );
		$to_home = true;
	}
	$langs = array(
		// Visible text, accessible name, lang (the Spanish pages are es-AR).
		'en' => array( 'EN', 'English (EN)', 'en' ),
		'es' => array( 'ES', $to_home ? 'Español (ES): página de inicio' : 'Español (ES)', 'es-AR' ),
	);
	$items = array();
	foreach ( $langs as $code => $text ) {
		$items[] = $code === $current
			? '<span class="sn-lang-switch__seg is-current" aria-current="true">' . esc_html( $text[0] ) . '</span>'
			: '<a class="sn-lang-switch__seg" href="' . esc_url( $pair[ $code ] ) . '" hreflang="' . esc_attr( $code ) . '" lang="' . esc_attr( $text[2] ) . '" aria-label="' . esc_attr( $text[1] ) . '">' . esc_html( $text[0] ) . '</a>';
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
	$es_home = function_exists( 'sn_lang_spanish_url_for' ) ? sn_lang_spanish_url_for( '/' ) : '';
	return sn_lang_toggle_html( sn_lang_pair_urls(), sn_lang_current(), $es_home );
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
