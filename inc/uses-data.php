<?php
/**
 * Signal & Noise — /uses gear data (D6).
 *
 * The structured kit list rendered by the /uses page (inc/page-uses-render.php).
 * THIS FILE IS THE EDIT SURFACE: to add, remove, or re-note a piece of gear,
 * edit the array in sn_uses_data() — one line per item, grouped by category.
 *
 * sn_uses_groups() applies the `sn_uses_groups` filter over this data and
 * normalizes the result, so the companion plugin (or any add_filter) can supply
 * the list later WITHOUT a theme change — the deferred-admin-UI seam. Pure: the
 * only WP dependency is apply_filters, so it is trivially unit-testable.
 *
 * @package SignalNoise
 * @since 10.10.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The seeded gear list. Each group: { label, items: [ { name, note } ] }.
 * `note` is optional (a short qualifier); leave it '' for none.
 *
 * @return array<int,array{label:string,items:array<int,array{name:string,note:string}>}>
 */
function sn_uses_data() {
	return array(
		array(
			'label' => 'Interface & control',
			'items' => array(
				array( 'name' => 'Universal Audio Apollo Twin X DUO — Heritage Edition', 'note' => 'Custom 10 plug-in upgrade' ),
				array( 'name' => 'SSL UF8', 'note' => 'Advanced DAW controller' ),
			),
		),
		array(
			'label' => 'Microphones',
			'items' => array(
				array( 'name' => 'Antelope Audio Edge Duo', 'note' => 'Modeling microphone' ),
				array( 'name' => 'K&M 210/9', 'note' => 'Microphone stand' ),
			),
		),
		array(
			'label' => 'Headphones',
			'items' => array(
				array( 'name' => 'Audeze LCD-X', 'note' => 'Creator Package' ),
				array( 'name' => 'Sony MDR-7506', 'note' => '' ),
			),
		),
		array(
			'label' => 'Instruments & keys',
			'items' => array(
				array( 'name' => 'Gretsch G5622', 'note' => '' ),
				array( 'name' => 'MotorAve Bel-Air', 'note' => '' ),
				array( 'name' => 'Arturia MiniFreak', 'note' => '' ),
				array( 'name' => 'Arturia KeyLab 49 mkII', 'note' => '' ),
			),
		),
		array(
			'label' => 'Software & licensing',
			'items' => array(
				array( 'name' => 'Avid Pace iLok3', 'note' => '' ),
			),
		),
	);
}

/**
 * The normalized, filtered gear groups. Applies the `sn_uses_groups` override
 * hook, then prunes anything malformed: a group needs a non-empty label AND at
 * least one named item; a bare-string item becomes { name, note:'' }; nameless
 * items are dropped. Always returns a clean array (or [] for hostile input).
 *
 * @return array<int,array{label:string,items:array<int,array{name:string,note:string}>}>
 */
function sn_uses_groups() {
	$raw = apply_filters( 'sn_uses_groups', sn_uses_data() );
	if ( ! is_array( $raw ) ) {
		return array();
	}
	$groups = array();
	foreach ( $raw as $group ) {
		if ( ! is_array( $group ) ) {
			continue;
		}
		$label     = trim( (string) ( $group['label'] ?? '' ) );
		$items_raw = ( isset( $group['items'] ) && is_array( $group['items'] ) ) ? $group['items'] : array();
		$items     = array();
		foreach ( $items_raw as $item ) {
			if ( is_string( $item ) ) {
				$item = array( 'name' => $item );
			}
			if ( ! is_array( $item ) ) {
				continue;
			}
			$name = trim( (string) ( $item['name'] ?? '' ) );
			if ( '' === $name ) {
				continue;
			}
			$items[] = array( 'name' => $name, 'note' => trim( (string) ( $item['note'] ?? '' ) ) );
		}
		if ( '' === $label || empty( $items ) ) {
			continue;
		}
		$groups[] = array( 'label' => $label, 'items' => $items );
	}
	return $groups;
}

/**
 * Total number of gear items across all groups (the hero count).
 *
 * @return int
 */
function sn_uses_item_count() {
	$n = 0;
	foreach ( sn_uses_groups() as $group ) {
		$n += count( $group['items'] );
	}
	return $n;
}
