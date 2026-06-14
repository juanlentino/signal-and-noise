<?php
/**
 * Signal & Noise — [sn_discography] cover-grid gallery (the /music discography).
 *
 * Renders a brutalist, year-grouped grid of album covers on the /music page from
 * the normalized discography entries the companion plugin (signal-and-noise-tools
 * v4.13.0+) supplies over the cross-package filter `sn_discography_entries`.
 *
 * v9.14.0 redesign: the covers are the hero. Output is a sticky controls rail
 * (live release count + role-filter chips) → per-year sections of cover cards →
 * a hidden empty state. Each card carries `data-roles` (pipe-joined) so
 * assets/js/discography.js can filter by credited role client-side, and
 * `data-spotify`/`data-type` so the whole cover acts as a lazy click-to-play
 * trigger (the JS swaps the cover for the Spotify embed — the server emits ZERO
 * eager iframes, the load-bearing performance contract).
 *
 * Standalone-safe by construction: the plugin owns the sync + data + the
 * add_filter that supplies entries. With the plugin absent (or the store empty
 * until the first sync) the filter yields array() and this shortcode degrades to
 * '' — no fatal. The theme makes no request-time API call; it only reads the
 * cached, normalized entries. Every external-data field is escaped at output.
 *
 * Normalized entry shape (the cross-package contract — see the plugin's
 * inc/discography-store.php): id, title, artist, roles[], year, date, type,
 * image, spotify_id, spotify_url, muso_url, isrc, upc.
 *
 * @package SignalNoise
 * @since 9.13.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Preferred display order for role-filter chips. Roles not listed here are
 * appended in first-encounter order; only roles actually present render a chip.
 *
 * @return string[]
 */
function sn_discography_role_order() {
	return array( 'Producer', 'Mixing', 'Mastering', 'Engineer', 'Composer', 'Keyboard', 'Synthesizer' );
}

/**
 * Collect the distinct credited roles across all entries, ordered by the
 * preferred sequence then any extras in first-encounter order.
 *
 * @param array<int,array<string,mixed>> $entries Normalized entries.
 * @return string[] Distinct roles, display-ordered.
 */
function sn_discography_collect_roles( $entries ) {
	$seen = array();
	foreach ( $entries as $entry ) {
		$roles = isset( $entry['roles'] ) && is_array( $entry['roles'] ) ? $entry['roles'] : array();
		foreach ( $roles as $role ) {
			$role = trim( (string) $role );
			if ( '' !== $role && ! in_array( $role, $seen, true ) ) {
				$seen[] = $role;
			}
		}
	}
	$ordered = array();
	foreach ( sn_discography_role_order() as $pref ) {
		if ( in_array( $pref, $seen, true ) ) {
			$ordered[] = $pref;
		}
	}
	foreach ( $seen as $role ) {
		if ( ! in_array( $role, $ordered, true ) ) {
			$ordered[] = $role;
		}
	}
	return $ordered;
}

/**
 * [sn_discography] — cover-grid gallery for the /music page.
 *
 * Reads the cached entries off the standalone-safe filter and renders them
 * grouped by release year (descending). Returns '' when there are no entries
 * (plugin absent, or store not yet synced) so placing the shortcode is safe.
 *
 * @return string Gallery HTML, or '' when there are no entries.
 */
