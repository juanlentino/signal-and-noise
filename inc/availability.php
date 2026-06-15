<?php
/**
 * Signal & Noise — [sn_availability] availability line (D5).
 *
 * Surfaces the owner-edited availability string in the /contact + /services page
 * heroes: a short status line like "Available for select mixing work". The
 * string lives in the companion plugin (signal-and-noise-tools v6.17.0+) under
 * the `sn_settings` option, subtree `identity.availability`, edited on Site →
 * Identity & SEO.
 *
 * STANDALONE-SAFE. The theme is independently installable. When the plugin is
 * absent the `sn_settings` option does not exist, the string is empty, and the
 * shortcode renders '' — no fatal, no empty box. Mirrors the read-and-degrade
 * pattern in inc/identity-rels.php and inc/music-featured-render.php. The value
 * is escaped at the output sink (the plugin pre-sanitizes on save, but the theme
 * never trusts stored data).
 *
 * @package SignalNoise
 * @since 10.9.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The configured availability string, trimmed. '' when unset / plugin absent /
 * malformed (non-string). Not escaped — callers escape at the output sink.
 *
 * @return string
 */
function sn_availability_text() {
	$settings = get_option( 'sn_settings' );
	if ( is_array( $settings )
		&& isset( $settings['identity']['availability'] )
		&& is_string( $settings['identity']['availability'] )
	) {
		return trim( $settings['identity']['availability'] );
	}
	return '';
}

/**
 * [sn_availability] — the availability status line for the Contact/Services hero.
 *
 * Returns '' when nothing is configured (no empty element). Mirrors the
 * .sn-music-featured__label + dot idiom: a small uppercase line with a leading
 * signal dot. The text is esc_html()'d at output.
 *
 * @return string Availability-line HTML, or '' when unset.
 */
function sn_availability_shortcode() {
	$text = sn_availability_text();
	if ( '' === $text ) {
		return '';
	}
	return '<p class="sn-availability"><span class="sn-availability__dot" aria-hidden="true"></span>'
		. '<span class="sn-availability__text">' . esc_html( $text ) . '</span></p>';
}

if ( ! defined( 'SN_AVAILABILITY_TEST' ) || ! SN_AVAILABILITY_TEST ) {
	add_shortcode( 'sn_availability', 'sn_availability_shortcode' );
}
