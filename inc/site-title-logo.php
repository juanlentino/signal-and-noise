<?php
/**
 * The header logo is core/site-title (parts/header.html). Its link carries the
 * wordmark class and the accessible name the hand-written anchor had, and on a
 * Spanish page it goes to the Spanish home and says so.
 *
 * Core renders `<p class="wp-block-site-title sn-logo-link"><a href rel="home">`
 * with the name from Settings > General and the link from home_url(). The
 * HTML API adds the class and label, so attribute order never matters.
 *
 * @package SignalNoise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The logo link, dressed. Pure.
 *
 * @param string $html    Rendered core/site-title.
 * @param string $name    The site name.
 * @param string $es_home The Spanish home URL, or '' on an English page.
 * @return string
 */
function sn_site_title_logo_html( $html, $name, $es_home ) {
	if ( ! class_exists( 'WP_HTML_Tag_Processor' ) ) {
		return $html;
	}
	$tags = new WP_HTML_Tag_Processor( (string) $html );
	if ( ! $tags->next_tag( 'A' ) ) {
		return $html;
	}
	$tags->add_class( 'sn-wordmark' );
	// The label the hand-written anchor carried, kept byte for byte.
	$tags->set_attribute( 'aria-label', '' === $es_home ? $name . " \u{2014} home" : $name . ', inicio' );
	if ( '' !== $es_home ) {
		$tags->set_attribute( 'href', $es_home );
	}
	return $tags->get_updated_html();
}

add_filter(
	'render_block_core/site-title',
	static function ( $html, $block ) {
		if ( false === strpos( (string) ( $block['attrs']['className'] ?? '' ), 'sn-logo-link' ) ) {
			return $html;
		}
		$spanish = function_exists( 'sn_theme_is_spanish' ) && sn_theme_is_spanish() && function_exists( 'sn_lang_spanish_url_for' );
		return sn_site_title_logo_html( (string) $html, (string) get_bloginfo( 'name' ), $spanish ? (string) sn_lang_spanish_url_for( '/' ) : '' );
	},
	10,
	2
);
