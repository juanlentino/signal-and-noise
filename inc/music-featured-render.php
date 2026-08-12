<?php
/**
 * Signal & Noise — [sn_music_featured] featured-release player (the /music hero).
 *
 * Renders the single "press play" Spotify embed at the top of /music from the
 * featured config the companion plugin (signal-and-noise-tools v4.14.0+) supplies
 * over the cross-package filter `sn_music_featured`. The owner sets it from
 * Monitoring → Music (paste a Spotify track/album/playlist link); the plugin
 * parses + stores it and answers the filter with a ready embed URL.
 *
 * Standalone-safe: plugin absent (or the setting empty) → filter yields array()
 * → this shortcode degrades to '' — no fatal, no empty box. This is the ONE
 * place /music mounts an eager iframe (the discography grid below stays
 * click-to-play); one featured player is the intentional hero. The embed URL is
 * escaped at output; the player height adapts to the embed type.
 *
 * @package SignalNoise
 * @since 9.15.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * [sn_music_featured] — the featured-release FACADE at the top of /music.
 *
 * Reads the featured config off the standalone-safe filter and renders an
 * accessible card, never an iframe: a real <a> to the release's public
 * Spotify page, carrying the embed URL and player height as data attributes.
 * assets/js/discography.js upgrades the card to click-to-play — the iframe is
 * mounted only on the reader's explicit activation, and the script refuses
 * any data-embed that is not a Spotify /embed/ URL. With JS off the card is
 * simply a link that works.
 *
 * This used to be the ONE place /music mounted an eager iframe (the
 * discography grid below was always click-to-play). The eager hero handed
 * Spotify the reader's fingerprint before the reader had asked to hear
 * anything — the accessible-embeds decision (docs/r1-prep.md in the plugin
 * repo, option (b)) retired that exception, so the whole site now fetches
 * nothing from a third party before the reader chooses it.
 *
 * Returns '' when nothing is configured.
 *
 * @return string Facade HTML, or '' when unset.
 */
function sn_music_featured_shortcode() {
	$featured = apply_filters( 'sn_music_featured', array() );
	if ( ! is_array( $featured ) || empty( $featured['embed_url'] ) ) {
		return '';
	}

	$embed = (string) $featured['embed_url'];
	$type  = (string) ( $featured['type'] ?? 'track' );
	$id    = (string) ( $featured['id'] ?? '' );

	// The link-out target. A partial record without open_url still gets a real
	// public URL derived from type+id — never the embed page, never a dead card.
	$open = (string) ( $featured['open_url'] ?? '' );
	if ( '' === $open && '' !== $id ) {
		$open = 'https://open.spotify.com/' . rawurlencode( $type ) . '/' . rawurlencode( $id );
	}
	if ( '' === $open ) {
		$open = $embed;
	}

	// Full-height player (shows the tracklist) for album/playlist/show; compact
	// for a single track/episode/artist. Stamped server-side so the script
	// never has to re-derive the type mapping.
	$tall   = in_array( $type, array( 'album', 'playlist', 'show' ), true );
	$height = $tall ? 352 : 152;

	$out  = '<div class="sn-music-featured" data-embed="' . esc_url( $embed ) . '" data-height="' . (int) $height . '">';
	$out .= '<p class="sn-music-featured__label"><span class="sn-music-featured__dot" aria-hidden="true"></span>'
		. esc_html__( 'Featured', 'signal-noise' ) . ' &middot; ' . esc_html__( 'Press play', 'signal-noise' ) . '</p>';
	$out .= '<a class="sn-music-featured__facade" href="' . esc_url( $open ) . '">'
		. '<span class="sn-music-featured__play" aria-hidden="true"></span>'
		. '<span class="sn-music-featured__cta">' . esc_html__( 'Play the featured release on Spotify', 'signal-noise' ) . '</span>'
		. '</a>';
	$out .= '</div>';

	return $out;
}

add_shortcode( 'sn_music_featured', 'sn_music_featured_shortcode' );
