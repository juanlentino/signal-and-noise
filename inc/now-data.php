<?php
/**
 * Signal & Noise — /now page data.
 *
 * THIS FILE IS THE EDIT SURFACE: /now updates are a one-array edit here +
 * a release (theme pages are files — the accepted maintenance model). Keep
 * sn_now_updated() honest on every content edit; the page renders it so
 * staleness is visible, not hidden.
 *
 * sn_now_sections() applies the `sn_now_sections` filter and normalizes,
 * mirroring inc/uses-data.php's plugin seam.
 *
 * @package SignalNoise
 * @since 10.21.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The date the /now content was last edited (rendered on the page).
 *
 * @return string YYYY-MM-DD.
 */
function sn_now_updated() {
	return '2026-07-01';
}

/**
 * The seeded /now list. Each section: { label, items: [ string ] }.
 * OWNER GATE (spec 2026-07-01): the owner edits the Listening + Reading
 * placeholders (and anything else) before this release merges.
 *
 * @return array<int,array{label:string,items:array<int,string>}>
 */
function sn_now_data() {
	return array(
		array(
			'label' => 'Building',
			'items' => array(
				'Signal & Noise — this site\'s theme and companion plugin, designed and shipped in public.',
				'The provenance sub-pillar series; the first extension note launches in July.',
			),
		),
		array(
			'label' => 'Writing & speaking',
			'items' => array(
				'Preparing a talk on provenance and writing in the AI era.',
			),
		),
		array(
			'label' => 'Listening',
			'items' => array(
				'OWNER-EDIT before merge: current rotation.',
			),
		),
		array(
			'label' => 'Reading',
			'items' => array(
				'OWNER-EDIT before merge: current reading.',
			),
		),
	);
}

/**
 * Normalized, filtered sections. Items normalize to bare strings (a
 * {text: ...} array item is accepted); empty items and label-less / empty
 * sections are pruned. Hostile filter input → [].
 *
 * @return array<int,array{label:string,items:array<int,string>}>
 */
function sn_now_sections() {
	$raw = apply_filters( 'sn_now_sections', sn_now_data() );
	if ( ! is_array( $raw ) ) {
		return array();
	}
	$sections = array();
	foreach ( $raw as $section ) {
		if ( ! is_array( $section ) ) {
			continue;
		}
		$label     = trim( (string) ( $section['label'] ?? '' ) );
		$items_raw = ( isset( $section['items'] ) && is_array( $section['items'] ) ) ? $section['items'] : array();
		$items     = array();
		foreach ( $items_raw as $item ) {
			$text = trim( (string) ( is_array( $item ) ? ( $item['text'] ?? '' ) : $item ) );
			if ( '' !== $text ) {
				$items[] = $text;
			}
		}
		if ( '' === $label || empty( $items ) ) {
			continue;
		}
		$sections[] = array( 'label' => $label, 'items' => $items );
	}
	return $sections;
}
