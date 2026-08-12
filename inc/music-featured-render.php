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
 * [sn_music_featured] — the one featured Spotify player at the top of /music.
 *
 * Reads the featured config off the standalone-safe filter and renders a single
 * embed. Returns '' when nothing is configured.
 *
 * @return string Featured-player HTML, or '' when unset.
 */
function sn_music_featured_shortcode() {
	$featured = apply_filters( 'sn_music_featured', array() );
	if ( ! is_array( $featured ) || empty( $featured['embed_url'] ) ) {
		return '';
	}

	$embed = (string) $featured['embed_url'];
	$type  = (string) ( $featured['type'] ?? 'track' );

	// Full-height player (shows the tracklist) for album/playlist/show; compact
	// for a single track/episode/artist.
	$tall   = in_array( $type, array( 'album', 'playlist', 'show' ), true );
	$height = $tall ? 352 : 152;

	$out  = '<div class="sn-music-featured">';
	$out .= '<p class="sn-music-featured__label"><span class="sn-music-featured__dot" aria-hidden="true"></span>'
		. esc_html__( 'Featured', 'signal-noise' ) . ' &middot; ' . esc_html__( 'Press play', 'signal-noise' ) . '</p>';
	$out .= '<iframe class="sn-music-featured__player" src="' . esc_url( $embed )
		. '" width="100%" height="' . (int) $height . '" loading="lazy"'
		. ' allow="encrypted-media; clipboard-write; fullscreen; picture-in-picture" allowfullscreen'
		. ' title="' . esc_attr( 'Featured Spotify player' ) . '"></iframe>';
	$out .= '</div>';

	return $out;
}

add_shortcode( 'sn_music_featured', 'sn_music_featured_shortcode' );
