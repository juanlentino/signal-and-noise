<?php
/**
 * /notes index helpers: the manifest row (provenance version, tags).
 *
 * Moved verbatim out of inc/notes-index-helpers.php, which loads it.
 *
 * @package SignalNoise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ─────────────────────────────────────────────────────────────────────
 * v11.10.0 — the index reads as a MANIFEST, not a feed.
 *
 * Row differentiators are editorial only: reading time, tags, and the
 * provenance version. Traffic and decay data are deliberately absent —
 * publishing per-note performance would cut against the ML kernel's
 * refusals, and a reader choosing what to read next is owed the argument,
 * not the numbers.
 * ───────────────────────────────────────────────────────────────────── */

/**
 * The Note's provenance version: how many times its prose has been
 * substantively revised AND signed.
 *
 * Reads the companion plugin's `_sn_prov_chain` meta by its literal key —
 * the established theme↔plugin seam (precedent: the `_sn_prov_uid` twin in
 * inc/note-uid.php). Absent plugin, absent meta, or a malformed chain all
 * return 0, so the badge simply does not render.
 *
 * @param int $post_id
 * @return int Highest version in the chain, 0 when there is none.
 */
function sn_notes_prov_version( $post_id ) {
	if ( ! function_exists( 'get_post_meta' ) ) {
		return 0;
	}
	$chain = get_post_meta( (int) $post_id, '_sn_prov_chain', true );
	if ( ! is_array( $chain ) ) {
		return 0;
	}
	$max = 0;
	foreach ( $chain as $entry ) {
		if ( is_array( $entry ) && (int) ( $entry['version'] ?? 0 ) > $max ) {
			$max = (int) $entry['version'];
		}
	}
	return $max;
}

/**
 * Whether to show a provenance badge at all.
 *
 * v1 means "published once, signed once" — true of nearly every Note, so
 * rendering it 30 times says nothing and costs a column. The badge earns its
 * place only from v2, where it reports something the date cannot: this
 * argument was revisited, and the revision was signed.
 *
 * @param int $version
 * @return bool
 */
function sn_notes_prov_version_is_notable( $version ) {
	return (int) $version >= 2;
}

/**
 * Up to $limit tags for a row, as {name, url} pairs.
 *
 * Tags are the only cross-cutting structure the corpus actually has (the
 * TF-IDF topic partition covers 4 of 33 Notes, so it cannot carry a spine),
 * which makes them the row's most useful non-chronological signal.
 *
 * @param int $post_id
 * @param int $limit
 * @return array<int,array{name:string,url:string}>
 */
function sn_notes_row_tags( $post_id, $limit = 2 ) {
	if ( ! function_exists( 'get_the_tags' ) ) {
		return array();
	}
	$tags = get_the_tags( (int) $post_id );
	if ( ! is_array( $tags ) ) {
		return array();
	}
	$out = array();
	foreach ( $tags as $tag ) {
		if ( count( $out ) >= (int) $limit ) {
			break;
		}
		$link  = function_exists( 'get_term_link' ) ? get_term_link( $tag ) : '';
		$out[] = array(
			'name' => (string) $tag->name,
			'url'  => is_string( $link ) ? $link : '',
		);
	}
	return $out;
}
