<?php
/**
 * Signal & Noise: the footer's language parts. The meta-nav's labels in
 * Spanish on a Spanish page (the pages stay English, so each link says so and
 * carries hreflang="en"), and the site-wide language link: "Español" on every
 * English page once the Spanish home is published, "English" on a Spanish one.
 * Loaded by inc/lang-chrome.php.
 *
 * @package SignalNoise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The meta-nav in Spanish. Pure; the English markup comes from
 * blocks/meta-nav/render.php untouched.
 *
 * @param string $html The meta-nav's markup.
 * @return string
 */
function sn_lang_meta_nav( $html ) {
	return strtr(
		$html,
		array(
			'aria-label="Site meta"'                                   => 'aria-label="Información del sitio"',
			'<a href="/now" aria-label="Now: what I&#8217;m focused on" title="Now">' => '<a href="/now" hreflang="en" aria-label="Ahora: en qué estoy (en inglés)" title="Ahora">',
			'<a href="/accessibility" aria-label="Accessibility statement" title="Accessibility">' => '<a href="/accessibility" hreflang="en" aria-label="Declaración de accesibilidad (en inglés)" title="Accesibilidad">',
			'<a href="/colophon/" aria-label="Colophon: how this site is made" title="Colophon">' => '<a href="/colophon/" hreflang="en" aria-label="Colofón: cómo está hecho este sitio (en inglés)" title="Colofón">',
			'<a href="/stats" aria-label="Stats: what gets read here" title="Stats">' => '<a href="/stats" hreflang="en" aria-label="Estadísticas: qué se lee acá (en inglés)" title="Estadísticas">',
			'<a href="/privacy-policy" aria-label="Privacy policy" title="Privacy">' => '<a href="/privacy-policy" hreflang="en" aria-label="Política de privacidad (en inglés)" title="Privacidad">',
		)
	);
}

/**
 * The footer's language link. Pure over its inputs.
 *
 * @param string $current  'en' or 'es': the page's language.
 * @param array  $pair     sn_lang_pair_urls() for this page ([] when unpaired).
 * @param string $es_home  The published Spanish home, or ''.
 * @param string $en_home  The English home.
 * @return string A <p>, or '' when there is no Spanish site to point at.
 */
function sn_lang_footer_link_html( $current, $pair, $es_home, $en_home ) {
	if ( '' === $es_home ) {
		return '';
	}
	if ( 'es' === $current ) {
		$href = $pair['en'] ?? $en_home;
		$a    = '<a href="' . esc_url( $href ) . '" hreflang="en" lang="en">English</a>';
	} else {
		$href = $pair['es'] ?? $es_home;
		$a    = '<a href="' . esc_url( $href ) . '" hreflang="es" lang="es-AR">Español</a>';
	}
	return '<p class="sn-lang-footer">' . $a . '</p>';
}

/**
 * The footer's language link for the page being viewed.
 *
 * @return string
 */
function sn_lang_footer_link_markup() {
	if ( ! function_exists( 'sn_lang_pair_urls' ) || ! function_exists( 'sn_lang_current' ) ) {
		return '';
	}
	return sn_lang_footer_link_html( sn_lang_current(), sn_lang_pair_urls(), sn_lang_spanish_url_for( '/' ), home_url( '/' ) );
}
