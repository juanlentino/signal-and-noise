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
	$links = array(
		'/now/'            => array( 'Ahora: en qué estoy (en inglés)', 'Ahora' ),
		'/accessibility/'  => array( 'Declaración de accesibilidad (en inglés)', 'Accesibilidad' ),
		'/colophon/'       => array( 'Colofón: cómo está hecho este sitio (en inglés)', 'Colofón' ),
		'/stats/'          => array( 'Estadísticas: qué se lee acá (en inglés)', 'Estadísticas' ),
		'/privacy-policy/' => array( 'Política de privacidad (en inglés)', 'Privacidad' ),
	);
	// Keyed on the nav's label and each link's href with the HTML API, so an
	// attribute reorder or an added class cannot stop the switch.
	$tags = new WP_HTML_Tag_Processor( (string) $html );
	while ( $tags->next_tag() ) {
		if ( 'NAV' === $tags->get_tag() && 'Site meta' === $tags->get_attribute( 'aria-label' ) ) {
			$tags->set_attribute( 'aria-label', 'Información del sitio' );
			continue;
		}
		$href = 'A' === $tags->get_tag() ? $tags->get_attribute( 'href' ) : null;
		if ( ! is_string( $href ) || ! isset( $links[ $href ] ) ) {
			continue;
		}
		$tags->set_attribute( 'hreflang', 'en' );
		$tags->set_attribute( 'aria-label', $links[ $href ][0] );
		$tags->set_attribute( 'data-hint', $links[ $href ][1] );
	}
	return $tags->get_updated_html();
}
