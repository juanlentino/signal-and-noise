<?php
/**
 * Signal & Noise — [sn_discography] release-timeline shortcode.
 *
 * Renders a brutalist, year-grouped discography timeline on the /music
 * page from the normalized discography entries supplied by the companion
 * plugin (signal-and-noise-tools v4.13.0+) over the cross-package filter
 * `sn_discography_entries`.
 *
 * Standalone-safe by construction: the plugin owns the sync + data + the
 * add_filter that supplies entries. With the plugin absent (or the store
 * empty until the first sync runs) the filter yields array() and this
 * shortcode degrades to '' — an empty timeline, NO fatal. The theme never
 * makes a request-time API call; it only reads the cached, normalized
 * entries.
 *
 * Performance (load-bearing): N live Spotify iframes would wreck the page,
 * so the timeline renders as a lightweight list — artwork + metadata, zero
 * iframes by default — with a click-to-play <button class="sn-disco-play"
 * data-spotify="..."> per release. assets/js/discography.js lazy-mounts the
 * Spotify embed on demand (open.spotify.com/embed/album/<id>). With JS
 * disabled the button no-ops and the Spotify link still works — nothing
 * breaks, nothing misleads.
 *
 * Every field that originates from external data (Muso/Spotify) is escaped
 * with esc_html / esc_url / esc_attr at the point of output.
 *
 * Normalized entry shape (the cross-package contract — see the plugin's
 * inc/discography-store.php): id, title, artist, roles[], year, type,
 * image, spotify_id, spotify_url, muso_url, isrc, upc.
 *
 * @package SignalNoise
 * @since 9.13.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * [sn_discography] — data-driven release timeline for the /music page.
 *
 * Reads the cached, normalized entries off the standalone-safe filter and
 * renders them grouped by release year, descending. Returns '' when there
 * are no entries (plugin absent, or store not yet synced) so placing the
 * shortcode is always safe.
 *
 * @return string Timeline HTML, or '' when there are no entries.
 */
function sn_discography_shortcode() {
	$entries = apply_filters( 'sn_discography_entries', array() );

	if ( ! is_array( $entries ) || empty( $entries ) ) {
		return '';
	}

	// Group by year (entries arrive sorted year-desc from the store, but
	// re-sort defensively so the timeline is correct regardless of the
	// supplier's ordering).
	$by_year = array();
	foreach ( $entries as $entry ) {
		if ( ! is_array( $entry ) ) {
			continue;
		}
		$year                = (int) ( $entry['year'] ?? 0 );
		$by_year[ $year ][]  = $entry;
	}
	if ( empty( $by_year ) ) {
		return '';
	}
	krsort( $by_year, SORT_NUMERIC );

	$out  = '<div class="sn-discography">';

	foreach ( $by_year as $year => $year_entries ) {
		$year_label = $year > 0 ? (string) $year : '—';

		$out .= '<section class="sn-disco-year">';
		$out .= '<h2 class="sn-disco-year__label">' . esc_html( $year_label ) . '</h2>';
		$out .= '<ul class="sn-disco-list">';

		foreach ( $year_entries as $entry ) {
			$out .= sn_discography_render_entry( $entry );
		}

		$out .= '</ul>';
		$out .= '</section>';
	}

	$out .= '</div>';

	return $out;
}

/**
 * Render a single normalized discography entry as a timeline row.
 *
 * Separated from the grouping loop so the per-entry markup contract is
 * testable in isolation. Every external-data field is escaped here.
 *
 * @param array $entry One normalized store entry.
 * @return string Row HTML (one <li>).
 */
function sn_discography_render_entry( $entry ) {
	$title      = (string) ( $entry['title'] ?? '' );
	$artist     = (string) ( $entry['artist'] ?? '' );
	$image      = (string) ( $entry['image'] ?? '' );
	$spotify_id = (string) ( $entry['spotify_id'] ?? '' );
	$type       = (string) ( $entry['type'] ?? 'album' );
	$muso_url   = (string) ( $entry['muso_url'] ?? '' );
	$roles      = isset( $entry['roles'] ) && is_array( $entry['roles'] ) ? $entry['roles'] : array();

	$out = '<li class="sn-disco-item">';

	// Artwork — lazy <img>; only rendered when Spotify matched media.
	if ( '' !== $image ) {
		$out .= '<img class="sn-disco-art" loading="lazy" decoding="async" src="'
			. esc_url( $image ) . '" alt="' . esc_attr( $title ) . '" width="120" height="120" />';
	}

	$out .= '<div class="sn-disco-meta">';

	// Title.
	$out .= '<h3 class="sn-disco-title">' . esc_html( $title ) . '</h3>';

	// Primary artist.
	if ( '' !== $artist ) {
		$out .= '<p class="sn-disco-artist">' . esc_html( $artist ) . '</p>';
	}

	// Role(s) — Juan's credited roles from Muso.
	$roles = array_values( array_filter( array_map( 'strval', $roles ) ) );
	if ( ! empty( $roles ) ) {
		$out .= '<p class="sn-disco-roles">' . esc_html( implode( ' · ', $roles ) ) . '</p>';
	}

	// Action row: click-to-play (lazy embed) + Muso deep link.
	$out .= '<p class="sn-disco-actions">';

	if ( '' !== $spotify_id ) {
		$out .= '<button type="button" class="sn-disco-play" data-spotify="'
			. esc_attr( $spotify_id ) . '" data-type="' . esc_attr( $type ) . '">'
			. esc_html__( 'Play', 'signal-noise' ) . '</button>';
	}

	if ( '' !== $muso_url ) {
		$out .= '<a class="sn-disco-credits" href="' . esc_url( $muso_url )
			. '" rel="noopener" target="_blank">'
			. esc_html__( 'Credits', 'signal-noise' ) . '</a>';
	}

	$out .= '</p>'; // .sn-disco-actions
	$out .= '</div>'; // .sn-disco-meta
	$out .= '</li>';

	return $out;
}

add_shortcode( 'sn_discography', 'sn_discography_shortcode' );
