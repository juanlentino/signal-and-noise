<?php
/**
 * Signal & Noise: the header and footer in Spanish on the Spanish pages.
 *
 * On a page the companion plugin calls Spanish (inc/bilingual.php there), the
 * shared chrome speaks Spanish: the header nav labels, each pointing at the
 * Spanish page where one is published (Música → /musica/) and at the English
 * page otherwise (Notas → /notes/, which stays English); the logo link goes to
 * /inicio/; the menu, search, theme-toggle and footer meta-nav labels switch.
 * The pages those footer links reach stay English, and their names say so.
 * English pages render byte for byte as before. Owner, 2026-10-15.
 *
 * @package SignalNoise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A string in the page's language. The Spanish is Argentine (voseo).
 *
 * @param string $en English.
 * @param string $es Spanish.
 * @return string
 */
function sn_theme_t( $en, $es ) {
	return function_exists( 'sn_theme_is_spanish' ) && sn_theme_is_spanish() ? $es : $en;
}

/**
 * The header nav's Spanish labels, by the English link's path.
 *
 * @return array<string,string>
 */
function sn_lang_nav_labels() {
	return array(
		'/'         => 'Inicio',
		'/about'    => 'Sobre mí',
		'/resume'   => 'Currículum',
		'/music'    => 'Música',
		'/services' => 'Servicios',
		'/notes'    => 'Notas',
		'/contact'  => 'Contacto',
	);
}

/**
 * The published Spanish page for an English path, or '' when there is none.
 *
 * @param string $path English path, e.g. "/music" or "/".
 * @return string Spanish URL, or ''.
 */
function sn_lang_spanish_url_for( $path ) {
	if ( ! function_exists( 'sn_lang_es_counterpart' ) || ! function_exists( 'sn_lang_page_url' ) ) {
		return '';
	}
	$page = '/' === $path ? null : get_page_by_path( ltrim( (string) $path, '/' ) );
	$id   = '/' === $path ? (int) get_option( 'page_on_front' ) : (int) ( $page->ID ?? 0 );
	$es   = $id ? sn_lang_es_counterpart( $id ) : 0;
	return $es ? (string) sn_lang_page_url( $es ) : '';
}

/**
 * One header nav link in Spanish: its label, its href (the Spanish page when
 * there is one) and aria-current when that href is this page. Runs after
 * inc/nav-current.php, which compares the English URL. Pure over its inputs.
 *
 * @param string $html     Rendered navigation-link.
 * @param string $label    The link's English label.
 * @param string $path     The link's English path.
 * @param string $es_url   The Spanish URL, or '' to keep the English href.
 * @param string $request  The request's path.
 * @return string
 */
function sn_lang_nav_link_html( $html, $label, $path, $es_url, $request ) {
	$labels = sn_lang_nav_labels();
	if ( ! isset( $labels[ $path ] ) ) {
		return $html;
	}
	$html = str_replace( '>' . esc_html( $label ) . '</span>', '>' . esc_html( $labels[ $path ] ) . '</span>', $html );
	if ( '' === $es_url ) {
		return $html;
	}
	$html = (string) preg_replace( '/(<a\b[^>]*\bhref=")[^"]*(")/', '${1}' . esc_url( $es_url ) . '${2}', $html, 1 );
	$es_path = rtrim( (string) wp_parse_url( $es_url, PHP_URL_PATH ), '/' );
	if ( '' !== $es_path && $es_path === $request && false === strpos( $html, 'aria-current=' ) ) {
		$html = (string) preg_replace( '/<a\b/', '<a aria-current="page"', $html, 1 );
	}
	return $html;
}

add_filter(
	'render_block_core/navigation-link',
	static function ( $html, $block ) {
		if ( ! sn_theme_is_spanish() || ! function_exists( 'sn_nav_current_path' ) ) {
			return $html;
		}
		$path = sn_nav_current_path( (string) ( $block['attrs']['url'] ?? '' ) );
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- compared only.
		$request = (string) sn_nav_current_path( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '' );
		return null === $path ? $html : sn_lang_nav_link_html( $html, (string) ( $block['attrs']['label'] ?? '' ), $path, sn_lang_spanish_url_for( $path ), $request );
	},
	11,
	2
);

/**
 * The fixed English strings of the header and footer chrome, in Spanish. Pure.
 *
 * @param string $html    Rendered markup.
 * @param string $es_home The Spanish home URL ('' to leave the logo link).
 * @return string
 */
function sn_lang_chrome_strings( $html, $es_home ) {
	$map = array(
		'aria-label="Main navigation"'          => 'aria-label="Navegación principal"',
		'aria-label="Open menu"'                => 'aria-label="Abrir el menú"',
		'aria-label="Close menu"'               => 'aria-label="Cerrar el menú"',
		'class="sn-cmdk-trigger-label">Search<' => 'class="sn-cmdk-trigger-label">Buscar<',
		'aria-label="Juan Lentino — home"'      => 'aria-label="Juan Lentino, inicio"',
	);
	$html = strtr( $html, $map );
	if ( '' !== $es_home && false !== strpos( $html, 'class="sn-logo-link"' ) ) {
		$html = str_replace( '<a href="/" class="sn-logo-link"', '<a href="' . esc_url( $es_home ) . '" class="sn-logo-link"', $html );
	}
	return $html;
}

foreach ( array( 'core/navigation', 'core/html' ) as $sn_chrome_block ) {
	add_filter(
		'render_block_' . $sn_chrome_block,
		static function ( $html ) {
			return sn_theme_is_spanish() ? sn_lang_chrome_strings( (string) $html, sn_lang_spanish_url_for( '/' ) ) : $html;
		}
	);
}
unset( $sn_chrome_block );

require_once __DIR__ . '/lang-chrome-footer.php';
