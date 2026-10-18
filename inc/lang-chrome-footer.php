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
			'<a href="/now" aria-label="Now: what I&#8217;m focused on" data-hint="Now">' => '<a href="/now" hreflang="en" aria-label="Ahora: en qué estoy (en inglés)" data-hint="Ahora">',
			'<a href="/accessibility" aria-label="Accessibility statement" data-hint="Accessibility">' => '<a href="/accessibility" hreflang="en" aria-label="Declaración de accesibilidad (en inglés)" data-hint="Accesibilidad">',
			'<a href="/colophon/" aria-label="Colophon: how this site is made" data-hint="Colophon">' => '<a href="/colophon/" hreflang="en" aria-label="Colofón: cómo está hecho este sitio (en inglés)" data-hint="Colofón">',
			'<a href="/stats" aria-label="Stats: what gets read here" data-hint="Stats">' => '<a href="/stats" hreflang="en" aria-label="Estadísticas: qué se lee acá (en inglés)" data-hint="Estadísticas">',
			'<a href="/privacy-policy" aria-label="Privacy policy" data-hint="Privacy">' => '<a href="/privacy-policy" hreflang="en" aria-label="Política de privacidad (en inglés)" data-hint="Privacidad">',
		)
	);
}