function sn_discography_shortcode() {
	$entries = apply_filters( 'sn_discography_entries', array() );
	if ( ! is_array( $entries ) || empty( $entries ) ) {
		return '';
	}

	// Group by year (entries arrive sorted year-desc; re-group defensively).
	$by_year = array();
	$years   = array();
	foreach ( $entries as $entry ) {
		if ( ! is_array( $entry ) ) {
			continue;
		}
		$year               = (int) ( $entry['year'] ?? 0 );
		$by_year[ $year ][] = $entry;
		if ( $year > 0 ) {
			$years[] = $year;
		}
	}
	if ( empty( $by_year ) ) {
		return '';
	}
	krsort( $by_year, SORT_NUMERIC );

	$count = count( $entries );
	$roles = sn_discography_collect_roles( $entries );

	$out = '<div class="sn-discography">';

	// ── Controls rail: count + role-filter chips. ──
	$out .= '<div class="sn-disco-controls">';
	$out .= '<p class="sn-disco-count"><strong data-disco-count>' . (int) $count . '</strong> '
		. esc_html__( 'releases', 'signal-noise' );
	if ( ! empty( $years ) ) {
		$out .= ' <span class="sn-disco-span">&middot; ' . (int) min( $years ) . ' &rarr; ' . (int) max( $years ) . '</span>';
	}
	$out .= '</p>';

	$out .= '<div class="sn-disco-filters" role="group" aria-label="' . esc_attr( 'Filter by role' ) . '">';
	$out .= '<button type="button" class="sn-disco-chip is-active" data-role="*" aria-pressed="true">' . esc_html__( 'All', 'signal-noise' ) . '</button>';
	foreach ( $roles as $role ) {
		$out .= '<button type="button" class="sn-disco-chip" data-role="' . esc_attr( $role ) . '" aria-pressed="false">' . esc_html( $role ) . '</button>';
	}
	$out .= '</div>'; // .sn-disco-filters
	$out .= '</div>'; // .sn-disco-controls

	// ── Year-grouped cover grid. ──
	foreach ( $by_year as $year => $year_entries ) {
		$year_label = $year > 0 ? (string) $year : '—';
		$n          = count( $year_entries );

		$out .= '<section class="sn-disco-year" data-year="' . esc_attr( (string) $year ) . '">';
		$out .= '<h2 class="sn-disco-year__label">' . esc_html( $year_label )
			. ' <span class="sn-disco-year__count">' . (int) $n . ' '
			. esc_html( 1 === $n ? 'release' : 'releases' ) . '</span></h2>';
		$out .= '<div class="sn-disco-grid">';
		foreach ( $year_entries as $entry ) {
			$out .= sn_discography_render_card( $entry );
		}
		$out .= '</div>'; // .sn-disco-grid
		$out .= '</section>';
	}

	// Hidden empty state — revealed by the filter JS when nothing matches.
	$out .= '<p class="sn-disco-empty" hidden>' . esc_html__( 'No releases with that credit.', 'signal-noise' ) . '</p>';

	$out .= '</div>'; // .sn-discography

	return $out;
}

/**
 * Render one normalized entry as a cover card.
 *
 * The whole cover is the play affordance when a Spotify id is present (a
 * keyboard-operable button the JS swaps for the embed); a Muso-only entry (no
 * Spotify match) renders a static cover with no play badge. Every external-data
 * field is escaped here.
 *
 * @param array<string,mixed> $entry One normalized store entry.
 * @return string Card HTML (one <article>).
 */
function sn_discography_render_card( $entry ) {
	$title      = (string) ( $entry['title'] ?? '' );
	$artist     = (string) ( $entry['artist'] ?? '' );
	$image      = (string) ( $entry['image'] ?? '' );
	$spotify_id = (string) ( $entry['spotify_id'] ?? '' );
	$type       = (string) ( $entry['type'] ?? 'album' );
	$muso_url   = (string) ( $entry['muso_url'] ?? '' );
	$roles      = isset( $entry['roles'] ) && is_array( $entry['roles'] )
		? array_values( array_filter( array_map( 'strval', $entry['roles'] ) ) )
		: array();
	$playable   = ( '' !== $spotify_id );

	$out = '<article class="sn-disco-card" data-roles="' . esc_attr( implode( '|', $roles ) ) . '"'
		. ' data-spotify="' . esc_attr( $spotify_id ) . '" data-type="' . esc_attr( $type ) . '">';

	// ── Cover (the play affordance when playable). ──
	if ( $playable ) {
		$out .= '<div class="sn-disco-cover-wrap" role="button" tabindex="0" aria-label="'
			. esc_attr( 'Play ' . $title ) . '">';
	} else {
		$out .= '<div class="sn-disco-cover-wrap sn-disco-cover-wrap--static">';
	}
	if ( '' !== $image ) {
		$out .= '<img class="sn-disco-art" loading="lazy" decoding="async" src="' . esc_url( $image )
			. '" alt="' . esc_attr( $title ) . '" width="300" height="300" />';
	} else {
		$out .= '<span class="sn-disco-art sn-disco-art--none" aria-hidden="true"></span>';
	}
	if ( $playable ) {
		$out .= '<span class="sn-disco-play-badge" aria-hidden="true"><span class="sn-disco-play-circle"></span></span>';
	}
	$out .= '</div>'; // .sn-disco-cover-wrap

	// ── Meta. ──
	$out .= '<div class="sn-disco-meta">';
	$out .= '<h3 class="sn-disco-title">' . esc_html( $title ) . '</h3>';
	if ( '' !== $artist ) {
		$out .= '<p class="sn-disco-artist">' . esc_html( $artist ) . '</p>';
	}
	if ( ! empty( $roles ) ) {
		$out .= '<p class="sn-disco-roles">' . esc_html( implode( ' · ', $roles ) ) . '</p>';
	}
	if ( '' !== $muso_url ) {
		$out .= '<a class="sn-disco-credits" href="' . esc_url( $muso_url ) . '" target="_blank" rel="noopener">'
			. esc_html__( 'Credits', 'signal-noise' ) . ' &#8599;</a>';
	}
	$out .= '</div>'; // .sn-disco-meta

	$out .= '</article>';

	return $out;
}

add_shortcode( 'sn_discography', 'sn_discography_shortcode' );
