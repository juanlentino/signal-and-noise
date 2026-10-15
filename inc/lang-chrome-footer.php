<?php
/**
 * Signal & Noise: the footer meta-nav's labels in Spanish on a Spanish page
 * (the pages stay English, so each link says so and carries hreflang="en").
 * The footer has no language link: the header's EN | ES switch is the one
 * place (owner, 2026-10-15). Loaded by inc/lang-chrome.php.
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
