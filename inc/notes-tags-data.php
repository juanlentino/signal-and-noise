<?php
/**
 * Editorial grouping for the /notes/tags/ index.
 *
 * The vocabulary is 25 tags and every one of them carries an owner-written
 * description (term description), which is what makes a glossary possible here
 * and a tag cloud unnecessary: the page can say what a tag IS rather than how
 * big it is. Counts are deliberately absent — the thin tags are the specific
 * ones, and a count next to them reads as "not worth clicking".
 *
 * Slugs, not names: a term rename must not silently drop a tag out of its
 * group. A slug that no longer resolves is skipped by the renderer, and any
 * in-use tag named in NO group falls through to the trailing group rather than
 * vanishing — see sn_notes_tag_groups_resolved().
 *
 * @package Signal_And_Noise
 * @since   12.15.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * The four editorial groups, in render order.
 *
 * @since 12.15.0
 * 13.4.0: each group carries an `id`, the value a tag's `sn_tag_group` term
 * meta names. The slug lists below are now the SEED: a tag with meta files
 * where the meta says, a tag without meta files where the list says, and a
 * tag in neither falls through to "Not yet filed". Filing a new tag is one
 * edit on Posts › Tags (inc/notes-tags-group-meta.php), no release.
 *
 * @return array<int, array{id:string, title:string, dek:string, slugs:array<int,string>}>
 */
function sn_notes_tag_groups() {
	return array(
		array(
			'id'    => 'record',
			'title' => 'The record',
			'dek'   => 'The pieces a record is built from, and the standards that let anyone read it.',
			'slugs' => array(
				'creation-time-capture',
				'cryptographic-signatures',
				'content-authenticity',
				'c2pa',
				'digital-identity',
				'standards',
			),
		),
		array(
			'id'    => 'settles',
			'title' => 'What it settles',
			'dek'   => 'Who made a thing, how anyone would know, and where each answer runs out.',
			'slugs' => array(
				'verification-limits',
				'authorship',
				'artist-verification',
				'ai-detection',
				'ai-disclosure',
			),
		),
		array(
			'id'    => 'built',
			'title' => 'Why it isn&rsquo;t built',
			'dek'   => 'Money, incentives, and the institutions that would have to move first.',
			'slugs' => array(
				'provenance-adoption',
				'music-industry',
				'music-distribution',
				'music-metadata',
				'music-rights',
				'music-royalties',
				'black-box-royalties',
				'ai-training',
				'fair-use', // 13.3.3: the defense the training cases turn on, beside the rights and the money.
				'legacy-catalog',
			),
		),
		array(
			'id'    => 'work',
			'title' => 'The work',
			'dek'   => 'Where the argument comes from: the studio, the business, the writing.',
			'slugs' => array(
				'music-production',
				'ai-music',
				'independent-artists',
				'freelance-business',
				'writing',
			),
		),
	);
}

/**
 * Resolve the groups against the live vocabulary.
 *
 * Two failure modes this exists to make impossible:
 *
 * 1. A slug in sn_notes_tag_groups() that no longer resolves (renamed, pruned)
 *    is skipped rather than emitting an empty row.
 * 2. An in-use tag named in NO group still renders, in a trailing group. A
 *    hardcoded grouping drifts the moment a tag is added, and the failure mode
 *    of a bare list is SILENT — the tag simply is not on the page and nothing
 *    reports it. Falling through is loud by comparison: the tag shows up under
 *    a heading that reads as unfinished, which is exactly the prompt to file it.
 *
 * Only tags with published posts appear (`hide_empty`), so a term sitting at
 * zero because its notes are still scheduled is not advertised as a dead end.
 *
 * @since 12.15.0
 * @return array<int, array{title:string, dek:string, terms:array<int,WP_Term>}>
 */
function sn_notes_tag_groups_resolved() {
	$all = get_terms(
		array(
			'taxonomy'   => 'post_tag',
			'hide_empty' => true,
		)
	);
	if ( is_wp_error( $all ) || ! is_array( $all ) ) {
		return array();
	}

	$by_slug = array();
	foreach ( $all as $term ) {
		$by_slug[ $term->slug ] = $term;
	}

	$out    = array();
	$placed = array();
	// 13.4.0: the term's own meta files it first; the seed list second.
	$filed = array();
	foreach ( $all as $term ) {
		$gid = sn_notes_tag_group_of( $term );
		if ( '' !== $gid ) {
			$filed[ $gid ][] = $term->slug;
		}
	}
	foreach ( sn_notes_tag_groups() as $group ) {
		$terms = array();
		foreach ( array_unique( array_merge( $filed[ $group['id'] ] ?? array(), $group['slugs'] ) ) as $slug ) {
			if ( isset( $by_slug[ $slug ] ) && ! isset( $placed[ $slug ] ) ) {
				$terms[]        = $by_slug[ $slug ];
				$placed[ $slug ] = true;
			}
		}
		if ( array() !== $terms ) {
			$out[] = array(
				'title' => $group['title'],
				'dek'   => $group['dek'],
				'terms' => $terms,
			);
		}
	}

	$rest = array();
	foreach ( $all as $term ) {
		if ( ! isset( $placed[ $term->slug ] ) ) {
			$rest[] = $term;
		}
	}
	if ( array() !== $rest ) {
		$out[] = array(
			'title' => 'Not yet filed',
			'dek'   => 'Newer tags that have not been placed in a group yet.',
			'terms' => $rest,
		);
	}

	return $out;
}

/** The term meta that files a tag: one of the group ids, or '' for unfiled. */
const SN_TAG_GROUP_META = 'sn_tag_group';

/**
 * The group ids, in render order.
 *
 * @since 13.4.0
 * @return string[]
 */
function sn_notes_tag_group_ids() {
	return array_column( sn_notes_tag_groups(), 'id' );
}

/**
 * The group a term's meta names, or '' when unset or not a known id. The
 * meta is the owner's filing; the seed list in sn_notes_tag_groups() is only
 * consulted when this is ''.
 *
 * @since 13.4.0
 * @param WP_Term|object $term
 * @return string
 */
function sn_notes_tag_group_of( $term ) {
	$gid = function_exists( 'get_term_meta' ) ? (string) get_term_meta( (int) $term->term_id, SN_TAG_GROUP_META, true ) : '';
	return in_array( $gid, sn_notes_tag_group_ids(), true ) ? $gid : '';
}

/**
 * The group a term renders under today, meta or seed, '' for "Not yet filed".
 * What the tag screen's select shows as selected.
 *
 * @since 13.4.0
 * @param WP_Term|object $term
 * @return string
 */
function sn_notes_tag_group_effective( $term ) {
	$gid = sn_notes_tag_group_of( $term );
	if ( '' !== $gid ) {
		return $gid;
	}
	foreach ( sn_notes_tag_groups() as $group ) {
		if ( in_array( $term->slug, $group['slugs'], true ) ) {
			return $group['id'];
		}
	}
	return '';
}
